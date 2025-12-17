jQuery(document).ready(function($) {
    'use strict';
    
    // View details
    $('.view-details').on('click', function() {
        const logId = $(this).data('log-id');
        const $modal = $('#log-details-modal');
        const $content = $('#log-details-content');
        
        // Show loading
        $content.html('<div class="spinner is-active"></div>');
        $modal.show();
        
        // Load details
        $.post(hftScraperLogs.ajaxUrl, {
            action: 'hft_get_log_details',
            nonce: hftScraperLogs.nonce,
            log_id: logId
        })
        .done(function(response) {
            if (response.success) {
                $content.html(response.data.html);
            } else {
                $content.html('<p class="error">Failed to load details.</p>');
            }
        })
        .fail(function() {
            $content.html('<p class="error">Request failed.</p>');
        });
    });
    
    // Close modal
    $('.close, #log-details-modal').on('click', function(e) {
        if (e.target === this) {
            $('#log-details-modal').hide();
        }
    });
    
    // Clear old logs
    $('#clear-old-logs').on('click', function() {
        if (!confirm('This will delete all logs older than 30 days. Continue?')) {
            return;
        }
        
        const $button = $(this);
        $button.prop('disabled', true);
        
        $.post(hftScraperLogs.ajaxUrl, {
            action: 'hft_clear_old_logs',
            nonce: hftScraperLogs.nonce
        })
        .done(function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            }
        })
        .always(function() {
            $button.prop('disabled', false);
        });
    });
});