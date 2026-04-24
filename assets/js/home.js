/* ============================================================
   HOME.JS — Hub page interactions
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  // --------------------------------------------------------
  // ELEMENT REFS
  // --------------------------------------------------------
  const bootstrap = window.homeDashboardBootstrap || {};
  const fabButton        = document.getElementById('homeFab');
  const fabMenu          = document.getElementById('fabMenu');
  const toastStack       = document.getElementById('homeToastStack');
  const feedList         = document.getElementById('feedList');
  const activityCard     = document.querySelector('.activity-highlight');
  const quickPublishBtn  = document.getElementById('quickPublishBtn');
  const openComposerBtn  = document.getElementById('openComposerBtn');
  const composerInput    = document.querySelector('.composer-input');
  const globalSearch     = document.querySelector('.home-global-search input');
  const liveNowWidgetList = document.getElementById('liveNowWidgetList');

  const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

  const formatNumber = (value) => {
    const parsed = Number(value || 0);
    if (!Number.isFinite(parsed)) {
      return '0';
    }
    try {
      return new Intl.NumberFormat().format(parsed);
    } catch (_error) {
      return String(Math.round(parsed));
    }
  };

  const renderFeedCard = (item) => {
    const type = String(item?.type || 'activity');
    const name = String(item?.name || 'Member');
    const role = String(item?.role || 'Activity');
    const avatar = String(item?.avatar || '');
    const badge = String(item?.badge || 'Update');
    const content = String(item?.content || '');
    const meta = String(item?.meta || '');
    const time = String(item?.time || '');
    const coverLabel = String(item?.cover_label || '');
    const coverStyle = String(item?.cover_style || 'height:240px;background:linear-gradient(135deg,#a5b4fc,#818cf8);');
    const tags = Array.isArray(item?.tags) ? item.tags : [];

    return [
      `<article class="feed-card glass-card fade-in-section home-feed-card home-feed-card--${escapeHtml(type)}">`,
      '<div class="feed-user-row">',
      `<img src="${escapeHtml(avatar)}" alt="${escapeHtml(name)}">`,
      '<div>',
      `<h4>${escapeHtml(name)} <span class="rep-pill">${escapeHtml(badge)}</span></h4>`,
      `<p>${escapeHtml(role)}${time ? ` · ${escapeHtml(time)}` : ''}</p>`,
      '</div>',
      '</div>',
      `<p class="feed-content">${escapeHtml(content)}</p>`,
      coverLabel ? `<div class="feed-cover" style="${escapeHtml(coverStyle)}display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;letter-spacing:1px;text-align:center;padding:1rem;min-height:240px;">${escapeHtml(coverLabel)}</div>` : '',
      tags.length ? `<div class="post-tags">${tags.map((tag) => `<span>${escapeHtml(tag)}</span>`).join('')}</div>` : '',
      `<div class="feed-meta"><span>${escapeHtml(meta)}</span><span>${escapeHtml(time || 'Just now')}</span><span>${escapeHtml(type.charAt(0).toUpperCase() + type.slice(1))}</span></div>`,
      '<div class="feed-actions">',
      '<button class="react-btn" type="button"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.514" /></svg> Like</button>',
      '<button class="comment-btn" type="button"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg> Comment</button>',
      '<button type="button"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" /></svg> Share</button>',
      '<button type="button"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" /></svg> Save</button>',
      '<button class="skill-boost" type="button"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" /></svg> Skill Boost</button>',
      '</div>',
      '</article>',
    ].join('');
  };

  const renderRecentActivityCard = (items) => {
    const list = Array.isArray(items) ? items : [];
    return [
      '<article class="activity-highlight glass-card fade-in-section">',
      '<h3>Recent Activity</h3>',
      '<ul>',
      list.map((item) => {
        const label = String(item?.label || 'Update');
        const text = String(item?.text || '');
        const time = String(item?.time || '');
        return `<li><strong>${escapeHtml(label)}</strong>${escapeHtml(text)}${time ? ` <span>${escapeHtml(time)}</span>` : ''}</li>`;
      }).join(''),
      '</ul>',
      '<a href="profile.php">See full profile activity</a>',
      '</article>',
    ].join('');
  };

  const renderHomeFeed = () => {
    if (!feedList) {
      return;
    }

    const items = Array.isArray(bootstrap.feedItems) ? bootstrap.feedItems : [];
    const feedMarkup = items.length
      ? items.map(renderFeedCard).join('')
      : [
          '<article class="feed-card glass-card fade-in-section">',
          '<div class="feed-user-row">',
          `<img src="${escapeHtml(bootstrap.avatarUrl || '')}" alt="${escapeHtml(bootstrap.displayName || 'Member')}">`,
          '<div>',
          `<h4>${escapeHtml(bootstrap.displayName || 'Member')} <span class="rep-pill">Ready</span></h4>`,
          '<p>Dashboard activity · Just now</p>',
          '</div>',
          '</div>',
          '<p class="feed-content">Your dashboard is ready. Start a project, share a story, or go live to populate the feed.</p>',
          '<div class="feed-cover" style="height:240px; background:linear-gradient(135deg, rgba(79,82,217,0.95), rgba(14,165,233,0.9)); border-radius:var(--radius-md); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:600; letter-spacing:1px; text-align:center; padding:1rem;">New activity will appear here</div>',
          '<div class="feed-meta"><span>Waiting for updates</span><span>Dashboard</span><span>Home</span></div>',
          '</article>',
        ].join('');

    feedList.innerHTML = feedMarkup + renderRecentActivityCard(bootstrap.recentActivityItems || []);
  };

  const animateHomeCounters = () => {
    document.querySelectorAll('[data-home-counter]').forEach((el) => {
      const target = Math.max(0, Number(el.getAttribute('data-count-target') || el.textContent || 0));
      if (!Number.isFinite(target)) {
        return;
      }

      const duration = 1100;
      const startedAt = performance.now();
      const tick = (now) => {
        const progress = Math.min((now - startedAt) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = formatNumber(Math.round(target * eased));
        if (progress < 1) {
          requestAnimationFrame(tick);
        }
      };

      requestAnimationFrame(tick);
    });
  };

  renderHomeFeed();
  animateHomeCounters();

  let feedFilters = document.querySelectorAll('.feed-filters button');
  let feedCards = document.querySelectorAll('.feed-card');
  const reactBtns = document.querySelectorAll('.react-btn, .skill-boost, .comment-btn');
  const connectBtns = document.querySelectorAll('.people-item button, .project-mini-card button');

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
  // LIVE NOW WIDGET + FEED BADGES
  // --------------------------------------------------------
  const liveApiUrl = 'profile.php?action=profile_live_stream&mode=list';

  function getHostName(stream) {
    const host = stream?.host || {};
    const first = String(host.first_name || '').trim();
    const last = String(host.last_name || '').trim();
    return `${first} ${last}`.trim() || 'Member';
  }

  function renderLiveWidget(streams) {
    if (!liveNowWidgetList) return;
    const list = Array.isArray(streams) ? streams : [];

    if (!list.length) {
      liveNowWidgetList.innerHTML = '<div class="live-now-empty">No one is live right now.</div>';
      return;
    }

    liveNowWidgetList.innerHTML = list.slice(0, 5).map((stream) => {
      const id = Number(stream?.id || 0);
      const hostName = getHostName(stream);
      const title = String(stream?.title || '').trim() || `${hostName}'s stream`;
      const viewers = Math.max(0, Number(stream?.viewer_count || 0));
      const visibility = String(stream?.visibility || 'public').trim() || 'public';
      return [
        '<article class="live-now-item">',
        `<strong><span class="live-now-dot"></span>${title}</strong>`,
        `<p>${hostName} · ${viewers} viewer${viewers === 1 ? '' : 's'} · ${visibility}</p>`,
        `<button type="button" data-live-stream-id="${id}">Join Live</button>`,
        '</article>'
      ].join('');
    }).join('');

    liveNowWidgetList.querySelectorAll('[data-live-stream-id]').forEach((buttonEl) => {
      buttonEl.addEventListener('click', () => {
        const streamId = Number(buttonEl.getAttribute('data-live-stream-id') || 0);
        if (!streamId) return;
        window.location.href = `live.php?stream=${streamId}&mode=watch`;
      });
    });
  }

  function applyFeedLiveBadges(streams) {
    const liveByHost = new Set(
      (Array.isArray(streams) ? streams : [])
        .map((stream) => getHostName(stream).toLowerCase())
        .filter(Boolean)
    );

    document.querySelectorAll('.feed-user-row h4').forEach((heading) => {
      heading.querySelectorAll('.feed-live-badge').forEach((badge) => badge.remove());

      if (!heading.dataset.baseName) {
        const titleNode = heading.childNodes[0];
        heading.dataset.baseName = String(titleNode?.textContent || heading.textContent || '').trim();
      }

      const baseName = String(heading.dataset.baseName || '').trim().toLowerCase();
      if (!baseName) return;

      let isLive = false;
      liveByHost.forEach((hostName) => {
        if (isLive) return;
        if (baseName.includes(hostName) || hostName.includes(baseName)) {
          isLive = true;
        }
      });

      if (!isLive) return;
      const badge = document.createElement('span');
      badge.className = 'feed-live-badge';
      badge.textContent = 'LIVE';
      heading.appendChild(badge);
    });
  }

  async function refreshLiveSurface() {
    try {
      const response = await fetch(liveApiUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload?.success) {
        throw new Error(payload?.message || 'Could not load live streams.');
      }
      const streams = Array.isArray(payload?.streams) ? payload.streams : [];
      renderLiveWidget(streams);
      applyFeedLiveBadges(streams);
    } catch (_error) {
      renderLiveWidget([]);
      applyFeedLiveBadges([]);
    }
  }

  refreshLiveSurface();
  window.setInterval(refreshLiveSurface, 20000);

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
