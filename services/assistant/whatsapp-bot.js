import whatsappWeb from 'whatsapp-web.js';
import qrcodeTerminal from 'qrcode-terminal';
import { AssistantTaskStore } from './lib/task-store.js';

const { Client, LocalAuth } = whatsappWeb;

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, Math.max(0, Number(ms) || 0)));
const nowIso = () => new Date().toISOString();
const safeText = (value) => String(value ?? '').trim();

const appUrl = safeText(process.env.ASSISTANT_APP_URL || 'http://localhost:3000').replace(/\/+$/, '');
const backendUrl = safeText(process.env.ASSISTANT_BACKEND_URL || new URL('index.php?action=assistant_command', `${appUrl}/`).toString());
const pollDelayMs = Math.max(3000, Number(process.env.ASSISTANT_WHATSAPP_POLL_MS || 5000));
const headless = String(process.env.ASSISTANT_WHATSAPP_HEADLESS ?? 'true').toLowerCase() !== 'false';
const clientId = safeText(process.env.ASSISTANT_WHATSAPP_CLIENT_ID || 'diversity-assistant');

const store = new AssistantTaskStore();

let pollTimer = null;
let ready = false;

const log = (...parts) => {
  console.log(`[assistant-whatsapp ${nowIso()}]`, ...parts);
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

const extractCommand = (body = '') => {
  const text = safeText(body);
  const match = text.match(/^(?:ai:|\/ai\b)\s*(.*)$/i);
  if (match) {
    return safeText(match[1] || '');
  }
  return '';
};

const forwardCommand = async (command, context = {}) => {
  const response = await fetch(backendUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      message: command,
      source: 'whatsapp',
      page: 'profile',
      context: {
        source: 'whatsapp',
        page: 'profile',
        whatsapp_chat_id: safeText(context.whatsapp_chat_id || ''),
        whatsapp_author: safeText(context.whatsapp_author || ''),
        whatsapp_message_id: safeText(context.whatsapp_message_id || ''),
        whatsapp_group_id: safeText(context.whatsapp_group_id || ''),
      },
    }),
  });

  const payloadText = await response.text();
  let payload = {};
  try {
    payload = payloadText ? JSON.parse(payloadText) : {};
  } catch (_error) {
    payload = { success: false, message: 'Unexpected backend response.' };
  }

  if (!response.ok || payload?.success === false) {
    throw new Error(String(payload?.message || 'Could not forward the command.'));
  }

  return payload;
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
      await client.sendMessage(chatId, summary);
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

const client = new Client({
  authStrategy: new LocalAuth({ clientId }),
  puppeteer: {
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

client.on('disconnected', (reason) => {
  ready = false;
  stopPolling();
  log(`WhatsApp disconnected: ${safeText(reason)}`);
});

client.on('message', async (message) => {
  if (!ready || message.fromMe) {
    return;
  }

  const command = extractCommand(message.body);
  if (!command) {
    return;
  }

  const context = {
    whatsapp_chat_id: safeText(message.from || ''),
    whatsapp_author: safeText(message.author || ''),
    whatsapp_message_id: safeText(message.id?.id || message.id?._serialized || ''),
    whatsapp_sender_id: safeText(message.author || ''),
    whatsapp_group_id: safeText(message.from || '').endsWith('@g.us') ? safeText(message.from || '') : '',
  };

  try {
    await message.reply('Received. I am processing your request now.');
  } catch (_error) {
  }

  try {
    const payload = await forwardCommand(command, context);
    const task = payload?.task || null;

    if (task && ['completed', 'failed', 'cancelled'].includes(safeText(task.status))) {
      const summary = buildTaskSummary(task);
      await message.reply(summary);
      await store.markWhatsAppDelivered(task.id, {
        chat_id: context.whatsapp_chat_id,
        source: 'whatsapp',
        summary: safeText(task.result?.summary || task.assistant_message || ''),
        duration_ms: taskDurationMs(task),
      });
      log(`Immediate delivery sent for ${task.id}`);
    } else if (task?.id) {
      log(`Queued task ${task.id} for WhatsApp command.`);
    }
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : safeText(error);
    try {
      await message.reply(`Could not process the command: ${errorMessage}`);
    } catch (_replyError) {
    }
    log(`Command forwarding failed: ${errorMessage}`);
  }
});

const shutdown = async (signal) => {
  log(`Received ${signal}, shutting down...`);
  stopPolling();
  try {
    await client.destroy();
  } catch (_error) {
  }
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
log(`Backend endpoint: ${backendUrl}`);
await client.initialize();
