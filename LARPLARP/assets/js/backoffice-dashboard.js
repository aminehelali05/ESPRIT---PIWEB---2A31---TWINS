document.addEventListener('DOMContentLoaded', () => {
    const apiBase = 'dashboard.php';
    const tableBody = document.getElementById('usersTableBody');
    const addUserBtn = document.getElementById('addUserBtn');
    const modal = document.getElementById('userModal');
    const modalTitle = document.getElementById('ufModalTitle');
    const modalSubTitle = document.getElementById('ufModalSubtitle');
    const closeModalBtn = document.getElementById('closeUserModal');
    const cancelModalBtn = document.getElementById('cancelUserModal');
    const userForm = document.getElementById('userForm');
    const saveUserBtn = document.getElementById('saveUserBtn');
    const currentUserId = Number(document.body.dataset.currentUserId || 0);
    const globeWrap = document.getElementById('dbGlobeWrap');
    const countryInput = document.getElementById('formCountry');
    const mapAddressInput = document.getElementById('formMapAddress');
    const latInput = document.getElementById('formLatitude');
    const lngInput = document.getElementById('formLongitude');
    const faceEnrolledAtInput = document.getElementById('formFaceEnrolledAt');
    const lastSeenInput = document.getElementById('formLastSeen');
    const deleteRequestsList = document.getElementById('deleteRequestsList');
    const refreshDeleteRequestsBtn = document.getElementById('refreshDeleteRequestsBtn');
    const avatarPreview = document.getElementById('formAvatarPreview');
    const avatarFallback = document.getElementById('formAvatarFallback');
    const avatarFileInput = document.getElementById('formAvatarFile');
    const avatarUrlInput = document.getElementById('formAvatarUrl');
    const pickAvatarFromFileBtn = document.getElementById('pickAvatarFromFileBtn');
    const openAvatarCameraBtn = document.getElementById('openAvatarCameraBtn');
    const captureAvatarBtn = document.getElementById('captureAvatarBtn');
    const randomAvatarBtn = document.getElementById('randomAvatarBtn');
    const avatarCameraVideo = document.getElementById('avatarCameraVideo');
    const avatarCaptureCanvas = document.getElementById('avatarCaptureCanvas');

    let users = [];
    let deleteRequests = [];
    let editMode = false;
    let pickedLocation = null;
    let initialFormSnapshot = '';
    let avatarCameraStream = null;
    let globeInitialized = false;
    const countryCapitalCache = new Map();

    const normalizeDateTimeLocal = (value) => {
        const raw = String(value || '').trim();
        if (!raw) return '';
        const normalized = raw.replace(' ', 'T');
        return normalized.length >= 16 ? normalized.slice(0, 16) : normalized;
    };

    const formatMemberSince = (rawDate) => {
        const parsed = new Date(String(rawDate || '').replace(' ', 'T'));
        if (Number.isNaN(parsed.getTime())) {
            return new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        }
        return parsed.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    };

    const readFormSnapshot = () => {
        const read = (id) => {
            const field = document.getElementById(id);
            return field ? String(field.value || '').trim() : '';
        };

        return JSON.stringify({
            id: read('formId'),
            first_name: read('formFirstName'),
            last_name: read('formLastName'),
            email: read('formEmail'),
            password: read('formPassword'),
            phone: read('formPhone'),
            role: read('formRole'),
            status: read('formStatus'),
            country: read('formCountry'),
            map_address: read('formMapAddress'),
            latitude: read('formLatitude'),
            longitude: read('formLongitude'),
            bio: read('formBio'),
            face_enrolled: read('formFaceEnrolled'),
            face_descriptor: read('formFaceDescriptor'),
            last_seen: read('formLastSeen')
        });
    };

    const normalizeRoleValue = (value) => {
        const role = String(value || '').trim().toLowerCase();
        if (role === 'freelancer' || role === 'client') {
            return role;
        }
        return 'client';
    };

    const avatarFallbackSvg = (seed = 'U') => {
        const initials = String(seed || 'U').slice(0, 2).toUpperCase();
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><defs><linearGradient id="grad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#60a5fa"/><stop offset="100%" stop-color="#6366f1"/></linearGradient></defs><rect width="120" height="120" rx="60" fill="url(#grad)" opacity="0.25"/><circle cx="60" cy="46" r="20" fill="#1e3a8a" opacity="0.92"/><path d="M24 93c6-16 20-26 36-26s30 10 36 26" fill="#1e3a8a" opacity="0.92"/><text x="60" y="108" text-anchor="middle" font-family="Poppins,Arial,sans-serif" font-size="16" fill="#1e3a8a" opacity="0.75">${initials}</text></svg>`;
        return `data:image/svg+xml,${encodeURIComponent(svg)}`;
    };

    const generateAndStoreRandomAvatar = async (seedBase = '') => {
        const firstName = String(document.getElementById('formFirstName')?.value || '').trim();
        const lastName = String(document.getElementById('formLastName')?.value || '').trim();
        const seed = `${seedBase || 'user'}-${firstName}-${lastName}-${Date.now()}`;
                const avatarUrl = `https://api.dicebear.com/9.x/avataaars/svg?seed=${encodeURIComponent(seed)}`;
                if (avatarUrlInput) avatarUrlInput.value = avatarUrl;
                updateAvatarPreview(avatarUrl, { first_name: firstName || 'U', last_name: lastName || 'S' });
                return avatarUrl;
    };

    const updateAvatarPreview = (avatarUrl, user = {}) => {
        if (!avatarPreview) return;
        const cleaned = String(avatarUrl || '').trim();
        const hasImage = cleaned !== '';
        if (hasImage) {
            avatarPreview.src = cleaned;
            if (avatarFallback) avatarFallback.style.display = 'none';
            return;
        }

        const initials = `${(user.first_name || 'U').charAt(0)}${(user.last_name || 'S').charAt(0)}`.toUpperCase();
        avatarPreview.src = avatarFallbackSvg(initials);
        if (avatarFallback) avatarFallback.style.display = 'flex';
    };

    const formatLastSeenRelative = (rawValue) => {
        const raw = String(rawValue || '').trim();
        if (!raw) return 'Last seen: recently active';

        const parsed = new Date(raw.replace(' ', 'T'));
        if (Number.isNaN(parsed.getTime())) return 'Last seen: unavailable';

        const now = new Date();
        const diffMs = Math.max(0, now.getTime() - parsed.getTime());
        const minute = 60 * 1000;
        const hour = 60 * minute;
        const day = 24 * hour;

        if (diffMs < minute) return 'Last seen: just now';
        if (diffMs < hour) return `Last seen: ${Math.floor(diffMs / minute)} minute${Math.floor(diffMs / minute) > 1 ? 's' : ''} ago`;
        if (diffMs < day) return `Last seen: ${Math.floor(diffMs / hour)} hour${Math.floor(diffMs / hour) > 1 ? 's' : ''} ago`;
        if (diffMs < 7 * day) return `Last seen: ${Math.floor(diffMs / day)} day${Math.floor(diffMs / day) > 1 ? 's' : ''} ago`;

        return `Last seen: ${parsed.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}`;
    };

    const applyPickedLocation = (picked, notify = false) => {
        if (!picked) return;
        pickedLocation = picked;

        if (latInput) latInput.value = String(picked.lat ?? '');
        if (lngInput) lngInput.value = String(picked.lng ?? '');

        const country = String(picked.country || '').trim();
        const address = String(picked.display || picked.fullAddress || country || '').trim();

        if (countryInput && country) {
            countryInput.value = country;
            countryInput.dispatchEvent(new Event('input', { bubbles: true }));
            countryInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (mapAddressInput && address) {
            mapAddressInput.value = address;
            mapAddressInput.dispatchEvent(new Event('input', { bubbles: true }));
            mapAddressInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (notify) {
            showSuccess(`Location selected: ${country || 'Picked point'}`);
        }
    };

    const syncDetailedMapToLocation = (picked) => {
        if (!picked || !window.GlobeExplorer) return;
        const lat = Number(picked.lat);
        const lng = Number(picked.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
        if (typeof window.GlobeExplorer.setView === 'function') {
            window.GlobeExplorer.setView([lat, lng], 6);
        }
    };

    const fallbackCapitals = {
        brazil: { lat: -15.793889, lng: -47.882778 },
        uruguay: { lat: -34.901112, lng: -56.164532 },
        argentina: { lat: -34.603722, lng: -58.381592 },
        tunisia: { lat: 36.806389, lng: 10.181667 },
        france: { lat: 48.856613, lng: 2.352222 },
        germany: { lat: 52.52, lng: 13.405 }
    };

    const resolveCapitalCoordinates = async (countryName) => {
        const normalized = String(countryName || '').trim();
        if (!normalized) return null;

        const cacheKey = normalized.toLowerCase();
        if (countryCapitalCache.has(cacheKey)) {
            return countryCapitalCache.get(cacheKey);
        }

        if (fallbackCapitals[cacheKey]) {
            countryCapitalCache.set(cacheKey, fallbackCapitals[cacheKey]);
            return fallbackCapitals[cacheKey];
        }

        try {
            let response = await fetch(`https://restcountries.com/v3.1/name/${encodeURIComponent(normalized)}?fullText=true&fields=name,capitalInfo,latlng`);
            if (!response.ok) {
                response = await fetch(`https://restcountries.com/v3.1/name/${encodeURIComponent(normalized)}?fields=name,capitalInfo,latlng`);
            }
            const rows = await response.json();
            const row = Array.isArray(rows)
                ? rows.find((entry) => String(entry?.name?.common || '').toLowerCase() === cacheKey) || rows[0]
                : null;
            const coords = row?.capitalInfo?.latlng || row?.latlng || null;
            if (Array.isArray(coords) && coords.length >= 2) {
                const result = {
                    lat: Number(coords[0]),
                    lng: Number(coords[1])
                };
                if (Number.isFinite(result.lat) && Number.isFinite(result.lng)) {
                    countryCapitalCache.set(cacheKey, result);
                    return result;
                }
            }
        } catch (error) {
            console.warn('Capital lookup failed:', error);
        }

        return null;
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
        if (captureAvatarBtn) {
            captureAvatarBtn.disabled = true;
        }
    };

    const uploadAvatarData = async (imageData, fileName = 'avatar.png') => {
        const response = await fetch(`${apiBase}?action=upload_avatar`, {
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

    const getModalTitle = (title) => title;

    const getSaveButtonHtml = (label) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>${label}`;

    const normalizeDateForPicker = (value) => {
        if (!value) return '';
        return String(value).replace('T', ' ').trim();
    };

    const refreshFloatingStates = () => {
        if (!userForm) return;
        const groups = userForm.querySelectorAll('.uf-group');
        groups.forEach((group) => {
            const control = group.querySelector('input, select, textarea');
            if (!control) { group.classList.remove('has-value'); return; }
            const value = String(control.value || '').trim();
            group.classList.toggle('has-value', value !== '');
        });
    };

    const bindFloatingFields = () => {
        if (!userForm) return;
        const controls = userForm.querySelectorAll('.uf-group input, .uf-group select, .uf-group textarea');
        controls.forEach((control) => {
            control.addEventListener('input', refreshFloatingStates);
            control.addEventListener('change', refreshFloatingStates);
        });
        refreshFloatingStates();
    };

    const setupDatePickers = () => {
        if (window.flatpickr) {
            const options = {
                enableTime: true,
                dateFormat: 'Y-m-d H:i:S',
                time_24hr: true,
                allowInput: true
            };
            if (faceEnrolledAtInput) window.flatpickr(faceEnrolledAtInput, options);
        }
    };

    const initGlobeMap = () => {
        if (!window.GlobeExplorer) return;

        if (!document.getElementById('userGlobeMap')) return;

        window.GlobeExplorer.init('userGlobeMap', {
            center: [18, 6],
            zoom: 3
        });

        window.GlobeExplorer.onMapClick({
            onPick: (picked) => {
                applyPickedLocation(picked, false);
            }
        });

        if (pickedLocation) {
            syncDetailedMapToLocation(pickedLocation);
        }
    };

    const showError = (message) => {
        if (window.Swal) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message || 'An error occurred.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3500,
                timerProgressBar: true,
                customClass: { container: 'uf-swal-front' }
            });
            return;
        }
        alert(message || 'An error occurred.');
    };

    const showSuccess = (message) => {
        if (window.Swal) {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2500,
                timerProgressBar: true,
                customClass: { container: 'uf-swal-front' }
            });
            return;
        }
    };

    const toInitials = (u) => `${(u.first_name || 'U').charAt(0)}${(u.last_name || 'S').charAt(0)}`.toUpperCase();

    const roleClass = (role) => {
        if (role === 'freelancer') return 'pill-manager';
        return 'pill-user';
    };

    const animateCounter = (element, target) => {
        if (!element) return;
        const start = Number(element.textContent.replace(/,/g, '')) || 0;
        const duration = 500;
        const startTime = performance.now();

        const tick = (now) => {
            const progress = Math.min((now - startTime) / duration, 1);
            const value = Math.floor(start + (target - start) * progress);
            element.textContent = value.toLocaleString();
            if (progress < 1) requestAnimationFrame(tick);
        };

        requestAnimationFrame(tick);
    };

    const updateStats = async () => {
        try {
            const response = await fetch(`${apiBase}?action=stats`);
            const data = await response.json();
            if (!response.ok || !data.success) return;

            animateCounter(document.getElementById('kpiTotalUsers'), Number(data.stats.total || 0));
            animateCounter(document.getElementById('kpiAdmins'), Number(data.stats.admins || 0));
            animateCounter(document.getElementById('kpiNewThisMonth'), Number(data.stats.newThisMonth || 0));
        } catch (error) {
            console.error(error);
        }
    };

    const loadUsers = async () => {
        try {
            const response = await fetch(`${apiBase}?action=list`);
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Could not load users.');
            }
            users = data.users || [];
            renderUsers();
            updateStats();
        } catch (error) {
            showError(error.message);
        }
    };

    const renderDeleteRequests = () => {
        if (!deleteRequestsList) return;

        if (!deleteRequests.length) {
            deleteRequestsList.innerHTML = '<div class="empty-requests">No pending delete requests.</div>';
            return;
        }

        deleteRequestsList.innerHTML = deleteRequests.map((request) => {
            const fullName = `${request.first_name || ''} ${request.last_name || ''}`.trim() || `User #${request.user_id}`;
            const reason = String(request.reason || '').trim() || 'No reason provided.';
            const initials = fullName
                .split(/\s+/)
                .filter(Boolean)
                .slice(0, 2)
                .map((part) => part.charAt(0).toUpperCase())
                .join('') || 'DU';
            return `
                <article class="delete-request-item" data-request-id="${request.id}">
                    <div class="delete-request-card-top">
                        <div class="delete-request-avatar" aria-hidden="true">${initials}</div>
                        <div class="delete-request-header-copy">
                            <div class="delete-request-user">${fullName} <span class="delete-request-chip">#${request.user_id}</span></div>
                            <div class="delete-request-meta">${request.email || 'No email'} · Requested ${request.created_at || ''}</div>
                        </div>
                        <div class="delete-request-status-pill">
                            <i data-lucide="clock-3" class="w-3 h-3"></i>
                            Pending review
                        </div>
                    </div>
                    <div class="delete-request-reason-wrap">
                        <div class="delete-request-label">Reason</div>
                        <div class="delete-request-reason">${reason}</div>
                    </div>
                    <div class="delete-request-actions">
                        <button class="db-mini-btn db-approve" data-delete-action="approve" data-request-id="${request.id}"><i data-lucide="check" class="w-3 h-3"></i><span>Approve</span></button>
                        <button class="db-mini-btn db-reject" data-delete-action="reject" data-request-id="${request.id}"><i data-lucide="x" class="w-3 h-3"></i><span>Reject</span></button>
                    </div>
                </article>
            `;
        }).join('');

        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    };

    const loadDeleteRequests = async () => {
        if (!deleteRequestsList) return;

        try {
            const response = await fetch(`${apiBase}?action=list_delete_requests&status=pending`);
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Could not load delete requests.');
            }
            deleteRequests = Array.isArray(data.requests) ? data.requests : [];
            renderDeleteRequests();
        } catch (error) {
            deleteRequestsList.innerHTML = `<div class="empty-requests">${error.message}</div>`;
        }
    };

    const renderUsers = () => {
        if (!tableBody) return;

        if (!users.length) {
            tableBody.innerHTML = '<tr><td colspan="6">No users found.</td></tr>';
            return;
        }

        tableBody.innerHTML = users.map((u) => {
            const statusClass = Number(u.status) === 1 ? 'active' : 'offline';
            const statusText = Number(u.status) === 1 ? 'Active' : 'Offline';
            const isCurrent = Number(u.id) === currentUserId;
            const editButton = isCurrent
                ? `<button class="t-btn icon-btn" data-action="edit" data-id="${u.id}" title="Edit my profile">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4z"></path></svg>
                   </button>`
                : `<button class="t-btn icon-btn" data-action="policy" data-id="${u.id}" title="Direct editing disabled by policy">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                   </button>`;
            return `
                <tr>
                    <td>#${u.id}</td>
                    <td>
                        <div class="u-cell">
                            <div class="u-avatar">${toInitials(u)}</div>
                            <div>
                                <span class="u-name">${u.first_name} ${u.last_name}</span>
                            </div>
                        </div>
                    </td>
                    <td><span class="u-email">${u.email}</span></td>
                    <td><span class="pill ${roleClass((u.role || '').toLowerCase())}">${u.role}</span></td>
                    <td><div class="status st-${statusClass}"><div class="st-dot"></div>${statusText}</div></td>
                    <td>
                        <div class="t-actions">
                            ${editButton}
                            <button class="t-btn icon-btn" data-action="toggle" data-id="${u.id}" title="Block/Unblock">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
                            </button>
                            <button class="t-btn icon-btn" data-action="policy" data-id="${u.id}" title="Deletion requires user request" style="color:#ef4444;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22"></path><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6"></path></svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    };

    const openModal = (title, mode = 'create', joinedAt = '') => {
        if (modalTitle) modalTitle.textContent = title;
        if (modalSubTitle) {
            const memberSince = formatMemberSince(joinedAt);
            modalSubTitle.innerHTML = mode === 'edit'
                ? `Refine this account with precision and consistency. <strong class="uf-member-since">Member since ${memberSince}</strong>.`
                : `Welcome to Diversity.is — build a complete profile from day one. <strong class="uf-member-since">Member since ${memberSince}</strong>.`;
        }
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        document.body.classList.add('modal-edit-open');

        if (lastSeenInput && lastSeenInput.type === 'datetime-local') {
            lastSeenInput.value = normalizeDateTimeLocal(lastSeenInput.value);
        }

        if (!globeInitialized) {
            initGlobeMap();
            globeInitialized = true;
        }
        if (window.GlobeExplorer && typeof window.GlobeExplorer.invalidateSize === 'function') {
            setTimeout(() => window.GlobeExplorer.invalidateSize(), 180);
        }

        const firstField = document.getElementById('formFirstName');
        if (firstField) {
            setTimeout(() => firstField.focus(), 220);
        }

        setTimeout(() => {
            initialFormSnapshot = readFormSnapshot();
        }, 210);

        setTimeout(() => refreshFloatingStates(), 120);
    };

    const closeModal = () => {
        stopAvatarCamera();
        modal.classList.remove('open');
        document.body.style.overflow = '';
        document.body.classList.remove('modal-edit-open');
        userForm.reset();
        document.getElementById('formId').value = '';
        updateAvatarPreview('', { first_name: 'U', last_name: 'S' });
        if (avatarUrlInput) avatarUrlInput.value = '';
        pickedLocation = null;
        if (latInput) latInput.value = '';
        if (lngInput) lngInput.value = '';
        if (mapAddressInput) mapAddressInput.value = '';
        editMode = false;
        if (modalSubTitle) {
            modalSubTitle.innerHTML = `Welcome to Diversity.is — build a complete profile from day one. <strong class="uf-member-since">Member since ${formatMemberSince('')}</strong>.`;
        }
        refreshFloatingStates();
    };

    const fillForm = (user) => {
        document.getElementById('formId').value = user.id || '';
        document.getElementById('formFirstName').value = user.first_name || '';
        document.getElementById('formLastName').value = user.last_name || '';
        document.getElementById('formEmail').value = user.email || '';
        document.getElementById('formPassword').value = '';
        document.getElementById('formPhone').value = user.phone || '';
        document.getElementById('formRole').value = normalizeRoleValue(user.role || 'client');
        document.getElementById('formStatus').value = String(Number(user.status) === 1 ? 1 : 0);
        document.getElementById('formCountry').value = (user.country && user.country.trim() !== '') ? user.country : 'Unknown';
        if (avatarUrlInput) {
            avatarUrlInput.value = user.avatar_url || '';
        }
        updateAvatarPreview(user.avatar_url || '', user);
        document.getElementById('formBio').value = user.bio || '';
        document.getElementById('formFaceEnrolled').value = String(Number(user.face_enrolled) === 1 ? 1 : 0);
        const facePathField = document.getElementById('formFaceImagesPath');
        if (facePathField) {
            facePathField.value = '';
            facePathField.dataset.currentPath = user.face_images_path || '';
        }
        document.getElementById('formFaceDescriptor').value = user.face_descriptor || '';
        if (lastSeenInput) {
            lastSeenInput.value = formatLastSeenRelative(user.last_seen || '');
            lastSeenInput.setAttribute('readonly', 'readonly');
        }
        mapAddressInput.value = user.country ? `${user.country}` : '';

        const countryForMap = String(user.country || '').trim();
        if (countryForMap) {
            resolveCapitalCoordinates(countryForMap).then((coords) => {
                if (!coords) return;
                const picked = {
                    lat: coords.lat,
                    lng: coords.lng,
                    country: countryForMap,
                    display: countryForMap,
                    fullAddress: countryForMap
                };
                applyPickedLocation(picked, false);
                syncDetailedMapToLocation(picked);
            });
        }

        refreshFloatingStates();
    };

    const formDataPayload = () => {
        const facePathField = document.getElementById('formFaceImagesPath');
        const pickedFileName = facePathField?.files?.[0]?.name || '';
        const currentPath = String(facePathField?.dataset?.currentPath || '').trim();

        return {
            id: document.getElementById('formId').value,
            first_name: document.getElementById('formFirstName').value.trim(),
            last_name: document.getElementById('formLastName').value.trim(),
            email: document.getElementById('formEmail').value.trim(),
            password: document.getElementById('formPassword').value,
            phone: document.getElementById('formPhone').value.trim(),
            role: document.getElementById('formRole').value,
            status: Number(document.getElementById('formStatus').value),
            avatar_url: String(avatarUrlInput?.value || '').trim(),
            country: document.getElementById('formCountry').value.trim() || 'Unknown',
            bio: document.getElementById('formBio').value.trim(),
            face_enrolled: Number(document.getElementById('formFaceEnrolled').value),
            face_images_path: pickedFileName || currentPath,
            face_descriptor: document.getElementById('formFaceDescriptor').value.trim(),
            last_seen: ''
        };
    };

    window.addEventListener('message', async (event) => {
        const data = event.data || {};
        if (data.type !== 'globale-explore-select') return;

        const selection = data.payload || {};
        const country = String(selection.country || '').trim();
        const address = String(selection.fullAddress || country || '').trim();

        const capitalCoordinates = await resolveCapitalCoordinates(country);
        const lat = capitalCoordinates?.lat ?? Number(selection.lat);
        const lng = capitalCoordinates?.lng ?? Number(selection.lng);

        const picked = {
            lat,
            lng,
            country,
            display: address,
            fullAddress: address
        };

        applyPickedLocation(picked, true);
        syncDetailedMapToLocation(picked);
    });

    if (addUserBtn) {
        addUserBtn.addEventListener('click', () => {
            editMode = false;
            userForm.reset();
            document.getElementById('formStatus').value = '1';
            document.getElementById('formRole').value = 'client';
            document.getElementById('formCountry').value = 'Unknown';
            updateAvatarPreview('', { first_name: 'U', last_name: 'S' });
            if (avatarUrlInput) {
                avatarUrlInput.value = '';
            }
            if (lastSeenInput) {
                lastSeenInput.value = formatLastSeenRelative('');
                lastSeenInput.setAttribute('readonly', 'readonly');
            }
            saveUserBtn.innerHTML = getSaveButtonHtml('Save User');
            openModal('Create User', 'create', '');
        });
    }

    if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
    if (cancelModalBtn) cancelModalBtn.addEventListener('click', closeModal);
    if (modal) {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeModal();
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && modal.classList.contains('open')) {
            closeModal();
        }
    });

    if (userForm) {
        userForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (window.UserValidation && !window.UserValidation.validateForm(userForm, 'dashboard')) {
                return;
            }

            const payload = formDataPayload();

            const currentSnapshot = readFormSnapshot();
            if (initialFormSnapshot && currentSnapshot === initialFormSnapshot) {
                showError('No changes detected.');
                return;
            }

            saveUserBtn.disabled = true;
            saveUserBtn.innerHTML = getSaveButtonHtml('Saving...');

            try {
                const action = editMode ? 'update' : 'create';
                const response = await fetch(`${apiBase}?action=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Could not save user.');
                }

                closeModal();
                await loadUsers();
                showSuccess(editMode ? 'User updated successfully.' : 'User created successfully.');
                initialFormSnapshot = '';
            } catch (error) {
                showError(error.message);
            } finally {
                saveUserBtn.disabled = false;
                saveUserBtn.innerHTML = getSaveButtonHtml('Save User');
            }
        });
    }

    if (tableBody) {
        tableBody.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-action]');
            if (!button) return;

            const action = button.dataset.action;
            const id = Number(button.dataset.id || 0);
            if (!id) return;

            if (action === 'edit') {
                const user = users.find((u) => Number(u.id) === id);
                if (!user) return;
                editMode = true;
                fillForm(user);
                openModal(`Edit User #${id}`, 'edit', user.created_at || '');
                return;
            }

            if (action === 'policy') {
                showError('Direct edit/delete of other users is disabled. Use delete requests workflow.');
                return;
            }

            if (action === 'toggle') {
                try {
                    const response = await fetch(`${apiBase}?action=toggle`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id })
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success) throw new Error(result.message || 'Toggle failed.');
                    await loadUsers();
                    showSuccess('User status updated.');
                } catch (error) {
                    showError(error.message);
                }
                return;
            }

            if (action === 'delete') {
                if (id === currentUserId) {
                    showError('You cannot delete your own active session user.');
                    return;
                }
                let confirmed = true;
                if (window.Swal) {
                    const result = await Swal.fire({
                        icon: 'warning',
                        title: `Delete user #${id}?`,
                        text: 'This action cannot be undone.',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, delete',
                        cancelButtonText: 'Cancel'
                    });
                    confirmed = result.isConfirmed;
                } else {
                    confirmed = confirm(`Delete user #${id}?`);
                }
                if (!confirmed) return;

                try {
                    const response = await fetch(`${apiBase}?action=delete`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id })
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success) throw new Error(result.message || 'Delete failed.');
                    await loadUsers();
                    showSuccess('User deleted successfully.');
                } catch (error) {
                    showError(error.message);
                }
            }
        });
    }

    if (pickAvatarFromFileBtn && avatarFileInput) {
        pickAvatarFromFileBtn.addEventListener('click', () => avatarFileInput.click());
        avatarFileInput.addEventListener('change', async () => {
            const file = avatarFileInput.files?.[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = async () => {
                try {
                    const dataUrl = String(reader.result || '');
                    const uploadedPath = await uploadAvatarData(dataUrl, file.name || 'avatar-upload.png');
                    if (avatarUrlInput) avatarUrlInput.value = uploadedPath;
                    updateAvatarPreview(uploadedPath);
                    showSuccess('Avatar uploaded successfully.');
                } catch (error) {
                    showError(error.message || 'Avatar upload failed.');
                }
            };
            reader.readAsDataURL(file);
        });
    }

    if (openAvatarCameraBtn && avatarCameraVideo) {
        openAvatarCameraBtn.addEventListener('click', async () => {
            try {
                stopAvatarCamera();
                avatarCameraStream = await navigator.mediaDevices.getUserMedia({ video: { width: { ideal: 720 }, height: { ideal: 720 }, facingMode: 'user' }, audio: false });
                avatarCameraVideo.srcObject = avatarCameraStream;
                avatarCameraVideo.classList.add('active');
                if (captureAvatarBtn) captureAvatarBtn.disabled = false;
            } catch (error) {
                showError('Camera unavailable or permission denied.');
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
                if (avatarUrlInput) avatarUrlInput.value = uploadedPath;
                updateAvatarPreview(uploadedPath);
                showSuccess('Camera avatar captured and saved.');
                stopAvatarCamera();
            } catch (error) {
                showError(error.message || 'Could not save camera avatar.');
            }
        });
    }

    if (randomAvatarBtn) {
        randomAvatarBtn.addEventListener('click', async () => {
            randomAvatarBtn.disabled = true;
            try {
                if (avatarPreview) {
                    avatarPreview.style.transition = 'transform 0.35s, opacity 0.28s';
                    avatarPreview.style.opacity = '0.2';
                    avatarPreview.style.transform = 'scale(0.94)';
                }

                await generateAndStoreRandomAvatar('dashboard-random');

                if (avatarPreview) {
                    avatarPreview.style.opacity = '1';
                    avatarPreview.style.transform = 'scale(1)';
                }
                showSuccess('Random avatar generated and saved.');
            } catch (error) {
                if (avatarPreview) {
                    avatarPreview.style.opacity = '1';
                    avatarPreview.style.transform = 'scale(1)';
                }
                showError(error.message || 'Random avatar generation failed.');
            } finally {
                randomAvatarBtn.disabled = false;
            }
        });
    }

    if (refreshDeleteRequestsBtn) {
        refreshDeleteRequestsBtn.addEventListener('click', () => {
            loadDeleteRequests();
        });
    }

    if (deleteRequestsList) {
        deleteRequestsList.addEventListener('click', async (event) => {
            const actionBtn = event.target.closest('[data-delete-action]');
            if (!actionBtn) return;

            const deleteAction = actionBtn.dataset.deleteAction;
            const requestId = Number(actionBtn.dataset.requestId || 0);
            if (!requestId) return;

            try {
                if (deleteAction === 'approve') {
                    const response = await fetch(`${apiBase}?action=approve_delete_request`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ request_id: requestId })
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success) throw new Error(result.message || 'Could not approve request.');
                    showSuccess('Delete request approved. Account permanently deleted.');
                }

                if (deleteAction === 'reject') {
                    const response = await fetch(`${apiBase}?action=reject_delete_request`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ request_id: requestId, admin_note: 'Rejected by admin' })
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success) throw new Error(result.message || 'Could not reject request.');
                    showSuccess('Delete request rejected. User account re-enabled.');
                }

                await loadDeleteRequests();
                await loadUsers();
            } catch (error) {
                showError(error.message || 'Action failed.');
            }
        });
    }

    document.querySelectorAll('.nav-item').forEach((link) => {
        link.addEventListener('click', function (event) {
            const href = this.getAttribute('href') || '';
            if (!href.startsWith('#')) return;
            event.preventDefault();
            document.querySelectorAll('.nav-item').forEach((l) => l.classList.remove('active'));
            this.classList.add('active');
            const target = document.querySelector(href);
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    loadUsers();
    loadDeleteRequests();
    setupDatePickers();
    bindFloatingFields();
});
