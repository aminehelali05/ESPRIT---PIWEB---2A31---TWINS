document.addEventListener('DOMContentLoaded', () => {
  const bootstrap = window.messagesPageBootstrap || {};

  const refreshMessagesBtn = document.getElementById('refreshMessagesBtn');
  const createGroupBtn = document.getElementById('createGroupBtn');
  const startRoomBtn = document.getElementById('startRoomBtn');
  const messagesSearchInput = document.getElementById('messagesSearchInput');

  const friendRequestsCount = document.getElementById('friendRequestsCount');
  const friendRequestsList = document.getElementById('friendRequestsList');
  const privateConversationsList = document.getElementById('privateConversationsList');
  const groupConversationsList = document.getElementById('groupConversationsList');
  const peopleDirectoryList = document.getElementById('peopleDirectoryList');

  const messagesThreadAvatar = document.getElementById('messagesThreadAvatar');
  const messagesThreadTitle = document.getElementById('messagesThreadTitle');
  const messagesThreadSubtitle = document.getElementById('messagesThreadSubtitle');
  const messagesThreadBody = document.getElementById('messagesThreadBody');
  const messagesComposer = document.getElementById('messagesComposer');
  const messageComposerInput = document.getElementById('messageComposerInput');
  const messageSendImageBtn = document.getElementById('messageSendImageBtn');
  const sendMessageBtn = document.getElementById('sendMessageBtn');

  const startAudioCallBtn = document.getElementById('startAudioCallBtn');
  const startVideoCallBtn = document.getElementById('startVideoCallBtn');
  const openExternalRoomBtn = document.getElementById('openExternalRoomBtn');

  const callRoomOverlay = document.getElementById('callRoomOverlay');
  const callRoomTitle = document.getElementById('callRoomTitle');
  const callRoomSubtitle = document.getElementById('callRoomSubtitle');
  const callRoomCode = document.getElementById('callRoomCode');
  const callLocalVideo = document.getElementById('callLocalVideo');
  const callRemoteVideo = document.getElementById('callRemoteVideo');
  const callLocalFallback = document.getElementById('callLocalFallback');
  const callRemoteFallback = document.getElementById('callRemoteFallback');
  const callRemoteLabel = document.getElementById('callRemoteLabel');
  const callParticipantsList = document.getElementById('callParticipantsList');

  const callToggleMicBtn = document.getElementById('callToggleMicBtn');
  const callToggleCameraBtn = document.getElementById('callToggleCameraBtn');
  const callShareScreenBtn = document.getElementById('callShareScreenBtn');
  const callCopyInviteBtn = document.getElementById('callCopyInviteBtn');
  const callLaunchProjectRoomBtn = document.getElementById('callLaunchProjectRoomBtn');
  const callEndBtn = document.getElementById('callEndBtn');

  const state = {
    loaded: false,
    loading: false,
    activeThreadType: null,
    activeThreadId: 0,
    incomingRequests: [],
    outgoingRequests: [],
    friends: [],
    privateConversations: [],
    groupChats: [],
    mapUsers: [],
    unreadTotal: 0,
  };

  const callState = {
    open: false,
    stream: null,
    screenStream: null,
    mode: 'video',
    roomCode: '',
    micOn: true,
    cameraOn: true,
  };

  const initialThreadType = (() => {
    const value = String(new URLSearchParams(window.location.search).get('thread_type') || '').toLowerCase();
    return value === 'private' || value === 'group' ? value : '';
  })();
  const initialThreadId = Number(new URLSearchParams(window.location.search).get('thread_id') || 0);
  const invitedRoomCode = String(new URLSearchParams(window.location.search).get('room') || '').trim();

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => {
    if (char === '&') return '&amp;';
    if (char === '<') return '&lt;';
    if (char === '>') return '&gt;';
    if (char === '"') return '&quot;';
    return '&#39;';
  });

  const getUserInitials = (firstName, lastName) => {
    const first = String(firstName || '').trim().charAt(0).toUpperCase();
    const last = String(lastName || '').trim().charAt(0).toUpperCase();
    const initials = `${first}${last}`.trim();
    return initials || 'U';
  };

  const getDisplayName = (user) => {
    const first = String(user?.first_name || '').trim();
    const last = String(user?.last_name || '').trim();
    const merged = `${first} ${last}`.trim();
    if (merged) return merged;
    return String(user?.name || '').trim() || 'Unknown member';
  };

  const getRelativeTime = (dateValue) => {
    const ts = Date.parse(String(dateValue || ''));
    if (!Number.isFinite(ts)) return '';
    const diff = Math.max(0, Math.floor((Date.now() - ts) / 1000));
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return new Date(ts).toLocaleDateString();
  };

  const getThreadMessagePreview = (thread) => {
    const type = String(thread?.last_message_type || 'text').toLowerCase();
    const body = String(thread?.last_message_body || '').trim();
    if (type === 'image') return 'Image';
    if (type === 'video') return 'Video';
    if (type === 'audio') return 'Audio';
    if (type === 'file') return 'Attachment';
    if (type === 'system') return body || 'System update';
    return body || 'No messages yet';
  };

  const showToast = (message, type = 'success') => {
    if (window.Swal) {
      window.Swal.fire({
        toast: true,
        position: 'top-end',
        icon: type === 'error' ? 'error' : (type === 'warning' ? 'warning' : 'success'),
        title: String(message || ''),
        showConfirmButton: false,
        timer: 2600,
        timerProgressBar: true,
        background: '#0f172a',
        color: '#f8fafc'
      });
      return;
    }
    window.alert(String(message || 'Action completed.'));
  };

  const refreshIcons = () => {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }
  };

  const buildActionUrl = (action, params = {}) => {
    const query = new URLSearchParams({ action: String(action || '').trim() });
    Object.entries(params || {}).forEach(([key, value]) => {
      if (value === null || value === undefined || value === '') return;
      query.set(String(key), String(value));
    });
    return `profile.php?${query.toString()}`;
  };

  const requestJson = async (url, options = {}) => {
    const response = await fetch(url, options);
    const text = await response.text();
    let payload;
    try {
      payload = text ? JSON.parse(text) : {};
    } catch (_error) {
      payload = { success: false, message: 'Unexpected server response.' };
    }

    if (!response.ok || payload?.success === false) {
      throw new Error(String(payload?.message || 'Request failed.'));
    }

    return payload || {};
  };

  const profileGet = (action, params = {}) => requestJson(buildActionUrl(action, params), { method: 'GET' });
  const profilePost = (action, body = {}) => requestJson(buildActionUrl(action), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body || {})
  });

  const isFriendWithUser = (targetUserId) => {
    const id = Number(targetUserId || 0);
    return (state.friends || []).some((friend) => Number(friend?.id || 0) === id);
  };

  const hasOutgoingRequestFor = (targetUserId) => {
    const id = Number(targetUserId || 0);
    return (state.outgoingRequests || []).some((request) => Number(request?.receiver_id || request?.user?.id || 0) === id);
  };

  const hasIncomingRequestFrom = (targetUserId) => {
    const id = Number(targetUserId || 0);
    return (state.incomingRequests || []).some((request) => Number(request?.sender_id || request?.user?.id || 0) === id);
  };

  const updateThreadHeader = () => {
    if (!messagesThreadTitle || !messagesThreadSubtitle || !messagesThreadAvatar) return;

    if (!state.activeThreadType || !state.activeThreadId) {
      messagesThreadTitle.textContent = 'Select a conversation';
      messagesThreadSubtitle.textContent = 'Pick a direct or group chat from the left panel.';
      messagesThreadAvatar.textContent = 'M';
      return;
    }

    const thread = findThreadById(state.activeThreadType, state.activeThreadId);
    if (!thread) {
      messagesThreadTitle.textContent = 'Conversation not found';
      messagesThreadSubtitle.textContent = 'Refresh and select another thread.';
      messagesThreadAvatar.textContent = 'M';
      return;
    }

    if (state.activeThreadType === 'private') {
      const peer = thread?.peer || {};
      messagesThreadTitle.textContent = getDisplayName(peer);
      messagesThreadSubtitle.textContent = String(peer?.role || 'member');
      messagesThreadAvatar.textContent = getUserInitials(peer?.first_name, peer?.last_name);
      if (callRemoteLabel) {
        callRemoteLabel.textContent = getDisplayName(peer);
      }
    } else {
      messagesThreadTitle.textContent = String(thread?.name || 'Group chat');
      messagesThreadSubtitle.textContent = String(thread?.description || 'Group conversation');
      messagesThreadAvatar.textContent = String(thread?.name || 'G').charAt(0).toUpperCase();
      if (callRemoteLabel) {
        callRemoteLabel.textContent = String(thread?.name || 'Group room');
      }
    }
  };

  const renderMessagesEmpty = (text = 'Choose a conversation to start messaging.') => {
    if (!messagesThreadBody) return;
    messagesThreadBody.innerHTML = `<div class="messages-empty messages-empty-large">${escapeHtml(text)}</div>`;
  };

  const renderThreadMessages = (messages) => {
    if (!messagesThreadBody) return;
    if (!Array.isArray(messages) || !messages.length) {
      renderMessagesEmpty('No messages yet. Start this conversation.');
      return;
    }

    const currentUserId = Number(bootstrap.currentUserId || 0);

    messagesThreadBody.innerHTML = messages.map((message) => {
      const sender = message?.sender || {};
      const senderName = getDisplayName(sender);
      const outgoing = Number(message?.sender_id || 0) === currentUserId;
      const messageType = String(message?.message_type || 'text').toLowerCase();
      const createdAt = getRelativeTime(message?.created_at || '');
      const body = String(message?.body || '').trim();
      const mediaUrl = String(message?.media_url || '').trim();

      let contentHtml = '';
      if (messageType === 'image' && mediaUrl) {
        contentHtml = `<a href="${escapeHtml(mediaUrl)}" target="_blank" rel="noopener noreferrer"><img src="${escapeHtml(mediaUrl)}" alt="Shared image" class="message-media-image"></a>`;
      } else if (messageType === 'video' && mediaUrl) {
        contentHtml = `<video src="${escapeHtml(mediaUrl)}" controls class="message-media-video"></video>`;
      } else if (messageType === 'file' && mediaUrl) {
        contentHtml = `<a href="${escapeHtml(mediaUrl)}" target="_blank" rel="noopener noreferrer" class="message-media-link">Open attachment</a>`;
      } else {
        contentHtml = `<p>${escapeHtml(body || (messageType === 'system' ? 'System update' : ''))}</p>`;
      }

      return [
        `<article class="message-bubble ${outgoing ? 'is-outgoing' : 'is-incoming'}">`,
        '<div class="message-bubble-head">',
        `<strong>${escapeHtml(senderName)}</strong>`,
        `<span>${escapeHtml(createdAt)}</span>`,
        '</div>',
        `<div class="message-bubble-content">${contentHtml}</div>`,
        '</article>'
      ].join('');
    }).join('');

    messagesThreadBody.scrollTop = messagesThreadBody.scrollHeight;
  };

  const findThreadById = (threadType, threadId) => {
    const id = Number(threadId || 0);
    if (!id) return null;
    const source = threadType === 'group' ? state.groupChats : state.privateConversations;
    return (source || []).find((entry) => Number(entry?.id || 0) === id) || null;
  };

  const renderFriendRequests = () => {
    if (!friendRequestsList) return;

    const incoming = Array.isArray(state.incomingRequests) ? state.incomingRequests : [];
    const outgoing = Array.isArray(state.outgoingRequests) ? state.outgoingRequests : [];

    if (friendRequestsCount) {
      friendRequestsCount.textContent = String(incoming.length);
    }

    if (!incoming.length && !outgoing.length) {
      friendRequestsList.innerHTML = '<div class="messages-empty">No pending requests.</div>';
      return;
    }

    const incomingHtml = incoming.map((request) => {
      const user = request?.user || {};
      const userName = getDisplayName(user);
      const initials = getUserInitials(user?.first_name, user?.last_name);

      return [
        `<div class="friend-request-card" data-request-id="${Number(request?.id || 0)}">`,
        '<div class="friend-request-main">',
        `<span class="friend-request-avatar">${escapeHtml(initials)}</span>`,
        '<div>',
        `<strong>${escapeHtml(userName)}</strong>`,
        `<p>${escapeHtml(String(request?.request_message || '').trim() || 'Wants to connect with you.')}</p>`,
        '</div>',
        '</div>',
        '<div class="friend-request-actions">',
        `<button type="button" class="messages-small-btn is-primary" data-request-action="accept" data-request-id="${Number(request?.id || 0)}">Accept</button>`,
        `<button type="button" class="messages-small-btn" data-request-action="decline" data-request-id="${Number(request?.id || 0)}">Decline</button>`,
        '</div>',
        '</div>'
      ].join('');
    }).join('');

    const outgoingHtml = outgoing.map((request) => {
      const user = request?.user || {};
      const userName = getDisplayName(user);

      return [
        `<div class="friend-request-card" data-request-id="${Number(request?.id || 0)}">`,
        '<div class="friend-request-main">',
        '<span class="friend-request-avatar">↗</span>',
        '<div>',
        `<strong>${escapeHtml(userName)}</strong>`,
        `<p>Request sent ${escapeHtml(getRelativeTime(request?.created_at || ''))}</p>`,
        '</div>',
        '</div>',
        '<div class="friend-request-actions">',
        `<button type="button" class="messages-small-btn" data-request-action="cancel" data-request-id="${Number(request?.id || 0)}">Cancel</button>`,
        '</div>',
        '</div>'
      ].join('');
    }).join('');

    friendRequestsList.innerHTML = [incomingHtml, outgoingHtml].join('');
  };

  const renderThreadList = (target, collection, type, searchQuery) => {
    if (!target) return;

    const query = String(searchQuery || '').trim().toLowerCase();
    const items = Array.isArray(collection) ? collection : [];

    const filtered = items.filter((item) => {
      if (!query) return true;
      if (type === 'private') {
        const peerName = getDisplayName(item?.peer || {}).toLowerCase();
        return peerName.includes(query) || getThreadMessagePreview(item).toLowerCase().includes(query);
      }
      const name = String(item?.name || '').toLowerCase();
      return name.includes(query) || getThreadMessagePreview(item).toLowerCase().includes(query);
    });

    if (!filtered.length) {
      target.innerHTML = `<div class="messages-empty">${query ? 'No matches found.' : 'No conversations yet.'}</div>`;
      return;
    }

    target.innerHTML = filtered.map((thread) => {
      const id = Number(thread?.id || 0);
      const active = state.activeThreadType === type && Number(state.activeThreadId || 0) === id;
      const title = type === 'private' ? getDisplayName(thread?.peer || {}) : String(thread?.name || 'Group');
      const subtitle = getThreadMessagePreview(thread);
      const timeLabel = getRelativeTime(thread?.last_message_at || '');
      const unread = Math.max(0, Number(thread?.unread_count || 0));
      const avatarText = type === 'private'
        ? getUserInitials(thread?.peer?.first_name, thread?.peer?.last_name)
        : String(title).charAt(0).toUpperCase();

      return [
        `<button type="button" class="messages-thread-item ${active ? 'is-active' : ''}" data-thread-type="${type}" data-thread-id="${id}">`,
        `<span class="messages-thread-avatar-mini">${escapeHtml(avatarText)}</span>`,
        '<span class="messages-thread-main">',
        `<strong>${escapeHtml(title)}</strong>`,
        `<small>${escapeHtml(subtitle)}</small>`,
        '</span>',
        '<span class="messages-thread-meta">',
        `<small>${escapeHtml(timeLabel)}</small>`,
        `${unread > 0 ? `<i>${unread}</i>` : ''}`,
        '</span>',
        '</button>'
      ].join('');
    }).join('');
  };

  const renderPeopleDirectory = () => {
    if (!peopleDirectoryList) return;

    const query = String(messagesSearchInput?.value || '').trim().toLowerCase();
    const candidates = (state.mapUsers || []).filter((user) => {
      const userId = Number(user?.id || 0);
      if (!userId || userId === Number(bootstrap.currentUserId || 0)) return false;
      if (isFriendWithUser(userId)) return false;
      if (hasOutgoingRequestFor(userId)) return false;
      if (hasIncomingRequestFrom(userId)) return false;
      if (!query) return true;
      return getDisplayName(user).toLowerCase().includes(query) || String(user?.exact_location || user?.country || '').toLowerCase().includes(query);
    }).slice(0, 40);

    if (!candidates.length) {
      peopleDirectoryList.innerHTML = '<div class="messages-empty">No discoverable users right now.</div>';
      return;
    }

    peopleDirectoryList.innerHTML = candidates.map((user) => {
      const userId = Number(user?.id || 0);
      const name = getDisplayName(user);
      const initials = getUserInitials(user?.first_name, user?.last_name);
      const location = String(user?.exact_location || user?.country || 'Unknown location').trim();

      return [
        `<div class="people-item" data-user-id="${userId}">`,
        '<div class="people-main">',
        `<span class="people-avatar">${escapeHtml(initials)}</span>`,
        '<div>',
        `<strong>${escapeHtml(name)}</strong>`,
        `<p>${escapeHtml(location)}</p>`,
        '</div>',
        '</div>',
        '<div class="people-actions">',
        `<button type="button" class="messages-small-btn is-primary" data-connect-user-id="${userId}">Send Request</button>`,
        '</div>',
        '</div>'
      ].join('');
    }).join('');
  };

  const renderConversationPanels = () => {
    const query = String(messagesSearchInput?.value || '').trim();
    renderFriendRequests();
    renderThreadList(privateConversationsList, state.privateConversations, 'private', query);
    renderThreadList(groupConversationsList, state.groupChats, 'group', query);
    renderPeopleDirectory();
  };

  const setActiveThread = async (threadType, threadId, loadMessages = true) => {
    const thread = findThreadById(threadType, threadId);
    if (!thread) {
      state.activeThreadType = null;
      state.activeThreadId = 0;
      if (messagesComposer) {
        messagesComposer.hidden = true;
      }
      updateThreadHeader();
      renderMessagesEmpty();
      renderConversationPanels();
      return;
    }

    state.activeThreadType = threadType;
    state.activeThreadId = Number(thread?.id || 0);

    const source = threadType === 'group' ? state.groupChats : state.privateConversations;
    source.forEach((item) => {
      if (Number(item?.id || 0) === state.activeThreadId) {
        item.unread_count = 0;
      }
    });

    if (messagesComposer) {
      messagesComposer.hidden = false;
    }

    updateThreadHeader();
    renderConversationPanels();
    renderCallParticipants();

    if (loadMessages) {
      await loadActiveThreadMessages();
    }
  };

  const loadActiveThreadMessages = async () => {
    if (!state.activeThreadType || !state.activeThreadId) {
      renderMessagesEmpty();
      return;
    }

    try {
      const payload = await profileGet('profile_messages', {
        thread_type: state.activeThreadType,
        thread_id: state.activeThreadId,
      });
      renderThreadMessages(payload?.messages || []);
    } catch (error) {
      renderMessagesEmpty('Could not load messages for this thread.');
      showToast(error.message || 'Could not load messages.', 'error');
    }
  };

  const ensureSocialDataLoaded = async (forceRefresh = false) => {
    if (state.loading) return;
    if (state.loaded && !forceRefresh) {
      renderConversationPanels();
      return;
    }

    state.loading = true;
    try {
      const payload = await profileGet('profile_social_data');
      state.loaded = true;
      state.incomingRequests = Array.isArray(payload?.incoming_requests) ? payload.incoming_requests : [];
      state.outgoingRequests = Array.isArray(payload?.outgoing_requests) ? payload.outgoing_requests : [];
      state.friends = Array.isArray(payload?.friends) ? payload.friends : [];
      state.privateConversations = Array.isArray(payload?.private_conversations) ? payload.private_conversations : [];
      state.groupChats = Array.isArray(payload?.group_chats) ? payload.group_chats : [];
      state.mapUsers = Array.isArray(payload?.map_users) ? payload.map_users : [];
      state.unreadTotal = Math.max(0, Number(payload?.unread_total || 0));

      renderConversationPanels();

      const activeStillExists = state.activeThreadType && state.activeThreadId
        ? findThreadById(state.activeThreadType, state.activeThreadId)
        : null;

      if (activeStillExists) {
        await setActiveThread(state.activeThreadType, state.activeThreadId, true);
      } else if (initialThreadType && initialThreadId > 0 && findThreadById(initialThreadType, initialThreadId)) {
        await setActiveThread(initialThreadType, initialThreadId, true);
      } else if (!state.activeThreadType && state.privateConversations.length > 0) {
        await setActiveThread('private', Number(state.privateConversations[0]?.id || 0), true);
      } else if (!state.activeThreadType && state.groupChats.length > 0) {
        await setActiveThread('group', Number(state.groupChats[0]?.id || 0), true);
      } else if (!state.activeThreadType) {
        renderMessagesEmpty('No conversations yet. Send a friend request to start chatting.');
      }

      if (invitedRoomCode && state.activeThreadType && state.activeThreadId) {
        showToast(`Invite received for room ${invitedRoomCode}. Start a call to join.`, 'warning');
      }
    } catch (error) {
      showToast(error.message || 'Could not load social data.', 'error');
    } finally {
      state.loading = false;
      refreshIcons();
    }
  };

  const submitMessage = async ({ messageType = 'text', mediaUrl = '' } = {}) => {
    if (!state.activeThreadType || !state.activeThreadId) {
      showToast('Select a conversation first.', 'warning');
      return;
    }

    const textBody = String(messageComposerInput?.value || '').trim();
    const safeMediaUrl = String(mediaUrl || '').trim();

    if (!textBody && !safeMediaUrl) {
      showToast('Write a message first.', 'warning');
      return;
    }

    if (sendMessageBtn) sendMessageBtn.disabled = true;

    try {
      const payload = await profilePost('profile_send_message', {
        thread_type: state.activeThreadType,
        thread_id: state.activeThreadId,
        message_type: messageType,
        body: textBody,
        media_url: safeMediaUrl,
      });

      if (messageComposerInput) {
        messageComposerInput.value = '';
      }

      const latestMessage = payload?.message || null;
      const thread = findThreadById(state.activeThreadType, state.activeThreadId);
      if (thread && latestMessage) {
        thread.last_message_body = String(latestMessage?.body || '');
        thread.last_message_type = String(latestMessage?.message_type || messageType);
        thread.last_message_at = String(latestMessage?.created_at || new Date().toISOString());
      }

      renderConversationPanels();
      await loadActiveThreadMessages();
    } catch (error) {
      showToast(error.message || 'Could not send message.', 'error');
    } finally {
      if (sendMessageBtn) sendMessageBtn.disabled = false;
    }
  };

  const openGroupComposer = async () => {
    const availableFriends = Array.isArray(state.friends) ? state.friends : [];
    if (!availableFriends.length) {
      showToast('Add friends first before creating a group chat.', 'warning');
      return;
    }

    let groupName = '';
    let groupDescription = '';
    let selectedMembers = [];

    if (window.Swal) {
      const friendsHtml = availableFriends.slice(0, 30).map((friend) => {
        const id = Number(friend?.id || 0);
        const label = escapeHtml(getDisplayName(friend));
        return `<label style="display:flex;align-items:center;gap:8px;font-size:13px;"><input type="checkbox" value="${id}" class="group-member-box"> <span>${label}</span></label>`;
      }).join('');

      const result = await window.Swal.fire({
        title: 'Create Group Chat',
        html: [
          '<input id="groupChatNameField" class="swal2-input" placeholder="Group name" maxlength="120">',
          '<textarea id="groupChatDescriptionField" class="swal2-textarea" placeholder="Short description (optional)" maxlength="255"></textarea>',
          `<div style="text-align:left;max-height:220px;overflow:auto;padding:8px 10px;border:1px solid #e2e8f0;border-radius:10px;">${friendsHtml}</div>`
        ].join(''),
        showCancelButton: true,
        confirmButtonText: 'Create',
        preConfirm: () => {
          const popup = window.Swal.getPopup();
          const name = String(popup?.querySelector('#groupChatNameField')?.value || '').trim();
          const description = String(popup?.querySelector('#groupChatDescriptionField')?.value || '').trim();
          const members = Array.from(popup?.querySelectorAll('.group-member-box:checked') || [])
            .map((node) => Number(node.value || 0))
            .filter((id) => id > 0);

          if (!name) {
            window.Swal.showValidationMessage('Group name is required.');
            return false;
          }

          return { name, description, members };
        }
      });

      if (!result.isConfirmed) return;
      groupName = String(result.value?.name || '').trim();
      groupDescription = String(result.value?.description || '').trim();
      selectedMembers = Array.isArray(result.value?.members) ? result.value.members : [];
    } else {
      groupName = String(window.prompt('Group name') || '').trim();
      if (!groupName) return;
    }

    try {
      const payload = await profilePost('profile_create_group_chat', {
        name: groupName,
        description: groupDescription,
        members: selectedMembers,
      });
      showToast('Group chat created.', 'success');
      await ensureSocialDataLoaded(true);
      const groupId = Number(payload?.group_chat_id || 0);
      if (groupId > 0) {
        await setActiveThread('group', groupId, true);
      }
    } catch (error) {
      showToast(error.message || 'Could not create group chat.', 'error');
    }
  };

  const stopMediaStream = (stream) => {
    if (!stream) return;
    stream.getTracks().forEach((track) => {
      try {
        track.stop();
      } catch (_error) {
      }
    });
  };

  const updateCallButtons = () => {
    if (callToggleMicBtn) {
      callToggleMicBtn.classList.toggle('is-primary', callState.micOn);
      callToggleMicBtn.innerHTML = callState.micOn
        ? '<i data-lucide="mic" class="w-4 h-4"></i> Mic'
        : '<i data-lucide="mic-off" class="w-4 h-4"></i> Mic Off';
    }

    if (callToggleCameraBtn) {
      callToggleCameraBtn.classList.toggle('is-primary', callState.cameraOn);
      callToggleCameraBtn.innerHTML = callState.cameraOn
        ? '<i data-lucide="video" class="w-4 h-4"></i> Camera'
        : '<i data-lucide="video-off" class="w-4 h-4"></i> Camera Off';
    }

    refreshIcons();
  };

  const buildRoomCode = () => {
    const threadType = String(state.activeThreadType || 'thread').slice(0, 1).toUpperCase();
    const threadId = Number(state.activeThreadId || 0);
    const random = Math.random().toString(36).slice(2, 8).toUpperCase();
    return `${threadType}${threadId}-${random}`;
  };

  const renderCallParticipants = () => {
    if (!callParticipantsList) return;

    const rows = [];
    rows.push({
      name: String(bootstrap.currentUserName || 'You'),
      subtitle: 'Host',
    });

    const thread = findThreadById(state.activeThreadType, state.activeThreadId);
    if (thread && state.activeThreadType === 'private') {
      rows.push({
        name: getDisplayName(thread?.peer || {}),
        subtitle: 'Direct participant',
      });
    } else if (thread && state.activeThreadType === 'group') {
      rows.push({
        name: String(thread?.name || 'Group participants'),
        subtitle: 'Group room',
      });
      rows.push({
        name: 'Members from this chat can join with invite link',
        subtitle: 'Invite pending',
      });
    }

    callParticipantsList.innerHTML = rows.map((row) => {
      return [
        '<div class="call-member-item">',
        `<strong>${escapeHtml(String(row.name || 'Member'))}</strong>`,
        `<small>${escapeHtml(String(row.subtitle || 'Participant'))}</small>`,
        '</div>'
      ].join('');
    }).join('');
  };

  const closeCallOverlay = () => {
    stopMediaStream(callState.screenStream);
    stopMediaStream(callState.stream);
    callState.screenStream = null;
    callState.stream = null;
    callState.open = false;
    callState.micOn = true;
    callState.cameraOn = true;

    if (callLocalVideo) {
      callLocalVideo.srcObject = null;
    }
    if (callRemoteVideo) {
      callRemoteVideo.srcObject = null;
    }
    if (callLocalFallback) {
      callLocalFallback.hidden = false;
    }
    if (callRemoteFallback) {
      callRemoteFallback.hidden = false;
    }

    if (callRoomOverlay) {
      callRoomOverlay.hidden = true;
    }
    document.body.classList.remove('call-room-open');
    updateCallButtons();
  };

  const openCallOverlay = async (mode = 'video') => {
    if (!state.activeThreadType || !state.activeThreadId) {
      showToast('Select a conversation before starting a call.', 'warning');
      return;
    }

    const wantsVideo = mode !== 'audio';

    stopMediaStream(callState.screenStream);
    stopMediaStream(callState.stream);
    callState.screenStream = null;

    try {
      callState.stream = await navigator.mediaDevices.getUserMedia({
        audio: true,
        video: wantsVideo
      });
    } catch (error) {
      showToast(error.message || 'Microphone or camera access was denied.', 'error');
      return;
    }

    callState.mode = mode;
    callState.open = true;
    callState.roomCode = invitedRoomCode || buildRoomCode();
    callState.micOn = true;
    callState.cameraOn = wantsVideo;

    if (callRoomTitle) {
      callRoomTitle.textContent = mode === 'audio' ? 'Audio Room' : 'Video Room';
    }
    if (callRoomSubtitle) {
      const title = String(messagesThreadTitle?.textContent || 'conversation').trim();
      callRoomSubtitle.textContent = `Live session for ${title}`;
    }
    if (callRoomCode) {
      callRoomCode.textContent = callState.roomCode;
    }

    if (callLocalVideo) {
      callLocalVideo.srcObject = callState.stream;
    }
    if (callLocalFallback) {
      callLocalFallback.hidden = wantsVideo;
      if (!wantsVideo) {
        callLocalFallback.innerHTML = '<i data-lucide="mic" class="w-8 h-8"></i><span>Audio mode active</span>';
      } else {
        callLocalFallback.innerHTML = '<i data-lucide="user-circle-2" class="w-8 h-8"></i><span>You</span>';
      }
    }

    if (callRemoteFallback) {
      callRemoteFallback.hidden = false;
    }

    if (callRoomOverlay) {
      callRoomOverlay.hidden = false;
    }

    document.body.classList.add('call-room-open');
    renderCallParticipants();
    updateCallButtons();
  };

  const toggleCallMic = () => {
    if (!callState.stream) return;
    const track = callState.stream.getAudioTracks()[0];
    if (!track) return;
    callState.micOn = !callState.micOn;
    track.enabled = callState.micOn;
    updateCallButtons();
  };

  const toggleCallCamera = async () => {
    if (!callState.stream) return;
    const track = callState.stream.getVideoTracks()[0];

    if (!track && !callState.cameraOn) {
      try {
        const cameraStream = await navigator.mediaDevices.getUserMedia({ video: true });
        const videoTrack = cameraStream.getVideoTracks()[0];
        if (videoTrack) {
          callState.stream.addTrack(videoTrack);
          if (callLocalVideo) {
            callLocalVideo.srcObject = callState.stream;
          }
          callState.cameraOn = true;
          if (callLocalFallback) {
            callLocalFallback.hidden = true;
          }
          updateCallButtons();
        }
      } catch (error) {
        showToast(error.message || 'Could not enable camera.', 'error');
      }
      return;
    }

    if (!track) return;

    callState.cameraOn = !callState.cameraOn;
    track.enabled = callState.cameraOn;

    if (callLocalFallback) {
      callLocalFallback.hidden = callState.cameraOn;
      if (!callState.cameraOn) {
        callLocalFallback.innerHTML = '<i data-lucide="video-off" class="w-8 h-8"></i><span>Camera paused</span>';
      }
    }

    updateCallButtons();
  };

  const shareScreen = async () => {
    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getDisplayMedia !== 'function') {
      showToast('Screen sharing is not supported in this browser.', 'warning');
      return;
    }

    try {
      const screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false });
      const screenTrack = screenStream.getVideoTracks()[0];
      if (!screenTrack) return;

      callState.screenStream = screenStream;
      if (callLocalVideo) {
        callLocalVideo.srcObject = screenStream;
      }
      if (callLocalFallback) {
        callLocalFallback.hidden = true;
      }

      screenTrack.onended = () => {
        stopMediaStream(callState.screenStream);
        callState.screenStream = null;
        if (callLocalVideo && callState.stream) {
          callLocalVideo.srcObject = callState.stream;
        }
      };
    } catch (error) {
      showToast(error.message || 'Could not share screen.', 'error');
    }
  };

  const copyRoomInvite = async () => {
    if (!state.activeThreadType || !state.activeThreadId) {
      showToast('Select a conversation first.', 'warning');
      return;
    }

    const inviteUrl = new URL(window.location.href);
    inviteUrl.searchParams.set('thread_type', state.activeThreadType);
    inviteUrl.searchParams.set('thread_id', String(state.activeThreadId));
    inviteUrl.searchParams.set('room', String(callState.roomCode || buildRoomCode()));

    try {
      await navigator.clipboard.writeText(inviteUrl.toString());
      showToast('Invite link copied to clipboard.', 'success');
    } catch (_error) {
      showToast(`Invite link: ${inviteUrl.toString()}`, 'warning');
    }
  };

  const openAdvancedProjectRoom = () => {
    const roomCode = String(callState.roomCode || invitedRoomCode || buildRoomCode()).trim();
    const targetUrl = `meeting-room.php?room=${encodeURIComponent(roomCode)}`;
    const popup = window.open(targetUrl, '_blank', 'noopener');
    if (!popup) {
      showToast('Popup blocked. Please allow popups and try again.', 'warning');
      return;
    }
    showToast('Projet-2A room opened in a new tab.', 'success');
  };

  if (refreshMessagesBtn) {
    refreshMessagesBtn.addEventListener('click', async () => {
      await ensureSocialDataLoaded(true);
      if (state.activeThreadType && state.activeThreadId) {
        await loadActiveThreadMessages();
      }
    });
  }

  if (messagesSearchInput) {
    messagesSearchInput.addEventListener('input', () => {
      renderConversationPanels();
    });
  }

  [privateConversationsList, groupConversationsList].forEach((listEl) => {
    if (!listEl) return;
    listEl.addEventListener('click', async (event) => {
      const button = event.target.closest('.messages-thread-item');
      if (!button) return;
      const threadType = String(button.dataset.threadType || '').trim();
      const threadId = Number(button.dataset.threadId || 0);
      if (!threadType || !threadId) return;
      await setActiveThread(threadType, threadId, true);
    });
  });

  if (friendRequestsList) {
    friendRequestsList.addEventListener('click', async (event) => {
      const actionBtn = event.target.closest('[data-request-action]');
      if (!actionBtn) return;

      const mode = String(actionBtn.dataset.requestAction || '').trim();
      const requestId = Number(actionBtn.dataset.requestId || 0);
      if (!mode || !requestId) return;

      actionBtn.setAttribute('disabled', 'disabled');
      try {
        await profilePost('profile_friend_request', { mode, request_id: requestId });
        await ensureSocialDataLoaded(true);
      } catch (error) {
        showToast(error.message || 'Could not update request.', 'error');
      } finally {
        actionBtn.removeAttribute('disabled');
      }
    });
  }

  if (peopleDirectoryList) {
    peopleDirectoryList.addEventListener('click', async (event) => {
      const connectBtn = event.target.closest('[data-connect-user-id]');
      if (!connectBtn) return;

      const targetUserId = Number(connectBtn.dataset.connectUserId || 0);
      if (!targetUserId) return;

      connectBtn.setAttribute('disabled', 'disabled');
      try {
        await profilePost('profile_friend_request', {
          mode: 'send',
          target_user_id: targetUserId,
          request_message: 'Let us connect on Diversity.is.',
        });
        showToast('Friend request sent.', 'success');
        await ensureSocialDataLoaded(true);
      } catch (error) {
        showToast(error.message || 'Could not send friend request.', 'error');
      } finally {
        connectBtn.removeAttribute('disabled');
      }
    });
  }

  if (sendMessageBtn) {
    sendMessageBtn.addEventListener('click', async () => {
      await submitMessage({ messageType: 'text' });
    });
  }

  if (messageComposerInput) {
    messageComposerInput.addEventListener('keydown', async (event) => {
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        await submitMessage({ messageType: 'text' });
      }
    });
  }

  if (messageSendImageBtn) {
    messageSendImageBtn.addEventListener('click', async () => {
      let mediaUrl = '';

      if (window.Swal) {
        const result = await window.Swal.fire({
          title: 'Send image URL',
          input: 'url',
          inputPlaceholder: 'https://example.com/image.jpg',
          showCancelButton: true,
          confirmButtonText: 'Send',
          background: '#ffffff',
          color: '#0f172a'
        });
        if (!result.isConfirmed) return;
        mediaUrl = String(result.value || '').trim();
      } else {
        mediaUrl = String(window.prompt('Paste an image URL') || '').trim();
      }

      if (!mediaUrl) return;
      await submitMessage({ messageType: 'image', mediaUrl });
    });
  }

  if (createGroupBtn) {
    createGroupBtn.addEventListener('click', openGroupComposer);
  }

  if (startRoomBtn) {
    startRoomBtn.addEventListener('click', async () => {
      await openCallOverlay('video');
    });
  }

  if (startAudioCallBtn) {
    startAudioCallBtn.addEventListener('click', async () => {
      await openCallOverlay('audio');
    });
  }

  if (startVideoCallBtn) {
    startVideoCallBtn.addEventListener('click', async () => {
      await openCallOverlay('video');
    });
  }

  if (openExternalRoomBtn) {
    openExternalRoomBtn.addEventListener('click', openAdvancedProjectRoom);
  }

  if (callToggleMicBtn) {
    callToggleMicBtn.addEventListener('click', toggleCallMic);
  }

  if (callToggleCameraBtn) {
    callToggleCameraBtn.addEventListener('click', toggleCallCamera);
  }

  if (callShareScreenBtn) {
    callShareScreenBtn.addEventListener('click', shareScreen);
  }

  if (callCopyInviteBtn) {
    callCopyInviteBtn.addEventListener('click', copyRoomInvite);
  }

  if (callLaunchProjectRoomBtn) {
    callLaunchProjectRoomBtn.addEventListener('click', openAdvancedProjectRoom);
  }

  if (callEndBtn) {
    callEndBtn.addEventListener('click', closeCallOverlay);
  }

  if (callRoomOverlay) {
    callRoomOverlay.addEventListener('click', (event) => {
      if (event.target === callRoomOverlay) {
        closeCallOverlay();
      }
    });
  }

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && callState.open) {
      closeCallOverlay();
    }
  });

  updateCallButtons();
  ensureSocialDataLoaded(false);
  refreshIcons();
});
