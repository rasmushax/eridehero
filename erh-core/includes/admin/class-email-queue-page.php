<?php
/**
 * Email Queue admin page - View queued, sent, and failed emails.
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

use ERH\Database\EmailQueue;
use ERH\Database\EmailQueueRepository;

/**
 * Provides an admin UI to monitor the email queue.
 */
class EmailQueuePage {

    /**
     * Page slug.
     */
    public const PAGE_SLUG = 'erh-email-queue';

    /**
     * Email queue repository.
     *
     * @var EmailQueueRepository
     */
    private EmailQueueRepository $queue_repo;

    /**
     * Constructor.
     *
     * @param EmailQueueRepository $queue_repo Email queue repository.
     */
    public function __construct(EmailQueueRepository $queue_repo) {
        $this->queue_repo = $queue_repo;
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action('admin_menu', [$this, 'add_menu_page']);
    }

    /**
     * Add submenu page under Tools.
     *
     * @return void
     */
    public function add_menu_page(): void {
        add_submenu_page(
            'tools.php',
            __('Email Queue', 'erh-core'),
            __('Email Queue', 'erh-core'),
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

        $stats = $this->queue_repo->get_stats();
        $sent_today = $this->queue_repo->count_sent_today();
        $stats_by_type = $this->queue_repo->get_stats_by_type();
        $failures = $this->queue_repo->get_recent_failures(10);

        // Filters from URL.
        $filter_type = isset($_GET['type']) ? sanitize_text_field(wp_unslash($_GET['type'])) : '';
        $filter_status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        $recent = $this->queue_repo->get_recent($filter_type, $filter_status, 50);

        // Type labels for display.
        $type_labels = [
            'price_alert'   => 'Price Alert',
            'deals_digest'  => 'Deals Digest',
            'newsletter'    => 'Newsletter',
            'welcome'       => 'Welcome',
            'password_reset' => 'Password Reset',
            'general'       => 'General',
        ];

        $base_url = admin_url('tools.php?page=' . self::PAGE_SLUG);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Email Queue', 'erh-core'); ?></h1>

            <style>
                .erh-eq-cards { display: flex; gap: 12px; margin: 16px 0 24px; flex-wrap: wrap; }
                .erh-eq-card {
                    background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;
                    padding: 16px 20px; min-width: 140px; flex: 1;
                }
                .erh-eq-card .number { font-size: 28px; font-weight: 600; line-height: 1.2; }
                .erh-eq-card .label { color: #646970; font-size: 13px; margin-top: 4px; }
                .erh-eq-card.warning .number { color: #d63638; }
                .erh-eq-card.success .number { color: #00a32a; }
                .erh-eq-card.info .number { color: #2271b1; }
                .erh-eq-filters { margin: 16px 0; display: flex; gap: 8px; align-items: center; }
                .erh-eq-status { padding: 2px 8px; border-radius: 3px; font-size: 12px; font-weight: 500; }
                .erh-eq-status.sent { background: #d4edda; color: #155724; }
                .erh-eq-status.pending { background: #fff3cd; color: #856404; }
                .erh-eq-status.processing { background: #cce5ff; color: #004085; }
                .erh-eq-status.failed { background: #f8d7da; color: #721c24; }
                .erh-eq-type { padding: 2px 8px; border-radius: 3px; font-size: 12px; background: #f0f0f1; }
                .erh-eq-error { color: #d63638; font-size: 12px; max-width: 300px; }
                .erh-eq-section { margin-top: 24px; }
                .erh-eq-section h2 { font-size: 14px; margin-bottom: 8px; }
            </style>

            <!-- Summary Cards -->
            <div class="erh-eq-cards">
                <div class="erh-eq-card info">
                    <div class="number"><?php echo esc_html((string) $sent_today); ?></div>
                    <div class="label"><?php esc_html_e('Sent Today', 'erh-core'); ?></div>
                </div>
                <div class="erh-eq-card <?php echo $stats['pending'] > 0 ? 'info' : ''; ?>">
                    <div class="number"><?php echo esc_html((string) $stats['pending']); ?></div>
                    <div class="label"><?php esc_html_e('Pending', 'erh-core'); ?></div>
                </div>
                <div class="erh-eq-card <?php echo $stats['processing'] > 0 ? 'info' : ''; ?>">
                    <div class="number"><?php echo esc_html((string) $stats['processing']); ?></div>
                    <div class="label"><?php esc_html_e('Processing', 'erh-core'); ?></div>
                </div>
                <div class="erh-eq-card success">
                    <div class="number"><?php echo esc_html((string) $stats['sent']); ?></div>
                    <div class="label"><?php esc_html_e('Sent (All Time)', 'erh-core'); ?></div>
                </div>
                <div class="erh-eq-card <?php echo $stats['failed'] > 0 ? 'warning' : ''; ?>">
                    <div class="number"><?php echo esc_html((string) $stats['failed']); ?></div>
                    <div class="label"><?php esc_html_e('Failed', 'erh-core'); ?></div>
                </div>
            </div>

            <?php if (!empty($failures)) : ?>
            <!-- Failures -->
            <div class="erh-eq-section">
                <h2><?php esc_html_e('Recent Failures', 'erh-core'); ?></h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'erh-core'); ?></th>
                            <th><?php esc_html_e('Type', 'erh-core'); ?></th>
                            <th><?php esc_html_e('Recipient', 'erh-core'); ?></th>
                            <th><?php esc_html_e('Subject', 'erh-core'); ?></th>
                            <th><?php esc_html_e('Retries', 'erh-core'); ?></th>
                            <th><?php esc_html_e('Error', 'erh-core'); ?></th>
                            <th><?php esc_html_e('Failed At', 'erh-core'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($failures as $fail) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $fail['id']); ?></td>
                            <td><span class="erh-eq-type"><?php echo esc_html($type_labels[$fail['email_type']] ?? $fail['email_type']); ?></span></td>
                            <td><?php echo esc_html($fail['recipient_email']); ?></td>
                            <td><?php echo esc_html($fail['subject']); ?></td>
                            <td><?php echo esc_html((string) $fail['retry_count']); ?>/3</td>
                            <td class="erh-eq-error"><?php echo esc_html($fail['error_message'] ?: '—'); ?></td>
                            <td><?php echo esc_html($fail['processed_at'] ?: '—'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Breakdown by Type -->
            <?php if (!empty($stats_by_type)) : ?>
            <div class="erh-eq-section">
                <h2><?php esc_html_e('Breakdown by Type', 'erh-core'); ?></h2>
                <table class="widefat striped" style="max-width: 600px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Type', 'erh-core'); ?></th>
                            <th><?php esc_html_e('Pending', 'erh-core'); ?></th>
                            <th><?php esc_html_e('Sent', 'erh-core'); ?></th>
                            <th><?php esc_html_e('Failed', 'erh-core'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats_by_type as $type => $counts) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg('type', $type, $base_url)); ?>">
                                    <?php echo esc_html($type_labels[$type] ?? $type); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html((string) $counts['pending']); ?></td>
                            <td><?php echo esc_html((string) $counts['sent']); ?></td>
                            <td><?php echo esc_html((string) $counts['failed']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Recent Emails -->
            <div class="erh-eq-section">
                <h2>
                    <?php esc_html_e('Recent Emails', 'erh-core'); ?>
                    <?php if ($filter_type || $filter_status) : ?>
                        <a href="<?php echo esc_url($base_url); ?>" style="font-size: 12px; font-weight: normal; margin-left: 8px;"><?php esc_html_e('Clear filters', 'erh-core'); ?></a>
                    <?php endif; ?>
                </h2>

                <!-- Filters -->
                <div class="erh-eq-filters">
                    <strong><?php esc_html_e('Filter:', 'erh-core'); ?></strong>
                    <?php
                    $statuses = ['pending', 'processing', 'sent', 'failed'];
                    foreach ($statuses as $s) :
                        $active = ($filter_status === $s);
                        $url = $active ? remove_query_arg('status', $base_url) : add_query_arg('status', $s, $base_url);
                        if ($filter_type) {
                            $url = add_query_arg('type', $filter_type, $url);
                        }
                    ?>
                        <a href="<?php echo esc_url($url); ?>" class="button <?php echo $active ? 'button-primary' : ''; ?>" style="min-height: 28px; line-height: 26px; padding: 0 10px;">
                            <?php echo esc_html(ucfirst($s)); ?>
                        </a>
                    <?php endforeach; ?>

                    <span style="margin-left: 8px;">|</span>

                    <?php foreach ($type_labels as $type_key => $type_label) :
                        if (!isset($stats_by_type[$type_key])) continue;
                        $active = ($filter_type === $type_key);
                        $url = $active ? remove_query_arg('type', $base_url) : add_query_arg('type', $type_key, $base_url);
                        if ($filter_status) {
                            $url = add_query_arg('status', $filter_status, $url);
                        }
                    ?>
                        <a href="<?php echo esc_url($url); ?>" class="button <?php echo $active ? 'button-primary' : ''; ?>" style="min-height: 28px; line-height: 26px; padding: 0 10px;">
                            <?php echo esc_html($type_label); ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($recent)) : ?>
                    <p><?php esc_html_e('No emails found.', 'erh-core'); ?></p>
                <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'erh-core'); ?></th>
                            <th><?php esc_html_e('Type', 'erh-core'); ?></th>
                            <th><?php esc_html_e('Recipient', 'erh-core'); ?></th>
                            <th><?php esc_html_e('Subject', 'erh-core'); ?></th>
                            <th><?php esc_html_e('Status', 'erh-core'); ?></th>
                            <th><?php esc_html_e('Created', 'erh-core'); ?></th>
                            <th><?php esc_html_e('Sent', 'erh-core'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $email) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $email['id']); ?></td>
                            <td><span class="erh-eq-type"><?php echo esc_html($type_labels[$email['email_type']] ?? $email['email_type']); ?></span></td>
                            <td><?php echo esc_html($email['recipient_email']); ?></td>
                            <td><?php echo esc_html($email['subject']); ?></td>
                            <td><span class="erh-eq-status <?php echo esc_attr($email['status']); ?>"><?php echo esc_html(ucfirst($email['status'])); ?></span></td>
                            <td><?php echo esc_html($email['created_at']); ?></td>
                            <td><?php echo esc_html($email['processed_at'] ?: '—'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
