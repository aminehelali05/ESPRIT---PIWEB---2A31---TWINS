/* ============================================================
   HOME.JS — Home page interactions
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  // --- Animate stat counters when visible ---
  const statObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const statNumbers = entry.target.querySelectorAll('.stat-number[data-target]');
        statNumbers.forEach(el => {
          const target = parseInt(el.dataset.target, 10);
          if (target && el.textContent === '0') {
            animateCounter(el, target, 2000);
          }
        });
        statObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.5 });

  const heroStats = document.querySelector('.hero-stats');
  if (heroStats) {
    statObserver.observe(heroStats);
  }

  // --- Parallax hero shapes on mouse move ---
  const heroSection = document.getElementById('hero');
  const heroShapes = document.querySelectorAll('.hero-shape');
  
  if (heroSection && heroShapes.length > 0) {
    heroSection.addEventListener('mousemove', (e) => {
      const rect = heroSection.getBoundingClientRect();
      const centerX = rect.width / 2;
      const centerY = rect.height / 2;
      const mouseX = e.clientX - rect.left - centerX;
      const mouseY = e.clientY - rect.top - centerY;
      
      heroShapes.forEach((shape, i) => {
        const speed = (i + 1) * 0.02;
        const rotateX = mouseY * speed * 0.5;
        const rotateY = mouseX * speed * 0.5;
        const translateX = mouseX * speed;
        const translateY = mouseY * speed;
        
        shape.style.transform = `translate(${translateX}px, ${translateY}px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
      });
    });
  }

  // --- Module cards hover sound effect (visual shimmer) ---
  document.querySelectorAll('.module-card').forEach(card => {
    card.addEventListener('mouseenter', () => {
      // Add shimmer effect
      const shimmer = document.createElement('div');
      shimmer.style.cssText = `
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.05), transparent);
        animation: shimmer 0.6s ease-out;
        pointer-events: none;
        z-index: 3;
      `;
      card.appendChild(shimmer);
      shimmer.addEventListener('animationend', () => shimmer.remove());
    });
  });
});
