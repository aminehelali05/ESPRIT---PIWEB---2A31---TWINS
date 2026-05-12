document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('editModal');
  const userForm = document.getElementById('userForm');
  const editToggleBtn = document.getElementById('editToggleBtn');
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
  const latInput = document.getElementById('formLatitude');
  const lngInput = document.getElementById('formLongitude');
  const mapAddressInput = document.getElementById('formMapAddress');
  const profileAvatar = document.getElementById('profileAvatar');
  const profileLocationText = document.getElementById('profileLocationText');
  const aboutBioText = document.getElementById('aboutBioText');
  const changePasswordForm = document.getElementById('changePasswordForm');
  const requestDeleteAccountBtn = document.getElementById('requestDeleteAccountBtn');
  const profilePitchVideo = document.getElementById('profilePitchVideo');
  const videoOverlayPlay = document.getElementById('videoOverlayPlay');
  const pipVideoBtn = document.getElementById('pipVideoBtn');
  const secStrengthFill = document.getElementById('secStrengthFill');

  let avatarCameraStream = null;
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
      target.closest('.profile-toast-stack')
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
    if (countryInput) {
      countryInput.value = String(picked.country || '').trim();
      countryInput.dispatchEvent(new Event('input', { bubbles: true }));
      countryInput.dispatchEvent(new Event('change', { bubbles: true }));
    }
    if (latInput) latInput.value = String(picked.lat ?? '');
    if (lngInput) lngInput.value = String(picked.lng ?? '');
    if (mapAddressInput) mapAddressInput.value = String(picked.display || picked.country || '').trim();
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
    document.body.style.overflow = 'hidden';

    if (!globeInitialized) {
      initGlobeMap();
      globeInitialized = true;
    }
    if (window.GlobeExplorer && typeof window.GlobeExplorer.invalidateSize === 'function') {
      setTimeout(() => window.GlobeExplorer.invalidateSize(), 180);
    }

    const country = String(countryInput?.value || '').trim();
    if (country) {
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
    document.body.style.overflow = '';
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
        country: String(document.getElementById('formCountry')?.value || '').trim() || 'Unknown',
        bio: String(document.getElementById('formBio')?.value || '').trim(),
        avatar_url: String(document.getElementById('formAvatarUrl')?.value || '').trim()
      };

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

        const fullName = `${payload.first_name} ${payload.last_name}`.trim();
        const profileName = document.querySelector('.profile-name');
        if (profileName) profileName.textContent = fullName;
        const navName = document.querySelector('.nav-dropdown-header strong');
        if (navName) navName.textContent = fullName;

        const titleTag = document.getElementById('profileTitleTag');
        if (titleTag) {
          const roleLabel = payload.role === 'freelancer' ? 'Freelancer' : 'Client';
          titleTag.innerHTML = `<i data-lucide="code-2" class="w-3 h-3"></i> ${roleLabel}`;
        }

        if (profileLocationText) {
          profileLocationText.innerHTML = `<i data-lucide="map-pin" class="w-3.5 h-3.5"></i> ${payload.country || 'Unknown'}`;
        }
        if (aboutBioText) {
          if (payload.bio) {
            aboutBioText.textContent = payload.bio;
          } else {
            aboutBioText.innerHTML = '<strong>Driven member of Diversity.is</strong> focused on building inclusive digital products, shipping reliable work, and collaborating with teams across design, engineering, and strategy.';
          }
        }
        if (profileAvatar && payload.avatar_url) {
          profileAvatar.src = payload.avatar_url;
        }

        initialSnapshot = readSnapshot();
        if (typeof lucide !== 'undefined') lucide.createIcons();
        showToast('Profile updated successfully.', 'success');
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
            <div style="text-align:left;">
              <div style="display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:18px;border:1px solid rgba(239,68,68,0.16);background:linear-gradient(180deg,rgba(254,242,242,0.98),rgba(255,255,255,0.98));margin-bottom:14px;">
                <div style="width:42px;height:42px;border-radius:14px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#fecaca,#f87171);color:#7f1d1d;flex:0 0 auto;">
                  <i data-lucide="shield-alert" style="width:18px;height:18px;"></i>
                </div>
                <div>
                  <div style="font-size:13px;font-weight:700;color:#7f1d1d;margin-bottom:2px;">Serious account action</div>
                  <div style="font-size:12px;color:#7f1d1d;line-height:1.5;">The request is reviewed by an admin before anything is deleted.</div>
                </div>
              </div>
              <p style="margin:0 0 12px;color:#475569;font-size:13px;line-height:1.65;">
                Your account will be blocked while the request is pending. You can still log in to cancel it before approval.
              </p>
              <label for="deleteReasonField" style="display:block;margin-bottom:8px;font-size:12px;font-weight:700;color:#0f172a;">Reason <span style="font-weight:500;color:#94a3b8;">(optional, but recommended)</span></label>
              <textarea id="deleteReasonField" rows="4" placeholder="Tell us why you are requesting deletion..." style="width:100%;resize:none;border-radius:16px;border:1.5px solid rgba(226,232,240,0.98);background:#f8fafc;padding:12px 14px;font-family:'Poppins',sans-serif;font-size:13px;line-height:1.6;color:#0f172a;outline:none;box-sizing:border-box;"></textarea>
              <label style="display:flex;align-items:flex-start;gap:10px;margin-top:14px;padding:12px 14px;border-radius:14px;background:#f8fafc;border:1px solid rgba(226,232,240,0.98);color:#334155;font-size:12.5px;line-height:1.6;">
                <input id="deleteAckField" type="checkbox" style="margin-top:3px;accent-color:#dc2626;" />
                <span>I understand this will block my account until an admin reviews the request.</span>
              </label>
            </div>
          `,
          showCancelButton: true,
          confirmButtonText: 'Submit Deletion Request',
          cancelButtonText: 'Cancel',
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

