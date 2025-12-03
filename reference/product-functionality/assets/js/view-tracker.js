(function() {
    // Only track if user shows real interaction (helps filter bots)
    var hasInteracted = false;
    var viewTracked = false;
    
    // Track the view
    function trackView() {
        if (viewTracked) return;
        
        // Check if already tracked in this session
        var sessionKey = 'viewed_' + view_tracker_ajax.product_id;
        if (sessionStorage.getItem(sessionKey)) {
            return;
        }
        
        // Create form data
        var formData = new FormData();
        formData.append('action', 'track_product_view');
        formData.append('product_id', view_tracker_ajax.product_id);
        
        // Send AJAX request
        fetch(view_tracker_ajax.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(function(response) {
            return response.text();
        })
        .then(function(data) {
            if (data === 'success') {
                viewTracked = true;
                sessionStorage.setItem(sessionKey, '1');
            }
        })
        .catch(function(error) {
            console.error('Error tracking view:', error);
        });
    }
    
    // Track various user interactions
    function trackInteraction() {
        if (!hasInteracted) {
            hasInteracted = true;
            trackView();
        }
    }
    
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        // Track on various interactions (helps filter bots)
        document.addEventListener('mousemove', trackInteraction, { once: true });
        document.addEventListener('scroll', trackInteraction, { once: true });
        document.addEventListener('click', trackInteraction, { once: true });
        document.addEventListener('touchstart', trackInteraction, { once: true });
        
        // Also track after a delay (for users who don't interact immediately)
        setTimeout(function() {
            if (!viewTracked) {
                trackView();
            }
        }, 3000);
    }
})();