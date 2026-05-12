/* ============================================================
   SOCIAL.JS — Social feed interactions
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  // --- Toggle Comments ---
  document.querySelectorAll('.post-comment-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const postId = btn.dataset.post;
      const comments = document.getElementById(`comments-${postId}`);
      if (comments) {
        comments.classList.toggle('expanded');
      }
    });
  });

  // --- Like Button Toggle ---
  document.querySelectorAll('[data-action="like"]').forEach(btn => {
    btn.addEventListener('click', () => {
      btn.classList.toggle('liked');
      
      // Animate heart
      if (btn.classList.contains('liked')) {
        btn.style.transform = 'scale(1.2)';
        setTimeout(() => {
          btn.style.transform = '';
        }, 200);
      }
    });
  });

  // --- Create Post (demo) ---
  const postBtn = document.getElementById('post-btn');
  const postInput = document.getElementById('post-input');
  
  if (postBtn && postInput) {
    postBtn.addEventListener('click', () => {
      const text = postInput.value.trim();
      if (!text) return;

      const feedMain = document.querySelector('.feed-main');
      const createPost = document.querySelector('.create-post');

      const newPost = document.createElement('div');
      newPost.className = 'post-card glass-card fade-in-section';
      newPost.innerHTML = `
        <div class="post-header">
          <div class="avatar"><span>JD</span></div>
          <div>
            <p class="post-author">John Doe</p>
            <p class="text-small">Just now</p>
          </div>
        </div>
        <div class="post-body">
          <p>${text}</p>
        </div>
        <div class="post-stats">
          <span>❤️ 0 likes</span>
          <span>💬 0 comments</span>
          <span>🔄 0 shares</span>
        </div>
        <div class="post-actions">
          <button class="post-action-btn" data-action="like">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            Like
          </button>
          <button class="post-action-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            Comment
          </button>
          <button class="post-action-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
            Share
          </button>
        </div>
      `;

      feedMain.insertBefore(newPost, createPost.nextSibling);
      
      // Trigger scroll animation
      requestAnimationFrame(() => {
        newPost.classList.add('visible');
      });

      // Re-bind like button
      newPost.querySelector('[data-action="like"]').addEventListener('click', function() {
        this.classList.toggle('liked');
      });

      postInput.value = '';
    });
  }
});
