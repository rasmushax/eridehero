<?php
/**
 * Newsletter Admin - Meta boxes, columns, and AJAX handlers.
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

use ERH\PostTypes\Newsletter;
use ERH\Newsletter\NewsletterSender;

/**
 * Handles newsletter admin UI components.
 */
class NewsletterAdmin {

    /**
     * AJAX nonce action.
     */
    private const NONCE_ACTION = 'erh_newsletter_admin';

    /**
     * Register admin hooks.
     *
     * @return void
     */
    public function register(): void {
        // Admin columns.
        add_filter('manage_' . Newsletter::POST_TYPE . '_posts_columns', [$this, 'add_columns']);
        add_action('manage_' . Newsletter::POST_TYPE . '_posts_custom_column', [$this, 'render_column'], 10, 2);

        // Meta boxes.
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);

        // Admin scripts.
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Post status dropdown fix for custom statuses.
        add_action('admin_footer-post.php', [$this, 'append_post_status_list']);
        add_action('admin_footer-post-new.php', [$this, 'append_post_status_list']);

        // AJAX handlers.
        add_action('wp_ajax_erh_newsletter_send_test', [$this, 'ajax_send_test']);
        add_action('wp_ajax_erh_newsletter_schedule', [$this, 'ajax_schedule']);
        add_action('wp_ajax_erh_newsletter_send_now', [$this, 'ajax_send_now']);
        add_action('wp_ajax_erh_newsletter_preview', [$this, 'ajax_preview']);
    }

    /**
     * Add custom columns to newsletters list.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function add_columns(array $columns): array {
        $new_columns = [];

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            // Insert after title.
            if ($key === 'title') {
                $new_columns['newsletter_status']     = __('Status', 'erh-core');
                $new_columns['newsletter_recipients'] = __('Recipients', 'erh-core');
            }
        }

        return $new_columns;
    }

    /**
     * Render custom column content.
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     * @return void
     */
    public function render_column(string $column, int $post_id): void {
        $post = get_post($post_id);

        switch ($column) {
            case 'newsletter_status':
                $this->render_status_column($post);
                break;

            case 'newsletter_recipients':
                $this->render_recipients_column($post_id);
                break;
        }
    }

    /**
     * Render status column.
     *
     * @param \WP_Post $post Post object.
     * @return void
     */
    private function render_status_column(\WP_Post $post): void {
        $status = $post->post_status;

        switch ($status) {
            case 'draft':
                echo '<span class="erh-status erh-status--draft">' . esc_html__('Draft', 'erh-core') . '</span>';
                break;

            case Newsletter::STATUS_SCHEDULED:
                $scheduled_at = get_post_meta($post->ID, Newsletter::META_SCHEDULED_AT, true);
                $formatted    = $scheduled_at ? wp_date('M j, Y g:i A', strtotime($scheduled_at)) : '';
                echo '<span class="erh-status erh-status--scheduled">' . esc_html__('Scheduled', 'erh-core') . '</span>';
                if ($formatted) {
                    echo '<br><small>' . esc_html($formatted) . '</small>';
                }
                break;

            case Newsletter::STATUS_SENDING:
                echo '<span class="erh-status erh-status--sending">' . esc_html__('Sending...', 'erh-core') . '</span>';
                break;

            case Newsletter::STATUS_SENT:
                $completed_at = get_post_meta($post->ID, Newsletter::META_SEND_COMPLETED_AT, true);
                $formatted    = $completed_at ? wp_date('M j, Y g:i A', strtotime($completed_at)) : '';
                echo '<span class="erh-status erh-status--sent">' . esc_html__('Sent', 'erh-core') . '</span>';
                if ($formatted) {
                    echo '<br><small>' . esc_html($formatted) . '</small>';
                }
                break;

            default:
                echo '<span class="erh-status">' . esc_html(ucfirst($status)) . '</span>';
        }
    }

    /**
     * Render recipients column.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    private function render_recipients_column(int $post_id): void {
        $post   = get_post($post_id);
        $status = $post->post_status;

        if (in_array($status, [Newsletter::STATUS_SENDING, Newsletter::STATUS_SENT], true)) {
            $stats = Newsletter::get_stats($post_id);
            echo sprintf(
                '%d / %d',
                $stats['queued_count'],
                $stats['total_recipients']
            );
        } else {
            $sender = new NewsletterSender();
            $count  = $sender->get_subscriber_count();
            echo sprintf(
                // translators: %d is the number of subscribers.
                _n('%d subscriber', '%d subscribers', $count, 'erh-core'),
                $count
            );
        }
    }

    /**
     * Add meta boxes.
     *
     * @return void
     */
    public function add_meta_boxes(): void {
        // Send controls meta box.
        add_meta_box(
            'erh-newsletter-send-controls',
            __('Send Newsletter', 'erh-core'),
            [$this, 'render_send_controls_meta_box'],
            Newsletter::POST_TYPE,
            'side',
            'high'
        );

        // Stats meta box (only for sent newsletters).
        add_meta_box(
            'erh-newsletter-stats',
            __('Newsletter Stats', 'erh-core'),
            [$this, 'render_stats_meta_box'],
            Newsletter::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Render send controls meta box.
     *
     * @param \WP_Post $post Post object.
     * @return void
     */
    public function render_send_controls_meta_box(\WP_Post $post): void {
        $status = $post->post_status;
        $sender = new NewsletterSender();
        $count  = $sender->get_subscriber_count();

        wp_nonce_field(self::NONCE_ACTION, 'erh_newsletter_nonce');
        ?>
        <div class="erh-newsletter-controls">
            <?php if ($status === Newsletter::STATUS_SENT) : ?>
                <p class="erh-newsletter-notice erh-newsletter-notice--success">
                    <?php esc_html_e('This newsletter has been sent.', 'erh-core'); ?>
                </p>
            <?php elseif ($status === Newsletter::STATUS_SENDING) : ?>
                <p class="erh-newsletter-notice erh-newsletter-notice--info">
                    <?php esc_html_e('This newsletter is currently being sent.', 'erh-core'); ?>
                </p>
            <?php elseif ($status === Newsletter::STATUS_SCHEDULED) : ?>
                <?php
                $scheduled_at = get_post_meta($post->ID, Newsletter::META_SCHEDULED_AT, true);
                $formatted    = $scheduled_at ? wp_date('M j, Y \a\t g:i A', strtotime($scheduled_at)) : '';
                ?>
                <p class="erh-newsletter-notice erh-newsletter-notice--scheduled">
                    <?php
                    printf(
                        // translators: %s is the scheduled date/time.
                        esc_html__('Scheduled for %s', 'erh-core'),
                        '<strong>' . esc_html($formatted) . '</strong>'
                    );
                    ?>
                </p>
            <?php else : ?>
                <p class="erh-newsletter-subscriber-count">
                    <?php
                    printf(
                        // translators: %d is the number of subscribers.
                        esc_html__('This will be sent to %d subscribers.', 'erh-core'),
                        $count
                    );
                    ?>
                </p>

                <!-- Test Email -->
                <div class="erh-newsletter-section">
                    <label for="erh-test-email"><?php esc_html_e('Test Email:', 'erh-core'); ?></label>
                    <div class="erh-newsletter-input-group">
                        <input type="email" id="erh-test-email" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>">
                        <button type="button" class="button" id="erh-send-test">
                            <?php esc_html_e('Send Test', 'erh-core'); ?>
                        </button>
                    </div>
                    <p class="erh-newsletter-result" id="erh-test-result"></p>
                </div>

                <hr>

                <!-- Schedule -->
                <div class="erh-newsletter-section">
                    <label for="erh-schedule-datetime"><?php esc_html_e('Schedule:', 'erh-core'); ?></label>
                    <div class="erh-newsletter-input-group">
                        <input type="datetime-local" id="erh-schedule-datetime" min="<?php echo esc_attr(gmdate('Y-m-d\TH:i')); ?>">
                        <button type="button" class="button" id="erh-schedule">
                            <?php esc_html_e('Schedule', 'erh-core'); ?>
                        </button>
                    </div>
                    <p class="erh-newsletter-result" id="erh-schedule-result"></p>
                </div>

                <hr>

                <!-- Send Now -->
                <div class="erh-newsletter-section">
                    <button type="button" class="button button-primary button-large" id="erh-send-now" style="width: 100%;">
                        <?php esc_html_e('Send Now', 'erh-core'); ?>
                    </button>
                    <p class="erh-newsletter-result" id="erh-send-result"></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render stats meta box.
     *
     * @param \WP_Post $post Post object.
     * @return void
     */
    public function render_stats_meta_box(\WP_Post $post): void {
        $status = $post->post_status;

        if (!in_array($status, [Newsletter::STATUS_SENDING, Newsletter::STATUS_SENT], true)) {
            echo '<p>' . esc_html__('Stats will appear after the newsletter is sent.', 'erh-core') . '</p>';
            return;
        }

        $stats = Newsletter::get_stats($post->ID);
        ?>
        <table class="erh-newsletter-stats-table">
            <tr>
                <th><?php esc_html_e('Total Recipients', 'erh-core'); ?></th>
                <td><?php echo esc_html(number_format($stats['total_recipients'])); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Emails Queued', 'erh-core'); ?></th>
                <td><?php echo esc_html(number_format($stats['queued_count'])); ?></td>
            </tr>
            <?php if ($stats['send_started_at']) : ?>
            <tr>
                <th><?php esc_html_e('Send Started', 'erh-core'); ?></th>
                <td><?php echo esc_html(wp_date('M j, Y g:i A', strtotime($stats['send_started_at']))); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($stats['send_completed_at']) : ?>
            <tr>
                <th><?php esc_html_e('Send Completed', 'erh-core'); ?></th>
                <td><?php echo esc_html(wp_date('M j, Y g:i A', strtotime($stats['send_completed_at']))); ?></td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_scripts(string $hook): void {
        global $post_type;

        // Only load on newsletter edit screens.
        if ($post_type !== Newsletter::POST_TYPE) {
            return;
        }

        if (!in_array($hook, ['post.php', 'post-new.php', 'edit.php'], true)) {
            return;
        }

        wp_enqueue_style(
            'erh-newsletter-admin',
            ERH_PLUGIN_URL . 'assets/css/newsletter-admin.css',
            [],
            ERH_VERSION
        );

        wp_enqueue_script(
            'erh-newsletter-admin',
            ERH_PLUGIN_URL . 'assets/js/newsletter-admin.js',
            ['jquery'],
            ERH_VERSION,
            true
        );

        wp_localize_script('erh-newsletter-admin', 'erhNewsletter', [
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce(self::NONCE_ACTION),
            'postId'        => get_the_ID(),
            'strings'       => [
                'sending'         => __('Sending...', 'erh-core'),
                'scheduling'      => __('Scheduling...', 'erh-core'),
                'testSent'        => __('Test email sent!', 'erh-core'),
                'testFailed'      => __('Failed to send test email.', 'erh-core'),
                'scheduled'       => __('Newsletter scheduled!', 'erh-core'),
                'scheduleFailed'  => __('Failed to schedule newsletter.', 'erh-core'),
                'sendingNow'      => __('Queueing emails...', 'erh-core'),
                'sent'            => __('Newsletter queued for sending!', 'erh-core'),
                'sendFailed'      => __('Failed to queue newsletter.', 'erh-core'),
                'confirmSendNow'  => __('Are you sure you want to send this newsletter to all subscribers now?', 'erh-core'),
                'saveDraftFirst'  => __('Please save the newsletter as a draft first.', 'erh-core'),
                'selectDateTime'  => __('Please select a date and time.', 'erh-core'),
            ],
        ]);
    }

    /**
     * Append custom statuses to post status dropdown.
     *
     * @return void
     */
    public function append_post_status_list(): void {
        global $post;

        if (!$post || $post->post_type !== Newsletter::POST_TYPE) {
            return;
        }

        $statuses = [
            Newsletter::STATUS_SCHEDULED => __('Scheduled', 'erh-core'),
            Newsletter::STATUS_SENDING   => __('Sending', 'erh-core'),
            Newsletter::STATUS_SENT      => __('Sent', 'erh-core'),
        ];

        $complete = '';
        $label    = '';

        foreach ($statuses as $status => $status_label) {
            if ($post->post_status === $status) {
                $complete = ' selected="selected"';
                $label    = $status_label;
            }
        }
        ?>
        <script>
        jQuery(document).ready(function($) {
            <?php foreach ($statuses as $status => $status_label) : ?>
            $('select#post_status').append('<option value="<?php echo esc_js($status); ?>"<?php echo $post->post_status === $status ? ' selected="selected"' : ''; ?>><?php echo esc_js($status_label); ?></option>');
            <?php endforeach; ?>

            <?php if ($label) : ?>
            $('#post-status-display').text('<?php echo esc_js($label); ?>');
            <?php endif; ?>
        });
        </script>
        <?php
    }

    /**
     * Maximum test emails allowed per hour per user.
     */
    private const TEST_EMAIL_LIMIT = 10;

    /**
     * AJAX: Send test email.
     *
     * Rate limited to prevent abuse (10 emails per user per hour).
     *
     * @return void
     */
    public function ajax_send_test(): void {
        $this->verify_ajax_request();

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $email   = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';

        if (!$post_id || !$email) {
            wp_send_json_error(['message' => __('Invalid request.', 'erh-core')]);
        }

        // Rate limiting: max 10 test emails per user per hour.
        $user_id       = get_current_user_id();
        $transient_key = 'erh_test_email_count_' . $user_id;
        $count         = (int) get_transient($transient_key);

        if ($count >= self::TEST_EMAIL_LIMIT) {
            wp_send_json_error([
                'message' => __('Rate limit exceeded. Please wait before sending more test emails.', 'erh-core'),
            ]);
        }

        // Save the post first to ensure latest content is rendered.
        if (isset($_POST['post_data'])) {
            // ACF fields will be saved separately.
        }

        $sender = new NewsletterSender();
        $result = $sender->send_test($post_id, $email);

        if ($result) {
            // Increment counter with 1-hour expiry.
            set_transient($transient_key, $count + 1, HOUR_IN_SECONDS);
            wp_send_json_success(['message' => __('Test email sent!', 'erh-core')]);
        } else {
            wp_send_json_error(['message' => __('Failed to send test email.', 'erh-core')]);
        }
    }

    /**
     * AJAX: Schedule newsletter.
     *
     * @return void
     */
    public function ajax_schedule(): void {
        $this->verify_ajax_request();

        $post_id  = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $datetime = isset($_POST['datetime']) ? sanitize_text_field(wp_unslash($_POST['datetime'])) : '';

        if (!$post_id || !$datetime) {
            wp_send_json_error(['message' => __('Invalid request.', 'erh-core')]);
        }

        // Convert local time to UTC.
        $local_time = new \DateTime($datetime, wp_timezone());
        $utc_time   = $local_time->setTimezone(new \DateTimeZone('UTC'));
        $utc_string = $utc_time->format('Y-m-d H:i:s');

        $result = Newsletter::schedule($post_id, $utc_string);

        if ($result) {
            $formatted = wp_date('M j, Y \a\t g:i A', strtotime($utc_string));
            wp_send_json_success([
                'message'   => __('Newsletter scheduled!', 'erh-core'),
                'scheduled' => $formatted,
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to schedule newsletter.', 'erh-core')]);
        }
    }

    /**
     * AJAX: Send newsletter now.
     *
     * @return void
     */
    public function ajax_send_now(): void {
        $this->verify_ajax_request();

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid request.', 'erh-core')]);
        }

        // Mark as sending.
        if (!Newsletter::mark_sending($post_id)) {
            wp_send_json_error(['message' => __('Failed to start sending.', 'erh-core')]);
        }

        // Queue all emails.
        $sender = new NewsletterSender();
        $result = $sender->queue_newsletter($post_id);

        // Mark as sent.
        Newsletter::mark_sent($post_id, $result['total'], $result['queued']);

        wp_send_json_success([
            'message' => sprintf(
                // translators: %1$d is queued count, %2$d is total count.
                __('Newsletter queued! %1$d/%2$d emails queued for sending.', 'erh-core'),
                $result['queued'],
                $result['total']
            ),
            'total'  => $result['total'],
            'queued' => $result['queued'],
        ]);
    }

    /**
     * AJAX: Get newsletter preview HTML.
     *
     * @return void
     */
    public function ajax_preview(): void {
        $this->verify_ajax_request();

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid request.', 'erh-core')]);
        }

        $sender = new NewsletterSender();
        $html   = $sender->get_preview_html($post_id);

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Verify AJAX request.
     *
     * @return void
     */
    private function verify_ajax_request(): void {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'erh-core')]);
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }
    }
}
