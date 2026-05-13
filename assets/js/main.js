/* ============================================================
   MAIN.JS — Shared engine: background, navbar, scroll, 3D, theme
   ============================================================ */

// ============================================================
// ANIMATED GRADIENT MESH BACKGROUND
// ============================================================
class GradientMesh {
  constructor(canvasId) {
    this.canvas = document.getElementById(canvasId);
    if (!this.canvas) return;
    this.ctx = this.canvas.getContext('2d');
    this.blobs = [];
    this.raf = null;
    this.resize();
    this.initBlobs();
    window.addEventListener('resize', () => this.resize());
    this.animate();
  }

  resize() {
    this.canvas.width  = window.innerWidth;
    this.canvas.height = window.innerHeight;
  }

  initBlobs() {
    // Light-mode: softer, desaturated colors
    const colors = [
      { r: 79,  g: 82,  b: 217 },  // accent indigo
      { r: 14,  g: 165, b: 233 },  // accent sky
      { r: 124, g: 58,  b: 237 },  // purple
      { r: 5,   g: 150, b: 105 },  // emerald
    ];
    for (let i = 0; i < 5; i++) {
      const c = colors[i % colors.length];
      this.blobs.push({
        x:      Math.random() * this.canvas.width,
        y:      Math.random() * this.canvas.height,
        radius: 180 + Math.random() * 280,
        color:  c,
        vx:     (Math.random() - 0.5) * 0.4,
        vy:     (Math.random() - 0.5) * 0.4,
        phase:  Math.random() * Math.PI * 2,
      });
    }
  }

  animate() {
    const { ctx, canvas } = this;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const time = Date.now() * 0.0008;

    this.blobs.forEach(b => {
      b.x += b.vx + Math.sin(time + b.phase) * 0.25;
      b.y += b.vy + Math.cos(time + b.phase) * 0.25;
      if (b.x < -b.radius)                 b.x = canvas.width + b.radius;
      if (b.x > canvas.width + b.radius)   b.x = -b.radius;
      if (b.y < -b.radius)                 b.y = canvas.height + b.radius;
      if (b.y > canvas.height + b.radius)  b.y = -b.radius;

      const g = ctx.createRadialGradient(b.x, b.y, 0, b.x, b.y, b.radius);
      g.addColorStop(0, `rgba(${b.color.r},${b.color.g},${b.color.b},0.06)`);
      g.addColorStop(1, `rgba(${b.color.r},${b.color.g},${b.color.b},0)`);
      ctx.fillStyle = g;
      ctx.fillRect(0, 0, canvas.width, canvas.height);
    });

    this.raf = requestAnimationFrame(() => this.animate());
  }
}

// ============================================================
// INTERSECTION OBSERVER — SCROLL ANIMATIONS
// ============================================================
function initScrollAnimations() {
  const observer = new IntersectionObserver(
    entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          // Once visible, stop observing to save resources
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.08, rootMargin: '0px 0px -40px 0px' }
  );
  document.querySelectorAll('.fade-in-section').forEach(el => observer.observe(el));
}

// ============================================================
// NAVBAR
// ============================================================
function initNavbar() {
  const navbar = document.querySelector('.navbar');
  if (!navbar) return;

  // Scroll class
  const handleScroll = () => {
    navbar.classList.toggle('scrolled', window.scrollY > 40);
  };
  window.addEventListener('scroll', handleScroll, { passive: true });
  handleScroll();

  // Mobile toggle
  const toggle = document.querySelector('.nav-toggle');
  const nav    = document.querySelector('.navbar-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      const open = nav.classList.toggle('open');
      toggle.classList.toggle('active', open);
      toggle.setAttribute('aria-expanded', open);
    });
    nav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        nav.classList.remove('open');
        toggle.classList.remove('active');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  // Profile dropdown
  const profile = document.querySelector('.nav-profile');
  if (profile) {
    profile.querySelector('.nav-profile-btn')?.addEventListener('click', e => {
      e.stopPropagation();
      profile.classList.toggle('active');
    });
    document.addEventListener('click', e => {
      if (!profile.contains(e.target)) profile.classList.remove('active');
    });
  }

  // Active link highlight
  const page = window.location.pathname.split('/').pop() || 'index.html';
  navbar.querySelectorAll('.navbar-nav a').forEach(link => {
    const href = link.getAttribute('href')?.split('/').pop() || '';
    if (href === page || (page === '' && href === 'index.html')) {
      link.classList.add('active');
    }
  });
}

// ============================================================
// THEME
// ============================================================
function initTheme() {
  const saved = localStorage.getItem('diversity-theme') || 'light';
  document.documentElement.setAttribute('data-theme', saved);
}

function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme') || 'light';
  const next    = current === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('diversity-theme', next);
}

// ============================================================
// MOUSE PROXIMITY GLOW + DEPTH ORB PARALLAX
// ============================================================
function initMouseEffects() {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const glow = document.createElement('div');
  glow.setAttribute('aria-hidden', 'true');
  Object.assign(glow.style, {
    position:      'fixed',
    width:         '600px',
    height:        '600px',
    borderRadius:  '50%',
    background:    'radial-gradient(circle, rgba(79,82,217,0.07) 0%, rgba(14,165,233,0.03) 40%, transparent 70%)',
    pointerEvents: 'none',
    zIndex:        '-1',
    transform:     'translate(-50%, -50%)',
    transition:    'left 0.55s cubic-bezier(0.25,0.46,0.45,0.94), top 0.55s cubic-bezier(0.25,0.46,0.45,0.94), opacity 0.4s ease',
    opacity:       '0',
    filter:        'blur(18px)',
  });
  document.body.appendChild(glow);

  const depthOrbs = document.querySelectorAll('.depth-orb');

  document.addEventListener('mousemove', e => {
    glow.style.left    = e.clientX + 'px';
    glow.style.top     = e.clientY + 'px';
    glow.style.opacity = '1';

    const mx = e.clientX / window.innerWidth;
    const my = e.clientY / window.innerHeight;
    depthOrbs.forEach((orb, i) => {
      const s  = 18 + i * 10;
      const px = (0.5 - mx) * s;
      const py = (0.5 - my) * s;
      orb.style.transform = `translate3d(${px.toFixed(1)}px, ${py.toFixed(1)}px, 0)`;
    });
  }, { passive: true });

  document.addEventListener('mouseleave', () => {
    glow.style.opacity = '0';
    depthOrbs.forEach(orb => { orb.style.transform = 'translate3d(0,0,0)'; });
  });
}

// ============================================================
// 3D CARD TILT ON HOVER
// ============================================================
function initCard3DEffects() {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const cards = document.querySelectorAll('.glass-card:not(.panel):not(.no-tilt), .dash-card');

  cards.forEach(card => {
    card.addEventListener('mousemove', function(e) {
      const r  = this.getBoundingClientRect();
      const x  = (e.clientX - r.left)  / r.width  - 0.5;
      const y  = (e.clientY - r.top)   / r.height - 0.5;
      this.style.transform  = `perspective(900px) rotateX(${(y * -5).toFixed(1)}deg) rotateY(${(x * 5).toFixed(1)}deg) translateY(-3px)`;
      this.style.transition = 'transform 0.08s ease';
    });
    card.addEventListener('mouseleave', function() {
      this.style.transform  = '';
      this.style.transition = 'transform 0.45s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.35s ease, background 0.35s ease, border-color 0.35s ease';
    });
  });
}

// ============================================================
// PRO MAX BACKGROUND LAYER
// ============================================================
function initProMaxBackground() {
  if (!document.body.classList.contains('grid-dot-bg')) return;
  if (document.querySelector('.pro-max-bg')) return;

  const container = document.createElement('div');
  container.className = 'pro-max-bg';
  container.setAttribute('aria-hidden', 'true');
  container.innerHTML = `
    <div class="pro-max-bg-layer pro-max-bg-grid"></div>
    <div class="pro-max-bg-layer pro-max-bg-glow"></div>
  `;

  const count = window.matchMedia('(max-width: 768px)').matches ? 8 : 14;
  for (let i = 0; i < count; i++) {
    const p = document.createElement('span');
    p.className = 'pro-max-bg-particle';
    p.style.cssText = `
      left: ${Math.random() * 100}%;
      top: ${Math.random() * 100}%;
      animation-delay: ${(Math.random() * 5).toFixed(2)}s;
      animation-duration: ${(8 + Math.random() * 8).toFixed(2)}s;
    `;
    container.appendChild(p);
  }
  document.body.prepend(container);

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const layers = container.querySelectorAll('.pro-max-bg-layer');
  document.addEventListener('mousemove', e => {
    const x = (e.clientX / window.innerWidth  - 0.5) * 2;
    const y = (e.clientY / window.innerHeight - 0.5) * 2;
    layers.forEach((layer, idx) => {
      const s = (idx + 1) * 6;
      layer.style.transform = `translate3d(${(x * s).toFixed(1)}px, ${(y * s).toFixed(1)}px, 0)`;
    });
  }, { passive: true });
}

// ============================================================
// FLOATING PRISM PARTICLES
// ============================================================
function inject3DDecorations() {
  // Depth orbs
  ['depth-orb--1', 'depth-orb--2', 'depth-orb--3'].forEach(cls => {
    const orb = document.createElement('div');
    orb.className = `depth-orb ${cls}`;
    orb.setAttribute('aria-hidden', 'true');
    document.body.appendChild(orb);
  });

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const canvas = document.createElement('div');
  canvas.className = 'prism-canvas';
  canvas.setAttribute('aria-hidden', 'true');
  document.body.prepend(canvas);

  const shapes = ['triangle', 'hex', 'square', 'dot', 'ring'];
  const count  = window.matchMedia('(max-width: 768px)').matches ? 10 : 20;

  for (let i = 0; i < count; i++) {
    const shape = shapes[i % shapes.length];
    const p     = document.createElement('div');
    p.className = `prism-particle prism-particle--${shape}`;
    const x       = `${(Math.random() * 100).toFixed(1)}vw`;
    const drift   = `${((Math.random() - 0.5) * 80).toFixed(0)}px`;
    const rotate  = `${(Math.random() * 720 - 360).toFixed(0)}deg`;
    const opacity = (0.1 + Math.random() * 0.2).toFixed(2);
    const dur     = (20 + Math.random() * 28).toFixed(1);
    const delay   = (Math.random() * 22).toFixed(1);
    p.style.cssText = `
      --prism-x: ${x};
      --prism-drift: ${drift};
      --prism-rotate: ${rotate};
      --prism-opacity: ${opacity};
      left: ${x};
      animation-duration: ${dur}s;
      animation-delay: -${delay}s;
    `;
    canvas.appendChild(p);
  }
}

// ============================================================
// INLINE SVG GRADIENT DEFS (icon colorisation)
// ============================================================
function injectSvgGradients() {
  const ns  = 'http://www.w3.org/2000/svg';
  const svg = document.createElementNS(ns, 'svg');
  svg.setAttribute('aria-hidden', 'true');
  Object.assign(svg.style, { position: 'absolute', width: '0', height: '0', overflow: 'hidden' });
  svg.innerHTML = `
    <defs>
      <linearGradient id="grad-primary" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%"   stop-color="#4f52d9"/>
        <stop offset="100%" stop-color="#0ea5e9"/>
      </linearGradient>
      <linearGradient id="grad-success" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%"   stop-color="#059669"/>
        <stop offset="100%" stop-color="#10b981"/>
      </linearGradient>
      <linearGradient id="grad-warning" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%"   stop-color="#d97706"/>
        <stop offset="100%" stop-color="#f59e0b"/>
      </linearGradient>
      <linearGradient id="grad-danger" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%"   stop-color="#e11d48"/>
        <stop offset="100%" stop-color="#f43f5e"/>
      </linearGradient>
      <linearGradient id="grad-purple" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%"   stop-color="#7c3aed"/>
        <stop offset="100%" stop-color="#c084fc"/>
      </linearGradient>
    </defs>
  `;
  document.body.prepend(svg);
}

// ============================================================
// PAGE POLISH — remove emoji chips, apply pro-premium-btn
// ============================================================
function initProMaxPagePolish() {
  document.body.classList.add('pro-enhanced');

  // Apply shimmer to all buttons except icon-only
  document.querySelectorAll('button.btn, a.btn, .dash-primary-btn').forEach(node => {
    node.classList.add('pro-premium-btn');
  });

  // Mark cards for fade-in
  document.querySelectorAll('.glass-card, .widget-card, .feed-card, .dash-card').forEach(node => {
    if (!node.classList.contains('fade-in-section')) {
      node.classList.add('fade-in-section');
    }
  });
}

// ============================================================
// COUNTER ANIMATION
// ============================================================
function animateCounter(el, target, duration = 1800) {
  let start     = 0;
  const t0      = performance.now();
  const tick = (now) => {
    const progress = Math.min((now - t0) / duration, 1);
    const val      = Math.floor((1 - Math.pow(1 - progress, 3)) * target);
    el.textContent = val.toLocaleString();
    if (progress < 1) requestAnimationFrame(tick);
    else el.textContent = target.toLocaleString();
  };
  requestAnimationFrame(tick);
}

// ============================================================
// INIT
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
  initTheme();
  new GradientMesh('gradient-canvas');
  initProMaxBackground();
  initProMaxPagePolish();
  initScrollAnimations();
  initNavbar();
  inject3DDecorations();
  injectSvgGradients();
  initMouseEffects();
  initCard3DEffects();
  initGlobalWakeWord();

  // Theme toggle buttons
  document.querySelectorAll('.theme-toggle').forEach(btn => {
    btn.addEventListener('click', toggleTheme);
  });

  // Lucide icons (CDN fallback only if loaded)
  if (typeof lucide !== 'undefined') {
    try {
      lucide.createIcons({ attrs: { 'stroke-width': 1.75, class: 'lucide' } });
    } catch (e) { /* ignore */ }
  }
});

// ============================================================
// GLOBAL WAKE WORD — "Hey Bro" (works on ALL pages)
// Redirects to ai-agent.php?autostart=1 when triggered.
// On the AI Agent page itself, ai-agent.js takes over instead.
// ============================================================
function initGlobalWakeWord() {
  // Skip on AI Agent page — ai-agent.js manages its own monitor
  if (document.body.classList.contains('ai-agent-page')) return;

  const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition || null;
  if (!SpeechRec) return;

  const voiceEnabled = localStorage.getItem('div-ai-voice-enabled') !== '0';
  if (!voiceEnabled) return;

  const WAKE_WORD = 'hey bro';
  let monitor = null;
  let triggered = false;

  const startMonitor = () => {
    if (monitor || triggered) return;
    const rec = new SpeechRec();
    rec.lang           = 'en-US';
    rec.continuous     = true;
    rec.interimResults = true;
    rec.maxAlternatives = 1;

    rec.onresult = (event) => {
      if (triggered) return;
      const idx    = event.resultIndex;
      const result = event.results[idx];
      if (!result) return;
      const text = String(result[0]?.transcript || '').toLowerCase().trim();

      if (text.includes(WAKE_WORD)) {
        triggered = true;
        try { rec.onend = null; rec.abort(); } catch {}
        monitor = null;

        // Brief TTS acknowledgement
        if (typeof window.speechSynthesis !== 'undefined') {
          const utt = new SpeechSynthesisUtterance("Hey, I'm listening!");
          utt.lang = 'en-US';
          utt.rate = 1.05;
          window.speechSynthesis.speak(utt);
        }

        // Determine path depth to ai-agent.php
        const path = window.location.pathname;
        const depth = (path.match(/\//g) || []).length;
        const relPath = depth <= 2
          ? 'Views/FrontOffice/ai-agent.php?autostart=1'
          : 'ai-agent.php?autostart=1';

        setTimeout(() => {
          window.location.href = relPath;
        }, 500);
      }
    };

    rec.onend = () => {
      monitor = null;
      if (!triggered) setTimeout(startMonitor, 1500);
    };

    rec.onerror = (e) => {
      monitor = null;
      const errType = String(e?.error || '');
      if (['not-allowed', 'service-not-allowed', 'audio-capture'].includes(errType)) return;
      if (!triggered) setTimeout(startMonitor, 3000);
    };

    monitor = rec;
    try { rec.start(); } catch { monitor = null; }
  };

  // Small delay to let the page settle before opening mic
  setTimeout(startMonitor, 1200);
}
