import fs from 'node:fs/promises';
import path from 'node:path';
import { AssistantBrowserController } from './browser-controller.js';
import { AssistantTaskStore } from './lib/task-store.js';
import { AssistantRealtimeHub } from './realtime-hub.js';

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, Math.max(0, Number(ms) || 0)));
const nowIso = () => new Date().toISOString();
const safeText = (value) => String(value ?? '').trim();
const parseBoolean = (value, defaultValue = false) => {
  const normalized = safeText(value).toLowerCase();
  if (!normalized) {
    return Boolean(defaultValue);
  }
  return ['1', 'true', 'yes', 'on'].includes(normalized);
};

const pollDelayMs = Math.max(1000, Number(process.env.ASSISTANT_WORKER_POLL_MS || 3500));
const workerId = safeText(process.env.ASSISTANT_WORKER_ID || `assistant-worker-${process.pid}`);
const maxExecutionAttempts = Math.max(1, Number(process.env.ASSISTANT_WORKER_MAX_ATTEMPTS || 3));
const retryDelayMs = Math.max(500, Number(process.env.ASSISTANT_WORKER_RETRY_DELAY_MS || 1200));

const store = new AssistantTaskStore();
const realtimeHub = new AssistantRealtimeHub();
const workerLockPath = path.join(store.storageDir, 'worker.lock');
const browser = new AssistantBrowserController({
  store,
  appUrl: process.env.ASSISTANT_APP_URL || process.env.APP_URL || 'http://localhost:3000',
  headless: parseBoolean(process.env.ASSISTANT_HEADLESS, false),
  events: realtimeHub,
});

let shuttingDown = false;
let browserReady = false;
let workerLockHandle = null;
let realtimeHubActive = false;

const log = (...parts) => {
  console.log(`[assistant-worker ${nowIso()}]`, ...parts);
};

const failSafeClose = async () => {
  try {
    await browser.close();
  } catch (_error) {
  }
  try {
    await realtimeHub.close();
  } catch (_error) {
  }
  try {
    if (workerLockHandle) {
      await workerLockHandle.close();
      workerLockHandle = null;
    }
  } catch (_error) {
  }
  try {
    await fs.unlink(workerLockPath);
  } catch (_error) {
  }
};

const isProcessAlive = (pid) => {
  const numericPid = Number(pid);
  if (!Number.isFinite(numericPid) || numericPid <= 0) {
    return false;
  }

  try {
    process.kill(numericPid, 0);
    return true;
  } catch (error) {
    return !(error && typeof error === 'object' && error.code === 'ESRCH');
  }
};

const acquireWorkerLock = async () => {
  await fs.mkdir(store.storageDir, { recursive: true });

  try {
    workerLockHandle = await fs.open(workerLockPath, 'wx');
  } catch (error) {
    if (!error || typeof error !== 'object' || error.code !== 'EEXIST') {
      throw error;
    }

    try {
      const raw = await fs.readFile(workerLockPath, 'utf8');
      const existingPid = Number(JSON.parse(raw || '{}')?.pid || 0);
      if (isProcessAlive(existingPid)) {
        return false;
      }
    } catch (_readError) {
    }

    try {
      await fs.unlink(workerLockPath);
    } catch (_unlinkError) {
    }

    workerLockHandle = await fs.open(workerLockPath, 'wx');
  }

  await workerLockHandle.writeFile(JSON.stringify({
    pid: process.pid,
    started_at: nowIso(),
  }, null, 2), 'utf8');

  return true;
};

const handleSignal = async (signal) => {
  if (shuttingDown) {
    return;
  }

  shuttingDown = true;
  log(`Received ${signal}, shutting down...`);
  await failSafeClose();
  process.exit(0);
};

process.on('SIGINT', () => {
  void handleSignal('SIGINT');
});
process.on('SIGTERM', () => {
  void handleSignal('SIGTERM');
});
process.on('unhandledRejection', (error) => {
  console.error('[assistant-worker] Unhandled rejection:', error);
});
process.on('uncaughtException', (error) => {
  console.error('[assistant-worker] Uncaught exception:', error);
});

const ensureBrowser = async () => {
  if (browserReady) {
    return;
  }

  await browser.launch();
  browserReady = true;
};

const processNextTask = async () => {
  const task = await store.claimNextTask(workerId);
  if (!task) {
    return false;
  }

  log(`Claimed task ${task.id} for ${task.source || 'web'} command: "${task.message}"`);

  const startedAt = Date.now();
  let lastError = null;

  for (let attempt = 1; attempt <= maxExecutionAttempts; attempt += 1) {
    let browserStarted = false;

    try {
      await ensureBrowser();
      browserStarted = true;

      const completed = await browser.executeTask(task, { workerId });
      const verified = await store.getTask(task.id, 0);
      const status = safeText(verified?.status || completed?.status || 'completed');
      if (status !== 'completed') {
        throw new Error(`Execution verification failed for task ${task.id}.`);
      }
      if (verified?.result && verified.result.success === false) {
        throw new Error(`Execution verification reported a failure for task ${task.id}.`);
      }

      await store.recordExecutionLog({
        task_id: task.id,
        user_id: task.user_id,
        action: 'verify',
        status: 'completed',
        duration_ms: Date.now() - startedAt,
        worker_id: workerId,
        attempt,
      });

      log(`Finished task ${task.id} with status "${status}" after attempt ${attempt}`);
      return true;
    } catch (error) {
      lastError = error instanceof Error ? error : new Error(safeText(error));
      log(`Task ${task.id} attempt ${attempt}/${maxExecutionAttempts} failed: ${lastError.message}`);

      try {
        await browser.close();
      } catch (_closeError) {
      }
      browserReady = false;

      if (attempt < maxExecutionAttempts) {
        try {
          await store.updateTask(task.id, (draft) => {
            draft.status = 'queued';
            draft.error = null;
            draft.result = null;
            draft.finished_at = null;
            draft.started_at = null;
            draft.claimed_by = null;
            draft.worker_id = null;
            draft.plan = {
              ...(draft.plan || {}),
              steps: Array.isArray(draft.plan?.steps)
                ? draft.plan.steps.map((step) => ({
                    ...step,
                    status: 'queued',
                  }))
                : [],
            };
            draft.events = Array.isArray(draft.events) ? draft.events : [];
            draft.events.push(store.buildEvent('queued', `Retrying execution after attempt ${attempt} failed.`, 'retry', {
              worker_id: workerId,
              attempt,
              max_attempts: maxExecutionAttempts,
              message: lastError.message,
            }));
            draft.events = draft.events.slice(-20);
            draft.updated_at = nowIso();
          });
        } catch (_storeError) {
        }

        await sleep(Math.min(5000, retryDelayMs * attempt));
        continue;
      }

      try {
        await store.failTask(task.id, lastError, {
          worker_id: workerId,
          stage: browserStarted ? 'execute' : 'browser_launch',
          duration_ms: Date.now() - startedAt,
          attempts: maxExecutionAttempts,
        });
      } catch (_storeError) {
      }

      try {
        await store.recordExecutionLog({
          task_id: task.id,
          user_id: task.user_id,
          action: 'worker_error',
          status: 'failed',
          duration_ms: Date.now() - startedAt,
          message: lastError.message,
          worker_id: workerId,
          attempts: maxExecutionAttempts,
        });
      } catch (_logError) {
      }

      return true;
    }
  }

  return true;
};

const main = async () => {
  await store.ensure();
  const hasLock = await acquireWorkerLock();
  if (!hasLock) {
    log(`Another assistant worker is already running. Exiting duplicate worker ${process.pid}.`);
    await failSafeClose();
    process.exit(0);
  }

  try {
    await realtimeHub.start();
    realtimeHubActive = true;
  } catch (error) {
    if (error && typeof error === 'object' && error.code === 'EADDRINUSE') {
      log(`Realtime hub already running on ${realtimeHub.host}:${realtimeHub.port}. Continuing without realtime events.`);
    } else {
      throw error;
    }
  }
  log('Store ready. Poll interval:', `${pollDelayMs}ms`);
  if (realtimeHubActive) {
    log(`Realtime hub listening on ws://${realtimeHub.host}:${realtimeHub.port}${realtimeHub.path}`);
  }

  while (!shuttingDown) {
    const ranTask = await processNextTask();
    if (!ranTask) {
      await sleep(pollDelayMs);
    }
  }
};

main().catch(async (error) => {
  console.error('[assistant-worker] Fatal error:', error);
  await failSafeClose();
  process.exit(1);
});
