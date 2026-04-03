/* ============================================================
   PROJECTS.JS — Projects page interactions
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  // --- Filter Buttons ---
  const filterBtns = document.querySelectorAll('.filter-btn');
  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    });
  });

  // --- Staggered fade-in for timeline ---
  const timelineObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const items = entry.target.querySelectorAll('.timeline-item');
        items.forEach((item, i) => {
          setTimeout(() => {
            item.style.opacity = '1';
            item.style.transform = 'translateX(0)';
          }, i * 200);
        });
        timelineObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.2 });

  const timeline = document.querySelector('.project-timeline');
  if (timeline) {
    // Set initial state
    timeline.querySelectorAll('.timeline-item').forEach(item => {
      item.style.opacity = '0';
      item.style.transform = 'translateX(-20px)';
      item.style.transition = 'all 0.6s var(--ease-out, cubic-bezier(0, 0, 0.2, 1))';
    });
    timelineObserver.observe(timeline);
  }
});
