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
                'key'           => 'field_buying_guides_link_url',
                'label'         => __( 'View All Link URL', 'erh' ),
                'name'          => 'buying_guides_link_url',
                'type'          => 'url',
                'default_value' => '/buying-guides/',
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
