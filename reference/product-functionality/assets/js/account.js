// account.js
document.addEventListener('DOMContentLoaded', function() {
    initSettingsForms();
    initSalesRoundupFrequency();
	initPriceTrackersWarning();
});

function initSettingsForms() {
    const changeEmailForm = document.getElementById('pf-change-email-form');
    const changePasswordForm = document.getElementById('pf-change-password-form');
    const emailPreferencesForm = document.getElementById('pf-email-preferences-form');

    if (changeEmailForm) {
        changeEmailForm.addEventListener('submit', handleChangeEmail);
    }
    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', handleChangePassword);
    }
    if (emailPreferencesForm) {
        emailPreferencesForm.addEventListener('submit', handleEmailPreferences);
    }
}

function initSalesRoundupFrequency() {
    const salesRoundupCheckbox = document.getElementById('pf-sales-roundup');
    const salesRoundupFrequency = document.getElementById('pf-sales-roundup-frequency');
    
    if (salesRoundupCheckbox && salesRoundupFrequency) {
        salesRoundupCheckbox.addEventListener('change', function() {
            salesRoundupFrequency.style.display = this.checked ? 'block' : 'none';
        });
        // Trigger the change event on page load
        salesRoundupCheckbox.dispatchEvent(new Event('change'));
    }
}

function initPriceTrackersWarning() {
    const priceTrackersCheckbox = document.getElementById('pf-price-trackers');
    const warningMessage = document.getElementById('pf-price-trackers-warning');
    const form = document.getElementById('pf-email-preferences-form');

    if (priceTrackersCheckbox && warningMessage && form) {
        const initialCheckedState = priceTrackersCheckbox.checked;

        priceTrackersCheckbox.addEventListener('change', function() {
            if (!this.checked) {
                warningMessage.style.display = 'block';
            }
        });

        form.addEventListener('reset', function() {
            priceTrackersCheckbox.checked = initialCheckedState;
            updateWarningVisibility();
        });
    }
}

function updateWarningVisibility() {
    const priceTrackersCheckbox = document.getElementById('pf-price-trackers');
    const warningMessage = document.getElementById('pf-price-trackers-warning');
    
    if (priceTrackersCheckbox && warningMessage) {
        warningMessage.style.display = priceTrackersCheckbox.checked ? 'none' : 'block';
    }
}


function handleFormSubmit(event, actionType, formData) {
    const form = event.target;
    const submitButton = form.querySelector('button[type="submit"]');

    // Add loading class
    submitButton.classList.add('pf-loading');
    submitButton.disabled = true;

    sendAjaxRequest(pfResetVars.ajaxUrl, formData, function(response) {
        // Remove loading class
        submitButton.classList.remove('pf-loading');
        submitButton.disabled = false;

        let successMessage = 'Action completed successfully';
        if (response.data && response.data.message) {
            successMessage = response.data.message;
        } else if (response.message) {
            successMessage = response.message;
        }
        ProductFunctionality.showSnackbar(successMessage, 3000, 'success');
    }, function(error) {
        // Remove loading class on error as well
        submitButton.classList.remove('pf-loading');
        submitButton.disabled = false;

        ProductFunctionality.showSnackbar(error.message || 'An error occurred. Please try again.', 3000, 'failed');
    });
}

function handleChangeEmail(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    // Log form data
    for (let [key, value] of formData.entries()) {
        console.log(key, value);
    }

    formData.append('action', 'pf_change_email');
    formData.append('_wpnonce', pfResetVars.nonce);

    handleFormSubmit(event, 'pf_change_email', formData);
}

function isValidEmail(email) {
    const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
}

function handleChangePassword(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'pf_change_password'); // Make sure this matches the PHP hook
    formData.append('_wpnonce', pfResetVars.nonce);

    handleFormSubmit(event, 'pf_change_password', formData);
}

function handleEmailPreferences(event) {
	event.preventDefault();
    const form = event.target;
    const formData = new FormData();

    // Add action and nonce
    formData.append('action', 'pf_update_email_preferences');
    formData.append('_wpnonce', pfResetVars.nonce);

    // Price trackers emails
    formData.append('price_trackers', form.querySelector('#pf-price-trackers').checked ? '1' : '0');

    // Sales roundup emails
    formData.append('sales_roundup', form.querySelector('#pf-sales-roundup').checked ? '1' : '0');

    // Sales roundup frequency
    const frequencySelect = form.querySelector('select[name="sales_roundup_frequency"]');
    formData.append('sales_roundup_frequency', frequencySelect.value);

    // Newsletter subscription
    formData.append('newsletter', form.querySelector('#pf-newsletter').checked ? '1' : '0');

    // Log form data
    for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }
	
	handleFormSubmit(event, 'pf_update_email_preferences', formData, function(response) {
        if (response.success) {
            updateWarningVisibility();
        }
    });
	
}

function sendAjaxRequest(url, formData, successCallback, errorCallback) {
    console.log('Sending request to:', url);
    console.log('Form data:', Object.fromEntries(formData));

    fetch(url, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON:', text);
                throw new Error('Invalid JSON response from server');
            }
        });
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            successCallback(data);
        } else {
            let errorMessage = 'An unknown error occurred';
            if (data.data && data.data.message) {
                errorMessage = data.data.message;
            } else if (data.message) {
                errorMessage = data.message;
            } else if (typeof data === 'string') {
                errorMessage = data;
            }
            throw new Error(errorMessage);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        errorCallback(error);
    });
}