document.addEventListener('DOMContentLoaded', async () => {
    // Check session
    const user = await Auth.requireAuth();
    if (!user) return; // Auth helper redirects

    // Populate Data - with null checks to prevent errors
    const avatarUrl = user.avatar ? user.avatar : `https://api.dicebear.com/7.x/avataaars/svg?seed=${encodeURIComponent(user.name)}`;

    const avatarEl = document.getElementById('profileAvatar');
    const nameEl = document.getElementById('profileName');
    const editNameEl = document.getElementById('editName');
    const displayEmailEl = document.getElementById('displayEmail');
    const editEmailEl = document.getElementById('editEmail');
    const editCountryEl = document.getElementById('editCountry');
    const openAuthGlobe3DBtn = document.getElementById('openAuthGlobe3DBtn');
    const locationTextEl = document.getElementById('locationText');
    const profileLocationEl = document.getElementById('profileLocation');

    // New Elements
    const editTitleEl = document.getElementById('editTitle'); // Assuming input exists or needs to be matched
    const editBioEl = document.getElementById('editBio');
    const skillsContainer = document.querySelector('.glass-panel .flex-wrap'); // Skills container
    const skillsInput = document.querySelector('.glass-panel input[placeholder="Add a skill..."]'); // Skill input

    let currentSkills = [];

    // Safely set values only if elements exist
    if (avatarEl) avatarEl.src = avatarUrl;
    if (nameEl) nameEl.textContent = user.name;
    if (editNameEl) editNameEl.value = user.name;
    if (displayEmailEl) displayEmailEl.textContent = user.email;
    if (editEmailEl) editEmailEl.value = user.email;
    if (editCountryEl) editCountryEl.value = user.country || '';
    if (locationTextEl) locationTextEl.textContent = user.country || 'Global Citizen';
    if (profileLocationEl && user.country) {
        profileLocationEl.textContent = user.country;
        profileLocationEl.classList.remove('hidden');
    }

    if (openAuthGlobe3DBtn && editCountryEl) {
        openAuthGlobe3DBtn.addEventListener('click', () => {
            if (!window.Globe3DPicker) {
                Swal.fire({
                    icon: 'error',
                    title: '3D Globe unavailable',
                    text: 'Could not load the 3D globe picker right now.',
                    background: '#1f1f23',
                    color: '#fff'
                });
                return;
            }

            const opened = window.Globe3DPicker.open({
                url: '../assets/globale_explore/index.html?picker=1',
                onPick: (selection) => {
                    const country = String(selection?.country || '').trim();
                    if (!country) return;

                    editCountryEl.value = country;
                    if (locationTextEl) locationTextEl.textContent = country;
                    if (profileLocationEl) {
                        profileLocationEl.textContent = country;
                        profileLocationEl.classList.remove('hidden');
                    }

                    Swal.fire({
                        icon: 'success',
                        title: '3D location selected',
                        text: country,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 1500,
                        background: '#1f1f23',
                        color: '#fff'
                    });
                }
            });

            if (!opened) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Popup blocked',
                    text: 'Please allow popups to use the 3D globe picker.',
                    background: '#1f1f23',
                    color: '#fff'
                });
            }
        });
    }

    // Load detailed profile including Bio, Title, Skills
    try {
        const profileResponse = await fetch(`../api/users/get_user_profile.php?user_id=${user.id}`);
        const profileData = await profileResponse.json();

        if (profileData.success && profileData.data && profileData.data.user) {
            const fullUser = profileData.data.user;

            // Populate extended fields
            const roleBadge = document.querySelector('.text-2xl ~ span'); // "Legend" badge
            if (fullUser.badge && roleBadge) roleBadge.textContent = fullUser.badge;

            // Fill Edit Form Fields
            if (editTitleEl) editTitleEl.value = fullUser.title || '';
            if (editBioEl) editBioEl.value = fullUser.bio || '';

            // Render Skills
            currentSkills = fullUser.skills || [];
            renderSkills();

            // Stats
            const actionsJoinedElement = document.querySelector('[data-stat="actions-joined"]');
            const resourcesSharedElement = document.querySelector('[data-stat="resources-shared"]');

            // If the HTML has data-stat attributes
            if (actionsJoinedElement) actionsJoinedElement.textContent = fullUser.actions_joined;
            if (resourcesSharedElement) resourcesSharedElement.textContent = fullUser.resources_shared;

            // Also update fixed stats in HTML if no data attributes (based on previous view)
            const statsCards = document.querySelectorAll('.glass-panel .grid .p-3 .text-xl');
            if (statsCards.length >= 2) {
                statsCards[0].textContent = fullUser.actions_joined || 0;
                statsCards[1].textContent = fullUser.resources_shared || 0;
            }
        }
    } catch (error) {
        console.error('Failed to load full profile:', error);
    }

    // --- Skills Logic ---
    function renderSkills() {
        if (!skillsContainer) return;
        // Keep the input container (last child usually or check structure)
        // Actually, the structure in HTML was: container with spans, then a separate div for input.
        // Let's clear the container but we need to match the selector precisely.

        // Based on HTML: .flex.flex-wrap.gap-2 is the container.
        // It has spans inside.
        skillsContainer.innerHTML = '';

        currentSkills.forEach(skill => {
            const span = document.createElement('span');
            span.className = 'px-2 py-1 rounded text-[11px] bg-zinc-800 text-zinc-300 border border-zinc-700 flex items-center gap-1';
            span.innerHTML = `${skill} <button type="button" class="hover:text-red-400" onclick="removeSkill('${skill}')"><i data-lucide="x" class="w-2 h-2"></i></button>`;
            skillsContainer.appendChild(span);
        });

        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    // Expose removeSkill globally
    window.removeSkill = (skill) => {
        currentSkills = currentSkills.filter(s => s !== skill);
        renderSkills();
    };

    if (skillsInput) {
        skillsInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const val = skillsInput.value.trim();
                if (val && !currentSkills.includes(val)) {
                    currentSkills.push(val);
                    renderSkills();
                    skillsInput.value = '';
                }
            }
        });

        // Also add on button click if there is a plus button nearby (HTML had a plus button in header)
        const addSkillBtn = skillsInput.closest('.glass-panel').querySelector('h3 button');
        if (addSkillBtn) {
            addSkillBtn.onclick = () => skillsInput.focus();
        }
    }


    // Random Badge Logic (Visual only fallback)
    const badges = [
        { name: 'LEGEND', color: 'from-amber-200 to-yellow-500', icon: 'crown' },
        { name: 'HERO', color: 'from-blue-200 to-indigo-500', icon: 'shield' },
        { name: 'STAR', color: 'from-purple-200 to-pink-500', icon: 'star' },
        { name: 'GUARDIAN', color: 'from-emerald-200 to-green-500', icon: 'leaf' }
    ];
    const badgeIndex = user.name.length % badges.length;
    const badge = badges[badgeIndex];
    const badgeEl = document.getElementById('aiBadge');

    // Only show random badge if no real badge loaded or just decorative
    if (badgeEl && badgeEl.textContent.trim() === 'Legend') {
        badgeEl.className = `px-2 py-1 rounded-full text-[10px] font-semibold bg-gradient-to-r ${badge.color} text-zinc-900 badge-glow inline-flex items-center gap-1`;
        badgeEl.innerHTML = `<i data-lucide="${badge.icon}" class="w-3 h-3"></i> ${badge.name}`;
        badgeEl.classList.remove('hidden');
        lucide.createIcons();
    }

    // Toggle Edit Form
    const editBtn = document.getElementById('editToggleBtn');
    const editSection = document.getElementById('editFormSection');

    if (editBtn && editSection) {
        editBtn.addEventListener('click', () => {
            editSection.classList.toggle('hidden');
            if (!editSection.classList.contains('hidden')) {
                // Pre-fill inputs again to be sure
                if (editNameEl) editNameEl.value = nameEl.textContent;
                if (editCountryEl) editCountryEl.value = user.country || '';
                // Add bio/title pre-fill if vars available
            }
        });
    }

    // AI Bio Enhancement
    const aiEnhanceBioBtn = document.getElementById('aiEnhanceBioBtn');
    if (aiEnhanceBioBtn && editBioEl) {
        aiEnhanceBioBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            const currentBio = editBioEl.value;
            if (!currentBio || currentBio.trim().length < 10) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Too Short',
                    text: 'Please write a bit more about yourself first (at least 10 characters).',
                    background: '#1f1f23',
                    color: '#fff'
                });
                return;
            }

            // Show loading
            const originalContent = aiEnhanceBioBtn.innerHTML;
            aiEnhanceBioBtn.disabled = true;
            aiEnhanceBioBtn.innerHTML = '<i data-lucide="loader-2" class="w-3 h-3 animate-spin"></i> Improving...';
            lucide.createIcons();

            try {
                // Use centralized AI Features
                if (typeof AI_Features === 'undefined') {
                    throw new Error('AI Features not loaded');
                }
                const improved = await AI_Features.improveText(currentBio, 'bio');
                if (improved) {
                    editBioEl.value = improved;
                    Swal.fire({
                        icon: 'success',
                        title: 'Bio Enhanced',
                        text: 'Your bio has been polished!',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        background: '#1f1f23',
                        color: '#fff'
                    });
                }
            } catch (error) {
                console.error("AI Bio Error:", error);
                Swal.fire({
                    icon: 'error',
                    title: 'AI Error',
                    text: 'Failed to enhance bio. Please try again.',
                    background: '#1f1f23',
                    color: '#fff'
                });
            } finally {
                aiEnhanceBioBtn.disabled = false;
                aiEnhanceBioBtn.innerHTML = originalContent;
                lucide.createIcons();
            }
        });
    }

    // Handle Form Submit
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const newName = editNameEl ? editNameEl.value : user.name;
            const newEmail = editEmailEl ? editEmailEl.value : user.email; // Note: HTML might not have editEmail
            const newCountry = editCountryEl ? editCountryEl.value : user.country;
            const newTitle = editTitleEl ? editTitleEl.value : '';
            const newBio = editBioEl ? editBioEl.value : '';

            // Show loading state
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';

            // Call API
            try {
                const response = await fetch('../api/users/update_profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        name: newName,
                        email: user.email, // Keep email same if not editable
                        country: newCountry,
                        title: newTitle,
                        bio: newBio,
                        skills: currentSkills
                    })
                });
                const result = await response.json();

                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Profile Updated',
                        text: 'Your profile has been updated successfully.',
                        background: '#1f1f23',
                        color: '#fff',
                        confirmButtonColor: '#AEE1F9'
                    });
                    // Update UI
                    if (nameEl) nameEl.textContent = newName;
                    if (locationTextEl) locationTextEl.textContent = newCountry || 'Global Citizen';
                    if (editSection) editSection.classList.add('hidden');

                    // Update Tags/Badges based on title? (Optional)
                    const roleTag = document.querySelector('.glass-panel .flex-wrap span:first-child');
                    if (roleTag && newTitle) roleTag.textContent = newTitle;

                    // Update avatar if name changed
                    const newAvatarUrl = `https://api.dicebear.com/7.x/avataaars/svg?seed=${encodeURIComponent(newName)}`;
                    if (avatarEl) avatarEl.src = newAvatarUrl;
                } else {
                    throw new Error(result.message);
                }
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: err.message || 'Failed to update profile.',
                    background: '#1f1f23',
                    color: '#fff'
                });
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }

    // Handle notification/privacy toggles (save on change)
    const toggles = document.querySelectorAll('input[type="checkbox"]');
    toggles.forEach(toggle => {
        toggle.addEventListener('change', async () => {
            const preferences = {
                weekly_digest: document.getElementById('toggle1')?.checked ? 1 : 0,
                project_alerts: document.getElementById('toggle2')?.checked ? 1 : 0,
                marketing_emails: document.getElementById('toggle3')?.checked ? 1 : 0,
                public_profile: document.getElementById('toggle4')?.checked ? 1 : 0,
                share_activity_status: document.getElementById('toggle5')?.checked ? 1 : 0,
                allow_ai_analysis: document.getElementById('toggle6')?.checked ? 1 : 0
            };

            try {
                await fetch('../api/users/update_preferences.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(preferences)
                });
                console.log('Preferences saved');
            } catch (error) {
                console.error('Failed to save preferences:', error);
            }
        });
    });

    // --- Avatar Logic ---
    const uploadBtn = document.getElementById('uploadAvatarBtn');
    const fileInput = document.getElementById('avatarInput');
    const randomBtn = document.getElementById('randomAvatarBtn');

    // 1. Upload Avatar
    if (uploadBtn && fileInput) {
        uploadBtn.addEventListener('click', () => fileInput.click());

        fileInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('avatar', file);

            // Show loading state
            const originalIcon = uploadBtn.innerHTML;
            uploadBtn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 text-white animate-spin"></i>';
            lucide.createIcons();

            try {
                const response = await fetch('../api/users/upload_avatar.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    // Update image
                    if (avatarEl) avatarEl.src = result.data.avatar_url;

                    Swal.fire({
                        icon: 'success',
                        title: 'Avatar Updated',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        background: '#1f1f23',
                        color: '#fff'
                    });
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                console.error('Upload failed:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Upload Failed',
                    text: error.message,
                    background: '#1f1f23',
                    color: '#fff'
                });
            } finally {
                uploadBtn.innerHTML = originalIcon;
                lucide.createIcons();
                fileInput.value = ''; // Reset input
            }
        });
    }

    // 2. Random Avatar
    if (randomBtn) {
        randomBtn.addEventListener('click', async (e) => {
            e.stopPropagation(); // Prevent bubbling

            // Generate random seed
            const randomSeed = Math.random().toString(36).substring(7);
            const newAvatarUrl = `https://api.dicebear.com/7.x/avataaars/svg?seed=${randomSeed}`;

            // Show loading
            const originalIcon = randomBtn.innerHTML;
            randomBtn.innerHTML = '<i data-lucide="loader-2" class="w-3.5 h-3.5 text-zinc-300 animate-spin"></i>';
            lucide.createIcons();

            try {
                // Update profile with new URL
                const response = await fetch('../api/users/update_profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        name: user.name, // Keep existing name
                        email: user.email, // Keep existing email
                        country: user.country, // Keep existing country
                        avatar_url: newAvatarUrl
                    })
                });
                const result = await response.json();

                if (result.success) {
                    if (avatarEl) avatarEl.src = newAvatarUrl;

                    Swal.fire({
                        icon: 'success',
                        title: 'New Look!',
                        text: 'Random avatar applied.',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        background: '#1f1f23',
                        color: '#fff'
                    });
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                console.error('Random avatar failed:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to save random avatar.',
                    background: '#1f1f23',
                    color: '#fff'
                });
            } finally {
                randomBtn.innerHTML = originalIcon;
                lucide.createIcons();
            }
        });
    }
    // 3. Export Data
    const exportBtn = document.getElementById('exportDataBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', () => {
            const dataToExport = {
                user: user,
                profile: {
                    title: document.getElementById('editTitle')?.value || '',
                    bio: document.getElementById('editBio')?.value || '',
                    skills: currentSkills
                },
                exported_at: new Date().toISOString()
            };

            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(dataToExport, null, 2));
            const downloadAnchorNode = document.createElement('a');
            downloadAnchorNode.setAttribute("href", dataStr);
            downloadAnchorNode.setAttribute("download", "user_profile_data.json");
            document.body.appendChild(downloadAnchorNode); // required for firefox
            downloadAnchorNode.click();
            downloadAnchorNode.remove();

            Swal.fire({
                icon: 'success',
                title: 'Data Exported',
                text: 'Your profile data has been downloaded.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                background: '#1f1f23',
                color: '#fff'
            });
        });
    }
});
