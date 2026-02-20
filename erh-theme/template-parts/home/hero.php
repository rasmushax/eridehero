<?php
/**
 * Homepage Hero Section
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<section class="hero">
    <div class="hero-grid" aria-hidden="true"></div>
    <div class="container">
        <div class="hero-content">
            <div class="hero-left">
                <h1><?php esc_html_e( 'Find your perfect', 'erh' ); ?> <span><?php esc_html_e( 'electric ride', 'erh' ); ?></span></h1>
                <p class="hero-subtitle"><?php esc_html_e( 'Data-driven reviews and comparison tools to help you choose the right e-scooter, e-bike, or EUC.', 'erh' ); ?></p>

                <div class="hero-ctas">
                    <a href="<?php echo esc_url( home_url( '/buying-guides/' ) ); ?>" class="btn btn-primary">
                        <?php erh_the_icon( 'book' ); ?>
                        <?php esc_html_e( 'Buying guides', 'erh' ); ?>
                    </a>
                    <a href="<?php echo esc_url( home_url( '/deals/' ) ); ?>" class="btn btn-secondary">
                        <?php erh_the_icon( 'percent' ); ?>
                        <?php esc_html_e( 'Best deals', 'erh' ); ?>
                    </a>
                </div>

                <div class="check-list">
                    <div class="check-item">
                        <?php erh_the_icon( 'check' ); ?>
                        <?php esc_html_e( 'Hands-on testing', 'erh' ); ?>
                    </div>
                    <div class="check-item">
                        <?php erh_the_icon( 'check' ); ?>
                        <?php esc_html_e( 'Unbiased reviews', 'erh' ); ?>
                    </div>
                    <div class="check-item">
                        <?php erh_the_icon( 'check' ); ?>
                        <?php esc_html_e( 'Free to use', 'erh' ); ?>
                    </div>
                </div>
            </div>

            <div class="hero-right">
                <div class="finder-card">
                    <div class="finder-body">
                        <div class="finder-header">
                            <div class="icon-box icon-box-gradient finder-icon">
                                <?php erh_the_icon( 'search' ); ?>
                            </div>
                            <div>
                                <span class="finder-title"><?php esc_html_e( 'Quick finder', 'erh' ); ?></span>
                                <p><?php esc_html_e( 'Answer 3 questions, get matched', 'erh' ); ?></p>
                            </div>
                        </div>
                        <form class="finder-form" action="<?php echo esc_url( home_url( '/finder/' ) ); ?>" method="get">
                            <!-- Ride Type Selection -->
                            <div class="finder-types">
                                <button type="button" class="finder-type active" data-type="escooter">
                                    <div class="finder-type-icon">
                                        <?php erh_the_icon( 'escooter', 'icon-filled' ); ?>
                                    </div>
                                    <span class="finder-type-label"><?php esc_html_e( 'E-scooter', 'erh' ); ?></span>
                                </button>
                                <button type="button" class="finder-type" data-type="ebike">
                                    <div class="finder-type-icon">
                                        <?php erh_the_icon( 'ebike', 'icon-filled' ); ?>
                                    </div>
                                    <span class="finder-type-label"><?php esc_html_e( 'E-bike', 'erh' ); ?></span>
                                </button>
                                <button type="button" class="finder-type" data-type="euc">
                                    <div class="finder-type-icon">
                                        <?php erh_the_icon( 'euc' ); ?>
                                    </div>
                                    <span class="finder-type-label"><?php esc_html_e( 'EUC', 'erh' ); ?></span>
                                </button>
                                <button type="button" class="finder-type" data-type="eskate">
                                    <div class="finder-type-icon">
                                        <?php erh_the_icon( 'eskate' ); ?>
                                    </div>
                                    <span class="finder-type-label"><?php esc_html_e( 'E-Skate', 'erh' ); ?></span>
                                </button>
                                <button type="button" class="finder-type" data-type="hoverboard">
                                    <div class="finder-type-icon">
                                        <?php erh_the_icon( 'hoverboard', 'icon-filled' ); ?>
                                    </div>
                                    <span class="finder-type-label"><?php esc_html_e( 'Hoverboard', 'erh' ); ?></span>
                                </button>
                            </div>

                            <!-- Hidden field for selected type -->
                            <input type="hidden" name="type" id="finder-type-input" value="escooter">

                            <!-- Budget & Use Dropdowns -->
                            <div class="finder-row">
                                <div class="finder-field">
                                    <label class="form-label" for="finder-budget" id="label-finder-budget"><?php esc_html_e( 'Your budget', 'erh' ); ?></label>
                                    <?php
                                    erh_custom_select( [
                                        'name'        => 'budget',
                                        'id'          => 'finder-budget',
                                        'placeholder' => __( 'Any budget', 'erh' ),
                                        'options'     => [
                                            ''          => __( 'Any budget', 'erh' ),
                                            'under-500' => __( 'Under $500', 'erh' ),
                                            '500-1000'  => __( '$500 – $1,000', 'erh' ),
                                            '1000-2000' => __( '$1,000 – $2,000', 'erh' ),
                                            '2000-plus' => __( '$2,000+', 'erh' ),
                                        ],
                                    ] );
                                    ?>
                                </div>
                                <div class="finder-field">
                                    <label class="form-label" for="finder-use" id="label-finder-use"><?php esc_html_e( 'Primary use', 'erh' ); ?></label>
                                    <?php
                                    erh_custom_select( [
                                        'name'        => 'use',
                                        'id'          => 'finder-use',
                                        'placeholder' => __( 'Any use', 'erh' ),
                                        'options'     => [
                                            ''           => __( 'Any use', 'erh' ),
                                            'commuting'  => __( 'Commuting', 'erh' ),
                                            'recreation' => __( 'Recreation', 'erh' ),
                                            'off-road'   => __( 'Off-road', 'erh' ),
                                            'last-mile'  => __( 'Last-mile', 'erh' ),
                                        ],
                                    ] );
                                    ?>
                                </div>
                            </div>
                            <button type="submit" class="finder-submit">
                                <span class="finder-submit-text"><?php esc_html_e( 'Find my ride', 'erh' ); ?></span>
                                <?php erh_the_icon( 'arrow-right' ); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
