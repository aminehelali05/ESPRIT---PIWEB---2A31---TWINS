import fs from 'node:fs/promises';
import net from 'node:net';
import { spawn } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import whatsappWeb from 'whatsapp-web.js';
import qrcodeTerminal from 'qrcode-terminal';
import { AssistantTaskStore } from './lib/task-store.js';
import { resolveChromeExecutablePath } from './lib/browser-path.js';

const { Client, LocalAuth } = whatsappWeb;

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
const normalizeChatId = (value) => {
  const text = safeText(value);
  if (!text) {
    return '';
  }

  if (text.endsWith('@c.us') || text.endsWith('@g.us')) {
    return text;
  }

  const digits = text.replace(/\D+/g, '');
  return digits ? `${digits}@c.us` : text;
};

const moduleDir = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(moduleDir, '..', '..');

const appUrl = safeText(process.env.ASSISTANT_APP_URL || process.env.APP_URL || 'http://localhost:3000').replace(/\/+$/, '');
const backendUrl = safeText(process.env.ASSISTANT_BACKEND_URL || new URL('index.php?action=assistant_command', `${appUrl}/`).toString());
const pollDelayMs = Math.max(3000, Number(process.env.ASSISTANT_WHATSAPP_POLL_MS || 5000));
const headless = parseBoolean(process.env.ASSISTANT_WHATSAPP_HEADLESS, false);
const clientId = safeText(process.env.ASSISTANT_WHATSAPP_CLIENT_ID || 'diversity-assistant');
const backendTimeoutMs = Math.max(5000, Number(process.env.ASSISTANT_BACKEND_TIMEOUT_MS || 20000));
const backendRetryCount = Math.max(1, Number(process.env.ASSISTANT_BACKEND_RETRIES || 2));
const backendRetryDelayMs = Math.max(250, Number(process.env.ASSISTANT_BACKEND_RETRY_DELAY_MS || 800));
const assistantWorkerHost = safeText(process.env.ASSISTANT_WS_HOST || '127.0.0.1');
const assistantWorkerPort = Math.max(1, Number(process.env.ASSISTANT_WS_PORT || 8787));
const assistantWorkerScript = path.join(moduleDir, 'worker.js');
const assistantWorkerLockPath = path.join(repoRoot, 'storage', 'assistant', 'worker.lock');

const store = new AssistantTaskStore();

let pollTimer = null;
let ready = false;
let workerProcess = null;
let workerBootPromise = null;
const sentMessageIds = new Set();
const sentMessageBodies = new Set();
const handledIncomingMessageIds = new Set();

const executionQueue = [];
let isExecuting = false;
const messageDebounce = new Map();

// --- WHATSAPP AI AGENT COMMAND SYSTEM ---
const ALLOWED_COMMANDS = [
  { intent: 'OPEN_PROFILE', regex: /open\s+profile/i, action: 'Navigate to profile page', response: 'Profile page opened successfully' },
  { intent: 'OPEN_MARKETPLACE', regex: /open\s+marketplace/i, action: 'Navigate to marketplace page', response: 'Marketplace opened' },
  { intent: 'AI_CONTRACT_ANALYZE', regex: /analyze\s+contract/i, action: 'Analyze contract', response: 'AI analysis returned' },
  { intent: 'JOB_MATCHING', regex: /find\s+(best\s+)?jobs/i, action: 'Trigger OpenRouter job matching', response: 'Top jobs found' }
];

const userRateLimiter = new Map();
const RATE_LIMIT_WINDOW_MS = 5000;
// ----------------------------------------


const log = (...parts) => {
  console.log(`[assistant-whatsapp ${nowIso()}]`, ...parts);
};

const trackSentMessage = async (clientInstance, chatId, body, options = {}) => {
  const sent = await clientInstance.sendMessage(chatId, body, options);
  const messageId = safeText(sent?.id?.id || sent?.id?._serialized || sent?.id || '');
  if (messageId) {
    sentMessageIds.add(messageId);
  }

  const normalizedBody = safeText(body);
  if (normalizedBody) {
    sentMessageBodies.add(normalizedBody);
    const timer = setTimeout(() => {
      sentMessageBodies.delete(normalizedBody);
    }, 15000);
    if (typeof timer.unref === 'function') {
      timer.unref();
    }
  }

  return sent;
};

const isAssistantSentMessage = (message) => {
  if (message?.fromMe === true) return true;
  const messageId = safeText(message?.id?.id || message?.id?._serialized || message?.id || '');
  const body = safeText(message?.body || '');
  return Boolean(
    (messageId && sentMessageIds.has(messageId)) ||
    (body && sentMessageBodies.has(body))
  );
};

const getMessageKey = (message) => safeText(message?.id?.id || message?.id?._serialized || message?.id || '');

const markHandledIncomingMessage = (message) => {
  const messageKey = getMessageKey(message);
  if (!messageKey) {
    return false;
  }

  if (handledIncomingMessageIds.has(messageKey)) {
    return true;
  }

  handledIncomingMessageIds.add(messageKey);
  const timer = setTimeout(() => handledIncomingMessageIds.delete(messageKey), 300000); // 5 minutes
  if (typeof timer.unref === 'function') {
    timer.unref();
  }

  return false;
};

const messageQueue = [];
let isProcessingQueue = false;

const processQueue = async () => {
  if (isProcessingQueue || messageQueue.length === 0) return;
  isProcessingQueue = true;
  
  try {
    while (messageQueue.length > 0) {
      const current = messageQueue.shift();
      await _handleCommandMessage(current.clientInstance, current.message, current.meta);
    }
  } finally {
    isProcessingQueue = false;
  }
};

const handleCommandMessage = async (clientInstance, message, meta = {}) => {
  // STRICTLY IGNORE own messages
  if (message.fromMe || isAssistantSentMessage(message)) {
    return;
  }

  const messageKey = getMessageKey(message);
  if (markHandledIncomingMessage(message)) {
    return;
  }

  // Add to execution queue (one command at a time)
  messageQueue.push({ clientInstance, message, meta });
  
  // Debounce system: wait a short time before processing to group rapid messages or just to prevent spam
  const chatId = safeText(message.from || '');
  if (messageDebounce.has(chatId)) {
    clearTimeout(messageDebounce.get(chatId));
  }
  
  messageDebounce.set(chatId, setTimeout(() => {
    messageDebounce.delete(chatId);
    processQueue();
  }, 1000));
};

const _handleCommandMessage = async (clientInstance, message, meta = {}) => {
  const incomingBody = safeText(message?.body || '');
  const incomingFrom = safeText(message?.from || '');
  const incomingAuthor = safeText(message?.author || '');

  if (!incomingBody) {
    return;
  }

  const messageId = safeText(message.id?.id || message.id?._serialized || '');
  
  // LOG FULL FLOW: RECEIVED
  log('FLOW: RECEIVED', {
    message_id: messageId,
    from: incomingFrom,
    author: incomingAuthor,
    body: incomingBody,
  });

  const prefixedCommand = extractCommand(incomingBody);
  const command = prefixedCommand || normalizeIncomingText(incomingBody);
  if (!command) {
    return;
  }

  const senderChatId = getSenderChatId(message);

  // 1. SECURITY RULE: Rate limit requests per user
  const nowMs = Date.now();
  const lastRequestTime = userRateLimiter.get(senderChatId) || 0;
  if (nowMs - lastRequestTime < RATE_LIMIT_WINDOW_MS) {
    log('FLOW: RATE LIMITED', { from: senderChatId, command: command });
    await trackSentMessage(clientInstance, message.from, 'Too many requests. Please wait a few seconds before trying again.');
    return;
  }
  userRateLimiter.set(senderChatId, nowMs);

  // 2. SECURITY RULE: Match command against allowed intent list (ONLY allow predefined commands)
  let matchedIntent = null;
  for (const allowed of ALLOWED_COMMANDS) {
    if (allowed.regex.test(command)) {
      matchedIntent = allowed;
      break;
    }
  }

  // 3. SECURITY RULE: Reject unknown commands safely, DO NOT execute raw code
  if (!matchedIntent) {
    log('FLOW: REJECTED UNKNOWN COMMAND', { from: senderChatId, command: command });
    await trackSentMessage(clientInstance, message.from, 'Unknown command. Allowed commands:\n- Open Profile page\n- Open Marketplace\n- Analyze contract\n- Find best jobs');
    return;
  }

  // LOG FULL FLOW: PARSED & ALLOWED
  log('FLOW: PARSED & ALLOWED', {
    message_id: messageId,
    original_command: command,
    detected_intent: matchedIntent.intent,
    timestamp: nowIso(),
    user_id: senderChatId
  });

  try {
    await startAssistantWorker();
  } catch (error) {
    log(`Worker bootstrap failed: ${error instanceof Error ? error.message : safeText(error)}`);
  }

  const context = {
    whatsapp_chat_id: safeText(message.from || ''),
    whatsapp_author: safeText(message.author || senderChatId || ''),
    whatsapp_message_id: messageId,
    whatsapp_sender_id: safeText(senderChatId || message.author || ''),
    whatsapp_group_id: safeText(message.from || '').endsWith('@g.us') ? safeText(message.from || '') : '',
    detected_intent: matchedIntent.intent
  };

  let attempts = 0;
  const maxAttempts = 2; // Retry limit (max 2)
  let lastError = null;
  let payload = null;

  while (attempts < maxAttempts) {
    attempts++;
    try {
      // LOG FULL FLOW: EXECUTING
      log(`FLOW: EXECUTING (Attempt ${attempts}/${maxAttempts})`, {
        message_id: messageId,
        action: matchedIntent.action
      });
      
      // ENSURE command always executes BEFORE replying
      // We pass the safe action from the predefined list to prevent injection
      payload = await forwardCommand(matchedIntent.action, context);
      
      // Override the response with our strict predefined response
      if (payload && !payload.assistant_message) {
        payload.assistant_message = matchedIntent.response;
      } else if (payload && matchedIntent.response) {
        payload.assistant_message = `${matchedIntent.response}\n\n${payload.assistant_message}`;
      }

      lastError = null;
      break;
    } catch (error) {
      lastError = error;
      log(`FLOW: ATTEMPT ${attempts} FAILED`, {
        message_id: messageId,
        error: error instanceof Error ? error.message : safeText(error)
      });
      if (attempts < maxAttempts) {
        await new Promise(resolve => setTimeout(resolve, 1000));
      }
    }
  }

  if (lastError) {
    const errorMessage = lastError instanceof Error ? lastError.message : safeText(lastError);
    log('FLOW: FINAL FAILURE', {
      message_id: messageId,
      error: errorMessage
    });
    try {
      await trackSentMessage(clientInstance, message.from, `Could not process the command after ${maxAttempts} attempts: ${errorMessage}`);
    } catch (_replyError) {
    }
    return; // Stop execution
  }

  try {
    const task = payload?.task || null;
    const assistantMessage = safeText(payload?.assistant_message || task?.assistant_message || task?.result?.summary || '');

    let responseText = '';
    if (task && ['completed', 'failed', 'cancelled'].includes(safeText(task.status))) {
      responseText = buildTaskSummary(task);
    } else if (task?.id) {
      responseText = assistantMessage || 'Command queued. I will send the result when it is ready.';
    } else if (assistantMessage) {
      responseText = assistantMessage;
    }

    if (responseText) {
      // LOG FULL FLOW: RESPONSE
      log('FLOW: RESPONSE', {
        message_id: messageId,
        response: responseText.substring(0, 100) + (responseText.length > 100 ? '...' : '')
      });
      await trackSentMessage(clientInstance, message.from, responseText);
      
      if (task?.id) {
          await store.markWhatsAppDelivered(task.id, {
            chat_id: context.whatsapp_chat_id,
            source: 'whatsapp',
            summary: safeText(task.result?.summary || task.assistant_message || ''),
            duration_ms: taskDurationMs(task),
          });
      }
    }
  } catch (error) {
    log('FLOW: RESPONSE ERROR', {
      message_id: messageId,
      error: error instanceof Error ? error.message : safeText(error)
    });
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

const isWorkerHubReachable = () => new Promise((resolve) => {
  const socket = net.createConnection({
    host: assistantWorkerHost,
    port: assistantWorkerPort,
  });
  let settled = false;

  const finish = (value) => {
    if (settled) {
      return;
    }
    settled = true;
    try {
      socket.destroy();
    } catch (_error) {
    }
    resolve(Boolean(value));
  };

  socket.setTimeout(1000);
  socket.once('connect', () => finish(true));
  socket.once('timeout', () => finish(false));
  socket.once('error', () => finish(false));
});

const getActiveWorkerLock = async () => {
  try {
    const raw = await fs.readFile(assistantWorkerLockPath, 'utf8');
    const decoded = JSON.parse(raw || '{}');
    const pid = Number(decoded?.pid || 0);
    if (isProcessAlive(pid)) {
      return pid;
    }

    try {
      await fs.unlink(assistantWorkerLockPath);
    } catch (_error) {
    }
  } catch (_error) {
  }

  return 0;
};

const stopAssistantWorker = async () => {
  if (!workerProcess) {
    return;
  }

  try {
    workerProcess.removeAllListeners('exit');
    workerProcess.kill();
  } catch (_error) {
  }

  workerProcess = null;
};

const startAssistantWorker = async () => {
  if (workerBootPromise) {
    return workerBootPromise;
  }

  workerBootPromise = (async () => {
    const activeLockPid = await getActiveWorkerLock();
    if (activeLockPid > 0) {
      log(`Assistant worker already locked by PID ${activeLockPid}.`);
      return null;
    }

    if (await isWorkerHubReachable()) {
      log(`Assistant worker hub already reachable on ${assistantWorkerHost}:${assistantWorkerPort}.`);
      return null;
    }

    if (workerProcess) {
      return workerProcess;
    }

    log('Starting assistant worker...');
    workerProcess = spawn(process.execPath, [assistantWorkerScript], {
      cwd: moduleDir,
      env: {
        ...process.env,
        ASSISTANT_WS_HOST: assistantWorkerHost,
        ASSISTANT_WS_PORT: String(assistantWorkerPort),
        ASSISTANT_APP_URL: appUrl,
        APP_URL: appUrl,
      },
      stdio: 'inherit',
      windowsHide: true,
    });

    workerProcess.once('exit', (code, signal) => {
      log(`Assistant worker exited (${code ?? 'null'}, ${signal ?? 'null'}).`);
      workerProcess = null;
    });

    await sleep(1200);
    return workerProcess;
  })().finally(() => {
    workerBootPromise = null;
  });

  return workerBootPromise;
};

const getSenderChatId = (message = {}) => {
  const from = safeText(message.from || '');
  const author = safeText(message.author || '');
  if (from.endsWith('@g.us')) {
    return normalizeChatId(author);
  }
  return normalizeChatId(from);
};

const formatDuration = (milliseconds = 0) => {
  const total = Math.max(0, Math.round(Number(milliseconds) || 0));
  const seconds = Math.floor(total / 1000);
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const remainingSeconds = seconds % 60;
  const segments = [];

  if (hours > 0) {
    segments.push(String(hours).padStart(2, '0'));
  }

  segments.push(String(minutes).padStart(2, '0'));
  segments.push(String(remainingSeconds).padStart(2, '0'));
  return segments.join(':');
};

const taskDurationMs = (task = {}) => {
  const fromResult = Number(task?.result?.duration_ms || 0);
  if (Number.isFinite(fromResult) && fromResult > 0) {
    return fromResult;
  }

  const started = Date.parse(String(task.started_at || '')) || 0;
  const finished = Date.parse(String(task.finished_at || '')) || 0;
  if (started > 0 && finished > started) {
    return finished - started;
  }

  return 0;
};

const buildStepSummary = (task = {}) => {
  const steps = Array.isArray(task?.plan?.steps) ? task.plan.steps : [];
  if (!steps.length) {
    return ['- No browser steps were required.'];
  }

  return steps.slice(0, 8).map((step, index) => {
    const parts = [`${index + 1}. ${safeText(step?.action || 'step')}`];
    if (safeText(step?.target || '')) {
      parts.push(`target: ${safeText(step.target)}`);
    }
    if (safeText(step?.value || '')) {
      parts.push(`value: ${safeText(step.value)}`);
    }
    return `- ${parts.join(' | ')}`;
  });
};

const buildTaskSummary = (task = {}) => {
  const status = safeText(task.status || 'completed');
  const duration = formatDuration(taskDurationMs(task));
  const goal = safeText(task.goal || task.message || 'Assistant command');
  const summary = safeText(task.result?.summary || task.assistant_message || task.plan?.summary || '');
  const error = safeText(task.error || '');

  const lines = [
    `Task ${safeText(task.id || '')} ${status}.`,
    `Goal: ${goal}`,
    `Duration: ${duration}`,
  ];

  if (summary) {
    lines.push(`Summary: ${summary}`);
  }
  if (error) {
    lines.push(`Error: ${error}`);
  }

  lines.push('Actions:');
  lines.push(...buildStepSummary(task));

  return lines.join('\n');
};

const normalizeIncomingText = (value = '') => safeText(value)
  .replace(/[\u200B-\u200D\uFEFF]/g, '')
  .replace(/\s+/g, ' ')
  .trim();

const extractCommand = (body = '') => {
  const text = normalizeIncomingText(body);
  const match = text.match(/^(?:\/ai\b|ai\s*[:\-]?)\s*(.*)$/i);
  if (match) {
    return normalizeIncomingText(match[1] || '');
  }
  return '';
};

const logCommandStatus = (status, details = {}) => {
  log(
    'Command status',
    safeText(status || 'unknown'),
    {
      command: normalizeIncomingText(details.command || ''),
      task_id: safeText(details.task_id || ''),
      chat_id: safeText(details.chat_id || ''),
      attempt: Number(details.attempt || 0),
      max_attempts: Number(details.max_attempts || 0),
      message_id: safeText(details.message_id || ''),
      duration_ms: Number(details.duration_ms || 0),
      detail: safeText(details.detail || ''),
    }
  );
};

const validateAssistantResponse = (payload) => {
  if (!payload || typeof payload !== 'object') {
    return { ok: false, message: 'Unexpected backend response.' };
  }

  if (payload.success === false) {
    return {
      ok: false,
      message: safeText(payload.message || 'Could not process the command.'),
    };
  }

  const task = payload.task;
  if (task !== undefined && task !== null && typeof task !== 'object') {
    return { ok: false, message: 'Backend task payload is invalid.' };
  }

  if (task && !safeText(task.id)) {
    return { ok: false, message: 'Backend task id is missing.' };
  }

  if (task && !safeText(task.status)) {
    return { ok: false, message: 'Backend task status is missing.' };
  }

  const assistantMessage = safeText(
    payload.assistant_message
    || task?.assistant_message
    || task?.result?.summary
    || ''
  );

  if (!assistantMessage && !task) {
    return { ok: false, message: 'Backend reply was empty.' };
  }

  return {
    ok: true,
    payload: {
      ...payload,
      assistant_message: assistantMessage,
    },
  };
};

const fetchWithTimeout = async (url, init = {}, timeoutMs = backendTimeoutMs) => {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), Math.max(1000, Number(timeoutMs) || backendTimeoutMs));
  try {
    return await fetch(url, {
      ...init,
      signal: controller.signal,
    });
  } finally {
    clearTimeout(timer);
  }
};

const sendCommandOnce = async (command, context = {}) => {
  const response = await fetchWithTimeout(backendUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      message: command,
      command,
      source: 'whatsapp',
      page: 'ai-agent',
      context: {
        source: 'whatsapp',
        page: 'ai-agent',
        whatsapp_chat_id: safeText(context.whatsapp_chat_id || ''),
        whatsapp_author: safeText(context.whatsapp_author || ''),
        whatsapp_message_id: safeText(context.whatsapp_message_id || ''),
        whatsapp_group_id: safeText(context.whatsapp_group_id || ''),
        detected_intent: safeText(context.detected_intent || ''),
      },
    }),
  });

  const payloadText = await response.text();
  let payload = {};
  try {
    payload = payloadText ? JSON.parse(payloadText) : {};
  } catch (_error) {
    throw new Error('Unexpected backend response.');
  }

  const validated = validateAssistantResponse(payload);
  if (!response.ok || !validated.ok) {
    throw new Error(validated.message || payload?.message || `Backend request failed with status ${response.status}.`);
  }

  return validated.payload;
};

const forwardCommand = async (command, context = {}) => {
  let lastError = null;

  for (let attempt = 1; attempt <= backendRetryCount; attempt += 1) {
    logCommandStatus('forward_attempt', {
      command,
      chat_id: context.whatsapp_chat_id,
      message_id: context.whatsapp_message_id,
      attempt,
      max_attempts: backendRetryCount,
    });

    try {
      const payload = await sendCommandOnce(command, context);
      logCommandStatus('forward_success', {
        command,
        chat_id: context.whatsapp_chat_id,
        message_id: context.whatsapp_message_id,
        attempt,
        max_attempts: backendRetryCount,
        task_id: payload?.task?.id || '',
      });
      return payload;
    } catch (error) {
      lastError = error instanceof Error ? error : new Error(safeText(error));
      logCommandStatus('forward_failed', {
        command,
        chat_id: context.whatsapp_chat_id,
        message_id: context.whatsapp_message_id,
        attempt,
        max_attempts: backendRetryCount,
        detail: lastError.message,
      });

      if (attempt < backendRetryCount) {
        await sleep(Math.min(2500, backendRetryDelayMs * attempt));
      }
    }
  }

  throw lastError || new Error('Could not forward the command.');
};

const deliverPendingSummaries = async (client) => {
  const tasks = await store.getPendingWhatsAppTasks(20);
  if (!tasks.length) {
    return;
  }

  for (const task of tasks) {
    const chatId = safeText(task.context?.whatsapp_chat_id || '');
    if (!chatId) {
      continue;
    }

    const summary = buildTaskSummary(task);
    try {
      await trackSentMessage(client, chatId, summary);
      await store.markWhatsAppDelivered(task.id, {
        chat_id: chatId,
        source: 'whatsapp',
        summary: safeText(task.result?.summary || task.assistant_message || ''),
        duration_ms: taskDurationMs(task),
      });
      log(`Delivered summary for ${task.id} to ${chatId}`);
    } catch (error) {
      log(`Failed to deliver summary for ${task.id}: ${error instanceof Error ? error.message : safeText(error)}`);
    }
  }
};

const startPolling = (client) => {
  if (pollTimer) {
    return;
  }

  pollTimer = setInterval(() => {
    void deliverPendingSummaries(client).catch((error) => {
      log(`Polling error: ${error instanceof Error ? error.message : safeText(error)}`);
    });
  }, pollDelayMs);
};

const stopPolling = () => {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
};

const browserExecutablePath = await resolveChromeExecutablePath();

const client = new Client({
  authStrategy: new LocalAuth({
    clientId,
    dataPath: path.join(repoRoot, 'storage', 'assistant', 'whatsapp-session'),
  }),
  puppeteer: {
    executablePath: browserExecutablePath || undefined,
    headless: headless ? 'new' : false,
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
    ],
  },
});

client.on('qr', (qr) => {
  qrcodeTerminal.generate(qr, { small: true });
  log('Scan the QR code shown in the terminal to connect WhatsApp.');
});

client.on('authenticated', () => {
  log('WhatsApp authenticated.');
});

client.on('auth_failure', (message) => {
  log(`WhatsApp authentication failed: ${safeText(message)}`);
});

client.on('ready', async () => {
  ready = true;
  log('WhatsApp bridge is ready.');
  await store.ensure();
  await deliverPendingSummaries(client);
  startPolling(client);
});

client.on('message_create', (message) => {
  log('RAW message_create', {
    from_me: Boolean(message?.fromMe),
    from: safeText(message?.from || ''),
    body: safeText(message?.body || ''),
  });
});

client.on('message_revoke_everyone', (message) => {
  log('RAW message_revoke_everyone', {
    from: safeText(message?.from || ''),
    body: safeText(message?.body || ''),
  });
});

client.on('disconnected', (reason) => {
  ready = false;
  stopPolling();
  log(`WhatsApp disconnected: ${safeText(reason)}`);
});

client.on('message', async (message) => {
  // Double check strict ignoring
  if (message?.fromMe === true || isAssistantSentMessage(message)) {
    return;
  }
  await handleCommandMessage(client, message, { source: 'message' });
});

const shutdown = async (signal) => {
  log(`Received ${signal}, shutting down...`);
  stopPolling();
  try {
    await client.destroy();
  } catch (_error) {
  }
  await stopAssistantWorker();
  process.exit(0);
};

process.on('SIGINT', () => {
  void shutdown('SIGINT');
});
process.on('SIGTERM', () => {
  void shutdown('SIGTERM');
});
process.on('unhandledRejection', (error) => {
  log(`Unhandled rejection: ${error instanceof Error ? error.message : safeText(error)}`);
});
process.on('uncaughtException', (error) => {
  log(`Uncaught exception: ${error instanceof Error ? error.message : safeText(error)}`);
});

await store.ensure();
await startAssistantWorker();
log(`Backend endpoint: ${backendUrl}`);
log('WhatsApp bridge is configured to accept every incoming message as a task command.');
await client.initialize();
