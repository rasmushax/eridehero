<?php
/**
 * Deals digest cron job for sending periodic deals roundup emails.
 *
 * @package ERH\Cron
 */

declare(strict_types=1);

namespace ERH\Cron;

use ERH\CategoryConfig;
use ERH\Database\EmailQueue;
use ERH\Database\EmailQueueRepository;
use ERH\Email\DealsDigestTemplate;
use ERH\Pricing\DealsFinder;
use ERH\User\UserRepository;

/**
 * Sends deals digest emails to subscribed users based on their frequency preference.
 *
 * Runs daily. For each subscribed user, checks if enough time has elapsed since
 * their last digest (7 days for weekly, 14 for bi-weekly, 28 for monthly).
 * Collects deals for the user's selected categories and geo, renders via
 * DealsDigestTemplate, and queues through EmailQueueRepository.
 */
class DealsDigestJob implements CronJobInterface {

    /**
     * Minimum days between sends per frequency.
     */
    private const FREQUENCY_DAYS = [
        'weekly'    => 7,
        'bi-weekly' => 14,
        'monthly'   => 28,
    ];

    /**
     * Max deals to fetch per category for the email.
     */
    private const DEALS_PER_CATEGORY = 20;

    /**
     * Deals finder instance.
     *
     * @var DealsFinder
     */
    private DealsFinder $deals_finder;

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
     * @param DealsFinder    $deals_finder Deals finder instance.
     * @param UserRepository $user_repo    User repository instance.
     * @param CronManager    $cron_manager Cron manager instance.
     */
    public function __construct(
        DealsFinder $deals_finder,
        UserRepository $user_repo,
        CronManager $cron_manager
    ) {
        $this->deals_finder = $deals_finder;
        $this->user_repo = $user_repo;
        $this->cron_manager = $cron_manager;
    }

    /**
     * Get the job's display name.
     *
     * @return string
     */
    public function get_name(): string {
        return __('Deals Digest', 'erh-core');
    }

    /**
     * Get the job's description.
     *
     * @return string
     */
    public function get_description(): string {
        return __('Sends periodic deals digest emails to subscribed users.', 'erh-core');
    }

    /**
     * Get the WordPress hook name.
     *
     * @return string
     */
    public function get_hook_name(): string {
        return 'erh_cron_deals_digest';
    }

    /**
     * Get the cron schedule.
     *
     * @return string
     */
    public function get_schedule(): string {
        return 'daily';
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function execute(): void {
        if (!$this->cron_manager->lock_job('deals-digest', 1800)) {
            error_log('[ERH Cron] Deals digest job already running, skipping.');
            return;
        }

        try {
            $this->run();
        } finally {
            $this->cron_manager->unlock_job('deals-digest');
            $this->cron_manager->record_run_time('deals-digest');
        }
    }

    /**
     * Run the deals digest logic.
     *
     * @return void
     */
    private function run(): void {
        $users = $this->user_repo->get_sales_roundup_subscribers();

        if (empty($users)) {
            error_log('[ERH Cron] Deals digest: no subscribers found.');
            return;
        }

        $emails_queued = 0;
        $users_skipped_timing = 0;
        $users_skipped_no_deals = 0;

        foreach ($users as $user) {
            if (!$this->is_user_eligible($user->ID)) {
                $users_skipped_timing++;
                continue;
            }

            $queued = $this->process_user($user);

            if ($queued) {
                $emails_queued++;
            } else {
                $users_skipped_no_deals++;
            }
        }

        error_log(sprintf(
            '[ERH Cron] Deals digest completed. Queued: %d, Skipped (timing): %d, Skipped (no deals): %d',
            $emails_queued,
            $users_skipped_timing,
            $users_skipped_no_deals
        ));
    }

    /**
     * Check if a user is eligible for a digest based on their frequency preference.
     *
     * @param int $user_id The user ID.
     * @return bool True if enough time has elapsed since their last digest.
     */
    private function is_user_eligible(int $user_id): bool {
        $last_sent = $this->user_repo->get_last_deals_email_sent($user_id);

        // Never received a digest — always eligible.
        if ($last_sent === null) {
            return true;
        }

        $frequency = $this->user_repo->get_sales_roundup_frequency($user_id);
        $min_days = self::FREQUENCY_DAYS[$frequency] ?? self::FREQUENCY_DAYS['weekly'];

        $elapsed_seconds = time() - strtotime($last_sent);
        $elapsed_days = $elapsed_seconds / DAY_IN_SECONDS;

        return $elapsed_days >= $min_days;
    }

    /**
     * Process a single user: collect deals and queue email.
     *
     * @param \WP_User $user The user object.
     * @return bool True if an email was queued.
     */
    private function process_user(\WP_User $user): bool {
        $geo = $this->user_repo->get_geo_preference($user->ID);
        $category_keys = $this->user_repo->get_sales_roundup_types($user->ID);

        $deals_by_category = $this->collect_deals($category_keys, $geo);

        if (empty($deals_by_category)) {
            return false;
        }

        return $this->queue_digest_email($user, $deals_by_category, $geo);
    }

    /**
     * Collect deals for the given category keys and geo.
     *
     * @param array<string> $category_keys Finder keys (e.g., ['escooter', 'ebike']).
     * @param string        $geo           Geo region code.
     * @return array<string, array> Deals grouped by finder key. Empty categories omitted.
     */
    private function collect_deals(array $category_keys, string $geo): array {
        $deals_by_category = [];

        foreach ($category_keys as $finder_key) {
            $product_type = CategoryConfig::key_to_type($finder_key);

            if (empty($product_type)) {
                continue;
            }

            $deals = $this->deals_finder->get_deals(
                $product_type,
                DealsFinder::DEFAULT_THRESHOLD,
                self::DEALS_PER_CATEGORY,
                $geo
            );

            if (!empty($deals)) {
                $deals_by_category[$finder_key] = $deals;
            }
        }

        return $deals_by_category;
    }

    /**
     * Render and queue a deals digest email for a user.
     *
     * @param \WP_User             $user              The user object.
     * @param array<string, array> $deals_by_category Deals grouped by finder key.
     * @param string               $geo               User's geo region.
     * @return bool True if email was queued successfully.
     */
    private function queue_digest_email(\WP_User $user, array $deals_by_category, string $geo): bool {
        // Generate subject line (matches EmailSender::send_deals_digest logic).
        $category_count = count($deals_by_category);
        if ($category_count === 1) {
            $slug = array_key_first($deals_by_category);
            $name = CategoryConfig::get_name($slug, 'name_short') ?: 'Electric Ride';
            $subject = sprintf(__("This Week's Best %s Deals", 'erh-core'), $name);
        } else {
            $subject = __('Your Weekly Deals Roundup', 'erh-core');
        }

        // Render HTML via template.
        $unsubscribe_url = home_url('/account/#settings');
        $template = new DealsDigestTemplate($geo);
        $html = $template->render($deals_by_category, $unsubscribe_url);

        // Queue via email queue.
        $queue_repo = new EmailQueueRepository();
        $queued_id = $queue_repo->queue([
            'email_type'        => EmailQueue::TYPE_DEALS_DIGEST,
            'recipient_email'   => $user->user_email,
            'recipient_user_id' => $user->ID,
            'subject'           => $subject,
            'body'              => $html,
            'headers'           => ['Content-Type: text/html; charset=UTF-8'],
            'priority'          => EmailQueue::PRIORITY_NORMAL,
        ]);

        if ($queued_id) {
            // Update timestamp immediately to prevent double-sends on re-run.
            $this->user_repo->update_last_deals_email_sent($user->ID);

            error_log(sprintf(
                '[ERH Cron] Deals digest queued (ID: %d) for %s (%d categories, geo: %s).',
                $queued_id,
                $user->user_email,
                $category_count,
                $geo
            ));
        } else {
            error_log(sprintf(
                '[ERH Cron] Failed to queue deals digest for %s.',
                $user->user_email
            ));
        }

        return (bool) $queued_id;
    }
}
