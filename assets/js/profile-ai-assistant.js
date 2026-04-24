document.addEventListener('DOMContentLoaded', () => {
  const bootstrap = window.profileAiBootstrap || {};
  const shell = document.querySelector('[data-assistant-shell]');
  if (!shell) {
    return;
  }

  const nodes = {
    orb: shell.querySelector('[data-assistant-orb]'),
    statusText: shell.querySelector('[data-assistant-status-text]'),
    stateText: shell.querySelector('[data-assistant-state]'),
    aria: shell.querySelector('[data-assistant-aria]'),
    shortcuts: Array.from(shell.querySelectorAll('[data-assistant-command]')),
  };

  const baseUrl = new URL('../../index.php', document.baseURI);
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition || null;
  const canSpeak = typeof window.speechSynthesis !== 'undefined' && typeof window.SpeechSynthesisUtterance !== 'undefined';

  const MODE_TEXT = {
    idle: { statusText: 'Online', microText: 'Click to start' },
    listening: { statusText: 'Listening', microText: 'Listening...' },
    processing: { statusText: 'Processing', microText: 'Processing...' },
    executing: { statusText: 'Executing', microText: 'Executing...' },
    done: { statusText: 'Online', microText: 'Done' },
  };

  const CONTROL_COMMAND = /^(stop listening|deactivate|cancel)$/i;

  const state = {
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
  };

  const setAriaLabel = () => {
    if (!nodes.orb) {
      return;
    }
    const name = String(bootstrap.currentUserName || 'profile');
    const label = state.listening
      ? `Stop voice control for ${name}`
      : `Start voice control for ${name}`;
    nodes.orb.setAttribute('aria-label', label);
  };

  const buildUrl = (action, params = {}) => {
    const url = new URL(baseUrl.href);
    url.searchParams.set('action', action);
    Object.entries(params).forEach(([key, value]) => {
      if (value === undefined || value === null) {
        return;
      }
      const stringValue = String(value).trim();
      if (stringValue !== '') {
        url.searchParams.set(key, stringValue);
      }
    });
    return url.toString();
  };

  const normalizeText = (value) => String(value || '').replace(/\s+/g, ' ').trim();

  const setMode = (mode, overrides = {}) => {
    state.mode = mode;
    shell.dataset.assistantMode = mode;

    const preset = MODE_TEXT[mode] || MODE_TEXT.idle;
    const statusText = overrides.statusText ?? preset.statusText;
    const microText = overrides.microText ?? preset.microText;
    const announce = overrides.announce ?? `${statusText}. ${microText}`;

    if (nodes.statusText) {
      nodes.statusText.textContent = statusText;
    }
    if (nodes.stateText) {
      nodes.stateText.textContent = microText;
    }
    if (nodes.aria) {
      nodes.aria.textContent = announce;
    }

    setAriaLabel();
  };

  const speak = (text) => {
    const utteranceText = normalizeText(text);
    if (!canSpeak || utteranceText === '') {
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
      // Voice output is best-effort only.
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

  const finishTask = (task, summaryText) => {
    state.processing = false;
    state.executing = false;
    state.currentTaskId = '';
    clearTaskTimer();

    const doneText = normalizeText(summaryText || task?.result?.summary || task?.plan?.voice_reply || 'Task completed successfully.');
    setMode('done', { statusText: 'Online', microText: 'Done' });
    speak(doneText);

    if (state.doneTimer) {
      window.clearTimeout(state.doneTimer);
    }
    state.doneTimer = window.setTimeout(() => {
      if (state.mode === 'done') {
        setMode('idle');
      }
    }, 1400);
  };

  const stopRecognition = (resetMode = true) => {
    if (state.recognition) {
      try {
        state.recognition.onend = null;
        state.recognition.onresult = null;
        state.recognition.onerror = null;
        state.recognition.abort();
      } catch (_error) {
        try {
          state.recognition.stop();
        } catch (_ignore) {
        }
      }
    }

    state.recognition = null;
    state.listening = false;

    if (resetMode && !state.processing && !state.executing) {
      setMode('idle');
    }
  };

  const fallbackCommand = () => {
    const text = window.prompt('What should I do?');
    const command = normalizeText(text);
    if (command) {
      submitCommand(command, 'web');
    } else if (!state.processing && !state.executing) {
      setMode('idle');
    }
  };

  const startRecognition = () => {
    if (!SpeechRecognition) {
      fallbackCommand();
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
      setMode('listening');
    };

    recognition.onresult = (event) => {
      const transcript = Array.from(event.results || [])
        .map((result) => String(result?.[0]?.transcript || ''))
        .join(' ');
      const command = normalizeText(transcript);
      state.listening = false;
      state.recognition = null;

      if (!command) {
        setMode('idle');
        return;
      }

      submitCommand(command, 'voice');
    };

    recognition.onerror = (event) => {
      state.listening = false;
      state.recognition = null;

      const errorType = String(event?.error || '');
      if (['not-allowed', 'service-not-allowed', 'audio-capture'].includes(errorType)) {
        fallbackCommand();
        return;
      }

      if (!state.processing && !state.executing) {
        setMode('idle');
      }
    };

    recognition.onend = () => {
      state.listening = false;
      state.recognition = null;
      if (!state.processing && !state.executing) {
        setMode('idle');
      }
    };

    state.recognition = recognition;
    try {
      recognition.start();
    } catch (_error) {
      state.recognition = null;
      state.listening = false;
      fallbackCommand();
    }
  };

  const pollTask = async (taskId, token, summaryText) => {
    if (!taskId) {
      return;
    }

    if (state.currentTaskId !== taskId || token !== state.currentCommandToken) {
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
      if (state.currentTaskId !== taskId || token !== state.currentCommandToken) {
        return;
      }
      if (!payload || !payload.success) {
        return;
      }

      const task = payload.task || {};
      const status = String(task.status || '').toLowerCase();
      if (['queued', 'planning', 'executing', 'running'].includes(status)) {
        state.executing = true;
        state.processing = false;
        setMode('executing');
        return;
      }

      if (['completed', 'final'].includes(status)) {
        finishTask(task, summaryText || task?.plan?.voice_reply || task?.result?.summary);
        return;
      }

      if (['failed', 'cancelled'].includes(status)) {
        clearTaskTimer();
        state.processing = false;
        state.executing = false;
        state.currentTaskId = '';
        setMode('idle');
      }
    } catch (_error) {
      // Keep polling quietly. The state remains minimal on purpose.
    }
  };

  const beginTaskTracking = (taskId, summaryText) => {
    clearTaskTimer();
    state.currentTaskId = taskId;
    state.executing = true;
    state.processing = false;
    setMode('executing');

    const token = state.currentCommandToken;
    const tick = () => pollTask(taskId, token, summaryText);
    tick();
    state.taskTimer = window.setInterval(tick, 2500);
  };

  async function refreshAssistantState() {
    if (state.listening || state.processing || state.currentTaskId) {
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

      const task = payload.active_task || null;
      const status = String(task?.status || '').toLowerCase();
      if (task && task.id && ['queued', 'planning', 'executing', 'running'].includes(status)) {
        beginTaskTracking(String(task.id), task?.plan?.voice_reply || task?.assistant_message || '');
        return;
      }

      if (state.mode !== 'idle') {
        setMode('idle');
      }
    } catch (_error) {
      // Silence is intentional here.
    }
  }

  async function submitCommand(command, source = 'web') {
    const message = normalizeText(command);
    if (!message) {
      return;
    }

    const token = ++state.currentCommandToken;
    stopRecognition(false);
    clearTaskTimer();
    if (state.doneTimer) {
      window.clearTimeout(state.doneTimer);
      state.doneTimer = null;
    }

    state.processing = true;
    state.executing = false;
    state.currentTaskId = '';
    setMode('processing');

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
          source,
          page: String(bootstrap.page || 'profile'),
          context: {
            page: String(bootstrap.page || 'profile'),
            theme: String(bootstrap.theme || 'profile'),
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
      const summaryText = normalizeText(payload.voice_reply || payload.assistant_message || task?.plan?.voice_reply || task?.assistant_message || '');

      if (task.id && ['queued', 'planning', 'executing', 'running'].includes(taskStatus)) {
        beginTaskTracking(String(task.id), summaryText);
        return;
      }

      finishTask(task, summaryText);
    } catch (_error) {
      if (token !== state.currentCommandToken) {
        return;
      }

      state.processing = false;
      state.executing = false;
      state.currentTaskId = '';
      setMode('idle');
    }
  }

  function handleShortcut(command) {
    const text = normalizeText(command);
    if (!text) {
      return;
    }

    if (CONTROL_COMMAND.test(text)) {
      stopRecognition(true);
      return;
    }

    submitCommand(text, 'web');
  }

  if (nodes.orb) {
    nodes.orb.addEventListener('click', () => {
      if (state.listening) {
        stopRecognition(true);
        return;
      }

      startRecognition();
    });
  }

  nodes.shortcuts.forEach((button) => {
    button.addEventListener('click', () => {
      handleShortcut(button.dataset.assistantCommand || '');
    });
  });

  setMode('idle');
  refreshAssistantState();
  state.statusTimer = window.setInterval(refreshAssistantState, 12000);

  if (window.lucide && typeof window.lucide.createIcons === 'function') {
    window.lucide.createIcons();
  }
});
