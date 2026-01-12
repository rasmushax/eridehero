<?php
/**
 * Quick Take Component
 *
 * Displays the review quick take/verdict section with score badge.
 *
 * @package ERideHero
 *
 * Expected $args:
 *   'text'       => string - Quick take text (can contain paragraphs)
 *   'score'      => float  - Rating score (0-10)
 *   'title'      => string - Section title (default: 'Quick take')
 *   'section_id' => string - HTML id for anchor linking (default: 'quick-take')
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get arguments with defaults
$text       = $args['text'] ?? '';
$score      = $args['score'] ?? null;
$title      = $args['title'] ?? 'Quick take';
$section_id = $args['section_id'] ?? 'quick-take';

// Bail if no content
if ( empty( $text ) ) {
    return;
}

// Get score label and attribute
$score_label = $score ? erh_get_score_label( (float) $score ) : '';
$score_attr  = $score ? erh_get_score_attr( (float) $score ) : '';
?>

<section class="review-verdict" id="<?php echo esc_attr( $section_id ); ?>">
    <div class="review-verdict-header">
        <h2 class="review-verdict-title"><?php echo esc_html( $title ); ?></h2>

        <?php if ( $score ) : ?>
            <div
                class="review-verdict-score"
                data-score="<?php echo esc_attr( $score_attr ); ?>"
                data-tooltip="<?php esc_attr_e( 'ERideHero Score based on performance, value, build quality and features', 'erh' ); ?>"
                data-tooltip-trigger="click"
                data-tooltip-position="bottom"
            >
                <span class="review-verdict-value"><?php echo esc_html( number_format( $score, 1 ) ); ?></span>
                <span class="review-verdict-label"><?php echo esc_html( $score_label ); ?></span>
                <svg class="icon" aria-hidden="true"><use href="#icon-info"></use></svg>
            </div>
        <?php endif; ?>
    </div>

    <div class="review-verdict-text">
        <?php
        // Convert line breaks to paragraphs
        $paragraphs = preg_split( '/\n\s*\n/', trim( $text ) );
        foreach ( $paragraphs as $paragraph ) {
            $paragraph = trim( $paragraph );
            if ( $paragraph ) {
                echo '<p>' . wp_kses_post( nl2br( $paragraph ) ) . '</p>';
            }
        }
        ?>
    </div>
</section>
