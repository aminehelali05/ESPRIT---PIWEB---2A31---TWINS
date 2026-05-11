import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import puppeteer from 'puppeteer';

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, Math.max(0, Number(ms) || 0)));
const nowIso = () => new Date().toISOString();
const safeText = (value) => String(value ?? '').trim();
const safeLower = (value) => safeText(value).toLowerCase();

const isVisibleElement = async (handle) => {
  if (!handle) {
    return false;
  }

  try {
    return await handle.evaluate((element) => {
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return Boolean(
        style &&
        style.display !== 'none' &&
        style.visibility !== 'hidden' &&
        rect.width > 0 &&
        rect.height > 0
      );
    });
  } catch (_error) {
    return false;
  }
};

const firstVisibleSelector = async (page, selectors = []) => {
  for (const selector of selectors) {
    try {
      const handle = await page.$(selector);
      if (!handle) {
        continue;
      }

      const visible = await isVisibleElement(handle);
      if (visible) {
        return { selector, handle };
      }

      await handle.dispose();
    } catch (_error) {
    }
  }

  return null;
};

export class AssistantBrowserController {
  constructor(options = {}) {
    const moduleDir = path.dirname(fileURLToPath(import.meta.url));
    this.store = options.store || null;
    this.repoRoot = options.repoRoot || this.store?.repoRoot || path.resolve(moduleDir, '..', '..');
    this.appUrl = safeText(options.appUrl || process.env.ASSISTANT_APP_URL || 'http://localhost:3000').replace(/\/+$/, '');
    this.headless = options.headless !== undefined
      ? Boolean(options.headless)
      : String(process.env.ASSISTANT_HEADLESS ?? 'true').toLowerCase() !== 'false';
    this.userDataDir = options.userDataDir || this.store?.browserProfileDir || path.join(this.repoRoot, 'storage', 'assistant', 'browser-profile');
    this.events = options.events || null;
    this.browser = null;
    this.page = null;
  }

  async emitTaskSnapshot(snapshot, extra = {}) {
    if (!this.events || typeof this.events.broadcast !== 'function' || !snapshot) {
      return;
    }

    try {
      this.events.broadcast({
        type: extra.type || 'task:update',
        phase: safeText(extra.phase || ''),
        task: snapshot,
      });
    } catch (_error) {
    }
  }

  buildEvent(status, message = '', action = 'update', extra = {}) {
    return {
      status: safeLower(status || 'queued') || 'queued',
      action: safeText(action || 'update') || 'update',
      message: safeText(message),
      timestamp: nowIso(),
      duration_ms: Number(extra?.duration_ms || 0),
      ...extra,
    };
  }

  resolveBaseUrl(task = {}) {
    const contextUrl = safeText(task?.context?.app_url || '');
    return contextUrl.replace(/\/+$/, '') || this.appUrl;
  }

  resolveUrl(target, baseUrl) {
    const value = safeText(target);
    if (!value) {
      return safeText(baseUrl || this.appUrl);
    }

    if (/^https?:\/\//i.test(value)) {
      return value;
    }

    const base = safeText(baseUrl || this.appUrl).replace(/\/+$/, '') || this.appUrl;
    try {
      return new URL(value, `${base}/`).toString();
    } catch (_error) {
      return `${base}/${value.replace(/^\/+/, '')}`;
    }
  }

  async launch() {
    if (this.browser) {
      return this.browser;
    }

    await fs.mkdir(this.userDataDir, { recursive: true });

    this.browser = await puppeteer.launch({
      headless: this.headless ? 'new' : false,
      userDataDir: this.userDataDir,
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-infobars',
      ],
      defaultViewport: {
        width: 1440,
        height: 1200,
      },
    });

    return this.browser;
  }

  async close() {
    if (this.page && !this.page.isClosed()) {
      try {
        await this.page.close();
      } catch (_error) {
      }
    }

    this.page = null;

    if (this.browser) {
      try {
        await this.browser.close();
      } catch (_error) {
      }
    }

    this.browser = null;
  }

  async ensurePage() {
    await this.launch();

    if (this.page && !this.page.isClosed()) {
      return this.page;
    }

    this.page = await this.browser.newPage();
    this.page.setDefaultTimeout(15000);
    this.page.setDefaultNavigationTimeout(60000);

    this.page.on('dialog', async (dialog) => {
      try {
        await dialog.accept();
      } catch (_error) {
      }
    });

    return this.page;
  }

  async goToPage(relativePath, task = {}) {
    const page = await this.ensurePage();
    const url = this.resolveUrl(relativePath, this.resolveBaseUrl(task));
    await page.goto(url, { waitUntil: 'networkidle2' });
    return page;
  }

  async waitForComposer(page) {
    const selectors = [
      '#messageComposerInput',
      '#sendMessageBtn',
      '#sendBtn',
      '#assistantCommandInput',
      'textarea',
      'input[type="text"]',
      '[contenteditable="true"]',
    ];

    for (const selector of selectors) {
      try {
        await page.waitForSelector(selector, { visible: true, timeout: 4000 });
        return selector;
      } catch (_error) {
      }
    }

    return null;
  }

  async waitForThreadList(page) {
    const selectors = [
      '.msg-thread-item',
      '.messages-thread-item',
      '[data-type][data-id]',
      '[data-thread-type][data-thread-id]',
    ];

    for (const selector of selectors) {
      try {
        await page.waitForSelector(selector, { visible: true, timeout: 4000 });
        return selector;
      } catch (_error) {
      }
    }

    return null;
  }

  async clickSelector(page, selector) {
    const handleInfo = await firstVisibleSelector(page, [selector]);
    if (!handleInfo) {
      throw new Error(`Selector not found: ${selector}`);
    }

    try {
      await handleInfo.handle.click({ delay: 25 });
    } finally {
      await handleInfo.handle.dispose();
    }
  }

  async typeSelector(page, selector, value) {
    const handleInfo = await firstVisibleSelector(page, [selector]);
    if (!handleInfo) {
      throw new Error(`Input selector not found: ${selector}`);
    }

    try {
      await handleInfo.handle.click({ clickCount: 3 });
      await page.keyboard.type(String(value ?? ''), { delay: 12 });
    } finally {
      await handleInfo.handle.dispose();
    }
  }

  async clickByText(page, text, selectors = []) {
    const needle = safeLower(text);
    if (!needle) {
      return false;
    }

    const clicked = await page.evaluate(({ needleValue, selectorList }) => {
      const candidates = Array.from(document.querySelectorAll(selectorList.join(',')));
      const match = candidates.find((element) => {
        const style = window.getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        if (!style || style.display === 'none' || style.visibility === 'hidden' || rect.width <= 0 || rect.height <= 0) {
          return false;
        }
        const label = String(element.innerText || element.textContent || '').trim().toLowerCase();
        return label.includes(needleValue);
      });

      if (!match) {
        return false;
      }

      match.scrollIntoView({ block: 'center', inline: 'center' });
      match.click();
      return true;
    }, {
      needleValue: needle,
      selectorList: selectors.length > 0 ? selectors : [
        'button',
        'a',
        '[role="button"]',
        '.msg-thread-item',
        '.messages-thread-item',
      ],
    });

    return Boolean(clicked);
  }

  async clickConversation(page, task = {}, step = {}) {
    const context = task?.context && typeof task.context === 'object' ? task.context : {};
    const type = safeLower(context.thread_type || context.threadType || step.thread_type || '');
    const threadId = Number(context.thread_id || context.threadId || step.thread_id || step.threadId || 0);
    const target = safeText(step.target || step.value || '').trim();

    if (type && threadId > 0) {
      const selectors = [
        `[data-type="${type}"][data-id="${threadId}"]`,
        `[data-thread-type="${type}"][data-thread-id="${threadId}"]`,
      ];

      const found = await firstVisibleSelector(page, selectors);
      if (found) {
        try {
          await found.handle.click({ delay: 25 });
          return true;
        } finally {
          await found.handle.dispose();
        }
      }
    }

    const selectors = [
      '.msg-thread-item',
      '.messages-thread-item',
      'button.msg-thread-item',
      'button.messages-thread-item',
      '[data-type][data-id]',
      '[data-thread-type][data-thread-id]',
    ];

    const clicked = await this.clickByText(page, target || 'conversation', selectors);
    if (clicked) {
      return true;
    }

    const fallbackSelectors = [
      '.msg-thread-item',
      '.messages-thread-item',
      '[data-type][data-id]',
      '[data-thread-type][data-thread-id]',
    ];

    const fallback = await firstVisibleSelector(page, fallbackSelectors);
    if (fallback) {
      try {
        await fallback.handle.click({ delay: 25 });
        return true;
      } finally {
        await fallback.handle.dispose();
      }
    }

    return false;
  }

  async resolveInputSelector(page, step = {}) {
    if (safeText(step.selector)) {
      return step.selector;
    }

    const target = safeLower(step.target || '');
    const selectors = [];

    if (step.action === 'type') {
      if (target.includes('composer') || target.includes('message')) {
        selectors.push('#messageComposerInput', '#assistantCommandInput');
      }
      selectors.push('textarea', 'input[type="text"]', 'input:not([type="hidden"])', '[contenteditable="true"]', '#assistantCommandInput');
    } else {
      selectors.push('#messageComposerInput', 'textarea', 'input[type="text"]', '#assistantCommandInput');
    }

    const found = await firstVisibleSelector(page, selectors);
    return found ? found.selector : null;
  }

  async resolveFileSelector(page, step = {}) {
    if (safeText(step.selector)) {
      return step.selector;
    }

    const found = await firstVisibleSelector(page, [
      'input[type="file"]',
      '[data-upload-input]',
      'input[name*="file"]',
    ]);

    return found ? found.selector : null;
  }

  async resolveClickSelector(page, step = {}) {
    if (safeText(step.selector)) {
      return step.selector;
    }

    const target = safeLower(step.target || '');
    const hintSelectors = [];

    if (target.includes('send')) {
      hintSelectors.push('#sendMessageBtn', '#sendBtn', '[data-action="send"]');
    }
    if (target.includes('refresh')) {
      hintSelectors.push('[data-assistant-refresh]', '#refreshMessagesBtn');
    }
    if (target.includes('clear')) {
      hintSelectors.push('[data-assistant-clear]');
    }
    if (target.includes('settings')) {
      hintSelectors.push('[data-assistant-settings-toggle]');
    }
    if (target.includes('voice')) {
      hintSelectors.push('[data-assistant-voice-input]', '[data-assistant-voice-toggle]');
    }

    hintSelectors.push(
      '#sendMessageBtn',
      '#sendBtn'
    );

    const found = await firstVisibleSelector(page, hintSelectors);
    return found ? found.selector : null;
  }

  async resolveNavigateTarget(task = {}, step = {}) {
    const raw = safeText(step.target || step.value || '');
    if (!raw) {
      return this.resolveUrl('Views/FrontOffice/profile.php', this.resolveBaseUrl(task));
    }

    if (/^https?:\/\//i.test(raw)) {
      return raw;
    }

    return this.resolveUrl(raw, this.resolveBaseUrl(task));
  }

  async ensureMessagesWorkspace(task = {}) {
    const page = await this.ensurePage();
    const currentUrl = safeText(page.url());
    if (!/messages\.php/i.test(currentUrl)) {
      await page.goto(this.resolveUrl('Views/FrontOffice/messages.php', this.resolveBaseUrl(task)), {
        waitUntil: 'networkidle2',
      });
    }

    await this.waitForThreadList(page);
    await this.waitForComposer(page);
    return page;
  }

  async runStep(task, step, index) {
    const action = safeLower(step?.action || '');
    const page = await this.ensurePage();
    const baseUrl = this.resolveBaseUrl(task);
    const startedAt = Date.now();

    switch (action) {
      case 'navigate': {
        const url = await this.resolveNavigateTarget(task, step);
        await page.goto(url, { waitUntil: 'networkidle2' });
        break;
      }

      case 'click': {
        const selector = await this.resolveClickSelector(page, step);
        if (selector) {
          await this.clickSelector(page, selector);
        } else if (!(await this.clickByText(page, step.target || step.value || '', []))) {
          throw new Error(`Unable to find a clickable element for "${step.target || step.value || action}"`);
        }
        break;
      }

      case 'type': {
        const selector = await this.resolveInputSelector(page, step);
        const text = safeText(step.value || step.target || '');
        if (!selector) {
          await page.keyboard.type(text, { delay: 12 });
          break;
        }

        await this.typeSelector(page, selector, text);
        break;
      }

      case 'open_chat': {
        await this.ensureMessagesWorkspace(task);
        const opened = await this.clickConversation(page, task, step);
        if (!opened) {
          throw new Error(`Unable to locate the requested conversation: ${step.target || 'conversation'}`);
        }
        await this.waitForComposer(page);
        break;
      }

      case 'upload_file': {
        const selector = await this.resolveFileSelector(page, step);
        if (!selector) {
          throw new Error('No file input was found on the current page.');
        }

        const candidate = safeText(step.value || step.target || '');
        const filePath = path.isAbsolute(candidate)
          ? candidate
          : path.resolve(this.repoRoot, candidate);
        await fs.access(filePath);

        const input = await page.$(selector);
        if (!input) {
          throw new Error(`Unable to interact with file input: ${selector}`);
        }

        try {
          await input.uploadFile(filePath);
        } finally {
          await input.dispose();
        }
        break;
      }

      case 'press': {
        await page.keyboard.press(safeText(step.value || step.target || 'Enter') || 'Enter');
        break;
      }

      case 'focus': {
        const selector = await this.resolveInputSelector(page, step);
        if (!selector) {
          throw new Error(`Unable to find an element to focus for "${step.target || action}"`);
        }
        const handle = await page.$(selector);
        if (!handle) {
          throw new Error(`Unable to focus selector: ${selector}`);
        }
        try {
          await handle.focus();
        } finally {
          await handle.dispose();
        }
        break;
      }

      case 'wait': {
        const numericDelay = Number(step.value || step.target || 0);
        if (Number.isFinite(numericDelay) && numericDelay > 0) {
          await sleep(numericDelay);
        } else if (safeText(step.target || '').startsWith('#') || safeText(step.target || '').startsWith('.')) {
          await page.waitForSelector(step.target, { visible: true, timeout: 15000 });
        } else {
          await sleep(750);
        }
        break;
      }

      default:
        throw new Error(`Unsupported browser action: ${action || 'unknown'}`);
    }

    const durationMs = Date.now() - startedAt;
    await this.store?.recordExecutionLog({
      task_id: task.id,
      user_id: task.user_id,
      action,
      step_index: index,
      status: 'completed',
      duration_ms: durationMs,
      target: safeText(step.target || ''),
      value: safeText(step.value || ''),
      url: safeText(page.url()),
      page: safeText(baseUrl),
    });

    return {
      action,
      duration_ms: durationMs,
      url: safeText(page.url()),
    };
  }

  async executeTask(task, options = {}) {
    if (!this.store) {
      throw new Error('Browser controller requires a task store.');
    }

    const page = await this.ensurePage();
    const workerId = safeText(options.workerId || 'assistant-worker');
    const startedAt = Date.now();
    const original = await this.store.getTask(task.id, 0);
    const workingTask = original || task;
    const steps = Array.isArray(workingTask.plan?.steps) ? workingTask.plan.steps : [];
    const taskSummary = safeText(workingTask.plan?.voice_reply || workingTask.assistant_message || 'Task completed successfully.');

    await this.store.updateTask(task.id, (draft) => {
      draft.status = 'executing';
      draft.claimed_by = workerId;
      draft.worker_id = workerId;
      draft.started_at = draft.started_at || nowIso();
      draft.updated_at = nowIso();
      draft.events = Array.isArray(draft.events) ? draft.events : [];
      draft.events.push(this.buildEvent('executing', 'Execution started.', 'start', { worker_id: workerId }));
      draft.events = draft.events.slice(-20);
    });
    await this.emitTaskSnapshot(await this.store.getTask(task.id, 0), {
      type: 'task:update',
      phase: 'start',
    });

    await this.store.recordExecutionLog({
      task_id: task.id,
      user_id: task.user_id,
      action: 'start',
      status: 'executing',
      duration_ms: 0,
      worker_id: workerId,
      url: safeText(page.url()),
    });

    if (!steps.length) {
      const completed = await this.store.completeTask(task.id, {
        success: true,
        summary: taskSummary,
      }, {
        worker_id: workerId,
        duration_ms: Date.now() - startedAt,
      });
      await this.emitTaskSnapshot(completed, {
        type: 'task:completed',
        phase: 'final',
      });
      return this.store.getTask(task.id, 0);
    }

    for (let index = 0; index < steps.length; index += 1) {
      const step = steps[index];
      const stepStart = await this.store.setTaskStepStatus(task.id, index, 'executing', `Starting step ${index + 1}: ${safeText(step.action || 'step')}`, {
        worker_id: workerId,
        action: safeText(step.action || 'step'),
        target: safeText(step.target || ''),
      });
      await this.emitTaskSnapshot(stepStart, {
        type: 'task:update',
        phase: 'step-start',
      });

      try {
        const result = await this.runStep(workingTask, step, index);
        const stepDone = await this.store.setTaskStepStatus(task.id, index, 'completed', `Completed step ${index + 1}: ${safeText(step.action || 'step')}`, {
          worker_id: workerId,
          action: safeText(step.action || 'step'),
          duration_ms: Number(result.duration_ms || 0),
          url: result.url,
        });
        await this.emitTaskSnapshot(stepDone, {
          type: 'task:update',
          phase: 'step-complete',
        });
      } catch (error) {
        const message = error instanceof Error ? error.message : safeText(error);
        const stepFailed = await this.store.setTaskStepStatus(task.id, index, 'failed', message, {
          worker_id: workerId,
          action: safeText(step.action || 'step'),
        });
        await this.emitTaskSnapshot(stepFailed, {
          type: 'task:update',
          phase: 'step-failed',
        });
        const failed = await this.store.failTask(task.id, error, {
          worker_id: workerId,
          duration_ms: Date.now() - startedAt,
          failed_step: index,
          failed_action: safeText(step.action || 'step'),
        });
        await this.emitTaskSnapshot(failed, {
          type: 'task:failed',
          phase: 'final',
        });
        throw error;
      }
    }

    const completed = await this.store.completeTask(task.id, {
      success: true,
      summary: taskSummary,
      steps_executed: steps.length,
      duration_ms: Date.now() - startedAt,
      final_url: safeText(page.url()),
    }, {
      worker_id: workerId,
      duration_ms: Date.now() - startedAt,
    });
    await this.emitTaskSnapshot(completed, {
      type: 'task:completed',
      phase: 'final',
    });

    return this.store.getTask(task.id, 0);
  }
}
