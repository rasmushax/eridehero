const ProductFunctionality = (function() {
  const modal = document.getElementById('pf-modal');
  const modalContent = document.querySelector('.pf-modal-content');
  const snackbar = document.getElementById('pf-snackbar');

function showModal(content, title) {
    modalContent.innerHTML = `
      <div class="pf-modal-header">
        <h2>${title}</h2>
        <span class="pf-close">
          <svg class="pf-close-icon icon-x1"><use xlink:href="#icon-x1"></use></svg>
        </span>
      </div>
      <div class="pf-modal-body">${content}</div>
    `;
    modal.style.display = 'flex';
    document.body.classList.add('pf-modal-open');
    
    // Reattach close button event listener
    modalContent.querySelector('.pf-close').addEventListener('click', closeModal);

    // Add click event listener to the modal background
    modal.addEventListener('click', closeModalOnBackgroundClick);
  }

  function closeModal() {
    modal.style.display = 'none';
    document.body.classList.remove('pf-modal-open');
    
    // Remove the click event listener from the modal background
    modal.removeEventListener('click', closeModalOnBackgroundClick);
  }
  
   function closeModalOnBackgroundClick(event) {
    if (event.target === modal) {
      closeModal();
    }
  }

   let snackbarQueue = [];
  let isSnackbarVisible = false;
  let snackbarTimeout;

function showSnackbar(message, duration = 3000) {
  snackbarQueue.push({ message, duration });
  if (!isSnackbarVisible) {
    processSnackbarQueue();
  }
}

  function processSnackbarQueue() {
    if (snackbarQueue.length === 0) {
      isSnackbarVisible = false;
      return;
    }

    isSnackbarVisible = true;
    const { message, duration } = snackbarQueue.shift();
    const snackbar = document.getElementById('pf-snackbar');
    
    // Clear any existing timeout
    if (snackbarTimeout) {
      clearTimeout(snackbarTimeout);
    }

    // If snackbar is already visible, hide it first
    if (snackbar.classList.contains('show')) {
      snackbar.classList.remove('show');
      setTimeout(() => {
        showSnackbarMessage(snackbar, message, duration);
      }, 300); // Wait for fade out
    } else {
      showSnackbarMessage(snackbar, message, duration);
    }
  }

  function showSnackbarMessage(snackbar, message, duration) {
    snackbar.textContent = message;
    snackbar.classList.add('show');

    if (duration > 0) {
      snackbarTimeout = setTimeout(() => {
        hideSnackbar();
      }, duration);
    }
  }

function hideSnackbar() {
  const snackbar = document.getElementById('pf-snackbar');
  snackbar.classList.remove('show');
  
  // Clear any existing timeout
  if (snackbarTimeout) {
    clearTimeout(snackbarTimeout);
  }

  // Set a flag to indicate we're in the process of hiding
  isSnackbarVisible = false;

  // Wait for the transition to complete before allowing the next message
  setTimeout(() => {
    if (snackbarQueue.length > 0) {
      processSnackbarQueue();
    }
  }, 300);
}

  function showLoadingIndicator(element) {
    if (element && element.classList) {
      element.classList.add('pf-loading');
      element.disabled = true;
    }
  }

  function hideLoadingIndicator(element) {
    if (element && element.classList) {
      element.classList.remove('pf-loading');
      element.disabled = false;
    }
  }
  
  function generateStarRating(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
      stars += `<svg class="pf-star${i <= rating ? ' pf-star-filled' : ''}"><use xlink:href="#icon-star"></use></svg>`;
    }
    return stars;
  }
  
  function truncateReview(text, maxLength = 150) {
    if (text.length <= maxLength) return text;
    const truncated = text.substr(0, maxLength);
    return `
      <span class="pf-truncated-text">${truncated}...</span>
      <span class="pf-full-text" style="display:none;">${text}</span>
      <a href="#" class="pf-show-more">Show more</a>
    `;
  }
  
  function toggleFullText(event) {
    event.preventDefault();
    const reviewText = event.target.closest('.pf-text');
    const truncatedText = reviewText.querySelector('.pf-truncated-text');
    const fullText = reviewText.querySelector('.pf-full-text');
    const showMoreLink = reviewText.querySelector('.pf-show-more');

    if (truncatedText.style.display !== 'none') {
      truncatedText.style.display = 'none';
      fullText.style.display = 'inline';
      showMoreLink.textContent = 'Show less';
    } else {
      truncatedText.style.display = 'inline';
      fullText.style.display = 'none';
      showMoreLink.textContent = 'Show more';
    }
  }

  function loadAllReviews() {
    if (typeof pf_reviews === 'undefined' || !pf_reviews.length) {
      showSnackbar('No reviews available');
      return;
    }

    let reviewsHtml = pf_reviews.map(review => `
      <div class="pf-review">
        <div class="pf-user">
          <div class="pf-avatar">${review.authorFirstLetter}</div>
          <div class="pf-user-info">
            <div class="pf-username">${review.author}</div>
            <div class="pf-date">${review.date}</div>
          </div>
        </div>
        <div class="pf-stars">${generateStarRating(review.rating)}</div>
        <div class="pf-text">${truncateReview(review.text)}</div>
      </div>
    `).join('');
    showModal(`<div class="pf-reviews-container">${reviewsHtml}</div>`, 'All Reviews');

    // Add event listeners for "Show more" links
    document.querySelectorAll('.pf-show-more').forEach(link => {
      link.addEventListener('click', toggleFullText);
    });
  }

let isCheckingStatus = false;

function checkUserReviewStatus(data) {
    if (isCheckingStatus) return;
    isCheckingStatus = true;

    const { productId, rating, isStarRating, isStatic } = data;
    
    if (!isStarRating && !isStatic && data.element) {
      showLoadingIndicator(data.element);
    }
    
    const isLoggedIn = document.body.classList.contains('logged-in');

    if (isLoggedIn) {
      // User is logged in, check review status
      checkReviewStatus(productId, rating);
    } else {
      // User is not logged in, show login form
      showLoginForm();
    }

    if (!isStarRating && !isStatic && data.element) {
      hideLoadingIndicator(data.element);
    }
    isCheckingStatus = false;
}

function checkReviewStatus(productId, rating) {
    showSnackbar('Checking review status...', 0);

    fetch(`${pf_ajax.ajax_url}?action=pf_user_review_status&product_id=${productId}&_wpnonce=${pf_ajax.nonce}`)
      .then(response => response.json())
      .then(responseData => {
        hideSnackbar();

        if (responseData.hasPendingReview) {
          showSnackbar('Your review is awaiting moderation', 3000);
        } else if (responseData.hasPublishedReview) {
          showSnackbar('You have already posted a review', 3000);
        } else {
          showReviewForm(productId, rating);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        hideSnackbar();
        showSnackbar('Failed to check review status', 3000);
      });
}


function showReviewForm(productId, preSelectedRating = '') {
  let formHtml = `
    <form id="pf-review-form">
      <input type="hidden" name="product_id" value="${productId}">
      <div>
        <label for="rating">Rating</label>
        <select name="rating" required>
          <option value="">How do you rate it from 1-5?</option>
          <option value="5"${preSelectedRating === 5 ? ' selected' : ''}>5 Stars</option>
          <option value="4"${preSelectedRating === 4 ? ' selected' : ''}>4 Stars</option>
          <option value="3"${preSelectedRating === 3 ? ' selected' : ''}>3 Stars</option>
          <option value="2"${preSelectedRating === 2 ? ' selected' : ''}>2 Stars</option>
          <option value="1"${preSelectedRating === 1 ? ' selected' : ''}>1 Star</option>
        </select>
      </div>
      <div>
        <label for="review">Your Review</label>
        <textarea name="review" id="pf-review-textarea" required></textarea>
        <div id="pf-char-count">0 characters (max 5000)</div>
      </div>
      <button class="pfbtn" type="submit">Submit Review</button>
    </form>
  `;
  showModal(formHtml, 'Write a Review');

  const form = document.getElementById('pf-review-form');
  const textarea = document.getElementById('pf-review-textarea');
  const charCount = document.getElementById('pf-char-count');

  textarea.addEventListener('input', function() {
    const currentLength = this.value.length;
    charCount.textContent = `${currentLength} characters (max 5000)`;
    
    if (currentLength > 5000) {
      charCount.classList.add('pf-char-count-exceeded');
    } else {
      charCount.classList.remove('pf-char-count-exceeded');
    }
  });

  form.addEventListener('submit', function(e) {
    e.preventDefault();
    if (textarea.value.length > 5000) {
      showErrorMessage('Your review is too long. Please limit it to 5000 characters.');
      return;
    }
    submitReview(new FormData(this), this);
  });
}

function showLoginForm() {
  showSnackbar('Loading login form...', 0);
  
  fetch(`${pf_ajax.ajax_url}?action=pf_get_login_form`)
    .then(response => response.text())
    .then(formHtml => {
      showModal(formHtml, 'Login to Write a Review');
      
      const loginForm = document.getElementById('pf-login-form');
      if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
          e.preventDefault();
          login(new FormData(this), this);
        });
      }
      hideSnackbar();
    })
    .catch(error => {
      console.error('Error loading login form:', error);
      showSnackbar('Failed to load login form. Please try again.', 5000);
    });
}

function submitReview(formData, form) {
  const reviewText = formData.get('review');
  const rating = formData.get('rating');
  
  showSnackbar('Submitting your review...', 0);
  showLoadingIndicator(form.querySelector('button'));

  formData.append('action', 'pf_submit_review');
  formData.append('_wpnonce', pf_ajax.nonce);

  fetch(pf_ajax.ajax_url, {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    hideLoadingIndicator(form.querySelector('button'));
    if (data.success) {
      showSuccessMessage(data.data);
      closeModal(); // Close the review form modal
      // Optionally, refresh the reviews section or update UI
      // refreshReviews();
    } else {
      showErrorMessage(data.data);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    hideLoadingIndicator(form.querySelector('button'));
    showErrorMessage('An unexpected error occurred. Please try again later.');
  })
  .finally(() => {
    hideSnackbar(); // Ensure the "Submitting your review..." message is hidden
  });
}

function showSuccessMessage(message) {
  showSnackbar(message, 5000); // Show success message for 5 seconds
}

function showErrorMessage(message) {
  showSnackbar(message, 5000); // Show error message for 5 seconds
}

function login(formData, form) {
  showLoadingIndicator(form.querySelector('input[type="submit"]'));
  showSnackbar('Logging in...', 0);

  formData.append('action', 'pf_login');
  fetch(pf_ajax.ajax_url, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin' // This is important for handling cookies
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showSnackbar('Logged in successfully', 3000);
      closeModal();
      // Refresh the page or update UI as needed
      window.location.reload();
    } else {
      showSnackbar(data.message || 'Login failed', 5000);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showSnackbar('Login failed', 5000);
  })
  .finally(() => {
    hideLoadingIndicator(form.querySelector('input[type="submit"]'));
  });
}

  // Public methods
  return {
  showAllReviews: loadAllReviews,
  writeReview: checkUserReviewStatus,
  showLoadingIndicator,
  hideLoadingIndicator,
  showModal,
  closeModal,
  showSnackbar,
  hideSnackbar
};
})();

// Usage
document.addEventListener('DOMContentLoaded', function() {
  refreshNonce();
   const showAllReviewsBtn = document.querySelector('.pf-show-all-reviews');
  const writeReviewBtn = document.querySelector('.pf-write-review');
  const staticWriteReviewBtn = document.querySelector('.pf-writereview');

  if (showAllReviewsBtn) {
    showAllReviewsBtn.addEventListener('click', function() {
      ProductFunctionality.showAllReviews();
    });
  }

  if (writeReviewBtn) {
    writeReviewBtn.addEventListener('click', function() {
      ProductFunctionality.writeReview({
        productId: this.dataset.productId,
        isStarRating: false,
        element: this
      });
    });
  }

  if (staticWriteReviewBtn) {
    staticWriteReviewBtn.addEventListener('click', function() {
      ProductFunctionality.writeReview({
        productId: this.dataset.productId,
        isStarRating: false,
        isStatic: true // New flag to indicate this is the static button
      });
    });
  }

  // Star rating code (if you have it)
  const starRating = document.querySelector('.p-r-star-rating');
  if (starRating) {
    starRating.addEventListener('click', function(event) {
      const star = event.target.closest('.p-r-star');
      if (star) {
        const rating = parseInt(star.dataset.rating);
        ProductFunctionality.writeReview({
          productId: this.dataset.productId,
          rating: rating,
          isStarRating: true
        });
      }
    });
  }
});

function refreshNonce() {
  fetch(`${pf_ajax.ajax_url}?action=refresh_pf_nonce`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        pf_ajax.nonce = data.data.nonce;
      }
    })
    .catch(error => {
      console.error('Error refreshing nonce:', error);
    });
}

document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.pf-show-more').forEach(link => {
    link.addEventListener('click', function(event) {
      event.preventDefault();
      const reviewText = this.closest('.p-r-text');
      const truncatedText = reviewText.querySelector('.pf-truncated-text');
      const fullText = reviewText.querySelector('.pf-full-text');

      if (truncatedText.style.display !== 'none') {
        truncatedText.style.display = 'none';
        fullText.style.display = 'inline';
        this.textContent = 'Show less';
      } else {
        truncatedText.style.display = 'inline';
        fullText.style.display = 'none';
        this.textContent = 'Show more';
      }
    });
  });
});

function refreshNonce() {
    fetch(`${pf_ajax.ajax_url}?action=refresh_pf_nonce`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelector('.pf-write-review').dataset.nonce = data.data.nonce;
            }
        })
        .catch(error => {
            console.error('Error refreshing nonce:', error);
        });
}