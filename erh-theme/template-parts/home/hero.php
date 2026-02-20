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
                        <?php
                        // Build type → finder URL map keyed by finder_key (matches data-type on buttons).
                        $finder_urls = [];
                        foreach ( \ERH\CategoryConfig::CATEGORIES as $cat ) {
                            $finder_slug = $cat['finder_slug'] ?? '';
                            $finder_key  = $cat['finder_key'] ?? $cat['key'];
                            if ( $finder_slug ) {
                                $page = get_page_by_path( $finder_slug );
                                $finder_urls[ $finder_key ] = $page ? get_permalink( $page ) : home_url( '/' . $finder_slug . '/' );
                            }
                        }

                        // Per-type budget presets (aligned with PriceBracketConfig).
                        $budget_options = [
                            'escooter'   => [ '' => __( 'Any budget', 'erh' ), 'under-500' => __( 'Under $500', 'erh' ), '500-1000' => __( '$500 – $1,000', 'erh' ), '1000-2000' => __( '$1,000 – $2,000', 'erh' ), '2000-plus' => __( '$2,000+', 'erh' ) ],
                            'ebike'      => [ '' => __( 'Any budget', 'erh' ), 'under-1500' => __( 'Under $1,500', 'erh' ), '1500-3000' => __( '$1,500 – $3,000', 'erh' ), '3000-5000' => __( '$3,000 – $5,000', 'erh' ), '5000-plus' => __( '$5,000+', 'erh' ) ],
                            'euc'        => [ '' => __( 'Any budget', 'erh' ), 'under-1000' => __( 'Under $1,000', 'erh' ), '1000-2000' => __( '$1,000 – $2,000', 'erh' ), '2000-3000' => __( '$2,000 – $3,000', 'erh' ), '3000-plus' => __( '$3,000+', 'erh' ) ],
                            'eskate'     => [ '' => __( 'Any budget', 'erh' ), 'under-500' => __( 'Under $500', 'erh' ), '500-1000' => __( '$500 – $1,000', 'erh' ), '1000-2000' => __( '$1,000 – $2,000', 'erh' ), '2000-plus' => __( '$2,000+', 'erh' ) ],
                            'hoverboard' => [ '' => __( 'Any budget', 'erh' ), 'under-100' => __( 'Under $100', 'erh' ), '100-200' => __( '$100 – $200', 'erh' ), '200-300' => __( '$200 – $300', 'erh' ), '300-plus' => __( '$300+', 'erh' ) ],
                        ];

                        // Per-type priority options for "What matters most?" dropdown.
                        $priority_options = [
                            'escooter'   => [ '' => __( 'Any', 'erh' ), 'speed' => __( 'Fastest', 'erh' ), 'range' => __( 'Largest battery', 'erh' ), 'lightweight' => __( 'Lightweight', 'erh' ), 'deals' => __( 'Best deals', 'erh' ) ],
                            'ebike'      => [ '' => __( 'Any', 'erh' ), 'power' => __( 'Most powerful', 'erh' ), 'range' => __( 'Largest battery', 'erh' ), 'lightweight' => __( 'Lightweight', 'erh' ), 'deals' => __( 'Best deals', 'erh' ) ],
                            'euc'        => [ '' => __( 'Any', 'erh' ), 'speed' => __( 'Fastest', 'erh' ), 'range' => __( 'Largest battery', 'erh' ), 'lightweight' => __( 'Lightweight', 'erh' ), 'deals' => __( 'Best deals', 'erh' ) ],
                            'eskate'     => [ '' => __( 'Any', 'erh' ), 'speed' => __( 'Fastest', 'erh' ), 'range' => __( 'Largest battery', 'erh' ), 'lightweight' => __( 'Lightweight', 'erh' ), 'deals' => __( 'Best deals', 'erh' ) ],
                            'hoverboard' => [ '' => __( 'Any', 'erh' ), 'popular' => __( 'Most popular', 'erh' ), 'range' => __( 'Largest battery', 'erh' ), 'lightweight' => __( 'Lightweight', 'erh' ), 'deals' => __( 'Best deals', 'erh' ) ],
                        ];
                        ?>
                        <form class="finder-form"
                            data-finder-urls="<?php echo esc_attr( wp_json_encode( $finder_urls ) ); ?>"
                            data-budget-options="<?php echo esc_attr( wp_json_encode( $budget_options ) ); ?>"
                            data-priority-options="<?php echo esc_attr( wp_json_encode( $priority_options ) ); ?>">
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

                            <!-- Budget & Priority Dropdowns -->
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
                                <div class="finder-field" data-priority-field>
                                    <label class="form-label" for="finder-priority" id="label-finder-priority"><?php esc_html_e( 'What matters most?', 'erh' ); ?></label>
                                    <?php
                                    erh_custom_select( [
                                        'name'        => 'priority',
                                        'id'          => 'finder-priority',
                                        'placeholder' => __( 'Any', 'erh' ),
                                        'options'     => [
                                            ''            => __( 'Any', 'erh' ),
                                            'speed'       => __( 'Fastest', 'erh' ),
                                            'range'       => __( 'Largest battery', 'erh' ),
                                            'lightweight' => __( 'Lightweight', 'erh' ),
                                            'deals'       => __( 'Best deals', 'erh' ),
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
