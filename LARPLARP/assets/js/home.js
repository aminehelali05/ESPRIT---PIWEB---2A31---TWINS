/* ============================================================
   HOME.JS — Hub page interactions
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  // --------------------------------------------------------
  // ELEMENT REFS
  // --------------------------------------------------------
  const fabButton        = document.getElementById('homeFab');
  const fabMenu          = document.getElementById('fabMenu');
  const toastStack       = document.getElementById('homeToastStack');
  const feedFilters      = document.querySelectorAll('.feed-filters button');
  const feedCards        = document.querySelectorAll('.feed-card');
  const reactBtns        = document.querySelectorAll('.react-btn, .skill-boost, .comment-btn');
  const connectBtns      = document.querySelectorAll('.people-item button, .project-mini-card button');
  const quickPublishBtn  = document.getElementById('quickPublishBtn');
  const openComposerBtn  = document.getElementById('openComposerBtn');
  const composerInput    = document.querySelector('.composer-input');
  const globalSearch     = document.querySelector('.home-global-search input');

  // --------------------------------------------------------
  // FAB — floating action button
  // --------------------------------------------------------
  if (fabButton && fabMenu) {
    fabButton.addEventListener('click', e => {
      e.stopPropagation();
      const isOpen = fabMenu.classList.toggle('open');
      fabButton.setAttribute('aria-expanded', isOpen);
      // Rotate icon
      fabButton.style.transform = isOpen
        ? 'scale(1.08) rotate(45deg)'
        : 'scale(1) rotate(0deg)';
    });

    document.addEventListener('click', e => {
      if (!fabMenu.contains(e.target) && !fabButton.contains(e.target)) {
        fabMenu.classList.remove('open');
        fabButton.style.transform = '';
        fabButton.setAttribute('aria-expanded', 'false');
      }
    });
  }

  // --------------------------------------------------------
  // FEED FILTER TABS — animated card stagger
  // --------------------------------------------------------
  feedFilters.forEach(btn => {
    btn.addEventListener('click', () => {
      feedFilters.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      // Stagger feed cards off then back
      feedCards.forEach((card, i) => {
        card.style.transition = `opacity 0.2s ease ${i * 35}ms, transform 0.2s ease ${i * 35}ms`;
        card.style.opacity    = '0';
        card.style.transform  = 'translateY(10px)';
        setTimeout(() => {
          card.style.opacity   = '1';
          card.style.transform = '';
        }, 200 + i * 45);
      });

      showToast(`Showing: ${btn.textContent.trim()}`, 'success');
    });
  });

  // --------------------------------------------------------
  // REACT BUTTONS
  // --------------------------------------------------------
  reactBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const isBoost = btn.classList.contains('skill-boost');
      const label   = btn.querySelector('span') || btn;
      const count   = parseInt(label.textContent.replace(/\D/g, '')) || 0;

      if (!btn.classList.contains('reacted')) {
        btn.classList.add('reacted');
        label.textContent = isBoost
          ? `Boost (${count + 1})`
          : `(${count + 1})`;
        showToast(
          isBoost ? 'Skill Boost sent · +15 XP gained' : 'Engagement noted · +10 REP',
          'success'
        );
      }
    });
  });

  // --------------------------------------------------------
  // CONNECT BUTTONS
  // --------------------------------------------------------
  connectBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      if (btn.dataset.connected) return;
      btn.dataset.connected = '1';
      btn.textContent       = '✓ Sent';
      btn.disabled          = true;
      btn.style.opacity     = '0.65';
      showToast('Connection request sent', 'success');
    });
  });

  // --------------------------------------------------------
  // QUICK PUBLISH
  // --------------------------------------------------------
  if (quickPublishBtn) {
    quickPublishBtn.addEventListener('click', () => {
      showToast('Post published to your network', 'success');
      pulseFeed();
    });
  }

  // --------------------------------------------------------
  // OPEN COMPOSER
  // --------------------------------------------------------
  if (openComposerBtn) {
    openComposerBtn.addEventListener('click', () => {
      composerInput?.focus();
      showToast('Composer ready', 'success');
    });
  }

  // --------------------------------------------------------
  // COMPOSER INPUT — focus expand
  // --------------------------------------------------------
  if (composerInput) {
    composerInput.addEventListener('focus', () => {
      const card = composerInput.closest('.composer-card');
      if (card) card.style.boxShadow = 'var(--shadow-card-hover), 0 0 0 2px rgba(var(--color-accent-rgb), 0.15)';
    });
    composerInput.addEventListener('blur', () => {
      const card = composerInput.closest('.composer-card');
      if (card) card.style.boxShadow = '';
    });
  }

  // --------------------------------------------------------
  // GLOBAL SEARCH — keyboard shortcut (/)
  // --------------------------------------------------------
  if (globalSearch) {
    document.addEventListener('keydown', e => {
      if (e.key === '/' && document.activeElement.tagName !== 'INPUT'
          && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        globalSearch.focus();
      }
      if (e.key === 'Escape' && document.activeElement === globalSearch) {
        globalSearch.blur();
      }
    });
  }

  // --------------------------------------------------------
  // LIKE COUNTERS — animate on click
  // --------------------------------------------------------
  document.querySelectorAll('.post-action-btn[data-action="like"], .feed-actions .react-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      this.classList.toggle('liked');
      const icon = this.querySelector('svg');
      if (icon) icon.style.transform = 'scale(1.4)';
      setTimeout(() => { if (icon) icon.style.transform = ''; }, 250);
    });
  });

  // --------------------------------------------------------
  // COUNTER ANIMATIONS (reputation ring etc.)
  // --------------------------------------------------------
  const repRing = document.querySelector('.reputation-ring');
  if (repRing) {
    const progress = parseInt(repRing.style.getPropertyValue('--progress') || '0');
    let current    = 0;
    const dur      = 1600;
    const t0       = performance.now();
    const tick     = now => {
      const p = Math.min((now - t0) / dur, 1);
      const v = Math.floor((1 - Math.pow(1 - p, 3)) * progress);
      repRing.style.setProperty('--progress', v);
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  }

  // --------------------------------------------------------
  // PULSE FEED helper
  // --------------------------------------------------------
  function pulseFeed() {
    feedCards.forEach((card, i) => {
      setTimeout(() => {
        card.style.transition = 'transform 0.15s ease';
        card.style.transform  = 'translateY(-4px)';
        setTimeout(() => { card.style.transform = ''; }, 160);
      }, i * 60);
    });
  }

  // --------------------------------------------------------
  // TOAST STACK
  // --------------------------------------------------------
  function showToast(message, type = 'success') {
    if (!toastStack) return;

    const toast = document.createElement('div');
    toast.className = `home-toast ${type}`;

    // Icon prefix
    const icons = { success: '✓', error: '✕', info: 'ℹ' };
    toast.innerHTML = `<span style="font-weight:700;margin-right:5px">${icons[type] || ''}</span>${message}`;

    toastStack.appendChild(toast);

    // Animate in
    requestAnimationFrame(() => {
      toast.style.opacity   = '1';
      toast.style.transform = 'translateY(0)';
    });

    // Auto-dismiss after 3.5s
    setTimeout(() => {
      toast.style.opacity   = '0';
      toast.style.transform = 'translateY(-8px)';
      setTimeout(() => toast.remove(), 280);
    }, 3500);
  }
});
