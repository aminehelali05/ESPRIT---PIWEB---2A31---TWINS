document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('editModal');
  const userForm = document.getElementById('userForm');
  const editToggleBtn = document.getElementById('editToggleBtn');
  const exportProfileBtn = document.getElementById('exportProfileBtn');
  const closeEditModal = document.getElementById('closeEditModal');
  const cancelUserModal = document.getElementById('cancelUserModal');
  const fabEditProfile = document.getElementById('fabEditProfile');
  const saveUserBtn = document.getElementById('saveUserBtn');
  const avatarPreview = document.getElementById('formAvatarPreview');
  const avatarFallback = document.getElementById('formAvatarFallback');
  const avatarUrlInput = document.getElementById('formAvatarUrl');
  const avatarFileInput = document.getElementById('formAvatarFile');
  const pickAvatarFromFileBtn = document.getElementById('pickAvatarFromFileBtn');
  const openAvatarCameraBtn = document.getElementById('openAvatarCameraBtn');
  const captureAvatarBtn = document.getElementById('captureAvatarBtn');
  const formRandomAvatarBtn = document.getElementById('formRandomAvatarBtn');
  const profileRandomAvatarBtn = document.getElementById('profileRandomAvatarBtn');
  const avatarCameraVideo = document.getElementById('avatarCameraVideo');
  const avatarCaptureCanvas = document.getElementById('avatarCaptureCanvas');
  const countryInput = document.getElementById('formCountry');
  const exactLocationInput = document.getElementById('formExactLocation');
  const latInput = document.getElementById('formLatitude');
  const lngInput = document.getElementById('formLongitude');
  const mapAddressInput = document.getElementById('formMapAddress');
  const profileAvatar = document.getElementById('profileAvatar');
  const profileAvatarFrame = profileAvatar?.closest('.profile-avatar') || null;
  const profileLocationText = document.getElementById('profileLocationText');
  const aboutBioText = document.getElementById('aboutBioText');
  const changePasswordForm = document.getElementById('changePasswordForm');
  const requestDeleteAccountBtn = document.getElementById('requestDeleteAccountBtn');
  const profilePitchVideo = document.getElementById('profilePitchVideo');
  const videoOverlayPlay = document.getElementById('videoOverlayPlay');
  const pipVideoBtn = document.getElementById('pipVideoBtn');
  const secStrengthFill = document.getElementById('secStrengthFill');
  const openMessagesPanelBtn = document.getElementById('openMessagesPanelBtn');
  const openMessagesFromStoriesBtn = document.getElementById('openMessagesFromStoriesBtn');
  const openLiveFromStoriesBtn = document.getElementById('openLiveFromStoriesBtn');
  const messagesNavCount = document.getElementById('messagesNavCount');
  const messagesSearchInput = document.getElementById('messagesSearchInput');
  const friendRequestsCount = document.getElementById('friendRequestsCount');
  const friendRequestsList = document.getElementById('friendRequestsList');
  const privateConversationsList = document.getElementById('privateConversationsList');
  const groupConversationsList = document.getElementById('groupConversationsList');
  const messagesThreadAvatar = document.getElementById('messagesThreadAvatar');
  const messagesThreadTitle = document.getElementById('messagesThreadTitle');
  const messagesThreadSubtitle = document.getElementById('messagesThreadSubtitle');
  const messagesThreadBody = document.getElementById('messagesThreadBody');
  const messagesComposer = document.getElementById('messagesComposer');
  const messageComposerInput = document.getElementById('messageComposerInput');
  const messageSendImageBtn = document.getElementById('messageSendImageBtn');
  const sendMessageBtn = document.getElementById('sendMessageBtn');
  const messagesRefreshBtn = document.getElementById('messagesRefreshBtn');
  const openGroupComposerBtn = document.getElementById('openGroupComposerBtn');
  const openLinkedAccountsEditorBtn = document.getElementById('openLinkedAccountsEditorBtn');
  const quickLinkAccountBtn = document.getElementById('quickLinkAccountBtn');
  const linkedAccountsModal = document.getElementById('linkedAccountsModal');
  const closeLinkedAccountsModalBtn = document.getElementById('closeLinkedAccountsModalBtn');
  const cancelLinkedAccountsBtn = document.getElementById('cancelLinkedAccountsBtn');
  const saveLinkedAccountsBtn = document.getElementById('saveLinkedAccountsBtn');
  const linkedAccountUrlInputs = Array.from(document.querySelectorAll('.linked-account-url'));
  const storiesActiveRail = document.getElementById('storiesActiveRail');
  const storiesArchiveList = document.getElementById('storiesArchiveList');
  const toggleStoriesArchiveBtn = document.getElementById('toggleStoriesArchiveBtn');
  const openStoryComposerBtn = document.getElementById('openStoryComposerBtn');
  const storyComposer = document.getElementById('storyComposer');
  const cancelStoryComposerBtn = document.getElementById('cancelStoryComposerBtn');
  const cancelStoryComposerBtnSecondary = document.getElementById('cancelStoryComposerBtnSecondary');
  const publishStoryBtn = document.getElementById('publishStoryBtn');
  const storyTypeInput = document.getElementById('storyTypeInput');
  const storyMediaUrlInput = document.getElementById('storyMediaUrlInput');
  const storyCaptionInput = document.getElementById('storyCaptionInput');
  const storyCameraPreview = document.getElementById('storyCameraPreview');
  const storyCaptureCanvas = document.getElementById('storyCaptureCanvas');
  const storyCaptureBtn = document.getElementById('storyCaptureBtn');
  const storyImportInput = document.getElementById('storyImportInput');
  const storyFlipCameraBtn = document.getElementById('storyFlipCameraBtn');
  const messagesFab = document.getElementById('messagesFab');
  const snapSocialMapEl = document.getElementById('snapSocialMap');

  const profileSocialBootstrap = window.profileSocialBootstrap || {};
  const socialState = {
    loaded: false,
    loading: false,
    mapReady: false,
    mapMarkers: [],
    mapInstance: null,
    activeThreadType: null,
    activeThreadId: 0,
    activeThreadLabel: '',
    activeThreadAvatar: '',
    activeThreadSubtitle: '',
    linkedAccounts: Array.isArray(profileSocialBootstrap.linkedAccounts) ? profileSocialBootstrap.linkedAccounts.slice() : [],
    incomingRequests: [],
    outgoingRequests: [],
    friends: [],
    privateConversations: [],
    groupChats: [],
    storiesActive: [],
    storiesArchive: [],
    liveStreams: [],
    mapUsers: [],
    unreadTotal: 0,
  };
  const geoCache = new Map();

  let avatarCameraStream = null;
  let storyCameraStream = null;
  let storyFacingMode = 'environment';
  let globeInitialized = false;
  let initialSnapshot = '';

  const fallbackCapitals = {
    brazil: { lat: -15.793889, lng: -47.882778 },
    uruguay: { lat: -34.901112, lng: -56.164532 },
    argentina: { lat: -34.603722, lng: -58.381592 },
    tunisia: { lat: 36.806389, lng: 10.181667 },
    france: { lat: 48.856613, lng: 2.352222 },
    germany: { lat: 52.52, lng: 13.405 }
  };
  const capitalCache = new Map();

  const showToast = (message, type = 'success') => {
    if (window.Swal) {
      window.Swal.fire({
        toast: true,
        position: 'top-end',
        icon: type === 'error' ? 'error' : (type === 'warning' ? 'warning' : 'success'),
        title: message,
        showConfirmButton: false,
        timer: 2600,
        timerProgressBar: true,
        background: '#0f172a',
        color: '#f8fafc',
        customClass: { container: 'uf-swal-front' }
      });
      return;
    }

    const stack = document.getElementById('profileToastStack');
    if (!stack) return;
    const toast = document.createElement('div');
    toast.className = `profile-toast ${type === 'error' ? 'is-error' : ''}`;
    toast.textContent = message;
    stack.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(-4px)';
      setTimeout(() => toast.remove(), 220);
    }, 2200);
  };

  const isBlockedAccount = () => String(document.body?.dataset.accountBlocked || '0') === '1' || document.body.classList.contains('is-account-blocked');

  const isDeleteRequestPending = () => String(document.body?.dataset.deleteRequestPending || '0') === '1';

  const setDeleteRequestButtonState = (pending) => {
    if (!requestDeleteAccountBtn) return;
    requestDeleteAccountBtn.dataset.deleteRequestState = pending ? 'pending' : 'ready';
    requestDeleteAccountBtn.classList.toggle('btn-success', pending);
    requestDeleteAccountBtn.classList.toggle('btn-danger', !pending);
    requestDeleteAccountBtn.classList.add('blocked-allow');
    requestDeleteAccountBtn.disabled = false;
    requestDeleteAccountBtn.removeAttribute('title');
    requestDeleteAccountBtn.innerHTML = pending
      ? '<i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Cancel Deletion Request'
      : '<i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Request Permanent Account Deletion';
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }
  };

  const showBlockedActionToast = (message = 'Account blocked. Wait for admin approval before making changes.') => {
    if (window.Swal) {
      window.Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'warning',
        title: 'Account blocked',
        text: message,
        showConfirmButton: false,
        timer: 2800,
        timerProgressBar: true,
        background: '#0f172a',
        color: '#f8fafc',
        customClass: { container: 'uf-swal-front' }
      });
      return;
    }

    showToast(message, 'warning');
  };

  const applyBlockedMode = (message = '') => {
    if (!document.body) return;
    document.body.dataset.accountBlocked = '1';
    document.body.classList.add('is-account-blocked');

    if (String(message || '').trim()) {
      showBlockedActionToast(String(message || '').trim());
    }

    const hardDisableSelectors = [
      '#editToggleBtn',
      '#fabEditProfile',
      '#saveUserBtn',
      '#openAvatarCameraBtn',
      '#pickAvatarFromFileBtn',
      '#captureAvatarBtn',
      '#formRandomAvatarBtn',
      '#profileRandomAvatarBtn',
      '#changePasswordForm button[type="submit"]',
    ];

    if (!isDeleteRequestPending()) {
      hardDisableSelectors.push('#requestDeleteAccountBtn');
    }

    hardDisableSelectors.forEach((selector) => {
      const node = document.querySelector(selector);
      if (!node) return;
      node.setAttribute('disabled', 'disabled');
      node.classList.add('is-blocked-disabled');
      node.setAttribute('title', 'Account blocked until admin approval');
    });

    const lockFields = document.querySelectorAll('#userForm input, #userForm select, #userForm textarea, #changePasswordForm input, #changePasswordForm textarea');
    lockFields.forEach((field) => {
      field.setAttribute('disabled', 'disabled');
    });

    closeModal();
  };

  const isAllowedBlockedTarget = (target) => {
    if (!(target instanceof Element)) return false;
    return Boolean(
      target.closest('.blocked-allow') ||
      target.closest('.nav-profile') ||
      target.closest('.nav-profile-btn') ||
      target.closest('.nav-dropdown') ||
      target.closest('.nav-dropdown-item') ||
      target.closest('.swal2-container') ||
      target.closest('.swal2-popup') ||
      target.closest('.profile-toast-stack') ||
      target.closest('#closeLinkedAccountsModalBtn') ||
      target.closest('#cancelLinkedAccountsBtn')
    );
  };

  document.addEventListener('click', (event) => {
    if (!isBlockedAccount()) return;
    if (isAllowedBlockedTarget(event.target)) return;

    const interactive = event.target instanceof Element
      ? event.target.closest('a, button, input, select, textarea, label, [role="button"], [onclick], [data-clickable]')
      : null;

    if (!interactive) return;

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
    showBlockedActionToast();
  }, true);

  const readSnapshot = () => {
    if (!userForm) return '';
    const read = (id) => String(document.getElementById(id)?.value || '').trim();
    return JSON.stringify({
      first_name: read('formFirstName'),
      last_name: read('formLastName'),
      email: read('formEmail'),
      phone: read('formPhone'),
      role: read('formRole'),
      status: read('formStatus'),
      country: read('formCountry'),
      exact_location: read('formExactLocation'),
      latitude: read('formLatitude'),
      longitude: read('formLongitude'),
      map_address: read('formMapAddress'),
      bio: read('formBio'),
      avatar_url: read('formAvatarUrl')
    });
  };

  const avatarFallbackSvg = (seed = 'U') => {
    const initials = String(seed || 'U').slice(0, 2).toUpperCase();
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><defs><linearGradient id="gradp" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#60a5fa"/><stop offset="100%" stop-color="#6366f1"/></linearGradient></defs><rect width="120" height="120" rx="60" fill="url(#gradp)" opacity="0.25"/><circle cx="60" cy="46" r="20" fill="#1e3a8a" opacity="0.92"/><path d="M24 93c6-16 20-26 36-26s30 10 36 26" fill="#1e3a8a" opacity="0.92"/><text x="60" y="108" text-anchor="middle" font-family="Poppins,Arial,sans-serif" font-size="16" fill="#1e3a8a" opacity="0.75">${initials}</text></svg>`;
    return `data:image/svg+xml,${encodeURIComponent(svg)}`;
  };

  const updateAvatarPreview = (url, firstName = 'U', lastName = 'S') => {
    if (!avatarPreview) return;
    const cleaned = String(url || '').trim();
    if (cleaned) {
      avatarPreview.src = cleaned;
      if (avatarFallback) avatarFallback.style.display = 'none';
      return;
    }
    avatarPreview.src = avatarFallbackSvg(`${String(firstName).charAt(0)}${String(lastName).charAt(0)}`);
    if (avatarFallback) avatarFallback.style.display = 'flex';
  };

  const stopAvatarCamera = () => {
    if (avatarCameraStream) {
      avatarCameraStream.getTracks().forEach((track) => track.stop());
      avatarCameraStream = null;
    }
    if (avatarCameraVideo) {
      avatarCameraVideo.srcObject = null;
      avatarCameraVideo.classList.remove('active');
    }
    if (captureAvatarBtn) captureAvatarBtn.disabled = true;
  };

  const uploadAvatarData = async (imageData, fileName = 'avatar.png') => {
    const response = await fetch('profile.php?action=profile_upload_avatar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ image_data: imageData, file_name: fileName })
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.message || 'Could not upload avatar.');
    }
    return String(result.avatar_url || '').trim();
  };

  const persistAvatarPath = async (avatarUrl) => {
    const response = await fetch('profile.php?action=profile_save_avatar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ avatar_url: String(avatarUrl || '').trim() })
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.message || 'Could not persist avatar.');
    }
    return String(result.avatar_url || avatarUrl || '').trim();
  };

  const generateAndStoreRandomAvatar = async (seedBase = 'profile-random') => {
    const firstName = String(document.getElementById('formFirstName')?.value || '').trim();
    const lastName = String(document.getElementById('formLastName')?.value || '').trim();
    const seed = `${seedBase}-${firstName}-${lastName}-${Date.now()}`;
    const avatarUrl = `https://api.dicebear.com/9.x/avataaars/svg?seed=${encodeURIComponent(seed)}`;
    if (avatarUrlInput) avatarUrlInput.value = avatarUrl;
    updateAvatarPreview(avatarUrl, firstName || 'U', lastName || 'S');
    if (profileAvatar) profileAvatar.src = avatarUrl;
    return avatarUrl;
  };

  const passwordMessage = (value, required = true) => {
    const raw = String(value || '');
    if (!raw && !required) return '';
    if (!raw) return 'Password is required.';
    if (raw.length < 10) return 'Password must be at least 10 characters.';
    if (raw.length > 128) return 'Password must be at most 128 characters.';
    if (/\s/.test(raw)) return 'Password cannot contain spaces.';
    if (!/[a-z]/.test(raw)) return 'Password needs at least one lowercase letter.';
    if (!/[A-Z]/.test(raw)) return 'Password needs at least one uppercase letter.';
    if (!/[0-9]/.test(raw)) return 'Password needs at least one number.';
    if (!/[^A-Za-z0-9]/.test(raw)) return 'Password needs at least one symbol.';
    return '';
  };

  const securityAlert = (message, type = 'error', title = 'Security check') => {
    if (window.Swal) {
      window.Swal.fire({
        icon: type,
        title,
        text: message,
        confirmButtonColor: '#6366f1',
        background: '#0f172a',
        color: '#f8fafc',
        customClass: { container: 'uf-swal-front' }
      });
      return;
    }
    showToast(message, type === 'error' ? 'error' : 'warning');
  };

  const loadProfileExportSummary = async () => {
    const response = await fetch('profile.php?action=profile_export_summary');
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.message || 'Could not prepare export data.');
    }
    return result;
  };

  const buildExportPalette = () => {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    return isDark
      ? {
          surface: '#111827',
          surfaceSoft: 'rgba(255, 255, 255, 0.05)',
          border: 'rgba(255, 255, 255, 0.10)',
          borderStrong: 'rgba(255, 255, 255, 0.14)',
          text: '#f8fafc',
          muted: '#94a3b8',
          accent: '#6366f1',
          accentSoft: 'rgba(99, 102, 241, 0.16)',
          chip: 'rgba(255, 255, 255, 0.06)',
        }
      : {
          surface: '#ffffff',
          surfaceSoft: 'rgba(248, 250, 252, 0.9)',
          border: 'rgba(148, 163, 184, 0.2)',
          borderStrong: 'rgba(148, 163, 184, 0.26)',
          text: '#0f172a',
          muted: '#64748b',
          accent: '#4f46e5',
          accentSoft: 'rgba(79, 70, 229, 0.12)',
          chip: 'rgba(79, 70, 229, 0.08)',
        };
  };

  const buildExportModalHtml = (payload, palette) => {
    const summary = payload.summary || {};
    const user = summary.user || {};
    const stats = summary.stats || {};
    const activity = summary.activity || {};
    const templates = Array.isArray(payload.templates) && payload.templates.length > 0
      ? payload.templates
      : [
          { id: 'modern', label: 'Modern' },
          { id: 'minimal', label: 'Minimal' },
          { id: 'dark', label: 'Dark' },
        ];

    const statCards = [
      ['Friends', stats.friends || 0],
      ['Messages', stats.messages || 0],
      ['Stories', stats.stories || 0],
      ['Live', stats.live_sessions || 0],
    ].map(([label, value]) => `
      <div style="padding:12px;border-radius:14px;border:1px solid ${palette.border};background:${palette.surfaceSoft};">
        <div style="font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:${palette.muted};margin-bottom:4px;">${label}</div>
        <div style="font-size:18px;font-weight:700;color:${palette.text};">${value}</div>
      </div>
    `).join('');

    const activityLines = [
      ['Latest message', activity.latest_message_at || 'No messages yet'],
      ['Latest story', activity.latest_story_at || 'No stories yet'],
      ['Latest live', activity.latest_live_at || 'No live sessions yet'],
      ['Story views', stats.story_views || 0],
    ].map(([label, value]) => `
      <div style="display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid ${palette.border};">
        <span style="color:${palette.muted};font-size:12px;">${label}</span>
        <span style="color:${palette.text};font-size:12px;font-weight:600;text-align:right;">${String(value)}</span>
      </div>
    `).join('');

    const templateCards = templates.map((template, index) => {
      const isSelected = index === 0;
      return `
        <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border-radius:16px;border:1px solid ${isSelected ? palette.borderStrong : palette.border};background:${isSelected ? palette.accentSoft : palette.chip};cursor:pointer;">
          <input type="radio" name="profileExportTheme" value="${String(template.id)}" ${isSelected ? 'checked' : ''} style="margin-top:3px;">
          <span style="display:grid;gap:4px;">
            <strong style="color:${palette.text};font-size:13px;">${String(template.label)}</strong>
            <span style="color:${palette.muted};font-size:12px;line-height:1.45;">${template.id === 'dark' ? 'High contrast export for dark brand moods.' : template.id === 'minimal' ? 'Clean report with fewer decorative elements.' : 'Balanced export with accent color and cards.'}</span>
          </span>
        </label>
      `;
    }).join('');

    return `
      <div style="display:grid;gap:16px;text-align:left;">
        <div style="padding:14px 16px;border-radius:18px;border:1px solid ${palette.border};background:${palette.surfaceSoft};display:grid;gap:8px;">
          <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;">
            <div style="display:grid;gap:4px;">
              <strong style="font-size:18px;color:${palette.text};">${String(user.full_name || 'Profile Export')}</strong>
              <span style="font-size:12px;color:${palette.muted};">${String(user.email || 'No email provided')}</span>
            </div>
            <span style="padding:6px 10px;border-radius:999px;background:${palette.accentSoft};border:1px solid ${palette.border};color:${palette.text};font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">PDF Preview</span>
          </div>
          <div style="display:flex;flex-wrap:wrap;gap:8px;">
            ${['User info', 'Statistics', 'Activity', 'Achievements'].map((label) => `
              <span style="padding:6px 10px;border-radius:999px;background:${palette.chip};border:1px solid ${palette.border};color:${palette.text};font-size:11px;font-weight:600;">${label}</span>
            `).join('')}
          </div>
        </div>

        <div style="display:grid;gap:10px;">
          <p style="margin:0;color:${palette.muted};font-size:13px;line-height:1.6;">
            Choose the export style. The PDF will include profile details, statistics, activity summary, skills, and referral information.
          </p>
          <div style="display:grid;gap:10px;">${templateCards}</div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;">
          ${statCards}
        </div>

        <div style="padding:14px 16px;border-radius:16px;border:1px solid ${palette.border};background:${palette.surfaceSoft};display:grid;gap:8px;">
          <strong style="font-size:13px;color:${palette.text};">Activity summary</strong>
          <div>${activityLines}</div>
        </div>
      </div>
    `;
  };

  const addWrappedLines = (doc, text, x, y, maxWidth, fontSize = 10.5) => {
    doc.setFontSize(fontSize);
    const lines = doc.splitTextToSize(String(text || ''), maxWidth);
    doc.text(lines, x, y);
    return y + (lines.length * fontSize * 0.6);
  };

  const exportProfilePdf = async (theme = 'modern', payload = null) => {
    if (!window.jspdf?.jsPDF) {
      throw new Error('PDF engine is not available.');
    }

    const exportPayload = payload || await loadProfileExportSummary();
    const summary = exportPayload.summary || {};
    const user = summary.user || {};
    const stats = summary.stats || {};
    const activity = summary.activity || {};
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: 'mm', format: 'a4' });

    const palette = {
      modern: { bg: [244, 247, 251], panel: [255, 255, 255], accent: [37, 99, 235], text: [15, 23, 42], muted: [100, 116, 139], headerText: [255, 255, 255] },
      minimal: { bg: [255, 255, 255], panel: [255, 255, 255], accent: [17, 24, 39], text: [17, 24, 39], muted: [107, 114, 128], headerText: [255, 255, 255] },
      dark: { bg: [15, 23, 42], panel: [30, 41, 59], accent: [14, 165, 233], text: [241, 245, 249], muted: [148, 163, 184], headerText: [255, 255, 255] }
    }[theme] || { bg: [244, 247, 251], panel: [255, 255, 255], accent: [37, 99, 235], text: [15, 23, 42], muted: [100, 116, 139], headerText: [255, 255, 255] };

    doc.setFillColor(...palette.bg);
    doc.rect(0, 0, 210, 297, 'F');
    doc.setFillColor(...palette.panel);
    doc.roundedRect(10, 10, 190, 277, 8, 8, 'F');
    doc.setFillColor(...palette.accent);
    doc.roundedRect(10, 10, 190, 34, 8, 8, 'F');

    doc.setTextColor(...palette.headerText);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(22);
    doc.text(String(user.full_name || 'Profile Export'), 18, 26);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);
    doc.text(`Template: ${theme}  •  Exported ${new Date().toLocaleString()}`, 18, 34);

    doc.setTextColor(...palette.text);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(13);
    doc.text('User Info', 18, 58);
    doc.text('Activity Metrics', 110, 58);

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10.5);
    const infoRows = [
      ['Email', user.email || 'Not provided'],
      ['Role', user.role || 'member'],
      ['Title', user.title || 'Not provided'],
      ['Phone', user.phone || 'Not provided'],
      ['Location', user.exact_location || user.country || 'Not provided'],
      ['Invitation code', user.invitation_code || 'Not assigned'],
    ];
    infoRows.forEach(([label, value], index) => {
      const y = 67 + (index * 8);
      doc.setTextColor(...palette.muted);
      doc.text(`${label}:`, 18, y);
      doc.setTextColor(...palette.text);
      doc.text(String(value), 46, y);
    });

    const metricRows = [
      ['Friends', stats.friends || 0],
      ['Messages', stats.messages || 0],
      ['Stories', stats.stories || 0],
      ['Active stories', stats.active_stories || 0],
      ['Group chats', stats.group_chats || 0],
      ['Live sessions', stats.live_sessions || 0],
    ];
    metricRows.forEach(([label, value], index) => {
      const y = 67 + (index * 8);
      doc.setTextColor(...palette.muted);
      doc.text(`${label}:`, 110, y);
      doc.setTextColor(...palette.text);
      doc.text(String(value), 142, y);
    });

    let cursorY = 124;
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(13);
    doc.setTextColor(...palette.text);
    doc.text('Activity Summary', 18, cursorY);
    cursorY += 8;
    doc.setFont('helvetica', 'normal');
    cursorY = addWrappedLines(
      doc,
      [
        `Latest message: ${activity.latest_message_at || 'No messages yet'}`,
        `Latest story: ${activity.latest_story_at || 'No stories yet'}`,
        `Latest live session: ${activity.latest_live_at || 'No live sessions yet'}`,
        `Story views: ${stats.story_views || 0}`,
        `Archived stories: ${stats.archived_stories || 0}`,
        user.referrer_name ? `Referred by: ${user.referrer_name}${user.referrer_email ? ` (${user.referrer_email})` : ''}` : 'Referred by: Direct sign-up',
      ].join('\n'),
      18,
      cursorY,
      174
    );

    cursorY += 8;
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(13);
    doc.text('Bio & Skills', 18, cursorY);
    cursorY += 8;
    doc.setFont('helvetica', 'normal');
    cursorY = addWrappedLines(doc, `Bio: ${user.bio || 'No bio added yet.'}`, 18, cursorY, 174);
    cursorY += 4;
    addWrappedLines(doc, `Skills: ${user.skills || 'No skills listed yet.'}`, 18, cursorY, 174);

    const fileSafeName = String(user.full_name || 'profile')
      .trim()
      .replace(/[^a-z0-9]+/gi, '_')
      .replace(/^_+|_+$/g, '') || 'profile';
    doc.save(`${fileSafeName}_${theme}_export.pdf`);
  };

  const scorePasswordStrength = (rawPassword) => {
    const value = String(rawPassword || '');
    let score = 0;
    if (value.length >= 10) score += 30;
    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score += 20;
    if (/\d/.test(value)) score += 20;
    if (/[^A-Za-z0-9]/.test(value)) score += 20;
    if (value.length >= 14) score += 10;
    return Math.max(0, Math.min(100, score));
  };

  const bindSecurityFieldState = () => {
    const fields = Array.from(document.querySelectorAll('#changePasswordForm .sec-field'));
    fields.forEach((field) => {
      const input = field.querySelector('input');
      const label = field.querySelector('label');
      if (!input) return;

      const sync = () => {
        const hasValue = String(input.value || '').trim() !== '';
        const focused = document.activeElement === input;
        field.classList.toggle('is-active', hasValue || focused);
      };

      input.addEventListener('focus', sync);
      input.addEventListener('blur', sync);
      input.addEventListener('input', sync);
      input.addEventListener('change', sync);

      if (label) {
        label.addEventListener('click', () => {
          input.focus();
          field.classList.add('is-active');
        });
      }

      sync();
    });
  };

  const bindSecurityStrengthBar = () => {
    const newPasswordField = document.getElementById('securityNewPassword');
    if (!newPasswordField || !secStrengthFill) return;

    const updateStrength = () => {
      const score = scorePasswordStrength(newPasswordField.value);
      secStrengthFill.style.width = `${score}%`;
    };

    newPasswordField.addEventListener('input', updateStrength);
    updateStrength();
  };

  const resolveCapitalCoordinates = async (countryName) => {
    const normalized = String(countryName || '').trim();
    if (!normalized) return null;
    const key = normalized.toLowerCase();
    if (capitalCache.has(key)) return capitalCache.get(key);
    if (fallbackCapitals[key]) {
      capitalCache.set(key, fallbackCapitals[key]);
      return fallbackCapitals[key];
    }

    try {
      let response = await fetch(`https://restcountries.com/v3.1/name/${encodeURIComponent(normalized)}?fullText=true&fields=name,capitalInfo,latlng`);
      if (!response.ok) {
        response = await fetch(`https://restcountries.com/v3.1/name/${encodeURIComponent(normalized)}?fields=name,capitalInfo,latlng`);
      }
      const rows = await response.json();
      const row = Array.isArray(rows)
        ? rows.find((entry) => String(entry?.name?.common || '').toLowerCase() === key) || rows[0]
        : null;
      const coords = row?.capitalInfo?.latlng || row?.latlng || null;
      if (Array.isArray(coords) && coords.length >= 2) {
        const result = { lat: Number(coords[0]), lng: Number(coords[1]) };
        if (Number.isFinite(result.lat) && Number.isFinite(result.lng)) {
          capitalCache.set(key, result);
          return result;
        }
      }
    } catch (_error) {
    }

    return null;
  };

  const applyPickedLocation = (picked) => {
    if (!picked) return;
    const pickedCountry = String(picked.country || '').trim();
    const pickedDisplay = String(picked.display || picked.country || '').trim();
    if (countryInput) {
      countryInput.value = pickedCountry;
      countryInput.dispatchEvent(new Event('input', { bubbles: true }));
      countryInput.dispatchEvent(new Event('change', { bubbles: true }));
    }
    if (exactLocationInput) {
      exactLocationInput.value = pickedDisplay;
      exactLocationInput.dispatchEvent(new Event('input', { bubbles: true }));
      exactLocationInput.dispatchEvent(new Event('change', { bubbles: true }));
    }
    if (latInput) latInput.value = picked.lat == null ? '' : Number(picked.lat).toFixed(6);
    if (lngInput) lngInput.value = picked.lng == null ? '' : Number(picked.lng).toFixed(6);
    if (mapAddressInput) mapAddressInput.value = pickedDisplay;
  };

  const initGlobeMap = () => {
    if (!window.GlobeExplorer || !document.getElementById('profileUserGlobeMap')) return;
    window.GlobeExplorer.init('profileUserGlobeMap', { center: [20, 0], zoom: 2 });
    window.GlobeExplorer.onMapClick({
      onPick: (picked) => applyPickedLocation(picked)
    });
  };

  const openModal = async () => {
    if (!modal) return;
    modal.classList.add('open');
    modal.classList.add('active');
    updateBodyScrollState();

    if (!globeInitialized) {
      initGlobeMap();
      globeInitialized = true;
    }
    if (window.GlobeExplorer && typeof window.GlobeExplorer.invalidateSize === 'function') {
      setTimeout(() => window.GlobeExplorer.invalidateSize(), 180);
    }

    const storedLatitude = Number(latInput?.value || '');
    const storedLongitude = Number(lngInput?.value || '');
    if (Number.isFinite(storedLatitude) && Number.isFinite(storedLongitude) && window.GlobeExplorer && typeof window.GlobeExplorer.setView === 'function') {
      window.GlobeExplorer.setView([storedLatitude, storedLongitude], 13);
    } else {
      const country = String(countryInput?.value || '').trim();
      if (!country) {
        initialSnapshot = readSnapshot();
        return;
      }
      const coords = await resolveCapitalCoordinates(country);
      if (coords && window.GlobeExplorer && typeof window.GlobeExplorer.setView === 'function') {
        window.GlobeExplorer.setView([coords.lat, coords.lng], 5);
      }
    }

    initialSnapshot = readSnapshot();
  };

  const closeModal = () => {
    if (!modal) return;
    stopAvatarCamera();
    modal.classList.remove('open');
    modal.classList.remove('active');
    updateBodyScrollState();
  };

  document.querySelectorAll('.profile-tab').forEach((tab) => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.profile-tab').forEach((t) => t.classList.remove('active'));
      tab.classList.add('active');
      const target = tab.dataset.tab;
      document.querySelectorAll('.tab-content').forEach((content) => {
        content.classList.toggle('active', content.dataset.content === target);
      });
      if (typeof lucide !== 'undefined') lucide.createIcons();
    });
  });

  [editToggleBtn, fabEditProfile].forEach((button) => {
    if (!button) return;
    button.addEventListener('click', () => {
      if (isBlockedAccount()) {
        showToast('Account blocked. You cannot edit your profile until admin approval.', 'warning');
        return;
      }
      openModal();
    });
  });

  if (closeEditModal) closeEditModal.addEventListener('click', closeModal);
  if (cancelUserModal) cancelUserModal.addEventListener('click', closeModal);
  if (modal) {
    modal.addEventListener('click', (event) => {
      if (event.target === modal) closeModal();
    });
  }

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal?.classList.contains('open')) closeModal();
  });

  if (pickAvatarFromFileBtn && avatarFileInput) {
    pickAvatarFromFileBtn.addEventListener('click', () => avatarFileInput.click());
    avatarFileInput.addEventListener('change', async () => {
      const file = avatarFileInput.files?.[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = async () => {
        try {
          const uploadedPath = await uploadAvatarData(String(reader.result || ''), file.name || 'avatar-upload.png');
          await persistAvatarPath(uploadedPath);
          if (avatarUrlInput) avatarUrlInput.value = uploadedPath;
          updateAvatarPreview(uploadedPath);
          showToast('Avatar uploaded successfully.', 'success');
        } catch (error) {
          showToast(error.message || 'Avatar upload failed.', 'error');
        }
      };
      reader.readAsDataURL(file);
    });
  }

  const wireRandomAvatarButton = (button, seedBase, persistImmediately = false) => {
    if (!button) return;
    button.addEventListener('click', async () => {
      button.disabled = true;
      try {
        const generated = await generateAndStoreRandomAvatar(seedBase);
        if (persistImmediately) {
          await persistAvatarPath(generated);
        }
        showToast('Random avatar generated and saved.', 'success');
      } catch (error) {
        showToast(error.message || 'Random avatar generation failed.', 'error');
      } finally {
        button.disabled = false;
      }
    });
  };

  wireRandomAvatarButton(formRandomAvatarBtn, 'profile-form-random', false);
  wireRandomAvatarButton(profileRandomAvatarBtn, 'profile-header-random', true);

  if (openAvatarCameraBtn && avatarCameraVideo) {
    openAvatarCameraBtn.addEventListener('click', async () => {
      try {
        stopAvatarCamera();
        avatarCameraStream = await navigator.mediaDevices.getUserMedia({ video: { width: { ideal: 720 }, height: { ideal: 720 }, facingMode: 'user' }, audio: false });
        avatarCameraVideo.srcObject = avatarCameraStream;
        avatarCameraVideo.classList.add('active');
        if (captureAvatarBtn) captureAvatarBtn.disabled = false;
      } catch (_error) {
        showToast('Camera unavailable or permission denied.', 'error');
      }
    });
  }

  if (captureAvatarBtn && avatarCameraVideo && avatarCaptureCanvas) {
    captureAvatarBtn.addEventListener('click', async () => {
      if (!avatarCameraStream) return;
      const width = avatarCameraVideo.videoWidth || 640;
      const height = avatarCameraVideo.videoHeight || 640;
      avatarCaptureCanvas.width = width;
      avatarCaptureCanvas.height = height;
      const ctx = avatarCaptureCanvas.getContext('2d');
      if (!ctx) return;
      ctx.drawImage(avatarCameraVideo, 0, 0, width, height);

      try {
        const dataUrl = avatarCaptureCanvas.toDataURL('image/png', 0.95);
        const uploadedPath = await uploadAvatarData(dataUrl, `camera-avatar-${Date.now()}.png`);
        await persistAvatarPath(uploadedPath);
        if (avatarUrlInput) avatarUrlInput.value = uploadedPath;
        updateAvatarPreview(uploadedPath);
        showToast('Camera avatar captured and saved.', 'success');
        stopAvatarCamera();
      } catch (error) {
        showToast(error.message || 'Could not save camera avatar.', 'error');
      }
    });
  }

  if (userForm) {
    userForm.addEventListener('submit', async (event) => {
      event.preventDefault();

      if (isBlockedAccount()) {
        showToast('Account blocked. Profile editing is disabled until admin approval.', 'warning');
        return;
      }

      if (window.UserValidation && !window.UserValidation.validateForm(userForm, 'profile')) {
        return;
      }

      const payload = {
        first_name: String(document.getElementById('formFirstName')?.value || '').trim(),
        last_name: String(document.getElementById('formLastName')?.value || '').trim(),
        email: String(document.getElementById('formEmail')?.value || '').trim(),
        phone: String(document.getElementById('formPhone')?.value || '').trim(),
        role: String(document.getElementById('formRole')?.value || 'client').trim().toLowerCase(),
        country: String(document.getElementById('formCountry')?.value || '').trim(),
        exact_location: String(document.getElementById('formExactLocation')?.value || '').trim(),
        latitude: String(document.getElementById('formLatitude')?.value || '').trim(),
        longitude: String(document.getElementById('formLongitude')?.value || '').trim(),
        map_address: String(document.getElementById('formMapAddress')?.value || '').trim(),
        bio: String(document.getElementById('formBio')?.value || '').trim(),
        avatar_url: String(document.getElementById('formAvatarUrl')?.value || '').trim()
      };

      if (!payload.exact_location) {
        payload.exact_location = payload.map_address || payload.country;
      }

      if (!payload.first_name || !payload.last_name || !payload.email) {
        showToast('First name, last name, and email are required.', 'error');
        return;
      }

      const currentSnapshot = readSnapshot();
      if (currentSnapshot === initialSnapshot) {
        showToast('No changes detected.', 'warning');
        return;
      }

      if (saveUserBtn) {
        saveUserBtn.disabled = true;
        saveUserBtn.textContent = 'Saving...';
      }

      try {
        const response = await fetch('profile.php?action=profile_update', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.message || 'Could not update profile.');
        }

        const savedUser = result && typeof result === 'object' && result.user ? result.user : payload;
        const savedCountry = String(savedUser.country || payload.country || '').trim();
        const savedExactLocation = String(savedUser.exact_location || payload.exact_location || payload.map_address || savedCountry || '').trim();
        const savedLatitude = savedUser.latitude == null ? '' : String(savedUser.latitude);
        const savedLongitude = savedUser.longitude == null ? '' : String(savedUser.longitude);
        const savedAvatarUrl = String(savedUser.avatar_url || payload.avatar_url || '').trim();
        const savedRole = String(savedUser.role || payload.role || 'client').trim().toLowerCase();
        const savedBio = String(savedUser.bio || payload.bio || '').trim();

        if (countryInput) countryInput.value = savedCountry;
        if (exactLocationInput) exactLocationInput.value = savedExactLocation;
        if (latInput) latInput.value = savedLatitude;
        if (lngInput) lngInput.value = savedLongitude;
        if (mapAddressInput) mapAddressInput.value = savedExactLocation;
        if (avatarUrlInput) avatarUrlInput.value = savedAvatarUrl;

        const formRoleInput = document.getElementById('formRole');
        if (formRoleInput) formRoleInput.value = savedRole;

        const fullName = `${savedUser.first_name || payload.first_name} ${savedUser.last_name || payload.last_name}`.trim();
        const profileName = document.querySelector('.profile-name');
        if (profileName) profileName.textContent = fullName;
        const navName = document.querySelector('.nav-dropdown-header strong');
        if (navName) navName.textContent = fullName;

        const titleTag = document.getElementById('profileTitleTag');
        if (titleTag) {
          const roleLabel = savedRole === 'freelancer' ? 'Freelancer' : 'Client';
          titleTag.innerHTML = `<i data-lucide="code-2" class="w-3 h-3"></i> ${roleLabel}`;
        }

        if (profileLocationText) {
          profileLocationText.innerHTML = `<i data-lucide="map-pin" class="w-3.5 h-3.5"></i> ${savedExactLocation || savedCountry || 'Unknown'}`;
        }
        if (aboutBioText) {
          if (savedBio) {
            aboutBioText.textContent = savedBio;
          } else {
            aboutBioText.innerHTML = '<strong>Driven member of Diversity.is</strong> focused on building inclusive digital products, shipping reliable work, and collaborating with teams across design, engineering, and strategy.';
          }
        }
        if (profileAvatar && savedAvatarUrl) {
          profileAvatar.src = savedAvatarUrl;
        }
        updateAvatarPreview(savedAvatarUrl, savedUser.first_name || payload.first_name, savedUser.last_name || payload.last_name);

        initialSnapshot = readSnapshot();
        if (typeof lucide !== 'undefined') lucide.createIcons();
        showToast(result.message || 'Profile updated successfully.', 'success');
        closeModal();
      } catch (error) {
        showToast(error.message || 'Could not update profile right now.', 'error');
      } finally {
        if (saveUserBtn) {
          saveUserBtn.disabled = false;
          saveUserBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>Save Profile';
        }
      }
    });
  }

  if (changePasswordForm) {
    changePasswordForm.addEventListener('submit', async (event) => {
      event.preventDefault();

      if (isBlockedAccount()) {
        securityAlert('Account blocked. Password changes are disabled until admin approval.', 'warning', 'Account blocked');
        return;
      }

      const currentPassword = String(document.getElementById('securityCurrentPassword')?.value || '').trim();
      const newPassword = String(document.getElementById('securityNewPassword')?.value || '').trim();
      const confirmPassword = String(document.getElementById('securityConfirmPassword')?.value || '').trim();

      if (!currentPassword || !newPassword || !confirmPassword) {
        securityAlert('All password fields are required.', 'error', 'Missing fields');
        return;
      }

      const passwordError = passwordMessage(newPassword, true);
      if (passwordError) {
        securityAlert(passwordError, 'error', 'Weak password');
        return;
      }

      if (newPassword !== confirmPassword) {
        securityAlert('Confirm New Password must match New Password.', 'error', 'Password mismatch');
        return;
      }

      try {
        const response = await fetch('profile.php?action=change_password', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            current_password: currentPassword,
            new_password: newPassword,
            confirm_password: confirmPassword
          })
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.message || 'Could not change password.');
        }
        changePasswordForm.reset();
        bindSecurityFieldState();
        bindSecurityStrengthBar();
        showToast('Password updated successfully.', 'success');
      } catch (error) {
        const message = String(error.message || 'Could not change password.');
        if (/current password is incorrect/i.test(message)) {
          securityAlert(message, 'error', 'Current password is incorrect');
          const currentInput = document.getElementById('securityCurrentPassword');
          if (currentInput) {
            currentInput.focus();
            currentInput.select();
          }
          return;
        }
        securityAlert(message, 'error', 'Password update failed');
      }
    });
  }

  if (requestDeleteAccountBtn) {
    requestDeleteAccountBtn.addEventListener('click', async () => {
      if (isDeleteRequestPending()) {
        let cancelRequestResult;
        if (!window.Swal) {
          const confirmed = window.confirm('Cancel your deletion request and reactivate your account?');
          if (!confirmed) return;
          cancelRequestResult = { isConfirmed: true };
        } else {
          cancelRequestResult = await window.Swal.fire({
            title: 'Cancel deletion request?',
            html: `
              <div style="text-align:left;">
                <p style="margin:0 0 10px;color:#334155;font-size:13px;line-height:1.6;">
                  This will restore your account access and remove the pending deletion request.
                </p>
                <div style="padding:14px 16px;border-radius:16px;border:1px solid rgba(34,197,94,0.18);background:linear-gradient(180deg,rgba(236,253,245,0.95),rgba(255,255,255,0.98));color:#14532d;font-size:13px;line-height:1.6;">
                  You can return to normal use immediately after canceling.
                </div>
              </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Cancel Request',
            cancelButtonText: 'Keep It Pending',
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#e2e8f0',
            background: '#ffffff',
            color: '#0f172a',
            width: 460,
            padding: '24px 22px 20px',
            reverseButtons: true,
            focusConfirm: false,
            allowOutsideClick: () => !window.Swal.isLoading(),
            customClass: {
              popup: 'delete-request-swal-popup',
              container: 'uf-swal-front',
              confirmButton: 'delete-request-confirm-btn',
              cancelButton: 'delete-request-cancel-btn'
            }
          });
        }

        if (!cancelRequestResult.isConfirmed) return;

        try {
          const response = await fetch('profile.php?action=cancel_delete_request', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({})
          });
          const result = await response.json();
          if (!response.ok || !result.success) {
            throw new Error(result.message || 'Could not cancel deletion request.');
          }

          document.body.dataset.deleteRequestPending = '0';
          document.body.dataset.accountBlocked = '0';
          showToast(result.message || 'Deletion request canceled successfully.', 'success');
          window.location.reload();
          return;
        } catch (error) {
          showToast(error.message || 'Could not cancel deletion request.', 'error');
          return;
        }
      }

      if (isBlockedAccount()) {
        showBlockedActionToast('Your account is blocked. Cancel the deletion request to restore access.');
        return;
      }

      let requestResult;
      if (!window.Swal) {
        const confirmed = window.confirm('Submit a permanent deletion request? Your account will be blocked until admin approval.');
        if (!confirmed) return;
        requestResult = { isConfirmed: true, value: { reason: '' } };
      } else {
        requestResult = await window.Swal.fire({
          title: 'Request Permanent Account Deletion',
          html: `
            <div style="text-align:left;display:grid;gap:12px;">
              <div style="display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:18px;border:1px solid rgba(239,68,68,0.2);background:linear-gradient(180deg,rgba(254,242,242,0.98),rgba(255,255,255,0.98));">
                <div style="width:42px;height:42px;border-radius:14px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#fecaca,#f87171);color:#7f1d1d;flex:0 0 auto;box-shadow:0 10px 22px rgba(220,38,38,0.18);">
                  <i data-lucide="shield-alert" style="width:18px;height:18px;"></i>
                </div>
                <div>
                  <div style="font-size:13px;font-weight:700;color:#7f1d1d;margin-bottom:2px;">High-impact account action</div>
                  <div style="font-size:12px;color:#7f1d1d;line-height:1.5;">Your request is reviewed by an admin before any permanent deletion.</div>
                </div>
              </div>

              <div style="padding:12px 14px;border-radius:14px;border:1px solid rgba(226,232,240,0.95);background:#f8fafc;display:grid;gap:8px;">
                <div style="font-size:12px;font-weight:700;color:#0f172a;letter-spacing:0.02em;text-transform:uppercase;">What happens next</div>
                <div style="font-size:12px;color:#334155;line-height:1.65;display:grid;gap:4px;">
                  <span>1. Account is temporarily blocked.</span>
                  <span>2. Admin reviews your request and reason.</span>
                  <span>3. You can cancel before approval at any time.</span>
                </div>
              </div>

              <label for="deleteReasonField" style="display:block;margin-bottom:8px;font-size:12px;font-weight:700;color:#0f172a;">Reason <span style="font-weight:500;color:#94a3b8;">(optional, but recommended)</span></label>
              <textarea id="deleteReasonField" rows="4" placeholder="Tell us why you are requesting deletion..." style="width:100%;resize:none;border-radius:16px;border:1.5px solid rgba(226,232,240,0.98);background:#f8fafc;padding:12px 14px;font-family:'Poppins',sans-serif;font-size:13px;line-height:1.6;color:#0f172a;outline:none;box-sizing:border-box;"></textarea>
              <label style="display:flex;align-items:flex-start;gap:10px;margin-top:14px;padding:12px 14px;border-radius:14px;background:#f8fafc;border:1px solid rgba(226,232,240,0.98);color:#334155;font-size:12.5px;line-height:1.6;">
                <input id="deleteAckField" type="checkbox" style="margin-top:3px;accent-color:#dc2626;" />
                <span>I understand this will block my account until an admin reviews the request.</span>
              </label>
            </div>
          `,
          showCancelButton: true,
          confirmButtonText: 'Send For Admin Review',
          cancelButtonText: 'Keep My Account',
          confirmButtonColor: '#dc2626',
          cancelButtonColor: '#e2e8f0',
          background: '#ffffff',
          color: '#0f172a',
          width: 520,
          padding: '24px 22px 20px',
          reverseButtons: true,
          focusConfirm: false,
          allowOutsideClick: () => !window.Swal.isLoading(),
          customClass: {
            popup: 'delete-request-swal-popup',
            container: 'uf-swal-front',
            confirmButton: 'delete-request-confirm-btn',
            cancelButton: 'delete-request-cancel-btn'
          },
          preConfirm: () => {
            const popup = window.Swal.getPopup();
            const reason = String(popup?.querySelector('#deleteReasonField')?.value || '').trim();
            const acknowledged = Boolean(popup?.querySelector('#deleteAckField')?.checked);
            if (!acknowledged) {
              window.Swal.showValidationMessage('Please confirm that you understand the deletion request.');
              return false;
            }
            return { reason };
          },
          didOpen: () => {
            const popup = window.Swal.getPopup();
            const textarea = popup?.querySelector('#deleteReasonField');
            if (textarea) textarea.focus();
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
              window.lucide.createIcons();
            }
          },
        });
      }

      if (!requestResult.isConfirmed) return;
      const reason = String(requestResult.value?.reason || '').trim();

      try {
        const response = await fetch('profile.php?action=request_delete', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ reason: reason || 'User requested account deletion from profile page.' })
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.message || 'Could not submit delete request.');
        }
        const successMessage = String(result.message || 'Delete request sent. Please wait for admin approval.').trim();
        document.body.dataset.deleteRequestPending = '1';
        setDeleteRequestButtonState(true);
        applyBlockedMode(successMessage);
      } catch (error) {
        showToast(error.message || 'Delete request failed.', 'error');
      }
    });
  }

  if (requestDeleteAccountBtn) {
    setDeleteRequestButtonState(isDeleteRequestPending());
  }

  if (profilePitchVideo && videoOverlayPlay) {
    const syncOverlayState = () => {
      videoOverlayPlay.style.display = profilePitchVideo.paused ? 'flex' : 'none';
    };

    videoOverlayPlay.addEventListener('click', () => profilePitchVideo.play());
    profilePitchVideo.addEventListener('click', () => {
      if (profilePitchVideo.paused) profilePitchVideo.play();
      else profilePitchVideo.pause();
    });
    profilePitchVideo.addEventListener('play', syncOverlayState);
    profilePitchVideo.addEventListener('pause', syncOverlayState);
    syncOverlayState();
  }

  if (pipVideoBtn && profilePitchVideo) {
    pipVideoBtn.addEventListener('click', async () => {
      try {
        if (document.pictureInPictureElement) {
          await document.exitPictureInPicture();
        } else if (document.pictureInPictureEnabled && !profilePitchVideo.disablePictureInPicture) {
          await profilePitchVideo.requestPictureInPicture();
        }
      } catch (_error) {
      }
    });
  }

  const profileFab = document.getElementById('profileFab');
  const profileFabMenu = document.getElementById('profileFabMenu');
  if (profileFab && profileFabMenu) {
    profileFab.addEventListener('click', () => {
      profileFabMenu.classList.toggle('open');
    });
    document.addEventListener('click', (event) => {
      if (!profileFab.contains(event.target) && !profileFabMenu.contains(event.target)) {
        profileFabMenu.classList.remove('open');
      }
    });
  }

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => {
    if (char === '&') return '&amp;';
    if (char === '<') return '&lt;';
    if (char === '>') return '&gt;';
    if (char === '"') return '&quot;';
    return '&#39;';
  });

  const getUserInitials = (firstName, lastName) => {
    const first = String(firstName || '').trim().charAt(0);
    const last = String(lastName || '').trim().charAt(0);
    const initials = `${first}${last}`.trim().toUpperCase();
    return initials || 'U';
  };

  const getDisplayName = (user) => {
    const first = String(user?.first_name || '').trim();
    const last = String(user?.last_name || '').trim();
    const merged = `${first} ${last}`.trim();
    return merged || 'Unknown member';
  };

  const resolveAvatarUrl = (rawUrl, seed = 'User') => {
    const cleaned = String(rawUrl || '').trim();
    if (cleaned) return cleaned;
    return avatarFallbackSvg(seed);
  };

  const resolvePublicMediaUrl = (rawUrl) => {
    const raw = String(rawUrl || '').trim();
    if (!raw) return '';
    if (/^(https?:|data:|blob:)/i.test(raw)) return raw;

    const normalized = raw.replace(/\\/g, '/');
    if (normalized.startsWith('/')) return normalized;

    const cleaned = normalized
      .replace(/^(\.\/)+/, '')
      .replace(/^(\.\.\/)+/, '');

    if (cleaned.startsWith('assets/')) return `../../${cleaned}`;
    if (cleaned.startsWith('uploads/')) return `../../assets/${cleaned}`;
    return `../../${cleaned}`;
  };

  const getLiveStreamForUser = (userId) => {
    const uid = Number(userId || 0);
    if (!uid) return null;
    const streams = Array.isArray(socialState.liveStreams) ? socialState.liveStreams : [];
    return streams.find((stream) => Number(stream?.host_user_id || 0) === uid) || null;
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

  const getStoryExpiryLabel = (expiresAt) => {
    const ts = Date.parse(String(expiresAt || ''));
    if (!Number.isFinite(ts)) return '';
    const diff = ts - Date.now();
    if (diff <= 0) return 'Expired';
    const hours = Math.floor(diff / 3600000);
    if (hours >= 1) return `${hours}h left`;
    const mins = Math.max(1, Math.floor(diff / 60000));
    return `${mins}m left`;
  };

  const extractUsernameFromUrl = (urlValue) => {
    try {
      const parsed = new URL(String(urlValue || '').trim());
      const segments = parsed.pathname.split('/').filter(Boolean);
      return segments.length ? segments[segments.length - 1].slice(0, 120) : '';
    } catch (_error) {
      return '';
    }
  };

  const normalizeLinkedAccountLabel = (platform) => {
    const key = String(platform || '').toLowerCase();
    if (key === 'linkedin') return 'LinkedIn';
    if (key === 'github') return 'GitHub';
    if (key === 'discord') return 'Discord';
    if (key === 'portfolio') return 'Portfolio';
    if (key === 'twitter') return 'Twitter';
    return key ? `${key.charAt(0).toUpperCase()}${key.slice(1)}` : 'Link';
  };

  const linkedAccountDomainRules = {
    linkedin: ['linkedin.com'],
    github: ['github.com'],
    discord: ['discord.com', 'discordapp.com'],
    twitter: ['twitter.com', 'x.com'],
    portfolio: [],
  };

  const normalizeLinkedAccountUrl = (rawValue) => {
    const trimmed = String(rawValue || '').trim();
    if (!trimmed) return '';
    if (/^https?:\/\//i.test(trimmed)) return trimmed;
    return `https://${trimmed}`;
  };

  const hostMatchesDomain = (host, domain) => {
    const cleanHost = String(host || '').trim().toLowerCase().replace(/^www\./, '');
    const cleanDomain = String(domain || '').trim().toLowerCase();
    if (!cleanHost || !cleanDomain) return false;
    return cleanHost === cleanDomain || cleanHost.endsWith(`.${cleanDomain}`);
  };

  const ensureLinkedAccountFeedback = (input) => {
    const row = input?.closest('.linked-account-row');
    if (!row) return null;
    let feedback = row.querySelector('.linked-account-feedback');
    if (!feedback) {
      feedback = document.createElement('p');
      feedback.className = 'linked-account-feedback';
      row.appendChild(feedback);
    }
    return feedback;
  };

  const setLinkedAccountInputState = (input, mode = 'neutral', message = '') => {
    if (!input) return;
    const row = input.closest('.linked-account-row');
    const feedback = ensureLinkedAccountFeedback(input);
    if (row) {
      row.classList.remove('is-invalid', 'is-valid');
      if (mode === 'invalid') row.classList.add('is-invalid');
      if (mode === 'valid') row.classList.add('is-valid');
    }
    input.classList.remove('is-invalid', 'is-valid');
    if (mode === 'invalid') input.classList.add('is-invalid');
    if (mode === 'valid') input.classList.add('is-valid');
    if (feedback) {
      feedback.textContent = String(message || '');
      feedback.hidden = !message;
    }
  };

  const validateLinkedAccountInput = (input, { markTouched = false } = {}) => {
    const platform = String(input?.dataset.platform || '').trim().toLowerCase();
    const rawValue = String(input?.value || '').trim();

    if (!platform) {
      setLinkedAccountInputState(input, 'invalid', 'Platform is required.');
      return { valid: false, account: null, message: 'Platform is required.' };
    }

    if (!rawValue) {
      setLinkedAccountInputState(input, 'neutral', '');
      return { valid: true, account: null, message: '' };
    }

    const preparedUrl = normalizeLinkedAccountUrl(rawValue);

    let parsed;
    try {
      parsed = new URL(preparedUrl);
    } catch (_error) {
      setLinkedAccountInputState(input, 'invalid', 'Invalid URL format.');
      return { valid: false, account: null, message: 'Invalid URL format.' };
    }

    const protocol = String(parsed.protocol || '').toLowerCase();
    const host = String(parsed.hostname || '').toLowerCase();
    if (!(protocol === 'http:' || protocol === 'https:') || !host) {
      setLinkedAccountInputState(input, 'invalid', 'URL must start with http:// or https://');
      return { valid: false, account: null, message: 'URL must start with http:// or https://' };
    }

    const allowedDomains = linkedAccountDomainRules[platform] || [];
    if (allowedDomains.length > 0 && !allowedDomains.some((domain) => hostMatchesDomain(host, domain))) {
      setLinkedAccountInputState(input, 'invalid', 'URL domain does not match selected platform.');
      return { valid: false, account: null, message: 'URL domain does not match selected platform.' };
    }

    if (markTouched && input.value !== preparedUrl) {
      input.value = preparedUrl;
    }

    setLinkedAccountInputState(input, 'valid', 'URL format valid. Verification runs when saving.');

    return {
      valid: true,
      account: {
        platform,
        profile_url: preparedUrl,
        account_label: normalizeLinkedAccountLabel(platform),
        username: extractUsernameFromUrl(preparedUrl),
        is_public: 1,
      },
      message: '',
    };
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
    let parsed;
    try {
      parsed = text ? JSON.parse(text) : {};
    } catch (_error) {
      parsed = { success: false, message: 'Unexpected server response.' };
    }
    if (!response.ok || parsed?.success === false) {
      const error = new Error(String(parsed?.message || 'Request failed.'));
      error.payload = parsed || {};
      error.httpStatus = response.status;
      throw error;
    }
    return parsed || {};
  };

  const profileGet = (action, params = {}) => requestJson(buildActionUrl(action, params), { method: 'GET' });
  const profilePost = (action, payload = {}) => requestJson(buildActionUrl(action), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload || {})
  });

  const updateBodyScrollState = () => {
    const modalOpen = Boolean(modal?.classList.contains('open'));
    const storyComposerOpen = Boolean(storyComposer && !storyComposer.hidden);
    document.body.style.overflow = modalOpen || storyComposerOpen ? 'hidden' : '';
  };

  const updateMessagesNavCount = () => {
    if (!messagesNavCount) return;
    const unread = Math.max(0, Number(socialState.unreadTotal || 0));
    messagesNavCount.textContent = String(unread);
    messagesNavCount.hidden = unread <= 0;
  };

  const renderLinkedAccountsPreview = () => {
    const container = document.querySelector('.connections-list');
    if (!container) return;

    const accountItems = Array.isArray(socialState.linkedAccounts) ? socialState.linkedAccounts : [];
    if (!accountItems.length) {
      container.innerHTML = [
        '<div class="connection-item">',
        '<div class="connection-icon"><span>+</span></div>',
        '<div class="connection-info"><span class="connection-name">No linked accounts yet</span><span class="connection-status">Add links to boost profile trust</span></div>',
        '<button class="connection-connect-btn" id="quickLinkAccountBtn">Add</button>',
        '</div>'
      ].join('');
      const newQuickButton = document.getElementById('quickLinkAccountBtn');
      if (newQuickButton) {
        newQuickButton.addEventListener('click', () => {
          if (linkedAccountsModal) {
            linkedAccountsModal.hidden = false;
            linkedAccountsModal.classList.add('active');
          }
        });
      }
      return;
    }

    const iconMap = {
      linkedin: '💼',
      github: '🐙',
      discord: '💬',
      portfolio: '🌐',
      twitter: '@'
    };

    container.innerHTML = accountItems.map((account) => {
      const platform = String(account?.platform || '').toLowerCase();
      const label = String(account?.account_label || '').trim() || normalizeLinkedAccountLabel(platform);
      const username = String(account?.username || '').trim();
      const profileUrl = String(account?.profile_url || '').trim() || '#';
      const isVerified = Number(account?.verified || 0) === 1;
      const icon = iconMap[platform] || '🔗';
      const statusLabel = username ? `@${username}` : 'Linked account';
      return [
        '<div class="connection-item">',
        `<div class="connection-icon"><span>${icon}</span></div>`,
        '<div class="connection-info">',
        `<span class="connection-name">${escapeHtml(label)}</span>`,
        `<span class="connection-status ${isVerified ? 'is-verified' : 'is-unverified'}">${escapeHtml(statusLabel)} ${isVerified ? '· Verified' : '· Unverified'}</span>`,
        '</div>',
        `<a class="connection-connect-btn" href="${escapeHtml(profileUrl)}" target="_blank" rel="noopener noreferrer">Open</a>`,
        '</div>'
      ].join('');
    }).join('');
  };

  const hydrateLinkedAccountsForm = () => {
    if (!linkedAccountUrlInputs.length) return;
    const mapByPlatform = new Map();
    (socialState.linkedAccounts || []).forEach((account) => {
      const key = String(account?.platform || '').toLowerCase();
      if (!key || mapByPlatform.has(key)) return;
      mapByPlatform.set(key, {
        url: String(account?.profile_url || '').trim(),
        verified: Number(account?.verified || 0) === 1,
      });
    });

    linkedAccountUrlInputs.forEach((input) => {
      const platform = String(input.dataset.platform || '').toLowerCase();
      const entry = mapByPlatform.get(platform) || { url: '', verified: false };
      input.value = entry.url || '';
      if (!entry.url) {
        setLinkedAccountInputState(input, 'neutral', '');
      } else if (entry.verified) {
        setLinkedAccountInputState(input, 'valid', 'Verified account.');
      } else {
        setLinkedAccountInputState(input, 'neutral', 'Saved, waiting verification update.');
      }
    });
  };

  const openLinkedAccountsModal = () => {
    if (!linkedAccountsModal) return;
    hydrateLinkedAccountsForm();
    linkedAccountsModal.hidden = false;
    linkedAccountsModal.classList.add('active');
  };

  const closeLinkedAccountsModal = () => {
    if (!linkedAccountsModal) return;
    linkedAccountsModal.classList.remove('active');
    linkedAccountsModal.hidden = true;
  };

  const collectLinkedAccountsPayload = () => {
    const accounts = [];
    const invalidInputs = [];
    let hasPrimary = false;

    linkedAccountUrlInputs.forEach((input) => {
      const result = validateLinkedAccountInput(input, { markTouched: true });
      if (!result.valid) {
        invalidInputs.push(input);
        return;
      }
      if (!result.account) return;

      const isPrimary = hasPrimary ? 0 : 1;
      accounts.push({
        ...result.account,
        is_primary: isPrimary,
      });
      hasPrimary = true;
    });

    return {
      accounts,
      hasErrors: invalidInputs.length > 0,
    };
  };

  const isFriendWithUser = (targetUserId) => {
    const id = Number(targetUserId || 0);
    if (!id) return false;
    return (socialState.friends || []).some((friend) => Number(friend?.id || 0) === id);
  };

  const hasOutgoingRequestFor = (targetUserId) => {
    const id = Number(targetUserId || 0);
    if (!id) return false;
    return (socialState.outgoingRequests || []).some((request) => Number(request?.receiver_id || request?.user?.id || 0) === id);
  };

  const hasIncomingRequestFrom = (targetUserId) => {
    const id = Number(targetUserId || 0);
    if (!id) return false;
    return (socialState.incomingRequests || []).some((request) => Number(request?.sender_id || request?.user?.id || 0) === id);
  };

  const buildMapFallbackCoords = (label) => {
    const text = String(label || '').trim().toLowerCase();
    let hashA = 0;
    let hashB = 0;
    for (let i = 0; i < text.length; i += 1) {
      hashA = ((hashA << 5) - hashA) + text.charCodeAt(i);
      hashA |= 0;
      hashB = ((hashB << 7) - hashB) + text.charCodeAt(i) * 3;
      hashB |= 0;
    }
    const lat = ((Math.abs(hashA) % 12000) / 100) - 60;
    const lng = ((Math.abs(hashB) % 34000) / 100) - 170;
    return { lat, lng };
  };

  const geocodeLocation = async (locationLabel) => {
    const key = String(locationLabel || '').trim().toLowerCase();
    if (!key) return null;
    if (geoCache.has(key)) return geoCache.get(key);

    let coords = null;
    try {
      const endpoint = `https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=${encodeURIComponent(key)}`;
      const response = await fetch(endpoint, {
        headers: {
          Accept: 'application/json'
        }
      });
      if (response.ok) {
        const rows = await response.json();
        if (Array.isArray(rows) && rows.length > 0) {
          const lat = Number(rows[0]?.lat);
          const lng = Number(rows[0]?.lon);
          if (Number.isFinite(lat) && Number.isFinite(lng)) {
            coords = { lat, lng };
          }
        }
      }
    } catch (_error) {
    }

    if (!coords) {
      coords = buildMapFallbackCoords(key);
    }

    geoCache.set(key, coords);
    return coords;
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

  const setThreadHeader = (title, subtitle, avatarSeed) => {
    if (messagesThreadTitle) messagesThreadTitle.textContent = title || 'Select a conversation';
    if (messagesThreadSubtitle) messagesThreadSubtitle.textContent = subtitle || 'Open Direct or Group chats from the left panel.';
    if (messagesThreadAvatar) {
      const seed = String(avatarSeed || '').trim();
      messagesThreadAvatar.textContent = seed ? seed.charAt(0).toUpperCase() : 'M';
    }
  };

  const renderMessagesEmpty = (text = 'Pick a conversation to start messaging.') => {
    if (!messagesThreadBody) return;
    messagesThreadBody.innerHTML = `<div class="messages-empty messages-empty-large">${escapeHtml(text)}</div>`;
  };

  const renderThreadMessages = (messages) => {
    if (!messagesThreadBody) return;
    if (!Array.isArray(messages) || !messages.length) {
      renderMessagesEmpty('No messages yet. Start the conversation.');
      return;
    }

    const currentUserId = Number(profileSocialBootstrap.currentUserId || 0);
    messagesThreadBody.innerHTML = messages.map((message) => {
      const sender = message?.sender || {};
      const senderName = getDisplayName(sender);
      const outgoing = Number(message?.sender_id || 0) === currentUserId;
      const messageType = String(message?.message_type || 'text').toLowerCase();
      const createdAt = getRelativeTime(message?.created_at || '');
      const body = String(message?.body || '').trim();
      const mediaUrl = resolvePublicMediaUrl(message?.media_url || '');

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
        `<article class="message-bubble ${outgoing ? 'is-outgoing' : 'is-incoming'} ${messageType === 'system' ? 'is-system' : ''}">`,
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

  const renderFriendRequests = () => {
    if (!friendRequestsList) return;

    const incoming = Array.isArray(socialState.incomingRequests) ? socialState.incomingRequests : [];
    const outgoing = Array.isArray(socialState.outgoingRequests) ? socialState.outgoingRequests : [];

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
        `<div class="friend-request-card is-outgoing" data-request-id="${Number(request?.id || 0)}">`,
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

    friendRequestsList.innerHTML = [
      incomingHtml,
      outgoingHtml ? '<div class="friend-request-subhead">Sent Requests</div>' : '',
      outgoingHtml,
    ].join('');
  };

  const renderThreadList = (targetElement, collection, type, searchQuery) => {
    if (!targetElement) return;
    const normalizedQuery = String(searchQuery || '').trim().toLowerCase();
    const items = Array.isArray(collection) ? collection : [];

    const filtered = items.filter((item) => {
      if (!normalizedQuery) return true;
      if (type === 'private') {
        const peer = item?.peer || {};
        const peerName = getDisplayName(peer).toLowerCase();
        const preview = getThreadMessagePreview(item).toLowerCase();
        return peerName.includes(normalizedQuery) || preview.includes(normalizedQuery);
      }
      const name = String(item?.name || '').toLowerCase();
      const preview = getThreadMessagePreview(item).toLowerCase();
      return name.includes(normalizedQuery) || preview.includes(normalizedQuery);
    });

    if (!filtered.length) {
      targetElement.innerHTML = `<div class="messages-empty">${normalizedQuery ? 'No matches found.' : 'No conversations yet.'}</div>`;
      return;
    }

    targetElement.innerHTML = filtered.map((thread) => {
      const id = Number(thread?.id || 0);
      const isActive = socialState.activeThreadType === type && Number(socialState.activeThreadId || 0) === id;
      const unread = Math.max(0, Number(thread?.unread_count || 0));
      const preview = getThreadMessagePreview(thread);
      const timeLabel = getRelativeTime(thread?.last_message_at || '');
      const title = type === 'private'
        ? getDisplayName(thread?.peer || {})
        : String(thread?.name || 'Unnamed group');
      const avatarText = type === 'private'
        ? getUserInitials(thread?.peer?.first_name, thread?.peer?.last_name)
        : String(title).charAt(0).toUpperCase();

      return [
        `<button type="button" class="messages-thread-item ${isActive ? 'is-active' : ''}" data-thread-type="${type}" data-thread-id="${id}">`,
        `<span class="messages-thread-avatar-mini">${escapeHtml(avatarText || 'C')}</span>`,
        '<span class="messages-thread-main">',
        `<strong>${escapeHtml(title)}</strong>`,
        `<small>${escapeHtml(preview)}</small>`,
        '</span>',
        '<span class="messages-thread-meta">',
        `<small>${escapeHtml(timeLabel)}</small>`,
        `${unread > 0 ? `<i>${unread}</i>` : ''}`,
        '</span>',
        '</button>'
      ].join('');
    }).join('');
  };

  const renderConversationPanels = () => {
    const query = String(messagesSearchInput?.value || '');
    renderThreadList(privateConversationsList, socialState.privateConversations, 'private', query);
    renderThreadList(groupConversationsList, socialState.groupChats, 'group', query);
    renderFriendRequests();
    updateMessagesNavCount();
  };

  const findThreadById = (threadType, threadId) => {
    const targetId = Number(threadId || 0);
    if (!targetId) return null;
    const source = threadType === 'group' ? socialState.groupChats : socialState.privateConversations;
    return (source || []).find((entry) => Number(entry?.id || 0) === targetId) || null;
  };

  const setActiveThread = (threadType, threadId) => {
    const thread = findThreadById(threadType, threadId);
    if (!thread) {
      socialState.activeThreadType = null;
      socialState.activeThreadId = 0;
      socialState.activeThreadLabel = '';
      socialState.activeThreadSubtitle = '';
      socialState.activeThreadAvatar = '';
      if (messagesComposer) messagesComposer.hidden = true;
      setThreadHeader('', '', '');
      renderMessagesEmpty();
      return;
    }

    socialState.activeThreadType = threadType;
    socialState.activeThreadId = Number(thread?.id || 0);

    if (threadType === 'private') {
      const peer = thread?.peer || {};
      socialState.activeThreadLabel = getDisplayName(peer);
      socialState.activeThreadSubtitle = String(peer?.role || 'member');
      socialState.activeThreadAvatar = getUserInitials(peer?.first_name, peer?.last_name);
    } else {
      socialState.activeThreadLabel = String(thread?.name || 'Group chat');
      socialState.activeThreadSubtitle = String(thread?.description || 'Group conversation');
      socialState.activeThreadAvatar = String(thread?.name || 'G').charAt(0).toUpperCase();
    }

    setThreadHeader(socialState.activeThreadLabel, socialState.activeThreadSubtitle, socialState.activeThreadAvatar);
    if (messagesComposer) messagesComposer.hidden = false;

    const source = threadType === 'group' ? socialState.groupChats : socialState.privateConversations;
    source.forEach((item) => {
      if (Number(item?.id || 0) === socialState.activeThreadId) {
        item.unread_count = 0;
      }
    });

    socialState.unreadTotal = [
      ...(socialState.privateConversations || []),
      ...(socialState.groupChats || [])
    ].reduce((sum, item) => sum + Math.max(0, Number(item?.unread_count || 0)), 0);

    renderConversationPanels();
  };

  const loadActiveThreadMessages = async () => {
    if (!socialState.activeThreadType || !socialState.activeThreadId) {
      renderMessagesEmpty();
      return;
    }

    try {
      const payload = await profileGet('profile_messages', {
        thread_type: socialState.activeThreadType,
        thread_id: socialState.activeThreadId,
      });
      renderThreadMessages(payload?.messages || []);
    } catch (error) {
      renderMessagesEmpty('Could not load messages for this thread.');
      showToast(error.message || 'Could not load messages.', 'error');
    }
  };

  const submitMessage = async ({ messageType = 'text', mediaUrl = '' } = {}) => {
    if (!socialState.activeThreadType || !socialState.activeThreadId) {
      showToast('Pick a conversation first.', 'warning');
      return;
    }

    const textBody = String(messageComposerInput?.value || '').trim();
    const safeMediaUrl = String(mediaUrl || '').trim();
    if (!textBody && !safeMediaUrl) {
      showToast('Write a message first.', 'warning');
      return;
    }

    if (sendMessageBtn) {
      sendMessageBtn.disabled = true;
    }

    try {
      const payload = await profilePost('profile_send_message', {
        thread_type: socialState.activeThreadType,
        thread_id: socialState.activeThreadId,
        message_type: messageType,
        body: textBody,
        media_url: safeMediaUrl,
      });

      if (messageComposerInput) {
        messageComposerInput.value = '';
      }

      const latestMessage = payload?.message || null;
      const currentThread = findThreadById(socialState.activeThreadType, socialState.activeThreadId);
      if (currentThread && latestMessage) {
        currentThread.last_message_body = String(latestMessage?.body || '');
        currentThread.last_message_type = String(latestMessage?.message_type || messageType);
        currentThread.last_message_at = String(latestMessage?.created_at || new Date().toISOString());
      }

      renderConversationPanels();
      await loadActiveThreadMessages();
    } catch (error) {
      showToast(error.message || 'Could not send message.', 'error');
    } finally {
      if (sendMessageBtn) {
        sendMessageBtn.disabled = false;
      }
    }
  };

  const syncProfileAvatarStoryState = () => {
    if (!profileAvatarFrame) return;
    const meId = Number(profileSocialBootstrap.currentUserId || 0);
    const hasActiveStory = (socialState.storiesActive || []).some(
      (story) => Number(story?.user_id || 0) === meId
    );
    profileAvatarFrame.classList.toggle('has-active-story', hasActiveStory);
  };

  const renderStories = () => {
    syncProfileAvatarStoryState();
    if (storiesActiveRail) {
      const activeStories = Array.isArray(socialState.storiesActive) ? socialState.storiesActive : [];
      if (!activeStories.length) {
        storiesActiveRail.innerHTML = '<div class="stories-empty">No active stories. Create your first story.</div>';
      } else {
        storiesActiveRail.innerHTML = activeStories.map((story) => {
          const user = story?.user || {};
          const userName = getDisplayName(user);
          const initials = getUserInitials(user?.first_name, user?.last_name);
          const isOwner = Number(story?.user_id || 0) === Number(profileSocialBootstrap.currentUserId || 0);
          const liveStream = getLiveStreamForUser(story?.user_id);
          const expiry = getStoryExpiryLabel(story?.expires_at || '');
          return [
            `<button type="button" class="story-chip ${isOwner ? 'is-owner' : ''} ${liveStream ? 'is-live' : ''}" data-story-id="${Number(story?.id || 0)}" data-story-mode="active">`,
            `<span class="story-chip-avatar">${escapeHtml(initials)}</span>`,
            '<span class="story-chip-content">',
            `<strong>${escapeHtml(userName)}</strong>`,
            `<small>${escapeHtml(expiry || 'Live')}</small>`,
            liveStream ? '<span class="story-chip-live">LIVE NOW</span>' : '',
            '</span>',
            '</button>'
          ].join('');
        }).join('');
      }
    }

    if (storiesArchiveList) {
      const archivedStories = Array.isArray(socialState.storiesArchive) ? socialState.storiesArchive : [];
      if (!archivedStories.length) {
        storiesArchiveList.innerHTML = '<div class="stories-empty">No archived stories yet.</div>';
      } else {
        storiesArchiveList.innerHTML = archivedStories.map((story) => {
          const label = String(story?.caption || '').trim() || `${String(story?.story_type || 'story')} story`;
          const dateText = getRelativeTime(story?.created_at || '');
          const views = Number(story?.views_count || 0);
          return [
            `<button type="button" class="story-archive-item" data-story-id="${Number(story?.id || 0)}" data-story-mode="archive">`,
            `<strong>${escapeHtml(label)}</strong>`,
            `<small>${escapeHtml(dateText)} · ${views} views</small>`,
            '</button>'
          ].join('');
        }).join('');
      }
    }
  };

  const storyLayerList = (rawValue) => {
    if (Array.isArray(rawValue)) return rawValue;
    if (typeof rawValue === 'string') {
      try {
        const parsed = JSON.parse(rawValue);
        return Array.isArray(parsed) ? parsed : [];
      } catch (_error) {
        return [];
      }
    }
    return [];
  };

  const storyCssValue = (value, fallback = '') => {
    const raw = String(value ?? '').trim();
    return raw || fallback;
  };

  const renderStoryOverlayLayer = (layer, defaultClassName) => {
    const type = String(layer?.type || defaultClassName || 'text').toLowerCase();
    const x = Number(layer?.x ?? layer?.left ?? 50);
    const y = Number(layer?.y ?? layer?.top ?? 50);
    const rotation = Number(layer?.rotation ?? layer?.rotate ?? 0);
    const scale = Number(layer?.scale ?? 1);
    const content = escapeHtml(String(layer?.content ?? layer?.text ?? layer?.emoji ?? layer?.value ?? '').trim());
    if (!content) return '';

    const styleParts = [
      `left:${Number.isFinite(x) ? x : 50}%`,
      `top:${Number.isFinite(y) ? y : 50}%`,
      `transform:translate(-50%,-50%) rotate(${Number.isFinite(rotation) ? rotation : 0}deg) scale(${Number.isFinite(scale) ? scale : 1})`,
      `color:${storyCssValue(layer?.color, '#ffffff')}`,
      `font-size:${storyCssValue(layer?.fontSize, type === 'emoji' ? '34px' : '26px')}`,
      `font-weight:${storyCssValue(layer?.fontWeight, '700')}`,
      `text-align:${storyCssValue(layer?.textAlign, 'center')}`,
      `text-shadow:${storyCssValue(layer?.textShadow, '0 8px 18px rgba(15,23,42,0.45)')}`,
      `max-width:${storyCssValue(layer?.maxWidth, '82%')}`,
    ];

    return `<div class="story-viewer-layer ${escapeHtml(defaultClassName)} story-viewer-layer-${escapeHtml(type)}" style="${styleParts.join(';')}">${content}</div>`;
  };

  const showStoryViewer = async (story, mode = 'active') => {
    if (!story) return;
    const storyType = String(story?.story_type || 'image').toLowerCase();
    const caption = String(story?.caption || '').trim();
    const mediaUrl = resolvePublicMediaUrl(story?.media_url || '');
    const storyId = Number(story?.id || 0);
    const isOwner = Number(story?.user_id || 0) === Number(profileSocialBootstrap.currentUserId || 0);
    const textLayers = storyLayerList(story?.text_layers);
    const stickerLayers = storyLayerList(story?.sticker_layers);
    const filterCss = String(story?.filter_css || '').trim();
    const gradientBg = String(story?.gradient_bg || '').trim() || 'linear-gradient(180deg, rgba(15,23,42,0.18), rgba(15,23,42,0.72))';
    const drawingData = resolvePublicMediaUrl(story?.drawing_data || '');
    const musicUrl = resolvePublicMediaUrl(story?.music_url || '');
    const musicTitle = String(story?.music_title || '').trim();
    const locationLabel = String(story?.location_label || '').trim();
    const durationLabel = Math.max(1, Number(story?.duration || 5));
    const overlayLayers = [
      ...textLayers.map((layer) => renderStoryOverlayLayer(layer, 'story-viewer-text-layer')),
      ...stickerLayers.map((layer) => renderStoryOverlayLayer(layer, 'story-viewer-sticker-layer')),
    ].join('');

    let mediaHtml = '';
    if (storyType === 'image' && mediaUrl) {
      mediaHtml = `<img src="${escapeHtml(mediaUrl)}" alt="Story media" class="story-viewer-media" style="${filterCss ? `filter:${escapeHtml(filterCss)};` : ''}">`;
    } else if (storyType === 'video' && mediaUrl) {
      mediaHtml = `<video controls autoplay loop playsinline class="story-viewer-media" style="${filterCss ? `filter:${escapeHtml(filterCss)};` : ''}"><source src="${escapeHtml(mediaUrl)}"></video>`;
    } else {
      mediaHtml = `<div class="story-viewer-text-only">${escapeHtml(caption || 'Story')}</div>`;
    }

    const bodyHtml = [
      '<div class="story-viewer-shell" style="position:relative;width:min(92vw,380px);aspect-ratio:9/16;margin:0 auto;border-radius:24px;overflow:hidden;background:#0f172a;box-shadow:0 28px 70px rgba(15,23,42,0.35);">',
      `<div class="story-viewer-gradient" style="position:absolute;inset:0;background:${escapeHtml(gradientBg)};"></div>`,
      mediaHtml,
      drawingData ? `<img src="${escapeHtml(drawingData)}" alt="" aria-hidden="true" class="story-viewer-drawing" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;pointer-events:none;">` : '',
      `<div class="story-viewer-overlay" style="position:absolute;inset:0;pointer-events:none;">${overlayLayers}</div>`,
      '<div class="story-viewer-topbar" style="position:absolute;left:0;right:0;top:0;display:flex;justify-content:space-between;gap:12px;padding:16px 16px 0;color:#fff;z-index:2;">',
      `<span style="font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;background:rgba(15,23,42,0.42);padding:7px 10px;border-radius:999px;backdrop-filter:blur(14px);">${escapeHtml(locationLabel || `${durationLabel}s story`)}</span>`,
      musicTitle ? `<span style="font-size:12px;font-weight:600;background:rgba(15,23,42,0.42);padding:7px 10px;border-radius:999px;backdrop-filter:blur(14px);">${escapeHtml(musicTitle)}</span>` : '',
      '</div>',
      caption ? `<div class="story-viewer-caption" style="position:absolute;left:16px;right:16px;bottom:18px;z-index:2;color:#fff;background:rgba(15,23,42,0.38);border:1px solid rgba(255,255,255,0.12);backdrop-filter:blur(14px);padding:12px 14px;border-radius:18px;font-size:13px;line-height:1.55;">${escapeHtml(caption)}</div>` : '',
      musicUrl ? `<audio src="${escapeHtml(musicUrl)}" controls autoplay loop style="position:absolute;left:14px;right:14px;bottom:${caption ? '82px' : '16px'};z-index:2;opacity:.92;"></audio>` : '',
      '</div>',
    ].join('');

    let deleted = false;

    if (window.Swal) {
      const modalResult = await window.Swal.fire({
        title: 'Story Viewer',
        html: bodyHtml,
        width: 520,
        showConfirmButton: true,
        confirmButtonText: 'Close',
        showDenyButton: isOwner,
        denyButtonText: 'Delete story',
        showCloseButton: true,
        background: 'linear-gradient(180deg, rgba(248,250,252,0.96), rgba(241,245,249,0.98))',
        color: '#0f172a',
        customClass: {
          popup: 'story-viewer-popup'
        }
      });

      if (modalResult?.isDenied && isOwner && storyId > 0) {
        try {
          await profilePost('profile_delete_story', { story_id: storyId });
          deleted = true;
          showToast('Story deleted.', 'success');
        } catch (error) {
          showToast(error.message || 'Could not delete story.', 'error');
        }
      }
    } else {
      window.alert(caption || 'Story opened.');
      if (isOwner && storyId > 0 && window.confirm('Delete this story?')) {
        try {
          await profilePost('profile_delete_story', { story_id: storyId });
          deleted = true;
          showToast('Story deleted.', 'success');
        } catch (error) {
          showToast(error.message || 'Could not delete story.', 'error');
        }
      }
    }

    if (deleted) {
      await ensureSocialDataLoaded(true);
      return;
    }

    if (mode === 'active' && !isOwner && storyId > 0) {
      try {
        await profilePost('profile_story_view', { story_id: storyId });
      } catch (_error) {
      }
    }
  };

  const openStoryForMapUser = async (targetUserId) => {
    const userId = Number(targetUserId || 0);
    if (!userId) return;

    let story = (socialState.storiesActive || []).find((entry) => Number(entry?.user_id || 0) === userId) || null;

    if (!story) {
      try {
        const payload = await profileGet('profile_get_stories', { user_id: userId });
        const stories = Array.isArray(payload?.stories) ? payload.stories : [];
        story = stories[0] || null;
      } catch (error) {
        showToast(error.message || 'Could not load story.', 'error');
        return;
      }
    }

    if (!story) {
      showToast('No active story available for this user.', 'warning');
      return;
    }

    await showStoryViewer(story, 'active');
    await ensureSocialDataLoaded(true);
  };

  const renderSnapSocialMap = async () => {
    if (!snapSocialMapEl || !window.L) return;

    if (!socialState.mapInstance) {
      socialState.mapInstance = window.L.map(snapSocialMapEl, {
        zoomControl: true,
        minZoom: 2,
        maxZoom: 15,
      }).setView([20, 0], 2);

      window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(socialState.mapInstance);

      setTimeout(() => socialState.mapInstance?.invalidateSize(), 180);
      window.addEventListener('resize', () => {
        if (socialState.mapInstance) {
          socialState.mapInstance.invalidateSize();
        }
      });
    }

    socialState.mapMarkers.forEach((marker) => marker.remove());
    socialState.mapMarkers = [];

    const users = (socialState.mapUsers || []).filter((user) => {
      const lat = Number(user?.latitude);
      const lng = Number(user?.longitude);
      return Number.isFinite(lat) && Number.isFinite(lng);
    });

    if (!users.length) {
      socialState.mapInstance.setView([20, 0], 2);
      return;
    }

    const mapped = users.map((user) => ({
      user,
      coords: {
        lat: Number(user.latitude),
        lng: Number(user.longitude),
      },
    }));

    const bounds = [];
    mapped.forEach(({ user, coords }) => {
      if (!coords || !Number.isFinite(coords.lat) || !Number.isFinite(coords.lng)) return;

      const displayName = getDisplayName(user);
      const initials = getUserInitials(user?.first_name, user?.last_name);
      const avatarUrl = resolveAvatarUrl(user?.avatar_url, initials);
      const linkedAccounts = Array.isArray(user?.linked_accounts) ? user.linked_accounts : [];
      const isSelf = Number(user?.id || 0) === Number(profileSocialBootstrap.currentUserId || 0);
      const hasStory = Boolean(user?.has_story);
      const liveStream = getLiveStreamForUser(user?.id);
      const canConnect = Number(user?.id || 0) > 0
        && !isSelf
        && !isFriendWithUser(user?.id)
        && !hasOutgoingRequestFor(user?.id)
        && !hasIncomingRequestFrom(user?.id);

      const markerHtml = [
        `<div class="snap-map-marker ${isSelf ? 'is-self' : ''} ${hasStory ? 'has-story' : ''} ${liveStream ? 'has-live' : ''}">`,
        `<img src="${escapeHtml(avatarUrl)}" alt="${escapeHtml(displayName)}">`,
        '</div>'
      ].join('');

      const icon = window.L.divIcon({
        className: 'snap-map-marker-wrap',
        html: markerHtml,
        iconSize: [44, 44],
        iconAnchor: [22, 40],
        popupAnchor: [0, -34],
      });

      const linkedHtml = linkedAccounts.length
        ? linkedAccounts.slice(0, 4).map((account) => {
          const label = String(account?.account_label || '').trim() || normalizeLinkedAccountLabel(account?.platform || '');
          const url = String(account?.profile_url || '').trim();
          const isVerified = Number(account?.verified || 0) === 1;
          if (!url) return '';
          return `<a class="${isVerified ? 'is-verified' : 'is-unverified'}" href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(label)}${isVerified ? ' ✓' : ''}</a>`;
        }).join('')
        : '<span class="empty">No linked accounts</span>';

      const popupHtml = [
        '<div class="snap-map-popup">',
        '<div class="snap-map-popup-head">',
        `<img src="${escapeHtml(avatarUrl)}" alt="${escapeHtml(displayName)}" class="${hasStory ? 'has-story-ring' : ''}">`,
        '<div>',
        `<strong>${escapeHtml(displayName)}</strong>`,
        `<small>${escapeHtml(String(user?.role || 'member'))}${liveStream ? ' · LIVE' : ''}</small>`,
        '</div>',
        '</div>',
        '<div class="snap-map-popup-meta">',
        `<span>Location: ${escapeHtml(String(user?.exact_location || user?.country || 'Unknown'))}</span>`,
        `<span>Phone: ${escapeHtml(String(user?.phone || 'Not shared'))}</span>`,
        `<span>XP: ${escapeHtml(String(user?.xp ?? 0))}</span>`,
        '</div>',
        `<p class="snap-map-popup-bio">${escapeHtml(String(user?.bio || '').trim() || 'No bio available.')}</p>`,
        `<div class="snap-map-popup-links">${linkedHtml}</div>`,
        hasStory ? '<button type="button" class="snap-map-story-btn" data-user-id="' + Number(user?.id || 0) + '">View Story</button>' : '',
        liveStream ? `<button type="button" class="snap-map-live-btn" data-stream-id="${Number(liveStream?.id || 0)}">Join Live</button>` : '',
        canConnect
          ? `<button type="button" class="snap-map-connect-btn" data-target-user-id="${Number(user?.id || 0)}">Send Friend Request</button>`
          : '<span class="snap-map-connected-badge">Connected or pending</span>',
        '</div>'
      ].join('');

      const marker = window.L.marker([coords.lat, coords.lng], { icon }).addTo(socialState.mapInstance);
      marker.bindPopup(popupHtml, { maxWidth: 320 });
      socialState.mapMarkers.push(marker);
      bounds.push([coords.lat, coords.lng]);
    });

    if (bounds.length) {
      socialState.mapInstance.fitBounds(bounds, { padding: [38, 38], maxZoom: 6 });
    }
  };

  const ensureSocialDataLoaded = async (forceRefresh = false) => {
    if (socialState.loading) return;
    if (socialState.loaded && !forceRefresh) {
      renderConversationPanels();
      renderStories();
      renderLinkedAccountsPreview();
      hydrateLinkedAccountsForm();
      await renderSnapSocialMap();
      return;
    }

    socialState.loading = true;
    try {
      const data = await profileGet('profile_social_data');
      socialState.loaded = true;
      socialState.linkedAccounts = Array.isArray(data?.linked_accounts) ? data.linked_accounts : [];
      socialState.friends = Array.isArray(data?.friends) ? data.friends : [];
      socialState.incomingRequests = Array.isArray(data?.incoming_requests) ? data.incoming_requests : [];
      socialState.outgoingRequests = Array.isArray(data?.outgoing_requests) ? data.outgoing_requests : [];
      socialState.privateConversations = Array.isArray(data?.private_conversations) ? data.private_conversations : [];
      socialState.groupChats = Array.isArray(data?.group_chats) ? data.group_chats : [];
      socialState.storiesActive = Array.isArray(data?.stories?.active) ? data.stories.active : [];
      socialState.storiesArchive = Array.isArray(data?.stories?.archive) ? data.stories.archive : [];
      socialState.liveStreams = Array.isArray(data?.live_streams) ? data.live_streams : [];
      socialState.mapUsers = Array.isArray(data?.map_users) ? data.map_users : [];
      socialState.unreadTotal = Math.max(0, Number(data?.unread_total || 0));

      if (socialState.activeThreadType && socialState.activeThreadId) {
        const stillExists = findThreadById(socialState.activeThreadType, socialState.activeThreadId);
        if (!stillExists) {
          socialState.activeThreadType = null;
          socialState.activeThreadId = 0;
          if (messagesComposer) messagesComposer.hidden = true;
        }
      }

      renderConversationPanels();
      renderStories();
      renderLinkedAccountsPreview();
      hydrateLinkedAccountsForm();
      await renderSnapSocialMap();

      if (socialState.activeThreadType && socialState.activeThreadId) {
        setActiveThread(socialState.activeThreadType, socialState.activeThreadId);
        await loadActiveThreadMessages();
      }
    } catch (error) {
      showToast(error.message || 'Could not load social data.', 'error');
    } finally {
      socialState.loading = false;
      if (typeof lucide !== 'undefined') lucide.createIcons();
    }
  };

  const openMessagesPage = (params = {}) => {
    const query = new URLSearchParams();
    Object.entries(params || {}).forEach(([key, value]) => {
      if (value === null || value === undefined || value === '') return;
      query.set(String(key), String(value));
    });
    const suffix = query.toString();
    window.location.href = suffix ? `messages.php?${suffix}` : 'messages.php';
  };

  if (openMessagesPanelBtn) {
    openMessagesPanelBtn.addEventListener('click', () => {
      openMessagesPage();
    });
  }

  if (openMessagesFromStoriesBtn) {
    openMessagesFromStoriesBtn.addEventListener('click', () => {
      openMessagesPage();
    });
  }

  if (openLiveFromStoriesBtn) {
    openLiveFromStoriesBtn.addEventListener('click', () => {
      window.location.href = 'live.php?mode=broadcast';
    });
  }

  if (messagesSearchInput) {
    messagesSearchInput.addEventListener('input', () => renderConversationPanels());
  }

  const openThreadFromButton = async (buttonEl) => {
    if (!buttonEl) return;
    const threadType = String(buttonEl.dataset.threadType || 'private');
    const threadId = Number(buttonEl.dataset.threadId || 0);
    if (!threadId) return;
    setActiveThread(threadType, threadId);
    await loadActiveThreadMessages();
  };

  [privateConversationsList, groupConversationsList].forEach((listEl) => {
    if (!listEl) return;
    listEl.addEventListener('click', (event) => {
      const button = event.target.closest('.messages-thread-item');
      if (!button) return;
      openThreadFromButton(button);
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

  document.addEventListener('click', async (event) => {
    const storyBtn = event.target.closest('.snap-map-story-btn');
    if (storyBtn) {
      const targetUserId = Number(storyBtn.dataset.userId || 0);
      if (!targetUserId) return;
      storyBtn.setAttribute('disabled', 'disabled');
      try {
        await openStoryForMapUser(targetUserId);
      } finally {
        storyBtn.removeAttribute('disabled');
      }
      return;
    }

    const liveBtn = event.target.closest('.snap-map-live-btn');
    if (liveBtn) {
      const streamId = Number(liveBtn.dataset.streamId || 0);
      if (!streamId) return;
      liveBtn.setAttribute('disabled', 'disabled');
      window.location.href = `live.php?stream=${streamId}`;
      return;
    }

    const connectBtn = event.target.closest('.snap-map-connect-btn');
    if (!connectBtn) return;
    const targetUserId = Number(connectBtn.dataset.targetUserId || 0);
    if (!targetUserId) return;

    connectBtn.setAttribute('disabled', 'disabled');
    try {
      await profilePost('profile_friend_request', {
        mode: 'send',
        target_user_id: targetUserId,
        request_message: 'Let\'s connect on Diversity.is.',
      });
      showToast('Friend request sent.', 'success');
      await ensureSocialDataLoaded(true);
    } catch (error) {
      showToast(error.message || 'Could not send request.', 'error');
    } finally {
      connectBtn.removeAttribute('disabled');
    }
  });

  if (messagesRefreshBtn) {
    messagesRefreshBtn.addEventListener('click', async () => {
      await ensureSocialDataLoaded(true);
      if (socialState.activeThreadType && socialState.activeThreadId) {
        await loadActiveThreadMessages();
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

  if (openGroupComposerBtn) {
    openGroupComposerBtn.addEventListener('click', async () => {
      const availableFriends = Array.isArray(socialState.friends) ? socialState.friends : [];
      if (!availableFriends.length) {
        showToast('Add friends first before creating a group chat.', 'warning');
        return;
      }

      let groupName = '';
      let groupDescription = '';
      let selectedMembers = [];

      if (window.Swal) {
        const friendsHtml = availableFriends.slice(0, 20).map((friend) => {
          const id = Number(friend?.id || 0);
          const label = escapeHtml(getDisplayName(friend));
          return `<label style="display:flex;align-items:center;gap:8px;font-size:13px;"><input type="checkbox" value="${id}" class="group-member-box"> <span>${label}</span></label>`;
        }).join('');

        const result = await window.Swal.fire({
          title: 'Create Group Chat',
          html: [
            '<input id="groupChatNameField" class="swal2-input" placeholder="Group name" maxlength="120">',
            '<textarea id="groupChatDescriptionField" class="swal2-textarea" placeholder="Short description (optional)" maxlength="255"></textarea>',
            `<div style="text-align:left;max-height:180px;overflow:auto;padding:6px 10px;border:1px solid #e2e8f0;border-radius:10px;">${friendsHtml}</div>`
          ].join(''),
          showCancelButton: true,
          confirmButtonText: 'Create',
          preConfirm: () => {
            const popup = window.Swal.getPopup();
            const name = String(popup?.querySelector('#groupChatNameField')?.value || '').trim();
            const description = String(popup?.querySelector('#groupChatDescriptionField')?.value || '').trim();
            const members = Array.from(popup?.querySelectorAll('.group-member-box:checked') || []).map((node) => Number(node.value || 0)).filter((id) => id > 0);
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
          setActiveThread('group', groupId);
          await loadActiveThreadMessages();
        }
      } catch (error) {
        showToast(error.message || 'Could not create group chat.', 'error');
      }
    });
  }

  const openStoryComposer = () => {
    if (isBlockedAccount()) {
      showBlockedActionToast('Account blocked. Story publishing is disabled until admin approval.');
      return;
    }
    if (!storyComposer) return;
    storyComposer.hidden = false;
    // start camera preview for story composer
    startStoryCamera().catch(() => {});
    updateBodyScrollState();
    if (storyCaptionInput) {
      setTimeout(() => storyCaptionInput.focus(), 80);
    }
  };

  const closeStoryComposer = () => {
    if (!storyComposer) return;
    storyComposer.hidden = true;
    // stop media streams
    stopStoryCamera();
    if (storyCaptureCanvas) {
      storyCaptureCanvas.hidden = true;
    }
    updateBodyScrollState();
  };

  // Story camera helpers
  const startStoryCamera = async () => {
    if (!storyCameraPreview) return;
    try {
      stopStoryCamera();
      const constraints = { video: { facingMode: storyFacingMode }, audio: false };
      const stream = await navigator.mediaDevices.getUserMedia(constraints);
      storyCameraStream = stream;
      try { storyCameraPreview.srcObject = stream; } catch (e) { storyCameraPreview.src = URL.createObjectURL(stream); }
      storyCameraPreview.hidden = false;
      if (storyCaptureCanvas) storyCaptureCanvas.hidden = true;
      await storyCameraPreview.play?.();
    } catch (error) {
      console.warn('Could not start story camera', error);
    }
  };

  const stopStoryCamera = () => {
    if (storyCameraStream) {
      storyCameraStream.getTracks().forEach((t) => { try { t.stop(); } catch (e) {} });
      storyCameraStream = null;
    }
    if (storyCameraPreview) {
      try { storyCameraPreview.pause(); } catch (e) {}
      try { storyCameraPreview.srcObject = null; } catch (e) { storyCameraPreview.src = ''; }
    }
  };

  const captureStoryImage = () => {
    if (!storyCameraPreview || !storyCaptureCanvas) return null;
    const video = storyCameraPreview;
    const canvas = storyCaptureCanvas;
    canvas.width = video.videoWidth || 1080;
    canvas.height = video.videoHeight || 1920;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
    canvas.hidden = false;
    video.hidden = true;
    if (storyMediaUrlInput) storyMediaUrlInput.value = dataUrl;
    if (storyTypeInput) storyTypeInput.value = 'image';
    return dataUrl;
  };

  if (storyCaptureBtn) {
    storyCaptureBtn.addEventListener('click', () => {
      captureStoryImage();
    });
  }

  if (storyImportInput) {
    storyImportInput.addEventListener('change', (event) => {
      const file = (event.target.files || [])[0];
      if (!file) return;
      const isImage = file.type.startsWith('image/');
      const reader = new FileReader();
      reader.onload = (ev) => {
        const data = String(ev.target.result || '');
        if (isImage) {
          // draw to canvas for preview
          if (storyCaptureCanvas) {
            const img = new Image();
            img.onload = () => {
              const canvas = storyCaptureCanvas;
              canvas.width = img.width;
              canvas.height = img.height;
              const ctx = canvas.getContext('2d');
              ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
              canvas.hidden = false;
              if (storyCameraPreview) storyCameraPreview.hidden = true;
            };
            img.src = data;
          }
          if (storyMediaUrlInput) storyMediaUrlInput.value = data;
          if (storyTypeInput) storyTypeInput.value = 'image';
        } else {
          // video import: use blob URL preview
          const blobUrl = URL.createObjectURL(file);
          if (storyCameraPreview) {
            try { stopStoryCamera(); } catch (e) {}
            storyCameraPreview.hidden = false;
            storyCameraPreview.srcObject = null;
            storyCameraPreview.src = blobUrl;
            storyCameraPreview.play?.();
          }
          if (storyMediaUrlInput) storyMediaUrlInput.value = blobUrl;
          if (storyTypeInput) storyTypeInput.value = 'video';
        }
      };
      reader.readAsDataURL(file);
    });
  }

  if (storyFlipCameraBtn) {
    storyFlipCameraBtn.addEventListener('click', async () => {
      storyFacingMode = storyFacingMode === 'environment' ? 'user' : 'environment';
      await startStoryCamera();
    });
  }

  if (messagesFab) {
    messagesFab.addEventListener('click', () => {
      const target = 'messages.php';
      messagesFab.classList.add('animate-click');
      setTimeout(() => messagesFab.classList.remove('animate-click'), 450);
      setTimeout(() => { window.location.href = target; }, 120);
    });
  }

  // Story toolbar buttons (text/draw/stickers/music) - lightweight handlers
  document.addEventListener('click', (event) => {
    const btn = event.target.closest && event.target.closest('.story-tool-btn');
    if (!btn || !storyComposer || storyComposer.hidden) return;
    const tool = String(btn.dataset.tool || '').toLowerCase();
    if (tool === 'text') {
      if (storyCaptionInput) {
        storyCaptionInput.focus();
        showToast('Text mode: type your caption or add text layers (quick).', 'success');
      }
      return;
    }
    if (tool === 'draw') {
      showToast('Draw tool coming soon — basic sketch support will be added.', 'warning');
      return;
    }
    if (tool === 'stickers') {
      showToast('Stickers coming soon — open sticker tray.', 'warning');
      return;
    }
    if (tool === 'music') {
      showToast('Music overlay coming soon — choose a track.', 'warning');
      return;
    }
  });

  if (openStoryComposerBtn && storyComposer) {
    openStoryComposerBtn.addEventListener('click', openStoryComposer);
  }

  if (cancelStoryComposerBtn && storyComposer) {
    cancelStoryComposerBtn.addEventListener('click', closeStoryComposer);
  }

  if (cancelStoryComposerBtnSecondary && storyComposer) {
    cancelStoryComposerBtnSecondary.addEventListener('click', closeStoryComposer);
  }

  if (storyComposer) {
    storyComposer.addEventListener('click', (event) => {
      if (event.target === storyComposer) {
        closeStoryComposer();
      }
    });
  }

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && storyComposer && !storyComposer.hidden) {
      closeStoryComposer();
    }
  });

  if (toggleStoriesArchiveBtn && storiesArchiveList) {
    toggleStoriesArchiveBtn.addEventListener('click', () => {
      const hidden = Boolean(storiesArchiveList.hidden);
      storiesArchiveList.hidden = !hidden;
      toggleStoriesArchiveBtn.textContent = hidden ? 'Hide' : 'Show';
    });
  }

  if (publishStoryBtn) {
    publishStoryBtn.addEventListener('click', async () => {
      const storyType = String(storyTypeInput?.value || 'image').toLowerCase();
      const mediaUrl = String(storyMediaUrlInput?.value || '').trim();
      const caption = String(storyCaptionInput?.value || '').trim();

      if ((storyType === 'image' || storyType === 'video') && !mediaUrl) {
        showToast('Media URL is required for image/video stories.', 'warning');
        return;
      }

      try {
        await profilePost('profile_create_story', {
          story_type: storyType,
          media_url: mediaUrl,
          caption,
        });
        if (storyCaptionInput) storyCaptionInput.value = '';
        if (storyMediaUrlInput) storyMediaUrlInput.value = '';
        closeStoryComposer();
        showToast('Story published.', 'success');
        await ensureSocialDataLoaded(true);
      } catch (error) {
        showToast(error.message || 'Could not publish story.', 'error');
      }
    });
  }

  const handleStoryClick = async (event) => {
    const storyButton = event.target.closest('[data-story-id]');
    if (!storyButton) return;
    const storyId = Number(storyButton.dataset.storyId || 0);
    const mode = String(storyButton.dataset.storyMode || 'active');
    if (!storyId) return;

    const source = mode === 'archive' ? socialState.storiesArchive : socialState.storiesActive;
    const story = (source || []).find((entry) => Number(entry?.id || 0) === storyId);
    if (!story) return;

    await showStoryViewer(story, mode);
    if (mode === 'active') {
      await ensureSocialDataLoaded(true);
    }
  };

  if (storiesActiveRail) {
    storiesActiveRail.addEventListener('click', handleStoryClick);
  }

  if (storiesArchiveList) {
    storiesArchiveList.addEventListener('click', handleStoryClick);
  }

  if (openLinkedAccountsEditorBtn) {
    openLinkedAccountsEditorBtn.addEventListener('click', openLinkedAccountsModal);
  }

  if (quickLinkAccountBtn) {
    quickLinkAccountBtn.addEventListener('click', openLinkedAccountsModal);
  }

  if (closeLinkedAccountsModalBtn) {
    closeLinkedAccountsModalBtn.addEventListener('click', closeLinkedAccountsModal);
  }

  if (cancelLinkedAccountsBtn) {
    cancelLinkedAccountsBtn.addEventListener('click', closeLinkedAccountsModal);
  }

  if (linkedAccountsModal) {
    linkedAccountsModal.addEventListener('click', (event) => {
      if (event.target === linkedAccountsModal) {
        closeLinkedAccountsModal();
      }
    });
  }

  linkedAccountUrlInputs.forEach((input) => {
    input.addEventListener('input', () => {
      validateLinkedAccountInput(input, { markTouched: false });
    });
    input.addEventListener('blur', () => {
      validateLinkedAccountInput(input, { markTouched: true });
    });
  });

  if (saveLinkedAccountsBtn) {
    saveLinkedAccountsBtn.addEventListener('click', async () => {
      const { accounts, hasErrors } = collectLinkedAccountsPayload();
      if (hasErrors) {
        showToast('Fix invalid linked account URLs before saving.', 'warning');
        return;
      }

      saveLinkedAccountsBtn.disabled = true;
      try {
        const response = await profilePost('profile_save_linked_accounts', { accounts });
        socialState.linkedAccounts = Array.isArray(response?.linked_accounts)
          ? response.linked_accounts
          : accounts.slice();

        const verifiedCount = Math.max(0, Number(response?.verified_count || 0));
        closeLinkedAccountsModal();
        showToast(`Linked accounts updated. ${verifiedCount} verified.`, 'success');
        await ensureSocialDataLoaded(true);
      } catch (error) {
        const invalid = Array.isArray(error?.payload?.invalid_accounts) ? error.payload.invalid_accounts : [];
        if (invalid.length) {
          invalid.forEach((item) => {
            const platform = String(item?.platform || '').toLowerCase();
            const targetInput = linkedAccountUrlInputs.find((input) => String(input.dataset.platform || '').toLowerCase() === platform);
            if (targetInput) {
              setLinkedAccountInputState(targetInput, 'invalid', String(item?.message || 'Invalid URL.'));
            }
          });
        }
        showToast(error.message || 'Could not save linked accounts.', 'error');
      } finally {
        saveLinkedAccountsBtn.disabled = false;
      }
    });
  }

  if (exportProfileBtn) {
    exportProfileBtn.addEventListener('click', async () => {
      let summaryPayload = null;
      const palette = buildExportPalette();
      try {
        exportProfileBtn.disabled = true;
        summaryPayload = await loadProfileExportSummary();
        let selectedTheme = 'modern';
        if (window.Swal) {
          const result = await window.Swal.fire({
            title: 'Export profile data',
            html: buildExportModalHtml(summaryPayload, palette),
            showCancelButton: true,
            confirmButtonText: 'Download PDF',
            cancelButtonText: 'Cancel',
            width: 760,
            background: palette.surface,
            color: palette.text,
            preConfirm: () => {
              const popup = window.Swal.getPopup();
              return String(popup?.querySelector('input[name="profileExportTheme"]:checked')?.value || 'modern');
            }
          });
          if (!result.isConfirmed) return;
          selectedTheme = String(result.value || 'modern');
        }

        await exportProfilePdf(selectedTheme, summaryPayload);
        showToast('Profile PDF exported successfully.', 'success');
      } catch (error) {
        showToast(error.message || 'Could not export profile PDF.', 'error');
      } finally {
        exportProfileBtn.disabled = false;
      }
    });
  }

  ensureSocialDataLoaded(false);

  const aiBar = document.getElementById('aiBarFill');
  if (aiBar) {
    setTimeout(() => {
      aiBar.style.width = '92%';
    }, 300);
  }

  const repSparkFill = document.getElementById('repSparkFill');
  if (repSparkFill) {
    setTimeout(() => {
      repSparkFill.style.width = '76%';
    }, 300);
  }

  const firstName = String(document.getElementById('formFirstName')?.value || 'U');
  const lastName = String(document.getElementById('formLastName')?.value || 'S');
  updateAvatarPreview(String(avatarUrlInput?.value || '').trim(), firstName, lastName);
  if (String(document.body?.dataset.accountBlocked || '0') === '1') {
    applyBlockedMode('Account blocked — your account is temporarily disabled while waiting for admin approval.');
  }
  bindSecurityFieldState();
  bindSecurityStrengthBar();
  if (typeof lucide !== 'undefined') lucide.createIcons();
});

