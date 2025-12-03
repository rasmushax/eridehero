<?php
/**
 * Search JSON generation cron job.
 *
 * @package ERH\Cron
 */

declare(strict_types=1);

namespace ERH\Cron;

/**
 * Generates the search_items.json file for client-side search.
 */
class SearchJsonJob implements CronJobInterface {

    /**
     * Post types to include in search.
     */
    private const SEARCH_POST_TYPES = ['post', 'products', 'tool'];

    /**
     * Default thumbnail URL.
     */
    private const DEFAULT_THUMBNAIL = 'https://eridehero.com/wp-content/uploads/2024/07/kick-scooter-1.svg';

    /**
     * Cron manager reference for locking.
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
        return __('Search JSON Generator', 'erh-core');
    }

    /**
     * Get the job's description.
     *
     * @return string
     */
    public function get_description(): string {
        return __('Generates the search index JSON file for instant search.', 'erh-core');
    }

    /**
     * Get the WordPress hook name.
     *
     * @return string
     */
    public function get_hook_name(): string {
        return 'erh_cron_search_json';
    }

    /**
     * Get the cron schedule.
     *
     * @return string
     */
    public function get_schedule(): string {
        return 'twicedaily';
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function execute(): void {
        // Acquire lock to prevent concurrent execution.
        if (!$this->cron_manager->lock_job('search-json', 300)) {
            error_log('[ERH Cron] Search JSON job already running, skipping.');
            return;
        }

        try {
            $this->run();
        } finally {
            $this->cron_manager->unlock_job('search-json');
            $this->cron_manager->record_run_time('search-json');
        }
    }

    /**
     * Run the search JSON generation logic.
     *
     * @return void
     */
    private function run(): void {
        global $wpdb;

        // Get all published posts of the specified types.
        $post_types_placeholder = implode("', '", array_map('esc_sql', self::SEARCH_POST_TYPES));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results(
            "SELECT ID, post_title, post_type
            FROM {$wpdb->posts}
            WHERE post_type IN ('{$post_types_placeholder}')
            AND post_status = 'publish'"
        );

        if (empty($results)) {
            error_log('[ERH Cron] Search JSON: No posts found.');
            return;
        }

        $search_items = [];

        foreach ($results as $post) {
            $item = $this->build_search_item($post);
            if ($item) {
                $search_items[] = $item;
            }
        }

        // Generate JSON.
        $json_data = wp_json_encode($search_items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json_data === false) {
            error_log('[ERH Cron] Search JSON: Failed to encode JSON.');
            return;
        }

        // Get upload directory.
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/search_items.json';

        // Write the file.
        $result = file_put_contents($file_path, $json_data);

        if ($result === false) {
            error_log('[ERH Cron] Search JSON: Failed to write file.');
            return;
        }

        error_log(sprintf(
            '[ERH Cron] Search JSON updated. Items: %d, Size: %s',
            count($search_items),
            size_format($result)
        ));
    }

    /**
     * Build a search item from a post object.
     *
     * @param object $post The post object.
     * @return array|null The search item or null on failure.
     */
    private function build_search_item(object $post): ?array {
        $thumbnail_url = self::DEFAULT_THUMBNAIL;
        $type = '';
        $product_type = '';

        switch ($post->post_type) {
            case 'products':
                $type = 'Product';
                $thumbnail_url = $this->get_product_thumbnail($post->ID);
                $product_type = get_field('product_type', $post->ID) ?: '';
                break;

            case 'tool':
                $type = 'Tool';
                $thumbnail_url = $this->get_post_thumbnail($post->ID);
                break;

            case 'post':
                $type = 'Article';
                $thumbnail_url = $this->get_post_thumbnail($post->ID);
                break;

            default:
                return null;
        }

        $item = [
            'id'        => (int) $post->ID,
            'title'     => $post->post_title,
            'url'       => get_permalink($post->ID),
            'type'      => $type,
            'thumbnail' => $thumbnail_url,
        ];

        // Add product type for products.
        if (!empty($product_type)) {
            $item['product_type'] = $product_type;
        }

        return $item;
    }

    /**
     * Get the thumbnail URL for a product.
     *
     * @param int $post_id The post ID.
     * @return string The thumbnail URL.
     */
    private function get_product_thumbnail(int $post_id): string {
        $big_thumbnail = get_field('big_thumbnail', $post_id);

        if ($big_thumbnail) {
            $url = wp_get_attachment_image_url($big_thumbnail, [50, 50]);
            if ($url) {
                return $url;
            }
        }

        return self::DEFAULT_THUMBNAIL;
    }

    /**
     * Get the thumbnail URL for a regular post.
     *
     * @param int $post_id The post ID.
     * @return string The thumbnail URL.
     */
    private function get_post_thumbnail(int $post_id): string {
        if (has_post_thumbnail($post_id)) {
            $url = get_the_post_thumbnail_url($post_id, [50, 50]);
            if ($url) {
                return $url;
            }
        }

        return self::DEFAULT_THUMBNAIL;
    }
}
