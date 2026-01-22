<?php
/**
 * Price notification cron job for sending price drop alerts.
 *
 * @package ERH\Cron
 */

declare(strict_types=1);

namespace ERH\Cron;

use ERH\Pricing\PriceFetcher;
use ERH\Database\PriceTracker;
use ERH\Database\PriceHistory;
use ERH\Database\EmailQueue;
use ERH\Database\EmailQueueRepository;
use ERH\Email\PriceAlertTemplate;
use ERH\User\UserRepository;

/**
 * Checks price trackers and sends notifications when price targets are met.
 */
class NotificationJob implements CronJobInterface {

    /**
     * Minimum hours between notifications for the same user.
     */
    private const NOTIFICATION_COOLDOWN_HOURS = 24;

    /**
     * Price fetcher instance.
     *
     * @var PriceFetcher
     */
    private PriceFetcher $price_fetcher;

    /**
     * Price tracker database instance.
     *
     * @var PriceTracker
     */
    private PriceTracker $price_tracker;

    /**
     * Price history database instance.
     *
     * @var PriceHistory
     */
    private PriceHistory $price_history;

    /**
     * User repository instance.
     *
     * @var UserRepository
     */
    private UserRepository $user_repo;

    /**
     * Cron manager reference for locking.
     *
     * @var CronManager
     */
    private CronManager $cron_manager;

    /**
     * Constructor.
     *
     * @param PriceFetcher   $price_fetcher Price fetcher instance.
     * @param PriceTracker   $price_tracker Price tracker database instance.
     * @param PriceHistory   $price_history Price history database instance.
     * @param UserRepository $user_repo     User repository instance.
     * @param CronManager    $cron_manager  Cron manager instance.
     */
    public function __construct(
        PriceFetcher $price_fetcher,
        PriceTracker $price_tracker,
        PriceHistory $price_history,
        UserRepository $user_repo,
        CronManager $cron_manager
    ) {
        $this->price_fetcher = $price_fetcher;
        $this->price_tracker = $price_tracker;
        $this->price_history = $price_history;
        $this->user_repo = $user_repo;
        $this->cron_manager = $cron_manager;
    }

    /**
     * Get the job's display name.
     *
     * @return string
     */
    public function get_name(): string {
        return __('Price Drop Notifications', 'erh-core');
    }

    /**
     * Get the job's description.
     *
     * @return string
     */
    public function get_description(): string {
        return __('Checks price trackers and sends email alerts when prices drop.', 'erh-core');
    }

    /**
     * Get the WordPress hook name.
     *
     * @return string
     */
    public function get_hook_name(): string {
        return 'erh_cron_notifications';
    }

    /**
     * Get the cron schedule.
     *
     * @return string
     */
    public function get_schedule(): string {
        return 'erh_two_hours';
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function execute(): void {
        // Acquire lock to prevent concurrent execution.
        if (!$this->cron_manager->lock_job('notifications', 900)) {
            error_log('[ERH Cron] Notification job already running, skipping.');
            return;
        }

        try {
            $this->run();
        } finally {
            $this->cron_manager->unlock_job('notifications');
            $this->cron_manager->record_run_time('notifications');
        }
    }

    /**
     * Run the notification logic.
     *
     * @return void
     */
    private function run(): void {
        // Get users who have opted in for price tracker emails.
        $users = get_users([
            'meta_key'   => UserRepository::META_PRICE_TRACKER_EMAILS,
            'meta_value' => '1',
        ]);

        $notifications_sent = 0;
        $users_processed = 0;

        foreach ($users as $user) {
            $users_processed++;

            // Collect eligible deals for this user.
            $deals = $this->collect_deals_for_user($user->ID);

            if (!empty($deals)) {
                $sent = $this->send_notification($user, $deals);
                if ($sent) {
                    $notifications_sent++;
                }
            }
        }

        error_log(sprintf(
            '[ERH Cron] Notifications completed. Users: %d, Emails sent: %d',
            $users_processed,
            $notifications_sent
        ));
    }

    /**
     * Collect eligible deals for a user based on their trackers.
     *
     * @param int $user_id The user ID.
     * @return array Array of eligible deals.
     */
    private function collect_deals_for_user(int $user_id): array {
        $trackers = $this->price_tracker->get_for_user($user_id);
        $deals = [];
        $current_time = strtotime(current_time('mysql'));

        foreach ($trackers as $tracker) {
            $product_id = $tracker['product_id'];
            $start_price = $tracker['start_price'];
            $target_price = $tracker['target_price'];
            $price_drop = $tracker['price_drop'];
            $last_notified_price = $tracker['last_notified_price'];
            $last_notification_time = $tracker['last_notification_time'];
            $tracker_geo = $tracker['geo'] ?? 'US';
            $tracker_currency = $tracker['currency'] ?? 'USD';

            // Check notification cooldown (24 hours).
            if ($last_notification_time) {
                $time_since_last = $current_time - strtotime($last_notification_time);
                if ($time_since_last < self::NOTIFICATION_COOLDOWN_HOURS * 3600) {
                    continue;
                }
            }

            // Get current price for the tracker's geo region.
            $prices = $this->price_fetcher->get_prices($product_id, $tracker_geo);

            if (empty($prices) || !isset($prices[0]['price'])) {
                continue;
            }

            // Skip out-of-stock products.
            if (empty($prices[0]['in_stock'])) {
                continue;
            }

            $current_price = (float) $prices[0]['price'];
            $price_currency = $prices[0]['currency'] ?? $tracker_currency;

            // Skip invalid prices.
            if ($current_price <= 0) {
                continue;
            }

            // Determine comparison price.
            $compare_price = $last_notified_price !== null ? $last_notified_price : $start_price;

            // Skip if no valid comparison price.
            if (empty($compare_price) || $compare_price <= 0) {
                continue;
            }

            // If price is above target, record it for future re-notification.
            if ($target_price !== null && $current_price > $target_price) {
                $this->price_tracker->record_above_target($tracker['id']);
                continue;
            }

            // Check if notification criteria are met.
            $should_notify = false;
            $notification_type = '';

            if ($target_price !== null && $current_price <= $target_price) {
                // Target price with re-notification support.
                $last_seen_above = $tracker['last_seen_above_target'];
                $last_notified = $tracker['last_notification_time'];

                $never_notified = $last_notified === null;
                $price_lower = $last_notified_price !== null && $current_price < $last_notified_price;
                $rebounded = $last_seen_above !== null && $last_notified !== null
                             && strtotime($last_seen_above) > strtotime($last_notified);

                if ($never_notified || $price_lower || $rebounded) {
                    $should_notify = true;
                    $notification_type = 'target';
                }
            } elseif ($price_drop !== null) {
                // Check for price drop threshold.
                $price_difference = $compare_price - $current_price;
                if ($price_difference >= $price_drop) {
                    $should_notify = true;
                    $notification_type = 'drop';
                }
            }

            if ($should_notify) {
                $product = get_post($product_id);

                if (!$product) {
                    continue;
                }

                // Get product image.
                $acf_fields = get_fields($product_id);
                $thumbnail_id = $acf_fields['big_thumbnail'] ?? get_post_thumbnail_id($product_id);
                $image_url = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'thumbnail') : '';

                // Calculate savings vs previous price.
                $savings = $compare_price - $current_price;
                $savings_percent = round(($savings / $compare_price) * 100);

                // Get 6-month average price for "below avg" display.
                $stats = $this->price_history->get_statistics($product_id, 180, $tracker_geo, $price_currency);
                $average_price = $stats['average_price'] ?? null;
                $percent_below_avg = null;

                if ($average_price && $average_price > 0 && $current_price < $average_price) {
                    $percent_below_avg = round((($average_price - $current_price) / $average_price) * 100);
                }

                // Get tracking users count (for social proof).
                $tracking_users = $this->price_tracker->count_for_product($product_id);

                $deals[] = [
                    'product_id'        => $product_id,
                    'product_name'      => $product->post_title,
                    'current_price'     => $current_price,
                    'compare_price'     => $compare_price,
                    'savings'           => $savings,
                    'savings_percent'   => $savings_percent,
                    'average_price'     => $average_price,
                    'percent_below_avg' => $percent_below_avg,
                    'notification_type' => $notification_type,
                    'image_url'         => $image_url,
                    'url'               => get_permalink($product_id),
                    'tracking_users'    => $tracking_users,
                    'tracker_id'        => $tracker['id'],
                    'user_id'           => $user_id,
                    'geo'               => $tracker_geo,
                    'currency'          => $price_currency,
                ];
            }
        }

        return $deals;
    }

    /**
     * Send notification email to user via the email queue.
     *
     * @param \WP_User $user  The user object.
     * @param array    $deals Array of deals to include.
     * @return bool True if email was queued successfully.
     */
    private function send_notification(\WP_User $user, array $deals): bool {
        if (empty($deals)) {
            return false;
        }

        $user_name = $user->first_name ?: $user->display_name ?: $user->user_login;

        // Generate branded HTML using the new template.
        // Use the first deal's geo for template currency display.
        $geo = $deals[0]['geo'] ?? 'US';
        $template = new PriceAlertTemplate($geo);
        $html = $template->render($user_name, $deals);

        // Build the email subject - include product name for single-product alerts.
        $deal_count = count($deals);
        $product_name = $deal_count === 1 ? ($deals[0]['product_name'] ?? null) : null;
        $subject = PriceAlertTemplate::get_subject($deal_count, $product_name);

        // Queue the email instead of sending directly.
        $queue_repo = new EmailQueueRepository();
        $queued_id = $queue_repo->queue([
            'email_type'        => EmailQueue::TYPE_PRICE_ALERT,
            'recipient_email'   => $user->user_email,
            'recipient_user_id' => $user->ID,
            'subject'           => $subject,
            'body'              => $html,
            'headers'           => ['Content-Type: text/html; charset=UTF-8'],
            'priority'          => EmailQueue::PRIORITY_NORMAL,
        ]);

        if ($queued_id) {
            // Update last notified price for all deals.
            // We update this immediately as the notification is queued successfully.
            $this->update_last_notified_prices($user->ID, $deals);

            error_log(sprintf(
                '[ERH Cron] Price alert queued (ID: %d) for %s with %d deal(s).',
                $queued_id,
                $user->user_email,
                count($deals)
            ));
        } else {
            error_log(sprintf(
                '[ERH Cron] Failed to queue price alert for %s.',
                $user->user_email
            ));
        }

        return (bool) $queued_id;
    }

    /**
     * Update last notified price and time for processed deals.
     *
     * @param int $user_id The user ID.
     * @param array $deals Array of deals that were notified.
     * @return void
     */
    private function update_last_notified_prices(int $user_id, array $deals): void {
        foreach ($deals as $deal) {
            $this->price_tracker->update($deal['tracker_id'], [
                'last_notified_price'    => $deal['current_price'],
                'last_notification_time' => current_time('mysql'),
                'current_price'          => $deal['current_price'],
            ]);
        }
    }
}
