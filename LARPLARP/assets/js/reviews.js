/* ============================================================
   REVIEWS.JS — Interactive star rating & review interactions
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  const stars = document.querySelectorAll('.star-input');
  const ratingText = document.getElementById('rating-text');
  let selectedRating = 0;

  const ratingLabels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

  // --- Star Hover Effect ---
  stars.forEach(star => {
    star.addEventListener('mouseenter', () => {
      const val = parseInt(star.dataset.value);
      stars.forEach((s, i) => {
        if (i < val) {
          s.classList.add('hover');
        } else {
          s.classList.remove('hover');
        }
      });
      if (ratingText) ratingText.textContent = ratingLabels[val];
    });

    star.addEventListener('mouseleave', () => {
      stars.forEach(s => s.classList.remove('hover'));
      if (ratingText) {
        ratingText.textContent = selectedRating > 0 
          ? `${ratingLabels[selectedRating]} — ${selectedRating}/5` 
          : 'Select a rating';
      }
    });

    star.addEventListener('click', () => {
      selectedRating = parseInt(star.dataset.value);
      stars.forEach((s, i) => {
        s.classList.toggle('active', i < selectedRating);
      });
      if (ratingText) ratingText.textContent = `${ratingLabels[selectedRating]} — ${selectedRating}/5`;

      // Click animation
      star.style.transform = 'scale(1.4)';
      setTimeout(() => {
        star.style.transform = 'scale(1.1)';
      }, 150);
    });
  });

  // --- Submit Review (demo) ---
  const submitBtn = document.getElementById('submit-review');
  const textarea = document.getElementById('review-textarea');

  if (submitBtn) {
    submitBtn.addEventListener('click', () => {
      if (selectedRating === 0) {
        if (ratingText) {
          ratingText.textContent = 'Please select a rating first!';
          ratingText.style.color = '#F43F5E';
          setTimeout(() => {
            ratingText.style.color = '';
            ratingText.textContent = 'Select a rating';
          }, 2000);
        }
        return;
      }

      submitBtn.textContent = 'Submitting...';
      submitBtn.style.opacity = '0.7';

      setTimeout(() => {
        submitBtn.textContent = '✓ Review Submitted!';
        submitBtn.style.background = 'linear-gradient(135deg, #22C55E, #16A34A)';

        // Add the review to the list
        const reviewsList = document.querySelector('.reviews-list');
        if (reviewsList && textarea) {
          const reviewText = textarea.value.trim() || 'Great experience!';
          const starsHtml = '★'.repeat(selectedRating) + '<span style="color:rgba(255,255,255,0.2)">' + '★'.repeat(5 - selectedRating) + '</span>';
          
          const newReview = document.createElement('div');
          newReview.className = 'review-card glass-card';
          newReview.style.opacity = '0';
          newReview.style.transform = 'translateY(20px)';
          newReview.innerHTML = `
            <div class="review-header">
              <div class="avatar" style="width:40px;height:40px;font-size:0.85rem"><span>JD</span></div>
              <div>
                <p class="review-author">John Doe</p>
                <div class="review-stars">${starsHtml}</div>
              </div>
              <span class="text-small" style="margin-left:auto">Just now</span>
            </div>
            <p class="review-text">${reviewText}</p>
            <div class="review-helpful">
              <button class="helpful-btn">👍 Helpful (0)</button>
            </div>
          `;
          reviewsList.prepend(newReview);
          
          requestAnimationFrame(() => {
            newReview.style.transition = 'all 0.5s var(--ease-out, cubic-bezier(0, 0, 0.2, 1))';
            newReview.style.opacity = '1';
            newReview.style.transform = 'translateY(0)';
          });
        }

        // Reset form
        setTimeout(() => {
          submitBtn.textContent = 'Submit Review';
          submitBtn.style.background = '';
          submitBtn.style.opacity = '';
          if (textarea) textarea.value = '';
          selectedRating = 0;
          stars.forEach(s => s.classList.remove('active'));
          if (ratingText) ratingText.textContent = 'Select a rating';
        }, 2000);
      }, 1000);
    });
  }

  // --- Helpful Button Toggle ---
  document.querySelectorAll('.helpful-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      btn.classList.toggle('clicked');
      const text = btn.textContent;
      const match = text.match(/\((\d+)\)/);
      if (match) {
        const count = parseInt(match[1]);
        const newCount = btn.classList.contains('clicked') ? count + 1 : count;
        btn.textContent = `👍 Helpful (${newCount})`;
      }
    });
  });
});
