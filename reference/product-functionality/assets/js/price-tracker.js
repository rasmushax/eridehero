const PriceTracker = (function() {
function initializeTrackPriceButton() {
    document.body.addEventListener('click', function(event) {
      const trackPriceBtn = event.target.closest('.pf-track-price, .comparison-track-product, .pricetracker');
      if (trackPriceBtn) {
        event.preventDefault();
        const isLoggedIn = document.body.classList.contains('logged-in');

        if (isLoggedIn) {
          handleTrackPriceClick(trackPriceBtn);
        } else {
          ProductFunctionality.showLoginForm();
        }
      }
    });
  }

function handleTrackPriceClick(button) {
  const isComparisonPage = button.classList.contains('comparison-track-product');
  const isStickyBar = button.classList.contains('pricetracker');
  
  if (isComparisonPage) {
    const productId = button.dataset.id;
    const svgElement = button.querySelector('svg use');
    const originalIcon = svgElement.getAttribute('xlink:href');
    
    // Change to spinner icon
    svgElement.setAttribute('xlink:href', '#icon-loader');
    button.classList.add('loading');
    
    // First, check if price data is available
    checkPriceData(productId)
      .then(priceData => {
        if (priceData.has_price_data) {
          // Now check if user has a tracker for this product
          return checkPriceTracker(productId, priceData.current_price);
        } else {
          throw new Error('No price data available for this product');
        }
      })
      .then(trackerData => {
        // Change back to original icon
        svgElement.setAttribute('xlink:href', originalIcon);
        button.classList.remove('loading');
        
        showPriceTrackerForm(trackerData);
      })
      .catch(error => {
        // Change back to original icon
        svgElement.setAttribute('xlink:href', originalIcon);
        button.classList.remove('loading');
        
        ProductFunctionality.showSnackbar(error.message, 3000, 'failed');
      });
	  } else if (isStickyBar) {
      const productId = button.dataset.productId;
      const currentPrice = parseFloat(button.dataset.price);
      const hasTracker = button.classList.contains('has-tracker');
      const targetPrice = button.dataset.targetPrice ? parseFloat(button.dataset.targetPrice) : null;
      const priceDrop = button.dataset.priceDrop ? parseFloat(button.dataset.priceDrop) : null;
      const trackerEmails = button.dataset.trackerEmails === '0';
      
      if (hasTracker) {
        showEditTrackerForm(productId, currentPrice, targetPrice, priceDrop, false, trackerEmails);
      } else {
        showNewTrackerForm(productId, currentPrice, trackerEmails);
      }
    
  } else {
    // Handle original pf-track-price button
    const tempButton = {
      dataset: {
        productId: button.dataset.productId,
        price: button.dataset.price,
        targetPrice: button.dataset.targetPrice,
        priceDrop: button.dataset.priceDrop,
        trackerEmails: button.dataset.trackerEmails
      },
      classList: {
        contains: (className) => className === 'has-tracker' ? button.classList.contains('has-tracker') : false
      }
    };
    showPriceTrackerForm(tempButton, false);
  }
}

function checkPriceData(productId) {
  return new Promise((resolve, reject) => {
    const formData = new FormData();
    formData.append('action', 'pf_check_price_data');
    formData.append('product_id', productId);
    formData.append('_wpnonce', pf_ajax.nonce);

    ProductFunctionality.ajaxRequest(
      pf_ajax.ajax_url,
      'POST',
      formData,
      function(response) {
        if (response.success) {
          resolve(response.data);
        } else {
          reject(new Error(response.data || 'Failed to fetch price data'));
        }
      },
      function(error) {
        reject(new Error('An error occurred while fetching price data'));
      }
    );
  });
}

function checkPriceTracker(productId, currentPrice) {
  return new Promise((resolve, reject) => {
    const formData = new FormData();
    formData.append('action', 'pf_check_price_tracker');
    formData.append('product_id', productId);
    formData.append('_wpnonce', pf_ajax.nonce);

    ProductFunctionality.ajaxRequest(
      pf_ajax.ajax_url,
      'POST',
      formData,
      function(response) {
        if (response.success) {
          const trackerData = {
            dataset: {
              productId: productId,
              price: currentPrice,
              targetPrice: response.data.has_tracker ? response.data.tracker.target_price : null,
              priceDrop: response.data.has_tracker ? response.data.tracker.price_drop : null,
              trackerEmails: response.data.email_alerts_enabled ? '1' : '0'
            },
            classList: {
              contains: (className) => className === 'has-tracker' ? response.data.has_tracker : false
            }
          };
          resolve(trackerData);
        } else {
          reject(new Error(response.data || 'Failed to fetch tracker data'));
        }
      },
      function(error) {
        reject(new Error('An error occurred while fetching tracker data'));
      }
    );
  });
}

function showPriceTrackerForm(button, isAccountPage = false) {
  const productId = button.dataset.productId;
  const currentPrice = parseFloat(button.dataset.price);
  const hasTracker = button.classList.contains('has-tracker');
  const needsEmailPermission = button.dataset.trackerEmails === '0';
  
  if (hasTracker) {
    const targetPrice = parseFloat(button.dataset.targetPrice);
    const priceDrop = parseFloat(button.dataset.priceDrop);
    showEditTrackerForm(productId, currentPrice, targetPrice, priceDrop, isAccountPage, needsEmailPermission);
  } else {
    showNewTrackerForm(productId, currentPrice, needsEmailPermission);
  }
}

  function showNewTrackerForm(productId, currentPrice, needsEmailPermission = false) {
    const formHtml = `
      <form id="pf-price-tracker-form">
        <input type="hidden" name="product_id" value="${productId}">
        <input type="hidden" name="current_price" value="${currentPrice}">
        <div class="pf-tracker-option">
          <input type="radio" id="price_drop" name="tracker_type" value="price_drop" checked hidden>
          <label for="price_drop" class="pf-checkbox-label">
            <span class="pf-custom-checkbox"></span>
            Notify me when the price drops by
          </label>
          <span>$</span>
          <input type="number" name="price_drop" step="0.01" min="0" max="${currentPrice}" value="10" required>
          <span>USD</span>
        </div>
        <div class="pf-tracker-option">
          <input type="radio" id="target_price" name="tracker_type" value="target_price" hidden>
          <label for="target_price" class="pf-checkbox-label">
            <span class="pf-custom-checkbox"></span>
            Notify me when the price reaches
          </label>
          <span>$</span>
          <input type="number" name="target_price" step="0.01" min="0" max="${currentPrice}" disabled required>
          <span>USD</span>
        </div>
		${needsEmailPermission ? `
        <div class="pf-tracker-email-permission">
          <input type="checkbox" id="tracker_email_permission" name="tracker_email_permission" required>
          <label for="tracker_email_permission">I agree to receive email notifications for price changes</label>
        </div>
      ` : ''}
        <button class="pfbtn" type="submit">Add Tracker</button>
      </form>
    `;
    ProductFunctionality.showModal(formHtml, 'Set Price Tracker');
    initializeTrackerForm(productId);
  }

 function showEditTrackerForm(productId, currentPrice, targetPrice, priceDrop, isAccountPage = false, needsEmailPermission = false) {
	 hideAllDropdowns();
    const formHtml = `
      <form id="pf-price-tracker-form">
        <input type="hidden" name="product_id" value="${productId}">
        <input type="hidden" name="current_price" value="${currentPrice}">
        <div class="pf-tracker-option">
          <input type="radio" id="price_drop" name="tracker_type" value="price_drop" ${priceDrop ? 'checked' : ''} hidden>
          <label for="price_drop" class="pf-checkbox-label">
            <span class="pf-custom-checkbox"></span>
            Notify me when the price drops by
          </label>
          <span>$</span>
          <input type="number" name="price_drop" step="0.01" min="0" max="${currentPrice}" value="${priceDrop || ''}" ${priceDrop ? '' : 'disabled'} required>
          <span>USD</span>
        </div>
        <div class="pf-tracker-option">
          <input type="radio" id="target_price" name="tracker_type" value="target_price" ${targetPrice ? 'checked' : ''} hidden>
          <label for="target_price" class="pf-checkbox-label">
            <span class="pf-custom-checkbox"></span>
            Notify me when the price reaches
          </label>
          <span>$</span>
          <input type="number" name="target_price" step="0.01" min="0" max="${currentPrice}" value="${targetPrice || ''}" ${targetPrice ? '' : 'disabled'} required>
          <span>USD</span>
        </div>
		${needsEmailPermission ? `
        <div class="pf-tracker-email-permission">
          <input type="checkbox" id="tracker_email_permission" name="tracker_email_permission" required>
          <label for="tracker_email_permission">I agree to receive email notifications for price changes</label>
        </div>
      ` : ''}
        <button class="pfbtn" type="submit">Update</button>
        ${!isAccountPage ? `<button class="pfbtn pfbtn-delete" type="button" data-product-id="${productId}">Delete Tracker</button>` : ''}
      </form>
    `;
    ProductFunctionality.showModal(formHtml, 'Edit Price Tracker');
    initializeTrackerForm(productId);
  }

function initializeTrackerForm(productId) {
    const form = document.getElementById('pf-price-tracker-form');
    const priceDropRadio = form.querySelector('#price_drop');
    const targetPriceRadio = form.querySelector('#target_price');
    const priceDropInput = form.querySelector('input[name="price_drop"]');
    const targetPriceInput = form.querySelector('input[name="target_price"]');
    const deleteButton = form.querySelector('.pfbtn-delete');

    function updateInputs() {
      priceDropInput.disabled = !priceDropRadio.checked;
      targetPriceInput.disabled = !targetPriceRadio.checked;
    }

    priceDropRadio.addEventListener('change', updateInputs);
    targetPriceRadio.addEventListener('change', updateInputs);

    form.addEventListener('submit', handleTrackerSubmit);
    
    if (deleteButton) {
      deleteButton.addEventListener('click', function() {
        handleTrackerDelete(productId);
      });
    }
  }

function handleTrackerSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const formData = new FormData(form);
  const productId = formData.get('product_id');
  const trackPriceBtn = document.querySelector(`.pf-track-price[data-product-id="${productId}"]`);
  
  if (trackPriceBtn && trackPriceBtn.dataset.trackerEmail === '0') {
    const emailPermission = form.querySelector('#tracker_email_permission');
    if (!emailPermission || !emailPermission.checked) {
      ProductFunctionality.showSnackbar('Please agree to receive email notifications', 3000, 'failed');
      return;
    }
  }

  formData.append('action', 'pf_set_price_tracker');
  formData.append('_wpnonce', pf_ajax.nonce);

  const submitButton = form.querySelector('button[type="submit"]');
  ProductFunctionality.showLoadingIndicator(submitButton);

  ProductFunctionality.ajaxRequest(
    pf_ajax.ajax_url,
    'POST',
    formData,
    function(response) {
      ProductFunctionality.hideLoadingIndicator(submitButton);
      if (response.success) {
        ProductFunctionality.showSnackbar('Price tracker set successfully', 3000, 'success');
        ProductFunctionality.closeModal();
        updateTrackPriceButton(form);
		document.querySelectorAll('.pf-track-price[data-tracker-emails="0"], .tracker-more[data-tracker-emails="0"]').forEach(button => {
          button.removeAttribute('data-tracker-emails');
        });
      } else {
        ProductFunctionality.showSnackbar(response.data || 'Failed to set price tracker. Please try again.', 3000, 'failed');
      }
    },
    function(error) {
      ProductFunctionality.hideLoadingIndicator(submitButton);
      ProductFunctionality.showSnackbar('An error occurred. Please try again.', 3000, 'failed');
    }
  );
}

function updateTrackPriceButton(form) {
    const productId = form.querySelector('input[name="product_id"]').value;
    const trackerType = form.querySelector('input[name="tracker_type"]:checked').value;
    const trackerValue = trackerType === 'target_price' 
      ? form.querySelector('input[name="target_price"]').value
      : form.querySelector('input[name="price_drop"]').value;

    // Update all .pf-track-price buttons for this product
    const buttons = document.querySelectorAll(`.pf-track-price[data-product-id="${productId}"], .pricetracker[data-product-id="${productId}"]`);
    buttons.forEach(button => {
      button.classList.add('has-tracker');
      if (button.classList.contains('pricetracker')) {
        button.innerHTML = '<svg><use xlink:href="#icon-bell"></use></svg><span>Edit Price </span>Tracker';
      } else {
        button.innerHTML = '<svg class="p-t-icon"><use xlink:href="#icon-bell"></use></svg> Edit Price Tracker';
      }
      if (trackerType === 'target_price') {
        button.dataset.targetPrice = trackerValue;
        button.removeAttribute('data-price-drop');
      } else {
        button.dataset.priceDrop = trackerValue;
        button.removeAttribute('data-target-price');
      }
      button.removeAttribute('data-tracker-emails');
    });

    // Update tracker more button if it exists
    const trackerMoreButton = document.querySelector(`.tracker-more[data-product-id="${productId}"]`);
    if (trackerMoreButton) {
      if (trackerType === 'target_price') {
        trackerMoreButton.dataset.targetPrice = trackerValue;
        trackerMoreButton.removeAttribute('data-price-drop');
      } else {
        trackerMoreButton.dataset.priceDrop = trackerValue;
        trackerMoreButton.removeAttribute('data-target-price');
      }
      trackerMoreButton.removeAttribute('data-tracker-emails');

      const trackerTypeCell = trackerMoreButton.closest('tr').querySelector('.tracker-type');
      if (trackerTypeCell) {
        trackerTypeCell.textContent = trackerType === 'target_price' 
          ? `Price: $${parseFloat(trackerValue).toFixed(2)}`
          : `Drop: $${parseFloat(trackerValue).toFixed(2)}`;
      }
    }

    // Remove data-tracker-emails from all buttons
    document.querySelectorAll('.pf-track-price[data-tracker-emails="0"], .tracker-more[data-tracker-emails="0"]').forEach(button => {
      button.removeAttribute('data-tracker-emails');
    });
}
  
  function handleTrackerDelete(productId) {
	  hideAllDropdowns();
    if (confirm('Are you sure you want to delete this price tracker?')) {
      const formData = new FormData();
      formData.append('action', 'pf_delete_price_tracker');
      formData.append('product_id', productId);
      formData.append('_wpnonce', pf_ajax.nonce);

      ProductFunctionality.ajaxRequest(
        pf_ajax.ajax_url,
        'POST',
        formData,
        function(response) {
          if (response.success) {
            ProductFunctionality.showSnackbar('Price tracker deleted successfully', 3000, 'success');
            ProductFunctionality.closeModal();
            removeTrackPriceButton(productId);
          } else {
            ProductFunctionality.showSnackbar(response.data || 'Failed to delete price tracker. Please try again.', 3000, 'failed');
          }
        },
        function(error) {
          ProductFunctionality.showSnackbar('An error occurred. Please try again.', 3000, 'failed');
        }
      );
    }
  }

function removeTrackPriceButton(productId) {
    // Update all .pf-track-price buttons for this product
    const buttons = document.querySelectorAll(`.pf-track-price[data-product-id="${productId}"], .pricetracker[data-product-id="${productId}"]`);
    buttons.forEach(button => {
      button.classList.remove('has-tracker');
      if (button.classList.contains('pricetracker')) {
        button.innerHTML = '<svg><use xlink:href="#icon-bell"></use></svg><span>Price </span>Tracker';
      } else {
        button.innerHTML = '<svg class="p-t-icon"><use xlink:href="#icon-bell"></use></svg> Track Price';
      }
      button.removeAttribute('data-target-price');
      button.removeAttribute('data-price-drop');
      button.removeAttribute('data-tracker-emails');
    });

    // Remove the tracker row from the table if it exists
    const row = document.querySelector(`.tracker-more[data-product-id="${productId}"]`);
    if (row) {
        const tableRow = row.closest('tr');
        if (tableRow) {
            tableRow.remove();
        }
    }

    // If this was the last tracker, you might want to update the UI accordingly
    updateUIAfterTrackerRemoval();
}

function updateUIAfterTrackerRemoval() {
    const remainingTrackers = document.querySelectorAll('.tracker-more');
    if (remainingTrackers.length === 0) {
        // No trackers left, you might want to show a message or update the UI
        const trackersTable = document.getElementById('trackers');
        if (trackersTable) {
            trackersTable.style.display = 'none';
            const noTrackersMessage = document.createElement('p');
            noTrackersMessage.textContent = 'You have no active price trackers.';
            trackersTable.parentNode.insertBefore(noTrackersMessage, trackersTable.nextSibling);
        }
    }
}
  
  function formatPrice(price) {
    return '$' + parseFloat(price).toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function initializeDataTable() {
    const trackersTable = document.getElementById('trackers');
    if (trackersTable) {
      new DataTable('#trackers', {
        columnDefs: [
          {
            targets: '.price-column',
            render: function(data, type, row) {
              if (type === 'sort' || type === 'type') {
                return parseFloat(data);
              }
              return formatPrice(data);
            }
          },
          {
            targets: [-2,-1],
            orderable: false
          }
        ],
        lengthChange: false,
        pageLength: 20,
        language: {
          "emptyTable": "No trackers found",
          "info": "Showing _START_ to _END_ of _TOTAL_ trackers",
          "infoEmpty": "Showing 0 to 0 of 0 trackers",
          "infoFiltered": "(filtered from _MAX_ total trackers)",
          "zeroRecords": "No matching trackers",
          "search": "Search",
          "searchPlaceholder": "Find a tracker..."
        }
      });
    }
  }

function initializeTrackerMoreButtons() {
  document.addEventListener('click', function(event) {
    const moreButton = event.target.closest('.tracker-more');
    if (moreButton) {
      event.preventDefault();
      toggleTrackerOptions(moreButton);
    } else if (!event.target.closest('.tracker-dropdown')) {
      hideAllDropdowns();
    }
  });
}

function toggleTrackerOptions(button) {
  const existingDropdown = button.nextElementSibling;
  if (existingDropdown && existingDropdown.classList.contains('tracker-dropdown')) {
    existingDropdown.remove();
  } else {
    hideAllDropdowns();
    showTrackerOptions(button);
  }
}

function showTrackerOptions(button) {
  const dropdown = document.createElement('div');
  dropdown.className = 'tracker-dropdown';
  dropdown.innerHTML = `
    <button class="tracker-edit">Edit</button>
    <button class="tracker-delete">Delete</button>
  `;

  button.parentNode.appendChild(dropdown);

  dropdown.querySelector('.tracker-edit').addEventListener('click', function() {
    hideAllDropdowns();
    const needsEmailPermission = button.dataset.trackerEmails === '0';
    showEditTrackerForm(button.dataset.productId, button.dataset.price, button.dataset.targetPrice, button.dataset.priceDrop, true, needsEmailPermission);
  });

  dropdown.querySelector('.tracker-delete').addEventListener('click', function() {
    hideAllDropdowns();
    handleTrackerDelete(button.dataset.productId);
  });
}

 function hideAllDropdowns() {
  document.querySelectorAll('.tracker-dropdown').forEach(dropdown => {
    dropdown.remove();
  });
}

  function init() {
    initializeTrackPriceButton();
    initializeDataTable();
    initializeTrackerMoreButtons();
  }

  return {
    init: init
  };
})();

document.addEventListener('DOMContentLoaded', PriceTracker.init);