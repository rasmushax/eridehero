<?php
/**
 * Full Specifications Component
 *
 * Displays comprehensive product specifications organized by category.
 * Adapts to different product types (e-scooter, e-bike, etc.)
 *
 * @package ERideHero
 *
 * @var array $args {
 *     @type int    $product_id   The product ID.
 *     @type string $product_type The product type.
 * }
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$product_id   = $args['product_id'] ?? 0;
$product_type = $args['product_type'] ?? '';

if ( ! $product_id ) {
    return;
}

// Get spec groups based on product type.
$spec_groups = erh_get_spec_groups( $product_id, $product_type );

// Check if we have any specs to show.
if ( empty( $spec_groups ) ) {
    return;
}
?>

<section class="review-section" id="full-specs">
    <h2 class="review-section-title">Full specifications</h2>
    <div class="review-full-specs">
        <?php
        $first = true;
        foreach ( $spec_groups as $group_key => $group ) :
            ?>
            <details class="review-specs-group" <?php echo $first ? 'open' : ''; ?>>
                <summary><?php echo esc_html( $group['label'] ); ?></summary>
                <table class="review-specs-table">
                    <?php foreach ( $group['specs'] as $spec ) : ?>
                        <tr>
                            <td><?php echo esc_html( $spec['label'] ); ?></td>
                            <td><?php echo esc_html( $spec['value'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </details>
            <?php
            $first = false;
        endforeach;
        ?>
    </div>
</section>
