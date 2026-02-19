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

        acf_add_local_field_group([
            'key'      => 'group_coupon_details',
            'title'    => 'Coupon Details',
            'fields'   => [
                [
                    'key'          => 'field_coupon_scraper_id',
                    'label'        => 'Retailer',
                    'name'         => 'coupon_scraper_id',
                    'type'         => 'select',
                    'instructions' => 'Select the retailer (from HFT scrapers). Category detection is automatic based on which products this retailer carries.',
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
                    'key'           => 'field_coupon_expires',
                    'label'         => 'Expiry Date',
                    'name'          => 'coupon_expires',
                    'type'          => 'date_picker',
                    'instructions'  => 'Leave empty for ongoing/no expiry.',
                    'required'      => 0,
                    'display_format' => 'F j, Y',
                    'return_format' => 'Y-m-d',
                ],
                [
                    'key'          => 'field_coupon_terms',
                    'label'        => 'Terms & Conditions',
                    'name'         => 'coupon_terms',
                    'type'         => 'textarea',
                    'instructions' => 'Fine print, e.g. "New customers only. Max 1 per order. Cannot combine with other offers."',
                    'required'     => 0,
                    'rows'         => 3,
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
                $new_columns['expires']     = __('Expires', 'erh-core');
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

            case 'expires':
                $expires = get_field('coupon_expires', $post_id);
                if ($expires) {
                    $is_expired = strtotime($expires) < time();
                    $formatted = date_i18n('M j, Y', strtotime($expires));
                    if ($is_expired) {
                        printf(
                            '<span style="color: #d63638;">%s</span>',
                            esc_html($formatted)
                        );
                    } else {
                        echo esc_html($formatted);
                    }
                } else {
                    echo '<em>' . esc_html__('Ongoing', 'erh-core') . '</em>';
                }
                break;
        }
    }

    /**
     * Check if a coupon has expired.
     *
     * @param int $coupon_id The coupon post ID.
     * @return bool True if expired.
     */
    public static function is_expired(int $coupon_id): bool {
        $expires = get_field('coupon_expires', $coupon_id);
        if (!$expires) {
            return false; // No expiry = ongoing.
        }
        // Compare as date only (expire at end of the day).
        return strtotime($expires . ' 23:59:59') < time();
    }

    /**
     * Get active (non-expired, published) coupons relevant to a product category.
     *
     * Uses smart detection: checks if the coupon's retailer (scraper) has tracked links
     * for any published products in the given category via the HFT tracked_links table.
     * Also respects scope (all/specific) and exclusion lists.
     *
     * @param string $category_key Category key (e.g. 'escooter').
     * @return array Array of enriched coupon data, grouped-ready (sorted by retailer).
     */
    public static function get_by_category(string $category_key): array {
        global $wpdb;

        // Resolve category key to product_type taxonomy slug.
        $category = CategoryConfig::get_by_key($category_key);
        if (!$category) {
            return [];
        }

        // Map canonical key to taxonomy slug (e.g. 'escooter' → 'electric-scooter').
        $taxonomy_slug_map = [
            'escooter'    => 'electric-scooter',
            'ebike'       => 'electric-bike',
            'eskateboard' => 'electric-skateboard',
            'euc'         => 'electric-unicycle',
            'hoverboard'  => 'hoverboard',
        ];
        $taxonomy_slug = $taxonomy_slug_map[$category_key] ?? '';
        if (!$taxonomy_slug) {
            return [];
        }

        // Get all published, non-expired coupons.
        $all_coupons = get_posts([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ]);

        if (empty($all_coupons)) {
            return [];
        }

        // For each coupon, check if its scraper has products in this category.
        $matching = [];
        $hft_table = $wpdb->prefix . 'hft_tracked_links';

        foreach ($all_coupons as $coupon) {
            // Skip expired coupons.
            if (self::is_expired($coupon->ID)) {
                continue;
            }

            $scraper_id = get_field('coupon_scraper_id', $coupon->ID);
            if (!$scraper_id) {
                continue;
            }

            // Check if this scraper has tracked links for products in this category.
            // Query: find product IDs from tracked_links for this scraper,
            // then check if any of those products have the target product_type taxonomy.
            $product_ids_in_category = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT tl.product_post_id
                 FROM {$hft_table} tl
                 INNER JOIN {$wpdb->posts} p
                     ON p.ID = tl.product_post_id AND p.post_status = 'publish'
                 INNER JOIN {$wpdb->term_relationships} tr
                     ON tr.object_id = p.ID
                 INNER JOIN {$wpdb->term_taxonomy} tt
                     ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_type'
                 INNER JOIN {$wpdb->terms} t
                     ON t.term_id = tt.term_id AND t.slug = %s
                 WHERE tl.scraper_id = %d",
                $taxonomy_slug,
                (int) $scraper_id
            ));

            if (empty($product_ids_in_category)) {
                continue;
            }

            // Check scope: does this coupon actually apply to any of these products?
            $scope = get_field('coupon_scope', $coupon->ID);
            $scope_products = get_field('coupon_products', $coupon->ID);
            $scope_ids = is_array($scope_products) ? array_map('intval', $scope_products) : [];

            $has_applicable_product = false;

            if ($scope === 'specific') {
                // Only applies to products in the inclusions list.
                $has_applicable_product = !empty(array_intersect(
                    array_map('intval', $product_ids_in_category),
                    $scope_ids
                ));
            } else {
                // scope=all: applies to all except exceptions.
                foreach ($product_ids_in_category as $pid) {
                    if (!in_array((int) $pid, $scope_ids, true)) {
                        $has_applicable_product = true;
                        break;
                    }
                }
            }

            if ($has_applicable_product) {
                $matching[] = $coupon;
            }
        }

        return self::enrich_coupons($matching);
    }

    /**
     * Get all published, non-expired coupons that apply to a specific product.
     *
     * Checks both scope types:
     * - scope=all: coupon applies unless product is in the exceptions list
     * - scope=specific: coupon applies only if product is in the inclusions list
     *
     * @param int $product_id The product post ID.
     * @return array Array of coupon data arrays.
     */
    public static function get_for_product(int $product_id): array {
        $all_coupons = get_posts([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ]);

        $matching = [];

        foreach ($all_coupons as $coupon) {
            if (self::is_expired($coupon->ID)) {
                continue;
            }

            $scope = get_field('coupon_scope', $coupon->ID);
            $products = get_field('coupon_products', $coupon->ID);
            $product_ids = is_array($products) ? array_map('intval', $products) : [];

            if ($scope === 'all') {
                if (!in_array($product_id, $product_ids, true)) {
                    $matching[] = $coupon;
                }
            } elseif ($scope === 'specific') {
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

        // Build affiliate homepage URL from scraper domain + affiliate_link_format.
        $homepage = 'https://' . $scraper->domain . '/';
        $affiliate_url = $homepage;
        if (!empty($scraper->affiliate_link_format)) {
            $affiliate_url = str_replace(
                ['{URL}', '{URLE}'],
                [$homepage, urlencode($homepage)],
                $scraper->affiliate_link_format
            );
        }

        return [
            'name'          => $scraper->name ?: $scraper->domain,
            'domain'        => $scraper->domain,
            'logo_url'      => $logo_url,
            'affiliate_url' => $affiliate_url,
        ];
    }

    /**
     * Group enriched coupons by retailer name.
     *
     * @param array $coupons Enriched coupon data from get_by_category().
     * @return array Grouped array: [ ['retailer' => [...], 'coupons' => [...]], ... ]
     */
    public static function group_by_retailer(array $coupons): array {
        $groups = [];

        foreach ($coupons as $coupon) {
            $retailer_name = $coupon['retailer']['name'] ?? 'Unknown';

            if (!isset($groups[$retailer_name])) {
                $groups[$retailer_name] = [
                    'retailer' => $coupon['retailer'],
                    'coupons'  => [],
                ];
            }

            $groups[$retailer_name]['coupons'][] = $coupon;
        }

        return array_values($groups);
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
            $expires = get_field('coupon_expires', $post->ID);

            $coupons[] = [
                'id'          => $post->ID,
                'title'       => get_the_title($post->ID),
                'code'        => get_field('coupon_code', $post->ID) ?: '',
                'type'        => get_field('coupon_type', $post->ID) ?: 'percent',
                'value'       => get_field('coupon_value', $post->ID),
                'description' => get_field('coupon_description', $post->ID) ?: '',
                'min_order'   => get_field('coupon_min_order', $post->ID),
                'url'         => get_field('coupon_url', $post->ID) ?: '',
                'expires'     => $expires ?: null,
                'terms'       => get_field('coupon_terms', $post->ID) ?: '',
                'scope'       => get_field('coupon_scope', $post->ID) ?: 'all',
                'retailer'    => $retailer,
                'modified'    => get_post_modified_time('U', false, $post->ID),
            ];
        }

        return $coupons;
    }
}
