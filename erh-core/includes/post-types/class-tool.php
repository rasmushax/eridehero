<?php
/**
 * Tool Custom Post Type.
 *
 * @package ERH\PostTypes
 */

declare(strict_types=1);

namespace ERH\PostTypes;

/**
 * Handles the Tools custom post type registration.
 * Used for calculators and interactive tools.
 */
class Tool {

    /**
     * Post type slug.
     *
     * @var string
     */
    public const POST_TYPE = 'tool';

    /**
     * Taxonomy slug.
     *
     * @var string
     */
    public const TAXONOMY = 'tool_category';

    /**
     * Register the post type and hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomy']);
        add_action('acf/init', [$this, 'register_acf_fields']);
    }

    /**
     * Register the Tools custom post type.
     *
     * @return void
     */
    public function register_post_type(): void {
        $labels = [
            'name'                  => _x('Tools', 'Post type general name', 'erh-core'),
            'singular_name'         => _x('Tool', 'Post type singular name', 'erh-core'),
            'menu_name'             => _x('Tools', 'Admin Menu text', 'erh-core'),
            'name_admin_bar'        => _x('Tool', 'Add New on Toolbar', 'erh-core'),
            'add_new'               => __('Add New', 'erh-core'),
            'add_new_item'          => __('Add New Tool', 'erh-core'),
            'new_item'              => __('New Tool', 'erh-core'),
            'edit_item'             => __('Edit Tool', 'erh-core'),
            'view_item'             => __('View Tool', 'erh-core'),
            'all_items'             => __('All Tools', 'erh-core'),
            'search_items'          => __('Search Tools', 'erh-core'),
            'parent_item_colon'     => __('Parent Tools:', 'erh-core'),
            'not_found'             => __('No tools found.', 'erh-core'),
            'not_found_in_trash'    => __('No tools found in Trash.', 'erh-core'),
            'featured_image'        => _x('Tool Image', 'Overrides the "Featured Image" phrase', 'erh-core'),
            'set_featured_image'    => _x('Set tool image', 'Overrides the "Set featured image" phrase', 'erh-core'),
            'remove_featured_image' => _x('Remove tool image', 'Overrides the "Remove featured image" phrase', 'erh-core'),
            'use_featured_image'    => _x('Use as tool image', 'Overrides the "Use as featured image" phrase', 'erh-core'),
            'archives'              => _x('Tool archives', 'The post type archive label', 'erh-core'),
            'insert_into_item'      => _x('Insert into tool', 'Overrides the "Insert into post" phrase', 'erh-core'),
            'uploaded_to_this_item' => _x('Uploaded to this tool', 'Overrides the "Uploaded to this post" phrase', 'erh-core'),
            'filter_items_list'     => _x('Filter tools list', 'Screen reader text', 'erh-core'),
            'items_list_navigation' => _x('Tools list navigation', 'Screen reader text', 'erh-core'),
            'items_list'            => _x('Tools list', 'Screen reader text', 'erh-core'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'tools', 'with_front' => false],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-calculator',
            'show_in_rest'       => true,
            'rest_base'          => 'tools',
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register the Tool Category taxonomy.
     * Hidden from frontend, used for internal categorization only.
     *
     * @return void
     */
    public function register_taxonomy(): void {
        $labels = [
            'name'                       => _x('Tool Categories', 'taxonomy general name', 'erh-core'),
            'singular_name'              => _x('Tool Category', 'taxonomy singular name', 'erh-core'),
            'search_items'               => __('Search Tool Categories', 'erh-core'),
            'all_items'                  => __('All Tool Categories', 'erh-core'),
            'parent_item'                => __('Parent Tool Category', 'erh-core'),
            'parent_item_colon'          => __('Parent Tool Category:', 'erh-core'),
            'edit_item'                  => __('Edit Tool Category', 'erh-core'),
            'update_item'                => __('Update Tool Category', 'erh-core'),
            'add_new_item'               => __('Add New Tool Category', 'erh-core'),
            'new_item_name'              => __('New Tool Category Name', 'erh-core'),
            'menu_name'                  => __('Categories', 'erh-core'),
        ];

        $args = [
            'labels'             => $labels,
            'hierarchical'       => true,
            'public'             => false,           // Not publicly queryable
            'publicly_queryable' => false,           // No archive pages
            'show_ui'            => true,            // Show in admin
            'show_in_menu'       => true,            // Show in Tools menu
            'show_in_nav_menus'  => false,           // Hide from nav menus
            'show_tagcloud'      => false,           // No tag cloud
            'show_in_rest'       => true,            // Enable Gutenberg
            'show_admin_column'  => true,            // Show in tools list
            'rewrite'            => false,           // No URL rewrites
        ];

        register_taxonomy(self::TAXONOMY, self::POST_TYPE, $args);
    }

    /**
     * Register ACF fields for Tools.
     *
     * @return void
     */
    public function register_acf_fields(): void {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key'      => 'group_tool_settings',
            'title'    => 'Tool Settings',
            'fields'   => [
                [
                    'key'           => 'field_tool_description',
                    'label'         => 'Short Description',
                    'name'          => 'tool_description',
                    'type'          => 'textarea',
                    'instructions'  => 'Brief description for archive cards and meta description. 1-2 sentences.',
                    'rows'          => 2,
                    'maxlength'     => 160,
                ],
                [
                    'key'           => 'field_tool_icon',
                    'label'         => 'Tool Icon',
                    'name'          => 'tool_icon',
                    'type'          => 'select',
                    'instructions'  => 'Select an icon for this tool. Shown in archive and sidebar.',
                    'choices'       => [
                        'tool-battery-degradation' => 'Battery Degradation',
                        'tool-charging-time'       => 'Charging Time',
                        'tool-battery-capacity'    => 'Battery Capacity',
                        'tool-laws-map'            => 'Laws / Legal Scale',
                        'range'                    => 'Range / Distance',
                        'calculator'               => 'Calculator (generic)',
                        'zap'                      => 'Lightning Bolt',
                        'battery-charging'         => 'Battery Charging',
                        'dashboard'                => 'Gauge / Speedometer',
                        'weight'                   => 'Weight',
                        'tool-calculator-money'    => 'Calculator Money',
                    ],
                    'default_value' => 'calculator',
                    'return_format' => 'value',
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
            'position' => 'side',
        ]);

        // ACF fields for Tool Category taxonomy term
        acf_add_local_field_group([
            'key'      => 'group_tool_category_settings',
            'title'    => 'Category Settings',
            'fields'   => [
                [
                    'key'           => 'field_tool_category_product_type',
                    'label'         => 'Linked Product Type',
                    'name'          => 'linked_product_type',
                    'type'          => 'taxonomy',
                    'instructions'  => 'Link this tool category to a product type (e.g., Electric Scooters). Used for related content.',
                    'taxonomy'      => 'product_type',
                    'field_type'    => 'select',
                    'allow_null'    => 1,
                    'return_format' => 'id',
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'taxonomy',
                        'operator' => '==',
                        'value'    => self::TAXONOMY,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Get tool icon name.
     *
     * @param int $tool_id The tool ID.
     * @return string The icon name (without 'icon-' prefix).
     */
    public static function get_tool_icon(int $tool_id): string {
        $icon = get_field('tool_icon', $tool_id);
        return $icon ?: 'calculator';
    }

    /**
     * Get tool short description.
     *
     * @param int $tool_id The tool ID.
     * @return string The description text.
     */
    public static function get_tool_description(int $tool_id): string {
        $description = get_field('tool_description', $tool_id);
        return $description ?: '';
    }

    /**
     * Get tool slug from post.
     *
     * @param int $tool_id The tool ID.
     * @return string The tool slug.
     */
    public static function get_tool_slug(int $tool_id): string {
        $post = get_post($tool_id);
        return $post ? $post->post_name : '';
    }

    /**
     * Get all published tools.
     *
     * @param int $limit Maximum number of tools to return.
     * @return array<\WP_Post> List of tool posts.
     */
    public static function get_tools(int $limit = -1): array {
        return get_posts([
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
    }

    /**
     * Get tool category term.
     *
     * @param int $tool_id The tool ID.
     * @return \WP_Term|null The first tool category term or null.
     */
    public static function get_tool_category(int $tool_id): ?\WP_Term {
        $terms = get_the_terms($tool_id, self::TAXONOMY);
        if (is_wp_error($terms) || empty($terms)) {
            return null;
        }
        return $terms[0];
    }

    /**
     * Get tool category name.
     *
     * @param int $tool_id The tool ID.
     * @return string The category name or empty string.
     */
    public static function get_tool_category_name(int $tool_id): string {
        $term = self::get_tool_category($tool_id);
        return $term ? $term->name : '';
    }
}
