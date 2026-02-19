<?php
/**
 * Coupon Custom Post Type.
 *
 * @package ERH\PostTypes
 */

declare(strict_types=1);

namespace ERH\PostTypes;

use ERH\CategoryConfig;

/**
 * Handles the Coupon custom post type registration.
 * Used for managing retailer coupon/discount codes.
 */
class Coupon {

    /**
     * Post type slug.
     *
     * @var string
     */
    public const POST_TYPE = 'coupon';

    /**
     * Product post type for relationships.
     *
     * @var string
     */
    private const PRODUCT_POST_TYPE = 'products';

    /**
     * Register the post type and hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action('init', [$this, 'register_post_type']);
        add_action('acf/init', [$this, 'register_acf_fields']);
        add_action('acf/save_post', [$this, 'on_save_coupon'], 20);
        add_filter('acf/load_field/name=coupon_scraper_id', [$this, 'load_scraper_choices']);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);
    }

    /**
     * Register the Coupon custom post type.
     *
     * @return void
     */
    public function register_post_type(): void {
        $labels = [
            'name'               => _x('Coupons', 'Post type general name', 'erh-core'),
            'singular_name'      => _x('Coupon', 'Post type singular name', 'erh-core'),
            'menu_name'          => _x('Coupons', 'Admin Menu text', 'erh-core'),
            'name_admin_bar'     => _x('Coupon', 'Add New on Toolbar', 'erh-core'),
            'add_new'            => __('Add New', 'erh-core'),
            'add_new_item'       => __('Add New Coupon', 'erh-core'),
            'new_item'           => __('New Coupon', 'erh-core'),
            'edit_item'          => __('Edit Coupon', 'erh-core'),
            'view_item'          => __('View Coupon', 'erh-core'),
            'all_items'          => __('All Coupons', 'erh-core'),
            'search_items'       => __('Search Coupons', 'erh-core'),
            'not_found'          => __('No coupons found.', 'erh-core'),
            'not_found_in_trash' => __('No coupons found in Trash.', 'erh-core'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 22,
            'menu_icon'          => 'dashicons-tag',
            'show_in_rest'       => true,
            'rest_base'          => 'coupons',
            'supports'           => ['author'],
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register ACF fields for Coupons.
     *
     * @return void
     */
    public function register_acf_fields(): void {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        // Build category choices from CategoryConfig.
        $category_choices = [];
        foreach (CategoryConfig::get_all() as $key => $cat) {
            $category_choices[$key] = $cat['name'];
        }

        acf_add_local_field_group([
            'key'      => 'group_coupon_details',
            'title'    => 'Coupon Details',
            'fields'   => [
                [
                    'key'          => 'field_coupon_scraper_id',
                    'label'        => 'Retailer',
                    'name'         => 'coupon_scraper_id',
                    'type'         => 'select',
                    'instructions' => 'Select the retailer (from HFT scrapers).',
                    'required'     => 1,
                    'choices'      => [], // Populated dynamically via acf/load_field.
                    'allow_null'   => 0,
                    'ui'           => 1,
                ],
                [
                    'key'          => 'field_coupon_code',
                    'label'        => 'Coupon Code',
                    'name'         => 'coupon_code',
                    'type'         => 'text',
                    'instructions' => 'The coupon/discount code.',
                    'required'     => 1,
                ],
                [
                    'key'           => 'field_coupon_type',
                    'label'         => 'Discount Type',
                    'name'          => 'coupon_type',
                    'type'          => 'select',
                    'instructions'  => 'Type of discount.',
                    'required'      => 1,
                    'choices'       => [
                        'percent' => 'Percentage Off',
                        'fixed'   => 'Fixed Amount Off',
                        'extras'  => 'Extras (free accessory, etc.)',
                        'freebie' => 'Freebie (free item)',
                    ],
                    'default_value' => 'percent',
                ],
                [
                    'key'          => 'field_coupon_value',
                    'label'        => 'Discount Value',
                    'name'         => 'coupon_value',
                    'type'         => 'number',
                    'instructions' => 'Numeric value (20 for 20%, 75 for $75 off). Leave empty for extras/freebie.',
                    'required'     => 0,
                ],
                [
                    'key'          => 'field_coupon_description',
                    'label'        => 'Description',
                    'name'         => 'coupon_description',
                    'type'         => 'text',
                    'instructions' => 'Human-readable description, e.g. "20% off sitewide".',
                    'required'     => 0,
                ],
                [
                    'key'          => 'field_coupon_min_order',
                    'label'        => 'Minimum Order',
                    'name'         => 'coupon_min_order',
                    'type'         => 'number',
                    'instructions' => 'Minimum order amount (optional).',
                    'required'     => 0,
                ],
                [
                    'key'          => 'field_coupon_url',
                    'label'        => 'Coupon URL',
                    'name'         => 'coupon_url',
                    'type'         => 'url',
                    'instructions' => 'Direct link where the coupon auto-applies (optional).',
                    'required'     => 0,
                ],
                [
                    'key'           => 'field_coupon_category',
                    'label'         => 'Product Category',
                    'name'          => 'coupon_category',
                    'type'          => 'select',
                    'instructions'  => 'Which product category does this coupon apply to?',
                    'required'      => 0,
                    'choices'       => $category_choices,
                    'allow_null'    => 1,
                    'ui'            => 1,
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => self::POST_TYPE,
                    ],
                ],
            ],
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
        ]);

        acf_add_local_field_group([
            'key'      => 'group_coupon_scope',
            'title'    => 'Product Scope',
            'fields'   => [
                [
                    'key'           => 'field_coupon_scope',
                    'label'         => 'Scope',
                    'name'          => 'coupon_scope',
                    'type'          => 'select',
                    'instructions'  => 'Does this coupon apply to all products or only specific ones?',
                    'required'      => 1,
                    'choices'       => [
                        'all'      => 'All Products (with exceptions)',
                        'specific' => 'Specific Products Only',
                    ],
                    'default_value' => 'all',
                ],
                [
                    'key'               => 'field_coupon_scope_all_msg',
                    'label'             => '',
                    'name'              => '',
                    'type'              => 'message',
                    'message'           => 'Products selected below are <strong>EXCEPTIONS</strong> &mdash; the coupon applies to everything <em>except</em> these.',
                    'conditional_logic' => [
                        [
                            [
                                'field'    => 'field_coupon_scope',
                                'operator' => '==',
                                'value'    => 'all',
                            ],
                        ],
                    ],
                ],
                [
                    'key'               => 'field_coupon_scope_specific_msg',
                    'label'             => '',
                    'name'              => '',
                    'type'              => 'message',
                    'message'           => 'Coupon applies <strong>ONLY</strong> to the products selected below.',
                    'conditional_logic' => [
                        [
                            [
                                'field'    => 'field_coupon_scope',
                                'operator' => '==',
                                'value'    => 'specific',
                            ],
                        ],
                    ],
                ],
                [
                    'key'           => 'field_coupon_products',
                    'label'         => 'Products',
                    'name'          => 'coupon_products',
                    'type'          => 'relationship',
                    'instructions'  => 'Select products (exceptions if scope=all, inclusions if scope=specific).',
                    'required'      => 0,
                    'post_type'     => [self::PRODUCT_POST_TYPE],
                    'filters'       => ['search'],
                    'min'           => 0,
                    'max'           => 0, // Unlimited.
                    'return_format' => 'id',
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => self::POST_TYPE,
                    ],
                ],
            ],
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
        ]);
    }

    /**
     * Auto-generate title on save from coupon fields.
     *
     * Format: "CODE — Retailer (description)" e.g. "SAVE20 — VoroMotors (20% off sitewide)"
     *
     * @param int|string $post_id The post ID.
     * @return void
     */
    public function on_save_coupon($post_id): void {
        if (!is_numeric($post_id)) {
            return;
        }

        $post_id = (int) $post_id;

        if (get_post_type($post_id) !== self::POST_TYPE) {
            return;
        }

        $code = get_field('coupon_code', $post_id);
        if (!$code) {
            return;
        }

        // Build title parts.
        $parts = [strtoupper($code)];

        $retailer = self::get_retailer_info($post_id);
        if ($retailer) {
            $parts[] = $retailer['name'];
        }

        $title = implode(' — ', $parts);

        // Append description in parentheses if available.
        $description = get_field('coupon_description', $post_id);
        if ($description) {
            $title .= ' (' . $description . ')';
        }

        // Only update if different (prevent infinite loop).
        $current_title = get_the_title($post_id);
        if ($current_title !== $title) {
            remove_action('acf/save_post', [$this, 'on_save_coupon'], 20);

            wp_update_post([
                'ID'         => $post_id,
                'post_title' => $title,
                'post_name'  => sanitize_title($code),
            ]);

            add_action('acf/save_post', [$this, 'on_save_coupon'], 20);
        }
    }

    /**
     * Dynamically load scraper choices from HFT plugin.
     *
     * @param array $field The ACF field array.
     * @return array Modified field with choices populated.
     */
    public function load_scraper_choices(array $field): array {
        if (!class_exists('HFT_Scraper_Repository')) {
            $field['choices'] = ['' => '-- HFT plugin not active --'];
            return $field;
        }

        $repo = new \HFT_Scraper_Repository();
        $scrapers = $repo->find_all_active();

        $choices = [];
        foreach ($scrapers as $scraper) {
            $choices[$scraper->id] = $scraper->name ?: $scraper->domain;
        }

        $field['choices'] = $choices;

        return $field;
    }

    /**
     * Add custom admin columns.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function add_admin_columns(array $columns): array {
        $new_columns = [];

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            if ($key === 'title') {
                $new_columns['coupon_code'] = __('Code', 'erh-core');
                $new_columns['retailer']    = __('Retailer', 'erh-core');
                $new_columns['type']        = __('Type', 'erh-core');
                $new_columns['scope']       = __('Scope', 'erh-core');
            }
        }

        return $new_columns;
    }

    /**
     * Render custom admin columns.
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     * @return void
     */
    public function render_admin_columns(string $column, int $post_id): void {
        switch ($column) {
            case 'coupon_code':
                $code = get_field('coupon_code', $post_id);
                if ($code) {
                    printf('<code>%s</code>', esc_html($code));
                }
                break;

            case 'retailer':
                $info = self::get_retailer_info($post_id);
                if ($info) {
                    echo esc_html($info['name']);
                } else {
                    echo '<em>' . esc_html__('Unknown', 'erh-core') . '</em>';
                }
                break;

            case 'type':
                $type = get_field('coupon_type', $post_id);
                $value = get_field('coupon_value', $post_id);
                $labels = [
                    'percent' => '%s%% off',
                    'fixed'   => '$%s off',
                    'extras'  => 'Extras',
                    'freebie' => 'Freebie',
                ];
                if (isset($labels[$type]) && $value) {
                    printf(esc_html($labels[$type]), esc_html((string) $value));
                } elseif (isset($labels[$type])) {
                    echo esc_html($labels[$type]);
                }
                break;

            case 'scope':
                $scope = get_field('coupon_scope', $post_id);
                $products = get_field('coupon_products', $post_id);
                $count = is_array($products) ? count($products) : 0;

                if ($scope === 'all' && $count > 0) {
                    printf(
                        esc_html__('All (-%d exceptions)', 'erh-core'),
                        $count
                    );
                } elseif ($scope === 'all') {
                    echo esc_html__('All products', 'erh-core');
                } elseif ($scope === 'specific' && $count > 0) {
                    printf(
                        esc_html(_n('%d product', '%d products', $count, 'erh-core')),
                        $count
                    );
                } else {
                    echo esc_html__('Specific (none set)', 'erh-core');
                }
                break;
        }
    }

    /**
     * Get all published coupons for a given product category.
     *
     * @param string $category Category key (e.g. 'escooter').
     * @return array Array of coupon post objects with ACF fields.
     */
    public static function get_by_category(string $category): array {
        $posts = get_posts([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'   => 'coupon_category',
                    'value' => $category,
                ],
            ],
        ]);

        return self::enrich_coupons($posts);
    }

    /**
     * Get all published coupons that apply to a specific product.
     *
     * Checks both scope types:
     * - scope=all: coupon applies unless product is in the exceptions list
     * - scope=specific: coupon applies only if product is in the inclusions list
     *
     * @param int $product_id The product post ID.
     * @return array Array of coupon data arrays.
     */
    public static function get_for_product(int $product_id): array {
        // Get all published coupons.
        $all_coupons = get_posts([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ]);

        $matching = [];

        foreach ($all_coupons as $coupon) {
            $scope = get_field('coupon_scope', $coupon->ID);
            $products = get_field('coupon_products', $coupon->ID);
            $product_ids = is_array($products) ? array_map('intval', $products) : [];

            if ($scope === 'all') {
                // Applies to all unless product is in exceptions list.
                if (!in_array($product_id, $product_ids, true)) {
                    $matching[] = $coupon;
                }
            } elseif ($scope === 'specific') {
                // Applies only if product is in inclusions list.
                if (in_array($product_id, $product_ids, true)) {
                    $matching[] = $coupon;
                }
            }
        }

        return self::enrich_coupons($matching);
    }

    /**
     * Get retailer info for a coupon (name, domain, logo URL).
     *
     * @param int $coupon_id The coupon post ID.
     * @return array{name: string, domain: string, logo_url: string|null}|null
     */
    public static function get_retailer_info(int $coupon_id): ?array {
        $scraper_id = get_field('coupon_scraper_id', $coupon_id);

        if (!$scraper_id || !class_exists('HFT_Scraper_Repository')) {
            return null;
        }

        $repo = new \HFT_Scraper_Repository();
        $scraper = $repo->find_by_id((int) $scraper_id);

        if (!$scraper) {
            return null;
        }

        $logo_url = null;
        if (!empty($scraper->logo_attachment_id)) {
            $logo_url = wp_get_attachment_image_url($scraper->logo_attachment_id, 'thumbnail') ?: null;
        }

        return [
            'name'     => $scraper->name ?: $scraper->domain,
            'domain'   => $scraper->domain,
            'logo_url' => $logo_url,
        ];
    }

    /**
     * Enrich coupon posts with ACF field data.
     *
     * @param array $posts Array of WP_Post objects.
     * @return array Array of enriched coupon data.
     */
    private static function enrich_coupons(array $posts): array {
        $coupons = [];

        foreach ($posts as $post) {
            $retailer = self::get_retailer_info($post->ID);

            $coupons[] = [
                'id'          => $post->ID,
                'title'       => get_the_title($post->ID),
                'code'        => get_field('coupon_code', $post->ID) ?: '',
                'type'        => get_field('coupon_type', $post->ID) ?: 'percent',
                'value'       => get_field('coupon_value', $post->ID),
                'description' => get_field('coupon_description', $post->ID) ?: '',
                'min_order'   => get_field('coupon_min_order', $post->ID),
                'url'         => get_field('coupon_url', $post->ID) ?: '',
                'category'    => get_field('coupon_category', $post->ID) ?: '',
                'scope'       => get_field('coupon_scope', $post->ID) ?: 'all',
                'retailer'    => $retailer,
            ];
        }

        return $coupons;
    }
}
