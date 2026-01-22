<?php
/**
 * Password Reset Email Template - Sent when user requests password reset.
 *
 * @package ERH\Email
 */

declare(strict_types=1);

namespace ERH\Email;

/**
 * Generates the password reset email.
 */
class PasswordResetTemplate extends EmailBuilder {

    /**
     * Generate the password reset email HTML.
     *
     * @param string $username  User's display name or username.
     * @param string $reset_url Password reset URL with token.
     * @return string Complete HTML email.
     */
    public function render(string $username, string $reset_url): string {
        $content = '';

        // Hero section - simpler for transactional email.
        $content .= $this->hero(
            'Password Reset',
            'Reset your password',
            'Click the button below to create a new password.'
        );

        // Main content card.
        $main_html = '
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td>
                        <p style="margin: 0 0 16px 0; font-size: 16px; color: ' . self::COLOR_BODY . '; line-height: 1.6;">
                            Hi ' . esc_html($username) . ',
                        </p>
                        <p style="margin: 0 0 24px 0; font-size: 16px; color: ' . self::COLOR_BODY . '; line-height: 1.6;">
                            We received a request to reset your password. Click the button below to choose a new one:
                        </p>
                        ' . $this->button($reset_url, 'Reset Password', self::COLOR_PRIMARY) . '
                        <p style="margin: 24px 0 0 0; font-size: 14px; color: ' . self::COLOR_MUTED . '; line-height: 1.6;">
                            This link expires in 24 hours. If the button doesn\'t work, copy and paste this URL into your browser:
                        </p>
                        <p style="margin: 8px 0 0 0; font-size: 13px; color: ' . self::COLOR_PRIMARY . '; word-break: break-all;">
                            <a href="' . esc_url($reset_url) . '" style="color: ' . self::COLOR_PRIMARY . ';">' . esc_html($reset_url) . '</a>
                        </p>
                    </td>
                </tr>
            </table>';

        $content .= $this->card($main_html);

        // Security notice card.
        $security_html = '
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td>
                        <p style="margin: 0; font-size: 14px; color: ' . self::COLOR_MUTED . '; line-height: 1.6;">
                            <strong style="color: ' . self::COLOR_BODY . ';">Didn\'t request this?</strong><br>
                            If you didn\'t ask to reset your password, you can safely ignore this email. Your password won\'t change until you click the button above and create a new one.
                        </p>
                    </td>
                </tr>
            </table>';

        $content .= $this->card($security_html);

        return $this->build([
            'preview_text' => 'Reset your ERideHero password',
            'content'      => $content,
            'unsubscribe'  => '',
        ]);
    }

    /**
     * Get the email subject line.
     *
     * @return string Subject line.
     */
    public static function get_subject(): string {
        return 'Reset your ERideHero password';
    }
}
