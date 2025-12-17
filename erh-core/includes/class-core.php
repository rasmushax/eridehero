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
use ERH\PostTypes\Taxonomies;
use ERH\Database\Schema;
use ERH\Database\ProductCache;
use ERH\Database\PriceHistory;
use ERH\Database\PriceTracker;
use ERH\Database\ViewTracker;
use ERH\User\AuthHandler;
use ERH\User\UserPreferences;
use ERH\User\UserTracker;
use ERH\User\RateLimiter;
use ERH\User\UserRepository;
use ERH\User\SocialAuth;
use ERH\Email\MailchimpSync;
use ERH\Email\EmailTemplate;
use ERH\Email\EmailSender;
use ERH\Reviews\ReviewQuery;
use ERH\Reviews\ReviewHandler;
use ERH\Pricing\PriceFetcher;
use ERH\Pricing\ExchangeRateService;
use ERH\Cron\CronManager;
use ERH\Cron\PriceUpdateJob;
use ERH\Cron\CacheRebuildJob;
use ERH\Cron\SearchJsonJob;
use ERH\Cron\ComparisonJsonJob;
use ERH\Cron\FinderJsonJob;
use ERH\Cron\NotificationJob;
use ERH\Cron\YouTubeSyncJob;
use ERH\Admin\SettingsPage;
use ERH\Migration\MigrationAdmin;
use ERH\Api\RestPrices;
use ERH\Api\RestDeals;

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
     * Taxonomies handler.
     *
     * @var Taxonomies
     */
    private Taxonomies $taxonomies;

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
     * Migration admin instance.
     *
     * @var MigrationAdmin
     */
    private MigrationAdmin $migration_admin;

    /**
     * Review query instance.
     *
     * @var ReviewQuery
     */
    private ReviewQuery $review_query;

    /**
     * Review handler instance.
     *
     * @var ReviewHandler
     */
    private ReviewHandler $review_handler;

    /**
     * Email template instance.
     *
     * @var EmailTemplate
     */
    private EmailTemplate $email_template;

    /**
     * Email sender instance.
     *
     * @var EmailSender
     */
    private EmailSender $email_sender;

    /**
     * Cron manager instance.
     *
     * @var CronManager
     */
    private CronManager $cron_manager;

    /**
     * Exchange rate service instance.
     *
     * @var ExchangeRateService
     */
    private ExchangeRateService $exchange_rate_service;

    /**
     * REST Prices API instance.
     *
     * @var RestPrices
     */
    private RestPrices $rest_prices;

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

        // Initialize email system.
        $this->init_email();

        // Initialize review system.
        $this->init_reviews();

        // Initialize cron jobs (before admin, since settings page needs cron_manager).
        $this->init_cron();

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
        $this->exchange_rate_service = new ExchangeRateService();

        // Register HFT product post types filter.
        add_filter('hft_product_post_types', function () {
            return ['products'];
        });

        // Disable HFT's built-in hf_product CPT (we use our own 'products' CPT).
        add_filter('hft_register_product_cpt', '__return_false');
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

        $this->taxonomies = new Taxonomies();
        $this->taxonomies->register();
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
        $this->settings_page->set_cron_manager($this->cron_manager);
        $this->settings_page->register();

        // Initialize migration admin.
        $this->migration_admin = new MigrationAdmin();
        $this->migration_admin->register();
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
     * Initialize email system.
     *
     * @return void
     */
    private function init_email(): void {
        $this->email_template = new EmailTemplate();
        $this->email_sender = new EmailSender($this->email_template);
    }

    /**
     * Initialize review system.
     *
     * @return void
     */
    private function init_reviews(): void {
        $this->review_query = new ReviewQuery();
        $this->review_handler = new ReviewHandler($this->rate_limiter, $this->review_query);
        $this->review_handler->register();
    }

    /**
     * Initialize cron jobs.
     *
     * @return void
     */
    private function init_cron(): void {
        // Initialize cron manager.
        $this->cron_manager = new CronManager();

        // Create shared dependencies.
        $price_fetcher = new PriceFetcher();
        $product_cache = new ProductCache();
        $price_history = new PriceHistory();
        $price_tracker_db = new PriceTracker();
        $view_tracker = new ViewTracker();

        // Add cron jobs.
        $this->cron_manager->add_job(
            'price-update',
            new PriceUpdateJob($price_fetcher, $price_history, $this->cron_manager)
        );

        $this->cron_manager->add_job(
            'cache-rebuild',
            new CacheRebuildJob(
                $price_fetcher,
                $product_cache,
                $price_history,
                $price_tracker_db,
                $view_tracker,
                $this->review_query,
                $this->cron_manager
            )
        );

        $this->cron_manager->add_job(
            'search-json',
            new SearchJsonJob($this->cron_manager)
        );

        $this->cron_manager->add_job(
            'comparison-json',
            new ComparisonJsonJob($this->cron_manager)
        );

        $this->cron_manager->add_job(
            'finder-json',
            new FinderJsonJob($this->cron_manager)
        );

        $this->cron_manager->add_job(
            'notifications',
            new NotificationJob(
                $price_fetcher,
                $price_tracker_db,
                $this->email_sender,
                $this->user_repo,
                $this->cron_manager
            )
        );

        $this->cron_manager->add_job(
            'youtube-sync',
            new YouTubeSyncJob($this->cron_manager)
        );

        // Register the cron manager.
        $this->cron_manager->register();
    }

    /**
     * Initialize REST API endpoints.
     *
     * @return void
     */
    public function init_rest_api(): void {
        // Initialize and register REST Prices API.
        $price_fetcher = new \ERH\Pricing\PriceFetcher();
        $this->rest_prices = new RestPrices($price_fetcher, $this->exchange_rate_service);
        $this->rest_prices->register_routes();

        // Initialize and register REST Deals API.
        $rest_deals = new RestDeals();
        $rest_deals->register_routes();
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

    /**
     * Get the review query instance.
     *
     * @return ReviewQuery
     */
    public function get_review_query(): ReviewQuery {
        return $this->review_query;
    }

    /**
     * Get the review handler instance.
     *
     * @return ReviewHandler
     */
    public function get_review_handler(): ReviewHandler {
        return $this->review_handler;
    }

    /**
     * Get the email template instance.
     *
     * @return EmailTemplate
     */
    public function get_email_template(): EmailTemplate {
        return $this->email_template;
    }

    /**
     * Get the email sender instance.
     *
     * @return EmailSender
     */
    public function get_email_sender(): EmailSender {
        return $this->email_sender;
    }

    /**
     * Get the cron manager instance.
     *
     * @return CronManager
     */
    public function get_cron_manager(): CronManager {
        return $this->cron_manager;
    }

    /**
     * Get the exchange rate service instance.
     *
     * @return ExchangeRateService
     */
    public function get_exchange_rate_service(): ExchangeRateService {
        return $this->exchange_rate_service;
    }
}
