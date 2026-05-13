/**
 * Live Studio — live.js
 * Handles WebRTC broadcasting/viewing, signalling, chat, viewers.
 *
 * KEY FIX vs previous version:
 *  - ICE candidates queued until remoteDescription is set (prevents black screen)
 *  - Host sends local tracks immediately when peer is created
 *  - Screen-share replaces main video; face-cam floats as PiP
 *  - Viewer list rendered with avatars
 *  - Chat rendered with avatars + name + timestamp
 *  - Join/leave toast notifications
 */
document.addEventListener('DOMContentLoaded', () => {
  /* ──────────────────────────────────────────────────
     Bootstrap & params
  ────────────────────────────────────────────────── */
  const bootstrap      = window.liveBootstrap || {};
  const currentUserId  = Number(bootstrap?.currentUser?.id || 0);
  const currentUser    = bootstrap?.currentUser || {};
  const requestedMode  = String(bootstrap?.mode || '').toLowerCase();
  const requestedStreamId = Number(bootstrap?.streamId || 0);

  /* ──────────────────────────────────────────────────
     DOM refs
  ────────────────────────────────────────────────── */
  const $ = id => document.getElementById(id);

  const refs = {
    // Video
    mainVideo:       $('lsMainVideo'),
    localVideo:      $('lsLocalVideo'),
    pipCam:          $('lsPipCam'),
    placeholder:     $('lsPlaceholder'),
    stageLiveBadge:  $('lsStageLiveBadge'),
    stageViewerBadge:$('lsStageViewerBadge'),
    stageTitle:      $('lsStageTitle'),
    // Header
    headerLivePill:  $('lsHeaderLivePill'),
    headerRolePill:  $('lsHeaderRolePill'),
    headerViewerPill:$('lsHeaderViewerPill'),
    headerViewerNum: $('lsHeaderViewerNum'),
    headerStreamName:$('lsHeaderStreamName'),
    liveIndicator:   $('lsLiveDot'),
    // Controls
    startBtn:   $('lsStartBtn'),
    endBtn:     $('lsEndBtn'),
    joinBtn:    $('lsJoinBtn'),
    leaveBtn:   $('lsLeaveBtn'),
    muteBtn:    $('lsMuteBtn'),
    camBtn:     $('lsCamBtn'),
    screenBtn:  $('lsScreenBtn'),
    // Setup
    titleInput:      $('lsTitleInput'),
    descInput:       $('lsDescInput'),
    categoryInput:   $('lsCategoryInput'),
    visibilityInput: $('lsVisibilityInput'),
    // Streams list
    streamsList:     $('lsStreamsList'),
    streamsCount:    $('lsStreamsCount'),
    refreshListBtn:  $('lsRefreshBtn'),
    // Viewers
    viewersList: $('lsViewersList'),
    viewerCount: $('lsViewerCount'),
    // Chat
    chatLog:     $('lsChatLog'),
    chatInput:   $('lsChatInput'),
    chatSendBtn: $('lsChatSendBtn'),
    // Status
    statusStrip: $('lsStatusStrip'),
    // Notifications
    joinNotification: $('lsJoinNotification'),
    joinToastText:    $('lsJoinToastText'),
    // Toast stack
    toastStack: $('lsToastStack'),
    // Setup panel toggle
    setupToggle: $('lsSetupToggle'),
    setupBody:   $('lsSetupBody'),
  };

  /* ──────────────────────────────────────────────────
     State
  ────────────────────────────────────────────────── */
  const state = {
    streams:        [],
    currentStream:  null,
    role:           'idle',   // 'idle' | 'host' | 'viewer'
    joined:         false,
    localStream:    null,     // camera/mic
    screenStream:   null,     // screen capture
    isScreenSharing:false,
    remoteStream:   null,
    hostPeers:      new Map(), // viewerUserId → RTCPeerConnection
    pendingHostCandidates: new Map(), // viewerUserId → []
    viewerPeer:     null,
    pendingViewerCandidates: [],
    heartbeatTimer: null,
    signalTimer:    null,
    chatTimer:      null,
    listTimer:      null,
    lastSignalId:   0,
    lastChatId:     0,
    renderedChatIds:new Set(),
    viewers:        new Map(), // userId → user obj
    muted:          false,
    camOff:         false,
    busy:           false,
  };

  const rtcConfig = {
    iceServers: [
      { urls: ['stun:stun.l.google.com:19302', 'stun:stun1.l.google.com:19302'] }
    ]
  };

  /* ──────────────────────────────────────────────────
     Utilities
  ────────────────────────────────────────────────── */
  const esc = s => String(s || '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;');

  const displayName = u => {
    const n = `${u?.first_name || ''} ${u?.last_name || ''}`.trim();
    return n || 'Member';
  };

  const initials = u => {
    const f = String(u?.first_name || '?')[0];
    const l = String(u?.last_name  || '?')[0];
    return (f + l).toUpperCase();
  };

  const avatarEl = (user, size = 30) => {
    const url = user?.avatar_url || '';
    const name = displayName(user);
    if (url) {
      return `<img src="${esc(url)}" alt="${esc(name)}" style="width:${size}px;height:${size}px;border-radius:50%;object-fit:cover;">`;
    }
    return `<span style="font-size:${Math.round(size*0.38)}px;font-weight:700;">${esc(initials(user))}</span>`;
  };

  const humanTime = iso => {
    if (!iso) return 'now';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return 'now';
    const s = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
    if (s < 60) return `${s}s`;
    if (s < 3600) return `${Math.floor(s/60)}m`;
    return `${Math.floor(s/3600)}h`;
  };

  const streamId = () => Number(state.currentStream?.id || 0);

  /* ──────────────────────────────────────────────────
     Toast / Status
  ────────────────────────────────────────────────── */
  const setStatus = (msg, tone = '') => {
    if (!refs.statusStrip) return;
    refs.statusStrip.className = 'live-status-strip' + (tone ? ` is-${tone}` : '');
    refs.statusStrip.innerHTML = `<span class="live-status-dot"></span><span>${esc(msg)}</span>`;
  };

  const showToast = (msg, tone = 'info', ms = 3500) => {
    if (!refs.toastStack) return;
    const el = document.createElement('div');
    el.className = `live-toast toast-${tone}`;
    el.textContent = msg;
    refs.toastStack.appendChild(el);
    setTimeout(() => {
      el.style.opacity = '0';
      el.style.transition = 'opacity 0.3s ease';
      setTimeout(() => el.remove(), 350);
    }, ms);
  };

  const showJoinToast = msg => {
    if (!refs.joinNotification || !refs.joinToastText) return;
    refs.joinToastText.textContent = msg;
    refs.joinNotification.classList.add('is-visible');
    clearTimeout(refs.joinNotification._timer);
    refs.joinNotification._timer = setTimeout(() => {
      refs.joinNotification.classList.remove('is-visible');
    }, 3000);
  };

  const setBusy = v => { state.busy = Boolean(v); syncControls(); };

  /* ──────────────────────────────────────────────────
     API helpers
  ────────────────────────────────────────────────── */
  const apiGet = async (mode, query = {}) => {
    const url = new URL('profile.php', location.href);
    url.searchParams.set('action', 'profile_live_stream');
    url.searchParams.set('mode', mode);
    for (const [k, v] of Object.entries(query || {})) {
      if (v !== null && v !== undefined && v !== '') url.searchParams.set(String(k), String(v));
    }
    const r = await fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const d = await r.json().catch(() => ({}));
    if (!r.ok || !d?.success) throw new Error(d?.message || `API error (${r.status})`);
    return d;
  };

  const apiPost = async (mode, body = {}) => {
    const r = await fetch('profile.php?action=profile_live_stream', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ mode, ...body }),
    });
    const d = await r.json().catch(() => ({}));
    if (!r.ok || !d?.success) throw new Error(d?.message || `API error (${r.status})`);
    return d;
  };

  /* ──────────────────────────────────────────────────
     UI Sync
  ────────────────────────────────────────────────── */
  const syncControls = () => {
    const isHost   = state.role === 'host';
    const isViewer = state.role === 'viewer';
    const sid      = streamId();
    const hasLocal = !!state.localStream;

    if (refs.startBtn)  refs.startBtn.disabled   = state.busy || isHost || isViewer;
    if (refs.endBtn)    refs.endBtn.disabled     = state.busy || !isHost || !sid;
    if (refs.joinBtn)   refs.joinBtn.disabled    = state.busy || isHost || !sid || (isViewer && state.joined);
    if (refs.leaveBtn)  refs.leaveBtn.disabled   = state.busy || !isViewer || !state.joined;
    if (refs.muteBtn)   refs.muteBtn.disabled    = state.busy || !isHost || !hasLocal;
    if (refs.camBtn)    refs.camBtn.disabled     = state.busy || !isHost || !hasLocal;
    if (refs.screenBtn) refs.screenBtn.disabled  = state.busy || !isHost;

    if (refs.muteBtn) {
      refs.muteBtn.innerHTML = state.muted
        ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="1" y1="1" x2="23" y2="23"/><path d="M9 9v3a3 3 0 0 0 5.12 2.12M15 9.34V4a3 3 0 0 0-5.94-.6"/><path d="M17 16.95A7 7 0 0 1 5 12v-2m14 0v2a7 7 0 0 1-.11 1.23"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg><span>Unmute</span>`
        : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg><span>Mute</span>`;
      refs.muteBtn.classList.toggle('is-active', state.muted);
    }

    if (refs.camBtn) {
      refs.camBtn.innerHTML = state.camOff
        ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 16v1a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2m5.66 0H14a2 2 0 0 1 2 2v3.34l1 1L23 7v10"/><line x1="1" y1="1" x2="23" y2="23"/></svg><span>Cam On</span>`
        : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg><span>Cam Off</span>`;
      refs.camBtn.classList.toggle('is-active', state.camOff);
    }

    if (refs.screenBtn) {
      refs.screenBtn.classList.toggle('is-screen-active', state.isScreenSharing);
      if (refs.screenBtn.querySelector('span')) {
        refs.screenBtn.querySelector('span').textContent = state.isScreenSharing ? 'Stop Share' : 'Share Screen';
      }
    }

    syncVideoElements();
    syncBadges();
  };

  const syncVideoElements = () => {
    const isHost   = state.role === 'host';
    const isViewer = state.role === 'viewer';

    // Main video (screen share takes priority for host, remote stream for viewer)
    if (refs.mainVideo) {
      if (isHost && state.screenStream) {
        refs.mainVideo.srcObject = state.screenStream;
        refs.mainVideo.hidden = false;
      } else if (isViewer && state.remoteStream) {
        refs.mainVideo.srcObject = state.remoteStream;
        refs.mainVideo.hidden = false;
        refs.mainVideo.muted = false;
      } else {
        refs.mainVideo.hidden = true;
      }
    }

    // Local camera as PiP (host: show when screen sharing, otherwise main; viewer: never)
    if (refs.localVideo && refs.pipCam) {
      if (isHost && state.localStream) {
        if (state.isScreenSharing) {
          // Show PiP cam on top of screen share
          refs.localVideo.srcObject = state.localStream;
          refs.localVideo.hidden = false;
          refs.pipCam.classList.remove('is-hidden');
        } else {
          // Camera is main view
          if (refs.mainVideo) {
            refs.mainVideo.srcObject = state.localStream;
            refs.mainVideo.hidden = false;
          }
          refs.localVideo.hidden = true;
          refs.pipCam.classList.add('is-hidden');
        }
      } else {
        refs.localVideo.hidden = true;
        refs.pipCam.classList.add('is-hidden');
      }
    }

    // Placeholder
    if (refs.placeholder) {
      const hasVideo = (isHost && state.localStream) || (isViewer && state.remoteStream) || (isHost && state.screenStream);
      refs.placeholder.style.display = hasVideo ? 'none' : 'flex';
      if (!hasVideo) {
        const [h3, p] = refs.placeholder.querySelectorAll('h3, p');
        if (h3 && p) {
          if (isHost && !state.localStream) {
            h3.textContent = 'Camera not started';
            p.textContent = 'Click Start Live to enable your camera and begin broadcasting.';
          } else if (isViewer) {
            h3.textContent = 'Waiting for stream...';
            p.textContent = 'Connecting to broadcaster. The stream will appear shortly.';
          } else {
            h3.textContent = 'No stream selected';
            p.textContent = 'Pick a live stream from the list below or start your own broadcast.';
          }
        }
      }
    }
  };

  const syncBadges = () => {
    const isHost   = state.role === 'host';
    const isViewer = state.role === 'viewer';
    const viewers  = Number(state.currentStream?.viewer_count || state.viewers.size || 0);

    if (refs.liveIndicator) refs.liveIndicator.classList.toggle('is-offline', !state.joined);
    if (refs.stageLiveBadge) refs.stageLiveBadge.style.display = state.joined ? 'inline-flex' : 'none';
    if (refs.stageViewerBadge) {
      refs.stageViewerBadge.style.display = state.joined ? 'inline-flex' : 'none';
      const n = refs.stageViewerBadge.querySelector('#lsStageViewerNum');
      if (n) n.textContent = String(viewers);
    }
    if (refs.stageTitle) {
      refs.stageTitle.textContent = state.currentStream?.title || '';
      refs.stageTitle.style.display = state.currentStream?.title ? 'block' : 'none';
    }

    if (refs.headerLivePill) refs.headerLivePill.className = 'live-pill' + (state.joined ? (isHost ? ' is-live' : ' is-connected') : '');
    if (refs.headerLivePill) refs.headerLivePill.textContent = state.joined ? (isHost ? '● ON AIR' : '● Connected') : '○ Not Live';
    if (refs.headerRolePill) refs.headerRolePill.textContent = isHost ? 'Role: Host' : isViewer ? 'Role: Viewer' : 'Role: Idle';
    if (refs.headerViewerNum) refs.headerViewerNum.textContent = String(viewers);
    if (refs.headerStreamName) refs.headerStreamName.textContent = state.currentStream?.title
      ? `• ${state.currentStream.title}`
      : '';
    if (refs.viewerCount) refs.viewerCount.textContent = String(viewers);
  };

  const renderStreamsList = () => {
    if (!refs.streamsList) return;
    if (refs.streamsCount) refs.streamsCount.textContent = String(state.streams.length);

    if (!state.streams.length) {
      refs.streamsList.innerHTML = `
        <div class="live-empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M21 2H3v16h5l3 3 3-3h7V2zM9 11v-1M12 11V8M15 11V6"/>
          </svg>
          <p>No active streams right now.<br>Be the first to go live!</p>
        </div>`;
      return;
    }

    const sid = streamId();
    refs.streamsList.innerHTML = state.streams.map(s => {
      const id      = Number(s?.id || 0);
      const host    = s?.host || {};
      const name    = displayName(host);
      const ini     = initials(host);
      const avatarUrl = host?.avatar_url || '';
      const title   = String(s?.title || '').trim() || `${name}'s stream`;
      const viewers = Number(s?.viewer_count || 0);
      const isMyStream = Number(s?.host_user_id || 0) === currentUserId;
      const selected = id === sid;

      return `
        <button type="button" class="live-stream-entry ${selected ? 'is-selected' : ''}" data-stream-id="${id}">
          <div class="live-entry-avatar">
            ${avatarUrl
              ? `<img src="${esc(avatarUrl)}" alt="${esc(name)}">`
              : `<span>${esc(ini)}</span>`}
            <span class="live-dot-badge"></span>
          </div>
          <div class="live-entry-body">
            <span class="live-entry-title">${esc(title)}</span>
            <span class="live-entry-meta">${esc(name)} · ${viewers} viewer${viewers===1?'':'s'}</span>
          </div>
          <div class="live-entry-actions">
            ${isMyStream
              ? `<span class="live-mini-action is-host">Your stream</span>`
              : `<span class="live-mini-action" data-quick-join="${id}">Join →</span>`}
          </div>
        </button>`;
    }).join('');

    refs.streamsList.querySelectorAll('[data-stream-id]').forEach(btn => {
      btn.addEventListener('click', async e => {
        if (e.target.closest('[data-quick-join]')) return; // handled separately
        const id = Number(btn.dataset.streamId);
        if (id) await selectStream(id, false);
      });
    });

    refs.streamsList.querySelectorAll('[data-quick-join]').forEach(btn => {
      btn.addEventListener('click', async e => {
        e.stopPropagation();
        const id = Number(btn.dataset.quickJoin);
        if (id) await selectStream(id, true);
      });
    });
  };

  const renderViewers = () => {
    if (!refs.viewersList) return;
    if (!state.viewers.size) {
      refs.viewersList.innerHTML = '<span style="font-size:.66rem;color:#475569;padding:4px">No viewers yet</span>';
      return;
    }
    refs.viewersList.innerHTML = Array.from(state.viewers.values()).map(u => {
      const name = displayName(u);
      const url  = u?.avatar_url || '';
      const ini  = initials(u);
      return `
        <div class="live-viewer-bubble" title="${esc(name)}">
          <div class="live-viewer-avatar">
            ${url ? `<img src="${esc(url)}" alt="${esc(name)}">` : `<span>${esc(ini)}</span>`}
          </div>
          <span class="live-viewer-tooltip">${esc(name)}</span>
        </div>`;
    }).join('');
  };

  /* ──────────────────────────────────────────────────
     Chat
  ────────────────────────────────────────────────── */
  const resetChat = () => {
    state.renderedChatIds.clear();
    state.lastChatId = 0;
    if (refs.chatLog) {
      refs.chatLog.innerHTML = `
        <div class="live-chat-empty">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
          </svg>
          <p>Chat will appear here after joining a stream.</p>
        </div>`;
    }
  };

  const appendChatMessages = msgs => {
    if (!refs.chatLog || !Array.isArray(msgs) || !msgs.length) return;

    // Remove empty state placeholder
    const empty = refs.chatLog.querySelector('.live-chat-empty');
    if (empty) empty.remove();

    const nearBottom = refs.chatLog.scrollHeight - refs.chatLog.scrollTop - refs.chatLog.clientHeight < 50;
    const frag = document.createDocumentFragment();

    msgs.forEach(m => {
      const id = Number(m?.id || 0);
      if (!id || state.renderedChatIds.has(id)) return;
      state.renderedChatIds.add(id);
      state.lastChatId = Math.max(state.lastChatId, id);

      const user = m?.user || {};
      const name = displayName(user);
      const body = String(m?.body || '').trim();
      const ts   = humanTime(m?.created_at);
      const url  = user?.avatar_url || '';
      const ini  = initials(user);
      const isSelf = Number(user?.id || 0) === currentUserId;

      const div = document.createElement('div');
      div.className = 'live-chat-msg';
      div.innerHTML = `
        <div class="live-chat-avatar">
          ${url ? `<img src="${esc(url)}" alt="${esc(name)}">` : `<span>${esc(ini)}</span>`}
        </div>
        <div class="live-chat-bubble ${isSelf ? 'is-self' : ''}">
          <div class="sender">
            <span style="${isSelf ? 'color:#bbf7d0' : ''}">${esc(name)}</span>
            <span class="ts">${esc(ts)}</span>
          </div>
          <div class="body">${esc(body)}</div>
        </div>`;
      frag.appendChild(div);
    });

    refs.chatLog.appendChild(frag);
    if (nearBottom) refs.chatLog.scrollTop = refs.chatLog.scrollHeight;
  };

  const sendChat = async () => {
    const body = String(refs.chatInput?.value || '').trim();
    if (!body) return;
    if (!state.joined || !streamId()) {
      showToast('Join a stream before chatting.', 'error'); return;
    }
    try {
      const d = await apiPost('chat_send', { stream_id: streamId(), body });
      if (d.message) appendChatMessages([d.message]);
      if (refs.chatInput) refs.chatInput.value = '';
    } catch (err) {
      showToast(err.message || 'Could not send message.', 'error');
    }
  };

  /* ──────────────────────────────────────────────────
     WebRTC — Signal exchange
  ────────────────────────────────────────────────── */
  const sendSignal = async (type, payload, targetUserId = 0) => {
    if (!state.joined || !streamId()) return;
    try {
      await apiPost('signal', {
        stream_id:      streamId(),
        target_user_id: targetUserId > 0 ? targetUserId : null,
        signal_type:    type,
        payload,
      });
    } catch (_) {}
  };

  /* ── HOST: manage a peer per viewer ─────────────── */
  const getOrCreateHostPeer = viewerUserId => {
    const key = Number(viewerUserId);
    if (!key) return null;
    if (state.hostPeers.has(key)) return state.hostPeers.get(key);

    const pc = new RTCPeerConnection(rtcConfig);
    state.pendingHostCandidates.set(key, []);

    // Add all local tracks immediately
    const localStreamToUse = state.screenStream || state.localStream;
    if (localStreamToUse) {
      localStreamToUse.getTracks().forEach(t => pc.addTrack(t, localStreamToUse));
    }
    // Also add camera audio if screen sharing (combined audio)
    if (state.screenStream && state.localStream) {
      state.localStream.getAudioTracks().forEach(t => {
        try { pc.addTrack(t, state.localStream); } catch (_) {}
      });
    }

    pc.onicecandidate = e => {
      if (e.candidate) sendSignal('candidate', { candidate: e.candidate }, key);
    };

    pc.onconnectionstatechange = () => {
      if (['disconnected','failed','closed'].includes(pc.connectionState)) {
        closeHostPeer(key);
        removeViewer(key);
      }
    };

    state.hostPeers.set(key, pc);
    return pc;
  };

  const closeHostPeer = key => {
    const k = Number(key);
    const pc = state.hostPeers.get(k);
    if (!pc) return;
    try { pc.close(); } catch (_) {}
    state.hostPeers.delete(k);
    state.pendingHostCandidates.delete(k);
  };

  const closeAllHostPeers = () => {
    Array.from(state.hostPeers.keys()).forEach(closeHostPeer);
  };

  /* ── VIEWER: single peer to host ─────────────────── */
  const createViewerPeer = async () => {
    closeViewerPeer();

    const hostUserId = Number(state.currentStream?.host_user_id || 0);
    if (!hostUserId) return;

    const pc = new RTCPeerConnection(rtcConfig);
    state.viewerPeer = pc;
    state.pendingViewerCandidates = [];

    // Receive host's video+audio
    pc.addTransceiver('video', { direction: 'recvonly' });
    pc.addTransceiver('audio', { direction: 'recvonly' });

    // When remote tracks arrive, bind to video element
    pc.ontrack = e => {
      const stream = e.streams?.[0] || (() => {
        if (!state.remoteStream) state.remoteStream = new MediaStream();
        state.remoteStream.addTrack(e.track);
        return state.remoteStream;
      })();
      state.remoteStream = stream;
      if (refs.mainVideo) {
        refs.mainVideo.srcObject = stream;
        refs.mainVideo.muted = false;
        refs.mainVideo.hidden = false;
        refs.mainVideo.play().catch(() => {
          // Autoplay blocked → show unmute hint
          showToast('Click the video to unmute and enable audio.', 'info', 5000);
        });
      }
      if (refs.placeholder) refs.placeholder.style.display = 'none';
      syncControls();
    };

    pc.onicecandidate = e => {
      if (e.candidate) sendSignal('candidate', { candidate: e.candidate }, hostUserId);
    };

    pc.onconnectionstatechange = () => {
      if (pc.connectionState === 'connected') {
        setStatus('Stream connected ✓', 'ok');
        showToast('Stream connected!', 'ok');
      }
      if (['failed','disconnected','closed'].includes(pc.connectionState)) {
        setStatus('Stream connection lost. Retrying...', 'error');
      }
    };

    // Create offer
    const offer = await pc.createOffer();
    await pc.setLocalDescription(offer);
    await sendSignal('offer', { sdp: pc.localDescription }, hostUserId);
  };

  const closeViewerPeer = () => {
    if (state.viewerPeer) { try { state.viewerPeer.close(); } catch (_) {} state.viewerPeer = null; }
    if (state.remoteStream) { state.remoteStream.getTracks().forEach(t => t.stop()); state.remoteStream = null; }
    if (refs.mainVideo) { refs.mainVideo.srcObject = null; }
    state.pendingViewerCandidates = [];
  };

  /* ── Signal dispatcher ───────────────────────────── */
  const handleSignal = async sig => {
    const senderId  = Number(sig?.sender_id || 0);
    const type      = String(sig?.signal_type || '').toLowerCase();
    const payload   = sig?.payload || {};

    if (state.role === 'host') {
      if (!senderId || senderId === currentUserId) return;
      const pc = getOrCreateHostPeer(senderId);
      if (!pc) return;

      if (type === 'offer') {
        const sdp = payload?.sdp || payload;
        if (!sdp?.type || !sdp?.sdp) return;
        await pc.setRemoteDescription(new RTCSessionDescription(sdp));
        // Flush queued candidates
        const queued = state.pendingHostCandidates.get(senderId) || [];
        for (const c of queued) await pc.addIceCandidate(new RTCIceCandidate(c)).catch(() => {});
        state.pendingHostCandidates.set(senderId, []);
        const answer = await pc.createAnswer();
        await pc.setLocalDescription(answer);
        await sendSignal('answer', { sdp: pc.localDescription }, senderId);
        return;
      }

      if (type === 'candidate') {
        const cand = payload?.candidate || payload;
        if (!cand?.candidate) return;
        if (pc.remoteDescription) {
          await pc.addIceCandidate(new RTCIceCandidate(cand)).catch(() => {});
        } else {
          const q = state.pendingHostCandidates.get(senderId) || [];
          q.push(cand);
          state.pendingHostCandidates.set(senderId, q);
        }
        return;
      }

      if (type === 'bye') { closeHostPeer(senderId); removeViewer(senderId); }
    }

    if (state.role === 'viewer') {
      const hostUserId = Number(state.currentStream?.host_user_id || 0);
      if (senderId !== hostUserId) return;
      const pc = state.viewerPeer;
      if (!pc) return;

      if (type === 'answer') {
        const sdp = payload?.sdp || payload;
        if (!sdp?.type || !sdp?.sdp) return;
        await pc.setRemoteDescription(new RTCSessionDescription(sdp));
        // Flush queued candidates
        for (const c of state.pendingViewerCandidates) await pc.addIceCandidate(new RTCIceCandidate(c)).catch(() => {});
        state.pendingViewerCandidates = [];
        return;
      }

      if (type === 'candidate') {
        const cand = payload?.candidate || payload;
        if (!cand?.candidate) return;
        if (pc.remoteDescription) {
          await pc.addIceCandidate(new RTCIceCandidate(cand)).catch(() => {});
        } else {
          state.pendingViewerCandidates.push(cand);
        }
        return;
      }

      if (type === 'bye') {
        setStatus('Host ended this stream.', 'error');
        showToast('Stream ended by host.', 'error');
        await handleStreamEnded('Stream ended.');
      }
    }
  };

  /* ──────────────────────────────────────────────────
     Media streams
  ────────────────────────────────────────────────── */
  const ensureLocalStream = async () => {
    if (state.localStream) return state.localStream;
    const s = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
    state.localStream = s;
    if (refs.localVideo) { refs.localVideo.srcObject = s; refs.localVideo.muted = true; }
    state.muted = false;
    state.camOff = false;
    return s;
  };

  const stopLocalStream = () => {
    if (!state.localStream) return;
    state.localStream.getTracks().forEach(t => t.stop());
    state.localStream = null;
    if (refs.localVideo) refs.localVideo.srcObject = null;
    if (refs.mainVideo && !state.screenStream) refs.mainVideo.srcObject = null;
  };

  const startScreenShare = async () => {
    if (!navigator.mediaDevices?.getDisplayMedia) {
      showToast('Screen sharing not supported in this browser.', 'error'); return;
    }
    try {
      const s = await navigator.mediaDevices.getDisplayMedia({ video: { cursor: 'always' }, audio: true });
      state.screenStream = s;
      state.isScreenSharing = true;

      // Replace video track in all host peers
      const videoTrack = s.getVideoTracks()[0];
      for (const [, pc] of state.hostPeers) {
        const sender = pc.getSenders().find(s => s.track?.kind === 'video');
        if (sender && videoTrack) await sender.replaceTrack(videoTrack).catch(() => {});
      }

      // When screen share ends, fall back to camera
      videoTrack.onended = () => stopScreenShare();

      syncControls();
      showToast('Screen sharing started.', 'info');
    } catch (err) {
      if (err.name !== 'NotAllowedError') showToast('Could not start screen share.', 'error');
    }
  };

  const stopScreenShare = async () => {
    if (!state.screenStream) return;
    state.screenStream.getTracks().forEach(t => t.stop());
    state.screenStream = null;
    state.isScreenSharing = false;

    // Restore camera track in peers
    if (state.localStream) {
      const videoTrack = state.localStream.getVideoTracks()[0];
      for (const [, pc] of state.hostPeers) {
        const sender = pc.getSenders().find(s => s.track?.kind === 'video');
        if (sender && videoTrack) await sender.replaceTrack(videoTrack).catch(() => {});
      }
    }

    syncControls();
    showToast('Screen share stopped.', 'info');
  };

  /* ──────────────────────────────────────────────────
     Viewers tracking
  ────────────────────────────────────────────────── */
  const addViewer = user => {
    const id = Number(user?.id || 0);
    if (!id || id === currentUserId) return;
    const isNew = !state.viewers.has(id);
    state.viewers.set(id, user);
    renderViewers();
    if (isNew) showJoinToast(`${displayName(user)} joined the stream`);
  };

  const removeViewer = id => {
    const k = Number(id);
    if (!k) return;
    state.viewers.delete(k);
    renderViewers();
  };

  /* ──────────────────────────────────────────────────
     Timers
  ────────────────────────────────────────────────── */
  const clearJoinTimers = () => {
    clearInterval(state.heartbeatTimer); state.heartbeatTimer = null;
    clearInterval(state.signalTimer);   state.signalTimer   = null;
    clearInterval(state.chatTimer);     state.chatTimer     = null;
  };

  const startJoinTimers = () => {
    clearJoinTimers();
    state.heartbeatTimer = setInterval(() => heartbeat(), 18000);
    state.signalTimer    = setInterval(() => pollSignals().catch(() => {}), 1200);
    state.chatTimer      = setInterval(() => pollChat().catch(() => {}), 2200);
  };

  const heartbeat = async () => {
    if (!state.joined || !streamId()) return;
    try {
      const d = await apiPost('heartbeat', { stream_id: streamId() });
      if (d.stream) { state.currentStream = d.stream; syncControls(); }
    } catch (err) {
      if (String(err.message).includes('not active')) handleStreamEnded('Stream ended.');
    }
  };

  const pollSignals = async () => {
    if (!state.joined || !streamId()) return;
    try {
      const d = await apiGet('signals', { stream_id: streamId(), last_signal_id: state.lastSignalId });
      const sigs = Array.isArray(d?.signals) ? d.signals : [];
      for (const s of sigs) {
        state.lastSignalId = Math.max(state.lastSignalId, Number(s?.id || 0));
        await handleSignal(s);
      }
    } catch (err) {
      if (String(err.message).includes('not active')) handleStreamEnded('Stream ended.');
    }
  };

  const pollChat = async () => {
    const sid = streamId();
    if (!sid) return;
    try {
      const d = await apiGet('chat_list', { stream_id: sid, after_id: state.lastChatId });
      appendChatMessages(Array.isArray(d?.messages) ? d.messages : []);
    } catch (_) {}
  };

  /* ──────────────────────────────────────────────────
     Stream lifecycle
  ────────────────────────────────────────────────── */
  const loadStreams = async () => {
    try {
      const d = await apiGet('list');
      state.streams = Array.isArray(d?.streams) ? d.streams : [];
      if (state.currentStream) {
        const fresh = state.streams.find(s => Number(s?.id) === streamId());
        if (fresh) state.currentStream = fresh;
      }
      renderStreamsList();
      syncControls();
    } catch (err) {
      setStatus(err.message || 'Could not load streams.', 'error');
    }
  };

  const selectStream = async (id, autoJoin = false) => {
    const tid = Number(id);
    if (!tid) return;
    setBusy(true);
    try {
      const d = await apiGet('get', { stream_id: tid });
      state.currentStream = d?.stream || null;
      renderStreamsList();
      syncControls();
      if (autoJoin && Number(state.currentStream?.host_user_id) !== currentUserId) {
        await joinStream();
      }
    } catch (err) {
      showToast(err.message || 'Could not load stream.', 'error');
    } finally {
      setBusy(false);
    }
  };

  const startBroadcast = async () => {
    if (!navigator.mediaDevices?.getUserMedia) {
      showToast('Camera/mic not supported in this browser.', 'error'); return;
    }
    setBusy(true);
    setStatus('Starting camera…', 'info');
    try {
      await ensureLocalStream();

      const title = String(refs.titleInput?.value || '').trim();
      const d = await apiPost('start', {
        title:       title,
        description: String(refs.descInput?.value    || '').trim(),
        category:    String(refs.categoryInput?.value  || 'General').trim() || 'General',
        visibility:  String(refs.visibilityInput?.value || 'public').trim(),
      });

      state.currentStream = d?.stream || null;
      state.role   = 'host';
      state.joined = true;
      state.lastSignalId = 0;

      resetChat();
      startJoinTimers();

      // Update URL
      const url = new URL(location.href);
      url.searchParams.set('mode', 'broadcast');
      if (state.currentStream?.id) url.searchParams.set('stream', String(state.currentStream.id));
      history.replaceState(null, '', url.toString());

      syncControls();
      setStatus('You are live! Share this page with your audience.', 'ok');
      showToast('🔴 Live started!', 'ok');
      await loadStreams();
    } catch (err) {
      setStatus(err.message || 'Could not start broadcast.', 'error');
      showToast(err.message || 'Failed to start.', 'error');
      stopLocalStream();
    } finally {
      setBusy(false);
    }
  };

  const endBroadcast = async () => {
    const sid = streamId();
    if (!sid || state.role !== 'host') return;
    setBusy(true);
    try {
      await sendSignal('bye', { reason: 'stream_ended' }, 0);
      await apiPost('end', { stream_id: sid });

      closeAllHostPeers();
      clearJoinTimers();
      await stopScreenShare().catch(() => {});
      stopLocalStream();

      state.role   = 'idle';
      state.joined = false;
      state.currentStream = null;
      state.lastSignalId  = 0;
      state.viewers.clear();

      resetChat();
      renderViewers();
      setStatus('Broadcast ended.', 'ok');
      showToast('Stream ended.', 'info');
      await loadStreams();
    } catch (err) {
      showToast(err.message || 'Could not end stream.', 'error');
    } finally {
      setBusy(false);
      syncControls();
    }
  };

  const joinStream = async () => {
    const sid = streamId();
    if (!sid) { showToast('Select a stream first.', 'error'); return; }
    if (Number(state.currentStream?.host_user_id) === currentUserId) {
      showToast('You are the host of this stream.', 'error'); return;
    }
    setBusy(true);
    setStatus('Joining stream…', 'info');
    try {
      if (state.role === 'viewer' && state.joined) await leaveStream();

      const d = await apiPost('join', { stream_id: sid });
      state.currentStream = d?.stream || state.currentStream;
      state.role   = 'viewer';
      state.joined = true;
      state.lastSignalId = 0;

      resetChat();
      await createViewerPeer();
      await pollChat();
      startJoinTimers();

      setStatus('Joined stream. Waiting for video…', 'ok');
      showToast('Joined stream!', 'ok');
      await loadStreams();
    } catch (err) {
      setStatus(err.message || 'Could not join stream.', 'error');
      showToast(err.message || 'Failed to join.', 'error');
    } finally {
      setBusy(false);
      syncControls();
    }
  };

  const leaveStream = async () => {
    const sid = streamId();
    if (!sid || !state.joined || state.role !== 'viewer') return;
    setBusy(true);
    try {
      const hostId = Number(state.currentStream?.host_user_id || 0);
      if (hostId) await sendSignal('bye', { reason: 'viewer_left' }, hostId);
      await apiPost('leave', { stream_id: sid });

      closeViewerPeer();
      clearJoinTimers();
      state.role   = 'idle';
      state.joined = false;
      state.lastSignalId = 0;
      state.viewers.clear();

      renderViewers();
      setStatus('Left the stream.', 'ok');
      await loadStreams();
    } catch (err) {
      showToast(err.message || 'Error leaving stream.', 'error');
    } finally {
      setBusy(false);
      syncControls();
    }
  };

  const handleStreamEnded = async msg => {
    clearJoinTimers();
    if (state.role === 'viewer') closeViewerPeer();
    if (state.role === 'host')   { closeAllHostPeers(); stopLocalStream(); stopScreenShare().catch(() => {}); }

    state.role   = 'idle';
    state.joined = false;
    state.lastSignalId  = 0;
    state.currentStream = null;
    state.viewers.clear();

    setStatus(msg || 'Stream ended.', 'error');
    resetChat();
    renderViewers();
    await loadStreams();
    syncControls();
  };

  /* ──────────────────────────────────────────────────
     Resume host stream on page reload
  ────────────────────────────────────────────────── */
  const resumeHostIfActive = async () => {
    try {
      const d = await apiGet('current');
      if (!d?.stream) return false;
      state.currentStream = d.stream;
      state.role   = 'host';
      state.joined = true;
      state.lastSignalId = 0;
      resetChat();
      try { await ensureLocalStream(); } catch (_) {}
      await pollChat();
      startJoinTimers();
      setStatus('Resumed your active broadcast.', 'ok');
      showToast('Broadcast resumed.', 'ok');
      return true;
    } catch (_) {
      return false;
    }
  };

  /* ──────────────────────────────────────────────────
     Event wiring
  ────────────────────────────────────────────────── */
  const wireEvents = () => {
    refs.startBtn?.addEventListener('click',   () => startBroadcast());
    refs.endBtn?.addEventListener('click',     () => endBroadcast());
    refs.joinBtn?.addEventListener('click',    () => joinStream());
    refs.leaveBtn?.addEventListener('click',   () => leaveStream());
    refs.refreshListBtn?.addEventListener('click', () => loadStreams());

    refs.muteBtn?.addEventListener('click', () => {
      if (!state.localStream) return;
      const t = state.localStream.getAudioTracks()[0];
      if (!t) return;
      state.muted = !state.muted;
      t.enabled   = !state.muted;
      syncControls();
    });

    refs.camBtn?.addEventListener('click', () => {
      if (!state.localStream) return;
      const t = state.localStream.getVideoTracks()[0];
      if (!t) return;
      state.camOff = !state.camOff;
      t.enabled    = !state.camOff;
      syncControls();
    });

    refs.screenBtn?.addEventListener('click', () => {
      state.isScreenSharing ? stopScreenShare() : startScreenShare();
    });

    refs.chatSendBtn?.addEventListener('click', () => sendChat());
    refs.chatInput?.addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChat(); }
    });

    // Setup toggle
    refs.setupToggle?.addEventListener('click', () => {
      const body = refs.setupBody;
      if (!body) return;
      body.classList.toggle('is-collapsed');
      const svg = refs.setupToggle.querySelector('svg');
      if (svg) svg.style.transform = body.classList.contains('is-collapsed') ? 'rotate(-90deg)' : '';
    });

    // Click main video to unmute (autoplay policy workaround)
    refs.mainVideo?.addEventListener('click', () => {
      if (refs.mainVideo.muted) {
        refs.mainVideo.muted = false;
        refs.mainVideo.play().catch(() => {});
      }
    });

    // Profile "Go Live" button
    document.getElementById('goLiveBtn')?.addEventListener('click', e => {
      e.preventDefault();
      location.href = 'live.php?mode=broadcast';
    });

    document.getElementById('openLiveFromStoriesBtn')?.addEventListener('click', () => {
      location.href = 'live.php?mode=broadcast';
    });

    // Cleanup on unload
    window.addEventListener('beforeunload', () => {
      const sid = streamId();
      if (!sid) return;
      const blob = new Blob([JSON.stringify({
        mode:      state.role === 'host' ? 'end' : 'leave',
        stream_id: sid,
      })], { type: 'application/json' });
      navigator.sendBeacon('profile.php?action=profile_live_stream', blob);
    });
  };

  /* ──────────────────────────────────────────────────
     Init
  ────────────────────────────────────────────────── */
  const init = async () => {
    wireEvents();
    resetChat();
    renderViewers();
    syncControls();

    await loadStreams();

    if (requestedMode === 'broadcast') {
      const resumed = await resumeHostIfActive();
      if (!resumed) setStatus('Ready to broadcast. Fill in details and click Start Live.', 'info');
    }

    if (!state.currentStream && requestedStreamId > 0) {
      await selectStream(requestedStreamId, requestedMode !== 'browse');
    } else if (!state.currentStream && state.streams.length > 0) {
      state.currentStream = state.streams[0];
      renderStreamsList();
      syncControls();
      if (requestedMode === 'watch') await joinStream();
    }

    // Background stream list refresh
    state.listTimer = setInterval(() => loadStreams(), 15000);

    syncControls();
  };

  init();
});