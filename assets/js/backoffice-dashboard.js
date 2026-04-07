document.addEventListener('DOMContentLoaded', () => {
    const apiBase = '../../Controllers/UserApiController.php';
    const tableBody = document.getElementById('usersTableBody');
    const addUserBtn = document.getElementById('addUserBtn');
    const modal = document.getElementById('userModal');
    const modalTitle = document.getElementById('userModalTitle');
    const closeModalBtn = document.getElementById('closeUserModal');
    const cancelModalBtn = document.getElementById('cancelUserModal');
    const userForm = document.getElementById('userForm');
    const saveUserBtn = document.getElementById('saveUserBtn');
    const currentUserId = Number(document.body.dataset.currentUserId || 0);
    const openGlobeBtn = document.getElementById('openGlobeBtn');
    const openGlobe3DBtn = document.getElementById('openGlobe3DBtn');
    const applyGlobeLocationBtn = document.getElementById('applyGlobeLocationBtn');
    const globeWrap = document.getElementById('dbGlobeWrap');
    const countryInput = document.getElementById('formCountry');
    const mapAddressInput = document.getElementById('formMapAddress');
    const latInput = document.getElementById('formLatitude');
    const lngInput = document.getElementById('formLongitude');
    const faceEnrolledAtInput = document.getElementById('formFaceEnrolledAt');
    const lastSeenInput = document.getElementById('formLastSeen');

    let users = [];
    let editMode = false;
    let pickedLocation = null;

    const getModalTitleHtml = (title) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M8 12h8"></path><path d="M12 8v8"></path></svg> ${title}`;

    const getSaveButtonHtml = (label) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><path d="M17 21v-8H7v8"></path><path d="M7 3v5h8"></path></svg>${label}`;

    const normalizeDateForPicker = (value) => {
        if (!value) return '';
        return String(value).replace('T', ' ').trim();
    };

    const setupDatePickers = () => {
        if (window.flatpickr) {
            const options = {
                enableTime: true,
                dateFormat: 'Y-m-d H:i:S',
                time_24hr: true,
                allowInput: true
            };
            window.flatpickr(faceEnrolledAtInput, options);
            window.flatpickr(lastSeenInput, options);
        }
    };

    const initGlobeMap = () => {
        if (!window.GlobeExplorer) return;

        window.GlobeExplorer.init('userGlobeMap', {
            center: [20, 0],
            zoom: 2
        });

        window.GlobeExplorer.onMapClick({
            onPick: (picked) => {
                pickedLocation = picked;
                latInput.value = String(picked.lat);
                lngInput.value = String(picked.lng);
                mapAddressInput.value = picked.display || '';

                if (picked.country) {
                    countryInput.value = picked.country;
                }
            }
        });
    };

    const showError = (message) => {
        if (window.Swal) {
            Swal.fire({
                icon: 'error',
                title: 'Oops',
                text: message || 'An error occurred.',
                confirmButtonText: 'Try again'
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
                timer: 1300,
                showConfirmButton: false
            });
            return;
        }
    };

    const toInitials = (u) => `${(u.first_name || 'U').charAt(0)}${(u.last_name || 'S').charAt(0)}`.toUpperCase();

    const roleClass = (role) => {
        if (role === 'admin') return 'pill-admin';
        if (role === 'manager') return 'pill-manager';
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

    const renderUsers = () => {
        if (!tableBody) return;

        if (!users.length) {
            tableBody.innerHTML = '<tr><td colspan="6">No users found.</td></tr>';
            return;
        }

        tableBody.innerHTML = users.map((u) => {
            const statusClass = Number(u.status) === 1 ? 'active' : 'offline';
            const statusText = Number(u.status) === 1 ? 'Active' : 'Offline';
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
                            <button class="t-btn icon-btn" data-action="edit" data-id="${u.id}" title="Edit">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4z"></path></svg>
                            </button>
                            <button class="t-btn icon-btn" data-action="toggle" data-id="${u.id}" title="Block/Unblock">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
                            </button>
                            <button class="t-btn icon-btn" data-action="delete" data-id="${u.id}" title="Delete" style="color:#ef4444;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    };

    const openModal = (title) => {
        modalTitle.innerHTML = getModalTitleHtml(title);
        modal.classList.add('open');
        if (globeWrap) globeWrap.classList.remove('open');
        setTimeout(() => {
            initGlobeMap();
            if (window.GlobeExplorer) window.GlobeExplorer.invalidateSize();
        }, 160);
    };

    const closeModal = () => {
        modal.classList.remove('open');
        userForm.reset();
        document.getElementById('formId').value = '';
        editMode = false;
    };

    const fillForm = (user) => {
        document.getElementById('formId').value = user.id || '';
        document.getElementById('formFirstName').value = user.first_name || '';
        document.getElementById('formLastName').value = user.last_name || '';
        document.getElementById('formEmail').value = user.email || '';
        document.getElementById('formPassword').value = '';
        document.getElementById('formPhone').value = user.phone || '';
        document.getElementById('formRole').value = user.role || 'user';
        document.getElementById('formStatus').value = String(Number(user.status) === 1 ? 1 : 0);
        document.getElementById('formIsBlocked').value = String(Number(user.is_blocked) === 1 ? 1 : 0);
        document.getElementById('formAvatarUrl').value = user.avatar_url || '';
        document.getElementById('formBadge').value = user.badge || '';
        document.getElementById('formCountry').value = user.country || '';
        document.getElementById('formTitle').value = user.title || '';
        document.getElementById('formSkills').value = user.skills || '';
        document.getElementById('formBio').value = user.bio || '';
        document.getElementById('formXp').value = Number(user.xp || 0);
        document.getElementById('formFaceEnrolled').value = String(Number(user.face_enrolled) === 1 ? 1 : 0);
        document.getElementById('formFaceImagesPath').value = user.face_images_path || '';
        document.getElementById('formFaceDescriptor').value = user.face_descriptor || '';
        document.getElementById('formFaceEnrolledAt').value = normalizeDateForPicker(user.face_enrolled_at || '');
        document.getElementById('formLastSeen').value = normalizeDateForPicker(user.last_seen || '');
        mapAddressInput.value = user.country ? `${user.country}` : '';
    };

    const formDataPayload = () => ({
        id: document.getElementById('formId').value,
        first_name: document.getElementById('formFirstName').value.trim(),
        last_name: document.getElementById('formLastName').value.trim(),
        email: document.getElementById('formEmail').value.trim(),
        password: document.getElementById('formPassword').value,
        phone: document.getElementById('formPhone').value.trim(),
        role: document.getElementById('formRole').value,
        status: Number(document.getElementById('formStatus').value),
        is_blocked: Number(document.getElementById('formIsBlocked').value),
        avatar_url: document.getElementById('formAvatarUrl').value.trim(),
        badge: document.getElementById('formBadge').value.trim(),
        country: document.getElementById('formCountry').value.trim(),
        title: document.getElementById('formTitle').value.trim(),
        skills: document.getElementById('formSkills').value.trim(),
        bio: document.getElementById('formBio').value.trim(),
        xp: Number(document.getElementById('formXp').value || 0),
        face_enrolled: Number(document.getElementById('formFaceEnrolled').value),
        face_images_path: document.getElementById('formFaceImagesPath').value.trim(),
        face_descriptor: document.getElementById('formFaceDescriptor').value.trim(),
        face_enrolled_at: document.getElementById('formFaceEnrolledAt').value.trim(),
        last_seen: document.getElementById('formLastSeen').value.trim()
    });

    if (addUserBtn) {
        addUserBtn.addEventListener('click', () => {
            editMode = false;
            userForm.reset();
            document.getElementById('formStatus').value = '1';
            document.getElementById('formRole').value = 'user';
            saveUserBtn.innerHTML = getSaveButtonHtml('Save User');
            openModal('Create User');
        });
    }

    if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
    if (cancelModalBtn) cancelModalBtn.addEventListener('click', closeModal);
    if (openGlobeBtn) {
        openGlobeBtn.addEventListener('click', () => {
            globeWrap.classList.toggle('open');
            if (globeWrap.classList.contains('open')) {
                initGlobeMap();
                setTimeout(() => window.GlobeExplorer && window.GlobeExplorer.invalidateSize(), 120);
            }
        });
    }

    if (applyGlobeLocationBtn) {
        applyGlobeLocationBtn.addEventListener('click', () => {
            if (!pickedLocation) {
                showError('Pick a point on the globe first.');
                return;
            }

            countryInput.value = pickedLocation.country || countryInput.value;
            mapAddressInput.value = pickedLocation.display;
            showSuccess('Location applied from Globe Explorer.');
        });
    }

    if (openGlobe3DBtn) {
        openGlobe3DBtn.addEventListener('click', () => {
            if (!window.Globe3DPicker) {
                showError('3D Globe picker is not available.');
                return;
            }

            const opened = window.Globe3DPicker.open({
                url: '../../assets/globale_explore/index.html?picker=1',
                onPick: (selection) => {
                    const country = String(selection?.country || '').trim();
                    const address = String(selection?.fullAddress || country || '').trim();

                    if (country) {
                        countryInput.value = country;
                    }
                    if (address) {
                        mapAddressInput.value = address;
                    }
                    showSuccess(`3D location selected: ${country || 'Unknown'}`);
                }
            });

            if (!opened) {
                showError('Popup blocked. Please allow popups for this site.');
            }
        });
    }
    if (modal) {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeModal();
        });
    }

    if (userForm) {
        userForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const payload = formDataPayload();

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
                openModal(`Edit User #${id}`);
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
    setupDatePickers();
});
