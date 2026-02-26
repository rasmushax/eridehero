<?php
/**
 * Buying Guide Table Block Template
 *
 * Comparison table for buying guides with geo-aware pricing.
 * Products as columns, specs as rows, price row at bottom hydrated via JS.
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

use ERH\Config\SpecConfig;

// Get block data.
$products        = get_field( 'products' ) ?: [];
$visible_columns = get_field( 'visible_columns' ) ?: [];

// Early return if no products.
if ( empty( $products ) ) {
    if ( $is_preview ) {
        echo '<div class="bgt-empty">';
        echo '<p>' . esc_html__( 'Add products to see the comparison table.', 'erh-core' ) . '</p>';
        echo '</div>';
    }
    return;
}

// Filter out empty product rows.
$products = array_filter( $products, function( $row ) {
    return ! empty( $row['product'] );
} );

if ( empty( $products ) ) {
    if ( $is_preview ) {
        echo '<div class="bgt-empty">';
        echo '<p>' . esc_html__( 'Select products to compare.', 'erh-core' ) . '</p>';
        echo '</div>';
    }
    return;
}

// Build class list.
$classes = [ 'buying-guide-table' ];
if ( ! empty( $block['className'] ) ) {
    $classes[] = $block['className'];
}
if ( ! empty( $block['align'] ) ) {
    $classes[] = 'align' . $block['align'];
}

// Generate unique ID.
$block_id = 'bgt-' . ( $block['id'] ?? uniqid() );
if ( ! empty( $block['anchor'] ) ) {
    $block_id = $block['anchor'];
}

// Build product data array.
$product_data = [];
foreach ( $products as $row ) {
    $pid = $row['product'];
    if ( ! $pid ) {
        continue;
    }

    // Get product type and category key.
    $product_type = erh_get_product_type( $pid );
    $category_key = erh_get_category_key( $product_type );

    // Get cached product data.
    $cache_data = erh_get_product_cache_data( $pid );
    $specs      = [];

    if ( $cache_data && ! empty( $cache_data['specs'] ) ) {
        $specs = is_array( $cache_data['specs'] )
            ? $cache_data['specs']
            : maybe_unserialize( $cache_data['specs'] );
    }

    // Get image (featured image preferred, big_thumbnail as fallback).
    $image_id = get_post_thumbnail_id( $pid ) ?: get_field( 'big_thumbnail', $pid );

    // Check if product has pricing.
    $has_pricing = erh_product_has_pricing( $pid );

    $product_data[] = [
        'id'             => $pid,
        'name'           => get_the_title( $pid ),
        'url'            => get_permalink( $pid ),
        'highlight'      => $row['highlight_text'] ?? '',
        'image_id'       => $image_id,
        'specs'          => $specs,
        'category_key'   => $category_key,
        'has_pricing'    => $has_pricing,
        'nested_wrapper' => erh_get_specs_wrapper_key( $category_key ),
    ];
}

if ( empty( $product_data ) ) {
    return;
}

// Validate all products share the same category.
$categories = array_unique( array_column( $product_data, 'category_key' ) );
if ( count( $categories ) > 1 ) {
    if ( $is_preview ) {
        echo '<div class="bgt-empty">';
        echo '<p>' . esc_html__( 'All products must be from the same category.', 'erh-core' ) . '</p>';
        echo '</div>';
    }
    return;
}

// Use category from first product for spec definitions.
$primary_category = $categories[0];

// Build spec rows based on visible columns.
// Tooltips are centralized in SpecConfig - no hardcoded overrides needed.
$spec_rows = [];
foreach ( $visible_columns as $spec_key ) {
    $spec_def = SpecConfig::get_spec_definition( $primary_category, $spec_key );
    if ( ! $spec_def ) {
        continue;
    }

    $spec_rows[] = [
        'key'     => $spec_key,
        'label'   => $spec_def['label'],
        'unit'    => $spec_def['unit'] ?? '',
        'def'     => $spec_def,
        'tooltip' => $spec_def['tooltip'] ?? null,
    ];
}

// Check if any product has pricing.
$any_has_pricing = false;
foreach ( $product_data as $p ) {
    if ( $p['has_pricing'] ) {
        $any_has_pricing = true;
        break;
    }
}

// Data for JavaScript hydration.
$js_data = wp_json_encode( [
    'productIds' => array_column( $product_data, 'id' ),
] );
?>
<div
    id="<?php echo esc_attr( $block_id ); ?>"
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-buying-guide-table='<?php echo esc_attr( $js_data ); ?>'
>
    <div class="bgt-scroll-container">
        <table class="bgt-table">
            <thead>
                <tr>
                    <!-- Sticky column header (empty for spec labels) -->
                    <th class="bgt-col-label bgt-sticky"></th>

                    <!-- Product headers -->
                    <?php foreach ( $product_data as $product ) : ?>
                        <th class="bgt-col-product">
                            <div class="bgt-product-header">
                                <?php if ( $product['image_id'] ) : ?>
                                    <a href="<?php echo esc_url( $product['url'] ); ?>" class="bgt-product-thumb">
                                        <?php echo wp_get_attachment_image( $product['image_id'], 'thumbnail', false, [
                                            'class'   => 'bgt-thumb-img',
                                            'loading' => 'lazy',
                                        ] ); ?>
                                    </a>
                                <?php endif; ?>
                                <div class="bgt-product-info">
                                    <a href="<?php echo esc_url( $product['url'] ); ?>" class="bgt-product-name">
                                        <?php echo esc_html( $product['name'] ); ?>
                                    </a>
                                    <?php if ( $product['highlight'] ) : ?>
                                        <span class="bgt-product-highlight"><?php echo esc_html( $product['highlight'] ); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $spec_rows as $spec ) : ?>
                    <tr>
                        <td class="bgt-col-label bgt-sticky">
                            <div class="bgt-label-inner">
                                <?php echo esc_html( $spec['label'] ); ?>
                                <?php if ( $spec['tooltip'] ) : ?>
                                    <span class="info-trigger" data-tooltip="<?php echo esc_attr( $spec['tooltip'] ); ?>" data-tooltip-trigger="click">
                                        <?php erh_the_icon( 'info', '', [ 'width' => '14', 'height' => '14' ] ); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <?php foreach ( $product_data as $product ) : ?>
                            <td class="bgt-col-value">
                                <?php
                                $value = erh_get_spec_from_cache(
                                    $product['specs'],
                                    $spec['key'],
                                    $product['nested_wrapper']
                                );
                                $formatted = erh_format_spec_value( $value, $spec['def'] );

                                if ( $formatted !== '' && $formatted !== 'N/A' ) {
                                    echo esc_html( $formatted );
                                } else {
                                    echo '<span class="bgt-na">&mdash;</span>';
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>

                <?php if ( $any_has_pricing && ! $is_preview ) : ?>
                    <!-- Price row (hydrated via JS) -->
                    <tr class="bgt-row-price">
                        <td class="bgt-col-label bgt-sticky">
                            <?php esc_html_e( 'Price', 'erh-core' ); ?>
                        </td>
                        <?php foreach ( $product_data as $product ) : ?>
                            <td class="bgt-col-price" data-product-id="<?php echo esc_attr( $product['id'] ); ?>">
                                <?php if ( $product['has_pricing'] ) : ?>
                                    <span class="skeleton skeleton-text" style="width: 80px;"></span>
                                <?php else : ?>
                                    <span class="bgt-na">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
