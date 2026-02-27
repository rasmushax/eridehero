<?php
/**
 * Shortlist Block Template
 *
 * Horizontal product cards for buying guide "Our Top Picks" sections.
 * Supports optional grouping (e.g., by weight class) and geo-aware pricing.
 *
 * @package ERH\Blocks
 *
 * @var array  $block      The block settings and attributes.
 * @var string $content    The block inner HTML (empty for ACF blocks).
 * @var bool   $is_preview True during AJAX preview in editor.
 * @var int    $post_id    The post ID this block is saved to.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get block data.
$title       = get_field( 'shortlist_title' ) ?: 'Our Top Picks';
$numbering   = get_field( 'shortlist_numbering' );
$use_groups  = get_field( 'shortlist_use_groups' );

// Build unified $sections array.
// Each section: ['heading' => string|null, 'items' => array]
$sections = [];

if ( $use_groups ) {
    $groups = get_field( 'shortlist_groups' );
    if ( ! empty( $groups ) && is_array( $groups ) ) {
        foreach ( $groups as $group ) {
            $sections[] = [
                'heading' => $group['group_heading'] ?? '',
                'items'   => $group['group_items'] ?? [],
            ];
        }
    }
} else {
    $items = get_field( 'shortlist_items' );
    if ( ! empty( $items ) && is_array( $items ) ) {
        $sections[] = [
            'heading' => null,
            'items'   => $items,
        ];
    }
}

// Early return if no items.
$total_items = 0;
foreach ( $sections as $section ) {
    $total_items += count( $section['items'] ?? [] );
}

if ( $total_items === 0 ) {
    if ( $is_preview ) {
        echo '<div class="erh-shortlist-empty">';
        echo '<p>' . esc_html__( 'Add items to see the shortlist preview.', 'erh-core' ) . '</p>';
        echo '</div>';
    }
    return;
}

// Build class list.
$classes = [ 'erh-shortlist' ];
if ( ! empty( $block['className'] ) ) {
    $classes[] = $block['className'];
}

// Generate unique ID.
$block_id = 'shortlist-' . ( $block['id'] ?? uniqid() );
if ( ! empty( $block['anchor'] ) ) {
    $block_id = $block['anchor'];
}

// Global item counter for numbering across groups.
$item_number = 0;
?>
<div
    id="<?php echo esc_attr( $block_id ); ?>"
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-shortlist
>
    <?php if ( $title ) : ?>
        <h2 class="erh-shortlist__title"><?php echo esc_html( $title ); ?></h2>
    <?php endif; ?>

    <?php foreach ( $sections as $section ) : ?>
        <?php if ( ! empty( $section['heading'] ) ) : ?>
            <div class="erh-shortlist__group-heading">
                <?php echo esc_html( $section['heading'] ); ?>
            </div>
        <?php endif; ?>

        <?php foreach ( $section['items'] as $item ) :
            $item_number++;
            $product_id = $item['item_product'] ?? 0;

            if ( empty( $product_id ) ) {
                continue;
            }

            // Product data.
            $product_name = get_the_title( $product_id );
            $product_url  = get_permalink( $product_id );
            $product_type = erh_get_product_type( $product_id );
            $category_key = erh_get_category_key( $product_type );

            // Review URL.
            $review_data = get_field( 'review', $product_id );
            $review_url  = null;
            if ( ! empty( $review_data['review_post'] ) ) {
                $review_post = get_post( $review_data['review_post'] );
                if ( $review_post ) {
                    $review_url = get_permalink( $review_post->ID );
                }
            }

            // Image: override or featured image.
            $image_override = $item['item_image_override'] ?? null;
            $image_id       = $image_override ? $image_override['ID'] : get_post_thumbnail_id( $product_id );

            // Label (e.g., "Best Overall").
            $label = $item['item_label'] ?? '';

            // Description (WYSIWYG).
            $description = $item['item_description'] ?? '';

            // Resolve specs (max 4).
            $specs      = [];
            $spec_rows  = $item['item_specs'] ?? [];
            if ( ! empty( $spec_rows ) && is_array( $spec_rows ) ) {
                foreach ( $spec_rows as $spec_row ) {
                    $mode = $spec_row['spec_mode'] ?? 'preset';

                    if ( 'manual' === $mode ) {
                        $manual_label = trim( $spec_row['manual_label'] ?? '' );
                        $manual_value = trim( $spec_row['manual_value'] ?? '' );
                        if ( $manual_label && $manual_value ) {
                            $specs[] = [
                                'label' => $manual_label,
                                'value' => $manual_value,
                            ];
                        }
                    } else {
                        $preset_key = $spec_row['spec_preset'] ?? '';
                        if ( $preset_key ) {
                            $resolved = erh_resolve_preset_spec( $preset_key, $product_id, $category_key );
                            if ( $resolved ) {
                                $specs[] = [
                                    'label' => $resolved['label'],
                                    'value' => $resolved['value'],
                                ];
                            }
                        }
                    }

                    if ( count( $specs ) >= 4 ) {
                        break;
                    }
                }
            }

            // Check if product has pricing (for buy button hydration).
            $has_pricing = ! $is_preview && erh_product_has_pricing( $product_id );

            // JS data for hydration.
            $js_data = $has_pricing ? wp_json_encode( [ 'productId' => $product_id ] ) : '';
        ?>
            <div
                class="erh-shortlist__item"
                <?php if ( $js_data ) : ?>data-shortlist-product='<?php echo esc_attr( $js_data ); ?>'<?php endif; ?>
            >
                <?php if ( $numbering || $label ) : ?>
                    <div class="erh-shortlist__item-meta">
                        <?php if ( $numbering ) : ?>
                            <span class="erh-shortlist__number">#<?php echo esc_html( $item_number ); ?></span>
                        <?php endif; ?>
                        <?php if ( $label ) : ?>
                            <span class="erh-shortlist__label"><?php echo esc_html( $label ); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="erh-shortlist__item-body">
                    <?php if ( $image_id ) : ?>
                        <div class="erh-shortlist__image">
                            <?php echo wp_get_attachment_image( $image_id, 'medium', false, [
                                'class'   => 'erh-shortlist__img',
                                'loading' => 'lazy',
                            ] ); ?>
                        </div>
                    <?php endif; ?>

                    <div class="erh-shortlist__info">
                        <h3 class="erh-shortlist__name">
                            <a href="<?php echo esc_url( $product_url ); ?>"><?php echo esc_html( $product_name ); ?></a>
                        </h3>

                        <?php if ( ! empty( $specs ) ) : ?>
                            <div class="erh-shortlist__specs">
                                <?php foreach ( $specs as $i => $spec ) : ?>
                                    <?php if ( $i > 0 ) : ?>
                                        <span class="erh-shortlist__specs-sep" aria-hidden="true">&middot;</span>
                                    <?php endif; ?>
                                    <span class="erh-shortlist__spec"><?php echo esc_html( $spec['value'] ); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( $description ) : ?>
                            <div class="erh-shortlist__desc">
                                <?php echo wp_kses_post( $description ); ?>
                            </div>
                        <?php endif; ?>

                        <div class="erh-shortlist__actions">
                            <?php if ( $has_pricing ) : ?>
                                <a href="#" target="_blank" rel="sponsored noopener" class="btn btn-primary btn-sm erh-shortlist__buy" data-shortlist-buy>
                                    <span data-shortlist-buy-text style="display: none;"><?php esc_html_e( 'Check Price', 'erh-core' ); ?></span>
                                    <svg class="icon" aria-hidden="true" data-shortlist-buy-icon style="display: none;"><use href="#icon-external-link"></use></svg>
                                    <svg class="spinner" viewBox="0 0 24 24" data-shortlist-spinner><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-linecap="round"/></svg>
                                </a>
                            <?php endif; ?>

                            <?php if ( $review_url ) : ?>
                                <a href="<?php echo esc_url( $review_url ); ?>" class="erh-shortlist__review-link">
                                    <?php esc_html_e( 'Read Review', 'erh-core' ); ?>
                                    <svg class="icon" aria-hidden="true"><use href="#icon-arrow-right"></use></svg>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>
