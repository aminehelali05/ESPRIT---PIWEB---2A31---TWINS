document.addEventListener('DOMContentLoaded', () => {
  const bootstrap = window.profileAiBootstrap || {};
  const panel = document.querySelector('[data-assistant-panel]');
  if (!panel) {
    return;
  }

  const api = {
    command: String(bootstrap?.api?.command || '../../index.php?action=assistant_command'),
    status: String(bootstrap?.api?.status || '../../index.php?action=assistant_status'),
    task: String(bootstrap?.api?.task || '../../index.php?action=assistant_task'),
  };
  const realtimeUrl = String(bootstrap?.realtime?.url || '').trim();

  const nodes = {
    chatLog: panel.querySelector('[data-assistant-chat-log]'),
    empty: panel.querySelector('[data-assistant-empty]'),
    typing: panel.querySelector('[data-assistant-typing]'),
    input: panel.querySelector('[data-assistant-input]'),
    send: panel.querySelector('[data-assistant-send]'),
    voiceInput: panel.querySelector('[data-assistant-voice-input]'),
    voiceToggle: panel.querySelector('[data-assistant-voice-toggle]'),
    settingsToggle: panel.querySelector('[data-assistant-settings-toggle]'),
    settingsPanel: panel.querySelector('[data-assistant-settings]'),
    settingsClose: panel.querySelector('[data-assistant-settings-close]'),
    clear: panel.querySelector('[data-assistant-clear]'),
    refresh: panel.querySelector('[data-assistant-refresh]'),
    status: panel.querySelector('[data-assistant-status]'),
    statusDot: panel.querySelector('[data-assistant-status-dot]'),
    statusLabel: panel.querySelector('[data-assistant-status-label]'),
    taskLabel: panel.querySelector('[data-assistant-task-label]'),
    taskSummary: panel.querySelector('[data-assistant-execution-summary]'),
    steps: panel.querySelector('[data-assistant-steps]'),
    executionIndicator: panel.querySelector('[data-assistant-execution-indicator]'),
    voiceFeedback: panel.querySelector('[data-assistant-voice-feedback]'),
    autoSpeak: panel.querySelector('[data-assistant-auto-speak]'),
    livePoll: panel.querySelector('[data-assistant-live-poll]'),
  };

  const userId = Number(bootstrap.currentUserId || 0);
  const storagePrefix = `assistant_${userId || 'guest'}`;
  const historyKey = `${storagePrefix}_history`;
  const preferencesKey = `${storagePrefix}_preferences`;

  const state = {
    messages: [],
    activeTask: null,
    activeTaskStatus: '',
    pollingTimer: null,
    listening: false,
    awaitingResponse: false,
    recognition: null,
    recognitionSupported: false,
    realtimeSocket: null,
    realtimeReconnectTimer: null,
    realtimeConnected: false,
    prefs: {
      voiceFeedback: true,
      autoSpeak: true,
      livePoll: true,
    },
    warnedConfigMissing: false,
  };

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => {
    if (char === '&') return '&amp;';
    if (char === '<') return '&lt;';
    if (char === '>') return '&gt;';
    if (char === '"') return '&quot;';
    return '&#39;';
  });

  const nowLabel = (value = new Date()) => {
    try {
      return new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    } catch (_error) {
      return '';
    }
  };

  const taskStatusLabel = (status) => {
    const value = String(status || '').toLowerCase();
    if (['queued', 'planning'].includes(value)) return 'Queued';
    if (['executing', 'running'].includes(value)) return 'Executing';
    if (value === 'completed') return 'Completed';
    if (value === 'failed') return 'Failed';
    if (value === 'cancelled') return 'Cancelled';
    return 'Idle';
  };

  const stepStatusLabel = (status) => {
    const value = String(status || '').toLowerCase();
    if (value === 'completed') return 'Done';
    if (value === 'executing' || value === 'running') return 'Running';
    if (value === 'failed') return 'Failed';
    if (value === 'cancelled') return 'Skipped';
    if (value === 'waiting') return 'Waiting';
    return 'Queued';
  };

  const readLocalJson = (key, fallback) => {
    try {
      const raw = localStorage.getItem(key);
      if (!raw) return fallback;
      const parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : fallback;
    } catch (_error) {
      return fallback;
    }
  };

  const writeLocalJson = (key, value) => {
    try {
      localStorage.setItem(key, JSON.stringify(value));
    } catch (_error) {
    }
  };

  const loadPreferences = () => {
    const stored = readLocalJson(preferencesKey, {});
    state.prefs.voiceFeedback = stored.voiceFeedback !== false;
    state.prefs.autoSpeak = stored.autoSpeak !== false;
    state.prefs.livePoll = stored.livePoll !== false;

    if (nodes.voiceFeedback) nodes.voiceFeedback.checked = state.prefs.voiceFeedback;
    if (nodes.autoSpeak) nodes.autoSpeak.checked = state.prefs.autoSpeak;
    if (nodes.livePoll) nodes.livePoll.checked = state.prefs.livePoll;
  };

  const savePreferences = () => {
    writeLocalJson(preferencesKey, state.prefs);
  };

  const loadHistory = () => {
    const history = readLocalJson(historyKey, []);
    state.messages = Array.isArray(history) ? history.slice(-40) : [];
  };

  const saveHistory = () => {
    writeLocalJson(historyKey, state.messages.slice(-40));
  };

  const setTyping = (isTyping) => {
    if (!nodes.typing) return;
    nodes.typing.hidden = !isTyping;
  };

  const setStatusMode = (mode, label) => {
    if (!nodes.status || !nodes.statusLabel) return;

    nodes.status.classList.remove('is-listening', 'is-executing');
    if (mode === 'listening') {
      nodes.status.classList.add('is-listening');
    } else if (mode === 'executing') {
      nodes.status.classList.add('is-executing');
    }

    nodes.statusLabel.textContent = label || 'Online';
  };

  const updateStatusFromTask = (task = null) => {
    const hasActiveTask = task && ['queued', 'planning', 'executing', 'running'].includes(String(task.status || '').toLowerCase());

    if (state.listening) {
      setStatusMode('listening', 'Listening');
      return;
    }

    if (hasActiveTask || state.awaitingResponse) {
      setStatusMode('executing', hasActiveTask ? taskStatusLabel(task.status) : 'Executing');
      return;
    }

    setStatusMode('online', 'Online');
  };

  const appendMessage = (role, text, meta = {}) => {
    const message = {
      role: String(role || 'system'),
      text: String(text || ''),
      meta: {
        timestamp: meta.timestamp || new Date().toISOString(),
        source: meta.source || '',
        taskId: meta.taskId || '',
      },
    };
    state.messages.push(message);
    saveHistory();
    renderMessages();
    return message;
  };

  const createMessageNode = (message) => {
    const row = document.createElement('div');
    row.className = `ai-message ${message.role === 'user' ? 'is-user' : 'is-ai'}`;

    const bubble = document.createElement('div');
    bubble.className = 'ai-message-bubble';
    bubble.textContent = String(message.text || '');

    const meta = document.createElement('div');
    meta.className = 'ai-message-meta';
    const metaBits = [];
    if (message.meta?.source) metaBits.push(String(message.meta.source).toUpperCase());
    if (message.meta?.timestamp) metaBits.push(nowLabel(message.meta.timestamp));
    meta.textContent = metaBits.join(' | ');

    const wrapper = document.createElement('div');
    wrapper.appendChild(bubble);
    if (meta.textContent) {
      wrapper.appendChild(meta);
    }

    row.appendChild(wrapper);
    return row;
  };

  const renderMessages = () => {
    if (!nodes.chatLog) return;
    nodes.chatLog.innerHTML = '';

    if (!state.messages.length) {
      if (nodes.empty) nodes.empty.hidden = false;
      nodes.chatLog.appendChild(nodes.empty || document.createElement('div'));
    } else {
      if (nodes.empty) nodes.empty.hidden = true;
      state.messages.forEach((message) => {
        nodes.chatLog.appendChild(createMessageNode(message));
      });
    }

    if (nodes.typing && !nodes.typing.hidden) {
      nodes.chatLog.appendChild(nodes.typing);
    }

    requestAnimationFrame(() => {
      if (nodes.chatLog) {
        nodes.chatLog.scrollTop = nodes.chatLog.scrollHeight;
      }
    });

    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }
  };

  const renderSteps = (task) => {
    if (!nodes.steps) return;
    nodes.steps.innerHTML = '';

    const steps = Array.isArray(task?.plan?.steps) ? task.plan.steps : [];
    if (!steps.length) {
      const empty = document.createElement('div');
      empty.className = 'ai-step-empty';
      empty.textContent = 'No browser steps were generated for this command.';
      nodes.steps.appendChild(empty);
      return;
    }

    const activeIndex = Number(task?.progress?.current_step?.index ?? -1);
    steps.forEach((step, index) => {
      const item = document.createElement('div');
      const stepStatus = String(step?.status || 'queued').toLowerCase();
      item.className = 'ai-step-item';
      if (index === activeIndex || ['executing', 'running'].includes(stepStatus)) {
        item.classList.add('is-active');
      }

      const indexNode = document.createElement('div');
      indexNode.className = 'ai-step-index';
      indexNode.textContent = String(index + 1);

      const body = document.createElement('div');
      body.className = 'ai-step-body';

      const title = document.createElement('strong');
      title.textContent = String(step?.action || 'step').replace(/_/g, ' ');

      const description = document.createElement('p');
      const target = String(step?.target || '').trim();
      const value = String(step?.value || '').trim();
      const notes = String(step?.notes || '').trim();
      description.textContent = [target, value, notes].filter(Boolean).join(' - ');

      const status = document.createElement('span');
      status.className = 'ai-step-status';
      status.textContent = stepStatusLabel(stepStatus);

      body.appendChild(title);
      if (description.textContent) {
        body.appendChild(description);
      }
      body.appendChild(status);

      item.appendChild(indexNode);
      item.appendChild(body);
      nodes.steps.appendChild(item);
    });
  };

  const renderTaskPanel = (task = null) => {
    state.activeTask = task || null;
    state.activeTaskStatus = String(task?.status || '');

    const isBusy = Boolean(task && ['queued', 'planning', 'executing', 'running'].includes(String(task.status || '').toLowerCase()));
    const mode = state.listening ? 'listening' : (isBusy ? 'executing' : 'online');
    updateStatusFromTask(task);

    if (nodes.taskLabel) {
      nodes.taskLabel.textContent = task ? taskStatusLabel(task.status) : 'Idle';
    }

    if (nodes.taskSummary) {
      nodes.taskSummary.textContent = task
        ? String(task.plan?.summary || task.assistant_message || 'Working on it.')
        : 'Waiting for a command.';
    }

    if (nodes.executionIndicator) {
      nodes.executionIndicator.style.opacity = isBusy ? '1' : '0.5';
    }

    renderSteps(task);

    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }

    if (mode !== 'executing' && !state.listening && nodes.status) {
      nodes.status.classList.remove('is-executing');
    }
  };

  const applyTaskSnapshot = (task = null, options = {}) => {
    if (!task || !task.id) {
      return null;
    }

    const previousStatus = state.activeTaskStatus;
    state.activeTask = task;
    state.activeTaskStatus = String(task.status || '');
    renderTaskPanel(task);

    const isFinal = ['completed', 'failed', 'cancelled'].includes(String(task.status || '').toLowerCase());
    if (isFinal && previousStatus !== task.status) {
      const finalMessage = String(task.plan?.voice_reply || task.assistant_message || task.plan?.summary || 'Task complete.');
      appendMessage('assistant', finalMessage, { source: task.source || options.source || 'assistant', taskId: task.id });
      if (!options.silent) {
        speakText(finalMessage);
      }
      stopPolling();
      state.awaitingResponse = false;
      updateStatusFromTask(task);
    }

    return task;
  };

  const requestJson = async (url, options = {}) => {
    const response = await fetch(url, {
      credentials: 'same-origin',
      ...options,
    });

    const text = await response.text();
    let parsed;
    try {
      parsed = text ? JSON.parse(text) : {};
    } catch (_error) {
      parsed = { success: false, message: 'Unexpected response format.' };
    }

    if (!response.ok || parsed?.success === false) {
      const error = new Error(String(parsed?.message || 'Request failed.'));
      error.payload = parsed || {};
      error.httpStatus = response.status;
      throw error;
    }

    return parsed;
  };

  const speakText = (text) => {
    if (!state.prefs.voiceFeedback || !state.prefs.autoSpeak) return;
    const synth = window.speechSynthesis;
    if (!synth || typeof SpeechSynthesisUtterance === 'undefined') return;

    try {
      synth.cancel();
      const utterance = new SpeechSynthesisUtterance(String(text || '').slice(0, 220));
      utterance.rate = 1;
      utterance.pitch = 1;
      utterance.lang = 'en-US';
      synth.speak(utterance);
    } catch (_error) {
    }
  };

  const startPolling = () => {
    if (!state.prefs.livePoll) return;
    stopPolling();
    state.pollingTimer = window.setInterval(async () => {
      if (!state.activeTask?.id) return;
      try {
        await refreshTask(state.activeTask.id, { silent: true });
      } catch (_error) {
      }
    }, 2500);
  };

  const stopPolling = () => {
    if (state.pollingTimer) {
      window.clearInterval(state.pollingTimer);
      state.pollingTimer = null;
    }
  };

  const disconnectRealtimeSocket = () => {
    if (state.realtimeReconnectTimer) {
      window.clearTimeout(state.realtimeReconnectTimer);
      state.realtimeReconnectTimer = null;
    }

    if (state.realtimeSocket) {
      try {
        state.realtimeSocket.close();
      } catch (_error) {
      }
      state.realtimeSocket = null;
    }
    state.realtimeConnected = false;
  };

  const scheduleRealtimeReconnect = () => {
    if (!realtimeUrl) return;
    if (state.realtimeReconnectTimer) return;

    state.realtimeReconnectTimer = window.setTimeout(() => {
      state.realtimeReconnectTimer = null;
      connectRealtimeSocket();
    }, 4500);
  };

  const connectRealtimeSocket = () => {
    if (!realtimeUrl || typeof window.WebSocket === 'undefined') {
      return;
    }

    if (state.realtimeSocket && (state.realtimeSocket.readyState === WebSocket.OPEN || state.realtimeSocket.readyState === WebSocket.CONNECTING)) {
      return;
    }

    try {
      const socket = new WebSocket(realtimeUrl);
      state.realtimeSocket = socket;

      socket.onopen = () => {
        state.realtimeConnected = true;
        if (state.realtimeReconnectTimer) {
          window.clearTimeout(state.realtimeReconnectTimer);
          state.realtimeReconnectTimer = null;
        }
      };

      socket.onmessage = (event) => {
        try {
          const payload = JSON.parse(String(event.data || '{}'));
          if (payload?.task && typeof payload.task === 'object') {
            applyTaskSnapshot(payload.task, {
              source: 'ws',
              silent: false,
            });
          }
        } catch (_error) {
        }
      };

      socket.onclose = () => {
        if (state.realtimeSocket === socket) {
          state.realtimeSocket = null;
        }
        state.realtimeConnected = false;
        scheduleRealtimeReconnect();
      };

      socket.onerror = () => {
        state.realtimeConnected = false;
      };
    } catch (_error) {
      state.realtimeConnected = false;
      scheduleRealtimeReconnect();
    }
  };

  const refreshStatus = async () => {
    if (!api.status) return;
    try {
      const data = await requestJson(api.status, { method: 'GET' });
      if (!state.warnedConfigMissing && !data.configured) {
        state.warnedConfigMissing = true;
        appendMessage('system', 'OpenRouter is not configured yet. Add your API key to config.local.php and the assistant will use the selected model automatically.', {
          source: 'system',
        });
      }

      const activeTask = data.active_task || null;
      if (activeTask && activeTask.id) {
        renderTaskPanel(activeTask);
        state.activeTask = activeTask;
        state.activeTaskStatus = String(activeTask.status || '');
        if (['queued', 'planning', 'executing', 'running'].includes(String(activeTask.status || '').toLowerCase())) {
          startPolling();
        }
      } else if (!state.awaitingResponse) {
        renderTaskPanel(null);
      }

      if (Array.isArray(data.recent_tasks) && !state.messages.length) {
        const latest = data.recent_tasks[0];
        if (latest && latest.assistant_message) {
          appendMessage('system', `Last command: ${latest.assistant_message}`, {
            source: 'system',
            taskId: latest.id,
          });
        }
      }
    } catch (_error) {
    }
  };

  const refreshTask = async (taskId, options = {}) => {
    if (!taskId) return null;
    const url = new URL(api.task, window.location.href);
    url.searchParams.set('task_id', taskId);
    const data = await requestJson(url.toString(), { method: 'GET' });
    const task = data.task || null;
    if (!task) {
      return null;
    }
    return applyTaskSnapshot(task, options);
  };

  const submitCommand = async (rawText, source = 'web') => {
    const command = String(rawText || '').trim();
    if (!command || state.awaitingResponse) return;

    appendMessage('user', command, { source });
    setTyping(true);
    state.awaitingResponse = true;
    updateStatusFromTask(state.activeTask);

    if (nodes.send) nodes.send.disabled = true;
    if (nodes.input) nodes.input.disabled = true;

    try {
      const payload = await requestJson(api.command, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          message: command,
          source,
          page: 'profile',
          context: {
            page: 'profile',
            model: String(bootstrap.model || ''),
          },
        }),
      });

      const task = payload.task || null;
      const assistantMessage = String(payload.assistant_message || task?.assistant_message || task?.plan?.summary || 'Command received.');
      const isFinalTask = Boolean(task && ['completed', 'failed', 'cancelled'].includes(String(task.status || '').toLowerCase()));
      if (!isFinalTask) {
        appendMessage('assistant', assistantMessage, {
          source: task?.source || source,
          taskId: task?.id || '',
        });
      }

      if (task) {
        applyTaskSnapshot(task, {
          source: task?.source || source,
          silent: !isFinalTask,
        });
        if (!isFinalTask && ['queued', 'planning', 'executing', 'running'].includes(String(task.status || '').toLowerCase())) {
          startPolling();
        }
      }
    } catch (error) {
      appendMessage('assistant', String(error.message || 'Could not process the command.'), {
        source: 'system',
      });
      if (error?.payload?.task) {
        renderTaskPanel(error.payload.task);
      }
    } finally {
      setTyping(false);
      state.awaitingResponse = false;
      if (nodes.send) nodes.send.disabled = false;
      if (nodes.input) nodes.input.disabled = false;
      updateStatusFromTask(state.activeTask);
    }
  };

  const stopRecognition = () => {
    if (!state.recognition) return;
    try {
      state.recognition.stop();
    } catch (_error) {
    }
  };

  const startRecognition = () => {
    const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!Recognition) {
      appendMessage('system', 'Voice input is not supported in this browser.', { source: 'system' });
      return;
    }

    if (!state.recognition) {
      state.recognition = new Recognition();
      state.recognition.lang = 'en-US';
      state.recognition.interimResults = false;
      state.recognition.continuous = false;
      state.recognition.onstart = () => {
        state.listening = true;
        updateStatusFromTask(state.activeTask);
      };
      state.recognition.onresult = (event) => {
        const transcript = Array.from(event.results || [])
          .map((result) => String(result?.[0]?.transcript || '').trim())
          .filter(Boolean)
          .join(' ')
          .trim();

        if (transcript) {
          if (nodes.input) {
            nodes.input.value = transcript;
          }
          submitCommand(transcript, 'voice');
        }
      };
      state.recognition.onerror = () => {
        state.listening = false;
        updateStatusFromTask(state.activeTask);
        setTyping(false);
      };
      state.recognition.onend = () => {
        state.listening = false;
        updateStatusFromTask(state.activeTask);
      };
    }

    try {
      state.recognition.start();
    } catch (_error) {
      stopRecognition();
    }
  };

  const toggleRecognition = () => {
    if (state.listening) {
      stopRecognition();
      return;
    }
    startRecognition();
  };

  const toggleSettings = (visible) => {
    if (!nodes.settingsPanel) return;
    const next = typeof visible === 'boolean' ? visible : nodes.settingsPanel.hidden;
    nodes.settingsPanel.hidden = !next;
  };

  const clearChat = () => {
    state.messages = [];
    saveHistory();
    renderMessages();
  };

  const applyToggleState = () => {
    state.prefs.voiceFeedback = Boolean(nodes.voiceFeedback?.checked);
    state.prefs.autoSpeak = Boolean(nodes.autoSpeak?.checked);
    state.prefs.livePoll = Boolean(nodes.livePoll?.checked);
    savePreferences();

    if (!state.prefs.livePoll) {
      stopPolling();
    } else if (state.activeTask && ['queued', 'planning', 'executing', 'running'].includes(String(state.activeTask.status || '').toLowerCase())) {
      startPolling();
    }
  };

  loadPreferences();
  loadHistory();
  renderMessages();
  renderTaskPanel(null);
  updateStatusFromTask(null);

  if (!state.messages.length && state.warnedConfigMissing === false) {
    appendMessage('system', 'AI Assistant is ready. Ask me to open chats, draft replies, or queue browser actions.', {
      source: 'system',
    });
  }

  if (nodes.send) {
    nodes.send.addEventListener('click', () => {
      submitCommand(String(nodes.input?.value || ''), 'web');
      if (nodes.input) nodes.input.value = '';
    });
  }

  if (nodes.input) {
    nodes.input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        if (nodes.send) {
          nodes.send.click();
        }
      }
    });
  }

  if (nodes.voiceInput) {
    nodes.voiceInput.addEventListener('click', toggleRecognition);
  }

  if (nodes.voiceToggle) {
    nodes.voiceToggle.addEventListener('click', toggleRecognition);
  }

  if (nodes.settingsToggle) {
    nodes.settingsToggle.addEventListener('click', () => toggleSettings());
  }

  if (nodes.settingsClose) {
    nodes.settingsClose.addEventListener('click', () => toggleSettings(false));
  }

  if (nodes.clear) {
    nodes.clear.addEventListener('click', clearChat);
  }

  if (nodes.refresh) {
    nodes.refresh.addEventListener('click', async () => {
      await refreshStatus();
      if (state.activeTask?.id) {
        await refreshTask(state.activeTask.id, { silent: true });
      }
    });
  }

  if (nodes.voiceFeedback) {
    nodes.voiceFeedback.addEventListener('change', applyToggleState);
  }

  if (nodes.autoSpeak) {
    nodes.autoSpeak.addEventListener('change', applyToggleState);
  }

  if (nodes.livePoll) {
    nodes.livePoll.addEventListener('change', applyToggleState);
  }

  document.addEventListener('click', (event) => {
    if (!nodes.settingsPanel || nodes.settingsPanel.hidden) return;
    if (nodes.settingsPanel.contains(event.target) || nodes.settingsToggle?.contains(event.target)) {
      return;
    }
    toggleSettings(false);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && nodes.settingsPanel && !nodes.settingsPanel.hidden) {
      toggleSettings(false);
      return;
    }
    if (event.key === 'Escape' && state.listening) {
      stopRecognition();
    }
  });

  loadPreferences();
  updateStatusFromTask(null);
  connectRealtimeSocket();

  window.addEventListener('beforeunload', () => {
    disconnectRealtimeSocket();
  });

  refreshStatus().finally(() => {
    if (state.activeTask?.id && ['queued', 'planning', 'executing', 'running'].includes(String(state.activeTask.status || '').toLowerCase())) {
      startPolling();
    }
  });
});
