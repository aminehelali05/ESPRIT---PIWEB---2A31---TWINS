import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const deepClone = (value) => {
  try {
    return structuredClone(value);
  } catch (_error) {
    return JSON.parse(JSON.stringify(value));
  }
};

const nowIso = () => new Date().toISOString();

const defaultTasksState = () => ({
  version: 1,
  updated_at: nowIso(),
  tasks: [],
});

const defaultMemoryState = () => ({
  version: 1,
  updated_at: nowIso(),
  meta: {
    last_active_user_id: 0,
  },
  users: {},
});

const defaultNotificationState = () => ({
  version: 1,
  updated_at: nowIso(),
  whatsapp: {},
});

const toNumber = (value, fallback = 0) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
};

const safeLower = (value) => String(value ?? '').trim().toLowerCase();

const safeText = (value) => String(value ?? '').trim();

const sortByCreatedAtAsc = (left, right) => {
  const leftTime = Date.parse(String(left?.created_at ?? '')) || 0;
  const rightTime = Date.parse(String(right?.created_at ?? '')) || 0;
  if (leftTime === rightTime) {
    return String(left?.id ?? '').localeCompare(String(right?.id ?? ''));
  }
  return leftTime - rightTime;
};

const sortByCreatedAtDesc = (left, right) => sortByCreatedAtAsc(right, left);

export class AssistantTaskStore {
  constructor(options = {}) {
    const moduleDir = path.dirname(fileURLToPath(import.meta.url));
    this.repoRoot = path.resolve(options.repoRoot || path.join(moduleDir, '..', '..', '..'));
    this.storageDir = path.resolve(options.storageDir || path.join(this.repoRoot, 'storage', 'assistant'));
    this.tasksPath = path.join(this.storageDir, 'tasks.json');
    this.memoryPath = path.join(this.storageDir, 'memory.json');
    this.notificationsPath = path.join(this.storageDir, 'notifications.json');
    this.logsPath = path.join(this.storageDir, 'execution-logs.jsonl');
    this.browserProfileDir = path.join(this.storageDir, 'browser-profile');
  }

  async ensure() {
    await fs.mkdir(this.storageDir, { recursive: true });
    await fs.mkdir(this.browserProfileDir, { recursive: true });
    await Promise.all([
      this.ensureJsonFile(this.tasksPath, defaultTasksState()),
      this.ensureJsonFile(this.memoryPath, defaultMemoryState()),
      this.ensureJsonFile(this.notificationsPath, defaultNotificationState()),
    ]);
  }

  async ensureJsonFile(filePath, fallbackState) {
    try {
      await fs.access(filePath);
      return;
    } catch (_error) {
    }

    await this.writeJsonFile(filePath, fallbackState);
  }

  async readJsonFile(filePath, fallbackState) {
    try {
      const raw = await fs.readFile(filePath, 'utf8');
      if (!raw.trim()) {
        return deepClone(fallbackState);
      }
      const decoded = JSON.parse(raw);
      return decoded && typeof decoded === 'object' ? decoded : deepClone(fallbackState);
    } catch (_error) {
      return deepClone(fallbackState);
    }
  }

  async writeJsonFile(filePath, data) {
    const json = JSON.stringify(data, null, 2);
    const tempPath = `${filePath}.${process.pid}.${Date.now()}.tmp`;
    await fs.writeFile(tempPath, json, 'utf8');
    try {
      await fs.unlink(filePath);
    } catch (_error) {
    }
    await fs.rename(tempPath, filePath);
    return true;
  }

  async readTasksState() {
    return this.readJsonFile(this.tasksPath, defaultTasksState());
  }

  async writeTasksState(state) {
    return this.writeJsonFile(this.tasksPath, state);
  }

  async readMemoryState() {
    return this.readJsonFile(this.memoryPath, defaultMemoryState());
  }

  async writeMemoryState(state) {
    return this.writeJsonFile(this.memoryPath, state);
  }

  async readNotificationState() {
    return this.readJsonFile(this.notificationsPath, defaultNotificationState());
  }

  async writeNotificationState(state) {
    return this.writeJsonFile(this.notificationsPath, state);
  }

  normalizeStatus(status) {
    const value = safeLower(status);
    return ['queued', 'planning', 'executing', 'completed', 'failed', 'cancelled', 'waiting', 'running', 'final'].includes(value)
      ? value
      : 'queued';
  }

  normalizePriority(priority) {
    const value = safeLower(priority);
    return ['low', 'normal', 'high', 'urgent'].includes(value) ? value : 'normal';
  }

  normalizeStep(step = {}, index = 0) {
    if (!step || typeof step !== 'object') {
      return null;
    }

    const action = safeLower(step.action);
    if (!action) {
      return null;
    }

    return {
      id: safeText(step.id) || `step_${index + 1}`,
      action,
      target: safeText(step.target),
      value: String(step.value ?? ''),
      selector: safeText(step.selector),
      notes: safeText(step.notes),
      status: this.normalizeStatus(step.status || 'queued'),
    };
  }

  normalizePlan(plan = {}, message = '') {
    const steps = Array.isArray(plan.steps) ? plan.steps : [];
    const normalizedSteps = steps
      .slice(0, 8)
      .map((step, index) => this.normalizeStep(step, index))
      .filter(Boolean);

    const assistantMessage = safeText(plan.assistant_message || plan.voice_reply || plan.summary || message);
    const voiceReply = safeText(plan.voice_reply || assistantMessage || message);
    const summary = safeText(plan.summary || assistantMessage || message);

    return {
      goal: safeText(plan.goal || message),
      summary: summary || message,
      assistant_message: assistantMessage || message,
      voice_reply: voiceReply || assistantMessage || message,
      priority: this.normalizePriority(plan.priority || 'normal'),
      requires_browser: Boolean(plan.requires_browser || normalizedSteps.length > 0),
      requires_whatsapp: Boolean(plan.requires_whatsapp),
      steps: normalizedSteps,
    };
  }

  normalizeEvent(event = {}) {
    if (!event || typeof event !== 'object') {
      return null;
    }

    return {
      status: this.normalizeStatus(event.status || 'queued'),
      action: safeText(event.action || 'update'),
      message: safeText(event.message || ''),
      timestamp: safeText(event.timestamp || nowIso()),
      duration_ms: toNumber(event.duration_ms, 0),
    };
  }

  normalizeTask(task = {}) {
    const notifications = task.notifications && typeof task.notifications === 'object'
      ? task.notifications
      : {};

    return {
      id: safeText(task.id),
      user_id: toNumber(task.user_id, 0),
      source: safeLower(task.source || 'web'),
      message: safeText(task.message),
      goal: safeText(task.goal || task.message),
      assistant_message: safeText(task.assistant_message),
      status: this.normalizeStatus(task.status || 'queued'),
      priority: this.normalizePriority(task.priority || 'normal'),
      plan: this.normalizePlan(task.plan || {}, task.message || ''),
      context: task.context && typeof task.context === 'object' ? deepClone(task.context) : {},
      events: Array.isArray(task.events)
        ? task.events.map((event) => this.normalizeEvent(event)).filter(Boolean)
        : [],
      notifications: {
        whatsapp_sent: Boolean(notifications.whatsapp_sent),
        web_spoken: Boolean(notifications.web_spoken),
      },
      result: task.result ?? null,
      error: task.error ?? null,
      claimed_by: task.claimed_by ?? null,
      created_at: safeText(task.created_at || nowIso()),
      updated_at: safeText(task.updated_at || task.created_at || nowIso()),
      started_at: task.started_at ?? null,
      finished_at: task.finished_at ?? null,
      worker_id: task.worker_id ?? null,
    };
  }

  buildEvent(status, message = '', action = 'update', extra = {}) {
    return {
      status: this.normalizeStatus(status || 'queued'),
      action: safeText(action || 'update') || 'update',
      message: safeText(message),
      timestamp: nowIso(),
      duration_ms: toNumber(extra.duration_ms, 0),
      ...deepClone(extra || {}),
    };
  }

  buildProgress(task = {}) {
    const steps = Array.isArray(task.plan?.steps) ? task.plan.steps : [];
    const progress = {
      total_steps: steps.length,
      completed_steps: 0,
      current_step: null,
    };

    steps.forEach((step, index) => {
      const status = safeLower(step?.status || 'queued');
      if (['completed', 'done'].includes(status)) {
        progress.completed_steps += 1;
        return;
      }

      if (!progress.current_step && !['queued', 'waiting'].includes(status)) {
        progress.current_step = {
          index,
          action: safeText(step?.action || ''),
          target: safeText(step?.target || ''),
          status,
        };
      }
    });

    return progress;
  }

  summarizeTask(task = {}, includeEvents = false) {
    const normalized = this.normalizeTask(task);
    const summary = {
      id: normalized.id,
      user_id: normalized.user_id,
      source: normalized.source,
      message: normalized.message,
      goal: normalized.goal,
      assistant_message: normalized.assistant_message || normalized.plan.assistant_message,
      status: normalized.status,
      priority: normalized.priority,
      plan: normalized.plan,
      context: normalized.context,
      result: normalized.result,
      error: normalized.error,
      claimed_by: normalized.claimed_by,
      notifications: normalized.notifications,
      created_at: normalized.created_at,
      updated_at: normalized.updated_at,
      started_at: normalized.started_at,
      finished_at: normalized.finished_at,
      progress: this.buildProgress(normalized),
    };

    if (normalized.worker_id) {
      summary.worker_id = normalized.worker_id;
    }

    if (includeEvents) {
      summary.events = deepClone(normalized.events);
    }

    return summary;
  }

  sortTasks(tasks, direction = 'desc') {
    const normalized = (Array.isArray(tasks) ? tasks : [])
      .map((task) => this.normalizeTask(task));

    normalized.sort(direction === 'asc' ? sortByCreatedAtAsc : sortByCreatedAtDesc);
    return normalized;
  }

  filterTasksByUser(tasks, userId = 0) {
    return (Array.isArray(tasks) ? tasks : []).filter((task) => {
      if (userId <= 0) {
        return true;
      }
      return toNumber(task.user_id, 0) === userId;
    });
  }

  async listTasks(userId = 0, limit = 12, direction = 'desc') {
    const state = await this.readTasksState();
    const tasks = this.sortTasks(this.filterTasksByUser(state.tasks || [], userId), direction);
    return tasks.slice(0, Math.max(1, limit)).map((task) => this.summarizeTask(task, true));
  }

  async getTask(taskId, userId = 0) {
    const targetId = safeText(taskId);
    if (!targetId) {
      return null;
    }

    const state = await this.readTasksState();
    const task = (state.tasks || []).find((item) => {
      if (safeText(item?.id) !== targetId) {
        return false;
      }
      if (userId > 0 && toNumber(item?.user_id, 0) !== userId) {
        return false;
      }
      return true;
    });

    return task ? this.summarizeTask(task, true) : null;
  }

  async updateTask(taskId, mutator) {
    const targetId = safeText(taskId);
    if (!targetId || typeof mutator !== 'function') {
      return null;
    }

    const state = await this.readTasksState();
    const tasks = Array.isArray(state.tasks) ? state.tasks.map((task) => this.normalizeTask(task)) : [];
    const index = tasks.findIndex((task) => task.id === targetId);
    if (index === -1) {
      return null;
    }

    const draft = deepClone(tasks[index]);
    const mutationResult = await mutator(draft, index, tasks);
    if (mutationResult === false) {
      return this.summarizeTask(tasks[index], true);
    }

    const nextTask = this.normalizeTask(draft);
    nextTask.updated_at = nowIso();
    tasks[index] = nextTask;
    state.tasks = tasks;
    state.updated_at = nowIso();
    await this.writeTasksState(state);
    return this.summarizeTask(nextTask, true);
  }

  async claimNextTask(workerId = 'assistant-worker') {
    const state = await this.readTasksState();
    const tasks = Array.isArray(state.tasks) ? state.tasks.map((task) => this.normalizeTask(task)) : [];
    const candidates = this.sortTasks(tasks, 'asc').filter((task) => {
      const status = safeLower(task.status);
      return status === 'queued' || status === 'planning';
    });

    const nextTask = candidates[0];
    if (!nextTask) {
      return null;
    }

    const updated = await this.updateTask(nextTask.id, (draft) => {
      draft.status = 'executing';
      draft.claimed_by = workerId;
      draft.worker_id = workerId;
      draft.started_at = draft.started_at || nowIso();
      draft.updated_at = nowIso();
      draft.events = Array.isArray(draft.events) ? draft.events : [];
      draft.events.push(this.buildEvent('executing', 'Worker claimed the task.', 'claim', { worker_id: workerId }));
      draft.events = draft.events.slice(-20);
    });

    return updated;
  }

  async appendTaskEvent(taskId, event = {}, expectedStatus = null) {
    return this.updateTask(taskId, (draft) => {
      if (expectedStatus && safeLower(draft.status) !== safeLower(expectedStatus)) {
        return false;
      }

      draft.events = Array.isArray(draft.events) ? draft.events : [];
      draft.events.push(this.buildEvent(
        event.status || draft.status || 'update',
        event.message || '',
        event.action || 'update',
        event
      ));
      draft.events = draft.events.slice(-20);

      if (event.status) {
        draft.status = this.normalizeStatus(event.status);
      }
      if (Object.prototype.hasOwnProperty.call(event, 'result')) {
        draft.result = event.result;
      }
      if (Object.prototype.hasOwnProperty.call(event, 'error')) {
        draft.error = event.error;
      }
      if (Object.prototype.hasOwnProperty.call(event, 'claimed_by')) {
        draft.claimed_by = event.claimed_by;
      }
      if (Object.prototype.hasOwnProperty.call(event, 'worker_id')) {
        draft.worker_id = event.worker_id;
      }
      if (event.plan && typeof event.plan === 'object') {
        draft.plan = this.normalizePlan(event.plan, draft.message);
      }
      if (event.notifications && typeof event.notifications === 'object') {
        draft.notifications = {
          ...draft.notifications,
          ...event.notifications,
        };
      }
      if (event.context && typeof event.context === 'object') {
        draft.context = {
          ...draft.context,
          ...event.context,
        };
      }

      const now = nowIso();
      draft.updated_at = now;
      if (['executing'].includes(safeLower(draft.status)) && !draft.started_at) {
        draft.started_at = now;
      }
      if (['completed', 'failed', 'cancelled'].includes(safeLower(draft.status)) && !draft.finished_at) {
        draft.finished_at = now;
      }
    });
  }

  async setTaskStepStatus(taskId, stepIndex, status, message = '', extra = {}) {
    return this.updateTask(taskId, (draft) => {
      const steps = Array.isArray(draft.plan?.steps) ? draft.plan.steps : [];
      const index = Number(stepIndex);
      if (!Number.isInteger(index) || index < 0 || !steps[index]) {
        return false;
      }

      steps[index] = {
        ...steps[index],
        status: this.normalizeStatus(status),
      };
      draft.plan = {
        ...draft.plan,
        steps,
      };
      draft.events = Array.isArray(draft.events) ? draft.events : [];
      draft.events.push(this.buildEvent(status, message, extra.action || steps[index].action || 'step', {
        ...extra,
        step_index: index,
      }));
      draft.events = draft.events.slice(-20);
      draft.updated_at = nowIso();
    });
  }

  async completeTask(taskId, result = {}, extra = {}) {
    return this.updateTask(taskId, (draft) => {
      draft.status = 'completed';
      draft.result = result && typeof result === 'object' ? deepClone(result) : { success: true, summary: safeText(result) };
      draft.error = null;
      draft.finished_at = draft.finished_at || nowIso();
      draft.updated_at = nowIso();
      draft.events = Array.isArray(draft.events) ? draft.events : [];
      draft.events.push(this.buildEvent('completed', safeText(result?.summary || result?.message || 'Task completed.'), 'final', extra));
      draft.events = draft.events.slice(-20);
      return true;
    });
  }

  async failTask(taskId, error, extra = {}) {
    const message = error instanceof Error ? error.message : safeText(error);
    return this.updateTask(taskId, (draft) => {
      draft.status = 'failed';
      draft.error = message;
      draft.finished_at = draft.finished_at || nowIso();
      draft.updated_at = nowIso();
      draft.events = Array.isArray(draft.events) ? draft.events : [];
      draft.events.push(this.buildEvent('failed', message || 'Task failed.', 'error', extra));
      draft.events = draft.events.slice(-20);
      return true;
    });
  }

  async getTaskFeed(userId = 0, limit = 12) {
    return this.listTasks(userId, limit, 'desc');
  }

  async getPendingWhatsAppTasks(limit = 20) {
    const tasks = await this.listTasks(0, 200, 'asc');
    const notifications = await this.readNotificationState();
    const delivered = notifications.whatsapp || {};

    return tasks
      .filter((task) => ['completed', 'failed', 'cancelled'].includes(safeLower(task.status)))
      .filter((task) => safeText(task.context?.whatsapp_chat_id))
      .filter((task) => !delivered[task.id])
      .slice(0, Math.max(1, limit));
  }

  async markWhatsAppDelivered(taskId, meta = {}) {
    const notifications = await this.readNotificationState();
    notifications.whatsapp = notifications.whatsapp || {};

    if (!notifications.whatsapp[taskId]) {
      notifications.whatsapp[taskId] = {
        delivered_at: nowIso(),
        chat_id: safeText(meta.chat_id || ''),
        source: safeText(meta.source || 'whatsapp'),
        summary: safeText(meta.summary || ''),
        duration_ms: toNumber(meta.duration_ms, 0),
      };
      notifications.updated_at = nowIso();
      await this.writeNotificationState(notifications);
    }

    return notifications.whatsapp[taskId];
  }

  async wasWhatsAppDelivered(taskId) {
    const notifications = await this.readNotificationState();
    return Boolean(notifications.whatsapp && notifications.whatsapp[taskId]);
  }

  async recordExecutionLog(entry = {}) {
    const record = {
      timestamp: nowIso(),
      ...deepClone(entry || {}),
    };
    await fs.appendFile(this.logsPath, `${JSON.stringify(record)}\n`, 'utf8');
    return record;
  }

  async updateMemory(userId, updates = {}) {
    const numericUserId = toNumber(userId, 0);
    if (numericUserId <= 0) {
      return null;
    }

    const state = await this.readMemoryState();
    state.meta = state.meta && typeof state.meta === 'object' ? state.meta : {};
    state.users = state.users && typeof state.users === 'object' ? state.users : {};

    const current = state.users[numericUserId] && typeof state.users[numericUserId] === 'object'
      ? state.users[numericUserId]
      : {};

    current.updated_at = nowIso();
    current.last_active_user_id = numericUserId;
    Object.entries(updates || {}).forEach(([key, value]) => {
      current[key] = value;
    });

    current.recent_tasks = Array.isArray(current.recent_tasks) ? current.recent_tasks.slice(0, 6) : [];
    if (updates.last_task_id) {
      current.recent_tasks.unshift({
        task_id: safeText(updates.last_task_id),
        command: safeText(updates.last_command || ''),
        summary: safeText(updates.last_plan_summary || ''),
        timestamp: nowIso(),
      });
      current.recent_tasks = current.recent_tasks.slice(0, 6);
    }

    state.users[numericUserId] = current;
    state.meta.last_active_user_id = numericUserId;
    state.updated_at = nowIso();

    await this.writeMemoryState(state);
    return this.getUserMemoryFromState(state, numericUserId);
  }

  async getUserMemory(userId) {
    const numericUserId = toNumber(userId, 0);
    const state = await this.readMemoryState();
    return this.getUserMemoryFromState(state, numericUserId);
  }

  getUserMemoryFromState(state, userId) {
    const users = state && typeof state.users === 'object' ? state.users : {};
    const user = users[userId] && typeof users[userId] === 'object' ? users[userId] : {};
    return {
      user_id: userId,
      last_command: safeText(user.last_command || ''),
      last_source: safeText(user.last_source || ''),
      last_task_id: safeText(user.last_task_id || ''),
      last_plan_summary: safeText(user.last_plan_summary || ''),
      recent_tasks: Array.isArray(user.recent_tasks) ? deepClone(user.recent_tasks) : [],
      updated_at: safeText(user.updated_at || ''),
    };
  }

  async getBootstrapSnapshot(userId = 0) {
    const state = await this.readTasksState();
    const memory = await this.readMemoryState();
    const tasks = this.sortTasks(state.tasks || [], 'desc');
    const recentTasks = this.filterTasksByUser(tasks, userId).slice(0, 12).map((task) => this.summarizeTask(task, true));
    const activeTask = this.filterTasksByUser(tasks, userId)
      .filter((task) => ['queued', 'planning', 'executing'].includes(safeLower(task.status)))
      .sort(sortByCreatedAtAsc)[0] || null;

    return {
      success: true,
      configured: Boolean(process.env.OPENROUTER_API_KEY || ''),
      queue: {
        pending: tasks.filter((task) => ['queued', 'planning'].includes(safeLower(task.status))).length,
        running: tasks.filter((task) => safeLower(task.status) === 'executing').length,
        done: tasks.filter((task) => safeLower(task.status) === 'completed').length,
        failed: tasks.filter((task) => safeLower(task.status) === 'failed').length,
      },
      active_task: activeTask ? this.summarizeTask(activeTask, true) : null,
      recent_tasks: recentTasks,
      memory: this.getUserMemoryFromState(memory, userId),
      last_active_user_id: toNumber(memory?.meta?.last_active_user_id, 0),
      updated_at: safeText(state.updated_at || ''),
      now: nowIso(),
    };
  }
}
