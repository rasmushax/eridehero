<?php
/**
 * Main plugin class.
 *
 * @package ERH
 */

declare(strict_types=1);

namespace ERH;

use ERH\PostTypes\Product;
use ERH\PostTypes\Tool;
use ERH\PostTypes\Comparison;
use ERH\PostTypes\Newsletter;
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
use ERH\Email\EmailSender;
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
use ERH\Cron\EmailQueueJob;
use ERH\Cron\NewsletterJob;
use ERH\Database\EmailQueue;
use ERH\Database\EmailQueueRepository;
use ERH\Scoring\ProductScorer;
use ERH\Admin\SettingsPage;
use ERH\Admin\LinkPopulator;
use ERH\Admin\ClickStatsPage;
use ERH\Admin\ComparisonDashboardWidget;
use ERH\Admin\PopularComparisonsPage;
use ERH\Admin\ProductPopularityPage;
use ERH\Admin\ProductPopularityWidget;
use ERH\Admin\SpecEditor;
use ERH\Admin\SpecPopulator;
use ERH\Admin\ImagePopulator;
use ERH\Admin\PriceHistoryEditor;
use ERH\Admin\EmailTestPage;
use ERH\Admin\NewsletterAdmin;
use ERH\Migration\MigrationAdmin;
use ERH\Migration\MigrationCli;
use ERH\Api\RestPrices;
use ERH\Api\RestDeals;
use ERH\Api\RestProducts;
use ERH\Api\RestGeo;
use ERH\Api\RestListicle;
use ERH\Api\RestComparisonViews;
use ERH\Api\RestSpecEditor;
use ERH\Api\ContactHandler;
use ERH\Blocks\BlockManager;
use ERH\Tracking\ClickRedirector;
use ERH\CacheKeys;

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
     * Tool post type handler.
     *
     * @var Tool
     */
    private Tool $tool_post_type;

    /**
     * Comparison post type handler.
     *
     * @var Comparison
     */
    private Comparison $comparison_post_type;

    /**
     * Newsletter post type handler.
     *
     * @var Newsletter
     */
    private Newsletter $newsletter_post_type;

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
     * Link populator admin instance.
     *
     * @var LinkPopulator
     */
    private LinkPopulator $link_populator;

    /**
     * Click stats admin page instance.
     *
     * @var ClickStatsPage
     */
    private ClickStatsPage $click_stats_page;

    /**
     * Comparison dashboard widget instance.
     *
     * @var ComparisonDashboardWidget
     */
    private ComparisonDashboardWidget $comparison_dashboard_widget;

    /**
     * Popular comparisons admin page instance.
     *
     * @var PopularComparisonsPage
     */
    private PopularComparisonsPage $popular_comparisons_page;

    /**
     * Product popularity admin page instance.
     *
     * @var ProductPopularityPage
     */
    private ProductPopularityPage $product_popularity_page;

    /**
     * Product popularity dashboard widget instance.
     *
     * @var ProductPopularityWidget
     */
    private ProductPopularityWidget $product_popularity_widget;

    /**
     * Spec editor admin page instance.
     *
     * @var SpecEditor
     */
    private SpecEditor $spec_editor;

    /**
     * Spec populator admin page instance.
     *
     * @var SpecPopulator
     */
    private SpecPopulator $spec_populator;

    /**
     * Image populator admin tool instance.
     *
     * @var ImagePopulator
     */
    private ImagePopulator $image_populator;

    /**
     * Price history editor meta box instance.
     *
     * @var PriceHistoryEditor
     */
    private PriceHistoryEditor $price_history_editor;

    /**
     * Email test page instance.
     *
     * @var EmailTestPage
     */
    private EmailTestPage $email_test_page;

    /**
     * Newsletter admin instance.
     *
     * @var NewsletterAdmin
     */
    private NewsletterAdmin $newsletter_admin;

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
     * Contact form handler.
     *
     * @var ContactHandler
     */
    private ContactHandler $contact_handler;

    /**
     * Block manager instance.
     *
     * @var BlockManager
     */
    private BlockManager $block_manager;

    /**
     * Click redirector instance.
     *
     * @var ClickRedirector
     */
    private ClickRedirector $click_redirector;

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

        // Initialize ACF blocks.
        $this->init_blocks();

        // Initialize post types.
        $this->init_post_types();

        // Initialize database.
        $this->init_database();

        // Initialize user system.
        $this->init_user_system();

        // Initialize contact form handler.
        $this->contact_handler = new ContactHandler();
        $this->contact_handler->register();

        // Initialize email system.
        $this->init_email();

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

        // Register WP-CLI migration commands.
        MigrationCli::register();
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

        // Invalidate ERH price caches when HFT updates prices.
        // This allows us to use longer cache TTLs (6 hours) with on-demand invalidation.
        add_action('hft_price_updated', [$this, 'invalidate_price_caches'], 10, 2);

        // Invalidate specs cache when product is updated in admin.
        add_action('acf/save_post', [$this, 'invalidate_product_caches'], 20);

        // Initialize click tracking redirector (handles /go/ URLs).
        $this->click_redirector = new ClickRedirector();
        $this->click_redirector->register();
    }

    /**
     * Invalidate ERH price caches when HFT updates a product's prices.
     *
     * Called via hft_price_updated action when scraper updates price data.
     *
     * @param int $tracked_link_id The tracked link ID that was updated.
     * @param int $product_id      The product post ID.
     * @return void
     */
    public function invalidate_price_caches(int $tracked_link_id, int $product_id): void {
        CacheKeys::clearPriceCaches($product_id);
    }

    /**
     * Invalidate product caches when ACF fields are saved.
     *
     * Called via acf/save_post action when product is updated.
     *
     * @param int|string $post_id The post ID.
     * @return void
     */
    public function invalidate_product_caches($post_id): void {
        // Skip autosaves, revisions, and non-products.
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (get_post_type($post_id) !== 'products') {
            return;
        }

        // Clear specs caches (specs may have changed).
        CacheKeys::clearListicleSpecs((int) $post_id);
        CacheKeys::clearProductSpecs((int) $post_id);
    }

    /**
     * Initialize ACF blocks.
     *
     * @return void
     */
    private function init_blocks(): void {
        $this->block_manager = new BlockManager();
        $this->block_manager->register();
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

        // Clean up user data when user is deleted from WP admin.
        // Note: User meta is automatically deleted by WordPress.
        add_action('delete_user', [$this, 'cleanup_user_data']);

        // Clean up price trackers when a product is deleted.
        add_action('before_delete_post', [$this, 'cleanup_product_trackers']);

        // Clean up product views when a product is deleted.
        add_action('before_delete_post', [$this, 'cleanup_product_views']);
    }

    /**
     * Clean up custom table data when a user is deleted.
     *
     * WordPress automatically deletes user meta, but custom tables need manual cleanup.
     *
     * @param int $user_id The ID of the user being deleted.
     * @return void
     */
    public function cleanup_user_data(int $user_id): void {
        // Delete all price trackers for this user.
        $tracker_db = new Database\PriceTracker();
        $deleted_count = $tracker_db->delete_for_user($user_id);

        if ($deleted_count > 0) {
            error_log(sprintf(
                '[ERH] Cleaned up %d price tracker(s) for deleted user #%d',
                $deleted_count,
                $user_id
            ));
        }
    }

    /**
     * Clean up price trackers when a product is deleted.
     *
     * Removes all trackers associated with a deleted product to prevent orphaned data.
     *
     * @param int $post_id The ID of the post being deleted.
     * @return void
     */
    public function cleanup_product_trackers(int $post_id): void {
        if (get_post_type($post_id) !== 'products') {
            return;
        }

        $tracker_db = new Database\PriceTracker();
        $deleted_count = $tracker_db->delete_for_product($post_id);

        if ($deleted_count > 0) {
            error_log(sprintf(
                '[ERH] Cleaned up %d price tracker(s) for deleted product #%d',
                $deleted_count,
                $post_id
            ));
        }
    }

    /**
     * Clean up product views when a product is deleted.
     *
     * Removes all view records associated with a deleted product to prevent orphaned data.
     *
     * @param int $post_id The ID of the post being deleted.
     * @return void
     */
    public function cleanup_product_views(int $post_id): void {
        if (get_post_type($post_id) !== 'products') {
            return;
        }

        $view_tracker = new Database\ViewTracker();
        $deleted = $view_tracker->delete_for_product($post_id);

        if ($deleted) {
            error_log(sprintf(
                '[ERH] Cleaned up views for deleted product #%d',
                $post_id
            ));
        }
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

        $this->tool_post_type = new Tool();
        $this->tool_post_type->register();

        $this->comparison_post_type = new Comparison();
        $this->comparison_post_type->register();

        $this->newsletter_post_type = new Newsletter();
        $this->newsletter_post_type->register();

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

        // Initialize link populator (requires HFT plugin).
        $this->link_populator = new LinkPopulator();
        $this->link_populator->register();

        // Initialize click stats page.
        $this->click_stats_page = new ClickStatsPage();
        $this->click_stats_page->register();

        // Initialize comparison dashboard widget.
        $this->comparison_dashboard_widget = new ComparisonDashboardWidget();
        $this->comparison_dashboard_widget->init();

        // Initialize popular comparisons admin page.
        $this->popular_comparisons_page = new PopularComparisonsPage();
        $this->popular_comparisons_page->init();

        // Initialize product popularity admin page.
        $this->product_popularity_page = new ProductPopularityPage();
        $this->product_popularity_page->init();

        // Initialize product popularity dashboard widget.
        $this->product_popularity_widget = new ProductPopularityWidget();
        $this->product_popularity_widget->init();

        // Initialize spec editor admin page.
        $this->spec_editor = new SpecEditor();
        $this->spec_editor->register();

        // Initialize spec populator admin page.
        $this->spec_populator = new SpecPopulator();
        $this->spec_populator->register();

        // Initialize image populator on product edit screens.
        $this->image_populator = new ImagePopulator();
        $this->image_populator->register();

        // Initialize price history editor on product edit screens.
        $this->price_history_editor = new PriceHistoryEditor();
        $this->price_history_editor->register();

        // Initialize email test page.
        $this->email_test_page = new EmailTestPage();
        $this->email_test_page->register();

        // Initialize newsletter admin.
        $this->newsletter_admin = new NewsletterAdmin();
        $this->newsletter_admin->register();
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
        $this->email_sender = new EmailSender();

        // Send welcome email on user registration.
        add_action('erh_user_registered', [$this, 'send_welcome_email'], 10, 2);
    }

    /**
     * Send welcome email to newly registered user.
     *
     * @param int      $user_id The user ID.
     * @param \WP_User $user    The user object.
     * @return void
     */
    public function send_welcome_email(int $user_id, \WP_User $user): void {
        $this->email_sender->send_welcome(
            $user->user_email,
            $user->display_name ?: $user->user_login
        );
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
                $this->cron_manager,
                new ProductScorer()
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
                $price_history,
                $this->user_repo,
                $this->cron_manager
            )
        );

        $this->cron_manager->add_job(
            'youtube-sync',
            new YouTubeSyncJob($this->cron_manager)
        );

        $this->cron_manager->add_job(
            'email-queue',
            new EmailQueueJob(
                new EmailQueueRepository(),
                $this->cron_manager
            )
        );

        $this->cron_manager->add_job(
            'newsletter',
            new NewsletterJob($this->cron_manager)
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

        // Initialize and register REST Products API.
        $rest_products = new RestProducts();
        $rest_products->register_routes();

        // Initialize and register REST Geo API (server-side IPInfo proxy).
        $rest_geo = new RestGeo();
        $rest_geo->register_routes();

        // Initialize and register REST Listicle API.
        $rest_listicle = new RestListicle();
        $rest_listicle->register_routes();

        // Initialize and register REST Comparison Views API.
        $rest_comparison_views = new RestComparisonViews();
        $rest_comparison_views->register_routes();

        // Initialize and register REST Spec Editor API.
        $rest_spec_editor = new RestSpecEditor();
        $rest_spec_editor->register_routes();
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
     * Get the settings page handler.
     *
     * @return SettingsPage
     */
    public function get_settings_page(): SettingsPage {
        return $this->settings_page;
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

    /**
     * Get the block manager instance.
     *
     * @return BlockManager
     */
    public function get_block_manager(): BlockManager {
        return $this->block_manager;
    }

    /**
     * Get the click redirector instance.
     *
     * @return ClickRedirector
     */
    public function get_click_redirector(): ClickRedirector {
        return $this->click_redirector;
    }

    /**
     * Queue an email for sending.
     *
     * Emails are processed in batches by the EmailQueueJob cron.
     *
     * @param string   $to       Recipient email address.
     * @param string   $subject  Email subject.
     * @param string   $body     Email body (HTML).
     * @param string   $type     Email type (see EmailQueue constants).
     * @param int      $priority Priority level (1=critical, 5=normal, 10=low).
     * @param int|null $user_id  Associated user ID (optional).
     * @return int|false The queued email ID or false on failure.
     */
    public function queue_email(
        string $to,
        string $subject,
        string $body,
        string $type = EmailQueue::TYPE_GENERAL,
        int $priority = EmailQueue::PRIORITY_NORMAL,
        ?int $user_id = null
    ): int|false {
        $repo = new EmailQueueRepository();
        return $repo->queue([
            'email_type'        => $type,
            'recipient_email'   => $to,
            'recipient_user_id' => $user_id,
            'subject'           => $subject,
            'body'              => $body,
            'headers'           => ['Content-Type: text/html; charset=UTF-8'],
            'priority'          => $priority,
        ]);
    }
}
