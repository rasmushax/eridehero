const ProductReviews = (function() {
  let isCheckingStatus = false;
  let currentLoadingButton = null;

  function checkUserReviewStatus(data) {
    if (isCheckingStatus) return;
    isCheckingStatus = true;

    const { productId, rating, isStarRating, isStatic, element } = data;
    
    if (!isStarRating && !isStatic && element) {
      currentLoadingButton = element;
      ProductFunctionality.showLoadingIndicator(element);
    }
    
    const isLoggedIn = document.body.classList.contains('logged-in');

    if (isLoggedIn) {
      checkReviewStatus(productId, rating, element)
        .finally(() => {
          isCheckingStatus = false;
          if (currentLoadingButton) {
            ProductFunctionality.hideLoadingIndicator(currentLoadingButton);
            currentLoadingButton = null;
          }
          removeSpinner();
        });
    } else {
      setTimeout(() => {
        ProductFunctionality.showLoginForm();
        isCheckingStatus = false;
        if (currentLoadingButton) {
          ProductFunctionality.hideLoadingIndicator(currentLoadingButton);
          currentLoadingButton = null;
        }
        removeSpinner();
      }, 0);
    }
  }

	
function showReviewModal(reviewId) {
	console.log(reviewId);
    const review = pf_reviews.find(r => r.id === parseInt(reviewId));
	console.log(review);
    if (!review) return;

    const modalContent = `
        <div class="pf-review-modal">
            <div class="pf-review-modal-content">
                <div class="pf-review-modal-user">
                    <div class="pf-user-info">
                        <div class="pf-date">${review.date}</div>
                    </div>
                </div>
                <div class="pf-stars">${generateStarRating(review.score)}</div>
                <div class="pf-review-modal-text">${review.text}</div>
            </div>
			<div class="pf-review-modal-image">
                <img src="${review.large_url}" alt="Review image" class="pf-review-modal-image-full">
            </div>
        </div>
    `;

    ProductFunctionality.showModal(modalContent, review.author);
}

function getImageUrl(imageId, size = 'medium') {
    // This function should be implemented to return the correct URL for the image
    // You might need to make an AJAX call to the server to get this information
    // For now, we'll return a placeholder
    return `/wp-content/uploads/review-image-${imageId}-${size}.jpg`;
}

function generateStarRating(score) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        stars += `<svg class="pf-star${i <= score ? ' pf-star-filled' : ''}"><use xlink:href="#icon-star"></use></svg>`;
    }
    return stars;
}

  function checkReviewStatus(productId, rating, element) {
    return fetch(`${pf_reviews_ajax.ajax_url}?action=pf_user_review_status&product_id=${productId}&_wpnonce=${pf_ajax.nonce}`)
      .then(response => response.json())
      .then(responseData => {
        if (responseData.hasPendingReview) {
          ProductFunctionality.showSnackbar('Your review is awaiting moderation', 3000);
        } else if (responseData.hasPublishedReview) {
          ProductFunctionality.showSnackbar('You have already posted a review', 3000);
        } else {
          showReviewForm(productId, rating);
        }
      })
      .catch(error => {
        ProductFunctionality.showSnackbar('Failed to check review status', 3000,'failed');
      });
  }
  
function loadAllReviews() {
    if (typeof pf_reviews === 'undefined' || !pf_reviews.length) {
        ProductFunctionality.showSnackbar('No reviews available', 3000, 'failed');
        return;
    }
    let reviewsHtml = pf_reviews.map(review => `
        <div class="pf-review">
            <div class="pf-user">
                <div class="pf-avatar">${escapeHtml(review.author.charAt(0))}</div>
                <div class="pf-user-info">
                    <div class="pf-username">${escapeHtml(review.author)}</div>
                    <div class="pf-date">${escapeHtml(review.date)}</div>
                </div>
            </div>
            <div class="pf-stars">${generateStarRating(review.score)}</div>
            ${review.image_id ? `<div class="pf-review-image">
                <img src="${getImageUrl(review.image_id, 'medium')}" alt="Review image" class="pf-review-image">
            </div>` : ''}
            <div class="pf-text">${truncateReview(review.text)}</div>
        </div>
    `).join('');
    ProductFunctionality.showModal(`<div class="pf-reviews-container">${reviewsHtml}</div>`, 'All Reviews');
    
    // Add event listeners for "Show more" links after the modal is shown
    setTimeout(initializeShowMoreLinks, 0);
}

  function generateStarRating(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
      stars += `<svg class="pf-star${i <= rating ? ' pf-star-filled' : ''}"><use xlink:href="#icon-star"></use></svg>`;
    }
    return stars;
  }

  function truncateReview(text, maxLength = 150) {
    const escapedText = escapeHtml(text);
    const textWithLineBreaks = escapedText.replace(/\n/g, '<br>');
    
    if (text.length <= maxLength) return textWithLineBreaks;
    
    let truncated = text.substr(0, maxLength);
    const lastSpace = truncated.lastIndexOf(' ');
    if (lastSpace !== -1) {
      truncated = truncated.substr(0, lastSpace);
    }
    
    const escapedTruncated = escapeHtml(truncated);
    
    return `
      <span class="pf-truncated-text">${escapedTruncated}...</span>
      <span class="pf-full-text" style="display:none;">${textWithLineBreaks}</span>
      <a href="#" class="pf-show-more">Show more</a>
    `;
  }
  
  function initializeShowMoreLinks() {
    document.querySelectorAll('.pf-show-more').forEach(link => {
      link.removeEventListener('click', toggleFullText);
      link.addEventListener('click', toggleFullText);
    });
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function toggleFullText(event) {
    event.preventDefault();
    const reviewText = event.target.closest('.p-r-text, .pf-text');
    const truncatedText = reviewText.querySelector('.pf-truncated-text');
    const fullText = reviewText.querySelector('.pf-full-text');
    const showMoreLink = event.target;

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
   <div class="pf-file-upload">
    <input type="file" name="review-image" id="pf-review-image" accept="image/*" style="display: none;">
	<div id="pf-image-preview"><svg><use xlink:href="#icon-plus-square"></use></svg></div>
    <button type="button" id="pf-file-upload-btn" class="pfbtn">Upload Image</button>
  </div>
  <button class="pfbtn" type="submit">Submit Review</button>
</form>`;
    ProductFunctionality.showModal(formHtml, 'Write a Review');

    const form = document.getElementById('pf-review-form');
	  const textarea = document.getElementById('pf-review-textarea');
	  const charCount = document.getElementById('pf-char-count');
	  const imageInput = document.getElementById('pf-review-image');
	  const imagePreview = document.getElementById('pf-image-preview');
	  const fileUploadBtn = document.getElementById('pf-file-upload-btn');

	  textarea.addEventListener('input', function() {
		const currentLength = this.value.length;
		charCount.textContent = `${currentLength} characters (max 5000)`;
		
		if (currentLength > 5000) {
		  charCount.classList.add('pf-char-count-exceeded');
		} else {
		  charCount.classList.remove('pf-char-count-exceeded');
		}
	  });

	  fileUploadBtn.addEventListener('click', function() {
		imageInput.click();
	  });
	  
	  imagePreview.addEventListener('click', function() {
		imageInput.click();
	  });

	  imageInput.addEventListener('change', function(e) {
		const file = e.target.files[0];
		if (file) {
		  if (file.size > 5 * 1024 * 1024) { // 5MB limit
			ProductFunctionality.showSnackbar('Image size should not exceed 5MB', 3000, 'failed');
			this.value = ''; // Clear the input
			imagePreview.innerHTML = '';
			return;
		  }
		  const reader = new FileReader();
		  reader.onload = function(e) {
			imagePreview.innerHTML = `<img src="${e.target.result}">`;
		  }
		  reader.readAsDataURL(file);
		} else {
		  imagePreview.innerHTML = '<svg><use xlink:href="#icon-plus-square"></use></svg>';
		}
	  });

	  form.addEventListener('submit', function(e) {
		e.preventDefault();
		if (textarea.value.length > 5000) {
		  ProductFunctionality.showSnackbar('Your review is too long. Please limit it to 5000 characters.', 5000, 'failed');
		  return;
		}

		const formData = new FormData(this);
		formData.append('action', 'pf_submit_review');
		formData.append('_wpnonce', pf_ajax.nonce);

		const submitButton = form.querySelector('button[type="submit"]');
		ProductFunctionality.showLoadingIndicator(submitButton);

		fetch(pf_ajax.ajax_url, {
		  method: 'POST',
		  body: formData,
		  credentials: 'same-origin'
		})
		.then(response => response.json())
		.then(data => {
		  ProductFunctionality.hideLoadingIndicator(submitButton);
		  if (data.success) {
			ProductFunctionality.showSnackbar(data.data, 5000, 'success');
			ProductFunctionality.closeModal();
		  } else {
			ProductFunctionality.showSnackbar(data.data, 5000, 'failed');
		  }
		})
		.catch(error => {
		  ProductFunctionality.hideLoadingIndicator(submitButton);
		  ProductFunctionality.showSnackbar('An unexpected error occurred. Please try again later.', 5000, 'failed');
		});
	  });
	}

  function submitReview(formData) {
    const submitButton = document.querySelector('#pf-review-form button[type="submit"]');
    ProductFunctionality.showSnackbar('Submitting your review...', 0);
    ProductFunctionality.showLoadingIndicator(submitButton);

    formData.append('action', 'pf_submit_review');
    formData.append('_wpnonce', pf_ajax.nonce);

    fetch(pf_ajax.ajax_url, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        ProductFunctionality.showSnackbar(data.data, 5000,'success');
        ProductFunctionality.closeModal();
      } else {
        ProductFunctionality.showSnackbar('Failed to submit review. Please try again.', 5000,'failed');
      }
    })
    .catch(error => {
      ProductFunctionality.showSnackbar('An unexpected error occurred. Please try again later.', 5000,'failed');
    })
    .finally(() => {
      ProductFunctionality.hideSnackbar();
      ProductFunctionality.hideLoadingIndicator(submitButton);
    });
  }

  function initializeStarRating() {
    const heading = document.querySelector('#p-r-body h2.product-smallheading');
    if (heading && heading.classList.contains("noreviews")) {
      const starRating = document.querySelector('.p-r-star-rating');
      if (starRating) {
        const stars = Array.from(starRating.querySelectorAll('.p-r-star'));
        let currentRating = 0;

        function highlightStars(rating) {
          stars.forEach((star, index) => {
            star.classList.toggle('p-r-active', index < rating);
          });
        }

        function handleStarHover(event) {
          const star = event.target.closest('.p-r-star');
          if (star) {
            const rating = parseInt(star.dataset.rating);
            highlightStars(rating);
          }
        }

        function handleStarClick(event) {
          const star = event.target.closest('.p-r-star');
          if (star) {
            currentRating = parseInt(star.dataset.rating);
            highlightStars(currentRating);
            
            addSpinner(starRating);
            
            checkUserReviewStatus({
              productId: starRating.dataset.productId,
              rating: currentRating,
              isStarRating: true
            });
          }
        }

        function handleMouseLeave() {
          highlightStars(currentRating);
        }

        stars.forEach(star => {
          star.addEventListener('mouseenter', handleStarHover);
        });

        starRating.addEventListener('click', handleStarClick);
        starRating.addEventListener('mouseleave', handleMouseLeave);
      }
    }
  }

  function addSpinner(starRating) {
    const spinner = document.createElement('div');
    spinner.className = 'p-r-spinner';
    spinner.innerHTML = '<svg width=30 height=30 fill="currentcolor" viewBox="0 0 24 24"><path d="M11 2v4c0 0.552 0.448 1 1 1s1-0.448 1-1v-4c0-0.552-0.448-1-1-1s-1 0.448-1 1zM11 18v4c0 0.552 0.448 1 1 1s1-0.448 1-1v-4c0-0.552-0.448-1-1-1s-1 0.448-1 1zM4.223 5.637l2.83 2.83c0.391 0.391 1.024 0.391 1.414 0s0.391-1.024 0-1.414l-2.83-2.83c-0.391-0.391-1.024-0.391-1.414 0s-0.391 1.024 0 1.414zM15.533 16.947l2.83 2.83c0.391 0.391 1.024 0.391 1.414 0s0.391-1.024 0-1.414l-2.83-2.83c-0.391-0.391-1.024-0.391-1.414 0s-0.391 1.024 0 1.414zM2 13h4c0.552 0 1-0.448 1-1s-0.448-1-1-1h-4c-0.552 0-1 0.448-1 1s0.448 1 1 1zM18 13h4c0.552 0 1-0.448 1-1s-0.448-1-1-1h-4c-0.552 0-1 0.448-1 1s0.448 1 1 1zM5.637 19.777l2.83-2.83c0.391-0.391 0.391-1.024 0-1.414s-1.024-0.391-1.414 0l-2.83 2.83c-0.391 0.391-0.391 1.024 0 1.414s1.024 0.391 1.414 0zM16.947 8.467l2.83-2.83c0.391-0.391 0.391-1.024 0-1.414s-1.024-0.391-1.414 0l-2.83 2.83c-0.391 0.391-0.391 1.024 0 1.414s1.024 0.391 1.414 0z"></path></svg>';
    starRating.appendChild(spinner);
  }

  function removeSpinner() {
    const spinner = document.querySelector('.p-r-spinner');
    if (spinner) {
      spinner.remove();
    }
  }

  // Public methods
  return {
    checkUserReviewStatus,
    handleStarRating: initializeStarRating,
    showReviewForm,
	showReviewModal,
    submitReview,
    loadAllReviews,
    truncateReview,
    toggleFullText,
    initializeShowMoreLinks
  };
})();

document.addEventListener('DOMContentLoaded', function() {
  ProductReviews.handleStarRating();
  ProductReviews.initializeShowMoreLinks();

  document.body.addEventListener('click', function(event) {
    const writeReviewBtn = event.target.closest('.pf-write-review, .pf-writereview');
    if (writeReviewBtn) {
      event.preventDefault();
      ProductReviews.checkUserReviewStatus({
        productId: writeReviewBtn.dataset.productId,
        isStarRating: false,
        isStatic: writeReviewBtn.classList.contains('pf-writereview'),
        element: writeReviewBtn
      });
    }
  });

  const showAllReviewsBtn = document.querySelector('.pf-show-all-reviews');
  if (showAllReviewsBtn) {
    showAllReviewsBtn.addEventListener('click', ProductReviews.loadAllReviews);
  }
  
   document.querySelectorAll('.p-r-image').forEach(image => {
        image.addEventListener('click', function() {
            const reviewId = this.closest('.p-r-review').dataset.reviewId;
            ProductReviews.showReviewModal(reviewId);
        });
    });
  
});
