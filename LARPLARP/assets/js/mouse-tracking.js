/* ============================================================
   MOUSE-TRACKING.JS — 3D Tilt Effect with Light Reflection
   ============================================================ */

class TiltEffect {
  constructor(options = {}) {
    this.maxRotation = options.maxRotation || 15;
    this.perspective = options.perspective || 1000;
    this.scale = options.scale || 1.02;
    this.speed = options.speed || 400;
    this.glare = options.glare !== false;
    this.cards = [];
    
    this.init();
  }

  init() {
    document.querySelectorAll('.tilt-card').forEach(card => {
      this.setupCard(card);
    });
  }

  setupCard(card) {
    card.style.perspective = `${this.perspective}px`;
    
    // Create glare overlay
    if (this.glare) {
      const glareEl = document.createElement('div');
      glareEl.classList.add('tilt-reflection');
      card.appendChild(glareEl);
    }

    // Event listeners
    card.addEventListener('mousemove', (e) => this.handleMouseMove(e, card));
    card.addEventListener('mouseleave', (e) => this.handleMouseLeave(e, card));
    card.addEventListener('mouseenter', (e) => this.handleMouseEnter(e, card));
    
    this.cards.push(card);
  }

  handleMouseMove(e, card) {
    const rect = card.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;
    
    const mouseX = e.clientX - centerX;
    const mouseY = e.clientY - centerY;
    
    const rotateY = (mouseX / (rect.width / 2)) * this.maxRotation;
    const rotateX = -(mouseY / (rect.height / 2)) * this.maxRotation;
    
    card.style.transform = `
      perspective(${this.perspective}px)
      rotateX(${rotateX}deg)
      rotateY(${rotateY}deg)
      scale3d(${this.scale}, ${this.scale}, ${this.scale})
    `;

    // Update glare
    if (this.glare) {
      const glareEl = card.querySelector('.tilt-reflection');
      if (glareEl) {
        const percentX = ((e.clientX - rect.left) / rect.width) * 100;
        const percentY = ((e.clientY - rect.top) / rect.height) * 100;
        glareEl.style.background = `
          radial-gradient(
            circle at ${percentX}% ${percentY}%,
            rgba(255, 255, 255, 0.15) 0%,
            transparent 60%
          )
        `;
        glareEl.style.opacity = '1';
      }
    }
  }

  handleMouseEnter(e, card) {
    card.style.transition = 'none';
    card.style.willChange = 'transform';
  }

  handleMouseLeave(e, card) {
    card.style.transition = `transform ${this.speed}ms cubic-bezier(0.4, 0, 0.2, 1)`;
    card.style.transform = `
      perspective(${this.perspective}px)
      rotateX(0deg)
      rotateY(0deg)
      scale3d(1, 1, 1)
    `;
    card.style.willChange = 'auto';

    if (this.glare) {
      const glareEl = card.querySelector('.tilt-reflection');
      if (glareEl) {
        glareEl.style.opacity = '0';
      }
    }
  }

  // Allow manual initialization for dynamically added cards
  refresh() {
    this.cards = [];
    this.init();
  }
}

// Auto-initialize
document.addEventListener('DOMContentLoaded', () => {
  window.tiltEffect = new TiltEffect();
});
