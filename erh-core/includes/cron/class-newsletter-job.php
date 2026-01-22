<?php
/**
 * Newsletter cron job for processing scheduled newsletters.
 *
 * @package ERH\Cron
 */

declare(strict_types=1);

namespace ERH\Cron;

use ERH\PostTypes\Newsletter;
use ERH\Newsletter\NewsletterSender;

/**
 * Checks for due scheduled newsletters and queues them for sending.
 *
 * Runs every 2 minutes to check if any newsletters are due for sending.
 * When a due newsletter is found, it queues all subscriber emails and
 * transitions the newsletter status from 'scheduled' to 'sent'.
 */
class NewsletterJob implements CronJobInterface {

    /**
     * Cron manager for locking.
     *
     * @var CronManager
     */
    private CronManager $cron_manager;

    /**
     * Constructor.
     *
     * @param CronManager $cron_manager Cron manager instance.
     */
    public function __construct(CronManager $cron_manager) {
        $this->cron_manager = $cron_manager;
    }

    /**
     * Get the job's display name.
     *
     * @return string
     */
    public function get_name(): string {
        return __('Newsletter Scheduler', 'erh-core');
    }

    /**
     * Get the job's description.
     *
     * @return string
     */
    public function get_description(): string {
        return __('Checks for scheduled newsletters and queues them for sending.', 'erh-core');
    }

    /**
     * Get the WordPress hook name.
     *
     * @return string
     */
    public function get_hook_name(): string {
        return 'erh_cron_newsletter';
    }

    /**
     * Get the cron schedule.
     *
     * @return string
     */
    public function get_schedule(): string {
        return 'erh_two_minutes';
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function execute(): void {
        // Acquire lock (2 minute timeout to match schedule).
        if (!$this->cron_manager->lock_job('newsletter', 120)) {
            error_log('[ERH Newsletter] Job already running, skipping.');
            return;
        }

        try {
            $due_newsletters = Newsletter::get_due_newsletters();

            if (empty($due_newsletters)) {
                return;
            }

            foreach ($due_newsletters as $newsletter_id) {
                $this->process_newsletter($newsletter_id);
            }
        } finally {
            $this->cron_manager->unlock_job('newsletter');
            $this->cron_manager->record_run_time('newsletter');
        }
    }

    /**
     * Process a single newsletter.
     *
     * @param int $newsletter_id The newsletter post ID.
     * @return void
     */
    private function process_newsletter(int $newsletter_id): void {
        $title = get_the_title($newsletter_id);

        error_log(sprintf(
            '[ERH Newsletter] Processing newsletter #%d: %s',
            $newsletter_id,
            $title
        ));

        // Transition: scheduled -> sending.
        if (!Newsletter::mark_sending($newsletter_id)) {
            error_log(sprintf(
                '[ERH Newsletter] Failed to mark newsletter #%d as sending',
                $newsletter_id
            ));
            return;
        }

        // Queue all subscriber emails.
        $sender = new NewsletterSender();
        $result = $sender->queue_newsletter($newsletter_id);

        // Transition: sending -> sent.
        Newsletter::mark_sent($newsletter_id, $result['total'], $result['queued']);

        error_log(sprintf(
            '[ERH Newsletter] Newsletter #%d queued: %d/%d recipients',
            $newsletter_id,
            $result['queued'],
            $result['total']
        ));
    }
}
