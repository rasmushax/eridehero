<?php
/**
 * Email Queue cron job for batch processing queued emails.
 *
 * @package ERH\Cron
 */

declare(strict_types=1);

namespace ERH\Cron;

use ERH\Database\EmailQueue;
use ERH\Database\EmailQueueRepository;

/**
 * Processes queued emails in batches.
 *
 * This job runs every 2 minutes and sends up to 50 emails per batch.
 * Failed emails are retried with exponential backoff (5min, 15min, 45min).
 */
class EmailQueueJob implements CronJobInterface {

    /**
     * Number of emails to process per batch.
     */
    private const BATCH_SIZE = 50;

    /**
     * Minutes after which a stuck 'processing' email is considered stale.
     */
    private const STALE_THRESHOLD_MINUTES = 5;

    /**
     * Email queue repository.
     *
     * @var EmailQueueRepository
     */
    private EmailQueueRepository $queue_repo;

    /**
     * Cron manager for locking.
     *
     * @var CronManager
     */
    private CronManager $cron_manager;

    /**
     * Constructor.
     *
     * @param EmailQueueRepository $queue_repo   Email queue repository.
     * @param CronManager          $cron_manager Cron manager instance.
     */
    public function __construct(
        EmailQueueRepository $queue_repo,
        CronManager $cron_manager
    ) {
        $this->queue_repo = $queue_repo;
        $this->cron_manager = $cron_manager;
    }

    /**
     * Get the job's display name.
     *
     * @return string
     */
    public function get_name(): string {
        return __('Email Queue Processor', 'erh-core');
    }

    /**
     * Get the job's description.
     *
     * @return string
     */
    public function get_description(): string {
        return __('Processes queued emails in batches of 50 every 2 minutes.', 'erh-core');
    }

    /**
     * Get the WordPress hook name.
     *
     * @return string
     */
    public function get_hook_name(): string {
        return 'erh_cron_email_queue';
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
        if (!$this->cron_manager->lock_job('email_queue', 120)) {
            error_log('[ERH Email Queue] Job already running, skipping.');
            return;
        }

        try {
            // Release any stale processing emails first.
            $released = $this->queue_repo->release_stale(self::STALE_THRESHOLD_MINUTES);
            if ($released > 0) {
                error_log(sprintf('[ERH Email Queue] Released %d stale email(s)', $released));
            }

            // Process the batch.
            $this->process_batch();
        } finally {
            $this->cron_manager->unlock_job('email_queue');
            $this->cron_manager->record_run_time('email_queue');
        }
    }

    /**
     * Process a batch of pending emails.
     *
     * @return void
     */
    private function process_batch(): void {
        $emails = $this->queue_repo->get_pending(self::BATCH_SIZE);

        if (empty($emails)) {
            return;
        }

        $sent = 0;
        $failed = 0;

        foreach ($emails as $email) {
            $email_id = (int) $email['id'];

            // Claim the email first (prevents double-send in race conditions).
            if (!$this->queue_repo->claim($email_id)) {
                // Already claimed by another process.
                continue;
            }

            // Build headers.
            $headers = $this->parse_headers($email['headers']);

            // Attempt to send via wp_mail.
            $result = wp_mail(
                $email['recipient_email'],
                $email['subject'],
                $email['body'],
                $headers
            );

            if ($result) {
                $this->queue_repo->mark_sent($email_id);
                $sent++;
            } else {
                // Get error from phpmailer if available.
                $error = $this->get_mail_error();
                $this->queue_repo->mark_failed($email_id, $error);
                $failed++;

                // Log the failure for debugging.
                error_log(sprintf(
                    '[ERH Email Queue] Failed to send email #%d to %s: %s',
                    $email_id,
                    $email['recipient_email'],
                    $error
                ));
            }
        }

        // Log batch summary if any emails were processed.
        if ($sent > 0 || $failed > 0) {
            error_log(sprintf(
                '[ERH Email Queue] Batch processed: %d sent, %d failed',
                $sent,
                $failed
            ));
        }
    }

    /**
     * Parse headers string back to array.
     *
     * @param string|null $headers Headers string (separated by \r\n).
     * @return array Headers array.
     */
    private function parse_headers(?string $headers): array {
        if (empty($headers)) {
            return ['Content-Type: text/html; charset=UTF-8'];
        }

        $parsed = explode("\r\n", $headers);
        return array_filter($parsed);
    }

    /**
     * Get the last mail error from PHPMailer.
     *
     * @return string Error message or 'Unknown error'.
     */
    private function get_mail_error(): string {
        global $phpmailer;

        if (isset($phpmailer) && isset($phpmailer->ErrorInfo) && $phpmailer->ErrorInfo) {
            return $phpmailer->ErrorInfo;
        }

        return 'Unknown error';
    }
}
