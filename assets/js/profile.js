/* ============================================================
   PROFILE.JS — Profile page interactions
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  // --- AI Impact Bar Animation ---
  const aiBar = document.getElementById('aiBarFill');
  if (aiBar) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          setTimeout(() => { aiBar.style.width = '92%'; }, 300);
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.3 });
    observer.observe(aiBar.closest('.ai-insight-card'));
  }

  // --- Tab Switching ---
  const tabs = document.querySelectorAll('.profile-tab');
  const contents = document.querySelectorAll('.tab-content');

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');

      const target = tab.dataset.tab;
      contents.forEach(c => {
        c.classList.toggle('active', c.dataset.content === target);
      });

      // Re-render Lucide icons for newly visible tab
      if (typeof lucide !== 'undefined') lucide.createIcons();
    });
  });

  // --- Edit Modal ---
  const editBtn = document.getElementById('editToggleBtn');
  const modal = document.getElementById('editModal');
  const closeBtn = document.getElementById('closeUserModal') || document.getElementById('closeEditBtn');
  const cancelBtn = document.getElementById('cancelUserModal') || document.getElementById('cancelEditBtn');
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

  let pickedLocation = null;

  function openModal() {
    if (!modal) return;
    modal.classList.add('open');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    if (typeof lucide !== 'undefined') lucide.createIcons();
  }

  function closeModal() {
    if (!modal) return;
    modal.classList.remove('open');
    modal.classList.remove('active');
    document.body.style.overflow = '';
    if (typeof lucide !== 'undefined') lucide.createIcons();
  }

  const initGlobeMap = () => {
    if (!window.GlobeExplorer) return;

    window.GlobeExplorer.init('userGlobeMap', {
      center: [20, 0],
      zoom: 2
    });

    window.GlobeExplorer.onMapClick({
      onPick: (picked) => {
        pickedLocation = picked;
        if (latInput) latInput.value = String(picked.lat);
        if (lngInput) lngInput.value = String(picked.lng);
        if (mapAddressInput) mapAddressInput.value = picked.display || '';
        if (countryInput && picked.country) countryInput.value = picked.country;
      }
    });
  };

  const setupDatePickers = () => {
    if (!window.flatpickr) return;

    const options = {
      enableTime: true,
      dateFormat: 'Y-m-d H:i:S',
      time_24hr: true,
      allowInput: true
    };

    if (faceEnrolledAtInput) window.flatpickr(faceEnrolledAtInput, options);
    if (lastSeenInput) window.flatpickr(lastSeenInput, options);
  };

  setupDatePickers();

  if (editBtn) editBtn.addEventListener('click', openModal);
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeModal();
    });
  }

  if (openGlobeBtn && globeWrap) {
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
      if (!pickedLocation || !countryInput || !mapAddressInput) {
        showToast('Pick a point on the globe first.');
        return;
      }

      countryInput.value = pickedLocation.country || countryInput.value;
      mapAddressInput.value = pickedLocation.display || mapAddressInput.value;
      showToast('Location applied from Globe Explorer.');
    });
  }

  if (openGlobe3DBtn) {
    openGlobe3DBtn.addEventListener('click', () => {
      if (!window.Globe3DPicker) {
        showToast('3D Globe picker is not available.');
        return;
      }

      const opened = window.Globe3DPicker.open({
        url: '../../assets/globale_explore/index.html?picker=1',
        onPick: (selection) => {
          const country = String(selection?.country || '').trim();
          const address = String(selection?.fullAddress || country || '').trim();

          if (countryInput && country) {
            countryInput.value = country;
          }

          if (mapAddressInput && address) {
            mapAddressInput.value = address;
          }

          showToast(`3D location selected: ${country || 'Unknown'}`);
        }
      });

      if (!opened) {
        showToast('Popup blocked. Please allow popups for this site.');
      }
    });
  }

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal && modal.classList.contains('active')) {
      closeModal();
    }
  });

  const profileForm = document.getElementById('userForm') || document.getElementById('profileForm');
  if (profileForm) {
    profileForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const readValue = (...ids) => {
        for (const fieldId of ids) {
          const field = document.getElementById(fieldId);
          if (field) {
            return (field.value || '').trim();
          }
        }
        return '';
      };

      const payload = {
        first_name: readValue('formFirstName', 'editFirstName'),
        last_name: readValue('formLastName', 'editLastName'),
        email: readValue('formEmail', 'editEmail'),
        phone: readValue('formPhone', 'editPhone'),
        title: readValue('formTitle', 'editTitle'),
        country: readValue('formCountry', 'editLocation'),
        skills: readValue('formSkills', 'editSkills'),
        avatar_url: readValue('formAvatarUrl', 'editAvatarUrl'),
        bio: readValue('formBio', 'editBio')
      };

      if (!payload.first_name || !payload.last_name || !payload.email) {
        showToast('First name, last name, and email are required.');
        return;
      }

      try {
        const response = await fetch('../../Controllers/UserApiController.php?action=profile_update', {
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

        const locationText = document.getElementById('profileLocationText');
        if (locationText) {
          locationText.innerHTML = `<i data-lucide="map-pin" class="w-3.5 h-3.5"></i> ${payload.country || 'No country set'}`;
        }

        const titleTag = document.getElementById('profileTitleTag');
        if (titleTag) {
          titleTag.innerHTML = `<i data-lucide="code-2" class="w-3 h-3"></i> ${payload.title || 'Member'}`;
        }

        const bioText = document.getElementById('aboutBioText');
        if (bioText) {
          bioText.textContent = payload.bio || 'No bio yet.';
        }

        const avatarImg = document.getElementById('profileAvatar');
        if (avatarImg && payload.avatar_url) {
          avatarImg.src = payload.avatar_url;
        }

        if (typeof lucide !== 'undefined') lucide.createIcons();
        showToast('Profile updated successfully.');
        closeModal();
      } catch (error) {
        showToast(error.message || 'Could not update profile right now.');
      }
    });
  }

  // --- Avatar Upload ---
  const avatarBtn = document.getElementById('avatarUploadBtn');
  const avatarInput = document.getElementById('avatarInput');
  const avatarImg = document.getElementById('profileAvatar');

  if (avatarBtn && avatarInput) {
    avatarBtn.addEventListener('click', () => avatarInput.click());
    avatarInput.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (file && avatarImg) {
        const reader = new FileReader();
        reader.onload = (ev) => { avatarImg.src = ev.target.result; };
        reader.readAsDataURL(file);
      }
    });
  }

  // --- Random Avatar ---
  const randomBtn = document.getElementById('randomAvatarBtn');
  if (randomBtn && avatarImg) {
    randomBtn.addEventListener('click', () => {
      const seeds = ['Felix', 'Luna', 'Max', 'Nova', 'Aria', 'Storm', 'Blaze', 'Echo', 'Sage', 'Pixel'];
      const seed = seeds[Math.floor(Math.random() * seeds.length)] + Date.now();
      avatarImg.style.transition = 'transform 0.4s, opacity 0.3s';
      avatarImg.style.opacity = '0';
      avatarImg.style.transform = 'scale(0.8) rotate(10deg)';
      setTimeout(() => {
        avatarImg.src = `https://api.dicebear.com/7.x/avataaars/svg?seed=${seed}`;
        avatarImg.style.opacity = '1';
        avatarImg.style.transform = 'scale(1) rotate(0deg)';
      }, 300);
    });
  }

  // --- Skill Input ---
  const skillInput = document.getElementById('skillInput');
  const skillsContainer = document.querySelector('.skills-tags');

  if (skillInput && skillsContainer) {
    skillInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && skillInput.value.trim()) {
        e.preventDefault();
        const tag = document.createElement('span');
        tag.className = 'skill-tag';
        tag.innerHTML = `${skillInput.value.trim()} <button class="skill-remove"><i data-lucide="x" class="w-2.5 h-2.5"></i></button>`;
        skillsContainer.appendChild(tag);
        skillInput.value = '';

        tag.querySelector('.skill-remove').addEventListener('click', () => tag.remove());
        if (typeof lucide !== 'undefined') lucide.createIcons();
      }
    });
  }

  // --- Skill Remove buttons ---
  document.querySelectorAll('.skill-remove').forEach(btn => {
    btn.addEventListener('click', () => btn.closest('.skill-tag').remove());
  });

  // --- New profile enhancements ---
  const sparkFill = document.querySelector('.spark-fill');
  if (sparkFill) {
    setTimeout(() => {
      sparkFill.style.width = '76%';
    }, 300);
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

  const fabEditProfile = document.getElementById('fabEditProfile');
  if (fabEditProfile && editBtn) {
    fabEditProfile.addEventListener('click', () => {
      openModal();
    });
  }

  const togglePreviewBtn = document.getElementById('togglePreviewBtn');
  const previewModeBadge = document.getElementById('previewModeBadge');
  if (togglePreviewBtn && previewModeBadge) {
    let isPublicPreview = false;
    togglePreviewBtn.addEventListener('click', () => {
      isPublicPreview = !isPublicPreview;
      previewModeBadge.innerHTML = isPublicPreview
        ? '<i data-lucide="eye-off" class="w-3 h-3"></i> Public Preview'
        : '<i data-lucide="eye" class="w-3 h-3"></i> Owner View';
      showToast(isPublicPreview ? 'Public preview mode enabled.' : 'Owner mode restored.');
      if (typeof lucide !== 'undefined') lucide.createIcons();
    });
  }

  const richBio = document.querySelector('.rich-bio');
  const expandBioBtn = document.getElementById('expandBioBtn');
  if (richBio && expandBioBtn) {
    expandBioBtn.addEventListener('click', () => {
      const expanded = richBio.classList.toggle('expanded');
      expandBioBtn.innerHTML = expanded
        ? '<i class="fa-solid fa-chevron-up"></i> Show less'
        : '<i class="fa-solid fa-chevron-down"></i> Read full positioning';
    });
  }

  const aboutPillModal = document.getElementById('aboutPillModal');
  const aboutPillModalTitle = document.getElementById('aboutPillModalTitle');
  const aboutPillModalText = document.getElementById('aboutPillModalText');
  const aboutPillClose = document.getElementById('aboutPillClose');
  const aboutPillDetails = {
    projects: {
      title: 'Signature Projects',
      text: 'You delivered high-impact software products with strong adoption and measurable outcomes. This section highlights architecture depth, team leadership, and quality consistency across launches.'
    },
    stats: {
      title: 'Performance Snapshot',
      text: 'Your profile shows stable, above-benchmark engagement signals. Retention, collaboration response time, and contribution consistency indicate reliable long-term value in cross-functional teams.'
    },
    testimonials: {
      title: 'Peer Validation',
      text: 'Collected recommendations emphasize communication, delivery rigor, and problem-solving under constraints. Third-party validation reinforces professional credibility and trust perception.'
    },
    video: {
      title: 'Pitch Video',
      text: 'Your video positioning combines narrative clarity and confident delivery. Keep this updated with role-aligned messaging and concrete project outcomes to maximize conversion impact.'
    }
  };

  function openAboutPillModal(detail) {
    if (!aboutPillModal || !aboutPillModalTitle || !aboutPillModalText) {
      return;
    }
    aboutPillModalTitle.textContent = detail.title;
    aboutPillModalText.textContent = detail.text;
    aboutPillModal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeAboutPillModal() {
    if (!aboutPillModal) {
      return;
    }
    aboutPillModal.classList.remove('active');
    document.body.style.overflow = '';
  }

  document.querySelectorAll('.about-pill[data-detail]').forEach((pill) => {
    pill.addEventListener('click', () => {
      const key = pill.dataset.detail;
      const detail = aboutPillDetails[key];
      if (detail) {
        openAboutPillModal(detail);
      }
    });
  });

  if (aboutPillClose) {
    aboutPillClose.addEventListener('click', closeAboutPillModal);
  }
  if (aboutPillModal) {
    aboutPillModal.addEventListener('click', (event) => {
      if (event.target === aboutPillModal) {
        closeAboutPillModal();
      }
    });
  }

  const profilePitchVideo = document.getElementById('profilePitchVideo');
  const videoOverlayPlay = document.getElementById('videoOverlayPlay');
  if (profilePitchVideo && videoOverlayPlay) {
    const syncOverlayState = () => {
      videoOverlayPlay.style.display = profilePitchVideo.paused ? 'flex' : 'none';
    };

    videoOverlayPlay.addEventListener('click', () => {
      profilePitchVideo.play();
    });
    profilePitchVideo.addEventListener('click', () => {
      if (profilePitchVideo.paused) {
        profilePitchVideo.play();
      } else {
        profilePitchVideo.pause();
      }
    });
    profilePitchVideo.addEventListener('play', syncOverlayState);
    profilePitchVideo.addEventListener('pause', syncOverlayState);
    syncOverlayState();
  }

  const pipVideoBtn = document.getElementById('pipVideoBtn');
  if (pipVideoBtn && profilePitchVideo) {
    pipVideoBtn.addEventListener('click', async () => {
      try {
        if (document.pictureInPictureElement) {
          await document.exitPictureInPicture();
        } else if (document.pictureInPictureEnabled && !profilePitchVideo.disablePictureInPicture) {
          await profilePitchVideo.requestPictureInPicture();
        }
      } catch (error) {
        console.warn('PiP unavailable:', error);
      }
    });
  }

  const editCards = Array.from(document.querySelectorAll('.suggested-edit-card'));
  const editPrevBtn = document.getElementById('editPrevBtn');
  const editNextBtn = document.getElementById('editNextBtn');
  let editIndex = 0;

  const renderEditCard = () => {
    if (!editCards.length) {
      return;
    }
    editCards.forEach((card, index) => {
      card.classList.toggle('active', index === editIndex);
    });
  };

  if (editCards.length) {
    renderEditCard();
  }

  if (editPrevBtn && editCards.length) {
    editPrevBtn.addEventListener('click', () => {
      editIndex = (editIndex - 1 + editCards.length) % editCards.length;
      renderEditCard();
    });
  }

  if (editNextBtn && editCards.length) {
    editNextBtn.addEventListener('click', () => {
      editIndex = (editIndex + 1) % editCards.length;
      renderEditCard();
    });
  }

  document.querySelectorAll('.apply-edit-btn').forEach((button) => {
    button.addEventListener('click', () => {
      const message = button.dataset.apply || 'Suggested improvement applied.';
      showToast(message, 'success');
    });
  });

  const shareProfileBtn = document.getElementById('shareProfileBtn');
  if (shareProfileBtn) {
    shareProfileBtn.addEventListener('click', async () => {
      const url = window.location.href;
      try {
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(url);
          showToast('Profile link copied. QR share ready.');
        } else {
          showToast('Share link generated.');
        }
      } catch {
        showToast('Could not copy link right now.');
      }
    });
  }

  const aiPolishBtn = document.getElementById('aiPolishBtn');
  if (aiPolishBtn) {
    aiPolishBtn.addEventListener('click', () => {
      showToast('AI polish suggestions generated for your bio.');
    });
  }

  const requestReviewBtn = document.getElementById('requestReviewBtn');
  if (requestReviewBtn) {
    requestReviewBtn.addEventListener('click', () => {
      showToast('Review request sent to your collaborators.');
    });
  }

  const viewAllReviewsBtn = document.getElementById('viewAllReviewsBtn');
  if (viewAllReviewsBtn) {
    viewAllReviewsBtn.addEventListener('click', () => {
      showToast('Opening full reviews view...');
    });
  }

  function showToast(message) {
    const stack = document.getElementById('profileToastStack');
    if (!stack) return;

    const toast = document.createElement('div');
    toast.className = 'profile-toast';
    toast.textContent = message;
    stack.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(-4px)';
      setTimeout(() => toast.remove(), 220);
    }, 1800);
  }
});
