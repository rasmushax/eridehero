<?php
/**
 * Comparison Custom Post Type.
 *
 * @package ERH\PostTypes
 */

declare(strict_types=1);

namespace ERH\PostTypes;

/**
 * Handles the Comparison custom post type registration.
 * Used for curated 2-product comparisons with editorial content.
 */
class Comparison {

    /**
     * Post type slug.
     *
     * @var string
     */
    public const POST_TYPE = 'comparison';

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
        add_action('acf/save_post', [$this, 'on_save_comparison'], 20);
        add_action('before_delete_post', [$this, 'on_product_delete']);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);
        add_filter('acf/validate_value/name=product_2', [$this, 'validate_products'], 10, 4);

        // Pre-populate ACF fields from URL params when creating new comparison.
        add_filter('acf/load_value/name=product_1', [$this, 'prefill_product_field'], 10, 3);
        add_filter('acf/load_value/name=product_2', [$this, 'prefill_product_field'], 10, 3);
    }

    /**
     * Register the Comparison custom post type.
     *
     * @return void
     */
    public function register_post_type(): void {
        $labels = [
            'name'                  => _x('Comparisons', 'Post type general name', 'erh-core'),
            'singular_name'         => _x('Comparison', 'Post type singular name', 'erh-core'),
            'menu_name'             => _x('Comparisons', 'Admin Menu text', 'erh-core'),
            'name_admin_bar'        => _x('Comparison', 'Add New on Toolbar', 'erh-core'),
            'add_new'               => __('Add New', 'erh-core'),
            'add_new_item'          => __('Add New Comparison', 'erh-core'),
            'new_item'              => __('New Comparison', 'erh-core'),
            'edit_item'             => __('Edit Comparison', 'erh-core'),
            'view_item'             => __('View Comparison', 'erh-core'),
            'all_items'             => __('All Comparisons', 'erh-core'),
            'search_items'          => __('Search Comparisons', 'erh-core'),
            'parent_item_colon'     => __('Parent Comparisons:', 'erh-core'),
            'not_found'             => __('No comparisons found.', 'erh-core'),
            'not_found_in_trash'    => __('No comparisons found in Trash.', 'erh-core'),
            'featured_image'        => _x('Comparison Image', 'Overrides the "Featured Image" phrase', 'erh-core'),
            'set_featured_image'    => _x('Set comparison image', 'Overrides the "Set featured image" phrase', 'erh-core'),
            'remove_featured_image' => _x('Remove comparison image', 'Overrides the "Remove featured image" phrase', 'erh-core'),
            'use_featured_image'    => _x('Use as comparison image', 'Overrides the "Use as featured image" phrase', 'erh-core'),
            'archives'              => _x('Comparison archives', 'The post type archive label', 'erh-core'),
            'insert_into_item'      => _x('Insert into comparison', 'Overrides the "Insert into post" phrase', 'erh-core'),
            'uploaded_to_this_item' => _x('Uploaded to this comparison', 'Overrides the "Uploaded to this post" phrase', 'erh-core'),
            'filter_items_list'     => _x('Filter comparisons list', 'Screen reader text', 'erh-core'),
            'items_list_navigation' => _x('Comparisons list navigation', 'Screen reader text', 'erh-core'),
            'items_list'            => _x('Comparisons list', 'Screen reader text', 'erh-core'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'compare', 'with_front' => false],
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 21,
            'menu_icon'          => 'dashicons-columns',
            'show_in_rest'       => true,
            'rest_base'          => 'comparisons',
            'supports'           => ['title', 'thumbnail', 'author'],
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register ACF fields for Comparisons.
     *
     * @return void
     */
    public function register_acf_fields(): void {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key'      => 'group_comparison_products',
            'title'    => 'Products to Compare',
            'fields'   => [
                [
                    'key'           => 'field_comparison_product_1',
                    'label'         => 'Product 1',
                    'name'          => 'product_1',
                    'type'          => 'relationship',
                    'instructions'  => 'Select the first product to compare.',
                    'required'      => 1,
                    'post_type'     => [self::PRODUCT_POST_TYPE],
                    'filters'       => ['search'],
                    'min'           => 1,
                    'max'           => 1,
                    'return_format' => 'id',
                ],
                [
                    'key'           => 'field_comparison_product_2',
                    'label'         => 'Product 2',
                    'name'          => 'product_2',
                    'type'          => 'relationship',
                    'instructions'  => 'Select the second product to compare. Must be same category as Product 1.',
                    'required'      => 1,
                    'post_type'     => [self::PRODUCT_POST_TYPE],
                    'filters'       => ['search'],
                    'min'           => 1,
                    'max'           => 1,
                    'return_format' => 'id',
                ],
                [
                    'key'           => 'field_comparison_category',
                    'label'         => 'Category',
                    'name'          => 'comparison_category',
                    'type'          => 'select',
                    'instructions'  => 'Auto-filled from Product 1 type.',
                    'choices'       => [
                        'escooter'    => 'E-Scooters',
                        'ebike'       => 'E-Bikes',
                        'eskateboard' => 'E-Skateboards',
                        'euc'         => 'Electric Unicycles',
                        'hoverboard'  => 'Hoverboards',
                    ],
                    'readonly'      => 1,
                    'default_value' => 'escooter',
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
            'key'      => 'group_comparison_content',
            'title'    => 'Comparison Content',
            'fields'   => [
                [
                    'key'           => 'field_comparison_intro',
                    'label'         => 'Introduction',
                    'name'          => 'intro_text',
                    'type'          => 'textarea',
                    'instructions'  => 'SEO-friendly intro paragraph. What readers will learn from this comparison. Max 300 characters.',
                    'rows'          => 3,
                    'maxlength'     => 300,
                ],
                [
                    'key'           => 'field_comparison_verdict_winner',
                    'label'         => 'Winner',
                    'name'          => 'verdict_winner',
                    'type'          => 'select',
                    'instructions'  => 'Which product wins this comparison?',
                    'choices'       => [
                        ''         => '-- Select --',
                        'product_1' => 'Product 1',
                        'product_2' => 'Product 2',
                        'tie'       => 'Tie (Both are great)',
                        'depends'   => 'Depends on use case',
                    ],
                    'default_value' => '',
                ],
                [
                    'key'           => 'field_comparison_verdict_text',
                    'label'         => 'Verdict',
                    'name'          => 'verdict_text',
                    'type'          => 'textarea',
                    'instructions'  => 'Explain your verdict. Why does the winner win? Or why does it depend?',
                    'rows'          => 4,
                ],
                [
                    'key'               => 'field_comparison_choose_product_1',
                    'label'             => 'Choose Product 1 If...',
                    'name'              => 'choose_product_1_reasons',
                    'type'              => 'textarea',
                    'instructions'      => 'One reason per line. E.g., "You want maximum range" or "Budget is your priority".',
                    'rows'              => 4,
                    'conditional_logic' => [
                        [
                            [
                                'field'    => 'field_comparison_verdict_winner',
                                'operator' => '==',
                                'value'    => 'depends',
                            ],
                        ],
                    ],
                ],
                [
                    'key'               => 'field_comparison_choose_product_2',
                    'label'             => 'Choose Product 2 If...',
                    'name'              => 'choose_product_2_reasons',
                    'type'              => 'textarea',
                    'instructions'      => 'One reason per line. E.g., "Ride comfort is your priority" or "You need a portable scooter".',
                    'rows'              => 4,
                    'conditional_logic' => [
                        [
                            [
                                'field'    => 'field_comparison_verdict_winner',
                                'operator' => '==',
                                'value'    => 'depends',
                            ],
                        ],
                    ],
                ],
                [
                    'key'           => 'field_comparison_featured',
                    'label'         => 'Featured Comparison',
                    'name'          => 'is_featured',
                    'type'          => 'true_false',
                    'instructions'  => 'Show this comparison on the hub and category landing pages.',
                    'default_value' => 0,
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
    }

    /**
     * Handle post save - auto-generate slug and set category.
     *
     * @param int|string $post_id The post ID (ACF passes strings like 'user_1' for user saves).
     * @return void
     */
    public function on_save_comparison($post_id): void {
        // ACF fires this for users too (e.g., 'user_1'), skip non-numeric IDs.
        if (!is_numeric($post_id)) {
            return;
        }

        $post_id = (int) $post_id;

        if (get_post_type($post_id) !== self::POST_TYPE) {
            return;
        }

        // Get products.
        $product_1_id = get_field('product_1', $post_id);
        $product_2_id = get_field('product_2', $post_id);

        if (!$product_1_id || !$product_2_id) {
            return;
        }

        // Handle array return format from relationship field.
        if (is_array($product_1_id)) {
            $product_1_id = $product_1_id[0];
        }
        if (is_array($product_2_id)) {
            $product_2_id = $product_2_id[0];
        }

        // Ensure canonical order: lower ID first.
        // This ensures the slug matches what dynamic URLs expect.
        if ((int) $product_2_id < (int) $product_1_id) {
            // Swap products.
            $temp = $product_1_id;
            $product_1_id = $product_2_id;
            $product_2_id = $temp;

            // Update the ACF fields with swapped values.
            update_field('product_1', $product_1_id, $post_id);
            update_field('product_2', $product_2_id, $post_id);
        }

        // Auto-fill category from product 1 (product_type is a taxonomy).
        $terms = wp_get_post_terms($product_1_id, 'product_type', ['fields' => 'slugs']);
        $product_type_slug = ! empty($terms) && ! is_wp_error($terms) ? $terms[0] : '';
        $category_key = $this->get_category_key_from_slug($product_type_slug);
        update_field('comparison_category', $category_key, $post_id);

        // Auto-generate slug: product-1-slug-vs-product-2-slug.
        $product_1_slug = get_post_field('post_name', $product_1_id);
        $product_2_slug = get_post_field('post_name', $product_2_id);

        if ($product_1_slug && $product_2_slug) {
            $new_slug = sanitize_title($product_1_slug . '-vs-' . $product_2_slug);

            // Only update if different (prevent infinite loop).
            $current_slug = get_post_field('post_name', $post_id);
            if ($current_slug !== $new_slug) {
                // Remove hook temporarily to prevent recursion.
                remove_action('acf/save_post', [$this, 'on_save_comparison'], 20);

                wp_update_post([
                    'ID'        => $post_id,
                    'post_name' => $new_slug,
                ]);

                add_action('acf/save_post', [$this, 'on_save_comparison'], 20);
            }
        }

        // Auto-generate title if empty or default.
        $current_title = get_the_title($post_id);
        if (empty($current_title) || $current_title === 'Auto Draft') {
            $product_1_name = get_the_title($product_1_id);
            $product_2_name = get_the_title($product_2_id);

            if ($product_1_name && $product_2_name) {
                remove_action('acf/save_post', [$this, 'on_save_comparison'], 20);

                wp_update_post([
                    'ID'         => $post_id,
                    'post_title' => $product_1_name . ' vs ' . $product_2_name,
                ]);

                add_action('acf/save_post', [$this, 'on_save_comparison'], 20);
            }
        }
    }

    /**
     * Validate that product_2 is different from product_1 and same category.
     *
     * @param bool|string $valid   Whether the value is valid.
     * @param mixed       $value   The field value.
     * @param array       $field   The field array.
     * @param string      $input   The input name.
     * @return bool|string
     */
    public function validate_products($valid, $value, array $field, string $input) {
        if (!$valid) {
            return $valid;
        }

        // Get product_1 value from POST.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $product_1 = isset($_POST['acf']['field_comparison_product_1'])
            ? absint($_POST['acf']['field_comparison_product_1'][0] ?? 0)
            : 0;

        $product_2 = is_array($value) ? absint($value[0] ?? 0) : absint($value);

        // Check not same product.
        if ($product_1 && $product_2 && $product_1 === $product_2) {
            return __('Product 2 must be different from Product 1.', 'erh-core');
        }

        // Check same category (product_type is a taxonomy).
        if ($product_1 && $product_2) {
            $terms_1 = wp_get_post_terms($product_1, 'product_type', ['fields' => 'slugs']);
            $terms_2 = wp_get_post_terms($product_2, 'product_type', ['fields' => 'slugs']);
            $type_1 = ! empty($terms_1) && ! is_wp_error($terms_1) ? $terms_1[0] : '';
            $type_2 = ! empty($terms_2) && ! is_wp_error($terms_2) ? $terms_2[0] : '';

            if ($type_1 !== $type_2) {
                return __('Both products must be the same category (e.g., both E-Scooters).', 'erh-core');
            }
        }

        // Check for duplicate pair (including reverse).
        if ($product_1 && $product_2) {
            $existing = $this->find_existing_comparison($product_1, $product_2);
            if ($existing) {
                // Check if it's not the current post.
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $current_post_id = isset($_POST['post_ID']) ? absint($_POST['post_ID']) : 0;
                if ($existing !== $current_post_id) {
                    return sprintf(
                        __('A comparison between these products already exists: %s', 'erh-core'),
                        get_the_title($existing)
                    );
                }
            }
        }

        return $valid;
    }

    /**
     * Pre-fill product relationship fields from URL params.
     *
     * When clicking "Create" from Popular Comparisons admin page,
     * the URL contains product_1 and product_2 params that should
     * pre-populate the ACF relationship fields.
     *
     * @param mixed  $value   The field value.
     * @param int    $post_id The post ID.
     * @param array  $field   The field array.
     * @return mixed The modified value.
     */
    public function prefill_product_field($value, $post_id, array $field) {
        // Only on new post screen.
        global $pagenow;
        if ($pagenow !== 'post-new.php') {
            return $value;
        }

        // Only if value is empty (not already set).
        if (!empty($value)) {
            return $value;
        }

        // Check for URL param matching field name.
        $field_name = $field['name'] ?? '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($field_name === 'product_1' && isset($_GET['product_1'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $product_id = absint($_GET['product_1']);
            if ($product_id && get_post_type($product_id) === self::PRODUCT_POST_TYPE) {
                // Return as array since relationship field expects array format.
                return [$product_id];
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($field_name === 'product_2' && isset($_GET['product_2'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $product_id = absint($_GET['product_2']);
            if ($product_id && get_post_type($product_id) === self::PRODUCT_POST_TYPE) {
                return [$product_id];
            }
        }

        return $value;
    }

    /**
     * Find existing comparison for a product pair.
     *
     * ACF relationship fields store values as serialized arrays like:
     * a:1:{i:0;i:123;} or a:1:{i:0;s:3:"123";}
     * So we use LIKE with patterns to match the serialized format.
     *
     * @param int $product_1_id First product ID.
     * @param int $product_2_id Second product ID.
     * @return int|null Comparison post ID or null.
     */
    public function find_existing_comparison(int $product_1_id, int $product_2_id): ?int {
        global $wpdb;

        // ACF relationship fields store as serialized arrays.
        // Match patterns like: i:123; (integer) or "123" (string in serialized).
        $pattern_1_int = '%i:' . $product_1_id . ';%';
        $pattern_1_str = '%"' . $product_1_id . '"%';
        $pattern_2_int = '%i:' . $product_2_id . ';%';
        $pattern_2_str = '%"' . $product_2_id . '"%';

        // Check both orderings (A vs B and B vs A).
        $sql = $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'product_1'
             INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'product_2'
             WHERE p.post_type = %s
             AND p.post_status IN ('publish', 'draft', 'pending')
             AND (
                 (
                     (pm1.meta_value LIKE %s OR pm1.meta_value LIKE %s)
                     AND (pm2.meta_value LIKE %s OR pm2.meta_value LIKE %s)
                 )
                 OR (
                     (pm1.meta_value LIKE %s OR pm1.meta_value LIKE %s)
                     AND (pm2.meta_value LIKE %s OR pm2.meta_value LIKE %s)
                 )
             )
             LIMIT 1",
            self::POST_TYPE,
            $pattern_1_int,
            $pattern_1_str,
            $pattern_2_int,
            $pattern_2_str,
            $pattern_2_int,
            $pattern_2_str,
            $pattern_1_int,
            $pattern_1_str
        );

        $result = $wpdb->get_var($sql);

        return $result ? (int) $result : null;
    }

    /**
     * Auto-trash comparisons when a product is deleted.
     *
     * @param int $post_id The post ID being deleted.
     * @return void
     */
    public function on_product_delete(int $post_id): void {
        if (get_post_type($post_id) !== self::PRODUCT_POST_TYPE) {
            return;
        }

        // ACF relationship fields store as serialized arrays.
        // Match patterns like: i:123; (integer) or "123" (string in serialized).
        $pattern_int = 'i:' . $post_id . ';';
        $pattern_str = '"' . $post_id . '"';

        // Find comparisons referencing this product.
        $comparisons = get_posts([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft', 'pending'],
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => 'product_1',
                    'value'   => $pattern_int,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'product_1',
                    'value'   => $pattern_str,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'product_2',
                    'value'   => $pattern_int,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'product_2',
                    'value'   => $pattern_str,
                    'compare' => 'LIKE',
                ],
            ],
            'fields'         => 'ids',
        ]);

        foreach ($comparisons as $comp_id) {
            wp_trash_post($comp_id);
        }
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

            // Insert after title.
            if ($key === 'title') {
                $new_columns['products']  = __('Products', 'erh-core');
                $new_columns['category']  = __('Category', 'erh-core');
                $new_columns['featured']  = __('Featured', 'erh-core');
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
            case 'products':
                $product_1 = get_field('product_1', $post_id);
                $product_2 = get_field('product_2', $post_id);

                if (is_array($product_1)) {
                    $product_1 = $product_1[0] ?? null;
                }
                if (is_array($product_2)) {
                    $product_2 = $product_2[0] ?? null;
                }

                if ($product_1 && $product_2) {
                    $link_1 = get_edit_post_link($product_1);
                    $link_2 = get_edit_post_link($product_2);
                    printf(
                        '<a href="%s">%s</a> vs <a href="%s">%s</a>',
                        esc_url($link_1),
                        esc_html(get_the_title($product_1)),
                        esc_url($link_2),
                        esc_html(get_the_title($product_2))
                    );
                }
                break;

            case 'category':
                $category = get_field('comparison_category', $post_id);
                $labels = [
                    'escooter'    => 'E-Scooters',
                    'ebike'       => 'E-Bikes',
                    'eskateboard' => 'E-Skateboards',
                    'euc'         => 'Electric Unicycles',
                    'hoverboard'  => 'Hoverboards',
                ];
                echo esc_html($labels[$category] ?? $category);
                break;

            case 'featured':
                $is_featured = get_field('is_featured', $post_id);
                echo $is_featured
                    ? '<span class="dashicons dashicons-star-filled" style="color: #f0b849;"></span>'
                    : '<span class="dashicons dashicons-star-empty" style="color: #ccc;"></span>';
                break;
        }
    }

    /**
     * Get category key from product type taxonomy slug.
     *
     * @param string $taxonomy_slug The product_type taxonomy slug.
     * @return string Category key.
     */
    private function get_category_key_from_slug(string $taxonomy_slug): string {
        $map = [
            'electric-scooter'    => 'escooter',
            'electric-bike'       => 'ebike',
            'electric-skateboard' => 'eskateboard',
            'electric-unicycle'   => 'euc',
            'hoverboard'          => 'hoverboard',
        ];

        return $map[$taxonomy_slug] ?? 'escooter';
    }

    /**
     * Get all featured comparisons.
     *
     * @param string|null $category Optional category filter.
     * @param int         $limit    Max number to return.
     * @return array<\WP_Post>
     */
    public static function get_featured(string $category = null, int $limit = 10): array {
        $args = [
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => $limit,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'   => 'is_featured',
                    'value' => '1',
                ],
            ],
        ];

        if ($category) {
            $args['meta_query'][] = [
                'key'   => 'comparison_category',
                'value' => $category,
            ];
        }

        return get_posts($args);
    }

    /**
     * Get comparison products.
     *
     * @param int $comparison_id The comparison post ID.
     * @return array{product_1: int|null, product_2: int|null}
     */
    public static function get_products(int $comparison_id): array {
        $product_1 = get_field('product_1', $comparison_id);
        $product_2 = get_field('product_2', $comparison_id);

        // Handle array return format.
        if (is_array($product_1)) {
            $product_1 = $product_1[0] ?? null;
        }
        if (is_array($product_2)) {
            $product_2 = $product_2[0] ?? null;
        }

        return [
            'product_1' => $product_1 ? (int) $product_1 : null,
            'product_2' => $product_2 ? (int) $product_2 : null,
        ];
    }

    /**
     * Get comparison by product pair.
     *
     * @param int $product_1_id First product ID.
     * @param int $product_2_id Second product ID.
     * @return \WP_Post|null
     */
    public static function get_by_products(int $product_1_id, int $product_2_id): ?\WP_Post {
        $instance = new self();
        $comparison_id = $instance->find_existing_comparison($product_1_id, $product_2_id);

        if ($comparison_id) {
            return get_post($comparison_id);
        }

        return null;
    }

    /**
     * Get related comparisons (sharing a product or same category).
     *
     * @param int         $comparison_id Current comparison ID.
     * @param int         $limit         Max number to return.
     * @return array<\WP_Post>
     */
    public static function get_related(int $comparison_id, int $limit = 4): array {
        $products = self::get_products($comparison_id);
        $category = get_field('comparison_category', $comparison_id);

        if (!$products['product_1'] || !$products['product_2']) {
            return [];
        }

        // Find comparisons sharing either product.
        $args = [
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => $limit + 1, // Get extra in case we need to exclude current.
            'post_status'    => 'publish',
            'post__not_in'   => [$comparison_id],
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => 'comparison_category',
                    'value' => $category,
                ],
                [
                    'relation' => 'OR',
                    [
                        'key'   => 'product_1',
                        'value' => [$products['product_1'], $products['product_2']],
                        'compare' => 'IN',
                    ],
                    [
                        'key'   => 'product_2',
                        'value' => [$products['product_1'], $products['product_2']],
                        'compare' => 'IN',
                    ],
                ],
            ],
        ];

        $related = get_posts($args);

        // If not enough, fill with same category comparisons.
        if (count($related) < $limit) {
            $exclude = array_merge([$comparison_id], wp_list_pluck($related, 'ID'));

            $more_args = [
                'post_type'      => self::POST_TYPE,
                'posts_per_page' => $limit - count($related),
                'post_status'    => 'publish',
                'post__not_in'   => $exclude,
                'meta_query'     => [
                    [
                        'key'   => 'comparison_category',
                        'value' => $category,
                    ],
                ],
                'orderby'        => 'rand',
            ];

            $more = get_posts($more_args);
            $related = array_merge($related, $more);
        }

        return array_slice($related, 0, $limit);
    }
}
