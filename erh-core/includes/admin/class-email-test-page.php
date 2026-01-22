<?php
/**
 * Email Test Page - Admin page for testing email templates.
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

use ERH\Email\DealsDigestTemplate;
use ERH\Email\WelcomeEmailTemplate;
use ERH\Email\PasswordResetTemplate;
use ERH\Email\PriceAlertTemplate;
use ERH\Pricing\DealsFinder;
use ERH\GeoConfig;

/**
 * Admin page for testing email templates before sending to users.
 */
class EmailTestPage {

    /**
     * Page slug.
     */
    public const PAGE_SLUG = 'erh-email-test';

    /**
     * Log entries for current request.
     *
     * @var array<array{time: string, level: string, message: string}>
     */
    private array $logs = [];

    /**
     * Add a log entry.
     *
     * @param string $message Log message.
     * @param string $level   Log level (info, success, warning, error).
     * @return void
     */
    private function log(string $message, string $level = 'info'): void {
        $this->logs[] = [
            'time'    => gmdate('H:i:s'),
            'level'   => $level,
            'message' => $message,
        ];
    }

    /**
     * Get all log entries.
     *
     * @return array<array{time: string, level: string, message: string}>
     */
    private function get_logs(): array {
        return $this->logs;
    }

    /**
     * Clear log entries.
     *
     * @return void
     */
    private function clear_logs(): void {
        $this->logs = [];
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('wp_ajax_erh_send_test_email', [$this, 'ajax_send_test_email']);
        add_action('wp_ajax_erh_preview_email', [$this, 'ajax_preview_email']);
    }

    /**
     * Add the admin menu page.
     *
     * @return void
     */
    public function add_menu_page(): void {
        add_submenu_page(
            'tools.php',
            __('Email Testing', 'erh-core'),
            __('Email Testing', 'erh-core'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    /**
     * Render the admin page.
     *
     * @return void
     */
    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $current_user = wp_get_current_user();
        $default_email = $current_user->user_email;

        // Available email templates.
        $templates = [
            'deals_digest'   => __('Deals Digest', 'erh-core'),
            'welcome'        => __('Welcome Email', 'erh-core'),
            'password_reset' => __('Password Reset', 'erh-core'),
            'price_alert'    => __('Price Alert', 'erh-core'),
        ];

        // Available geos.
        $geos = GeoConfig::REGIONS;

        // Product type mapping for deals.
        $product_types = [
            'escooter'   => 'Electric Scooter',
            'ebike'      => 'Electric Bike',
            'euc'        => 'Electric Unicycle',
            'eskate'     => 'Electric Skateboard',
            'hoverboard' => 'Hoverboard',
        ];

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Email Testing', 'erh-core'); ?></h1>

            <p class="description">
                <?php esc_html_e('Test email templates before sending them to users. Emails are sent using real data from the database.', 'erh-core'); ?>
            </p>

            <div id="erh-email-test-feedback" class="notice" style="display: none;"></div>

            <!-- Log Output -->
            <div id="erh-email-log" style="display: none; margin-top: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <h3 style="margin: 0;"><?php esc_html_e('Debug Log', 'erh-core'); ?></h3>
                    <button type="button" id="clear-log" class="button button-small"><?php esc_html_e('Clear', 'erh-core'); ?></button>
                </div>
                <div id="erh-email-log-content" style="background: #1d2327; color: #c3c4c7; padding: 12px 16px; font-family: monospace; font-size: 13px; line-height: 1.6; max-height: 300px; overflow-y: auto; border-radius: 4px;"></div>
            </div>

            <form id="erh-email-test-form" method="post">
                <?php wp_nonce_field('erh_email_test', 'erh_email_test_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="email_template"><?php esc_html_e('Email Template', 'erh-core'); ?></label>
                        </th>
                        <td>
                            <select name="email_template" id="email_template" class="regular-text">
                                <?php foreach ($templates as $key => $label) : ?>
                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="recipient_email"><?php esc_html_e('Recipient Email', 'erh-core'); ?></label>
                        </th>
                        <td>
                            <input type="email"
                                   name="recipient_email"
                                   id="recipient_email"
                                   value="<?php echo esc_attr($default_email); ?>"
                                   class="regular-text"
                                   required>
                            <p class="description">
                                <?php esc_html_e('The email address to send the test email to.', 'erh-core'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="geo"><?php esc_html_e('Region / Geo', 'erh-core'); ?></label>
                        </th>
                        <td>
                            <select name="geo" id="geo" class="regular-text">
                                <?php foreach ($geos as $geo) : ?>
                                    <option value="<?php echo esc_attr($geo); ?>" <?php selected($geo, 'US'); ?>>
                                        <?php echo esc_html($geo . ' - ' . GeoConfig::get_currency($geo)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('The region/currency for price display.', 'erh-core'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Deals Digest specific options -->
                    <tr class="deals-digest-options">
                        <th scope="row">
                            <label><?php esc_html_e('Categories', 'erh-core'); ?></label>
                        </th>
                        <td>
                            <?php foreach ($product_types as $slug => $label) : ?>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox"
                                           name="categories[]"
                                           value="<?php echo esc_attr($slug); ?>"
                                           <?php checked($slug, 'escooter'); ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description">
                                <?php esc_html_e('Select which categories to include. Single category shows up to 20 deals, multiple categories show up to 8 each.', 'erh-core'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Price Alert specific options -->
                    <tr class="price-alert-options" style="display: none;">
                        <th scope="row">
                            <label for="alert_product_count"><?php esc_html_e('Number of Products', 'erh-core'); ?></label>
                        </th>
                        <td>
                            <select name="alert_product_count" id="alert_product_count" class="regular-text">
                                <option value="1">1 product (personalized title)</option>
                                <option value="2">2 products</option>
                                <option value="3" selected>3 products</option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Single product alerts show the product name in the subject and title.', 'erh-core'); ?>
                            </p>
                        </td>
                    </tr>

                </table>

                <p class="submit">
                    <button type="button" id="preview-email" class="button button-secondary">
                        <?php esc_html_e('Preview Email', 'erh-core'); ?>
                    </button>
                    <button type="submit" id="send-test-email" class="button button-primary">
                        <?php esc_html_e('Send Test Email', 'erh-core'); ?>
                    </button>
                    <span id="email-spinner" class="spinner" style="float: none; vertical-align: middle;"></span>
                </p>
            </form>

            <!-- Preview Modal -->
            <div id="email-preview-modal" style="display: none;">
                <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 100000; display: flex; align-items: center; justify-content: center;">
                    <div style="background: #fff; width: 90%; max-width: 700px; max-height: 90vh; overflow: auto; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                        <div style="padding: 16px 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
                            <strong><?php esc_html_e('Email Preview', 'erh-core'); ?></strong>
                            <button type="button" id="close-preview" class="button">&times; <?php esc_html_e('Close', 'erh-core'); ?></button>
                        </div>
                        <div id="email-preview-content" style="padding: 0;"></div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            const $form = $('#erh-email-test-form');
            const $feedback = $('#erh-email-test-feedback');
            const $spinner = $('#email-spinner');
            const $modal = $('#email-preview-modal');
            const $previewContent = $('#email-preview-content');

            const $logContainer = $('#erh-email-log');
            const $logContent = $('#erh-email-log-content');

            function showFeedback(message, type) {
                $feedback
                    .removeClass('notice-success notice-error notice-warning')
                    .addClass('notice-' + type)
                    .html('<p>' + message + '</p>')
                    .show();
            }

            function renderLogs(logs) {
                if (!logs || !logs.length) {
                    $logContainer.hide();
                    return;
                }

                const levelColors = {
                    'info': '#8c8f94',
                    'success': '#00a32a',
                    'warning': '#dba617',
                    'error': '#d63638'
                };

                let html = '';
                logs.forEach(function(entry) {
                    const color = levelColors[entry.level] || '#8c8f94';
                    html += '<div style="margin-bottom: 4px;">';
                    html += '<span style="color: #72aee6;">[' + entry.time + ']</span> ';
                    html += '<span style="color: ' + color + ';">' + entry.message + '</span>';
                    html += '</div>';
                });

                $logContent.html(html);
                $logContainer.show();

                // Auto-scroll to bottom.
                $logContent.scrollTop($logContent[0].scrollHeight);
            }

            // Send test email.
            $form.on('submit', function(e) {
                e.preventDefault();
                $spinner.addClass('is-active');
                $feedback.hide();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'erh_send_test_email',
                        _wpnonce: $('#erh_email_test_nonce').val(),
                        email_template: $('#email_template').val(),
                        recipient_email: $('#recipient_email').val(),
                        geo: $('#geo').val(),
                        categories: $('input[name="categories[]"]:checked').map(function() { return this.value; }).get(),
                        alert_product_count: $('#alert_product_count').val()
                    },
                    success: function(response) {
                        $spinner.removeClass('is-active');
                        renderLogs(response.data?.logs || []);
                        if (response.success) {
                            showFeedback(response.data.message, 'success');
                        } else {
                            showFeedback(response.data.message || 'An error occurred.', 'error');
                        }
                    },
                    error: function() {
                        $spinner.removeClass('is-active');
                        showFeedback('Request failed. Please try again.', 'error');
                    }
                });
            });

            // Preview email.
            $('#preview-email').on('click', function() {
                $spinner.addClass('is-active');
                $feedback.hide();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'erh_preview_email',
                        _wpnonce: $('#erh_email_test_nonce').val(),
                        email_template: $('#email_template').val(),
                        geo: $('#geo').val(),
                        categories: $('input[name="categories[]"]:checked').map(function() { return this.value; }).get(),
                        alert_product_count: $('#alert_product_count').val()
                    },
                    success: function(response) {
                        $spinner.removeClass('is-active');
                        renderLogs(response.data?.logs || []);
                        if (response.success) {
                            $previewContent.html('<iframe srcdoc="' + response.data.html.replace(/"/g, '&quot;') + '" style="width: 100%; height: 600px; border: none;"></iframe>');
                            $modal.show();
                        } else {
                            showFeedback(response.data.message || 'Failed to generate preview.', 'error');
                        }
                    },
                    error: function() {
                        $spinner.removeClass('is-active');
                        showFeedback('Request failed. Please try again.', 'error');
                    }
                });
            });

            // Close modal.
            $('#close-preview, #email-preview-modal > div').on('click', function(e) {
                if (e.target === this || $(e.target).closest('#close-preview').length) {
                    $modal.hide();
                }
            });

            // Escape key closes modal.
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $modal.is(':visible')) {
                    $modal.hide();
                }
            });

            // Toggle options based on template selection.
            $('#email_template').on('change', function() {
                const template = $(this).val();

                // Show/hide deals digest options.
                if (template === 'deals_digest') {
                    $('.deals-digest-options').show();
                } else {
                    $('.deals-digest-options').hide();
                }

                // Show/hide price alert options.
                if (template === 'price_alert') {
                    $('.price-alert-options').show();
                } else {
                    $('.price-alert-options').hide();
                }

                // Show/hide geo selector (needed for deals_digest and price_alert).
                const geoRow = $('#geo').closest('tr');
                if (template === 'deals_digest' || template === 'price_alert') {
                    geoRow.show();
                } else {
                    geoRow.hide();
                }
            }).trigger('change');

            // Clear log button.
            $('#clear-log').on('click', function() {
                $logContent.html('');
                $logContainer.hide();
            });
        });
        </script>

        <style>
            #email-preview-modal > div > div:first-child {
                cursor: default;
            }
        </style>
        <?php
    }

    /**
     * Maximum test emails allowed per hour per user.
     */
    private const TEST_EMAIL_LIMIT = 20;

    /**
     * AJAX handler to send a test email.
     *
     * Rate limited to prevent abuse (20 emails per user per hour).
     *
     * @return void
     */
    public function ajax_send_test_email(): void {
        check_ajax_referer('erh_email_test', '_wpnonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        $this->clear_logs();
        $this->log('Starting email send process...', 'info');

        // Rate limiting: max 20 test emails per user per hour.
        $user_id       = get_current_user_id();
        $transient_key = 'erh_email_test_count_' . $user_id;
        $count         = (int) get_transient($transient_key);

        if ($count >= self::TEST_EMAIL_LIMIT) {
            $this->log('Rate limit exceeded', 'error');
            wp_send_json_error([
                'message' => __('Rate limit exceeded. Please wait before sending more test emails.', 'erh-core'),
                'logs'    => $this->get_logs(),
            ]);
        }

        $template = isset($_POST['email_template']) ? sanitize_key($_POST['email_template']) : '';
        $recipient = isset($_POST['recipient_email']) ? sanitize_email($_POST['recipient_email']) : '';

        $this->log("Template: {$template}", 'info');
        $this->log("Recipient: {$recipient}", 'info');

        if (empty($recipient) || !is_email($recipient)) {
            $this->log('Invalid email address provided', 'error');
            wp_send_json_error([
                'message' => __('Invalid email address.', 'erh-core'),
                'logs'    => $this->get_logs(),
            ]);
        }

        $result = $this->send_email($template, $recipient);

        if ($result['success']) {
            // Increment counter with 1-hour expiry.
            set_transient($transient_key, $count + 1, HOUR_IN_SECONDS);
            wp_send_json_success([
                'message' => $result['message'],
                'logs'    => $this->get_logs(),
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message'],
                'logs'    => $this->get_logs(),
            ]);
        }
    }

    /**
     * AJAX handler to preview an email.
     *
     * @return void
     */
    public function ajax_preview_email(): void {
        check_ajax_referer('erh_email_test', '_wpnonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        $this->clear_logs();
        $this->log('Starting email preview generation...', 'info');

        $template = isset($_POST['email_template']) ? sanitize_key($_POST['email_template']) : '';
        $this->log("Template: {$template}", 'info');

        $result = $this->generate_email_html($template);

        if ($result['success']) {
            $this->log('Preview generated successfully', 'success');
            wp_send_json_success([
                'html' => $result['html'],
                'logs' => $this->get_logs(),
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message'],
                'logs'    => $this->get_logs(),
            ]);
        }
    }

    /**
     * Send an email based on template type.
     *
     * @param string $template  Template key.
     * @param string $recipient Recipient email.
     * @return array{success: bool, message: string}
     */
    private function send_email(string $template, string $recipient): array {
        $html_result = $this->generate_email_html($template);

        if (!$html_result['success']) {
            return [
                'success' => false,
                'message' => $html_result['message'],
            ];
        }

        $subject = $this->get_email_subject($template);
        $this->log("Subject: {$subject}", 'info');

        // Send email.
        $from_email = get_option('admin_email');
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ERideHero <' . $from_email . '>',
        ];

        $this->log("From header: ERideHero <{$from_email}>", 'info');
        $this->log('(SMTP plugin may override this with configured sender)', 'info');
        $this->log('Sending email via wp_mail()...', 'info');

        // Capture any PHP mail errors.
        $mail_error = null;
        add_action('wp_mail_failed', function ($wp_error) use (&$mail_error) {
            $mail_error = $wp_error;
        });

        $sent = wp_mail($recipient, $subject, $html_result['html'], $headers);

        if ($mail_error instanceof \WP_Error) {
            $this->log('wp_mail error: ' . $mail_error->get_error_message(), 'error');
            $error_data = $mail_error->get_error_data();
            if (!empty($error_data)) {
                $this->log('Error data: ' . wp_json_encode($error_data), 'error');
            }
        }

        if ($sent) {
            $this->log('wp_mail() returned true - email queued successfully', 'success');
            return [
                'success' => true,
                'message' => sprintf(
                    __('Test email sent to %s successfully! Check your inbox.', 'erh-core'),
                    $recipient
                ),
            ];
        }

        $this->log('wp_mail() returned false - email failed to send', 'error');
        $this->log('Check WordPress mail configuration or install an SMTP plugin', 'warning');

        return [
            'success' => false,
            'message' => __('Failed to send email. Check your WordPress mail configuration.', 'erh-core'),
        ];
    }

    /**
     * Get email subject for a template.
     *
     * @param string $template Template key.
     * @return string Subject line.
     */
    private function get_email_subject(string $template): string {
        switch ($template) {
            case 'deals_digest':
                $categories = $this->get_selected_categories();
                $category_count = count($categories);
                $category_names = [
                    'escooter'   => 'E-Scooter',
                    'ebike'      => 'E-Bike',
                    'euc'        => 'EUC',
                    'eskate'     => 'E-Skate',
                    'hoverboard' => 'Hoverboard',
                ];
                if ($category_count === 1) {
                    $slug = reset($categories);
                    $name = $category_names[$slug] ?? 'electric ride';
                    return sprintf("[TEST] This week's best %s prices", strtolower($name));
                }
                return "[TEST] This week's best electric ride prices";

            case 'welcome':
                return '[TEST] ' . WelcomeEmailTemplate::get_subject();

            case 'password_reset':
                return '[TEST] ' . PasswordResetTemplate::get_subject();

            case 'price_alert':
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $product_count = isset($_POST['alert_product_count']) ? (int) $_POST['alert_product_count'] : 3;
                $product_count = max(1, min(3, $product_count));
                $product_name = $product_count === 1 ? 'Segway Ninebot Max G2' : null;
                return '[TEST] ' . PriceAlertTemplate::get_subject($product_count, $product_name);

            default:
                return '[TEST] ERideHero Email';
        }
    }

    /**
     * Generate email HTML for preview.
     *
     * @param string $template Template key.
     * @return array{success: bool, html?: string, message?: string}
     */
    private function generate_email_html(string $template): array {
        switch ($template) {
            case 'deals_digest':
                return $this->generate_deals_digest_html();
            case 'welcome':
                return $this->generate_welcome_html();
            case 'password_reset':
                return $this->generate_password_reset_html();
            case 'price_alert':
                return $this->generate_price_alert_html();
            default:
                return [
                    'success' => false,
                    'message' => __('Unknown email template.', 'erh-core'),
                ];
        }
    }

    /**
     * Generate deals digest email HTML.
     *
     * @return array{success: bool, html?: string, message?: string}
     */
    private function generate_deals_digest_html(): array {
        $geo = isset($_POST['geo']) ? sanitize_text_field($_POST['geo']) : 'US';
        $categories = $this->get_selected_categories();
        $threshold = DealsFinder::DEFAULT_THRESHOLD;

        $this->log("Geo: {$geo}", 'info');
        $this->log("Discount threshold: {$threshold}% (from DealsFinder::DEFAULT_THRESHOLD)", 'info');
        $this->log('Categories selected: ' . implode(', ', $categories), 'info');

        if (empty($categories)) {
            $this->log('No categories selected', 'error');
            return [
                'success' => false,
                'message' => __('Please select at least one category.', 'erh-core'),
            ];
        }

        // Map category slugs to product types.
        $category_to_type = [
            'escooter'   => 'Electric Scooter',
            'ebike'      => 'Electric Bike',
            'euc'        => 'Electric Unicycle',
            'eskate'     => 'Electric Skateboard',
            'hoverboard' => 'Hoverboard',
        ];

        // Fetch deals for each category.
        $this->log('Initializing DealsFinder...', 'info');
        $deals_finder = new DealsFinder();
        $deals_by_category = [];
        $total_deals = 0;

        foreach ($categories as $slug) {
            $product_type = $category_to_type[$slug] ?? null;
            if (!$product_type) {
                $this->log("Unknown category slug: {$slug}", 'warning');
                continue;
            }

            $this->log("Fetching deals for {$product_type}...", 'info');

            $deals = $deals_finder->get_deals(
                $product_type,
                $threshold,
                100, // Fetch more than we need, template will limit.
                $geo,
                '6m'
            );

            $count = count($deals);
            if ($count > 0) {
                $deals_by_category[$slug] = $deals;
                $total_deals += $count;
                $this->log("Found {$count} deals for {$product_type}", 'success');

                // Log first few deal names for debugging.
                $sample_deals = array_slice($deals, 0, 3);
                foreach ($sample_deals as $deal) {
                    $name = $deal['name'] ?? 'Unknown';
                    $price = $deal['deal_analysis']['current_price'] ?? 0;
                    $discount = abs($deal['deal_analysis']['discount_percent'] ?? 0);
                    $this->log("  - {$name}: \${$price} ({$discount}% below avg)", 'info');
                }
                if ($count > 3) {
                    $this->log("  ... and " . ($count - 3) . " more", 'info');
                }
            } else {
                $this->log("No deals found for {$product_type}", 'warning');
            }
        }

        $this->log("Total deals found: {$total_deals}", $total_deals > 0 ? 'success' : 'warning');

        if ($total_deals === 0) {
            $this->log('Cannot generate email with zero deals', 'error');
            return [
                'success' => false,
                'message' => sprintf(
                    __('No deals found for the selected categories in %s with threshold of %d%% below average.', 'erh-core'),
                    $geo,
                    abs($threshold)
                ),
            ];
        }

        // Generate HTML.
        $this->log('Generating email HTML with DealsDigestTemplate...', 'info');
        $template = new DealsDigestTemplate($geo);
        $html = $template->render($deals_by_category, home_url('/account/settings/'));

        $html_size = strlen($html);
        $this->log("Email HTML generated: " . number_format($html_size) . " bytes", 'success');

        return [
            'success' => true,
            'html'    => $html,
        ];
    }

    /**
     * Generate welcome email HTML.
     *
     * @return array{success: bool, html?: string, message?: string}
     */
    private function generate_welcome_html(): array {
        $this->log('Generating welcome email...', 'info');

        $current_user = wp_get_current_user();
        $username = $current_user->display_name ?: $current_user->user_login;

        $this->log("Using username: {$username}", 'info');

        $template = new WelcomeEmailTemplate();
        $html = $template->render($username);

        $html_size = strlen($html);
        $this->log("Email HTML generated: " . number_format($html_size) . ' bytes', 'success');

        return [
            'success' => true,
            'html'    => $html,
        ];
    }

    /**
     * Generate password reset email HTML.
     *
     * @return array{success: bool, html?: string, message?: string}
     */
    private function generate_password_reset_html(): array {
        $this->log('Generating password reset email...', 'info');

        $current_user = wp_get_current_user();
        $username = $current_user->display_name ?: $current_user->user_login;

        // Generate a fake reset URL for preview.
        $reset_url = home_url('/reset-password/?key=SAMPLE_KEY_12345&login=' . rawurlencode($current_user->user_login));

        $this->log("Using username: {$username}", 'info');
        $this->log("Sample reset URL: {$reset_url}", 'info');

        $template = new PasswordResetTemplate();
        $html = $template->render($username, $reset_url);

        $html_size = strlen($html);
        $this->log("Email HTML generated: " . number_format($html_size) . ' bytes', 'success');

        return [
            'success' => true,
            'html'    => $html,
        ];
    }

    /**
     * Generate price alert email HTML with sample data.
     *
     * @return array{success: bool, html?: string, message?: string}
     */
    private function generate_price_alert_html(): array {
        $geo = isset($_POST['geo']) ? sanitize_text_field($_POST['geo']) : 'US';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $product_count = isset($_POST['alert_product_count']) ? (int) $_POST['alert_product_count'] : 3;
        $product_count = max(1, min(3, $product_count)); // Clamp to 1-3.

        $this->log("Generating price alert email for geo: {$geo}, products: {$product_count}", 'info');

        $current_user = wp_get_current_user();
        $username = $current_user->first_name ?: $current_user->display_name ?: $current_user->user_login;

        $this->log("Using username: {$username}", 'info');

        // Create sample deal data for preview.
        $all_sample_deals = [
            [
                'product_name'      => 'Segway Ninebot Max G2',
                'image_url'         => 'https://eridehero.com/wp-content/uploads/2024/03/Segway-Ninebot-KickScooter-Max-G2.webp',
                'current_price'     => 749,
                'compare_price'     => 899,
                'percent_below_avg' => 12,
                'url'               => home_url('/electric-scooter/segway-ninebot-max-g2/'),
                'tracking_users'    => 47,
                'currency'          => GeoConfig::get_currency($geo),
                'geo'               => $geo,
            ],
            [
                'product_name'      => 'NIU KQi3 Max',
                'image_url'         => 'https://eridehero.com/wp-content/uploads/2023/02/NIU-KQi3-Max.webp',
                'current_price'     => 599,
                'compare_price'     => 699,
                'percent_below_avg' => 8,
                'url'               => home_url('/electric-scooter/niu-kqi3-max/'),
                'tracking_users'    => 23,
                'currency'          => GeoConfig::get_currency($geo),
                'geo'               => $geo,
            ],
            [
                'product_name'      => 'Apollo City Pro',
                'image_url'         => 'https://eridehero.com/wp-content/uploads/2023/10/Apollo-City-Pro-2023.webp',
                'current_price'     => 1399,
                'compare_price'     => 1599,
                'percent_below_avg' => null, // No average data for this one.
                'url'               => home_url('/electric-scooter/apollo-city-pro-2023/'),
                'tracking_users'    => 12,
                'currency'          => GeoConfig::get_currency($geo),
                'geo'               => $geo,
            ],
        ];

        // Slice to requested count.
        $sample_deals = array_slice($all_sample_deals, 0, $product_count);

        $this->log('Using ' . count($sample_deals) . ' sample deal(s) for preview', 'info');
        foreach ($sample_deals as $deal) {
            $below_avg = $deal['percent_below_avg'] ? "{$deal['percent_below_avg']}% below avg" : 'no avg data';
            $this->log("  - {$deal['product_name']}: was {$deal['compare_price']}, now {$deal['current_price']} ({$below_avg})", 'info');
        }

        $template = new PriceAlertTemplate($geo);
        $html = $template->render($username, $sample_deals);

        $html_size = strlen($html);
        $this->log("Email HTML generated: " . number_format($html_size) . ' bytes', 'success');

        return [
            'success' => true,
            'html'    => $html,
        ];
    }

    /**
     * Get selected categories from POST data.
     *
     * @return array<string>
     */
    private function get_selected_categories(): array {
        if (!isset($_POST['categories']) || !is_array($_POST['categories'])) {
            return [];
        }

        $valid = ['escooter', 'ebike', 'euc', 'eskate', 'hoverboard'];
        $selected = [];

        foreach ($_POST['categories'] as $cat) {
            $sanitized = sanitize_key($cat);
            if (in_array($sanitized, $valid, true)) {
                $selected[] = $sanitized;
            }
        }

        return $selected;
    }
}
