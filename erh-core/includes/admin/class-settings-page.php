<?php
/**
 * Settings Page - Admin settings for ERH Core plugin.
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

use ERH\Cron\CronManager;

/**
 * Handles the ERH Core settings page in WordPress admin.
 */
class SettingsPage {

    /**
     * Cron manager instance.
     *
     * @var CronManager|null
     */
    private ?CronManager $cron_manager = null;

    /**
     * Option group name.
     */
    public const OPTION_GROUP = 'erh_settings';

    /**
     * Settings page slug.
     */
    public const PAGE_SLUG = 'erh-settings';

    /**
     * Set the cron manager instance.
     *
     * @param CronManager $cron_manager The cron manager.
     * @return void
     */
    public function set_cron_manager(CronManager $cron_manager): void {
        $this->cron_manager = $cron_manager;
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_erh_test_mailchimp', [$this, 'ajax_test_mailchimp']);
        add_action('wp_ajax_erh_run_cron_job', [$this, 'ajax_run_cron_job']);
    }

    /**
     * Add the settings menu page.
     *
     * @return void
     */
    public function add_menu_page(): void {
        add_options_page(
            __('ERideHero Settings', 'erh-core'),
            __('ERideHero', 'erh-core'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    /**
     * Register all settings.
     *
     * @return void
     */
    public function register_settings(): void {
        // Social Login Settings.
        $this->register_social_settings();

        // Mailchimp Settings.
        $this->register_mailchimp_settings();

        // General Settings.
        $this->register_general_settings();
    }

    /**
     * Register social login settings.
     *
     * @return void
     */
    private function register_social_settings(): void {
        // Google OAuth.
        register_setting(self::OPTION_GROUP, 'erh_google_client_id', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);
        register_setting(self::OPTION_GROUP, 'erh_google_client_secret', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        // Facebook OAuth.
        register_setting(self::OPTION_GROUP, 'erh_facebook_app_id', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);
        register_setting(self::OPTION_GROUP, 'erh_facebook_app_secret', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        // Reddit OAuth.
        register_setting(self::OPTION_GROUP, 'erh_reddit_client_id', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);
        register_setting(self::OPTION_GROUP, 'erh_reddit_client_secret', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);
    }

    /**
     * Register Mailchimp settings.
     *
     * @return void
     */
    private function register_mailchimp_settings(): void {
        register_setting(self::OPTION_GROUP, 'erh_mailchimp_api_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);
        register_setting(self::OPTION_GROUP, 'erh_mailchimp_list_id', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);
    }

    /**
     * Register general settings.
     *
     * @return void
     */
    private function register_general_settings(): void {
        register_setting(self::OPTION_GROUP, 'erh_email_preferences_page_id', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ]);
    }

    /**
     * Render the settings page.
     *
     * @return void
     */
    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get current tab.
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'social';

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url(admin_url('options-general.php?page=' . self::PAGE_SLUG . '&tab=social')); ?>"
                   class="nav-tab <?php echo $current_tab === 'social' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Social Login', 'erh-core'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=' . self::PAGE_SLUG . '&tab=mailchimp')); ?>"
                   class="nav-tab <?php echo $current_tab === 'mailchimp' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Mailchimp', 'erh-core'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=' . self::PAGE_SLUG . '&tab=general')); ?>"
                   class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('General', 'erh-core'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=' . self::PAGE_SLUG . '&tab=cron')); ?>"
                   class="nav-tab <?php echo $current_tab === 'cron' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Cron Jobs', 'erh-core'); ?>
                </a>
            </nav>

            <form action="options.php" method="post">
                <?php
                settings_fields(self::OPTION_GROUP);

                switch ($current_tab) {
                    case 'mailchimp':
                        $this->render_mailchimp_tab();
                        submit_button();
                        break;
                    case 'general':
                        $this->render_general_tab();
                        submit_button();
                        break;
                    case 'cron':
                        echo '</form>'; // Close the form early for cron tab.
                        $this->render_cron_tab();
                        return; // Skip the submit button for cron tab.
                    default:
                        $this->render_social_tab();
                        submit_button();
                        break;
                }
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the social login tab.
     *
     * @return void
     */
    private function render_social_tab(): void {
        ?>
        <h2><?php esc_html_e('Social Login Configuration', 'erh-core'); ?></h2>
        <p class="description">
            <?php esc_html_e('Configure OAuth credentials for social login providers. Users can sign in with these accounts.', 'erh-core'); ?>
        </p>

        <table class="form-table" role="presentation">
            <!-- Google OAuth -->
            <tr>
                <th scope="row" colspan="2">
                    <h3 style="margin-bottom: 0;"><?php esc_html_e('Google OAuth', 'erh-core'); ?></h3>
                    <p class="description" style="font-weight: normal;">
                        <?php
                        printf(
                            /* translators: %s: Google Console URL */
                            esc_html__('Create credentials at %s', 'erh-core'),
                            '<a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a>'
                        );
                        ?>
                    </p>
                </th>
            </tr>
            <tr>
                <th scope="row">
                    <label for="erh_google_client_id"><?php esc_html_e('Client ID', 'erh-core'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="erh_google_client_id"
                           name="erh_google_client_id"
                           value="<?php echo esc_attr(get_option('erh_google_client_id', '')); ?>"
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="erh_google_client_secret"><?php esc_html_e('Client Secret', 'erh-core'); ?></label>
                </th>
                <td>
                    <input type="password"
                           id="erh_google_client_secret"
                           name="erh_google_client_secret"
                           value="<?php echo esc_attr(get_option('erh_google_client_secret', '')); ?>"
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Callback URL', 'erh-core'); ?></th>
                <td>
                    <code><?php echo esc_html(rest_url('erh/v1/auth/social/google/callback')); ?></code>
                    <p class="description"><?php esc_html_e('Add this URL to your Google OAuth authorized redirect URIs.', 'erh-core'); ?></p>
                </td>
            </tr>

            <!-- Facebook OAuth -->
            <tr>
                <th scope="row" colspan="2">
                    <h3 style="margin-bottom: 0; margin-top: 2em;"><?php esc_html_e('Facebook OAuth', 'erh-core'); ?></h3>
                    <p class="description" style="font-weight: normal;">
                        <?php
                        printf(
                            /* translators: %s: Facebook Developers URL */
                            esc_html__('Create an app at %s', 'erh-core'),
                            '<a href="https://developers.facebook.com/apps/" target="_blank">Facebook Developers</a>'
                        );
                        ?>
                    </p>
                </th>
            </tr>
            <tr>
                <th scope="row">
                    <label for="erh_facebook_app_id"><?php esc_html_e('App ID', 'erh-core'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="erh_facebook_app_id"
                           name="erh_facebook_app_id"
                           value="<?php echo esc_attr(get_option('erh_facebook_app_id', '')); ?>"
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="erh_facebook_app_secret"><?php esc_html_e('App Secret', 'erh-core'); ?></label>
                </th>
                <td>
                    <input type="password"
                           id="erh_facebook_app_secret"
                           name="erh_facebook_app_secret"
                           value="<?php echo esc_attr(get_option('erh_facebook_app_secret', '')); ?>"
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Callback URL', 'erh-core'); ?></th>
                <td>
                    <code><?php echo esc_html(rest_url('erh/v1/auth/social/facebook/callback')); ?></code>
                    <p class="description"><?php esc_html_e('Add this URL to your Facebook app Valid OAuth Redirect URIs.', 'erh-core'); ?></p>
                </td>
            </tr>

            <!-- Reddit OAuth -->
            <tr>
                <th scope="row" colspan="2">
                    <h3 style="margin-bottom: 0; margin-top: 2em;"><?php esc_html_e('Reddit OAuth', 'erh-core'); ?></h3>
                    <p class="description" style="font-weight: normal;">
                        <?php
                        printf(
                            /* translators: %s: Reddit Apps URL */
                            esc_html__('Create an app at %s (select "web app" type)', 'erh-core'),
                            '<a href="https://www.reddit.com/prefs/apps" target="_blank">Reddit Apps</a>'
                        );
                        ?>
                    </p>
                </th>
            </tr>
            <tr>
                <th scope="row">
                    <label for="erh_reddit_client_id"><?php esc_html_e('Client ID', 'erh-core'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="erh_reddit_client_id"
                           name="erh_reddit_client_id"
                           value="<?php echo esc_attr(get_option('erh_reddit_client_id', '')); ?>"
                           class="regular-text">
                    <p class="description"><?php esc_html_e('The string under "web app" in your Reddit app settings.', 'erh-core'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="erh_reddit_client_secret"><?php esc_html_e('Client Secret', 'erh-core'); ?></label>
                </th>
                <td>
                    <input type="password"
                           id="erh_reddit_client_secret"
                           name="erh_reddit_client_secret"
                           value="<?php echo esc_attr(get_option('erh_reddit_client_secret', '')); ?>"
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Callback URL', 'erh-core'); ?></th>
                <td>
                    <code><?php echo esc_html(rest_url('erh/v1/auth/social/reddit/callback')); ?></code>
                    <p class="description"><?php esc_html_e('Add this URL as the redirect URI in your Reddit app.', 'erh-core'); ?></p>
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e('Provider Status', 'erh-core'); ?></h3>
        <table class="widefat" style="max-width: 600px;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Provider', 'erh-core'); ?></th>
                    <th><?php esc_html_e('Status', 'erh-core'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $providers = [
                    'Google'   => !empty(get_option('erh_google_client_id')) && !empty(get_option('erh_google_client_secret')),
                    'Facebook' => !empty(get_option('erh_facebook_app_id')) && !empty(get_option('erh_facebook_app_secret')),
                    'Reddit'   => !empty(get_option('erh_reddit_client_id')) && !empty(get_option('erh_reddit_client_secret')),
                ];

                foreach ($providers as $name => $configured) :
                    ?>
                    <tr>
                        <td><?php echo esc_html($name); ?></td>
                        <td>
                            <?php if ($configured) : ?>
                                <span style="color: #46b450;">&#10003; <?php esc_html_e('Configured', 'erh-core'); ?></span>
                            <?php else : ?>
                                <span style="color: #dc3232;">&#10007; <?php esc_html_e('Not configured', 'erh-core'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render the Mailchimp tab.
     *
     * @return void
     */
    private function render_mailchimp_tab(): void {
        $api_key = get_option('erh_mailchimp_api_key', '');
        $list_id = get_option('erh_mailchimp_list_id', '');
        $is_configured = !empty($api_key) && !empty($list_id);

        ?>
        <h2><?php esc_html_e('Mailchimp Configuration', 'erh-core'); ?></h2>
        <p class="description">
            <?php esc_html_e('Connect your Mailchimp account to sync newsletter subscriptions.', 'erh-core'); ?>
        </p>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="erh_mailchimp_api_key"><?php esc_html_e('API Key', 'erh-core'); ?></label>
                </th>
                <td>
                    <input type="password"
                           id="erh_mailchimp_api_key"
                           name="erh_mailchimp_api_key"
                           value="<?php echo esc_attr($api_key); ?>"
                           class="regular-text">
                    <p class="description">
                        <?php
                        printf(
                            /* translators: %s: Mailchimp API keys URL */
                            esc_html__('Get your API key from %s', 'erh-core'),
                            '<a href="https://admin.mailchimp.com/account/api/" target="_blank">Mailchimp Account &rarr; API Keys</a>'
                        );
                        ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="erh_mailchimp_list_id"><?php esc_html_e('Audience/List ID', 'erh-core'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="erh_mailchimp_list_id"
                           name="erh_mailchimp_list_id"
                           value="<?php echo esc_attr($list_id); ?>"
                           class="regular-text">
                    <p class="description">
                        <?php esc_html_e('Find this in Mailchimp: Audience &rarr; Settings &rarr; Audience name and defaults.', 'erh-core'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Webhook URL', 'erh-core'); ?></th>
                <td>
                    <code><?php echo esc_html(rest_url('erh/v1/webhooks/mailchimp')); ?></code>
                    <p class="description">
                        <?php esc_html_e('Add this webhook in Mailchimp: Audience &rarr; Settings &rarr; Webhooks. Enable "Unsubscribes" and "Email Changed" events.', 'erh-core'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Connection Status', 'erh-core'); ?></th>
                <td>
                    <div id="erh-mailchimp-status">
                        <?php if ($is_configured) : ?>
                            <button type="button" class="button" id="erh-test-mailchimp">
                                <?php esc_html_e('Test Connection', 'erh-core'); ?>
                            </button>
                            <span id="erh-mailchimp-result" style="margin-left: 10px;"></span>
                        <?php else : ?>
                            <span style="color: #dc3232;">
                                <?php esc_html_e('Enter API key and List ID, then save to enable testing.', 'erh-core'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        </table>

        <?php if ($is_configured) : ?>
        <script>
        jQuery(document).ready(function($) {
            $('#erh-test-mailchimp').on('click', function() {
                var $btn = $(this);
                var $result = $('#erh-mailchimp-result');

                $btn.prop('disabled', true).text('<?php esc_html_e('Testing...', 'erh-core'); ?>');
                $result.html('');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'erh_test_mailchimp',
                        _wpnonce: '<?php echo wp_create_nonce('erh_test_mailchimp'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<span style="color: #46b450;">&#10003; ' + response.data.message + '</span>');
                        } else {
                            $result.html('<span style="color: #dc3232;">&#10007; ' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        $result.html('<span style="color: #dc3232;">&#10007; <?php esc_html_e('Connection failed', 'erh-core'); ?></span>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('<?php esc_html_e('Test Connection', 'erh-core'); ?>');
                    }
                });
            });
        });
        </script>
        <?php endif; ?>
        <?php
    }

    /**
     * Render the general tab.
     *
     * @return void
     */
    private function render_general_tab(): void {
        ?>
        <h2><?php esc_html_e('General Settings', 'erh-core'); ?></h2>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="erh_email_preferences_page_id"><?php esc_html_e('Email Preferences Page', 'erh-core'); ?></label>
                </th>
                <td>
                    <?php
                    wp_dropdown_pages([
                        'name'              => 'erh_email_preferences_page_id',
                        'id'                => 'erh_email_preferences_page_id',
                        'selected'          => get_option('erh_email_preferences_page_id', 0),
                        'show_option_none'  => __('— Select —', 'erh-core'),
                        'option_none_value' => 0,
                    ]);
                    ?>
                    <p class="description">
                        <?php esc_html_e('New users will be redirected here to set their email preferences after first login.', 'erh-core'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e('System Information', 'erh-core'); ?></h3>
        <table class="widefat" style="max-width: 600px;">
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e('Plugin Version', 'erh-core'); ?></strong></td>
                    <td><?php echo defined('ERH_VERSION') ? esc_html(ERH_VERSION) : 'N/A'; ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('REST API Base', 'erh-core'); ?></strong></td>
                    <td><code><?php echo esc_html(rest_url('erh/v1/')); ?></code></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('HFT Plugin', 'erh-core'); ?></strong></td>
                    <td>
                        <?php if (defined('HFT_VERSION')) : ?>
                            <span style="color: #46b450;">&#10003; <?php echo esc_html(sprintf(__('Active (v%s)', 'erh-core'), HFT_VERSION)); ?></span>
                        <?php else : ?>
                            <span style="color: #dc3232;">&#10007; <?php esc_html_e('Not active', 'erh-core'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * AJAX handler to test Mailchimp connection.
     *
     * @return void
     */
    public function ajax_test_mailchimp(): void {
        check_ajax_referer('erh_test_mailchimp');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        $api_key = get_option('erh_mailchimp_api_key', '');
        $list_id = get_option('erh_mailchimp_list_id', '');

        if (empty($api_key) || empty($list_id)) {
            wp_send_json_error(['message' => __('API key or List ID not configured.', 'erh-core')]);
        }

        // Extract datacenter from API key.
        $dc = substr($api_key, strpos($api_key, '-') + 1);
        $url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}";

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode('anystring:' . $api_key),
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 200 && !empty($body['name'])) {
            wp_send_json_success([
                'message' => sprintf(
                    /* translators: %1$s: list name, %2$d: subscriber count */
                    __('Connected to "%1$s" (%2$d subscribers)', 'erh-core'),
                    $body['name'],
                    $body['stats']['member_count'] ?? 0
                ),
            ]);
        } elseif ($code === 401) {
            wp_send_json_error(['message' => __('Invalid API key.', 'erh-core')]);
        } elseif ($code === 404) {
            wp_send_json_error(['message' => __('List/Audience not found. Check your List ID.', 'erh-core')]);
        } else {
            wp_send_json_error(['message' => $body['detail'] ?? __('Unknown error.', 'erh-core')]);
        }
    }

    /**
     * Render the cron jobs tab.
     *
     * @return void
     */
    private function render_cron_tab(): void {
        if (!$this->cron_manager) {
            echo '<p>' . esc_html__('Cron manager not initialized.', 'erh-core') . '</p>';
            return;
        }

        $jobs_status = $this->cron_manager->get_all_status();

        ?>
        <h2><?php esc_html_e('Cron Jobs', 'erh-core'); ?></h2>
        <p class="description">
            <?php esc_html_e('Manage scheduled background tasks. Use "Run Now" to manually trigger a job for testing.', 'erh-core'); ?>
        </p>

        <?php if (empty($jobs_status)) : ?>
            <p><?php esc_html_e('No cron jobs registered.', 'erh-core'); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped" style="max-width: 900px;">
                <thead>
                    <tr>
                        <th style="width: 25%;"><?php esc_html_e('Job', 'erh-core'); ?></th>
                        <th style="width: 15%;"><?php esc_html_e('Schedule', 'erh-core'); ?></th>
                        <th style="width: 20%;"><?php esc_html_e('Last Run', 'erh-core'); ?></th>
                        <th style="width: 20%;"><?php esc_html_e('Next Run', 'erh-core'); ?></th>
                        <th style="width: 20%;"><?php esc_html_e('Actions', 'erh-core'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs_status as $key => $info) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($info['name']); ?></strong>
                                <p class="description" style="margin: 5px 0 0 0;">
                                    <?php echo esc_html($info['description']); ?>
                                </p>
                            </td>
                            <td>
                                <?php echo esc_html($this->format_schedule($info['schedule'])); ?>
                            </td>
                            <td>
                                <?php if ($info['last_run']) : ?>
                                    <?php echo esc_html($this->format_time_ago($info['last_run'])); ?>
                                    <br>
                                    <small style="color: #666;">
                                        <?php echo esc_html(date_i18n('M j, g:i a', strtotime($info['last_run']))); ?>
                                    </small>
                                <?php else : ?>
                                    <span style="color: #999;"><?php esc_html_e('Never', 'erh-core'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($info['next_run']) : ?>
                                    <?php echo esc_html($this->format_time_ago($info['next_run'], true)); ?>
                                    <br>
                                    <small style="color: #666;">
                                        <?php echo esc_html(date_i18n('M j, g:i a', strtotime($info['next_run']))); ?>
                                    </small>
                                <?php else : ?>
                                    <span style="color: #dc3232;"><?php esc_html_e('Not scheduled', 'erh-core'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($info['is_running']) : ?>
                                    <span style="color: #0073aa;">
                                        <span class="dashicons dashicons-update" style="animation: rotation 2s infinite linear;"></span>
                                        <?php esc_html_e('Running...', 'erh-core'); ?>
                                    </span>
                                <?php else : ?>
                                    <button type="button"
                                            class="button erh-run-cron-job"
                                            data-job-key="<?php echo esc_attr($key); ?>"
                                            data-job-name="<?php echo esc_attr($info['name']); ?>">
                                        <?php esc_html_e('Run Now', 'erh-core'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <style>
                @keyframes rotation {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
            </style>

            <script>
            jQuery(document).ready(function($) {
                $('.erh-run-cron-job').on('click', function() {
                    var $btn = $(this);
                    var jobKey = $btn.data('job-key');
                    var jobName = $btn.data('job-name');

                    if (!confirm('<?php esc_html_e('Run this job now?', 'erh-core'); ?> ' + jobName)) {
                        return;
                    }

                    $btn.prop('disabled', true).text('<?php esc_html_e('Running...', 'erh-core'); ?>');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'erh_run_cron_job',
                            job_key: jobKey,
                            _wpnonce: '<?php echo wp_create_nonce('erh_run_cron_job'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                                location.reload(); // Refresh to show updated last run time.
                            } else {
                                alert('Error: ' + response.data.message);
                                $btn.prop('disabled', false).text('<?php esc_html_e('Run Now', 'erh-core'); ?>');
                            }
                        },
                        error: function() {
                            alert('<?php esc_html_e('Request failed. Please try again.', 'erh-core'); ?>');
                            $btn.prop('disabled', false).text('<?php esc_html_e('Run Now', 'erh-core'); ?>');
                        }
                    });
                });
            });
            </script>
        <?php endif; ?>

        <h3 style="margin-top: 30px;"><?php esc_html_e('Cron Information', 'erh-core'); ?></h3>
        <table class="widefat" style="max-width: 600px;">
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e('WordPress Cron', 'erh-core'); ?></strong></td>
                    <td>
                        <?php if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) : ?>
                            <span style="color: #999;"><?php esc_html_e('Disabled (using system cron)', 'erh-core'); ?></span>
                        <?php else : ?>
                            <span style="color: #46b450;"><?php esc_html_e('Enabled', 'erh-core'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Custom Schedules', 'erh-core'); ?></strong></td>
                    <td>
                        <code>erh_two_hours</code> - <?php esc_html_e('Every 2 hours', 'erh-core'); ?><br>
                        <code>erh_six_hours</code> - <?php esc_html_e('Every 6 hours', 'erh-core'); ?><br>
                        <code>erh_twelve_hours</code> - <?php esc_html_e('Every 12 hours', 'erh-core'); ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * AJAX handler to run a cron job manually.
     *
     * @return void
     */
    public function ajax_run_cron_job(): void {
        check_ajax_referer('erh_run_cron_job');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        if (!$this->cron_manager) {
            wp_send_json_error(['message' => __('Cron manager not available.', 'erh-core')]);
        }

        $job_key = isset($_POST['job_key']) ? sanitize_key($_POST['job_key']) : '';

        if (empty($job_key)) {
            wp_send_json_error(['message' => __('No job specified.', 'erh-core')]);
        }

        $job = $this->cron_manager->get_job($job_key);

        if (!$job) {
            wp_send_json_error(['message' => __('Job not found.', 'erh-core')]);
        }

        // Run the job.
        $start = microtime(true);
        $this->cron_manager->run_job($job_key);
        $elapsed = round(microtime(true) - $start, 2);

        wp_send_json_success([
            'message' => sprintf(
                /* translators: %1$s: job name, %2$s: elapsed time */
                __('"%1$s" completed in %2$ss', 'erh-core'),
                $job->get_name(),
                $elapsed
            ),
        ]);
    }

    /**
     * Format a schedule name for display.
     *
     * @param string $schedule The schedule name.
     * @return string Formatted schedule name.
     */
    private function format_schedule(string $schedule): string {
        $schedules = [
            'daily'            => __('Daily', 'erh-core'),
            'twicedaily'       => __('Twice Daily', 'erh-core'),
            'hourly'           => __('Hourly', 'erh-core'),
            'erh_two_hours'    => __('Every 2 Hours', 'erh-core'),
            'erh_six_hours'    => __('Every 6 Hours', 'erh-core'),
            'erh_twelve_hours' => __('Every 12 Hours', 'erh-core'),
        ];

        return $schedules[$schedule] ?? $schedule;
    }

    /**
     * Format a time as relative (e.g., "2 hours ago").
     *
     * @param string $datetime The datetime string.
     * @param bool $future Whether this is a future time.
     * @return string Formatted time string.
     */
    private function format_time_ago(string $datetime, bool $future = false): string {
        $timestamp = strtotime($datetime);
        $now = current_time('timestamp');
        $diff = $future ? ($timestamp - $now) : ($now - $timestamp);

        if ($diff < 60) {
            return $future ? __('in a moment', 'erh-core') : __('just now', 'erh-core');
        }

        $minutes = floor($diff / 60);
        $hours = floor($diff / 3600);
        $days = floor($diff / 86400);

        if ($days > 0) {
            $text = sprintf(_n('%d day', '%d days', $days, 'erh-core'), $days);
        } elseif ($hours > 0) {
            $text = sprintf(_n('%d hour', '%d hours', $hours, 'erh-core'), $hours);
        } else {
            $text = sprintf(_n('%d minute', '%d minutes', $minutes, 'erh-core'), $minutes);
        }

        return $future
            ? sprintf(__('in %s', 'erh-core'), $text)
            : sprintf(__('%s ago', 'erh-core'), $text);
    }
}
