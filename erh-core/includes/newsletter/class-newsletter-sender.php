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
     * Get count of newsletter subscribers.
     *
     * @return int Subscriber count.
     */
    public function get_subscriber_count(): int {
        return count($this->get_subscribers());
    }

    /**
     * Get all newsletter subscribers.
     *
     * @return array<\WP_User> Array of user objects.
     */
    public function get_subscribers(): array {
        return get_users([
            'meta_key'   => UserRepository::META_NEWSLETTER_SUBSCRIPTION,
            'meta_value' => '1',
            'fields'     => 'all',
        ]);
    }

    /**
     * Queue a newsletter to all subscribers.
     *
     * @param int $newsletter_id The newsletter post ID.
     * @return array{total: int, queued: int} Results.
     */
    public function queue_newsletter(int $newsletter_id): array {
        $template    = new NewsletterTemplate();
        $html        = $template->render($newsletter_id);
        $subject     = get_the_title($newsletter_id);
        $subscribers = $this->get_subscribers();

        $queue_repo = new EmailQueueRepository();
        $queued     = 0;

        foreach ($subscribers as $user) {
            $result = $queue_repo->queue([
                'email_type'        => EmailQueue::TYPE_NEWSLETTER,
                'recipient_email'   => $user->user_email,
                'recipient_user_id' => $user->ID,
                'subject'           => $subject,
                'body'              => $html,
                'headers'           => ['Content-Type: text/html; charset=UTF-8'],
                'priority'          => EmailQueue::PRIORITY_LOW,
            ]);

            if ($result) {
                $queued++;
            }
        }

        return [
            'total'  => count($subscribers),
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
