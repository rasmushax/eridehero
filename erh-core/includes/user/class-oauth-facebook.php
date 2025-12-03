<?php
/**
 * Facebook OAuth Provider - Handles Facebook OAuth 2.0 authentication.
 *
 * @package ERH\User
 */

declare(strict_types=1);

namespace ERH\User;

/**
 * Facebook OAuth 2.0 provider implementation.
 */
class OAuthFacebook implements OAuthProviderInterface {

    /**
     * Provider name.
     */
    private const NAME = 'facebook';

    /**
     * Facebook OAuth endpoints.
     */
    private const AUTH_URL = 'https://www.facebook.com/v18.0/dialog/oauth';
    private const TOKEN_URL = 'https://graph.facebook.com/v18.0/oauth/access_token';
    private const USERINFO_URL = 'https://graph.facebook.com/v18.0/me';

    /**
     * OAuth scopes.
     */
    private const SCOPES = 'email,public_profile';

    /**
     * Client ID (App ID).
     *
     * @var string
     */
    private string $client_id;

    /**
     * Client secret (App Secret).
     *
     * @var string
     */
    private string $client_secret;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->client_id = (string) get_option('erh_facebook_app_id', '');
        $this->client_secret = (string) get_option('erh_facebook_app_secret', '');
    }

    /**
     * {@inheritDoc}
     */
    public function get_name(): string {
        return self::NAME;
    }

    /**
     * {@inheritDoc}
     */
    public function get_authorization_url(string $state): string {
        $params = [
            'client_id'     => $this->client_id,
            'redirect_uri'  => $this->get_callback_url(),
            'response_type' => 'code',
            'scope'         => self::SCOPES,
            'state'         => $state,
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * {@inheritDoc}
     */
    public function get_access_token(string $code): ?array {
        $response = wp_remote_get(self::TOKEN_URL . '?' . http_build_query([
            'client_id'     => $this->client_id,
            'client_secret' => $this->client_secret,
            'code'          => $code,
            'redirect_uri'  => $this->get_callback_url(),
        ]), [
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            error_log('Facebook OAuth token error: ' . $response->get_error_message());
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['access_token'])) {
            error_log('Facebook OAuth token response missing access_token: ' . wp_remote_retrieve_body($response));
            return null;
        }

        return [
            'access_token'  => $body['access_token'],
            'token_type'    => $body['token_type'] ?? 'Bearer',
            'expires_in'    => $body['expires_in'] ?? 3600,
            'refresh_token' => null, // Facebook doesn't use refresh tokens in the same way.
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function get_user_profile(string $access_token): ?array {
        $response = wp_remote_get(self::USERINFO_URL . '?' . http_build_query([
            'fields'       => 'id,email,name,picture.type(large)',
            'access_token' => $access_token,
        ]), [
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            error_log('Facebook OAuth userinfo error: ' . $response->get_error_message());
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['id'])) {
            error_log('Facebook OAuth userinfo missing id: ' . wp_remote_retrieve_body($response));
            return null;
        }

        // Facebook may not return email if not approved or user declined.
        $email = $body['email'] ?? '';

        return [
            'id'      => $body['id'],
            'email'   => $email,
            'name'    => $body['name'] ?? '',
            'picture' => $body['picture']['data']['url'] ?? '',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function is_configured(): bool {
        return !empty($this->client_id) && !empty($this->client_secret);
    }

    /**
     * {@inheritDoc}
     */
    public function get_callback_url(): string {
        return rest_url('erh/v1/auth/social/facebook/callback');
    }
}
