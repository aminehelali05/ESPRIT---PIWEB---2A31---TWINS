document.addEventListener('DOMContentLoaded', () => {
  const bootstrap = window.aiAgentBootstrap || {};
  const shell = document.querySelector('[data-agent-shell]');
  if (!shell) {
    return;
  }

  const nodes = {
    orb: shell.querySelector('[data-agent-orb]'),
    input: shell.querySelector('[data-agent-input]'),
    send: shell.querySelector('[data-agent-send]'),
    voiceToggle: shell.querySelector('[data-agent-voice-toggle]'),
    settingsToggle: shell.querySelector('[data-agent-settings-toggle]'),
    settingsPanel: shell.querySelector('[data-agent-settings-panel]'),
    modelSelect: shell.querySelector('[data-agent-model-select]'),
    voiceEnabled: shell.querySelector('[data-agent-voice-enabled]'),
    autoMode: shell.querySelector('[data-agent-auto-mode]'),
    whatsappEnabled: shell.querySelector('[data-agent-whatsapp-enabled]'),
    historyToggle: shell.querySelector('[data-agent-history-toggle]'),
    historyPanel: shell.querySelector('[data-agent-history-panel]'),
    historyList: shell.querySelector('[data-agent-history-list]'),
    historyCount: shell.querySelector('[data-agent-history-count]'),
    statusDot: shell.querySelector('[data-agent-status-dot]'),
    statusText: shell.querySelector('[data-agent-status-text]'),
    stateText: shell.querySelector('[data-agent-state-text]'),
    stepText: shell.querySelector('[data-agent-step]'),
    shortcuts: Array.from(shell.querySelectorAll('[data-agent-command]')),
  };

  const baseUrl = new URL('../../index.php', document.baseURI);
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition || null;
  const canSpeak = typeof window.speechSynthesis !== 'undefined' && typeof window.SpeechSynthesisUtterance !== 'undefined';

  const storageKeys = {
    settingsOpen: 'diversity-ai-agent-settings-open',
    historyOpen: 'diversity-ai-agent-history-open',
    voiceEnabled: 'diversity-ai-agent-voice-enabled',
    autoMode: 'diversity-ai-agent-auto-mode',
    whatsappEnabled: 'diversity-ai-agent-whatsapp-enabled',
    model: 'diversity-ai-agent-model',
  };

  const state = {
    configured: true,
    mode: 'idle',
    listening: false,
    processing: false,
    executing: false,
    recognition: null,
    currentTaskId: '',
    currentCommandToken: 0,
    statusTimer: null,
    taskTimer: null,
    doneTimer: null,
    settingsOpen: true,
    historyOpen: false,
    voiceEnabled: true,
    autoMode: true,
    whatsappEnabled: true,
    selectedModel: String(bootstrap.model || ''),
    recentTasks: [],
    activeTask: null,
    lastQueue: {},
  };

  const MODE_TEXT = {
    idle: {
      statusText: 'Online',
      stateText: 'Tap the orb to begin',
      stepText: 'Ready for the next command',
    },
    listening: {
      statusText: 'Listening',
      stateText: 'Listening for your command',
      stepText: 'Speak now',
    },
    processing: {
      statusText: 'Processing',
      stateText: 'Preparing the request',
      stepText: 'Planning response',
    },
    executing: {
      statusText: 'Executing',
      stateText: 'Command is running',
      stepText: 'Tracking live progress',
    },
    done: {
      statusText: 'Complete',
      stateText: 'Task completed',
      stepText: 'Ready for the next command',
    },
    error: {
      statusText: 'Attention',
      stateText: 'Something needs your attention',
      stepText: 'Try again',
    },
  };

  const clampCount = (value) => {
    const parsed = Number(value || 0);
    if (!Number.isFinite(parsed) || parsed < 0) {
      return 0;
    }
    return Math.floor(parsed);
  };

  const normalizeText = (value) => String(value ?? '').replace(/\s+/g, ' ').trim();

  const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

  const safeStorageGet = (key, fallback = null) => {
    try {
      const value = window.localStorage.getItem(key);
      return value === null ? fallback : value;
    } catch (_error) {
      return fallback;
    }
  };

  const safeStorageSet = (key, value) => {
    try {
      window.localStorage.setItem(key, String(value));
    } catch (_error) {
      // Local storage is best effort only.
    }
  };

  const buildUrl = (action, params = {}) => {
    const url = new URL(baseUrl.href);
    url.searchParams.set('action', action);

    Object.entries(params).forEach(([key, value]) => {
      if (value === undefined || value === null) {
        return;
      }
      const text = String(value).trim();
      if (text !== '') {
        url.searchParams.set(key, text);
      }
    });

    return url.toString();
  };

  const formatRelativeTime = (value) => {
    if (!value) {
      return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return '';
    }

    const diffSeconds = Math.round((date.getTime() - Date.now()) / 1000);
    const absSeconds = Math.abs(diffSeconds);

    if (absSeconds < 60) {
      return `${Math.max(1, absSeconds)}s ago`;
    }

    const minutes = Math.round(absSeconds / 60);
    if (minutes < 60) {
      return `${minutes}m ago`;
    }

    const hours = Math.round(minutes / 60);
    if (hours < 24) {
      return `${hours}h ago`;
    }

    const days = Math.round(hours / 24);
    return `${days}d ago`;
  };

  const formatTimeLabel = (value) => {
    if (!value) {
      return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return '';
    }

    try {
      return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
      }).format(date);
    } catch (_error) {
      return date.toLocaleTimeString();
    }
  };

  const formatQueueSummary = (queue = {}) => {
    const pending = clampCount(queue.pending);
    const running = clampCount(queue.running);
    const done = clampCount(queue.done);
    const failed = clampCount(queue.failed);

    const segments = [];
    if (pending) {
      segments.push(`${pending} queued`);
    }
    if (running) {
      segments.push(`${running} running`);
    }
    if (done) {
      segments.push(`${done} done`);
    }
    if (failed) {
      segments.push(`${failed} failed`);
    }

    return segments.length ? segments.join(' · ') : 'Ready for commands';
  };

  const getCurrentModeText = (mode) => MODE_TEXT[mode] || MODE_TEXT.idle;

  const setMode = (mode, overrides = {}) => {
    state.mode = mode;
    shell.dataset.agentState = mode;

    const preset = getCurrentModeText(mode);
    const statusText = overrides.statusText ?? preset.statusText;
    const stateText = overrides.stateText ?? preset.stateText;
    const stepText = overrides.stepText ?? preset.stepText;
    const announce = overrides.announce ?? `${statusText}. ${stateText}.`;

    if (nodes.statusText) {
      nodes.statusText.textContent = statusText;
    }
    if (nodes.stateText) {
      nodes.stateText.textContent = stateText;
    }
    if (nodes.stepText) {
      nodes.stepText.textContent = stepText;
    }
    if (nodes.statusDot) {
      nodes.statusDot.dataset.agentState = mode;
    }
    if (nodes.orb) {
      nodes.orb.dataset.agentState = mode;
    }

    shell.setAttribute('aria-busy', ['processing', 'executing', 'listening'].includes(mode) ? 'true' : 'false');
    shell.setAttribute('data-agent-announce', announce);
  };

  const speak = (text) => {
    if (!state.voiceEnabled || !canSpeak) {
      return;
    }

    const utteranceText = normalizeText(text);
    if (utteranceText === '') {
      return;
    }

    try {
      window.speechSynthesis.cancel();
      const utterance = new SpeechSynthesisUtterance(utteranceText);
      utterance.lang = 'en-US';
      utterance.rate = 1;
      utterance.pitch = 1;
      utterance.volume = 1;
      window.speechSynthesis.speak(utterance);
    } catch (_error) {
      // Voice output is best effort only.
    }
  };

  const clearTaskTimer = () => {
    if (state.taskTimer) {
      window.clearInterval(state.taskTimer);
      state.taskTimer = null;
    }
  };

  const clearStatusTimer = () => {
    if (state.statusTimer) {
      window.clearInterval(state.statusTimer);
      state.statusTimer = null;
    }
  };

  const clearDoneTimer = () => {
    if (state.doneTimer) {
      window.clearTimeout(state.doneTimer);
      state.doneTimer = null;
    }
  };

  const renderHistory = (tasks = [], activeTask = null) => {
    if (!nodes.historyList) {
      return;
    }

    const list = Array.isArray(tasks) ? tasks.slice() : [];
    const activeId = String(activeTask?.id || state.currentTaskId || '');
    if (activeTask && activeId && !list.some((task) => String(task?.id || '') === activeId)) {
      list.unshift(activeTask);
    }

    state.recentTasks = list;
    state.activeTask = activeTask;

    if (nodes.historyCount) {
      nodes.historyCount.textContent = String(list.length);
    }

    if (!list.length) {
      nodes.historyList.innerHTML = '<div class="ai-history-empty">Open this panel to review your latest commands and execution states.</div>';
      return;
    }

    nodes.historyList.innerHTML = list.map((task) => {
      const taskId = String(task?.id || '');
      const status = String(task?.status || 'queued');
      const title = normalizeText(task?.goal || task?.message || task?.assistant_message || 'Command');
      const summary = normalizeText(
        task?.plan?.summary ||
        task?.assistant_message ||
        task?.result?.summary ||
        task?.error ||
        'No summary available yet.'
      );
      const source = String(task?.source || 'web');
      const sourceLabel = {
        web: 'Web',
        voice: 'Voice',
        whatsapp: 'WhatsApp',
      }[source] || source;
      const updatedLabel = formatRelativeTime(task?.updated_at || task?.created_at);
      const progress = task?.progress || {};
      const currentStep = progress?.current_step || null;
      const progressLabel = progress?.total_steps
        ? `${clampCount(progress.completed_steps)} / ${clampCount(progress.total_steps)} steps`
        : '';
      const stepLabel = currentStep
        ? `${normalizeText(currentStep.action)}${currentStep.target ? ` · ${normalizeText(currentStep.target)}` : ''}`
        : (status === 'completed' ? 'Completed' : (status === 'executing' || status === 'running' ? 'Executing' : 'Queued'));
      const badgeLabel = {
        queued: 'Queued',
        planning: 'Planning',
        executing: 'Executing',
        running: 'Running',
        completed: 'Completed',
        failed: 'Failed',
        cancelled: 'Cancelled',
        waiting: 'Waiting',
        final: 'Done',
      }[status] || status;
      const metaBits = [sourceLabel];
      if (updatedLabel) {
        metaBits.push(updatedLabel);
      }
      if (progressLabel) {
        metaBits.push(progressLabel);
      }

      return `
        <article class="ai-history-item" data-task-id="${escapeHtml(taskId)}" data-task-status="${escapeHtml(status)}">
          <div class="ai-history-item-head">
            <div>
              <div class="ai-history-item-title">${escapeHtml(title)}</div>
              <div class="ai-history-item-meta">${escapeHtml(metaBits.join(' · '))}</div>
            </div>
            <span class="ai-history-state">${escapeHtml(badgeLabel)}</span>
          </div>
          <div class="ai-history-item-summary">${escapeHtml(summary)}</div>
          <div class="ai-history-item-meta">${escapeHtml(stepLabel)}</div>
        </article>
      `;
    }).join('');
  };

  const stopRecognition = (resetMode = true) => {
    if (state.recognition) {
      try {
        state.recognition.onstart = null;
        state.recognition.onend = null;
        state.recognition.onresult = null;
        state.recognition.onerror = null;
        state.recognition.abort();
      } catch (_error) {
        try {
          state.recognition.stop();
        } catch (_ignore) {
          // Ignore secondary abort errors.
        }
      }
    }

    state.recognition = null;
    state.listening = false;

    if (resetMode && !state.processing && !state.executing) {
      setMode('idle', {
        statusText: state.configured ? 'Online' : 'Offline',
        stateText: state.voiceEnabled ? 'Tap the orb to begin' : 'Voice input disabled. Use the text box below.',
        stepText: state.autoMode ? formatQueueSummary(state.lastQueue || {}) : 'Manual mode enabled',
      });
    }
  };

  const showTextEntryFallback = (message = 'Voice input is unavailable.') => {
    stopRecognition(false);
    if (nodes.input) {
      nodes.input.focus();
      if (typeof nodes.input.select === 'function') {
        nodes.input.select();
      }
    }
    setMode('idle', {
      statusText: state.configured ? 'Online' : 'Offline',
      stateText: normalizeText(message) || 'Type your command below.',
      stepText: state.autoMode ? formatQueueSummary(state.lastQueue || {}) : 'Manual mode enabled',
    });
  };

  const beginTaskTracking = (taskId, summaryText = '') => {
    if (!taskId) {
      return;
    }

    clearTaskTimer();
    clearDoneTimer();
    state.currentTaskId = String(taskId);
    state.processing = false;
    state.executing = true;
    setMode('executing', {
      statusText: 'Executing',
      stateText: normalizeText(summaryText) || 'Command is running',
      stepText: 'Waiting for execution updates',
    });

    const token = state.currentCommandToken;
    const tick = () => pollTask(taskId, token, summaryText);
    tick();
    state.taskTimer = window.setInterval(tick, 2500);
  };

  const finishTask = (task = {}, summaryText = '') => {
    state.processing = false;
    state.executing = false;
    state.currentTaskId = '';
    clearTaskTimer();

    const doneText = normalizeText(
      summaryText ||
      task?.result?.summary ||
      task?.plan?.voice_reply ||
      task?.assistant_message ||
      'Task completed successfully.'
    );

    setMode('done', {
      statusText: 'Complete',
      stateText: doneText || 'Task completed successfully.',
      stepText: 'Ready for the next command',
    });
    speak(doneText);

    clearDoneTimer();
    state.doneTimer = window.setTimeout(() => {
      if (state.mode === 'done') {
        setMode('idle', {
          statusText: state.configured ? 'Online' : 'Offline',
          stateText: state.voiceEnabled ? 'Tap the orb to begin' : 'Voice input disabled. Use the text box below.',
          stepText: state.autoMode ? formatQueueSummary(state.lastQueue || {}) : 'Manual mode enabled',
        });
      }
    }, 1400);
  };

  const pollTask = async (taskId, token, summaryText = '') => {
    if (!taskId) {
      return;
    }

    if (state.currentTaskId !== String(taskId) || token !== state.currentCommandToken) {
      return;
    }

    try {
      const response = await fetch(buildUrl('assistant_task', { task_id: taskId }), {
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
        },
      });
      const payload = await response.json();

      if (state.currentTaskId !== String(taskId) || token !== state.currentCommandToken) {
        return;
      }

      if (!payload || !payload.success) {
        return;
      }

      const task = payload.task || {};
      const status = String(task.status || '').toLowerCase();
      state.activeTask = task;

      if (['queued', 'planning', 'executing', 'running'].includes(status)) {
        const currentStep = task?.progress?.current_step || null;
        const stepLabel = currentStep
          ? `${normalizeText(currentStep.action)}${currentStep.target ? ` · ${normalizeText(currentStep.target)}` : ''}`
          : 'Waiting for execution updates';
        setMode('executing', {
          statusText: 'Executing',
          stateText: normalizeText(summaryText || task?.plan?.summary || task?.assistant_message || 'Command is running'),
          stepText: stepLabel,
        });
        return;
      }

      if (['completed', 'final'].includes(status)) {
        renderHistory(state.recentTasks.length ? state.recentTasks : [task], task);
        finishTask(task, summaryText || task?.plan?.voice_reply || task?.result?.summary);
        return;
      }

      if (['failed', 'cancelled'].includes(status)) {
        clearTaskTimer();
        state.processing = false;
        state.executing = false;
        state.currentTaskId = '';
        setMode('error', {
          statusText: 'Attention',
          stateText: task?.error ? normalizeText(task.error) : 'The task stopped before completion.',
          stepText: 'Try again',
        });
      }
    } catch (_error) {
      // Polling is intentionally quiet. The UI should remain minimal.
    }
  };

  const refreshAssistantState = async (force = false) => {
    if (!force && (state.listening || state.processing || state.currentTaskId)) {
      return;
    }

    try {
      const response = await fetch(buildUrl('assistant_status'), {
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
        },
      });
      const payload = await response.json();

      if (!payload || !payload.success) {
        return;
      }

      state.configured = Boolean(payload.configured);
      state.lastQueue = payload.queue || {};

      if (payload.model && !safeStorageGet(storageKeys.model, '')) {
        state.selectedModel = String(payload.model);
        if (nodes.modelSelect) {
          nodes.modelSelect.value = state.selectedModel;
        }
      }

      const recentTasks = Array.isArray(payload.recent_tasks) ? payload.recent_tasks : [];
      const activeTask = payload.active_task || null;
      renderHistory(recentTasks, activeTask);

      const activeTaskId = String(activeTask?.id || '');
      const activeStatus = String(activeTask?.status || '').toLowerCase();
      if (activeTaskId && ['queued', 'planning', 'executing', 'running'].includes(activeStatus)) {
        if (state.currentTaskId !== activeTaskId) {
          beginTaskTracking(activeTaskId, activeTask?.plan?.voice_reply || activeTask?.assistant_message || activeTask?.plan?.summary || '');
        } else {
          const currentStep = activeTask?.progress?.current_step || null;
          const stepLabel = currentStep
            ? `${normalizeText(currentStep.action)}${currentStep.target ? ` · ${normalizeText(currentStep.target)}` : ''}`
            : 'Tracking execution';
          setMode('executing', {
            statusText: 'Executing',
            stateText: normalizeText(activeTask?.plan?.summary || activeTask?.assistant_message || 'Command is running'),
            stepText: stepLabel,
          });
        }
        return;
      }

      if (!state.listening && !state.processing && !state.currentTaskId) {
        setMode('idle', {
          statusText: state.configured ? 'Online' : 'Offline',
          stateText: state.voiceEnabled ? 'Tap the orb to begin' : 'Voice input disabled. Use the text box below.',
          stepText: state.autoMode ? formatQueueSummary(payload.queue || {}) : 'Manual mode enabled',
        });
      }
    } catch (_error) {
      // Silently ignore network hiccups.
    }
  };

  const submitCommand = async (command, sourceHint = 'web') => {
    const message = normalizeText(command);
    if (!message) {
      return;
    }

    const token = ++state.currentCommandToken;
    stopRecognition(false);
    clearTaskTimer();
    clearDoneTimer();

    state.processing = true;
    state.executing = false;
    state.currentTaskId = '';
    setMode('processing', {
      statusText: state.whatsappEnabled ? 'WhatsApp' : 'Processing',
      stateText: 'Preparing your command',
      stepText: 'Submitting request',
    });

    const routingSource = state.whatsappEnabled ? 'whatsapp' : sourceHint;

    try {
      const response = await fetch(buildUrl('assistant_command'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          message,
          text: message,
          source: routingSource,
          page: 'ai-agent',
          context: {
            page: 'ai-agent',
            theme: String(bootstrap.theme || document.documentElement.getAttribute('data-theme') || 'light'),
            model: state.selectedModel || String(bootstrap.model || ''),
            voice_enabled: state.voiceEnabled,
            auto_mode: state.autoMode,
            whatsapp_enabled: state.whatsappEnabled,
            source_hint: sourceHint,
          },
        }),
      });

      const payload = await response.json();
      if (token !== state.currentCommandToken) {
        return;
      }

      if (!response.ok || !payload.success) {
        throw new Error(payload.message || 'Assistant request failed.');
      }

      const task = payload.task || {};
      const taskStatus = String(task.status || '').toLowerCase();
      const summaryText = normalizeText(
        payload.voice_reply ||
        payload.assistant_message ||
        task?.plan?.voice_reply ||
        task?.assistant_message ||
        ''
      );

      if (nodes.input) {
        nodes.input.value = '';
      }

      renderHistory([task, ...state.recentTasks.filter((item) => String(item?.id || '') !== String(task?.id || ''))], task);

      if (task.id && ['queued', 'planning', 'executing', 'running'].includes(taskStatus)) {
        beginTaskTracking(String(task.id), summaryText);
        return;
      }

      finishTask(task, summaryText);
    } catch (error) {
      if (token !== state.currentCommandToken) {
        return;
      }

      state.processing = false;
      state.executing = false;
      state.currentTaskId = '';
      setMode('error', {
        statusText: 'Attention',
        stateText: normalizeText(error?.message || 'Could not process the command.'),
        stepText: 'Check your model and try again',
      });
      clearDoneTimer();
      state.doneTimer = window.setTimeout(() => {
        if (state.mode === 'error') {
          setMode('idle', {
            statusText: state.configured ? 'Online' : 'Offline',
            stateText: state.voiceEnabled ? 'Tap the orb to begin' : 'Voice input disabled. Use the text box below.',
            stepText: state.autoMode ? formatQueueSummary(state.lastQueue || {}) : 'Manual mode enabled',
          });
        }
      }, 1800);
    }
  };

  const startRecognition = () => {
    if (!state.voiceEnabled) {
      showTextEntryFallback('Voice input is disabled in settings.');
      return;
    }

    if (!SpeechRecognition) {
      showTextEntryFallback('Speech recognition is not available in this browser.');
      return;
    }

    stopRecognition(false);

    const recognition = new SpeechRecognition();
    recognition.lang = 'en-US';
    recognition.continuous = false;
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;

    recognition.onstart = () => {
      state.listening = true;
      setMode('listening', {
        statusText: 'Listening',
        stateText: 'Listening for your voice command',
        stepText: 'Speak now',
      });
    };

    recognition.onresult = (event) => {
      const transcript = Array.from(event.results || [])
        .map((result) => String(result?.[0]?.transcript || ''))
        .join(' ');
      const command = normalizeText(transcript);
      state.listening = false;
      state.recognition = null;

      if (!command) {
        setMode('idle', {
          statusText: state.configured ? 'Online' : 'Offline',
          stateText: state.voiceEnabled ? 'Tap the orb to begin' : 'Voice input disabled. Use the text box below.',
          stepText: state.autoMode ? formatQueueSummary(state.lastQueue || {}) : 'Manual mode enabled',
        });
        return;
      }

      submitCommand(command, 'voice');
    };

    recognition.onerror = (event) => {
      state.listening = false;
      state.recognition = null;

      const errorType = String(event?.error || '');
      if (['not-allowed', 'service-not-allowed', 'audio-capture'].includes(errorType)) {
        showTextEntryFallback('Voice permission was denied.');
        return;
      }

      if (!state.processing && !state.executing) {
        setMode('idle', {
          statusText: state.configured ? 'Online' : 'Offline',
          stateText: state.voiceEnabled ? 'Tap the orb to begin' : 'Voice input disabled. Use the text box below.',
          stepText: state.autoMode ? formatQueueSummary(state.lastQueue || {}) : 'Manual mode enabled',
        });
      }
    };

    recognition.onend = () => {
      state.listening = false;
      state.recognition = null;

      if (!state.processing && !state.executing) {
        setMode('idle', {
          statusText: state.configured ? 'Online' : 'Offline',
          stateText: state.voiceEnabled ? 'Tap the orb to begin' : 'Voice input disabled. Use the text box below.',
          stepText: state.autoMode ? formatQueueSummary(state.lastQueue || {}) : 'Manual mode enabled',
        });
      }
    };

    state.recognition = recognition;
    try {
      recognition.start();
    } catch (_error) {
      state.recognition = null;
      state.listening = false;
      showTextEntryFallback('Voice input is unavailable right now.');
    }
  };

  const toggleRecognition = () => {
    if (state.listening) {
      stopRecognition(true);
      return;
    }
    startRecognition();
  };

  const setSettingsOpen = (open) => {
    state.settingsOpen = Boolean(open);
    shell.dataset.settingsOpen = state.settingsOpen ? 'true' : 'false';
    safeStorageSet(storageKeys.settingsOpen, state.settingsOpen ? '1' : '0');
    if (nodes.settingsPanel) {
      nodes.settingsPanel.hidden = !state.settingsOpen;
    }
    if (nodes.settingsToggle) {
      nodes.settingsToggle.setAttribute('aria-expanded', state.settingsOpen ? 'true' : 'false');
    }
  };

  const setHistoryOpen = (open) => {
    state.historyOpen = Boolean(open);
    shell.dataset.historyOpen = state.historyOpen ? 'true' : 'false';
    safeStorageSet(storageKeys.historyOpen, state.historyOpen ? '1' : '0');
    if (nodes.historyPanel) {
      nodes.historyPanel.hidden = !state.historyOpen;
    }
    if (nodes.historyToggle) {
      nodes.historyToggle.setAttribute('aria-expanded', state.historyOpen ? 'true' : 'false');
    }
    if (state.historyOpen) {
      refreshAssistantState(true);
    }
  };

  const syncPreferenceControls = () => {
    if (nodes.voiceEnabled) {
      nodes.voiceEnabled.checked = state.voiceEnabled;
    }
    if (nodes.autoMode) {
      nodes.autoMode.checked = state.autoMode;
    }
    if (nodes.whatsappEnabled) {
      nodes.whatsappEnabled.checked = state.whatsappEnabled;
    }
    if (nodes.modelSelect && state.selectedModel) {
      nodes.modelSelect.value = state.selectedModel;
    }

    shell.dataset.voiceEnabled = state.voiceEnabled ? 'true' : 'false';
    shell.dataset.autoMode = state.autoMode ? 'true' : 'false';
    shell.dataset.whatsappEnabled = state.whatsappEnabled ? 'true' : 'false';
    shell.dataset.selectedModel = state.selectedModel || '';

    if (nodes.orb) {
      nodes.orb.setAttribute('aria-label', state.voiceEnabled ? 'Start voice command' : 'Voice input is disabled. Use the text box below.');
    }
    if (nodes.voiceToggle) {
      nodes.voiceToggle.setAttribute('aria-label', state.voiceEnabled ? 'Start voice input' : 'Voice input is disabled');
    }
  };

  const initPreferences = () => {
    state.settingsOpen = safeStorageGet(storageKeys.settingsOpen, '1') !== '0';
    state.historyOpen = safeStorageGet(storageKeys.historyOpen, '0') === '1';
    state.voiceEnabled = safeStorageGet(storageKeys.voiceEnabled, '1') !== '0';
    state.autoMode = safeStorageGet(storageKeys.autoMode, '1') !== '0';
    state.whatsappEnabled = safeStorageGet(storageKeys.whatsappEnabled, '1') !== '0';
    state.selectedModel = normalizeText(safeStorageGet(storageKeys.model, state.selectedModel || bootstrap.model || '')) || String(bootstrap.model || '');

    if (bootstrap.modelOptions && Array.isArray(bootstrap.modelOptions) && bootstrap.modelOptions.length) {
      if (!state.selectedModel || !bootstrap.modelOptions.includes(state.selectedModel)) {
        state.selectedModel = String(bootstrap.modelOptions[0] || state.selectedModel || '');
      }
    }

    syncPreferenceControls();
    setSettingsOpen(state.settingsOpen);
    setHistoryOpen(state.historyOpen);
  };

  const bindEvents = () => {
    if (nodes.orb) {
      nodes.orb.addEventListener('click', () => {
        if (!state.voiceEnabled) {
          showTextEntryFallback('Voice input is disabled in settings.');
          return;
        }
        toggleRecognition();
      });
    }

    if (nodes.voiceToggle) {
      nodes.voiceToggle.addEventListener('click', () => {
        if (!state.voiceEnabled) {
          showTextEntryFallback('Voice input is disabled in settings.');
          return;
        }
        toggleRecognition();
      });
    }

    if (nodes.send && nodes.input) {
      const handleSend = () => {
        const command = normalizeText(nodes.input.value);
        if (!command) {
          if (state.voiceEnabled) {
            startRecognition();
          } else {
            nodes.input.focus();
          }
          return;
        }
        submitCommand(command, 'web');
      };

      nodes.send.addEventListener('click', handleSend);
      nodes.input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          handleSend();
        }
      });
    }

    if (nodes.settingsToggle) {
      nodes.settingsToggle.addEventListener('click', () => {
        setSettingsOpen(!state.settingsOpen);
      });
    }

    if (nodes.historyToggle) {
      nodes.historyToggle.addEventListener('click', () => {
        setHistoryOpen(!state.historyOpen);
      });
    }

    if (nodes.voiceEnabled) {
      nodes.voiceEnabled.addEventListener('change', () => {
        state.voiceEnabled = Boolean(nodes.voiceEnabled.checked);
        safeStorageSet(storageKeys.voiceEnabled, state.voiceEnabled ? '1' : '0');
        syncPreferenceControls();
        if (!state.voiceEnabled && state.listening) {
          stopRecognition(true);
        } else {
          setMode(state.mode, {
            statusText: state.configured ? 'Online' : 'Offline',
            stateText: state.voiceEnabled ? 'Tap the orb to begin' : 'Voice input disabled. Use the text box below.',
            stepText: state.autoMode ? formatQueueSummary(state.lastQueue || {}) : 'Manual mode enabled',
          });
        }
      });
    }

    if (nodes.autoMode) {
      nodes.autoMode.addEventListener('change', () => {
        state.autoMode = Boolean(nodes.autoMode.checked);
        safeStorageSet(storageKeys.autoMode, state.autoMode ? '1' : '0');
        syncPreferenceControls();
        setMode(state.mode, {
          statusText: state.configured ? 'Online' : 'Offline',
          stateText: state.autoMode ? 'Automatic execution ready' : 'Manual mode enabled',
          stepText: state.autoMode ? formatQueueSummary(state.lastQueue || {}) : 'Manual mode enabled',
        });
      });
    }

    if (nodes.whatsappEnabled) {
      nodes.whatsappEnabled.addEventListener('change', () => {
        state.whatsappEnabled = Boolean(nodes.whatsappEnabled.checked);
        safeStorageSet(storageKeys.whatsappEnabled, state.whatsappEnabled ? '1' : '0');
        syncPreferenceControls();
        setMode(state.mode, {
          statusText: state.whatsappEnabled ? 'WhatsApp' : 'Online',
          stateText: state.whatsappEnabled ? 'Commands will route through WhatsApp' : 'WhatsApp integration is off',
          stepText: state.autoMode ? formatQueueSummary(state.lastQueue || {}) : 'Manual mode enabled',
        });
      });
    }

    if (nodes.modelSelect) {
      nodes.modelSelect.addEventListener('change', () => {
        state.selectedModel = normalizeText(nodes.modelSelect.value);
        safeStorageSet(storageKeys.model, state.selectedModel);
        syncPreferenceControls();
        setMode(state.mode, {
          statusText: state.configured ? 'Online' : 'Offline',
          stateText: `Model set to ${state.selectedModel || 'default'}`,
          stepText: state.autoMode ? formatQueueSummary(state.lastQueue || {}) : 'Manual mode enabled',
        });
      });
    }

    nodes.shortcuts.forEach((button) => {
      button.addEventListener('click', () => {
        const command = normalizeText(button.dataset.agentCommand || '');
        if (!command) {
          return;
        }
        if (button.dataset.agentCommand?.toLowerCase() === 'stop listening') {
          stopRecognition(true);
          return;
        }
        submitCommand(command, 'web');
      });
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        if (state.listening) {
          stopRecognition(true);
        }
        if (state.historyOpen) {
          setHistoryOpen(false);
        }
      }
    });

    window.addEventListener('beforeunload', () => {
      stopRecognition(false);
      clearTaskTimer();
      clearStatusTimer();
      clearDoneTimer();
    });
  };

  const init = async () => {
    state.configured = bootstrap.configured !== false;
    initPreferences();
    bindEvents();

    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }

    setMode('idle', {
      statusText: state.configured ? 'Online' : 'Offline',
      stateText: state.voiceEnabled ? 'Tap the orb to begin' : 'Voice input disabled. Use the text box below.',
      stepText: state.autoMode ? formatQueueSummary({}) : 'Manual mode enabled',
    });

    await refreshAssistantState(true);
    clearStatusTimer();
    state.statusTimer = window.setInterval(() => {
      refreshAssistantState(false);
    }, 12000);
  };

  init();
});
