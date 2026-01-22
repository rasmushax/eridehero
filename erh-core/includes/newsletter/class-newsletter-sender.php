<?php
/**
 * Newsletter Sender - Handles queuing and sending newsletters.
 *
 * @package ERH\Newsletter
 */

declare(strict_types=1);

namespace ERH\Newsletter;

use ERH\Database\EmailQueue;
use ERH\Database\EmailQueueRepository;
use ERH\User\UserRepository;

/**
 * Handles queuing newsletters to subscribers.
 */
class NewsletterSender {

    /**
     * Email queue repository instance.
     *
     * @var EmailQueueRepository|null
     */
    private ?EmailQueueRepository $queue_repo = null;

    /**
     * Get queue repository (lazy-loaded singleton).
     *
     * @return EmailQueueRepository
     */
    private function get_queue_repo(): EmailQueueRepository {
        if ($this->queue_repo === null) {
            $this->queue_repo = new EmailQueueRepository();
        }
        return $this->queue_repo;
    }

    /**
     * Get count of newsletter subscribers.
     *
     * Uses optimized count query instead of fetching all users.
     *
     * @return int Subscriber count.
     */
    public function get_subscriber_count(): int {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta}
             WHERE meta_key = %s AND meta_value = %s",
            UserRepository::META_NEWSLETTER_SUBSCRIPTION,
            '1'
        ));

        return (int) $count;
    }

    /**
     * Get all newsletter subscribers (minimal data).
     *
     * Only fetches ID and email to minimize memory usage.
     *
     * @return array Array of stdClass with ID and user_email.
     */
    public function get_subscribers(): array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.user_email
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
             WHERE um.meta_key = %s AND um.meta_value = %s",
            UserRepository::META_NEWSLETTER_SUBSCRIPTION,
            '1'
        )) ?: [];
    }

    /**
     * Queue a newsletter to all subscribers.
     *
     * Uses batch insert for efficiency with large subscriber lists.
     *
     * @param int $newsletter_id The newsletter post ID.
     * @return array{total: int, queued: int} Results.
     */
    public function queue_newsletter(int $newsletter_id): array {
        $template    = new NewsletterTemplate();
        $html        = $template->render($newsletter_id);
        $subject     = get_the_title($newsletter_id);
        $subscribers = $this->get_subscribers();
        $total       = count($subscribers);

        if ($total === 0) {
            return ['total' => 0, 'queued' => 0];
        }

        // Build batch of emails.
        $emails = [];
        foreach ($subscribers as $user) {
            $emails[] = [
                'email_type'        => EmailQueue::TYPE_NEWSLETTER,
                'recipient_email'   => $user->user_email,
                'recipient_user_id' => $user->ID,
                'subject'           => $subject,
                'body'              => $html,
                'headers'           => ['Content-Type: text/html; charset=UTF-8'],
                'priority'          => EmailQueue::PRIORITY_LOW,
            ];
        }

        // Queue all at once using batch insert.
        $queued = $this->get_queue_repo()->queue_batch($emails);

        return [
            'total'  => $total,
            'queued' => $queued,
        ];
    }

    /**
     * Send a test newsletter email.
     *
     * @param int    $newsletter_id The newsletter post ID.
     * @param string $email         Recipient email address.
     * @return bool True on success.
     */
    public function send_test(int $newsletter_id, string $email): bool {
        if (!is_email($email)) {
            return false;
        }

        $template = new NewsletterTemplate();
        $html     = $template->render($newsletter_id);
        $subject  = '[TEST] ' . get_the_title($newsletter_id);

        return wp_mail($email, $subject, $html, [
            'Content-Type: text/html; charset=UTF-8',
        ]);
    }

    /**
     * Get rendered preview HTML for a newsletter.
     *
     * @param int $newsletter_id The newsletter post ID.
     * @return string Rendered HTML.
     */
    public function get_preview_html(int $newsletter_id): string {
        $template = new NewsletterTemplate();
        return $template->render($newsletter_id);
    }
}
