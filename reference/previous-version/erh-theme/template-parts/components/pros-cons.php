<?php
/**
 * Pros & Cons Component
 *
 * Displays two-column pros and cons lists.
 *
 * @package ERideHero
 *
 * Expected $args:
 *   'pros'       => string|array - Pros (newline-separated string or array)
 *   'cons'       => string|array - Cons (newline-separated string or array)
 *   'pros_title' => string       - Pros heading (default: 'What I like')
 *   'cons_title' => string       - Cons heading (default: "What I don't like")
 *   'section_id' => string       - HTML id for anchor linking (default: 'pros-cons')
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get arguments with defaults
$pros       = $args['pros'] ?? '';
$cons       = $args['cons'] ?? '';
$pros_title = $args['pros_title'] ?? 'What I like';
$cons_title = $args['cons_title'] ?? "What I don't like";
$section_id = $args['section_id'] ?? 'pros-cons';

/**
 * Parse items from string or array
 *
 * @param string|array $items
 * @return array
 */
function erh_parse_list_items( $items ): array {
    if ( is_array( $items ) ) {
        return array_filter( array_map( 'trim', $items ) );
    }

    if ( is_string( $items ) && ! empty( $items ) ) {
        $lines = preg_split( '/\r\n|\r|\n/', $items );
        return array_filter( array_map( 'trim', $lines ) );
    }

    return array();
}

$pros_items = erh_parse_list_items( $pros );
$cons_items = erh_parse_list_items( $cons );

// Bail if no items
if ( empty( $pros_items ) && empty( $cons_items ) ) {
    return;
}
?>

<section class="content-section" id="<?php echo esc_attr( $section_id ); ?>">
    <div class="pros-cons">
        <?php if ( ! empty( $pros_items ) ) : ?>
            <div class="pros">
                <h3 class="pros-title"><?php echo esc_html( $pros_title ); ?></h3>
                <ul class="pros-list">
                    <?php foreach ( $pros_items as $item ) : ?>
                        <li>
                            <svg class="icon" aria-hidden="true"><use href="#icon-check"></use></svg>
                            <?php echo esc_html( $item ); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $cons_items ) ) : ?>
            <div class="cons">
                <h3 class="cons-title"><?php echo esc_html( $cons_title ); ?></h3>
                <ul class="cons-list">
                    <?php foreach ( $cons_items as $item ) : ?>
                        <li>
                            <svg class="icon" aria-hidden="true"><use href="#icon-x"></use></svg>
                            <?php echo esc_html( $item ); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</section>
