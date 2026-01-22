<?php
/**
 * Newsletter Custom Post Type.
 *
 * @package ERH\PostTypes
 */

declare(strict_types=1);

namespace ERH\PostTypes;

/**
 * Handles the Newsletter custom post type registration.
 * Used for composing and sending marketing emails to subscribers.
 */
class Newsletter {

    /**
     * Post type slug.
     */
    public const POST_TYPE = 'newsletter';

    /**
     * Custom post statuses.
     */
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENDING   = 'sending';
    public const STATUS_SENT      = 'sent';

    /**
     * Post meta keys.
     */
    public const META_SCHEDULED_AT      = '_newsletter_scheduled_at';
    public const META_SEND_STARTED_AT   = '_newsletter_send_started_at';
    public const META_SEND_COMPLETED_AT = '_newsletter_send_completed_at';
    public const META_TOTAL_RECIPIENTS  = '_newsletter_total_recipients';
    public const META_QUEUED_COUNT      = '_newsletter_queued_count';

    /**
     * Register the post type and hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_post_statuses']);
        add_action('acf/init', [$this, 'register_acf_fields']);
    }

    /**
     * Register the Newsletter custom post type.
     *
     * @return void
     */
    public function register_post_type(): void {
        $labels = [
            'name'                  => _x('Newsletters', 'Post type general name', 'erh-core'),
            'singular_name'         => _x('Newsletter', 'Post type singular name', 'erh-core'),
            'menu_name'             => _x('Newsletters', 'Admin Menu text', 'erh-core'),
            'name_admin_bar'        => _x('Newsletter', 'Add New on Toolbar', 'erh-core'),
            'add_new'               => __('Add New', 'erh-core'),
            'add_new_item'          => __('Add New Newsletter', 'erh-core'),
            'new_item'              => __('New Newsletter', 'erh-core'),
            'edit_item'             => __('Edit Newsletter', 'erh-core'),
            'view_item'             => __('View Newsletter', 'erh-core'),
            'all_items'             => __('All Newsletters', 'erh-core'),
            'search_items'          => __('Search Newsletters', 'erh-core'),
            'parent_item_colon'     => __('Parent Newsletters:', 'erh-core'),
            'not_found'             => __('No newsletters found.', 'erh-core'),
            'not_found_in_trash'    => __('No newsletters found in Trash.', 'erh-core'),
            'archives'              => _x('Newsletter archives', 'The post type archive label', 'erh-core'),
            'insert_into_item'      => _x('Insert into newsletter', 'Overrides the "Insert into post" phrase', 'erh-core'),
            'uploaded_to_this_item' => _x('Uploaded to this newsletter', 'Overrides the "Uploaded to this post" phrase', 'erh-core'),
            'filter_items_list'     => _x('Filter newsletters list', 'Screen reader text', 'erh-core'),
            'items_list_navigation' => _x('Newsletters list navigation', 'Screen reader text', 'erh-core'),
            'items_list'            => _x('Newsletters list', 'Screen reader text', 'erh-core'),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => 31,
            'menu_icon'           => 'dashicons-email-alt',
            'show_in_rest'        => false,
            'supports'            => ['title', 'revisions'],
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register custom post statuses for newsletters.
     *
     * @return void
     */
    public function register_post_statuses(): void {
        // Scheduled status - waiting for scheduled time.
        register_post_status(self::STATUS_SCHEDULED, [
            'label'                     => _x('Scheduled', 'newsletter', 'erh-core'),
            'public'                    => false,
            'internal'                  => true,
            'protected'                 => true,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            // translators: %s is the count of scheduled newsletters.
            'label_count'               => _n_noop(
                'Scheduled <span class="count">(%s)</span>',
                'Scheduled <span class="count">(%s)</span>',
                'erh-core'
            ),
        ]);

        // Sending status - currently queueing emails.
        register_post_status(self::STATUS_SENDING, [
            'label'                     => _x('Sending', 'newsletter', 'erh-core'),
            'public'                    => false,
            'internal'                  => true,
            'protected'                 => true,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            // translators: %s is the count of sending newsletters.
            'label_count'               => _n_noop(
                'Sending <span class="count">(%s)</span>',
                'Sending <span class="count">(%s)</span>',
                'erh-core'
            ),
        ]);

        // Sent status - completed.
        register_post_status(self::STATUS_SENT, [
            'label'                     => _x('Sent', 'newsletter', 'erh-core'),
            'public'                    => false,
            'internal'                  => true,
            'protected'                 => true,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            // translators: %s is the count of sent newsletters.
            'label_count'               => _n_noop(
                'Sent <span class="count">(%s)</span>',
                'Sent <span class="count">(%s)</span>',
                'erh-core'
            ),
        ]);
    }

    /**
     * Register ACF fields for Newsletter content.
     *
     * @return void
     */
    public function register_acf_fields(): void {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key'      => 'group_newsletter_content',
            'title'    => 'Newsletter Content',
            'fields'   => [
                // Preview text.
                [
                    'key'          => 'field_newsletter_preview_text',
                    'label'        => 'Preview Text',
                    'name'         => 'newsletter_preview_text',
                    'type'         => 'text',
                    'instructions' => 'Shows in email client before opening (max 150 characters).',
                    'maxlength'    => 150,
                ],

                // Content blocks repeater.
                [
                    'key'          => 'field_newsletter_blocks',
                    'label'        => 'Content Blocks',
                    'name'         => 'newsletter_blocks',
                    'type'         => 'repeater',
                    'instructions' => 'Add content blocks to build your newsletter.',
                    'layout'       => 'block',
                    'button_label' => 'Add Block',
                    'sub_fields'   => [
                        // Block type selector.
                        [
                            'key'           => 'field_block_type',
                            'label'         => 'Block Type',
                            'name'          => 'block_type',
                            'type'          => 'select',
                            'choices'       => [
                                'hero'    => 'Hero Section',
                                'text'    => 'Text Content',
                                'image'   => 'Image',
                                'button'  => 'Button',
                                'divider' => 'Divider',
                            ],
                            'default_value' => 'text',
                            'return_format' => 'value',
                        ],

                        // Hero fields.
                        [
                            'key'               => 'field_hero_badge',
                            'label'             => 'Badge Text',
                            'name'              => 'hero_badge',
                            'type'              => 'text',
                            'instructions'      => 'Optional eyebrow text above the title (e.g., "Weekly Update").',
                            'conditional_logic' => [
                                [
                                    [
                                        'field'    => 'field_block_type',
                                        'operator' => '==',
                                        'value'    => 'hero',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'key'               => 'field_hero_title',
                            'label'             => 'Hero Title',
                            'name'              => 'hero_title',
                            'type'              => 'text',
                            'required'          => 1,
                            'conditional_logic' => [
                                [
                                    [
                                        'field'    => 'field_block_type',
                                        'operator' => '==',
                                        'value'    => 'hero',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'key'               => 'field_hero_subtitle',
                            'label'             => 'Hero Subtitle',
                            'name'              => 'hero_subtitle',
                            'type'              => 'textarea',
                            'rows'              => 2,
                            'conditional_logic' => [
                                [
                                    [
                                        'field'    => 'field_block_type',
                                        'operator' => '==',
                                        'value'    => 'hero',
                                    ],
                                ],
                            ],
                        ],

                        // Text content field.
                        [
                            'key'               => 'field_text_content',
                            'label'             => 'Text Content',
                            'name'              => 'text_content',
                            'type'              => 'wysiwyg',
                            'toolbar'           => 'basic',
                            'media_upload'      => 0,
                            'conditional_logic' => [
                                [
                                    [
                                        'field'    => 'field_block_type',
                                        'operator' => '==',
                                        'value'    => 'text',
                                    ],
                                ],
                            ],
                        ],

                        // Image fields.
                        [
                            'key'               => 'field_image',
                            'label'             => 'Image',
                            'name'              => 'image',
                            'type'              => 'image',
                            'required'          => 1,
                            'return_format'     => 'array',
                            'preview_size'      => 'medium',
                            'library'           => 'all',
                            'conditional_logic' => [
                                [
                                    [
                                        'field'    => 'field_block_type',
                                        'operator' => '==',
                                        'value'    => 'image',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'key'               => 'field_image_link',
                            'label'             => 'Image Link (optional)',
                            'name'              => 'image_link',
                            'type'              => 'url',
                            'instructions'      => 'If set, the image will be clickable.',
                            'conditional_logic' => [
                                [
                                    [
                                        'field'    => 'field_block_type',
                                        'operator' => '==',
                                        'value'    => 'image',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'key'               => 'field_image_alt',
                            'label'             => 'Alt Text',
                            'name'              => 'image_alt',
                            'type'              => 'text',
                            'instructions'      => 'Descriptive text for accessibility. Uses image title if empty.',
                            'conditional_logic' => [
                                [
                                    [
                                        'field'    => 'field_block_type',
                                        'operator' => '==',
                                        'value'    => 'image',
                                    ],
                                ],
                            ],
                        ],

                        // Button fields.
                        [
                            'key'               => 'field_button_text',
                            'label'             => 'Button Text',
                            'name'              => 'button_text',
                            'type'              => 'text',
                            'required'          => 1,
                            'conditional_logic' => [
                                [
                                    [
                                        'field'    => 'field_block_type',
                                        'operator' => '==',
                                        'value'    => 'button',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'key'               => 'field_button_url',
                            'label'             => 'Button URL',
                            'name'              => 'button_url',
                            'type'              => 'url',
                            'required'          => 1,
                            'conditional_logic' => [
                                [
                                    [
                                        'field'    => 'field_block_type',
                                        'operator' => '==',
                                        'value'    => 'button',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],

                // Include sign-off toggle.
                [
                    'key'           => 'field_newsletter_include_signoff',
                    'label'         => 'Include Personal Sign-off',
                    'name'          => 'newsletter_include_signoff',
                    'type'          => 'true_false',
                    'instructions'  => 'Adds the Rasmus Barslund signature with headshot.',
                    'default_value' => 1,
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
            'position' => 'normal',
            'style'    => 'default',
        ]);
    }

    /**
     * Get scheduled newsletters that are due.
     *
     * @return array<int> Array of newsletter post IDs.
     */
    public static function get_due_newsletters(): array {
        return get_posts([
            'post_type'   => self::POST_TYPE,
            'post_status' => self::STATUS_SCHEDULED,
            'meta_query'  => [
                [
                    'key'     => self::META_SCHEDULED_AT,
                    'value'   => current_time('mysql', true),
                    'compare' => '<=',
                    'type'    => 'DATETIME',
                ],
            ],
            'fields'         => 'ids',
            'posts_per_page' => -1,
        ]);
    }

    /**
     * Transition newsletter to sending status.
     *
     * @param int $newsletter_id The newsletter ID.
     * @return bool True on success.
     */
    public static function mark_sending(int $newsletter_id): bool {
        $result = wp_update_post([
            'ID'          => $newsletter_id,
            'post_status' => self::STATUS_SENDING,
        ], true);

        if (is_wp_error($result)) {
            return false;
        }

        update_post_meta($newsletter_id, self::META_SEND_STARTED_AT, current_time('mysql', true));
        return true;
    }

    /**
     * Transition newsletter to sent status.
     *
     * @param int $newsletter_id The newsletter ID.
     * @param int $total         Total recipients.
     * @param int $queued        Successfully queued count.
     * @return bool True on success.
     */
    public static function mark_sent(int $newsletter_id, int $total, int $queued): bool {
        $result = wp_update_post([
            'ID'          => $newsletter_id,
            'post_status' => self::STATUS_SENT,
        ], true);

        if (is_wp_error($result)) {
            return false;
        }

        update_post_meta($newsletter_id, self::META_TOTAL_RECIPIENTS, $total);
        update_post_meta($newsletter_id, self::META_QUEUED_COUNT, $queued);
        update_post_meta($newsletter_id, self::META_SEND_COMPLETED_AT, current_time('mysql', true));
        return true;
    }

    /**
     * Schedule a newsletter for future sending.
     *
     * @param int    $newsletter_id The newsletter ID.
     * @param string $datetime      UTC datetime string.
     * @return bool True on success.
     */
    public static function schedule(int $newsletter_id, string $datetime): bool {
        $result = wp_update_post([
            'ID'          => $newsletter_id,
            'post_status' => self::STATUS_SCHEDULED,
        ], true);

        if (is_wp_error($result)) {
            return false;
        }

        update_post_meta($newsletter_id, self::META_SCHEDULED_AT, $datetime);
        return true;
    }

    /**
     * Get newsletter stats.
     *
     * @param int $newsletter_id The newsletter ID.
     * @return array Newsletter stats.
     */
    public static function get_stats(int $newsletter_id): array {
        return [
            'scheduled_at'      => get_post_meta($newsletter_id, self::META_SCHEDULED_AT, true) ?: null,
            'send_started_at'   => get_post_meta($newsletter_id, self::META_SEND_STARTED_AT, true) ?: null,
            'send_completed_at' => get_post_meta($newsletter_id, self::META_SEND_COMPLETED_AT, true) ?: null,
            'total_recipients'  => (int) get_post_meta($newsletter_id, self::META_TOTAL_RECIPIENTS, true),
            'queued_count'      => (int) get_post_meta($newsletter_id, self::META_QUEUED_COUNT, true),
        ];
    }
}
