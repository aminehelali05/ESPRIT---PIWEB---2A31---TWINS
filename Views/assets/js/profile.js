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
  const closeBtn = document.getElementById('closeEditBtn');
  const cancelBtn = document.getElementById('cancelEditBtn');

  function openModal() {
    if (!modal) return;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    if (typeof lucide !== 'undefined') lucide.createIcons();
  }

  function closeModal() {
    if (!modal) return;
    modal.classList.remove('active');
    document.body.style.overflow = '';
    if (typeof lucide !== 'undefined') lucide.createIcons();
  }

  if (editBtn) editBtn.addEventListener('click', openModal);
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeModal();
    });
  }

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal && modal.classList.contains('active')) {
      closeModal();
    }
  });

  const profileForm = document.getElementById('profileForm');
  if (profileForm) {
    profileForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const name = document.getElementById('editName').value;
      if (name) {
        document.querySelector('.profile-name').textContent = name;
      }
      closeModal();
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
});
