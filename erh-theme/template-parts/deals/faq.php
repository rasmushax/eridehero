<?php
/**
 * Deals FAQ Section
 *
 * Renders FAQ accordion from ACF repeater field `deals_faq`.
 * Uses the same HTML pattern as erh-accordion block for consistent
 * styling and keyboard navigation.
 *
 * Two-column layout on desktop, all items closed by default.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$faq_items = get_field( 'deals_faq', 'option' );

if ( empty( $faq_items ) || ! is_array( $faq_items ) ) {
    return;
}

// Filter out incomplete items.
$faq_items = array_filter( $faq_items, function ( $item ) {
    return ! empty( $item['question'] ) && ! empty( $item['answer'] );
} );
$faq_items = array_values( $faq_items );

if ( empty( $faq_items ) ) {
    return;
}

// Enqueue accordion assets.
$block_url = plugins_url( 'includes/blocks/accordion/', ERH_PLUGIN_FILE );
$block_dir = ERH_PLUGIN_DIR . 'includes/blocks/accordion/';

if ( file_exists( $block_dir . 'accordion.css' ) ) {
    wp_enqueue_style( 'erh-block-accordion', $block_url . 'accordion.css', [], ERH_VERSION );
}
if ( file_exists( $block_dir . 'accordion.js' ) ) {
    wp_enqueue_script( 'erh-block-accordion', $block_url . 'accordion.js', [], ERH_VERSION, true );
}

$accordion_id = 'deals-faq-' . get_the_ID();

// Split items into two columns for desktop.
$total      = count( $faq_items );
$split      = (int) ceil( $total / 2 );
$col1_items = array_slice( $faq_items, 0, $split );
$col2_items = array_slice( $faq_items, $split );
?>

<section class="section deals-faq">
    <div class="container">
        <h2 class="deals-faq-title"><?php esc_html_e( 'Frequently Asked Questions', 'erh' ); ?></h2>

        <div class="deals-faq-grid">
            <?php
            $col_index = 0;
            foreach ( [ $col1_items, $col2_items ] as $col_items ) :
                if ( empty( $col_items ) ) {
                    continue;
                }
                $col_id = $accordion_id . '-col-' . $col_index;
            ?>
                <div class="erh-accordion" id="<?php echo esc_attr( $col_id ); ?>" data-erh-accordion>
                    <?php foreach ( $col_items as $index => $item ) :
                        $item_id   = $col_id . '-item-' . $index;
                        $header_id = $item_id . '-header';
                        $panel_id  = $item_id . '-panel';
                    ?>
                        <div class="erh-accordion-item" data-accordion-item>
                            <button
                                type="button"
                                id="<?php echo esc_attr( $header_id ); ?>"
                                class="erh-accordion-header"
                                aria-expanded="false"
                                aria-controls="<?php echo esc_attr( $panel_id ); ?>"
                                data-accordion-trigger
                            >
                                <span class="erh-accordion-title">
                                    <?php echo esc_html( $item['question'] ); ?>
                                </span>
                                <svg class="erh-accordion-icon icon" aria-hidden="true" focusable="false">
                                    <use href="#icon-chevron-down"></use>
                                </svg>
                            </button>
                            <div
                                id="<?php echo esc_attr( $panel_id ); ?>"
                                class="erh-accordion-panel"
                                role="region"
                                aria-labelledby="<?php echo esc_attr( $header_id ); ?>"
                            >
                                <?php echo wp_kses_post( $item['answer'] ); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php
                $col_index++;
            endforeach;
            ?>
        </div>
    </div>
</section>

<?php
// FAQPage schema for rich results.
$faq_schema_items = [];
foreach ( $faq_items as $item ) {
    $faq_schema_items[] = [
        '@type'          => 'Question',
        'name'           => $item['question'],
        'acceptedAnswer' => [
            '@type' => 'Answer',
            'text'  => wp_strip_all_tags( $item['answer'] ),
        ],
    ];
}

if ( ! empty( $faq_schema_items ) ) :
?>
<script type="application/ld+json">
<?php echo wp_json_encode( [
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => $faq_schema_items,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?>
</script>
<?php endif; ?>
