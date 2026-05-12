/* ================================================================
   AI AGENT — Voice-First Engine
   • Wake word: "Hey Bro"
   • Continuous background monitoring
   • Web Speech API (STT) + TTS feedback (voice-only, no text logs)
   • Pipeline step visualization
   • Compact history cards with replay + step count
   • Barge-in (interrupt during TTS)
   • WhatsApp relay
   ================================================================ */
document.addEventListener('DOMContentLoaded', () => {
  const bootstrap = window.aiAgentBootstrap || {};
  const shell = document.querySelector('[data-agent-shell]');
  if (!shell) return;

  // ── DOM node references ──────────────────────────────────────
  const nodes = {
    orb:             shell.querySelector('[data-agent-orb]'),
    input:           shell.querySelector('[data-agent-input]'),
    send:            shell.querySelector('[data-agent-send]'),
    voiceToggle:     shell.querySelector('[data-agent-voice-toggle]'),
    settingsToggle:  shell.querySelector('[data-agent-settings-toggle]'),
    settingsPanel:   shell.querySelector('[data-agent-settings-panel]'),
    modelSelect:     shell.querySelector('[data-agent-model-select]'),
    voiceEnabled:    shell.querySelector('[data-agent-voice-enabled]'),
    autoMode:        shell.querySelector('[data-agent-auto-mode]'),
    whatsappEnabled: shell.querySelector('[data-agent-whatsapp-enabled]'),
    historyToggle:   shell.querySelector('[data-agent-history-toggle]'),
    historyPanel:    shell.querySelector('[data-agent-history-panel]'),
    historyList:     shell.querySelector('[data-agent-history-list]'),
    historyCount:    shell.querySelector('[data-agent-history-count]'),
    statusText:      shell.querySelector('[data-agent-status-text]'),
    stateText:       shell.querySelector('[data-agent-state-text]'),
    stepText:        shell.querySelector('[data-agent-step]'),
    pipeline:        shell.querySelector('[data-agent-pipeline]'),
    shortcuts:       Array.from(shell.querySelectorAll('[data-agent-command]')),
    pipelineSteps:   Array.from(shell.querySelectorAll('[data-pipeline-step]')),
  };

  // ── Constants ────────────────────────────────────────────────
  const BASE_URL = new URL('../../index.php', document.baseURI);
  const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition || null;
  const canSpeak  = typeof window.speechSynthesis !== 'undefined';

  const WAKE_WORD = 'hey bro';

  const PIPELINE_STEPS = ['intent', 'planning', 'executing', 'done'];

  const STORAGE = {
    settingsOpen:    'div-ai-settings-open',
    historyOpen:     'div-ai-history-open',
    voiceEnabled:    'div-ai-voice-enabled',
    autoMode:        'div-ai-auto-mode',
    whatsappEnabled: 'div-ai-whatsapp-enabled',
    model:           'div-ai-model',
  };

  // ── State ────────────────────────────────────────────────────
  const state = {
    mode:             'idle',          // idle|listening|thinking|executing|done|error
    listening:        false,
    processing:       false,
    executing:        false,
    wakeMonitor:      null,            // background wake-word recognizer
    mainRecognition:  null,            // command recognizer
    currentTaskId:    '',
    commandToken:     0,
    statusTimer:      null,
    taskTimer:        null,
    doneTimer:        null,
    settingsOpen:     false,
    historyOpen:      false,
    voiceEnabled:     true,
    autoMode:         true,
    whatsappEnabled:  true,
    selectedModel:    String(bootstrap.model || ''),
    configured:       true,
    recentTasks:      [],
    activeTask:       null,
    lastQueue:        {},
  };

  // ── Utilities ────────────────────────────────────────────────
  const norm     = (v) => String(v ?? '').replace(/\s+/g, ' ').trim();
  const esc      = (v) => String(v ?? '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  const clampInt = (v) => Math.max(0, Math.floor(Number(v || 0) || 0));

  const store = {
    get: (k, fb = null) => { try { const v = localStorage.getItem(k); return v === null ? fb : v; } catch { return fb; } },
    set: (k, v) => { try { localStorage.setItem(k, String(v)); } catch {} },
  };

  const buildUrl = (action, params = {}) => {
    const url = new URL(BASE_URL.href);
    url.searchParams.set('action', action);
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null) {
        const t = String(v).trim();
        if (t) url.searchParams.set(k, t);
      }
    });
    return url.toString();
  };

  const relTime = (val) => {
    if (!val) return '';
    const d = new Date(val);
    if (isNaN(d.getTime())) return '';
    const s = Math.abs(Math.round((d.getTime() - Date.now()) / 1000));
    if (s < 60)  return `${Math.max(1, s)}s ago`;
    if (s < 3600) return `${Math.round(s / 60)}m ago`;
    if (s < 86400) return `${Math.round(s / 3600)}h ago`;
    return `${Math.round(s / 86400)}d ago`;
  };

  // ── TTS (voice-only feedback — no text log output) ───────────
  const speak = (text, interrupt = true) => {
    if (!state.voiceEnabled || !canSpeak) return;
    const t = norm(text);
    if (!t) return;
    if (interrupt) window.speechSynthesis.cancel();
    const utt = new SpeechSynthesisUtterance(t);
    utt.lang   = 'en-US';
    utt.rate   = 1.05;
    utt.pitch  = 1;
    utt.volume = 1;
    try { window.speechSynthesis.speak(utt); } catch {}
  };

  // ── Pipeline visualization ───────────────────────────────────
  const PIPELINE_MAP = {
    idle:      null,
    thinking:  'intent',
    processing:'planning',
    executing: 'executing',
    done:      'done',
    error:     null,
  };

  const updatePipeline = (agentState) => {
    if (!nodes.pipeline) return;
    const activeStep = PIPELINE_MAP[agentState];
    const isActive = agentState !== 'idle' && agentState !== 'error';

    nodes.pipeline.classList.toggle('is-visible', isActive);
    nodes.pipeline.setAttribute('aria-hidden', isActive ? 'false' : 'true');

    const activeIdx = PIPELINE_STEPS.indexOf(activeStep);

    nodes.pipelineSteps.forEach((stepEl, idx) => {
      const stepKey = stepEl.dataset.pipelineStep;
      const stepIdx = PIPELINE_STEPS.indexOf(stepKey);
      stepEl.classList.remove('step-active', 'step-done');

      if (stepIdx < activeIdx) {
        stepEl.classList.add('step-done');
      } else if (stepKey === activeStep) {
        stepEl.classList.add('step-active');
      }
    });
  };

  // ── Mode management ──────────────────────────────────────────
  const MODE_PRESETS = {
    idle:      { status: 'Online',      state: 'Tap the orb or say "Hey Bro"', step: 'Ready' },
    listening: { status: 'Listening',   state: 'Listening…',                  step: 'Speak now' },
    thinking:  { status: 'Thinking',    state: 'Understanding your command',   step: 'Detecting intent' },
    processing:{ status: 'Processing',  state: 'Planning the response',        step: 'Building plan' },
    executing: { status: 'Executing',   state: 'Running command',              step: 'In progress' },
    done:      { status: 'Done',        state: 'Task completed',               step: 'Ready for next command' },
    error:     { status: 'Attention',   state: 'Something went wrong',         step: 'Try again' },
  };

  const setMode = (mode, overrides = {}) => {
    state.mode = mode;
    shell.dataset.agentState = mode;

    const preset = MODE_PRESETS[mode] || MODE_PRESETS.idle;
    const statusTxt = overrides.status ?? preset.status;
    const stateTxt  = overrides.state  ?? preset.state;
    const stepTxt   = overrides.step   ?? preset.step;

    if (nodes.statusText) nodes.statusText.textContent = statusTxt;
    if (nodes.stateText)  nodes.stateText.textContent  = stateTxt;
    if (nodes.stepText)   nodes.stepText.textContent   = stepTxt;
    if (nodes.orb)        nodes.orb.dataset.agentState = mode;

    updatePipeline(mode);

    shell.setAttribute('aria-busy', ['thinking', 'processing', 'executing', 'listening'].includes(mode) ? 'true' : 'false');
  };

  // ── History rendering (compact glass mini-cards) ─────────────
  const STATUS_ICONS = {
    completed: '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>',
    failed:    '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
    executing: '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>',
    queued:    '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
  };

  const renderHistory = (tasks = [], activeTask = null) => {
    if (!nodes.historyList) return;
    const list = Array.isArray(tasks) ? tasks.slice() : [];
    const activeId = String(activeTask?.id || state.currentTaskId || '');
    if (activeTask && activeId && !list.some((t) => String(t?.id || '') === activeId)) {
      list.unshift(activeTask);
    }

    state.recentTasks = list;
    state.activeTask  = activeTask;
    if (nodes.historyCount) nodes.historyCount.textContent = String(list.length);

    if (!list.length) {
      nodes.historyList.innerHTML = '<div class="ai-history-empty">No commands yet. Tap the orb or say "Hey Bro" to start.</div>';
      return;
    }

    nodes.historyList.innerHTML = list.map((task) => {
      const taskId    = String(task?.id || '');
      const status    = String(task?.status || 'queued').toLowerCase();
      const title     = norm(task?.goal || task?.message || task?.assistant_message || 'Command');
      const source    = String(task?.source || 'web');
      const srcLabel  = { web: 'Web', voice: 'Voice', whatsapp: 'WhatsApp' }[source] || source;
      const timeLabel = relTime(task?.updated_at || task?.created_at);
      const stepCount = task?.progress?.total_steps ? clampInt(task.progress.total_steps) : 0;
      const doneCount = task?.progress?.completed_steps ? clampInt(task.progress.completed_steps) : 0;
      const badgeLabel = {
        queued: 'Queued', planning: 'Planning', executing: 'Executing', running: 'Running',
        completed: 'Done', failed: 'Failed', cancelled: 'Cancelled',
      }[status] || status;
      const icon = STATUS_ICONS[status] || STATUS_ICONS.queued;
      const metaText = [srcLabel, timeLabel, stepCount ? `${doneCount}/${stepCount} steps` : null].filter(Boolean).join(' · ');

      return `
        <article class="ai-history-item" data-task-id="${esc(taskId)}" data-task-status="${esc(status)}" role="listitem">
          <div class="ai-history-item-head">
            <div class="ai-history-item-title" title="${esc(title)}">${esc(title)}</div>
            <span class="ai-history-state" data-status="${esc(status)}">${icon}${esc(badgeLabel)}</span>
          </div>
          <div class="ai-history-item-meta">${esc(metaText)}</div>
          ${taskId ? `<button class="ai-history-replay" data-replay-id="${esc(taskId)}" data-replay-goal="${esc(title)}" aria-label="Replay command" title="Replay">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3"/></svg>
            Replay
          </button>` : ''}
        </article>`;
    }).join('');
  };

  // ── Task polling ─────────────────────────────────────────────
  const clearTimer = (key) => {
    if (state[key]) {
      (key === 'doneTimer' ? clearTimeout : clearInterval)(state[key]);
      state[key] = null;
    }
  };

  const finishTask = (task = {}, summaryText = '') => {
    state.processing  = false;
    state.executing   = false;
    state.currentTaskId = '';
    clearTimer('taskTimer');

    const doneMsg = norm(
      summaryText
      || task?.result?.summary
      || task?.plan?.voice_reply
      || task?.assistant_message
      || 'Task completed.'
    );

    setMode('done', { state: doneMsg });
    speak(doneMsg); // voice-only feedback

    clearTimer('doneTimer');
    state.doneTimer = setTimeout(() => {
      if (state.mode === 'done') resetToIdle();
    }, 1600);
  };

  const pollTask = async (taskId, token, summaryText = '') => {
    if (!taskId) return;
    if (state.currentTaskId !== String(taskId) || token !== state.commandToken) return;

    try {
      const res  = await fetch(buildUrl('assistant_task', { task_id: taskId }), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const data = await res.json();
      if (state.currentTaskId !== String(taskId) || token !== state.commandToken) return;
      if (!data?.success) return;

      const task   = data.task || {};
      const status = String(task.status || '').toLowerCase();
      state.activeTask = task;

      if (['queued', 'planning', 'executing', 'running'].includes(status)) {
        const curStep  = task?.progress?.current_step || null;
        const stepDesc = curStep
          ? `${norm(curStep.action)}${curStep.target ? ` · ${norm(curStep.target)}` : ''}`
          : 'Executing…';
        setMode('executing', {
          state: norm(summaryText || task?.plan?.summary || task?.assistant_message || 'Running command'),
          step:  stepDesc,
        });
        return;
      }

      if (['completed', 'final'].includes(status)) {
        renderHistory(state.recentTasks.length ? state.recentTasks : [task], task);
        finishTask(task, summaryText || task?.plan?.voice_reply || task?.result?.summary);
        return;
      }

      if (['failed', 'cancelled'].includes(status)) {
        clearTimer('taskTimer');
        state.processing = false;
        state.executing  = false;
        state.currentTaskId = '';
        const errMsg = norm(task?.error || 'The task stopped unexpectedly.');
        setMode('error', { state: errMsg });
        speak(errMsg);
        state.doneTimer = setTimeout(() => { if (state.mode === 'error') resetToIdle(); }, 2200);
      }
    } catch {}
  };

  const beginTaskTracking = (taskId, summaryText = '') => {
    if (!taskId) return;
    clearTimer('taskTimer');
    clearTimer('doneTimer');
    state.currentTaskId = String(taskId);
    state.processing    = false;
    state.executing     = true;

    const token = state.commandToken;
    const tick  = () => pollTask(taskId, token, summaryText);
    tick();
    state.taskTimer = setInterval(tick, 2500);
    setMode('executing', { state: norm(summaryText) || 'Running command' });
  };

  // ── Command submission ───────────────────────────────────────
  const submitCommand = async (command, sourceHint = 'web') => {
    const msg = norm(command);
    if (!msg) return;

    const token = ++state.commandToken;
    stopMainRecognition(false);
    clearTimer('taskTimer');
    clearTimer('doneTimer');

    state.processing    = true;
    state.executing     = false;
    state.currentTaskId = '';
    setMode('thinking', { state: 'Understanding your command…', step: 'Detecting intent' });

    const routingSource = state.whatsappEnabled ? 'whatsapp' : sourceHint;

    try {
      // Slight delay to show 'thinking' before 'planning'
      await new Promise((r) => setTimeout(r, 480));
      if (token !== state.commandToken) return;
      setMode('processing', { state: 'Building a plan…', step: 'Planning' });

      const res = await fetch(buildUrl('assistant_command'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          message: msg,
          text:    msg,
          source:  routingSource,
          page:    'ai-agent',
          context: {
            page:             'ai-agent',
            theme:            String(bootstrap.theme || document.documentElement.getAttribute('data-theme') || 'light'),
            model:            state.selectedModel || String(bootstrap.model || ''),
            voice_enabled:    state.voiceEnabled,
            auto_mode:        state.autoMode,
            whatsapp_enabled: state.whatsappEnabled,
            source_hint:      sourceHint,
          },
        }),
      });

      const data = await res.json();
      if (token !== state.commandToken) return;
      if (!res.ok || !data.success) throw new Error(data.message || 'Command failed.');

      const task       = data.task || {};
      const taskStatus = String(task.status || '').toLowerCase();
      const voiceReply = norm(
        data.voice_reply
        || data.assistant_message
        || task?.plan?.voice_reply
        || task?.assistant_message
        || ''
      );

      if (nodes.input) nodes.input.value = '';
      renderHistory([task, ...state.recentTasks.filter((i) => String(i?.id || '') !== String(task?.id || ''))], task);

      if (task.id && ['queued', 'planning', 'executing', 'running'].includes(taskStatus)) {
        beginTaskTracking(String(task.id), voiceReply);
        return;
      }

      finishTask(task, voiceReply);
    } catch (err) {
      if (token !== state.commandToken) return;
      state.processing    = false;
      state.executing     = false;
      state.currentTaskId = '';
      const errMsg = norm(err?.message || 'Could not process that command.');
      setMode('error', { state: errMsg });
      speak(errMsg);
      state.doneTimer = setTimeout(() => { if (state.mode === 'error') resetToIdle(); }, 2200);
    }
  };

  // ── Voice recognition (main command) ────────────────────────
  const stopMainRecognition = (resetMode = true) => {
    if (state.mainRecognition) {
      try { state.mainRecognition.onend = null; state.mainRecognition.abort(); } catch {}
      try { state.mainRecognition.stop(); } catch {}
    }
    state.mainRecognition = null;
    state.listening       = false;
    if (nodes.voiceToggle) nodes.voiceToggle.classList.remove('is-recording');
    if (nodes.orb) nodes.orb.classList.remove('is-recording');
    if (resetMode && !state.processing && !state.executing) resetToIdle();
  };

  const startMainRecognition = () => {
    if (!state.voiceEnabled || !SpeechRec) {
      focusInput('Voice input is not available.');
      return;
    }
    stopMainRecognition(false);

    // Interrupt any ongoing TTS (barge-in)
    if (canSpeak) window.speechSynthesis.cancel();

    setMode('listening');

    const rec = new SpeechRec();
    rec.lang             = 'en-US';
    rec.continuous       = false;
    rec.interimResults   = false;
    rec.maxAlternatives  = 1;

    rec.onstart = () => {
      state.listening = true;
      if (nodes.voiceToggle) nodes.voiceToggle.classList.add('is-recording');
      if (nodes.orb) nodes.orb.classList.add('is-recording');
    };

    rec.onresult = (event) => {
      const transcript = Array.from(event.results || [])
        .map((r) => String(r?.[0]?.transcript || ''))
        .join(' ');
      const cmd = norm(transcript);
      state.listening = false;
      state.mainRecognition = null;
      if (nodes.voiceToggle) nodes.voiceToggle.classList.remove('is-recording');

      if (!cmd) { resetToIdle(); return; }
      submitCommand(cmd, 'voice');
    };

    rec.onerror = (e) => {
      state.listening = false;
      state.mainRecognition = null;
      if (nodes.voiceToggle) nodes.voiceToggle.classList.remove('is-recording');
      const errType = String(e?.error || '');
      if (['not-allowed', 'service-not-allowed', 'audio-capture'].includes(errType)) {
        focusInput('Microphone permission denied.');
        return;
      }
      if (!state.processing && !state.executing) resetToIdle();
    };

    rec.onend = () => {
      state.listening = false;
      state.mainRecognition = null;
      if (nodes.voiceToggle) nodes.voiceToggle.classList.remove('is-recording');
      if (!state.processing && !state.executing) resetToIdle();
    };

    state.mainRecognition = rec;
    try { rec.start(); } catch { state.mainRecognition = null; state.listening = false; focusInput(); }
  };

  const toggleMainRecognition = () => {
    if (state.listening) { stopMainRecognition(true); return; }
    startMainRecognition();
  };

  // ── Wake-word monitoring (continuous background listener) ────
  const stopWakeMonitor = () => {
    if (state.wakeMonitor) {
      try { state.wakeMonitor.onend = null; state.wakeMonitor.abort(); } catch {}
      state.wakeMonitor = null;
    }
  };

  const startWakeMonitor = () => {
    if (!state.voiceEnabled || !SpeechRec) return;
    if (state.wakeMonitor) return; // already running

    const rec = new SpeechRec();
    rec.lang            = 'en-US';
    rec.continuous      = true;
    rec.interimResults  = true;
    rec.maxAlternatives = 1;

    rec.onresult = (event) => {
      const idx = event.resultIndex;
      const result = event.results[idx];
      if (!result) return;
      const transcript = norm(String(result[0]?.transcript || '')).toLowerCase();

      if (transcript.includes(WAKE_WORD)) {
        // Wake word detected — stop monitor, start command recognition
        stopWakeMonitor();
        stopMainRecognition(false);

        speak('Hey, I\'m listening!', true);
        // Navigate to AI page if not already here (no-op if on page)
        if (!document.body.classList.contains('ai-agent-page')) {
          window.location.href = 'ai-agent.php?autostart=1';
          return;
        }

        setTimeout(() => startMainRecognition(), 600);
      }
    };

    rec.onend = () => {
      // Restart if still enabled and not actively listening
      if (state.voiceEnabled && !state.listening && !state.processing && !state.executing) {
        state.wakeMonitor = null;
        setTimeout(startWakeMonitor, 1200);
      } else {
        state.wakeMonitor = null;
      }
    };

    rec.onerror = (e) => {
      state.wakeMonitor = null;
      const errType = String(e?.error || '');
      if (['not-allowed', 'service-not-allowed', 'audio-capture'].includes(errType)) return;
      // Retry after a short delay
      setTimeout(startWakeMonitor, 2500);
    };

    state.wakeMonitor = rec;
    try { rec.start(); } catch { state.wakeMonitor = null; }
  };

  // ── Helper resets ────────────────────────────────────────────
  const resetToIdle = () => {
    setMode('idle');
    // Restart wake monitor after returning to idle
    if (state.voiceEnabled) {
      setTimeout(startWakeMonitor, 300);
    }
  };

  const focusInput = (msg = '') => {
    stopMainRecognition(false);
    if (nodes.input) { nodes.input.focus(); nodes.input.select?.(); }
    if (msg) setMode('idle', { step: msg });
    else resetToIdle();
  };

  // ── Status polling ───────────────────────────────────────────
  const refreshStatus = async (force = false) => {
    if (!force && (state.listening || state.processing || state.currentTaskId)) return;
    try {
      const res  = await fetch(buildUrl('assistant_status'), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const data = await res.json();
      if (!data?.success) return;

      state.configured = Boolean(data.configured);
      state.lastQueue  = data.queue || {};

      if (data.model && !store.get(STORAGE.model, '')) {
        state.selectedModel = String(data.model);
        if (nodes.modelSelect) nodes.modelSelect.value = state.selectedModel;
      }

      const recent     = Array.isArray(data.recent_tasks) ? data.recent_tasks : [];
      const activeTask = data.active_task || null;
      renderHistory(recent, activeTask);

      const activeId     = String(activeTask?.id || '');
      const activeStatus = String(activeTask?.status || '').toLowerCase();
      if (activeId && ['queued', 'planning', 'executing', 'running'].includes(activeStatus)) {
        if (state.currentTaskId !== activeId) {
          beginTaskTracking(activeId, activeTask?.plan?.voice_reply || activeTask?.assistant_message || '');
        }
        return;
      }

      if (!state.listening && !state.processing && !state.currentTaskId) {
        setMode('idle');
      }
    } catch {}
  };

  // ── UI state setters ─────────────────────────────────────────
  const syncPrefs = () => {
    if (nodes.voiceEnabled)    nodes.voiceEnabled.checked    = state.voiceEnabled;
    if (nodes.autoMode)        nodes.autoMode.checked        = state.autoMode;
    if (nodes.whatsappEnabled) nodes.whatsappEnabled.checked = state.whatsappEnabled;
    if (nodes.modelSelect && state.selectedModel) nodes.modelSelect.value = state.selectedModel;

    shell.dataset.voiceEnabled    = String(state.voiceEnabled);
    shell.dataset.autoMode        = String(state.autoMode);
    shell.dataset.whatsappEnabled = String(state.whatsappEnabled);
    shell.dataset.selectedModel   = state.selectedModel || '';
  };

  const setSettingsOpen = (open) => {
    state.settingsOpen = Boolean(open);
    shell.dataset.settingsOpen = state.settingsOpen ? 'true' : 'false';
    store.set(STORAGE.settingsOpen, state.settingsOpen ? '1' : '0');
    if (nodes.settingsPanel)  nodes.settingsPanel.hidden = !state.settingsOpen;
    if (nodes.settingsToggle) nodes.settingsToggle.setAttribute('aria-expanded', String(state.settingsOpen));
  };

  const setHistoryOpen = (open) => {
    state.historyOpen = Boolean(open);
    shell.dataset.historyOpen = state.historyOpen ? 'true' : 'false';
    store.set(STORAGE.historyOpen, state.historyOpen ? '1' : '0');
    if (nodes.historyPanel)  nodes.historyPanel.hidden = !state.historyOpen;
    if (nodes.historyToggle) nodes.historyToggle.setAttribute('aria-expanded', String(state.historyOpen));
    if (state.historyOpen)   refreshStatus(true);
  };

  // ── Event bindings ───────────────────────────────────────────
  const bindEvents = () => {
    // Orb click
    if (nodes.orb) {
      nodes.orb.addEventListener('click', () => {
        // Barge-in: cancel TTS immediately
        if (canSpeak) window.speechSynthesis.cancel();
        if (!state.voiceEnabled) { focusInput('Voice disabled — use the text field.'); return; }
        toggleMainRecognition();
      });
    }

    // Voice toggle button
    if (nodes.voiceToggle) {
      nodes.voiceToggle.addEventListener('click', () => {
        if (canSpeak) window.speechSynthesis.cancel();
        if (!state.voiceEnabled) { focusInput('Voice disabled in settings.'); return; }
        toggleMainRecognition();
      });
    }

    // Send button + Enter key
    if (nodes.send && nodes.input) {
      const handleSend = () => {
        const cmd = norm(nodes.input.value);
        if (!cmd) {
          if (state.voiceEnabled) startMainRecognition();
          else nodes.input.focus();
          return;
        }
        submitCommand(cmd, 'web');
      };
      nodes.send.addEventListener('click', handleSend);
      nodes.input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); handleSend(); }
      });
    }

    // Settings toggle
    if (nodes.settingsToggle) {
      nodes.settingsToggle.addEventListener('click', () => setSettingsOpen(!state.settingsOpen));
    }

    // History toggle
    if (nodes.historyToggle) {
      nodes.historyToggle.addEventListener('click', () => setHistoryOpen(!state.historyOpen));
    }

    // History replay (event delegation)
    if (nodes.historyList) {
      nodes.historyList.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-replay-goal]');
        if (!btn) return;
        const goal = norm(btn.dataset.replayGoal);
        if (goal) submitCommand(goal, 'web');
      });
    }

    // Preference toggles
    if (nodes.voiceEnabled) {
      nodes.voiceEnabled.addEventListener('change', () => {
        state.voiceEnabled = nodes.voiceEnabled.checked;
        store.set(STORAGE.voiceEnabled, state.voiceEnabled ? '1' : '0');
        syncPrefs();
        if (!state.voiceEnabled) {
          stopWakeMonitor();
          stopMainRecognition(true);
        } else {
          startWakeMonitor();
        }
      });
    }

    if (nodes.autoMode) {
      nodes.autoMode.addEventListener('change', () => {
        state.autoMode = nodes.autoMode.checked;
        store.set(STORAGE.autoMode, state.autoMode ? '1' : '0');
        syncPrefs();
      });
    }

    if (nodes.whatsappEnabled) {
      nodes.whatsappEnabled.addEventListener('change', () => {
        state.whatsappEnabled = nodes.whatsappEnabled.checked;
        store.set(STORAGE.whatsappEnabled, state.whatsappEnabled ? '1' : '0');
        syncPrefs();
      });
    }

    if (nodes.modelSelect) {
      nodes.modelSelect.addEventListener('change', () => {
        state.selectedModel = norm(nodes.modelSelect.value);
        store.set(STORAGE.model, state.selectedModel);
        syncPrefs();
      });
    }

    // Quick-command cards
    nodes.shortcuts.forEach((btn) => {
      btn.addEventListener('click', () => {
        const cmd = norm(btn.dataset.agentCommand || '');
        if (!cmd) return;
        if (cmd.toLowerCase() === 'stop listening') { stopMainRecognition(true); return; }
        submitCommand(cmd, 'web');
      });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        if (state.listening)  stopMainRecognition(true);
        if (state.historyOpen) setHistoryOpen(false);
        if (canSpeak) window.speechSynthesis.cancel();
      }
      // Ctrl/Cmd + Space → toggle voice
      if ((e.ctrlKey || e.metaKey) && e.code === 'Space') {
        e.preventDefault();
        if (!state.voiceEnabled) return;
        toggleMainRecognition();
      }
    });

    // Cleanup on unload
    window.addEventListener('beforeunload', () => {
      stopWakeMonitor();
      stopMainRecognition(false);
      clearTimer('taskTimer');
      clearTimer('statusTimer');
      clearTimer('doneTimer');
    });
  };

  // ── Preference initialisation ────────────────────────────────
  const initPrefs = () => {
    state.settingsOpen    = store.get(STORAGE.settingsOpen, '0') === '1';
    state.historyOpen     = store.get(STORAGE.historyOpen,  '0') === '1';
    state.voiceEnabled    = store.get(STORAGE.voiceEnabled, '1') !== '0';
    state.autoMode        = store.get(STORAGE.autoMode,     '1') !== '0';
    state.whatsappEnabled = store.get(STORAGE.whatsappEnabled, '1') !== '0';
    state.selectedModel   = norm(store.get(STORAGE.model, state.selectedModel || bootstrap.model || '')) || String(bootstrap.model || '');

    if (Array.isArray(bootstrap.modelOptions) && bootstrap.modelOptions.length) {
      if (!state.selectedModel || !bootstrap.modelOptions.includes(state.selectedModel)) {
        state.selectedModel = String(bootstrap.modelOptions[0] || state.selectedModel || '');
      }
    }

    syncPrefs();
    setSettingsOpen(state.settingsOpen);
    setHistoryOpen(state.historyOpen);
  };

  // ── Init ─────────────────────────────────────────────────────
  const init = async () => {
    state.configured = bootstrap.configured !== false;
    initPrefs();
    bindEvents();

    setMode('idle');
    await refreshStatus(true);

    // Start background wake-word monitor
    if (state.voiceEnabled && SpeechRec) {
      setTimeout(startWakeMonitor, 800);
    }

    // Auto-start listening if redirected from wake word on another page
    if (new URLSearchParams(window.location.search).get('autostart') === '1') {
      setTimeout(() => startMainRecognition(), 900);
    }

    // Periodic status refresh
    clearTimer('statusTimer');
    state.statusTimer = setInterval(() => refreshStatus(false), 14000);
  };

  init();
});
