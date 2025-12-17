<?php
/**
 * Quick Finder Card (Hero)
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="quick-finder card">
    <h2 class="quick-finder-title"><?php esc_html_e( 'Quick finder', 'erh' ); ?></h2>

    <div class="quick-finder-tabs" role="tablist">
        <button class="tab is-active" role="tab" aria-selected="true" data-tab="e-scooters">
            <?php erh_the_icon( 'escooter' ); ?>
            <span><?php esc_html_e( 'E-scooters', 'erh' ); ?></span>
        </button>
        <button class="tab" role="tab" aria-selected="false" data-tab="e-bikes">
            <?php erh_the_icon( 'ebike' ); ?>
            <span><?php esc_html_e( 'E-bikes', 'erh' ); ?></span>
        </button>
    </div>

    <div class="quick-finder-form" data-finder-tab="e-scooters">
        <div class="quick-finder-field">
            <label class="label"><?php esc_html_e( 'Budget', 'erh' ); ?></label>
            <select class="custom-select" name="budget">
                <option value=""><?php esc_html_e( 'Any price', 'erh' ); ?></option>
                <option value="0-500"><?php esc_html_e( 'Under $500', 'erh' ); ?></option>
                <option value="500-1000"><?php esc_html_e( '$500 - $1,000', 'erh' ); ?></option>
                <option value="1000-2000"><?php esc_html_e( '$1,000 - $2,000', 'erh' ); ?></option>
                <option value="2000+"><?php esc_html_e( '$2,000+', 'erh' ); ?></option>
            </select>
        </div>

        <div class="quick-finder-field">
            <label class="label"><?php esc_html_e( 'Use case', 'erh' ); ?></label>
            <select class="custom-select" name="use_case">
                <option value=""><?php esc_html_e( 'Any use', 'erh' ); ?></option>
                <option value="commuter"><?php esc_html_e( 'Commuting', 'erh' ); ?></option>
                <option value="performance"><?php esc_html_e( 'Performance', 'erh' ); ?></option>
                <option value="offroad"><?php esc_html_e( 'Off-road', 'erh' ); ?></option>
                <option value="lightweight"><?php esc_html_e( 'Lightweight', 'erh' ); ?></option>
            </select>
        </div>

        <button type="button" class="btn btn-primary btn-block quick-finder-submit">
            <?php esc_html_e( 'Find scooters', 'erh' ); ?>
            <?php erh_the_icon( 'arrow-right' ); ?>
        </button>
    </div>
</div>
