/* ============================================================
   MAIN.JS — Shared utilities: gradient mesh, navbar, scroll
   ============================================================ */

// --- Animated Gradient Mesh Background ---
class GradientMesh {
  constructor(canvasId) {
    this.canvas = document.getElementById(canvasId);
    if (!this.canvas) return;
    this.ctx = this.canvas.getContext('2d');
    this.blobs = [];
    this.resize();
    this.initBlobs();
    window.addEventListener('resize', () => this.resize());
    this.animate();
  }

  resize() {
    this.canvas.width = window.innerWidth;
    this.canvas.height = window.innerHeight;
  }

  initBlobs() {
    const colors = [
      { r: 99, g: 102, b: 241 },   // Accent
      { r: 34, g: 211, b: 238 },   // Accent secondary
      { r: 168, g: 85, b: 247 },   // Purple
      { r: 59, g: 130, b: 246 },   // Blue
    ];
    
    for (let i = 0; i < 5; i++) {
      const color = colors[i % colors.length];
      this.blobs.push({
        x: Math.random() * this.canvas.width,
        y: Math.random() * this.canvas.height,
        radius: 200 + Math.random() * 300,
        color,
        vx: (Math.random() - 0.5) * 0.5,
        vy: (Math.random() - 0.5) * 0.5,
        phase: Math.random() * Math.PI * 2,
      });
    }
  }

  animate() {
    const { ctx, canvas } = this;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    const time = Date.now() * 0.001;
    
    this.blobs.forEach(blob => {
      blob.x += blob.vx + Math.sin(time + blob.phase) * 0.3;
      blob.y += blob.vy + Math.cos(time + blob.phase) * 0.3;
      
      // Bounce at edges
      if (blob.x < -blob.radius) blob.x = canvas.width + blob.radius;
      if (blob.x > canvas.width + blob.radius) blob.x = -blob.radius;
      if (blob.y < -blob.radius) blob.y = canvas.height + blob.radius;
      if (blob.y > canvas.height + blob.radius) blob.y = -blob.radius;
      
      const gradient = ctx.createRadialGradient(
        blob.x, blob.y, 0,
        blob.x, blob.y, blob.radius
      );
      gradient.addColorStop(0, `rgba(${blob.color.r}, ${blob.color.g}, ${blob.color.b}, 0.15)`);
      gradient.addColorStop(1, `rgba(${blob.color.r}, ${blob.color.g}, ${blob.color.b}, 0)`);
      
      ctx.fillStyle = gradient;
      ctx.fillRect(0, 0, canvas.width, canvas.height);
    });
    
    requestAnimationFrame(() => this.animate());
  }
}

// --- Intersection Observer for Scroll Animations ---
function initScrollAnimations() {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
      }
    });
  }, {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  });

  document.querySelectorAll('.fade-in-section').forEach(el => {
    observer.observe(el);
  });
}

// --- Navbar Scroll Effect ---
function initNavbar() {
  const navbar = document.querySelector('.navbar');
  if (!navbar) return;

  window.addEventListener('scroll', () => {
    if (window.scrollY > 50) {
      navbar.classList.add('scrolled');
    } else {
      navbar.classList.remove('scrolled');
    }
  });

  // Mobile toggle
  const toggle = document.querySelector('.nav-toggle');
  const nav = document.querySelector('.navbar-nav');
  
  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      nav.classList.toggle('open');
      toggle.classList.toggle('active');
    });

    // Close on nav link click
    nav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        nav.classList.remove('open');
        toggle.classList.remove('active');
      });
    });
  }

  // Set active link
  const currentPage = window.location.pathname.split('/').pop() || 'index.html';
  navbar.querySelectorAll('.navbar-nav a').forEach(link => {
    const href = link.getAttribute('href').split('/').pop();
    if (href === currentPage || (currentPage === '' && href === 'index.html')) {
      link.classList.add('active');
    }
  });

  // Init Profile Dropdown
  const profileDropdown = document.querySelector('.nav-profile');
  if (profileDropdown) {
    const profileBtn = profileDropdown.querySelector('.nav-profile-btn');
    profileBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      profileDropdown.classList.toggle('active');
    });

    // Close dropdown on outside click
    document.addEventListener('click', (e) => {
      if (!profileDropdown.contains(e.target)) {
        profileDropdown.classList.remove('active');
      }
    });
  }
}

// --- Parallax on Scroll ---
function initParallax() {
  const parallaxElements = document.querySelectorAll('[data-parallax]');
  
  window.addEventListener('scroll', () => {
    const scrollY = window.scrollY;
    parallaxElements.forEach(el => {
      const speed = parseFloat(el.dataset.parallax);
      el.style.transform = `translateY(${scrollY * speed}px)`;
    });
  });
}

// --- Smooth Page Loader ---
function initPageLoader() {
  document.body.classList.add('loaded');
}

// --- Counter Animation ---
function animateCounter(el, target, duration = 2000) {
  let start = 0;
  const startTime = performance.now();
  
  function update(currentTime) {
    const elapsed = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    const current = Math.floor(eased * target);
    
    el.textContent = current;
    
    if (progress < 1) {
      requestAnimationFrame(update);
    } else {
      el.textContent = target;
    }
  }
  
  requestAnimationFrame(update);
}

// --- Theme Toggle ---
function initTheme() {
  const saved = localStorage.getItem('diversity-theme') || localStorage.getItem('collabflow-theme') || 'dark';
  document.documentElement.setAttribute('data-theme', saved);
}

function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme') || 'dark';
  const next = current === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('diversity-theme', next);
}

// --- 3D Decorative Elements Injector ---
// --- 3D Decorative Elements Injector & Parallax ---
function inject3DDecorations() {
  // We wrap elements in a .parallax-wrapper so we can move them with JS
  // while their CSS keyframe animations run on the inner element.

  // Floating sparkle orbs
  for (let i = 0; i < 4; i++) {
    const wrap = document.createElement('div');
    wrap.className = 'parallax-wrapper';
    wrap.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; pointer-events:none; z-index:-1; transition: transform 0.2s cubic-bezier(0.25, 0.46, 0.45, 0.94);';
    wrap.dataset.speed = String((i + 1) * 15);
    
    const orb = document.createElement('div');
    orb.className = 'orb-decoration';
    wrap.appendChild(orb);
    document.body.appendChild(wrap);
  }

  // 3D rotating rings
  const ring1Wrap = document.createElement('div');
  ring1Wrap.className = 'parallax-wrapper';
  ring1Wrap.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; pointer-events:none; z-index:-1; transition: transform 0.2s cubic-bezier(0.25, 0.46, 0.45, 0.94);';
  ring1Wrap.dataset.speed = "20";

  const ring1 = document.createElement('div');
  ring1.className = 'ring-3d ring-3d-1';
  ring1Wrap.appendChild(ring1);
  document.body.appendChild(ring1Wrap);

  const ring2Wrap = document.createElement('div');
  ring2Wrap.className = 'parallax-wrapper';
  ring2Wrap.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; pointer-events:none; z-index:-1; transition: transform 0.2s cubic-bezier(0.25, 0.46, 0.45, 0.94);';
  ring2Wrap.dataset.speed = "35";

  const ring2 = document.createElement('div');
  ring2.className = 'ring-3d ring-3d-2';
  ring2Wrap.appendChild(ring2);
  document.body.appendChild(ring2Wrap);
}

// --- Global SVG Gradients for Colorful Icons ---
function injectSvgGradients() {
  const svgNS = "http://www.w3.org/2000/svg";
  const svg = document.createElementNS(svgNS, "svg");
  svg.setAttribute("aria-hidden", "true");
  svg.style.position = "absolute";
  svg.style.width = "0";
  svg.style.height = "0";
  svg.style.overflow = "hidden";

  svg.innerHTML = `
    <defs>
      <linearGradient id="grad-primary" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#6366F1" />
        <stop offset="100%" stop-color="#22D3EE" />
      </linearGradient>
      <linearGradient id="grad-success" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#22C55E" />
        <stop offset="100%" stop-color="#10B981" />
      </linearGradient>
      <linearGradient id="grad-warning" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#F59E0B" />
        <stop offset="100%" stop-color="#EF4444" />
      </linearGradient>
      <linearGradient id="grad-danger" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#EF4444" />
        <stop offset="100%" stop-color="#F43F5E" />
      </linearGradient>
      <linearGradient id="grad-purple" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#A855F7" />
        <stop offset="100%" stop-color="#EC4899" />
      </linearGradient>
    </defs>
  `;
  document.body.prepend(svg);
}

// --- Interactive Mouse Glow & Parallax ---
function initMouseEffects() {
  const glow = document.createElement('div');
  glow.style.cssText = `
    position: fixed;
    width: 600px;
    height: 600px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(99,102,241,0.06) 0%, transparent 70%);
    pointer-events: none;
    z-index: -1;
    transform: translate(-50%, -50%);
    transition: left 0.8s ease, top 0.8s ease, opacity 0.5s ease;
    opacity: 0;
    filter: blur(30px);
  `;
  document.body.appendChild(glow);

  const parallaxWrappers = document.querySelectorAll('.parallax-wrapper');

  document.addEventListener('mousemove', (e) => {
    // 1. Mouse Glow
    glow.style.left = e.clientX + 'px';
    glow.style.top = e.clientY + 'px';
    glow.style.opacity = '1';

    // 2. 3D Parallax Movement
    const x = (e.clientX / window.innerWidth - 0.5) * 2; // -1 to 1
    const y = (e.clientY / window.innerHeight - 0.5) * 2;

    parallaxWrappers.forEach((wrap) => {
      const speed = parseFloat(wrap.dataset.speed);
      wrap.style.transform = "translate3d(" + (x * speed) + "px, " + (y * speed) + "px, 0)";
    });
  });

  document.addEventListener('mouseleave', () => {
    glow.style.opacity = '0';
    parallaxWrappers.forEach((wrap) => {
      wrap.style.transform = 'translate3d(0, 0, 0)';
    });
  });
}

// --- Card 3D Depth on Hover ---
function initCard3DEffects() {
  document.querySelectorAll('.glass-card:not(.panel)').forEach(card => {
    card.addEventListener('mouseenter', function() {
      this.style.transition = 'all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
    });
    card.addEventListener('mouseleave', function() {
      this.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
    });
  });
}

// --- Initialize Shared Modules ---
document.addEventListener('DOMContentLoaded', () => {
  initTheme();
  new GradientMesh('gradient-canvas');
  initScrollAnimations();
  initNavbar();
  initParallax();
  inject3DDecorations();
  injectSvgGradients();
  initMouseEffects();
  initCard3DEffects();

  // Theme toggle buttons
  document.querySelectorAll('.theme-toggle').forEach(btn => {
    btn.addEventListener('click', toggleTheme);
  });

  // Initialize Lucide icons
  if (typeof lucide !== 'undefined') {
    lucide.createIcons({ attrs: { 'stroke-width': 1.75, class: 'lucide' } });
  }

  // Page load animation
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      initPageLoader();
    });
  });
});
