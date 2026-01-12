<?php
/**
 * Table of Contents Component
 *
 * Reusable TOC component for sidebar navigation.
 * Works with toc.js for scroll-spy and mobile behavior.
 *
 * @package ERideHero
 *
 * Expected $args:
 *   'items' => array - TOC items from erh_get_toc_items()
 *                      Each item: ['id' => string, 'label' => string, 'children' => array (optional)]
 *   'title' => string - Section title (default: 'On this page')
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get arguments with defaults
$items = $args['items'] ?? array();
$title = $args['title'] ?? 'On this page';

// Bail if no items
if ( empty( $items ) ) {
    return;
}
?>

<nav class="toc" aria-label="Table of contents">
    <h3 class="toc-title"><?php echo esc_html( $title ); ?></h3>
    <ul class="toc-list">
        <?php foreach ( $items as $item ) : ?>
            <li>
                <a href="#<?php echo esc_attr( $item['id'] ); ?>" class="toc-link">
                    <?php echo esc_html( $item['label'] ); ?>
                </a>

                <?php if ( ! empty( $item['children'] ) ) : ?>
                    <ul class="toc-sublist">
                        <?php foreach ( $item['children'] as $child ) : ?>
                            <li>
                                <a href="#<?php echo esc_attr( $child['id'] ); ?>" class="toc-link">
                                    <?php echo esc_html( $child['label'] ); ?>
                                </a>

                                <?php
                                // Support third-level nesting if needed
                                if ( ! empty( $child['children'] ) ) :
                                    ?>
                                    <ul class="toc-sublist">
                                        <?php foreach ( $child['children'] as $grandchild ) : ?>
                                            <li>
                                                <a href="#<?php echo esc_attr( $grandchild['id'] ); ?>" class="toc-link">
                                                    <?php echo esc_html( $grandchild['label'] ); ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
