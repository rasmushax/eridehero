<?php
/**
 * Main plugin class.
 *
 * @package ERH
 */

declare(strict_types=1);

namespace ERH;

use ERH\PostTypes\Product;
use ERH\PostTypes\Review;
use ERH\Database\Schema;
use ERH\User\AuthHandler;
use ERH\User\UserPreferences;
use ERH\User\UserTracker;
use ERH\User\RateLimiter;
use ERH\User\UserRepository;
use ERH\User\SocialAuth;
use ERH\Email\MailchimpSync;
use ERH\Admin\SettingsPage;

/**
 * Core plugin class that initializes all components.
 */
class Core {

    /**
     * Product post type handler.
     *
     * @var Product
     */
    private Product $product_post_type;

    /**
     * Review post type handler.
     *
     * @var Review
     */
    private Review $review_post_type;

    /**
     * Database schema handler.
     *
     * @var Schema
     */
    private Schema $schema;

    /**
     * Auth handler.
     *
     * @var AuthHandler
     */
    private AuthHandler $auth_handler;

    /**
     * User preferences handler.
     *
     * @var UserPreferences
     */
    private UserPreferences $user_preferences;

    /**
     * User tracker handler.
     *
     * @var UserTracker
     */
    private UserTracker $user_tracker;

    /**
     * Rate limiter instance.
     *
     * @var RateLimiter
     */
    private RateLimiter $rate_limiter;

    /**
     * User repository instance.
     *
     * @var UserRepository
     */
    private UserRepository $user_repo;

    /**
     * Social auth handler.
     *
     * @var SocialAuth
     */
    private SocialAuth $social_auth;

    /**
     * Mailchimp sync handler.
     *
     * @var MailchimpSync
     */
    private MailchimpSync $mailchimp_sync;

    /**
     * Settings page handler.
     *
     * @var SettingsPage
     */
    private SettingsPage $settings_page;

    /**
     * Initialize all plugin components.
     *
     * @return void
     */
    public function init(): void {
        // Load text domain for translations.
        add_action('init', [$this, 'load_textdomain']);

        // Initialize shared services.
        $this->init_services();

        // Initialize post types.
        $this->init_post_types();

        // Initialize database.
        $this->init_database();

        // Initialize user system.
        $this->init_user_system();

        // Initialize admin.
        if (is_admin()) {
            $this->init_admin();
        }

        // Initialize frontend.
        if (!is_admin()) {
            $this->init_frontend();
        }

        // Initialize AJAX handlers.
        $this->init_ajax();

        // Initialize cron jobs.
        $this->init_cron();

        // Initialize REST API.
        add_action('rest_api_init', [$this, 'init_rest_api']);
    }

    /**
     * Initialize shared services.
     *
     * @return void
     */
    private function init_services(): void {
        $this->rate_limiter = new RateLimiter();
        $this->user_repo = new UserRepository();

        // Register HFT product post types filter.
        add_filter('hft_product_post_types', function () {
            return ['products'];
        });
    }

    /**
     * Initialize user system components.
     *
     * @return void
     */
    private function init_user_system(): void {
        // Initialize auth handler.
        $this->auth_handler = new AuthHandler($this->rate_limiter, $this->user_repo);
        $this->auth_handler->register();

        // Initialize user preferences.
        $this->user_preferences = new UserPreferences($this->user_repo);
        $this->user_preferences->register();

        // Initialize user tracker.
        $this->user_tracker = new UserTracker($this->rate_limiter, $this->user_repo);
        $this->user_tracker->register();

        // Initialize social auth (OAuth for Google, Facebook, Reddit).
        $this->social_auth = new SocialAuth($this->user_repo);
        $this->social_auth->register();

        // Initialize Mailchimp sync.
        $this->mailchimp_sync = new MailchimpSync($this->user_repo);
        $this->mailchimp_sync->register();
    }

    /**
     * Load plugin text domain.
     *
     * @return void
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'erh-core',
            false,
            dirname(ERH_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Initialize custom post types.
     *
     * @return void
     */
    private function init_post_types(): void {
        $this->product_post_type = new Product();
        $this->product_post_type->register();

        $this->review_post_type = new Review();
        $this->review_post_type->register();
    }

    /**
     * Initialize database components.
     *
     * @return void
     */
    private function init_database(): void {
        $this->schema = new Schema();

        // Check if database needs updating.
        $installed_version = get_option('erh_db_version', '0');
        if (version_compare($installed_version, ERH_VERSION, '<')) {
            $this->schema->maybe_upgrade();
            update_option('erh_db_version', ERH_VERSION);
        }
    }

    /**
     * Initialize admin-specific components.
     *
     * @return void
     */
    private function init_admin(): void {
        // Enqueue admin styles.
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Initialize settings page.
        $this->settings_page = new SettingsPage();
        $this->settings_page->register();
    }

    /**
     * Enqueue admin assets.
     *
     * @return void
     */
    public function enqueue_admin_assets(): void {
        $screen = get_current_screen();

        // Only load on relevant admin pages.
        if (!$screen) {
            return;
        }

        wp_enqueue_style(
            'erh-admin',
            ERH_PLUGIN_URL . 'assets/css/admin-style.css',
            [],
            ERH_VERSION
        );
    }

    /**
     * Initialize frontend-specific components.
     *
     * @return void
     */
    private function init_frontend(): void {
        // Enqueue frontend assets.
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }

    /**
     * Enqueue frontend assets.
     *
     * @return void
     */
    public function enqueue_frontend_assets(): void {
        // Core plugin styles (minimal).
        wp_enqueue_style(
            'erh-core',
            ERH_PLUGIN_URL . 'assets/css/erh-core.css',
            [],
            ERH_VERSION
        );

        // Localize script with AJAX data.
        wp_localize_script('erh-core', 'erhAjax', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('erh_nonce'),
            'restUrl' => rest_url('erh/v1/'),
        ]);
    }

    /**
     * Initialize AJAX handlers.
     *
     * @return void
     */
    private function init_ajax(): void {
        // AJAX handlers will be registered here as components are added.
        // Example:
        // add_action('wp_ajax_erh_action', [$handler, 'handle']);
        // add_action('wp_ajax_nopriv_erh_action', [$handler, 'handle']);
    }

    /**
     * Initialize cron jobs.
     *
     * @return void
     */
    private function init_cron(): void {
        // Cron jobs will be registered here.
        // Example:
        // $cron_manager = new Cron\CronManager();
        // $cron_manager->register();
    }

    /**
     * Initialize REST API endpoints.
     *
     * @return void
     */
    public function init_rest_api(): void {
        // REST API endpoints will be registered here.
        // Example:
        // $products_api = new Api\RestProducts();
        // $products_api->register_routes();
    }

    /**
     * Get the Product post type handler.
     *
     * @return Product
     */
    public function get_product_post_type(): Product {
        return $this->product_post_type;
    }

    /**
     * Get the Review post type handler.
     *
     * @return Review
     */
    public function get_review_post_type(): Review {
        return $this->review_post_type;
    }

    /**
     * Get the database schema handler.
     *
     * @return Schema
     */
    public function get_schema(): Schema {
        return $this->schema;
    }

    /**
     * Get the auth handler.
     *
     * @return AuthHandler
     */
    public function get_auth_handler(): AuthHandler {
        return $this->auth_handler;
    }

    /**
     * Get the user preferences handler.
     *
     * @return UserPreferences
     */
    public function get_user_preferences(): UserPreferences {
        return $this->user_preferences;
    }

    /**
     * Get the user tracker handler.
     *
     * @return UserTracker
     */
    public function get_user_tracker(): UserTracker {
        return $this->user_tracker;
    }

    /**
     * Get the rate limiter.
     *
     * @return RateLimiter
     */
    public function get_rate_limiter(): RateLimiter {
        return $this->rate_limiter;
    }

    /**
     * Get the user repository.
     *
     * @return UserRepository
     */
    public function get_user_repo(): UserRepository {
        return $this->user_repo;
    }

    /**
     * Get the social auth handler.
     *
     * @return SocialAuth
     */
    public function get_social_auth(): SocialAuth {
        return $this->social_auth;
    }

    /**
     * Get the Mailchimp sync handler.
     *
     * @return MailchimpSync
     */
    public function get_mailchimp_sync(): MailchimpSync {
        return $this->mailchimp_sync;
    }

    /**
     * Get the settings page handler.
     *
     * @return SettingsPage
     */
    public function get_settings_page(): SettingsPage {
        return $this->settings_page;
    }
}
