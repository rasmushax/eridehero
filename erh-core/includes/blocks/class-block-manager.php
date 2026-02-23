<?php
/**
 * Block Manager - Handles ACF block registration.
 *
 * @package ERH\Blocks
 */

declare(strict_types=1);

namespace ERH\Blocks;

/**
 * Manages ACF block registration and field groups.
 */
class BlockManager {

    /**
     * Blocks directory path.
     *
     * @var string
     */
    private string $blocks_dir;

    /**
     * Blocks URL.
     *
     * @var string
     */
    private string $blocks_url;

    /**
     * Registered blocks.
     *
     * @var array<string, array>
     */
    private array $blocks = [];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->blocks_dir = ERH_PLUGIN_DIR . 'includes/blocks/';
        $this->blocks_url = ERH_PLUGIN_URL . 'includes/blocks/';
    }

    /**
     * Register all hooks.
     *
     * @return void
     */
    public function register(): void {
        // Register blocks after ACF is loaded.
        add_action('acf/init', [$this, 'register_blocks']);

        // Register ACF field groups.
        add_action('acf/init', [$this, 'register_field_groups']);

        // Enqueue block assets.
        add_action('enqueue_block_assets', [$this, 'enqueue_block_assets']);
    }

    /**
     * Register all ACF blocks.
     *
     * @return void
     */
    public function register_blocks(): void {
        if (!function_exists('acf_register_block_type')) {
            return;
        }

        // Register accordion block.
        $this->register_accordion_block();

        // Register jumplinks block.
        $this->register_jumplinks_block();

        // Register checklist block.
        $this->register_checklist_block();

        // Register video block.
        $this->register_video_block();

        // Register listicle item block.
        $this->register_listicle_item_block();

        // Register buying guide table block.
        $this->register_buying_guide_table_block();

        // Register callout block.
        $this->register_callout_block();

        // Register greybox block.
        $this->register_greybox_block();
    }

    /**
     * Register the accordion block.
     *
     * @return void
     */
    private function register_accordion_block(): void {
        acf_register_block_type([
            'name'            => 'accordion',
            'title'           => __('Accordion', 'erh-core'),
            'description'     => __('Expandable accordion sections for FAQs and collapsible content.', 'erh-core'),
            'category'        => 'formatting',
            'icon'            => 'list-view',
            'keywords'        => ['accordion', 'faq', 'collapse', 'expand', 'toggle'],
            'mode'            => 'preview',
            'align'           => 'full',
            'supports'        => [
                'align'  => ['wide', 'full'],
                'anchor' => true,
                'jsx'    => true,
            ],
            'render_callback' => [$this, 'render_accordion_block'],
            'enqueue_assets'  => [$this, 'enqueue_accordion_assets'],
        ]);

        $this->blocks['accordion'] = [
            'name' => 'accordion',
            'dir'  => $this->blocks_dir . 'accordion/',
            'url'  => $this->blocks_url . 'accordion/',
        ];
    }

    /**
     * Register the jumplinks block.
     *
     * @return void
     */
    private function register_jumplinks_block(): void {
        acf_register_block_type([
            'name'            => 'jumplinks',
            'title'           => __('Jump Links', 'erh-core'),
            'description'     => __('Quick navigation links to page sections.', 'erh-core'),
            'category'        => 'formatting',
            'icon'            => 'editor-ul',
            'keywords'        => ['jump', 'links', 'navigation', 'anchor', 'toc'],
            'mode'            => 'preview',
            'supports'        => [
                'align'  => false,
                'anchor' => true,
            ],
            'render_callback' => [$this, 'render_jumplinks_block'],
            'enqueue_assets'  => [$this, 'enqueue_jumplinks_assets'],
        ]);

        $this->blocks['jumplinks'] = [
            'name' => 'jumplinks',
            'dir'  => $this->blocks_dir . 'jumplinks/',
            'url'  => $this->blocks_url . 'jumplinks/',
        ];
    }

    /**
     * Register the checklist block.
     *
     * @return void
     */
    private function register_checklist_block(): void {
        acf_register_block_type([
            'name'            => 'checklist',
            'title'           => __('Checklist', 'erh-core'),
            'description'     => __('Displays a checklist with optional title and description.', 'erh-core'),
            'category'        => 'formatting',
            'icon'            => 'yes-alt',
            'keywords'        => ['checklist', 'list', 'tips', 'check'],
            'mode'            => 'preview',
            'supports'        => [
                'align'  => ['wide', 'full'],
                'anchor' => true,
            ],
            'render_callback' => [$this, 'render_checklist_block'],
            'enqueue_assets'  => [$this, 'enqueue_checklist_assets'],
        ]);

        $this->blocks['checklist'] = [
            'name' => 'checklist',
            'dir'  => $this->blocks_dir . 'checklist/',
            'url'  => $this->blocks_url . 'checklist/',
        ];
    }

    /**
     * Register the video block.
     *
     * @return void
     */
    private function register_video_block(): void {
        acf_register_block_type([
            'name'            => 'video',
            'title'           => __('Video', 'erh-core'),
            'description'     => __('Lazy-loaded video player for better pagespeed.', 'erh-core'),
            'category'        => 'media',
            'icon'            => 'video-alt3',
            'keywords'        => ['video', 'media', 'mp4', 'player'],
            'mode'            => 'preview',
            'supports'        => [
                'align'  => ['wide', 'full'],
                'anchor' => true,
            ],
            'render_callback' => [$this, 'render_video_block'],
            'enqueue_assets'  => [$this, 'enqueue_video_assets'],
        ]);

        $this->blocks['video'] = [
            'name' => 'video',
            'dir'  => $this->blocks_dir . 'video/',
            'url'  => $this->blocks_url . 'video/',
        ];
    }

    /**
     * Register ACF field groups for all blocks.
     *
     * @return void
     */
    public function register_field_groups(): void {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        $this->register_accordion_fields();
        $this->register_jumplinks_fields();
        $this->register_checklist_fields();
        $this->register_video_fields();
        $this->register_listicle_item_fields();
        $this->register_buying_guide_table_fields();
        $this->register_callout_fields();
        $this->register_greybox_fields();
    }

    /**
     * Register accordion block ACF fields.
     *
     * Uses same field keys as existing export for compatibility with existing content.
     *
     * @return void
     */
    private function register_accordion_fields(): void {
        acf_add_local_field_group([
            'key'      => 'group_66c5e049d132a',
            'title'    => 'Block - Accordion',
            'fields'   => [
                [
                    'key'          => 'field_66c5e04be5955',
                    'label'        => 'Item',
                    'name'         => 'item',
                    'type'         => 'repeater',
                    'layout'       => 'block',
                    'button_label' => 'Add Row',
                    'min'          => 0,
                    'max'          => 0,
                    'sub_fields'   => [
                        [
                            'key'         => 'field_66c5e06ae5956',
                            'label'       => 'Title',
                            'name'        => 'title',
                            'type'        => 'text',
                            'placeholder' => 'Enter question or heading...',
                        ],
                        [
                            'key'          => 'field_66c5e075e5957',
                            'label'        => 'Text',
                            'name'         => 'text',
                            'type'         => 'wysiwyg',
                            'tabs'         => 'all',
                            'toolbar'      => 'full',
                            'media_upload' => 1,
                            'delay'        => 0,
                        ],
                        [
                            'key'           => 'field_66c5e07ee5958',
                            'label'         => 'Opened',
                            'name'          => 'opened',
                            'type'          => 'true_false',
                            'default_value' => 0,
                            'ui'            => 1,
                            'ui_on_text'    => 'Yes',
                            'ui_off_text'   => 'No',
                        ],
                    ],
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'block',
                        'operator' => '==',
                        'value'    => 'acf/accordion',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Register jumplinks block ACF fields.
     *
     * Uses same field keys as existing export for compatibility with existing content.
     *
     * @return void
     */
    private function register_jumplinks_fields(): void {
        acf_add_local_field_group([
            'key'      => 'group_65607c35b2d31',
            'title'    => 'Block - Quick Jump',
            'fields'   => [
                [
                    'key'           => 'field_65607c3676ca1',
                    'label'         => 'Title',
                    'name'          => 'title',
                    'type'          => 'text',
                    'default_value' => 'Jump to',
                ],
                [
                    'key'          => 'field_65607c4f76ca2',
                    'label'        => 'Jumplinks',
                    'name'         => 'jumplinks',
                    'type'         => 'repeater',
                    'layout'       => 'table',
                    'button_label' => 'Add Row',
                    'min'          => 0,
                    'max'          => 0,
                    'sub_fields'   => [
                        [
                            'key'     => 'field_65607c5c76ca3',
                            'label'   => 'Title',
                            'name'    => 'title',
                            'type'    => 'text',
                            'wrapper' => ['width' => '50'],
                        ],
                        [
                            'key'     => 'field_65607c6276ca4',
                            'label'   => 'Anchor (or URL)',
                            'name'    => 'anchor',
                            'type'    => 'text',
                            'wrapper' => ['width' => '50'],
                        ],
                    ],
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'block',
                        'operator' => '==',
                        'value'    => 'acf/jumplinks',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Register checklist block ACF fields.
     *
     * Uses same field keys as existing export for compatibility with existing content.
     *
     * @return void
     */
    private function register_checklist_fields(): void {
        acf_add_local_field_group([
            'key'      => 'group_67efa66033b6c',
            'title'    => 'Block - Checklist',
            'fields'   => [
                [
                    'key'   => 'field_67efa661fc80d',
                    'label' => 'Title',
                    'name'  => 'checklist_title',
                    'type'  => 'text',
                ],
                [
                    'key'   => 'field_67efa679fc80e',
                    'label' => 'Description',
                    'name'  => 'checklist_description',
                    'type'  => 'text',
                ],
                [
                    'key'          => 'field_67efa686fc80f',
                    'label'        => 'Checklist Items',
                    'name'         => 'checklist_items',
                    'type'         => 'repeater',
                    'layout'       => 'table',
                    'button_label' => 'Add Row',
                    'min'          => 0,
                    'max'          => 0,
                    'sub_fields'   => [
                        [
                            'key'   => 'field_67efa693fc810',
                            'label' => 'Item Text',
                            'name'  => 'item_text',
                            'type'  => 'text',
                        ],
                    ],
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'block',
                        'operator' => '==',
                        'value'    => 'acf/checklist',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Register video block ACF fields.
     *
     * Uses same field keys as existing export for compatibility with existing content.
     *
     * @return void
     */
    private function register_video_fields(): void {
        acf_add_local_field_group([
            'key'      => 'group_64b9267d2f1ef',
            'title'    => 'Block - Video',
            'fields'   => [
                [
                    'key'           => 'field_64b9267e7c011',
                    'label'         => 'Video',
                    'name'          => 'video',
                    'type'          => 'file',
                    'return_format' => 'url',
                    'mime_types'    => 'mp4',
                ],
                [
                    'key'           => 'field_64b928add99f1',
                    'label'         => 'Thumbnail',
                    'name'          => 'thumbnail',
                    'type'          => 'image',
                    'return_format' => 'id',
                    'preview_size'  => 'medium',
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'block',
                        'operator' => '==',
                        'value'    => 'acf/video',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Render the accordion block.
     *
     * @param array  $block      The block settings.
     * @param string $content    The block content (empty for ACF blocks).
     * @param bool   $is_preview True during AJAX preview in editor.
     * @param int    $post_id    The post ID.
     * @return void
     */
    public function render_accordion_block(array $block, string $content = '', bool $is_preview = false, int $post_id = 0): void {
        $template = $this->blocks_dir . 'accordion/template.php';

        if (file_exists($template)) {
            include $template;
        }
    }

    /**
     * Enqueue accordion block assets.
     *
     * @return void
     */
    public function enqueue_accordion_assets(): void {
        $block_url = $this->blocks_url . 'accordion/';
        $block_dir = $this->blocks_dir . 'accordion/';

        // Enqueue CSS.
        if (file_exists($block_dir . 'accordion.css')) {
            wp_enqueue_style(
                'erh-block-accordion',
                $block_url . 'accordion.css',
                [],
                ERH_VERSION
            );
        }

        // Enqueue JS (frontend only, not in editor).
        if (!is_admin() && file_exists($block_dir . 'accordion.js')) {
            wp_enqueue_script(
                'erh-block-accordion',
                $block_url . 'accordion.js',
                [],
                ERH_VERSION,
                true
            );
        }
    }

    /**
     * Render the jumplinks block.
     *
     * @param array  $block      The block settings.
     * @param string $content    The block content (empty for ACF blocks).
     * @param bool   $is_preview True during AJAX preview in editor.
     * @param int    $post_id    The post ID.
     * @return void
     */
    public function render_jumplinks_block(array $block, string $content = '', bool $is_preview = false, int $post_id = 0): void {
        $template = $this->blocks_dir . 'jumplinks/template.php';

        if (file_exists($template)) {
            include $template;
        }
    }

    /**
     * Enqueue jumplinks block assets.
     *
     * @return void
     */
    public function enqueue_jumplinks_assets(): void {
        $block_url = $this->blocks_url . 'jumplinks/';
        $block_dir = $this->blocks_dir . 'jumplinks/';

        // Enqueue CSS.
        if (file_exists($block_dir . 'jumplinks.css')) {
            wp_enqueue_style(
                'erh-block-jumplinks',
                $block_url . 'jumplinks.css',
                [],
                ERH_VERSION
            );
        }
    }

    /**
     * Render the checklist block.
     *
     * @param array  $block      The block settings.
     * @param string $content    The block content (empty for ACF blocks).
     * @param bool   $is_preview True during AJAX preview in editor.
     * @param int    $post_id    The post ID.
     * @return void
     */
    public function render_checklist_block(array $block, string $content = '', bool $is_preview = false, int $post_id = 0): void {
        $template = $this->blocks_dir . 'checklist/template.php';

        if (file_exists($template)) {
            include $template;
        }
    }

    /**
     * Enqueue checklist block assets.
     *
     * @return void
     */
    public function enqueue_checklist_assets(): void {
        $block_url = $this->blocks_url . 'checklist/';
        $block_dir = $this->blocks_dir . 'checklist/';

        // Enqueue CSS.
        if (file_exists($block_dir . 'checklist.css')) {
            wp_enqueue_style(
                'erh-block-checklist',
                $block_url . 'checklist.css',
                [],
                ERH_VERSION
            );
        }
    }

    /**
     * Render the video block.
     *
     * @param array  $block      The block settings.
     * @param string $content    The block content (empty for ACF blocks).
     * @param bool   $is_preview True during AJAX preview in editor.
     * @param int    $post_id    The post ID.
     * @return void
     */
    public function render_video_block(array $block, string $content = '', bool $is_preview = false, int $post_id = 0): void {
        $template = $this->blocks_dir . 'video/template.php';

        if (file_exists($template)) {
            include $template;
        }
    }

    /**
     * Enqueue video block assets.
     *
     * @return void
     */
    public function enqueue_video_assets(): void {
        $block_url = $this->blocks_url . 'video/';
        $block_dir = $this->blocks_dir . 'video/';

        // Enqueue CSS.
        if (file_exists($block_dir . 'video.css')) {
            wp_enqueue_style(
                'erh-block-video',
                $block_url . 'video.css',
                [],
                ERH_VERSION
            );
        }

        // Enqueue JS (frontend only, not in editor).
        if (!is_admin() && file_exists($block_dir . 'video.js')) {
            wp_enqueue_script(
                'erh-block-video',
                $block_url . 'video.js',
                [],
                ERH_VERSION,
                true
            );
        }
    }

    /**
     * Enqueue shared block assets (called on all pages with blocks).
     *
     * @return void
     */
    public function enqueue_block_assets(): void {
        // Shared block styles could be enqueued here if needed.
    }

    /**
     * Register the listicle item block.
     *
     * @return void
     */
    private function register_listicle_item_block(): void {
        acf_register_block_type([
            'name'            => 'listicle-item',
            'title'           => __('Listicle Item', 'erh-core'),
            'description'     => __('Advanced product display component for buying guides.', 'erh-core'),
            'category'        => 'formatting',
            'icon'            => 'star-filled',
            'keywords'        => ['listicle', 'product', 'review', 'buying guide', 'top pick'],
            'mode'            => 'preview',
            'supports'        => [
                'align'  => ['wide', 'full'],
                'anchor' => true,
            ],
            'render_callback' => [$this, 'render_listicle_item_block'],
            'enqueue_assets'  => [$this, 'enqueue_listicle_item_assets'],
        ]);

        $this->blocks['listicle-item'] = [
            'name' => 'listicle-item',
            'dir'  => $this->blocks_dir . 'listicle-item/',
            'url'  => $this->blocks_url . 'listicle-item/',
        ];
    }

    /**
     * Register listicle item block ACF fields.
     *
     * Uses same field keys as existing export for compatibility with existing content.
     *
     * @return void
     */
    private function register_listicle_item_fields(): void {
        acf_add_local_field_group([
            'key'      => 'group_67f7796a562a4',
            'title'    => 'Block - Listicle Item',
            'fields'   => [
                [
                    'key'   => 'field_67f7796e0fc78',
                    'label' => 'Label',
                    'name'  => 'label',
                    'type'  => 'text',
                    'instructions' => 'Badge text like "Best Overall", "Budget Pick", etc.',
                ],
                [
                    'key'           => 'field_67f77c612eeb4',
                    'label'         => 'Product',
                    'name'          => 'product_relationship',
                    'type'          => 'post_object',
                    'post_type'     => ['products'],
                    'post_status'   => ['publish'],
                    'return_format' => 'id',
                    'ui'            => 1,
                ],
                [
                    'key'           => 'field_67f779b70fc7a',
                    'label'         => 'Image',
                    'name'          => 'item_image',
                    'type'          => 'image',
                    'instructions'  => 'Override image (uses product image if empty).',
                    'return_format' => 'array',
                    'preview_size'  => 'medium',
                ],
                [
                    'key'   => 'field_67f77d0bd0b70',
                    'label' => 'Quick Take',
                    'name'  => 'quick_take',
                    'type'  => 'text',
                    'instructions' => 'One-sentence summary of this pick.',
                ],
                [
                    'key'     => 'field_67f779f10fc7b',
                    'label'   => 'What I Like',
                    'name'    => 'what_i_like',
                    'type'    => 'textarea',
                    'rows'    => 4,
                    'instructions' => 'One item per line.',
                    'wrapper' => ['width' => '50'],
                ],
                [
                    'key'     => 'field_67f779ff0fc7c',
                    'label'   => "What I Don't Like",
                    'name'    => 'what_i_dont_like',
                    'type'    => 'textarea',
                    'rows'    => 4,
                    'instructions' => 'One item per line.',
                    'wrapper' => ['width' => '50'],
                ],
                [
                    'key'          => 'field_67f77a0b0fc7d',
                    'label'        => 'Body Text',
                    'name'         => 'body_text',
                    'type'         => 'wysiwyg',
                    'tabs'         => 'all',
                    'toolbar'      => 'full',
                    'media_upload' => 1,
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'block',
                        'operator' => '==',
                        'value'    => 'acf/listicle-item',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Render the listicle item block.
     *
     * @param array  $block      The block settings.
     * @param string $content    The block content (empty for ACF blocks).
     * @param bool   $is_preview True during AJAX preview in editor.
     * @param int    $post_id    The post ID.
     * @return void
     */
    public function render_listicle_item_block(array $block, string $content = '', bool $is_preview = false, int $post_id = 0): void {
        $template = $this->blocks_dir . 'listicle-item/template.php';

        if (file_exists($template)) {
            include $template;
        }
    }

    /**
     * Enqueue listicle item block assets.
     *
     * @return void
     */
    public function enqueue_listicle_item_assets(): void {
        $block_url = $this->blocks_url . 'listicle-item/';
        $block_dir = $this->blocks_dir . 'listicle-item/';

        // Enqueue CSS.
        if (file_exists($block_dir . 'listicle-item.css')) {
            wp_enqueue_style(
                'erh-block-listicle-item',
                $block_url . 'listicle-item.css',
                [],
                ERH_VERSION
            );
        }
    }

    /**
     * Register the buying guide table block.
     *
     * @return void
     */
    private function register_buying_guide_table_block(): void {
        acf_register_block_type([
            'name'            => 'buying-guide-table',
            'title'           => __('Buying Guide Table', 'erh-core'),
            'description'     => __('Comparison table for buying guides with geo-aware pricing.', 'erh-core'),
            'category'        => 'formatting',
            'icon'            => 'editor-table',
            'keywords'        => ['table', 'comparison', 'buying guide', 'products', 'specs'],
            'mode'            => 'preview',
            'supports'        => [
                'align'  => ['wide', 'full'],
                'anchor' => true,
            ],
            'render_callback' => [$this, 'render_buying_guide_table_block'],
            'enqueue_assets'  => [$this, 'enqueue_buying_guide_table_assets'],
        ]);

        $this->blocks['buying-guide-table'] = [
            'name' => 'buying-guide-table',
            'dir'  => $this->blocks_dir . 'buying-guide-table/',
            'url'  => $this->blocks_url . 'buying-guide-table/',
        ];
    }

    /**
     * Register buying guide table block ACF fields.
     *
     * @return void
     */
    private function register_buying_guide_table_fields(): void {
        // Get column choices from SpecConfig (escooter for now).
        $column_choices = \ERH\Config\SpecConfig::get_table_column_choices('escooter');

        acf_add_local_field_group([
            'key'      => 'group_buying_guide_table',
            'title'    => 'Block - Buying Guide Table',
            'fields'   => [
                [
                    'key'          => 'field_bgt_products',
                    'label'        => 'Products',
                    'name'         => 'products',
                    'type'         => 'repeater',
                    'instructions' => 'Add products to compare in the table.',
                    'layout'       => 'block',
                    'button_label' => 'Add Product',
                    'min'          => 2,
                    'max'          => 10,
                    'sub_fields'   => [
                        [
                            'key'           => 'field_bgt_product',
                            'label'         => 'Product',
                            'name'          => 'product',
                            'type'          => 'post_object',
                            'post_type'     => ['products'],
                            'post_status'   => ['publish'],
                            'return_format' => 'id',
                            'ui'            => 1,
                            'wrapper'       => ['width' => '60'],
                        ],
                        [
                            'key'         => 'field_bgt_highlight',
                            'label'       => 'Highlight Text',
                            'name'        => 'highlight_text',
                            'type'        => 'text',
                            'placeholder' => 'e.g., Best for Commuters',
                            'wrapper'     => ['width' => '40'],
                        ],
                    ],
                ],
                [
                    'key'           => 'field_bgt_visible_columns',
                    'label'         => 'Visible Columns',
                    'name'          => 'visible_columns',
                    'type'          => 'checkbox',
                    'instructions'  => 'Select which specs to show as table columns.',
                    'choices'       => $column_choices,
                    'default_value' => [
                        'top_speed_tested',
                        'range_tested',
                        'battery_capacity',
                        'motor_power',
                        'weight',
                    ],
                    'layout'        => 'vertical',
                    'toggle'        => 1,
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'block',
                        'operator' => '==',
                        'value'    => 'acf/buying-guide-table',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Render the buying guide table block.
     *
     * @param array  $block      The block settings.
     * @param string $content    The block content (empty for ACF blocks).
     * @param bool   $is_preview True during AJAX preview in editor.
     * @param int    $post_id    The post ID.
     * @return void
     */
    public function render_buying_guide_table_block(array $block, string $content = '', bool $is_preview = false, int $post_id = 0): void {
        $template = $this->blocks_dir . 'buying-guide-table/template.php';

        if (file_exists($template)) {
            include $template;
        }
    }

    /**
     * Enqueue buying guide table block assets.
     *
     * @return void
     */
    public function enqueue_buying_guide_table_assets(): void {
        $block_url = $this->blocks_url . 'buying-guide-table/';
        $block_dir = $this->blocks_dir . 'buying-guide-table/';

        // Enqueue CSS.
        if (file_exists($block_dir . 'buying-guide-table.css')) {
            wp_enqueue_style(
                'erh-block-buying-guide-table',
                $block_url . 'buying-guide-table.css',
                [],
                ERH_VERSION
            );
        }

        // Enqueue JS (frontend only, not in editor).
        if (!is_admin() && file_exists($block_dir . 'buying-guide-table.js')) {
            wp_enqueue_script(
                'erh-block-buying-guide-table',
                $block_url . 'buying-guide-table.js',
                [],
                ERH_VERSION,
                true
            );
        }
    }

    /**
     * Register the callout block.
     *
     * @return void
     */
    private function register_callout_block(): void {
        acf_register_block_type([
            'name'            => 'callout',
            'title'           => __('Callout', 'erh-core'),
            'description'     => __('Styled callout box for tips, notes, warnings, and summaries.', 'erh-core'),
            'category'        => 'formatting',
            'icon'            => 'megaphone',
            'keywords'        => ['callout', 'tip', 'note', 'warning', 'summary', 'alert'],
            'mode'            => 'preview',
            'supports'        => [
                'align'  => false,
                'anchor' => true,
            ],
            'render_callback' => [$this, 'render_callout_block'],
            'enqueue_assets'  => [$this, 'enqueue_callout_assets'],
        ]);

        $this->blocks['callout'] = [
            'name' => 'callout',
            'dir'  => $this->blocks_dir . 'callout/',
            'url'  => $this->blocks_url . 'callout/',
        ];
    }

    /**
     * Register callout block ACF fields.
     *
     * @return void
     */
    private function register_callout_fields(): void {
        acf_add_local_field_group([
            'key'      => 'group_erh_callout_block',
            'title'    => 'Block - Callout',
            'fields'   => [
                [
                    'key'           => 'field_erh_callout_style',
                    'label'         => 'Style',
                    'name'          => 'callout_style',
                    'type'          => 'select',
                    'choices'       => [
                        'tip'     => 'Tip',
                        'note'    => 'Note',
                        'warning' => 'Warning',
                        'summary' => 'Summary',
                    ],
                    'default_value' => 'tip',
                    'return_format' => 'value',
                    'wrapper'       => ['width' => '50'],
                ],
                [
                    'key'         => 'field_erh_callout_title',
                    'label'       => 'Title',
                    'name'        => 'callout_title',
                    'type'        => 'text',
                    'instructions' => 'Optional. Defaults to style name if empty.',
                    'placeholder' => 'e.g., Pro Tip, Note, Warning...',
                    'wrapper'     => ['width' => '50'],
                ],
                [
                    'key'          => 'field_erh_callout_body',
                    'label'        => 'Body',
                    'name'         => 'callout_body',
                    'type'         => 'wysiwyg',
                    'tabs'         => 'all',
                    'toolbar'      => 'full',
                    'media_upload' => 0,
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'block',
                        'operator' => '==',
                        'value'    => 'acf/callout',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Render the callout block.
     *
     * @param array  $block      The block settings.
     * @param string $content    The block content (empty for ACF blocks).
     * @param bool   $is_preview True during AJAX preview in editor.
     * @param int    $post_id    The post ID.
     * @return void
     */
    public function render_callout_block(array $block, string $content = '', bool $is_preview = false, int $post_id = 0): void {
        $template = $this->blocks_dir . 'callout/template.php';

        if (file_exists($template)) {
            include $template;
        }
    }

    /**
     * Enqueue callout block assets.
     *
     * @return void
     */
    public function enqueue_callout_assets(): void {
        $block_url = $this->blocks_url . 'callout/';
        $block_dir = $this->blocks_dir . 'callout/';

        // Enqueue CSS.
        if (file_exists($block_dir . 'callout.css')) {
            wp_enqueue_style(
                'erh-block-callout',
                $block_url . 'callout.css',
                [],
                ERH_VERSION
            );
        }
    }

    /**
     * Register the greybox block.
     *
     * @return void
     */
    private function register_greybox_block(): void {
        acf_register_block_type([
            'name'            => 'greybox',
            'title'           => __('Grey Box', 'erh-core'),
            'description'     => __('Grey bordered box with icon, heading, and rich text content.', 'erh-core'),
            'category'        => 'formatting',
            'icon'            => 'admin-comments',
            'keywords'        => ['greybox', 'box', 'icon', 'heading', 'content'],
            'mode'            => 'preview',
            'supports'        => [
                'align'  => false,
                'anchor' => true,
            ],
            'render_callback' => [$this, 'render_greybox_block'],
            'enqueue_assets'  => [$this, 'enqueue_greybox_assets'],
        ]);

        $this->blocks['greybox'] = [
            'name' => 'greybox',
            'dir'  => $this->blocks_dir . 'greybox/',
            'url'  => $this->blocks_url . 'greybox/',
        ];
    }

    /**
     * Register greybox block ACF fields.
     *
     * @return void
     */
    private function register_greybox_fields(): void {
        acf_add_local_field_group([
            'key'      => 'group_erh_greybox_block',
            'title'    => 'Block - Grey Box',
            'fields'   => [
                [
                    'key'           => 'field_erh_greybox_icon',
                    'label'         => 'Icon',
                    'name'          => 'greybox_icon',
                    'type'          => 'select',
                    'choices'       => [
                        'x'     => 'X / Close',
                        'info'  => 'Info',
                        'zap'   => 'Lightning',
                        'check' => 'Check',
                    ],
                    'default_value' => 'x',
                    'return_format' => 'value',
                    'wrapper'       => ['width' => '50'],
                ],
                [
                    'key'     => 'field_erh_greybox_heading',
                    'label'   => 'Heading',
                    'name'    => 'greybox_heading',
                    'type'    => 'text',
                    'wrapper' => ['width' => '50'],
                ],
                [
                    'key'          => 'field_erh_greybox_body',
                    'label'        => 'Body',
                    'name'         => 'greybox_body',
                    'type'         => 'wysiwyg',
                    'tabs'         => 'all',
                    'toolbar'      => 'full',
                    'media_upload' => 0,
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'block',
                        'operator' => '==',
                        'value'    => 'acf/greybox',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Render the greybox block.
     *
     * @param array  $block      The block settings.
     * @param string $content    The block content (empty for ACF blocks).
     * @param bool   $is_preview True during AJAX preview in editor.
     * @param int    $post_id    The post ID.
     * @return void
     */
    public function render_greybox_block(array $block, string $content = '', bool $is_preview = false, int $post_id = 0): void {
        $template = $this->blocks_dir . 'greybox/template.php';

        if (file_exists($template)) {
            include $template;
        }
    }

    /**
     * Enqueue greybox block assets.
     *
     * @return void
     */
    public function enqueue_greybox_assets(): void {
        $block_url = $this->blocks_url . 'greybox/';
        $block_dir = $this->blocks_dir . 'greybox/';

        // Enqueue CSS.
        if (file_exists($block_dir . 'greybox.css')) {
            wp_enqueue_style(
                'erh-block-greybox',
                $block_url . 'greybox.css',
                [],
                ERH_VERSION
            );
        }
    }
}
