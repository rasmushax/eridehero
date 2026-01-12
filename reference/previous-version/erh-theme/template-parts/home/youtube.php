<?php
/**
 * Homepage YouTube Section
 *
 * Displays latest videos from YouTube channel, fetched via cron job.
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get cached videos from cron job
$videos = [];
if ( class_exists( 'ERH\\Cron\\YouTubeSyncJob' ) ) {
    $videos = \ERH\Cron\YouTubeSyncJob::get_cached_videos();
}

// Get ACF options
$channel_url = get_field( 'youtube_channel_url', 'option' ) ?: 'https://youtube.com/@eridehero';
$view_stat   = get_field( 'youtube_view_stat', 'option' ) ?: '800K+ views';

// Don't show section if no videos and no fallback
if ( empty( $videos ) ) {
    return;
}
?>

<section class="section youtube-section scroll-section">
    <div class="container">
        <div class="section-header">
            <div class="youtube-heading">
                <svg class="youtube-logo" aria-hidden="true"><use href="#icon-youtube-logo"></use></svg>
                <h2><?php esc_html_e( 'YouTube', 'erh' ); ?></h2>
                <span class="youtube-stat"><?php echo esc_html( $view_stat ); ?></span>
            </div>
            <a href="<?php echo esc_url( $channel_url ); ?>" class="btn btn-youtube" target="_blank" rel="noopener noreferrer">
                <?php esc_html_e( 'Subscribe', 'erh' ); ?>
                <?php erh_the_icon( 'external-link' ); ?>
            </a>
        </div>

        <div class="youtube-grid scroll-container">
            <?php foreach ( $videos as $index => $video ) :
                $is_hero = ( 0 === $index );
                $card_class = $is_hero ? 'youtube-card youtube-card-hero scroll-item' : 'youtube-card scroll-item';
            ?>
                <a href="<?php echo esc_url( $video['url'] ); ?>" class="<?php echo esc_attr( $card_class ); ?>" target="_blank" rel="noopener noreferrer">
                    <?php if ( ! empty( $video['thumbnail'] ) ) : ?>
                        <img src="<?php echo esc_url( $video['thumbnail'] ); ?>" alt="<?php echo esc_attr( $video['title'] ); ?>" loading="lazy">
                    <?php endif; ?>
                    <div class="youtube-card-overlay"></div>
                    <div class="youtube-play">
                        <?php erh_the_icon( 'play' ); ?>
                    </div>
                    <h3 class="youtube-card-title"><?php echo esc_html( $video['title'] ); ?></h3>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
