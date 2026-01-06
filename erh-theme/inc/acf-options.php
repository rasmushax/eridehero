<?php
/**
 * ACF Options Pages & Field Groups
 *
 * Registers theme options pages and field groups for site-wide settings.
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register ACF Options Pages
 */
function erh_register_options_pages(): void {
    // Check if ACF Pro is active
    if ( ! function_exists( 'acf_add_options_page' ) ) {
        return;
    }

    // Main Theme Settings page
    acf_add_options_page( array(
        'page_title'  => __( 'Theme Settings', 'erh' ),
        'menu_title'  => __( 'Theme Settings', 'erh' ),
        'menu_slug'   => 'erh-theme-settings',
        'capability'  => 'manage_options',
        'redirect'    => true, // Redirect to first sub-page
        'icon_url'    => 'dashicons-admin-customizer',
        'position'    => 59,
    ) );

    // Homepage Settings sub-page
    acf_add_options_sub_page( array(
        'page_title'  => __( 'Homepage Settings', 'erh' ),
        'menu_title'  => __( 'Homepage', 'erh' ),
        'parent_slug' => 'erh-theme-settings',
        'menu_slug'   => 'erh-homepage-settings',
        'capability'  => 'manage_options',
    ) );

    // Global Settings sub-page (for footer, social links, etc.)
    acf_add_options_sub_page( array(
        'page_title'  => __( 'Global Settings', 'erh' ),
        'menu_title'  => __( 'Global', 'erh' ),
        'parent_slug' => 'erh-theme-settings',
        'menu_slug'   => 'erh-global-settings',
        'capability'  => 'manage_options',
    ) );
}
add_action( 'acf/init', 'erh_register_options_pages' );

/**
 * Register ACF Field Groups via PHP
 *
 * These field groups can also be created via the ACF UI and exported.
 * Using PHP ensures they exist even if the JSON sync is not set up.
 */
function erh_register_acf_fields(): void {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    // As Seen On Section Fields
    acf_add_local_field_group( array(
        'key'      => 'group_as_seen_on',
        'title'    => __( 'As Seen On Section', 'erh' ),
        'fields'   => array(
            array(
                'key'           => 'field_as_seen_on_label',
                'label'         => __( 'Section Label', 'erh' ),
                'name'          => 'as_seen_on_label',
                'type'          => 'text',
                'default_value' => 'Our Work Has Been Featured In',
                'placeholder'   => 'Our Work Has Been Featured In',
            ),
            array(
                'key'          => 'field_as_seen_on_logos',
                'label'        => __( 'Publication Logos', 'erh' ),
                'name'         => 'as_seen_on_logos',
                'type'         => 'repeater',
                'layout'       => 'table',
                'button_label' => __( 'Add Logo', 'erh' ),
                'sub_fields'   => array(
                    array(
                        'key'           => 'field_as_seen_on_logo_name',
                        'label'         => __( 'Publication Name', 'erh' ),
                        'name'          => 'name',
                        'type'          => 'text',
                        'required'      => 1,
                        'wrapper'       => array( 'width' => '40' ),
                    ),
                    array(
                        'key'           => 'field_as_seen_on_logo_image',
                        'label'         => __( 'Logo (SVG preferred)', 'erh' ),
                        'name'          => 'logo',
                        'type'          => 'image',
                        'return_format' => 'array',
                        'preview_size'  => 'thumbnail',
                        'mime_types'    => 'svg,png,jpg,jpeg,webp',
                        'required'      => 1,
                        'wrapper'       => array( 'width' => '40' ),
                    ),
                    array(
                        'key'           => 'field_as_seen_on_logo_url',
                        'label'         => __( 'Link URL (optional)', 'erh' ),
                        'name'          => 'url',
                        'type'          => 'url',
                        'wrapper'       => array( 'width' => '20' ),
                    ),
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'erh-homepage-settings',
                ),
            ),
        ),
        'menu_order' => 10,
    ) );

    // Hero Section Fields (for future customization)
    acf_add_local_field_group( array(
        'key'      => 'group_hero_section',
        'title'    => __( 'Hero Section', 'erh' ),
        'fields'   => array(
            array(
                'key'           => 'field_hero_eyebrow',
                'label'         => __( 'Eyebrow Text', 'erh' ),
                'name'          => 'hero_eyebrow',
                'type'          => 'text',
                'default_value' => '120+ products · 12,000+ miles tested',
            ),
            array(
                'key'           => 'field_hero_title',
                'label'         => __( 'Title', 'erh' ),
                'name'          => 'hero_title',
                'type'          => 'text',
                'default_value' => 'Find your perfect',
            ),
            array(
                'key'           => 'field_hero_title_highlight',
                'label'         => __( 'Title Highlight', 'erh' ),
                'name'          => 'hero_title_highlight',
                'type'          => 'text',
                'default_value' => 'electric ride',
                'instructions'  => __( 'This text appears with the gradient highlight', 'erh' ),
            ),
            array(
                'key'           => 'field_hero_subtitle',
                'label'         => __( 'Subtitle', 'erh' ),
                'name'          => 'hero_subtitle',
                'type'          => 'textarea',
                'rows'          => 2,
                'default_value' => 'Data-driven reviews and comparison tools to help you choose the right e-scooter, e-bike, or EUC.',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'erh-homepage-settings',
                ),
            ),
        ),
        'menu_order' => 0,
    ) );

    // Buying Guides Section Fields
    acf_add_local_field_group( array(
        'key'      => 'group_buying_guides_section',
        'title'    => __( 'Buying Guides Section', 'erh' ),
        'fields'   => array(
            array(
                'key'           => 'field_buying_guides_title',
                'label'         => __( 'Section Title', 'erh' ),
                'name'          => 'buying_guides_title',
                'type'          => 'text',
                'default_value' => 'Buying guides',
            ),
            array(
                'key'           => 'field_buying_guides_link_text',
                'label'         => __( 'View All Link Text', 'erh' ),
                'name'          => 'buying_guides_link_text',
                'type'          => 'text',
                'default_value' => 'View all guides',
            ),
            array(
                'key'           => 'field_buying_guides_link_page',
                'label'         => __( 'View All Link Page', 'erh' ),
                'name'          => 'buying_guides_link_page',
                'type'          => 'post_object',
                'post_type'     => array( 'page' ),
                'return_format' => 'id',
                'ui'            => 1,
                'instructions'  => __( 'Select the page to link to. Defaults to /buying-guides/ if not set.', 'erh' ),
            ),
            array(
                'key'          => 'field_buying_guides_posts',
                'label'        => __( 'Featured Buying Guides', 'erh' ),
                'name'         => 'buying_guides_posts',
                'type'         => 'relationship',
                'post_type'    => array( 'post' ),
                'taxonomy'     => array( 'post_tag:buying-guide' ),
                'filters'      => array( 'search', 'taxonomy' ),
                'return_format' => 'id',
                'min'          => 0,
                'max'          => 4,
                'instructions' => __( 'Select up to 4 buying guide posts. If empty, latest posts tagged "buying-guide" will be shown.', 'erh' ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'erh-homepage-settings',
                ),
            ),
        ),
        'menu_order' => 30,
    ) );

    // YouTube API Settings (Global Settings)
    acf_add_local_field_group( array(
        'key'      => 'group_youtube_settings',
        'title'    => __( 'YouTube Settings', 'erh' ),
        'fields'   => array(
            array(
                'key'          => 'field_youtube_api_key',
                'label'        => __( 'YouTube API Key', 'erh' ),
                'name'         => 'youtube_api_key',
                'type'         => 'text',
                'instructions' => __( 'Google Cloud API key with YouTube Data API v3 enabled.', 'erh' ),
            ),
            array(
                'key'          => 'field_youtube_channel_id',
                'label'        => __( 'YouTube Channel ID', 'erh' ),
                'name'         => 'youtube_channel_id',
                'type'         => 'text',
                'instructions' => __( 'Channel ID (starts with UC...). Find it in YouTube Studio > Settings > Channel > Advanced.', 'erh' ),
            ),
            array(
                'key'           => 'field_youtube_channel_url',
                'label'         => __( 'YouTube Channel URL', 'erh' ),
                'name'          => 'youtube_channel_url',
                'type'          => 'url',
                'default_value' => 'https://youtube.com/@eridehero',
                'instructions'  => __( 'Full URL for the subscribe button.', 'erh' ),
            ),
            array(
                'key'           => 'field_youtube_view_stat',
                'label'         => __( 'View Stats Text', 'erh' ),
                'name'          => 'youtube_view_stat',
                'type'          => 'text',
                'default_value' => '800K+ views',
                'instructions'  => __( 'Displayed next to the YouTube heading.', 'erh' ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'erh-global-settings',
                ),
            ),
        ),
        'menu_order' => -10,
    ) );

    // CTA Section (Global Settings - appears in footer on all pages)
    acf_add_local_field_group( array(
        'key'      => 'group_cta_section',
        'title'    => __( 'Sign Up CTA Section', 'erh' ),
        'fields'   => array(
            array(
                'key'           => 'field_cta_title',
                'label'         => __( 'Title', 'erh' ),
                'name'          => 'cta_title',
                'type'          => 'text',
                'default_value' => 'Unlock all ERideHero features for free',
            ),
            array(
                'key'           => 'field_cta_pill',
                'label'         => __( 'Pill Text', 'erh' ),
                'name'          => 'cta_pill',
                'type'          => 'text',
                'default_value' => '1,200+ members',
                'instructions'  => __( 'Small badge shown next to the title.', 'erh' ),
            ),
            array(
                'key'          => 'field_cta_benefits',
                'label'        => __( 'Benefits', 'erh' ),
                'name'         => 'cta_benefits',
                'type'         => 'repeater',
                'layout'       => 'table',
                'button_label' => __( 'Add Benefit', 'erh' ),
                'min'          => 1,
                'max'          => 5,
                'sub_fields'   => array(
                    array(
                        'key'   => 'field_cta_benefit_text',
                        'label' => __( 'Benefit', 'erh' ),
                        'name'  => 'text',
                        'type'  => 'text',
                    ),
                ),
            ),
            array(
                'key'           => 'field_cta_button_text',
                'label'         => __( 'Button Text', 'erh' ),
                'name'          => 'cta_button_text',
                'type'          => 'text',
                'default_value' => 'Sign up free',
            ),
            array(
                'key'           => 'field_cta_button_page',
                'label'         => __( 'Button Link Page', 'erh' ),
                'name'          => 'cta_button_page',
                'type'          => 'post_object',
                'post_type'     => array( 'page' ),
                'return_format' => 'id',
                'ui'            => 1,
                'instructions'  => __( 'Select the page to link to. Defaults to /signup/ if not set.', 'erh' ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'erh-global-settings',
                ),
            ),
        ),
        'menu_order' => 0,
    ) );

    // Social Links (Global Settings)
    acf_add_local_field_group( array(
        'key'      => 'group_social_links',
        'title'    => __( 'Social Media Links', 'erh' ),
        'fields'   => array(
            array(
                'key'   => 'field_social_youtube',
                'label' => __( 'YouTube URL', 'erh' ),
                'name'  => 'social_youtube',
                'type'  => 'url',
            ),
            array(
                'key'   => 'field_social_instagram',
                'label' => __( 'Instagram URL', 'erh' ),
                'name'  => 'social_instagram',
                'type'  => 'url',
            ),
            array(
                'key'   => 'field_social_facebook',
                'label' => __( 'Facebook URL', 'erh' ),
                'name'  => 'social_facebook',
                'type'  => 'url',
            ),
            array(
                'key'   => 'field_social_twitter',
                'label' => __( 'X (Twitter) URL', 'erh' ),
                'name'  => 'social_twitter',
                'type'  => 'url',
            ),
            array(
                'key'   => 'field_social_linkedin',
                'label' => __( 'LinkedIn URL', 'erh' ),
                'name'  => 'social_linkedin',
                'type'  => 'url',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'erh-global-settings',
                ),
            ),
        ),
        'menu_order' => 0,
    ) );
    // ===========================================
    // POST-LEVEL FIELD GROUPS (for individual posts)
    // ===========================================

    // Review Post Fields (shown when post has 'review' tag)
    acf_add_local_field_group( array(
        'key'      => 'group_review_post',
        'title'    => __( 'Review Details', 'erh' ),
        'fields'   => array(
            array(
                'key'           => 'field_review_product',
                'label'         => __( 'Linked Product', 'erh' ),
                'name'          => 'review_product',
                'type'          => 'post_object',
                'post_type'     => array( 'products' ),
                'return_format' => 'id',
                'ui'            => 1,
                'instructions'  => __( 'Select the product this review is for. Rating will be pulled from the product.', 'erh' ),
            ),
            array(
                'key'          => 'field_review_tldr',
                'label'        => __( 'TL;DR', 'erh' ),
                'name'         => 'review_tldr',
                'type'         => 'textarea',
                'rows'         => 2,
                'instructions' => __( 'Very short summary (1-2 sentences) for cards and previews.', 'erh' ),
            ),
            array(
                'key'          => 'field_review_quick_take',
                'label'        => __( 'Quick Take', 'erh' ),
                'name'         => 'review_quick_take',
                'type'         => 'textarea',
                'rows'         => 4,
                'instructions' => __( 'Brief overview of the product (shown at top of review).', 'erh' ),
            ),
            array(
                'key'          => 'field_review_pros',
                'label'        => __( 'Pros', 'erh' ),
                'name'         => 'review_pros',
                'type'         => 'textarea',
                'rows'         => 5,
                'instructions' => __( 'One pro per line.', 'erh' ),
            ),
            array(
                'key'          => 'field_review_cons',
                'label'        => __( 'Cons', 'erh' ),
                'name'         => 'review_cons',
                'type'         => 'textarea',
                'rows'         => 5,
                'instructions' => __( 'One con per line.', 'erh' ),
            ),
            array(
                'key'           => 'field_review_gallery',
                'label'         => __( 'Gallery Images', 'erh' ),
                'name'          => 'review_gallery',
                'type'          => 'gallery',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'library'       => 'all',
                'min'           => 0,
                'max'           => 20,
                'insert'        => 'append',
                'instructions'  => __( 'Additional product photos. The Featured Image will be used as the main hero image; these become the thumbnail strip below it.', 'erh' ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'post',
                ),
                array(
                    'param'    => 'post_taxonomy',
                    'operator' => '==',
                    'value'    => 'post_tag:review',
                ),
            ),
        ),
        'position'   => 'normal',
        'style'      => 'default',
        'menu_order' => 0,
    ) );

    // Buying Guide Post Fields (shown when post has 'buying-guide' tag)
    acf_add_local_field_group( array(
        'key'      => 'group_buying_guide_post',
        'title'    => __( 'Buying Guide Details', 'erh' ),
        'fields'   => array(
            array(
                'key'          => 'field_buying_guide_is_featured',
                'label'        => __( 'Featured Guide', 'erh' ),
                'name'         => 'is_featured_guide',
                'type'         => 'true_false',
                'ui'           => 1,
                'ui_on_text'   => __( 'Yes', 'erh' ),
                'ui_off_text'  => __( 'No', 'erh' ),
                'instructions' => __( 'Show this guide in the featured row on hub pages (max 2 per category).', 'erh' ),
                'wrapper'      => array( 'width' => '33' ),
            ),
            array(
                'key'           => 'field_buying_guide_order',
                'label'         => __( 'Sort Order', 'erh' ),
                'name'          => 'guide_order',
                'type'          => 'number',
                'default_value' => 10,
                'min'           => 0,
                'step'          => 1,
                'instructions'  => __( 'Lower numbers appear first. Use 1, 2, 3... for ordering.', 'erh' ),
                'wrapper'       => array( 'width' => '33' ),
            ),
            array(
                'key'          => 'field_buying_guide_badge',
                'label'        => __( 'Badge Text', 'erh' ),
                'name'         => 'guide_badge',
                'type'         => 'text',
                'placeholder'  => 'e.g., Start Here, Our Top Picks',
                'instructions' => __( 'Optional badge shown on featured cards. Leave empty for no badge.', 'erh' ),
                'wrapper'      => array( 'width' => '34' ),
            ),
            array(
                'key'          => 'field_buying_guide_card_title',
                'label'        => __( 'Card Title', 'erh' ),
                'name'         => 'buying_guide_card_title',
                'type'         => 'text',
                'instructions' => __( 'Short title for cards/lists (e.g., "Best E-Scooters 2025"). Leave empty to use post title.', 'erh' ),
            ),
            array(
                'key'          => 'field_buying_guide_subtitle',
                'label'        => __( 'Subtitle', 'erh' ),
                'name'         => 'buying_guide_subtitle',
                'type'         => 'text',
                'instructions' => __( 'Optional subtitle shown below the title.', 'erh' ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'post',
                ),
                array(
                    'param'    => 'post_taxonomy',
                    'operator' => '==',
                    'value'    => 'post_tag:buying-guide',
                ),
            ),
        ),
        'position'   => 'normal',
        'style'      => 'default',
        'menu_order' => 0,
    ) );

    // ===========================================
    // HOMEPAGE SECTION SETTINGS
    // ===========================================

    // "How We Test" Sidebar (Homepage)
    acf_add_local_field_group( array(
        'key'      => 'group_how_we_test',
        'title'    => __( 'How We Test Sidebar', 'erh' ),
        'fields'   => array(
            array(
                'key'           => 'field_how_we_test_image',
                'label'         => __( 'Image', 'erh' ),
                'name'          => 'how_we_test_image',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'instructions'  => __( 'Photo for the sidebar card.', 'erh' ),
            ),
            array(
                'key'           => 'field_how_we_test_title',
                'label'         => __( 'Title', 'erh' ),
                'name'          => 'how_we_test_title',
                'type'          => 'text',
                'default_value' => 'How we test',
            ),
            array(
                'key'           => 'field_how_we_test_text',
                'label'         => __( 'Description', 'erh' ),
                'name'          => 'how_we_test_text',
                'type'          => 'textarea',
                'rows'          => 3,
                'default_value' => 'We measure real-world range, top speed, acceleration, and hill climbing. 30+ data-driven tests on every vehicle.',
            ),
            array(
                'key'          => 'field_how_we_test_stats',
                'label'        => __( 'Stats', 'erh' ),
                'name'         => 'how_we_test_stats',
                'type'         => 'repeater',
                'layout'       => 'table',
                'button_label' => __( 'Add Stat', 'erh' ),
                'max'          => 3,
                'sub_fields'   => array(
                    array(
                        'key'   => 'field_how_we_test_stat_value',
                        'label' => __( 'Value', 'erh' ),
                        'name'  => 'value',
                        'type'  => 'text',
                        'wrapper' => array( 'width' => '50' ),
                    ),
                    array(
                        'key'   => 'field_how_we_test_stat_label',
                        'label' => __( 'Label', 'erh' ),
                        'name'  => 'label',
                        'type'  => 'text',
                        'wrapper' => array( 'width' => '50' ),
                    ),
                ),
            ),
            array(
                'key'           => 'field_how_we_test_link_text',
                'label'         => __( 'Link Text', 'erh' ),
                'name'          => 'how_we_test_link_text',
                'type'          => 'text',
                'default_value' => 'Learn about our process',
            ),
            array(
                'key'           => 'field_how_we_test_link_page',
                'label'         => __( 'Link Page', 'erh' ),
                'name'          => 'how_we_test_link_page',
                'type'          => 'post_object',
                'post_type'     => array( 'page' ),
                'return_format' => 'id',
                'ui'            => 1,
                'instructions'  => __( 'Select the page to link to. Defaults to /how-we-test/ if not set.', 'erh' ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'erh-homepage-settings',
                ),
            ),
        ),
        'menu_order' => 45,
    ) );

    // ===========================================
    // TAXONOMY FIELDS
    // ===========================================

    // Product Type Taxonomy Fields
    acf_add_local_field_group( array(
        'key'      => 'group_product_type_taxonomy',
        'title'    => __( 'Product Type Settings', 'erh' ),
        'fields'   => array(
            array(
                'key'           => 'field_product_type_finder_page',
                'label'         => __( 'Finder Page', 'erh' ),
                'name'          => 'finder_page',
                'type'          => 'page_link',
                'post_type'     => array( 'page' ),
                'allow_null'    => 1,
                'allow_archives' => 0,
                'instructions'  => __( 'Select the finder page for this product type. Used in breadcrumbs on product pages.', 'erh' ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'taxonomy',
                    'operator' => '==',
                    'value'    => 'product_type',
                ),
            ),
        ),
        'menu_order' => 0,
    ) );

    // ===========================================
    // USER PROFILE FIELDS
    // ===========================================

    // Author Profile Fields (for all users)
    acf_add_local_field_group( array(
        'key'      => 'group_user_profile',
        'title'    => __( 'Author Profile', 'erh' ),
        'fields'   => array(
            array(
                'key'           => 'field_user_profile_image',
                'label'         => __( 'Profile Image', 'erh' ),
                'name'          => 'profile_image',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'thumbnail',
                'instructions'  => __( 'Headshot photo for author bylines and bio sections. Recommended: square, at least 200×200px.', 'erh' ),
                'wrapper'       => array( 'width' => '100' ),
            ),
            array(
                'key'           => 'field_user_title',
                'label'         => __( 'Title / Role', 'erh' ),
                'name'          => 'user_title',
                'type'          => 'text',
                'instructions'  => __( 'Your role at ERideHero (e.g., "Founder & Lead Reviewer", "Contributing Writer").', 'erh' ),
                'placeholder'   => 'e.g., Founder & Lead Reviewer',
                'wrapper'       => array( 'width' => '50' ),
            ),
            array(
                'key'         => 'field_user_social_linkedin',
                'label'       => __( 'LinkedIn', 'erh' ),
                'name'        => 'social_linkedin',
                'type'        => 'url',
                'placeholder' => 'https://linkedin.com/in/username',
                'wrapper'     => array( 'width' => '50' ),
            ),
            array(
                'key'         => 'field_user_social_facebook',
                'label'       => __( 'Facebook', 'erh' ),
                'name'        => 'social_facebook',
                'type'        => 'url',
                'placeholder' => 'https://facebook.com/username',
                'wrapper'     => array( 'width' => '50' ),
            ),
            array(
                'key'         => 'field_user_social_instagram',
                'label'       => __( 'Instagram', 'erh' ),
                'name'        => 'social_instagram',
                'type'        => 'url',
                'placeholder' => 'https://instagram.com/username',
                'wrapper'     => array( 'width' => '50' ),
            ),
            array(
                'key'         => 'field_user_social_twitter',
                'label'       => __( 'X (Twitter)', 'erh' ),
                'name'        => 'social_twitter',
                'type'        => 'url',
                'placeholder' => 'https://x.com/username',
                'wrapper'     => array( 'width' => '50' ),
            ),
            array(
                'key'         => 'field_user_social_youtube',
                'label'       => __( 'YouTube', 'erh' ),
                'name'        => 'social_youtube',
                'type'        => 'url',
                'placeholder' => 'https://youtube.com/@channel',
                'wrapper'     => array( 'width' => '50' ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'user_form',
                    'operator' => '==',
                    'value'    => 'all',
                ),
            ),
        ),
        'menu_order' => 0,
    ) );

    // "About ERideHero" Sidebar (Homepage)
    acf_add_local_field_group( array(
        'key'      => 'group_about_sidebar',
        'title'    => __( 'About Sidebar', 'erh' ),
        'fields'   => array(
            array(
                'key'           => 'field_about_author_photo',
                'label'         => __( 'Author Photo', 'erh' ),
                'name'          => 'about_author_photo',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'thumbnail',
            ),
            array(
                'key'           => 'field_about_author_name',
                'label'         => __( 'Author Name', 'erh' ),
                'name'          => 'about_author_name',
                'type'          => 'text',
                'default_value' => 'Rasmus Barslund',
            ),
            array(
                'key'           => 'field_about_author_role',
                'label'         => __( 'Author Role', 'erh' ),
                'name'          => 'about_author_role',
                'type'          => 'text',
                'default_value' => 'Founder & Lead Reviewer',
            ),
            array(
                'key'           => 'field_about_title',
                'label'         => __( 'Title', 'erh' ),
                'name'          => 'about_title',
                'type'          => 'text',
                'default_value' => 'About ERideHero',
            ),
            array(
                'key'           => 'field_about_text',
                'label'         => __( 'Description', 'erh' ),
                'name'          => 'about_text',
                'type'          => 'textarea',
                'rows'          => 3,
                'default_value' => 'The independent, data-driven guide to electric rides. Reviews, guides, and tools built on 120+ hands-on tests to help you ride smarter.',
            ),
            array(
                'key'           => 'field_about_link_text',
                'label'         => __( 'Link Text', 'erh' ),
                'name'          => 'about_link_text',
                'type'          => 'text',
                'default_value' => 'Learn more about us',
            ),
            array(
                'key'           => 'field_about_link_page',
                'label'         => __( 'Link Page', 'erh' ),
                'name'          => 'about_link_page',
                'type'          => 'post_object',
                'post_type'     => array( 'page' ),
                'return_format' => 'id',
                'ui'            => 1,
                'instructions'  => __( 'Select the page to link to. Defaults to /about/ if not set.', 'erh' ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'erh-homepage-settings',
                ),
            ),
        ),
        'menu_order' => 50,
    ) );
}
add_action( 'acf/init', 'erh_register_acf_fields' );

/**
 * Enable SVG uploads
 */
function erh_allow_svg_upload( array $mimes ): array {
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
}
add_filter( 'upload_mimes', 'erh_allow_svg_upload' );

/**
 * Fix SVG display in media library
 */
function erh_fix_svg_display(): void {
    echo '<style>
        .attachment-266x266, .thumbnail img {
            width: 100% !important;
            height: auto !important;
        }
    </style>';
}
add_action( 'admin_head', 'erh_fix_svg_display' );
