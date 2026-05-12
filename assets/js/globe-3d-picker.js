window.Globe3DPicker = (function () {
  let overlayEl = null;
  let iframeEl = null;
  let pendingCallback = null;

  function buildModalShell() {
    if (overlayEl) return;

    overlayEl = document.createElement('div');
    overlayEl.id = 'globe3dPickerOverlay';
    overlayEl.style.position = 'fixed';
    overlayEl.style.inset = '0';
    overlayEl.style.zIndex = '100000';
    overlayEl.style.display = 'none';
    overlayEl.style.alignItems = 'center';
    overlayEl.style.justifyContent = 'center';
    overlayEl.style.background = 'rgba(15, 23, 42, 0.72)';
    overlayEl.style.backdropFilter = 'blur(6px)';
    overlayEl.style.padding = '20px';

    const card = document.createElement('div');
    card.style.width = 'min(1320px, 98vw)';
    card.style.height = 'min(860px, 94vh)';
    card.style.borderRadius = '14px';
    card.style.overflow = 'hidden';
    card.style.background = '#0b1220';
    card.style.boxShadow = '0 24px 56px rgba(2, 6, 23, 0.55)';
    card.style.border = '1px solid rgba(148, 163, 184, 0.35)';
    card.style.display = 'flex';
    card.style.flexDirection = 'column';

    const topBar = document.createElement('div');
    topBar.style.height = '44px';
    topBar.style.display = 'flex';
    topBar.style.alignItems = 'center';
    topBar.style.justifyContent = 'space-between';
    topBar.style.padding = '0 12px';
    topBar.style.background = 'linear-gradient(135deg, #111827, #0f172a)';
    topBar.style.borderBottom = '1px solid rgba(148, 163, 184, 0.25)';

    const title = document.createElement('span');
    title.textContent = '3D Globe Explorer';
    title.style.color = '#e2e8f0';
    title.style.fontSize = '13px';
    title.style.fontWeight = '600';

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.textContent = 'Close';
    closeBtn.style.height = '30px';
    closeBtn.style.padding = '0 12px';
    closeBtn.style.borderRadius = '8px';
    closeBtn.style.border = '1px solid rgba(148, 163, 184, 0.35)';
    closeBtn.style.background = 'rgba(30, 41, 59, 0.9)';
    closeBtn.style.color = '#e2e8f0';
    closeBtn.style.cursor = 'pointer';
    closeBtn.addEventListener('click', close);

    iframeEl = document.createElement('iframe');
    iframeEl.title = '3D Globe Picker';
    iframeEl.style.width = '100%';
    iframeEl.style.height = '100%';
    iframeEl.style.border = '0';
    iframeEl.style.display = 'block';
    iframeEl.allow = 'geolocation';

    topBar.appendChild(title);
    topBar.appendChild(closeBtn);
    card.appendChild(topBar);
    card.appendChild(iframeEl);
    overlayEl.appendChild(card);
    document.body.appendChild(overlayEl);

    overlayEl.addEventListener('click', (event) => {
      if (event.target === overlayEl) {
        close();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && overlayEl && overlayEl.style.display !== 'none') {
        close();
      }
    });
  }

  function open(options = {}) {
    const pickerUrl = options.url || '../../assets/globale_explore/index.html?picker=1&embed=1';

    pendingCallback = typeof options.onPick === 'function' ? options.onPick : null;

    buildModalShell();
    if (!overlayEl || !iframeEl) return false;

    iframeEl.src = pickerUrl;
    overlayEl.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    return true;
  }

  function close() {
    if (overlayEl) {
      overlayEl.style.display = 'none';
    }
    if (iframeEl) {
      iframeEl.src = 'about:blank';
    }
    document.body.style.overflow = '';
  }

  window.addEventListener('message', (event) => {
    const data = event.data || {};
    if (data.type !== 'globale-explore-select') {
      return;
    }

    const payload = data.payload || {};
    if (pendingCallback) {
      pendingCallback(payload);
    }
    close();
  });

  return {
    open,
    close
  };
})();
