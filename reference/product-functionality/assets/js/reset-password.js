document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('pf-reset-password-form');
    if (form) {
        form.addEventListener('submit', handleResetPassword);
    }
});

function handleResetPassword(e) {
    e.preventDefault();
    const form = e.target;
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;

    // Clear previous error messages
    clearMessages();

    // Validate password
    if (newPassword.length < 8) {
        showError('Password must be at least 8 characters long.');
        return;
    }

    // Check if passwords match
    if (newPassword !== confirmPassword) {
        showError('Passwords do not match.');
        return;
    }

    // Prepare form data
    const formData = new FormData(form);
    formData.append('action', 'pf_reset_password');
    formData.append('_wpnonce', pf_ajax.nonce);

    // Disable submit button and show loading state
    const submitButton = form.querySelector('input[type="submit"]');
    submitButton.disabled = true;
    submitButton.value = 'Processing...';

    // Send AJAX request
    fetch(pf_ajax.ajax_url, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        return response.text();
    })
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                localStorage.setItem('justResetPassword', 'true');
                window.location.href = (data.data.redirect || '/') + '?password_reset=success';
            } else {
                showError(data.data.message || 'An error occurred. Please try again.');
            }
        } catch (error) {
            showError('An error occurred on the server. Please try again later.');
        }
    })
    .catch(error => {
        showError('An error occurred. Please try again.');
    })
    .finally(() => {
        // Re-enable submit button and restore original text
        submitButton.disabled = false;
        submitButton.value = 'Reset Password';
    });
}

function showError(message) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'pf-error-message';
    errorDiv.textContent = message;
    insertMessage(errorDiv);
}

function showSuccess(message) {
    const successDiv = document.createElement('div');
    successDiv.className = 'pf-success-message';
    successDiv.textContent = message;
    insertMessage(successDiv);
}

function insertMessage(messageElement) {
    const form = document.getElementById('pf-reset-password-form');
    form.parentNode.insertBefore(messageElement, form);
}

function clearMessages() {
    const messages = document.querySelectorAll('.pf-error-message, .pf-success-message');
    messages.forEach(message => message.remove());
}