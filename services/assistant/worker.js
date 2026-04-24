import { AssistantBrowserController } from './browser-controller.js';
import { AssistantTaskStore } from './lib/task-store.js';
import { AssistantRealtimeHub } from './realtime-hub.js';

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, Math.max(0, Number(ms) || 0)));
const nowIso = () => new Date().toISOString();
const safeText = (value) => String(value ?? '').trim();

const pollDelayMs = Math.max(1000, Number(process.env.ASSISTANT_WORKER_POLL_MS || 3500));
const workerId = safeText(process.env.ASSISTANT_WORKER_ID || `assistant-worker-${process.pid}`);

const store = new AssistantTaskStore();
const realtimeHub = new AssistantRealtimeHub();
const browser = new AssistantBrowserController({
  store,
  appUrl: process.env.ASSISTANT_APP_URL || 'http://localhost:3000',
  headless: process.env.ASSISTANT_HEADLESS,
  events: realtimeHub,
});

let shuttingDown = false;
let browserReady = false;

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

  let browserStarted = false;
  try {
    await ensureBrowser();
    browserStarted = true;
    const completed = await browser.executeTask(task, { workerId });
    const status = safeText(completed?.status || 'completed');
    log(`Finished task ${task.id} with status "${status}"`);
  } catch (error) {
    const message = error instanceof Error ? error.message : safeText(error);
    log(`Task ${task.id} failed: ${message}`);
    if (!browserStarted) {
      try {
        await store.failTask(task.id, error, {
          worker_id: workerId,
          stage: 'browser_launch',
          duration_ms: 0,
        });
      } catch (_storeError) {
      }
    }
    try {
      await store.recordExecutionLog({
        task_id: task.id,
        user_id: task.user_id,
        action: 'worker_error',
        status: 'failed',
        duration_ms: 0,
        message,
      });
    } catch (_logError) {
    }
  }

  return true;
};

const main = async () => {
  await store.ensure();
  await realtimeHub.start();
  log('Store ready. Poll interval:', `${pollDelayMs}ms`);
  log(`Realtime hub listening on ws://${realtimeHub.host}:${realtimeHub.port}${realtimeHub.path}`);

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
