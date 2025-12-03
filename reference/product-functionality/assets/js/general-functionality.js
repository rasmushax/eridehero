const ProductFunctionality = (function() {
  // Modal functionality
  const modal = document.getElementById('pf-modal');
  const modalContent = document.querySelector('.pf-modal-content');

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
  
  // Set initial styles for animation
  modal.style.display = 'flex';
  modal.style.opacity = '0';
  modalContent.style.transform = 'scale(0.7)';
  modalContent.style.opacity = '0';
  
  // Force a reflow to ensure the initial styles are applied before animating
  void modal.offsetWidth;
  
  // Animate the modal background
  modal.style.transition = 'opacity 0.3s ease-out';
  modal.style.opacity = '1';
  
  // Animate the modal content
  modalContent.style.transition = 'all 0.3s ease-out';
  modalContent.style.transform = 'scale(1)';
  modalContent.style.opacity = '1';
  
  document.body.classList.add('pf-modal-open');
  
  modalContent.querySelector('.pf-close').addEventListener('click', closeModal);
  modal.addEventListener('click', closeModalOnBackgroundClick);
  
  removeAllLoadingIndicators();
}

function closeModal() {
  // Animate the modal content
  modalContent.style.transform = 'scale(0.7)';
  modalContent.style.opacity = '0';
  
  // Animate the modal background
  modal.style.opacity = '0';
  
  // Wait for the animation to finish before hiding the modal
  setTimeout(() => {
    modal.style.display = 'none';
    document.body.classList.remove('pf-modal-open');
    modal.removeEventListener('click', closeModalOnBackgroundClick);
    
    // Reset styles
    modalContent.style.transform = '';
    modalContent.style.opacity = '';
    modal.style.opacity = '';
  }, 300); // Match this with the transition duration
}
  
  function closeModalOnBackgroundClick(event) {
    if (event.target === modal) {
      closeModal();
    }
  }

// Snackbar functionality
const snackbar = document.getElementById('pf-snackbar');
const snackbarText = snackbar.querySelector('.pf-snackbar-text');
const snackbarClose = snackbar.querySelector('.pf-snackbar-close');
let snackbarQueue = [];
let isSnackbarVisible = false;
let snackbarTimeout;

function showSnackbar(message, duration = 3000, type = 'neutral') {
  snackbarQueue.push({ message, duration, type });
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
  const { message, duration, type } = snackbarQueue.shift();
  
  if (snackbarTimeout) {
    clearTimeout(snackbarTimeout);
  }

  if (snackbar.classList.contains('show')) {
    hideSnackbar(() => {
      showSnackbarMessage(message, duration, type);
    });
  } else {
    showSnackbarMessage(message, duration, type);
  }
}

function showSnackbarMessage(message, duration, type) {
  snackbarText.textContent = message;
  snackbar.classList.remove('pf-snackbar-success', 'pf-snackbar-failed');
  
  if (type === 'success') {
    snackbar.classList.add('pf-snackbar-success');
  } else if (type === 'failed') {
    snackbar.classList.add('pf-snackbar-failed');
  }
  
  // Force a reflow to ensure the removal and addition of classes take effect
  void snackbar.offsetWidth;
  
  snackbar.classList.add('show');

  if (duration > 0) {
    snackbarTimeout = setTimeout(() => {
      hideSnackbar();
    }, duration);
  }
}

function hideSnackbar(callback) {
  snackbar.classList.remove('show');
  
  if (snackbarTimeout) {
    clearTimeout(snackbarTimeout);
  }

  // Wait for the fade-out transition to complete before removing classes and processing the next message
  setTimeout(() => {
    snackbar.classList.remove('pf-snackbar-success', 'pf-snackbar-failed');
    isSnackbarVisible = false;

    if (callback) {
      callback();
    } else if (snackbarQueue.length > 0) {
      processSnackbarQueue();
    }
  }, 300); // This should match the CSS transition time
}

// Initialize close button functionality
snackbarClose.addEventListener('click', () => {
  hideSnackbar();
  snackbarQueue = []; // Clear the queue when manually closed
});

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

  function initializeLoadingButtons() {
    document.addEventListener('click', function(event) {
      const button = event.target.closest('button[data-static="false"]');
      if (button) {
        showLoadingIndicator(button);
      }
    });
  }

  // Form validation
  function validateForm(form) {
    let isValid = true;
    form.querySelectorAll('[required]').forEach(field => {
      if (!field.value.trim()) {
        isValid = false;
        showFieldError(field, 'This field is required');
      } else {
        clearFieldError(field);
      }
    });
    return isValid;
  }

  function showFieldError(field, message) {
    const errorElement = field.nextElementSibling;
    if (errorElement && errorElement.classList.contains('pf-error-message')) {
      errorElement.textContent = message;
    } else {
      const error = document.createElement('div');
      error.className = 'pf-error-message';
      error.textContent = message;
      field.parentNode.insertBefore(error, field.nextSibling);
    }
    field.classList.add('pf-error');
  }

  function clearFieldError(field) {
    const errorElement = field.nextElementSibling;
    if (errorElement && errorElement.classList.contains('pf-error-message')) {
      errorElement.remove();
    }
    field.classList.remove('pf-error');
  }


  function showLoginForm() {
    const currentUrl = encodeURIComponent(window.location.href);
    const loginFormHtml = `
      <div class="pf-login">
        <div class="pf-login-title">Login or register to track prices and unlock all full features.</div>
        <div class="pf-login-socials">
          <a href="#" class="pf-login-social pf-fb" data-login="facebook">
            <svg class="pf-login-icon" viewBox="0 0 32 32"><path d="M19 6h5v-6h-5c-3.86 0-7 3.14-7 7v3h-4v6h4v16h6v-16h5l1-6h-6v-3c0-0.542 0.458-1 1-1z"></path></svg>
            Continue with Facebook
          </a>
          <a href="#" class="pf-login-social pf-go" data-login="google">
            <svg class="pf-login-icon" viewBox="0 0 32 32"><path d="M16.319 13.713v5.487h9.075c-0.369 2.356-2.744 6.9-9.075 6.9-5.463 0-9.919-4.525-9.919-10.1s4.456-10.1 9.919-10.1c3.106 0 5.188 1.325 6.375 2.469l4.344-4.181c-2.788-2.612-6.4-4.188-10.719-4.188-8.844 0-16 7.156-16 16s7.156 16 16 16c9.231 0 15.363-6.494 15.363-15.631 0-1.050-0.113-1.85-0.25-2.65l-15.113-0.006z"></path></svg>
            Continue with Google
          </a>
          <a href="#" class="pf-login-social pf-re" data-login="reddit">
            <svg class="pf-login-icon" viewBox="0 0 32 32"><path d="M28 13.219c0 1.219-0.688 2.266-1.703 2.781 0.125 0.484 0.187 0.984 0.187 1.5 0 4.937-5.578 8.937-12.453 8.937-6.859 0-12.437-4-12.437-8.937 0-0.5 0.063-1 0.172-1.469-1.047-0.516-1.766-1.578-1.766-2.812 0-1.719 1.391-3.109 3.109-3.109 0.891 0 1.687 0.375 2.266 0.984 2.109-1.469 4.922-2.422 8.047-2.531l1.813-8.141c0.063-0.281 0.359-0.469 0.641-0.406l5.766 1.266c0.375-0.75 1.172-1.281 2.078-1.281 1.297 0 2.344 1.047 2.344 2.328 0 1.297-1.047 2.344-2.344 2.344-1.281 0-2.328-1.047-2.328-2.328l-5.219-1.156-1.625 7.375c3.141 0.094 5.984 1.031 8.109 2.5 0.562-0.594 1.359-0.953 2.234-0.953 1.719 0 3.109 1.391 3.109 3.109zM6.531 16.328c0 1.297 1.047 2.344 2.328 2.344 1.297 0 2.344-1.047 2.344-2.344 0-1.281-1.047-2.328-2.344-2.328-1.281 0-2.328 1.047-2.328 2.328zM19.187 21.875c0.234-0.234 0.234-0.578 0-0.812-0.219-0.219-0.578-0.219-0.797 0-0.938 0.953-2.953 1.281-4.391 1.281s-3.453-0.328-4.391-1.281c-0.219-0.219-0.578-0.219-0.797 0-0.234 0.219-0.234 0.578 0 0.812 1.484 1.484 4.344 1.594 5.187 1.594s3.703-0.109 5.187-1.594zM19.141 18.672c1.281 0 2.328-1.047 2.328-2.344 0-1.281-1.047-2.328-2.328-2.328-1.297 0-2.344 1.047-2.344 2.328 0 1.297 1.047 2.344 2.344 2.344z"></path></svg>
            Continue with Reddit
          </a>
        </div>
        <div class="pf-login-break">
          <span>or</span>
        </div>
        <button class="pf-btn pf-continue">Continue with e-mail</button>
        <div class="pf-disclaimer">
		By continuing, you accept ERideHero's <a target="_blank" href="https://eridehero.com/privacy-policy/">Privacy Policy</a>, <a target="_blank" href="https://eridehero.com/opt-out-preferences/">Cookie Policy</a> and <a target="_blank" href="https://eridehero.com/terms-conditions/">Terms & Conditions</a>.
        </div>
      </div>
    `;

    showModal(loginFormHtml, 'Login or Register');
    initializeLoginForm();
  }
  
    function removeAllLoadingIndicators() {
		document.querySelectorAll('.pf-loading').forEach(el => {
		  el.classList.remove('pf-loading');
		  el.disabled = false;
		});
	  }
  
 function initializeLoginForm() {
    const modal = document.getElementById('pf-modal');
    const continueWithEmailBtn = modal.querySelector('.pf-continue');
    const socialLoginButtons = modal.querySelectorAll('.pf-login-social');

    if (continueWithEmailBtn) {
      continueWithEmailBtn.addEventListener('click', showEmailForm);
    }

    socialLoginButtons.forEach(button => {
      button.addEventListener('click', function(e) {
        e.preventDefault();
        const loginType = this.getAttribute('data-login');
        const currentUrl = encodeURIComponent(window.location.href);
        const url = `https://eridehero.com/ML6pXX0UIX322323dfs/?loginSocial=${loginType}&redirect=${currentUrl}`;
        
        const width = 600;
        const height = 600;
        const left = (window.innerWidth - width) / 2;
        const top = (window.innerHeight - height) / 2;
        
        window.open(url, 'LoginPopup', `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`);
      });
    });
  }

function showEmailForm() {
  const loginFormHtml = `
    <form id="pf-login-form" class="pf-wp-form">
	<div class="pf-txt">Not a member? <a href="#" id="pf-register-link">Register here</a>.</div>
      <p>
        <label for="pf-user-login">Username or Email Address</label>
        <input type="text" name="log" id="pf-user-login" class="pf-input" required>
      </p>
      <p>
        <label for="pf-user-pass">Password</label>
        <input type="password" name="pwd" id="pf-user-pass" class="pf-input" required>
      </p>
      <p class="pf-forgetmenot">
        <label>
          <input name="rememberme" type="checkbox" id="pf-rememberme" value="forever"> Remember Me
        </label>
      </p>
      <p class="pf-submit">
        <button type="submit" name="wp-submit" id="pf-wp-submit" class="pf-btn pf-button-primary">Log In</button>
      </p>
    </form>
    <div class="pf-txt"><a href="#" id="pf-lostpassword-link">Lost your password?</a></div>
  `;

  showModal(loginFormHtml, 'Log In');
  initializeLoginFormEvents();
}

function initializeLoginFormEvents() {
  const form = document.getElementById('pf-login-form');
  const registerLink = document.getElementById('pf-register-link');
  const lostPasswordLink = document.getElementById('pf-lostpassword-link');

  form.addEventListener('submit', handleLogin);
  registerLink.addEventListener('click', showRegisterForm);
  lostPasswordLink.addEventListener('click', showLostPasswordForm);
}

function handleLogin(event) {
  event.preventDefault();
  const form = event.target;
  const formData = new FormData(form);
  
  formData.append('_wpnonce', pf_ajax.nonce);
  formData.append('action', 'pf_login');

  const submitButton = form.querySelector('button[type="submit"]');
  ProductFunctionality.showLoadingIndicator(submitButton);

  ProductFunctionality.ajaxRequest(
    pf_ajax.ajax_url,
    'POST',
    formData,
    function(response) {
      ProductFunctionality.hideLoadingIndicator(submitButton);
      if (response.success) {
        ProductFunctionality.showSnackbar('Logged in successfully', 3000, 'success');
        ProductFunctionality.closeModal();
        window.location.reload();
      } else {
        ProductFunctionality.showSnackbar('Login failed. Please try again.', 3000, 'failed');
      }
    },
    function(error) {
      ProductFunctionality.hideLoadingIndicator(submitButton);
      ProductFunctionality.showSnackbar('An error occurred. Please try again.', 3000, 'failed');
    }
  );
}

function showRegisterForm(event) {
  event.preventDefault();
  const registerFormHtml = `
    <form id="pf-register-form" class="pf-wp-form">
      <p>
        <label for="pf-user-login">Username</label>
        <input type="text" name="user_login" id="pf-user-login" class="pf-input" required>
      </p>
      <p>
        <label for="pf-user-email">Email</label>
        <input type="email" name="user_email" id="pf-user-email" class="pf-input" required>
      </p>
      <p>
        <label for="pf-user-pass">Password</label>
        <input type="password" name="user_pass" id="pf-user-pass" class="pf-input" required>
      </p>
      <input type="text" name="website" id="pf-website" style="display:none">
      <p class="pf-submit">
        <button type="submit" name="wp-submit" id="pf-wp-submit" class="pf-btn pf-button-primary">Register</button>
      </p>
    </form>
    <p class="pf-txt">Already have an account?
      <a href="#" id="pf-login-link">Log in</a>
    </p>
  `;

  ProductFunctionality.showModal(registerFormHtml, 'Register');
  initializeRegisterFormEvents();
}

function initializeRegisterFormEvents() {
  const form = document.getElementById('pf-register-form');
  const loginLink = document.getElementById('pf-login-link');

  form.addEventListener('submit', handleRegister);
  loginLink.addEventListener('click', showEmailForm);
}

function handleRegister(event) {
  event.preventDefault();
  const form = event.target;
  const formData = new FormData(form);
  
  // Add the nonce and action to the form data
  formData.append('_wpnonce', pf_ajax.nonce);
  formData.append('action', 'pf_register');

  // Show loading indicator
  const submitButton = form.querySelector('button[type="submit"]');
  ProductFunctionality.showLoadingIndicator(submitButton);

  // Make the AJAX request
  fetch(pf_ajax.ajax_url, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin'
  })
  .then(response => response.json())
  .then(data => {
    ProductFunctionality.hideLoadingIndicator(submitButton);
    if (data.success) {
      ProductFunctionality.showSnackbar(data.data.message, 5000, 'success');
      ProductFunctionality.closeModal();
      
      // Set a flag in localStorage to indicate successful registration
      localStorage.setItem('justRegistered', 'true');
      
      // Refresh the page
      window.location.reload();
    } else {
      ProductFunctionality.showSnackbar('Registration failed. Please try again.', 5000, 'failed');
    }
  })
  .catch(error => {
    ProductFunctionality.hideLoadingIndicator(submitButton);
    ProductFunctionality.showSnackbar('An error occurred. Please try again.', 5000, 'failed');
  });
}

function showLostPasswordForm(event) {
  event.preventDefault();
  const lostPasswordFormHtml = `
    <form id="pf-lostpassword-form" class="pf-wp-form">
      <p>
        <label for="pf-user-login">Username or Email Address</label>
        <input type="text" name="user_login" id="pf-user-login" class="pf-input" required>
      </p>
      <p class="pf-submit">
        <button type="submit" name="wp-submit" id="pf-wp-submit" class="pf-btn pf-button-primary">Get New Password</button>
      </p>
    </form>
    <p class="pf-txt">Or go back to
      <a href="#" id="pf-login-link">Log in</a>.
    </p>
  `;

  showModal(lostPasswordFormHtml, 'Lost Password');
  initializeLostPasswordFormEvents();
}

function initializeLostPasswordFormEvents() {
  const form = document.getElementById('pf-lostpassword-form');
  const loginLink = document.getElementById('pf-login-link');

  form.addEventListener('submit', handleLostPassword);
  loginLink.addEventListener('click', showEmailForm);
}

function handleLostPassword(event) {
  event.preventDefault();
  const form = event.target;
  const formData = new FormData(form);
  
  formData.append('_wpnonce', pf_ajax.nonce);
  formData.append('action', 'pf_lost_password');

  const submitButton = form.querySelector('button[type="submit"]');
  ProductFunctionality.showLoadingIndicator(submitButton);

  ProductFunctionality.ajaxRequest(
    pf_ajax.ajax_url,
    'POST',
    formData,
    function(response) {
      ProductFunctionality.hideLoadingIndicator(submitButton);
      if (response.success) {
        ProductFunctionality.showSnackbar(response.data.message, 5000, 'success');
        ProductFunctionality.closeModal();
      } else {
        ProductFunctionality.showSnackbar('An error occurred. Please try again.', 5000,'failed');
      }
    },
    function(error) {
      ProductFunctionality.hideLoadingIndicator(submitButton);
      ProductFunctionality.showSnackbar('An error occurred. Please try again.', 5000, 'failed');
    }
  );
}

function handleLogout() {
    const formData = new FormData();
    formData.append('action', 'pf_logout');
    formData.append('_wpnonce', pf_ajax.nonce);

    ProductFunctionality.ajaxRequest(
        pf_ajax.ajax_url,
        'POST',
        formData,
        function(response) {
            if (response.success) {
                ProductFunctionality.showSnackbar('Logged out successfully', 3000,'success');
                // Reload the page or redirect as needed
                window.location.reload();
            } else {
                ProductFunctionality.showSnackbar('Logout failed. Please try again.',3000,'failed');
            }
        },
        function(error) {
            ProductFunctionality.showSnackbar('An error occurred. Please try again.',3000,'failed');
        }
    );
}

  // AJAX functionality
function ajaxRequest(url, method, data, successCallback, errorCallback) {
  const xhr = new XMLHttpRequest();
  xhr.open(method, url, true);
  xhr.onload = function() {
    if (xhr.status >= 200 && xhr.status < 300) {
      successCallback(JSON.parse(xhr.responseText));
    } else {
      errorCallback(xhr.statusText);
    }
  };
  xhr.onerror = function() {
    errorCallback('Network error');
  };
  xhr.send(data);
}

  // Debounce function
  function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  // Initialize
  function init() {
    initializeLoadingButtons();
    // Add other initialization functions here
  }

  // Public methods
return {
    showModal,
    closeModal,
    showSnackbar,
    hideSnackbar,
    showLoginForm,
    showLoadingIndicator,
	showSnackbar: function(message, duration, type) {
		showSnackbar(message, duration, type);
	},
    hideLoadingIndicator,
    handleLogout,
    validateForm,
    ajaxRequest,
    debounce,
    init
  };
})();

document.addEventListener('DOMContentLoaded', ProductFunctionality.init);

document.addEventListener('DOMContentLoaded', function() {
    // Check if user just registered
    if (localStorage.getItem('justRegistered') === 'true') {
        localStorage.removeItem('justRegistered');
        ProductFunctionality.showSnackbar('Welcome! Your account has been created and you are now logged in.', 5000,'success');
    }
    
    // Check for password reset
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('password_reset') === 'success' && localStorage.getItem('justResetPassword') === 'true') {
        localStorage.removeItem('justResetPassword');
        ProductFunctionality.showSnackbar('Your password has been successfully reset. You are now logged in.', 5000,'success');
        
        // Clean up the URL
        const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
        window.history.replaceState({path: newUrl}, '', newUrl);
    }
	

    const accordionHeaders = document.querySelectorAll('.accordion-header');
    
accordionHeaders.forEach(header => {
        const panel = header.nextElementSibling;
        
        if (header.classList.contains('accordion-active')) {
            panel.style.maxHeight = (panel.scrollHeight + 36) + 'px';
        }
        
        header.addEventListener('click', function() {
            const expanded = this.getAttribute('aria-expanded') === 'true';
            
            this.classList.toggle('accordion-active');
            this.setAttribute('aria-expanded', !expanded);
            
            if (panel.style.maxHeight) {
                panel.style.maxHeight = null;
                panel.classList.remove('open');
            } else {
                panel.style.maxHeight = (panel.scrollHeight + 36) + 'px';
                panel.classList.add('open');
            }
        });
        
        header.addEventListener('keydown', function(e) {
            if (e.key === ' ' || e.key === 'Enter') {
                e.preventDefault();
                this.click();
            }
        });
    });
	
});
