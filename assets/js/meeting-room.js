/**
 * WebRTC Meeting Room
 * Mirrors the Projet-2A meeting room behavior in FrontOffice pages.
 */

class MeetingRoom {
  constructor() {
    this.roomId = null;
    this.localStream = null;
    this.peers = new Map();
    this.socket = null;
    this.isMicOn = true;
    this.isCameraOn = true;
    this.isScreenSharing = false;

    this.rtcConfig = {
      iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' }
      ]
    };
  }

  async init() {
    const urlParams = new URLSearchParams(window.location.search);
    this.roomId = urlParams.get('room');

    if (!this.roomId) {
      window.alert('No room ID provided.');
      window.location.href = 'messages.php';
      return;
    }

    this.setupEventListeners();
    await this.initializeLocalMedia();
    this.connectToSignalingServer();

    this.updateMeetingTime();
    window.setInterval(() => this.updateMeetingTime(), 1000);
  }

  setupEventListeners() {
    document.getElementById('micBtn')?.addEventListener('click', () => this.toggleMicrophone());
    document.getElementById('cameraBtn')?.addEventListener('click', () => this.toggleCamera());
    document.getElementById('screenBtn')?.addEventListener('click', () => this.toggleScreenShare());

    document.getElementById('chatBtn')?.addEventListener('click', () => this.toggleChat());
    document.getElementById('closeChatBtn')?.addEventListener('click', () => this.toggleChat());
    document.getElementById('sendChatBtn')?.addEventListener('click', () => this.sendChatMessage());

    document.getElementById('chatInput')?.addEventListener('keypress', (event) => {
      if (event.key === 'Enter') {
        this.sendChatMessage();
      }
    });

    document.getElementById('participantsBtn')?.addEventListener('click', () => this.toggleParticipants());
    document.getElementById('closeParticipantsBtn')?.addEventListener('click', () => this.toggleParticipants());

    document.getElementById('copyLinkBtn')?.addEventListener('click', () => this.copyMeetingLink());
    document.getElementById('leaveBtn')?.addEventListener('click', () => this.leaveMeeting());
  }

  async initializeLocalMedia() {
    try {
      this.localStream = await navigator.mediaDevices.getUserMedia({
        video: {
          width: { ideal: 1280 },
          height: { ideal: 720 }
        },
        audio: {
          echoCancellation: true,
          noiseSuppression: true,
          autoGainControl: true
        }
      });

      const localVideo = document.getElementById('localVideo');
      if (localVideo) {
        localVideo.srcObject = this.localStream;
      }
    } catch (error) {
      console.error('Error accessing media devices:', error);
      window.alert('Could not access camera/microphone. You can still join with limited functionality.');
    }
  }

  connectToSignalingServer() {
    const signalingServerUrl = window.SIGNALING_SERVER_URL || 'http://localhost:3000';

    if (typeof window.io !== 'function') {
      console.warn('Socket.io client not available.');
      return;
    }

    this.socket = window.io(signalingServerUrl, {
      reconnection: true,
      reconnectionDelay: 1000,
      reconnectionAttempts: 5
    });

    this.socket.on('connect', () => {
      this.socket.emit('join-room', this.roomId);
    });

    this.socket.on('user-connected', (userId) => {
      this.connectToNewUser(userId).catch((error) => {
        console.error('connectToNewUser failed:', error);
      });
    });

    this.socket.on('user-disconnected', (userId) => {
      this.removeUser(userId);
    });

    this.socket.on('receive-offer', async (userId, offer) => {
      try {
        await this.handleOffer(userId, offer);
      } catch (error) {
        console.error('handleOffer failed:', error);
      }
    });

    this.socket.on('receive-answer', async (userId, answer) => {
      try {
        await this.handleAnswer(userId, answer);
      } catch (error) {
        console.error('handleAnswer failed:', error);
      }
    });

    this.socket.on('receive-ice-candidate', async (userId, candidate) => {
      try {
        await this.handleIceCandidate(userId, candidate);
      } catch (error) {
        console.error('handleIceCandidate failed:', error);
      }
    });

    this.socket.on('chat-message', (data) => {
      this.displayChatMessage(data);
    });
  }

  async connectToNewUser(userId) {
    const peerConnection = this.createPeerConnection(userId);

    if (this.localStream) {
      this.localStream.getTracks().forEach((track) => {
        peerConnection.addTrack(track, this.localStream);
      });
    }

    const offer = await peerConnection.createOffer();
    await peerConnection.setLocalDescription(offer);

    this.socket?.emit('send-offer', userId, offer);
  }

  createPeerConnection(userId) {
    const peerConnection = new RTCPeerConnection(this.rtcConfig);

    peerConnection.onicecandidate = (event) => {
      if (event.candidate) {
        this.socket?.emit('send-ice-candidate', userId, event.candidate);
      }
    };

    peerConnection.ontrack = (event) => {
      this.addRemoteVideo(userId, event.streams[0]);
    };

    peerConnection.onconnectionstatechange = () => {
      if (peerConnection.connectionState === 'disconnected' || peerConnection.connectionState === 'failed') {
        this.removeUser(userId);
      }
    };

    this.peers.set(userId, peerConnection);
    return peerConnection;
  }

  async handleOffer(userId, offer) {
    const peerConnection = this.createPeerConnection(userId);

    if (this.localStream) {
      this.localStream.getTracks().forEach((track) => {
        peerConnection.addTrack(track, this.localStream);
      });
    }

    await peerConnection.setRemoteDescription(new RTCSessionDescription(offer));

    const answer = await peerConnection.createAnswer();
    await peerConnection.setLocalDescription(answer);

    this.socket?.emit('send-answer', userId, answer);
  }

  async handleAnswer(userId, answer) {
    const peerConnection = this.peers.get(userId);
    if (peerConnection) {
      await peerConnection.setRemoteDescription(new RTCSessionDescription(answer));
    }
  }

  async handleIceCandidate(userId, candidate) {
    const peerConnection = this.peers.get(userId);
    if (peerConnection) {
      await peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
    }
  }

  addRemoteVideo(userId, stream) {
    this.removeUser(userId);

    const videoGrid = document.getElementById('videoGrid');
    if (!videoGrid) return;

    const videoContainer = document.createElement('div');
    videoContainer.className = 'video-container';
    videoContainer.id = `video-${userId}`;

    const video = document.createElement('video');
    video.srcObject = stream;
    video.autoplay = true;
    video.playsinline = true;

    const overlay = document.createElement('div');
    overlay.className = 'video-overlay';
    overlay.innerHTML = [
      `<span class="text-sm font-medium">Participant ${String(userId).substring(0, 6)}</span>`,
      '<div class="flex items-center gap-2">',
      '<span class="w-6 h-6 bg-zinc-800/80 rounded-full flex items-center justify-center">',
      '<i data-lucide="mic" class="w-3.5 h-3.5"></i>',
      '</span>',
      '</div>'
    ].join('');

    videoContainer.appendChild(video);
    videoContainer.appendChild(overlay);
    videoGrid.appendChild(videoContainer);

    this.updateVideoGrid();
    this.updateParticipantCount();
    if (window.lucide) window.lucide.createIcons();
  }

  removeUser(userId) {
    const videoElement = document.getElementById(`video-${userId}`);
    if (videoElement) {
      videoElement.remove();
    }

    const peerConnection = this.peers.get(userId);
    if (peerConnection) {
      peerConnection.close();
      this.peers.delete(userId);
    }

    this.updateVideoGrid();
    this.updateParticipantCount();
  }

  updateVideoGrid() {
    const videoGrid = document.getElementById('videoGrid');
    if (!videoGrid) return;

    const videoCount = videoGrid.children.length;
    videoGrid.className = 'video-grid';

    if (videoCount === 1) {
      videoGrid.classList.add('grid-1');
    } else if (videoCount === 2) {
      videoGrid.classList.add('grid-2');
    } else if (videoCount <= 4) {
      videoGrid.classList.add('grid-4');
    } else {
      videoGrid.classList.add('grid-6');
    }
  }

  toggleMicrophone() {
    this.isMicOn = !this.isMicOn;

    const audioTrack = this.localStream?.getAudioTracks?.()[0];
    if (audioTrack) {
      audioTrack.enabled = this.isMicOn;
    }

    const micBtn = document.getElementById('micBtn');
    const micIcon = micBtn?.querySelector('i');
    if (!micBtn || !micIcon) return;

    if (this.isMicOn) {
      micBtn.classList.remove('active');
      micIcon.setAttribute('data-lucide', 'mic');
    } else {
      micBtn.classList.add('active');
      micIcon.setAttribute('data-lucide', 'mic-off');
    }

    if (window.lucide) window.lucide.createIcons();
  }

  toggleCamera() {
    this.isCameraOn = !this.isCameraOn;

    const videoTrack = this.localStream?.getVideoTracks?.()[0];
    if (videoTrack) {
      videoTrack.enabled = this.isCameraOn;
    }

    const cameraBtn = document.getElementById('cameraBtn');
    const cameraIcon = cameraBtn?.querySelector('i');
    if (!cameraBtn || !cameraIcon) return;

    if (this.isCameraOn) {
      cameraBtn.classList.remove('active');
      cameraIcon.setAttribute('data-lucide', 'video');
    } else {
      cameraBtn.classList.add('active');
      cameraIcon.setAttribute('data-lucide', 'video-off');
    }

    if (window.lucide) window.lucide.createIcons();
  }

  async toggleScreenShare() {
    if (!this.localStream) return;

    if (!this.isScreenSharing) {
      try {
        const screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
        const screenTrack = screenStream.getVideoTracks()[0];

        this.peers.forEach((peerConnection) => {
          const sender = peerConnection.getSenders().find((entry) => entry.track && entry.track.kind === 'video');
          if (sender) {
            sender.replaceTrack(screenTrack);
          }
        });

        const localVideo = document.getElementById('localVideo');
        if (localVideo) {
          localVideo.srcObject = screenStream;
        }

        this.isScreenSharing = true;

        screenTrack.onended = () => {
          this.toggleScreenShare().catch(() => {});
        };

        document.getElementById('screenBtn')?.classList.add('active');
      } catch (error) {
        console.error('Error sharing screen:', error);
      }
      return;
    }

    const videoTrack = this.localStream.getVideoTracks()[0];

    this.peers.forEach((peerConnection) => {
      const sender = peerConnection.getSenders().find((entry) => entry.track && entry.track.kind === 'video');
      if (sender && videoTrack) {
        sender.replaceTrack(videoTrack);
      }
    });

    const localVideo = document.getElementById('localVideo');
    if (localVideo) {
      localVideo.srcObject = this.localStream;
    }

    this.isScreenSharing = false;
    document.getElementById('screenBtn')?.classList.remove('active');
  }

  toggleChat() {
    document.getElementById('chatSidebar')?.classList.toggle('translate-x-full');
  }

  toggleParticipants() {
    document.getElementById('participantsSidebar')?.classList.toggle('translate-x-full');
  }

  sendChatMessage() {
    const input = document.getElementById('chatInput');
    const message = String(input?.value || '').trim();
    if (!message) return;

    this.socket?.emit('chat-message', {
      roomId: this.roomId,
      message,
      sender: 'You',
      timestamp: new Date().toISOString()
    });

    if (input) {
      input.value = '';
    }
  }

  displayChatMessage(data) {
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) return;

    const messageDiv = document.createElement('div');
    messageDiv.className = 'bg-zinc-800/50 rounded-lg p-3';

    const time = new Date(data.timestamp || Date.now()).toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit'
    });

    const safeSender = String(data.sender || 'Participant')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
    const safeMessage = String(data.message || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');

    messageDiv.innerHTML = [
      '<div class="flex items-center justify-between mb-1">',
      `<span class="text-xs font-medium text-purple-400">${safeSender}</span>`,
      `<span class="text-xs text-zinc-500">${time}</span>`,
      '</div>',
      `<p class="text-sm text-zinc-200">${safeMessage}</p>`
    ].join('');

    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  copyMeetingLink() {
    const link = window.location.href;
    navigator.clipboard.writeText(link).then(() => {
      const btn = document.getElementById('copyLinkBtn');
      if (!btn) return;
      const originalText = btn.innerHTML;
      btn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> Copied!';
      if (window.lucide) window.lucide.createIcons();

      window.setTimeout(() => {
        btn.innerHTML = originalText;
        if (window.lucide) window.lucide.createIcons();
      }, 2000);
    }).catch(() => {
      window.alert('Could not copy link.');
    });
  }

  updateParticipantCount() {
    const count = this.peers.size + 1;
    const el = document.getElementById('participantCount');
    if (el) {
      el.textContent = String(count);
    }
  }

  updateMeetingTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit'
    });
    const el = document.getElementById('meetingTime');
    if (el) {
      el.textContent = timeString;
    }
  }

  leaveMeeting() {
    if (!window.confirm('Are you sure you want to leave the meeting?')) return;

    if (this.localStream) {
      this.localStream.getTracks().forEach((track) => track.stop());
    }

    this.peers.forEach((peerConnection) => peerConnection.close());

    if (this.socket) {
      this.socket.disconnect();
    }

    window.location.href = 'messages.php';
  }
}

const meetingRoom = new MeetingRoom();
document.addEventListener('DOMContentLoaded', () => {
  meetingRoom.init().catch((error) => {
    console.error('Meeting room init failed:', error);
    window.alert('Meeting room failed to initialize.');
  });
});
