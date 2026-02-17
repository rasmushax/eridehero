<?php
/**
 * Template Name: About
 *
 * About page with hero, stats, approach sections, team, and bottom CTA.
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

// Hero fields
$hero_eyebrow   = get_field( 'about_hero_eyebrow', 'option' ) ?: 'About ERideHero';
$hero_title      = get_field( 'about_hero_title', 'option' ) ?: 'The data-driven guide';
$hero_highlight  = get_field( 'about_hero_title_highlight', 'option' ) ?: 'to electric rides';
$hero_subtitle   = get_field( 'about_hero_subtitle', 'option' ) ?: 'In-depth reviews backed by real performance data. Price tracking, comparison tools, and buying guides to help you find the right ride.';

// Stats
$stats = get_field( 'about_stats', 'option' );
if ( empty( $stats ) || ! is_array( $stats ) ) {
    $stats = array(
        array( 'value' => '2019', 'label' => 'Year founded' ),
        array( 'value' => '127', 'label' => 'Products tested' ),
        array( 'value' => '8.5K+', 'label' => 'Miles ridden' ),
        array( 'value' => '3', 'label' => 'Contributors' ),
    );
}

// Approach sections
$sections = get_field( 'about_sections', 'option' );

// Team
$team_heading    = get_field( 'about_team_heading', 'option' ) ?: 'The experts behind ERideHero';
$team_subheading = get_field( 'about_team_subheading', 'option' ) ?: 'Real-world riding experience and thousands of miles logged across every category.';
$team_members    = get_field( 'about_team_members', 'option' );
?>

    <!-- HERO SECTION -->
    <section class="about-hero">
        <div class="about-hero-grid" aria-hidden="true"></div>
        <div class="container">
            <div class="about-hero-content">
                <div class="about-hero-eyebrow"><?php echo esc_html( $hero_eyebrow ); ?></div>
                <h1><?php echo esc_html( $hero_title ); ?><br><span><?php echo esc_html( $hero_highlight ); ?></span></h1>
                <p class="about-hero-subtitle"><?php echo esc_html( $hero_subtitle ); ?></p>
            </div>
        </div>
    </section>

    <!-- STATS BAR -->
    <?php if ( ! empty( $stats ) ) : ?>
    <section class="about-stats">
        <div class="container">
            <div class="about-stats-grid">
                <?php foreach ( $stats as $stat ) : ?>
                    <div class="about-stat">
                        <div class="about-stat-value"><?php echo esc_html( $stat['value'] ); ?></div>
                        <div class="about-stat-label"><?php echo esc_html( $stat['label'] ); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- APPROACH SECTIONS -->
    <?php if ( ! empty( $sections ) && is_array( $sections ) ) : ?>
        <?php foreach ( $sections as $section ) :
            $is_flipped = ! empty( $section['flipped'] );
            $has_link   = ! empty( $section['link_text'] ) && ! empty( $section['link_url'] );
            $image      = $section['image'] ?? null;
        ?>
        <section class="about-approach">
            <div class="container">
                <div class="about-approach-grid<?php echo $is_flipped ? ' about-approach-grid--flipped' : ''; ?>">
                    <div class="about-approach-content">
                        <?php if ( ! empty( $section['eyebrow'] ) ) : ?>
                            <div class="about-approach-eyebrow"><?php echo esc_html( $section['eyebrow'] ); ?></div>
                        <?php endif; ?>

                        <?php if ( ! empty( $section['heading'] ) ) : ?>
                            <h2><?php echo esc_html( $section['heading'] ); ?></h2>
                        <?php endif; ?>

                        <?php if ( ! empty( $section['body'] ) ) : ?>
                            <?php echo wp_kses_post( $section['body'] ); ?>
                        <?php endif; ?>

                        <?php if ( $has_link ) : ?>
                            <a href="<?php echo esc_url( $section['link_url'] ); ?>" class="about-approach-link">
                                <?php echo esc_html( $section['link_text'] ); ?>
                                <?php erh_the_icon( 'arrow-right' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if ( $image ) : ?>
                        <div class="about-approach-image">
                            <img src="<?php echo esc_url( $image['sizes']['large'] ?? $image['url'] ); ?>"
                                 alt="<?php echo esc_attr( $image['alt'] ?? '' ); ?>"
                                 width="<?php echo esc_attr( $image['sizes']['large-width'] ?? $image['width'] ?? '' ); ?>"
                                 height="<?php echo esc_attr( $image['sizes']['large-height'] ?? $image['height'] ?? '' ); ?>"
                                 loading="lazy">
                        </div>
                    <?php else : ?>
                        <div class="about-approach-image"></div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- TEAM SECTION -->
    <?php if ( ! empty( $team_members ) && is_array( $team_members ) ) : ?>
    <section class="about-team">
        <div class="container">
            <div class="about-team-header">
                <div class="about-approach-eyebrow"><?php esc_html_e( 'Meet the team', 'erh' ); ?></div>
                <h2><?php echo esc_html( $team_heading ); ?></h2>
                <p><?php echo esc_html( $team_subheading ); ?></p>
            </div>

            <div class="about-team-grid">
                <?php foreach ( $team_members as $member ) :
                    $photo = $member['photo'] ?? null;
                    $member_socials = array();
                    $social_platforms = array( 'youtube', 'instagram', 'tiktok', 'facebook', 'twitter', 'linkedin' );
                    foreach ( $social_platforms as $p ) {
                        if ( ! empty( $member[ "social_{$p}" ] ) ) {
                            $member_socials[ $p ] = $member[ "social_{$p}" ];
                        }
                    }
                ?>
                <div class="about-team-card">
                    <div class="about-team-image">
                        <?php if ( $photo ) : ?>
                            <img src="<?php echo esc_url( $photo['sizes']['medium'] ?? $photo['url'] ); ?>"
                                 alt="<?php echo esc_attr( $member['name'] ?? '' ); ?>"
                                 width="<?php echo esc_attr( $photo['sizes']['medium-width'] ?? $photo['width'] ?? '' ); ?>"
                                 height="<?php echo esc_attr( $photo['sizes']['medium-height'] ?? $photo['height'] ?? '' ); ?>"
                                 loading="lazy">
                        <?php endif; ?>
                    </div>
                    <div class="about-team-info">
                        <?php if ( ! empty( $member_socials ) ) : ?>
                            <div class="about-team-socials">
                                <?php foreach ( $member_socials as $platform => $url ) :
                                    $label = esc_attr( $member['name'] ?? '' ) . ' on ' . ucfirst( $platform );
                                ?>
                                    <a href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( $label ); ?>" target="_blank" rel="noopener">
                                        <?php erh_the_icon( $platform ); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $member['name'] ) ) : ?>
                            <h3><?php echo esc_html( $member['name'] ); ?></h3>
                        <?php endif; ?>

                        <?php if ( ! empty( $member['role'] ) ) : ?>
                            <span class="about-team-role"><?php echo esc_html( $member['role'] ); ?></span>
                        <?php endif; ?>

                        <?php if ( ! empty( $member['bio'] ) ) : ?>
                            <p><?php echo esc_html( $member['bio'] ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- BOTTOM SECTION -->
    <section class="about-bottom">
        <div class="container">
            <div class="about-bottom-grid">
                <div class="about-bottom-cta">
                    <h2><?php esc_html_e( 'Ready to find your ride?', 'erh' ); ?></h2>
                    <p><?php esc_html_e( 'Browse our product database or check out the latest reviews and buying guides.', 'erh' ); ?></p>
                    <div class="about-bottom-actions">
                        <a href="<?php echo esc_url( home_url( '/e-scooters/' ) ); ?>" class="btn btn-primary">
                            <?php esc_html_e( 'Browse products', 'erh' ); ?>
                            <?php erh_the_icon( 'arrow-right' ); ?>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/buying-guides/' ) ); ?>" class="btn btn-secondary">
                            <?php esc_html_e( 'Buying guides', 'erh' ); ?>
                        </a>
                    </div>
                </div>

                <div class="about-bottom-social">
                    <h3><?php esc_html_e( 'Follow along', 'erh' ); ?></h3>
                    <p><?php esc_html_e( 'Latest reviews, deals, and news.', 'erh' ); ?></p>
                    <div class="about-social-links">
                        <?php
                        $socials = erh_get_social_links();
                        foreach ( $socials as $platform => $url ) :
                        ?>
                            <a href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( ucfirst( $platform ) ); ?>" target="_blank" rel="noopener">
                                <?php erh_the_icon( $platform ); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php
get_footer();
