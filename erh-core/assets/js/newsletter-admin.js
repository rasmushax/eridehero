/**
 * Newsletter Admin Scripts
 */
(function($) {
    'use strict';

    const config = window.erhNewsletter || {};

    /**
     * Initialize newsletter admin functionality.
     */
    function init() {
        // Send test email
        $('#erh-send-test').on('click', sendTestEmail);

        // Schedule newsletter
        $('#erh-schedule').on('click', scheduleNewsletter);

        // Send now
        $('#erh-send-now').on('click', sendNow);
    }

    /**
     * Send test email.
     */
    function sendTestEmail() {
        const $button = $(this);
        const $result = $('#erh-test-result');
        const email = $('#erh-test-email').val();

        if (!email) {
            showResult($result, 'error', 'Please enter an email address.');
            return;
        }

        // Check if post has been saved
        if (!config.postId || config.postId === '0') {
            showResult($result, 'error', config.strings.saveDraftFirst);
            return;
        }

        setButtonLoading($button, true);
        showResult($result, 'loading', config.strings.sending);

        $.ajax({
            url: config.ajaxUrl,
            method: 'POST',
            data: {
                action: 'erh_newsletter_send_test',
                nonce: config.nonce,
                post_id: config.postId,
                email: email
            },
            success: function(response) {
                if (response.success) {
                    showResult($result, 'success', config.strings.testSent);
                } else {
                    showResult($result, 'error', response.data?.message || config.strings.testFailed);
                }
            },
            error: function() {
                showResult($result, 'error', config.strings.testFailed);
            },
            complete: function() {
                setButtonLoading($button, false);
            }
        });
    }

    /**
     * Schedule newsletter.
     */
    function scheduleNewsletter() {
        const $button = $(this);
        const $result = $('#erh-schedule-result');
        const datetime = $('#erh-schedule-datetime').val();

        if (!datetime) {
            showResult($result, 'error', config.strings.selectDateTime);
            return;
        }

        // Check if post has been saved
        if (!config.postId || config.postId === '0') {
            showResult($result, 'error', config.strings.saveDraftFirst);
            return;
        }

        setButtonLoading($button, true);
        showResult($result, 'loading', config.strings.scheduling);

        $.ajax({
            url: config.ajaxUrl,
            method: 'POST',
            data: {
                action: 'erh_newsletter_schedule',
                nonce: config.nonce,
                post_id: config.postId,
                datetime: datetime
            },
            success: function(response) {
                if (response.success) {
                    showResult($result, 'success', config.strings.scheduled);
                    // Reload the page to show new status
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showResult($result, 'error', response.data?.message || config.strings.scheduleFailed);
                }
            },
            error: function() {
                showResult($result, 'error', config.strings.scheduleFailed);
            },
            complete: function() {
                setButtonLoading($button, false);
            }
        });
    }

    /**
     * Send newsletter immediately.
     */
    function sendNow() {
        const $button = $(this);
        const $result = $('#erh-send-result');

        // Check if post has been saved
        if (!config.postId || config.postId === '0') {
            showResult($result, 'error', config.strings.saveDraftFirst);
            return;
        }

        // Confirm before sending
        if (!confirm(config.strings.confirmSendNow)) {
            return;
        }

        setButtonLoading($button, true);
        showResult($result, 'loading', config.strings.sendingNow);

        $.ajax({
            url: config.ajaxUrl,
            method: 'POST',
            data: {
                action: 'erh_newsletter_send_now',
                nonce: config.nonce,
                post_id: config.postId
            },
            success: function(response) {
                if (response.success) {
                    showResult($result, 'success', response.data?.message || config.strings.sent);
                    // Reload the page to show new status
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    showResult($result, 'error', response.data?.message || config.strings.sendFailed);
                }
            },
            error: function() {
                showResult($result, 'error', config.strings.sendFailed);
            },
            complete: function() {
                setButtonLoading($button, false);
            }
        });
    }

    /**
     * Show result message.
     *
     * @param {jQuery} $element Result element.
     * @param {string} type     Message type (success, error, loading).
     * @param {string} message  Message text.
     */
    function showResult($element, type, message) {
        $element
            .removeClass('success error loading')
            .addClass(type)
            .text(message);
    }

    /**
     * Set button loading state.
     *
     * @param {jQuery}  $button Button element.
     * @param {boolean} loading Whether loading.
     */
    function setButtonLoading($button, loading) {
        $button.toggleClass('loading', loading);
        $button.prop('disabled', loading);
    }

    // Initialize on document ready
    $(document).ready(init);

})(jQuery);
