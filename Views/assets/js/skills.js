/* ============================================================
   SKILLS.JS — 3D skill grid + certificate carousel
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  // --- 3D Certificate Carousel ---
  const carousel = document.getElementById('cert-carousel');
  const cards = carousel ? carousel.querySelectorAll('.cert-card') : [];
  const prevBtn = document.getElementById('carousel-prev');
  const nextBtn = document.getElementById('carousel-next');
  const dotsContainer = document.getElementById('carousel-dots');
  
  let currentIndex = 0;
  const total = cards.length;

  function createDots() {
    if (!dotsContainer) return;
    dotsContainer.innerHTML = '';
    for (let i = 0; i < total; i++) {
      const dot = document.createElement('div');
      dot.className = 'carousel-dot' + (i === currentIndex ? ' active' : '');
      dot.addEventListener('click', () => goTo(i));
      dotsContainer.appendChild(dot);
    }
  }

  function updateCarousel() {
    cards.forEach((card, i) => {
      card.className = 'cert-card glass-card';
      const diff = i - currentIndex;
      
      if (diff === 0) card.classList.add('active');
      else if (diff === -1 || (currentIndex === 0 && i === total - 1)) card.classList.add('prev');
      else if (diff === 1 || (currentIndex === total - 1 && i === 0)) card.classList.add('next');
      else if (diff === -2) card.classList.add('far-prev');
      else if (diff === 2) card.classList.add('far-next');
      else card.classList.add('hidden');
    });

    // Update dots
    if (dotsContainer) {
      dotsContainer.querySelectorAll('.carousel-dot').forEach((dot, i) => {
        dot.classList.toggle('active', i === currentIndex);
      });
    }
  }

  function goTo(index) {
    currentIndex = ((index % total) + total) % total;
    updateCarousel();
  }

  function goNext() { goTo(currentIndex + 1); }
  function goPrev() { goTo(currentIndex - 1); }

  if (cards.length > 0) {
    createDots();
    updateCarousel();
    
    if (prevBtn) prevBtn.addEventListener('click', goPrev);
    if (nextBtn) nextBtn.addEventListener('click', goNext);

    // Auto-play
    let autoPlay = setInterval(goNext, 4000);
    
    if (carousel) {
      carousel.addEventListener('mouseenter', () => clearInterval(autoPlay));
      carousel.addEventListener('mouseleave', () => {
        autoPlay = setInterval(goNext, 4000);
      });
    }

    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowLeft') goPrev();
      if (e.key === 'ArrowRight') goNext();
    });
  }

  // --- Skill grid hover highlight ---
  const skillItems = document.querySelectorAll('.skill-float-item');
  skillItems.forEach(item => {
    item.addEventListener('mouseenter', () => {
      skillItems.forEach(other => {
        if (other !== item) {
          other.style.opacity = '0.4';
          other.style.filter = 'blur(2px)';
        }
      });
    });
    item.addEventListener('mouseleave', () => {
      skillItems.forEach(other => {
        other.style.opacity = '';
        other.style.filter = '';
      });
    });
  });
});
