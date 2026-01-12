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
                <div class="eyebrow hero-eyebrow">
                    <?php esc_html_e( '120+ products · 12,000+ miles tested', 'erh' ); ?>
                </div>
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
                                <h3 class="finder-title"><?php esc_html_e( 'Quick finder', 'erh' ); ?></h3>
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
                                    <select id="finder-budget" name="budget" data-custom-select data-placeholder="<?php esc_attr_e( 'Any budget', 'erh' ); ?>">
                                        <option value=""><?php esc_html_e( 'Any budget', 'erh' ); ?></option>
                                        <option value="under-500"><?php esc_html_e( 'Under $500', 'erh' ); ?></option>
                                        <option value="500-1000"><?php esc_html_e( '$500 – $1,000', 'erh' ); ?></option>
                                        <option value="1000-2000"><?php esc_html_e( '$1,000 – $2,000', 'erh' ); ?></option>
                                        <option value="2000-plus"><?php esc_html_e( '$2,000+', 'erh' ); ?></option>
                                    </select>
                                </div>
                                <div class="finder-field">
                                    <label class="form-label" for="finder-use" id="label-finder-use"><?php esc_html_e( 'Primary use', 'erh' ); ?></label>
                                    <select id="finder-use" name="use" data-custom-select data-placeholder="<?php esc_attr_e( 'Any use', 'erh' ); ?>">
                                        <option value=""><?php esc_html_e( 'Any use', 'erh' ); ?></option>
                                        <option value="commuting"><?php esc_html_e( 'Commuting', 'erh' ); ?></option>
                                        <option value="recreation"><?php esc_html_e( 'Recreation', 'erh' ); ?></option>
                                        <option value="off-road"><?php esc_html_e( 'Off-road', 'erh' ); ?></option>
                                        <option value="last-mile"><?php esc_html_e( 'Last-mile', 'erh' ); ?></option>
                                    </select>
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
