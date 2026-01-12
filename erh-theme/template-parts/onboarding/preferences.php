<?php
/**
 * Email Preferences Form Fields (Onboarding Variant)
 *
 * Preference toggles with category selection for onboarding flow.
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="onboarding-preferences">
    <!-- Price Alerts -->
    <div class="onboarding-preference">
        <label class="onboarding-toggle">
            <input type="checkbox" name="price_tracker_emails" checked data-preference="price_tracker_emails">
            <span class="onboarding-toggle-switch"></span>
            <span class="onboarding-toggle-content">
                <span class="onboarding-toggle-header">
                    <span class="onboarding-toggle-icon">
                        <?php erh_the_icon( 'bell', 'icon' ); ?>
                    </span>
                    <span class="onboarding-toggle-label"><?php esc_html_e( 'Price Alert Emails', 'erh' ); ?></span>
                </span>
                <span class="onboarding-toggle-desc"><?php esc_html_e( 'Get notified when prices drop on products you\'re tracking.', 'erh' ); ?></span>
            </span>
        </label>
    </div>

    <!-- Deal Roundups -->
    <div class="onboarding-preference" data-roundup-preference>
        <label class="onboarding-toggle">
            <input type="checkbox" name="sales_roundup_emails" checked data-preference="sales_roundup_emails" data-toggle-categories>
            <span class="onboarding-toggle-switch"></span>
            <span class="onboarding-toggle-content">
                <span class="onboarding-toggle-header">
                    <span class="onboarding-toggle-icon">
                        <?php erh_the_icon( 'tag', 'icon' ); ?>
                    </span>
                    <span class="onboarding-toggle-label"><?php esc_html_e( 'Deal Roundup Emails', 'erh' ); ?></span>
                </span>
                <span class="onboarding-toggle-desc"><?php esc_html_e( 'Get the best deals based on historical pricing data delivered to your inbox.', 'erh' ); ?></span>
            </span>
        </label>

        <!-- Roundup Options -->
        <div class="onboarding-roundup-options" data-roundup-categories>
            <div class="onboarding-categories">
                <span class="onboarding-options-label"><?php esc_html_e( 'Categories', 'erh' ); ?></span>
                <div class="onboarding-category-chips">
                    <label class="onboarding-chip">
                        <input type="checkbox" name="sales_roundup_types[]" value="escooter" checked data-roundup-type>
                        <span class="onboarding-chip-box">
                            <?php erh_the_icon( 'check', 'icon' ); ?>
                        </span>
                        <span><?php esc_html_e( 'E-Scooters', 'erh' ); ?></span>
                    </label>
                    <label class="onboarding-chip">
                        <input type="checkbox" name="sales_roundup_types[]" value="ebike" checked data-roundup-type>
                        <span class="onboarding-chip-box">
                            <?php erh_the_icon( 'check', 'icon' ); ?>
                        </span>
                        <span><?php esc_html_e( 'E-Bikes', 'erh' ); ?></span>
                    </label>
                    <label class="onboarding-chip">
                        <input type="checkbox" name="sales_roundup_types[]" value="eskate" checked data-roundup-type>
                        <span class="onboarding-chip-box">
                            <?php erh_the_icon( 'check', 'icon' ); ?>
                        </span>
                        <span><?php esc_html_e( 'E-Skateboards', 'erh' ); ?></span>
                    </label>
                    <label class="onboarding-chip">
                        <input type="checkbox" name="sales_roundup_types[]" value="euc" checked data-roundup-type>
                        <span class="onboarding-chip-box">
                            <?php erh_the_icon( 'check', 'icon' ); ?>
                        </span>
                        <span><?php esc_html_e( 'EUCs', 'erh' ); ?></span>
                    </label>
                    <label class="onboarding-chip">
                        <input type="checkbox" name="sales_roundup_types[]" value="hoverboard" checked data-roundup-type>
                        <span class="onboarding-chip-box">
                            <?php erh_the_icon( 'check', 'icon' ); ?>
                        </span>
                        <span><?php esc_html_e( 'Hoverboards', 'erh' ); ?></span>
                    </label>
                </div>
            </div>

            <div class="onboarding-frequency">
                <span class="onboarding-options-label"><?php esc_html_e( 'Frequency', 'erh' ); ?></span>
                <select name="sales_roundup_frequency" class="custom-select-sm" data-custom-select>
                    <option value="weekly" selected><?php esc_html_e( 'Weekly', 'erh' ); ?></option>
                    <option value="bi-weekly"><?php esc_html_e( 'Bi-weekly', 'erh' ); ?></option>
                    <option value="monthly"><?php esc_html_e( 'Monthly', 'erh' ); ?></option>
                </select>
            </div>
        </div>
    </div>

    <!-- Newsletter -->
    <div class="onboarding-preference">
        <label class="onboarding-toggle">
            <input type="checkbox" name="newsletter_subscription" checked data-preference="newsletter_subscription">
            <span class="onboarding-toggle-switch"></span>
            <span class="onboarding-toggle-content">
                <span class="onboarding-toggle-header">
                    <span class="onboarding-toggle-icon">
                        <?php erh_the_icon( 'mail', 'icon' ); ?>
                    </span>
                    <span class="onboarding-toggle-label"><?php esc_html_e( 'General Newsletter', 'erh' ); ?></span>
                </span>
                <span class="onboarding-toggle-desc"><?php esc_html_e( 'Stay updated with the latest reviews, news, and guides.', 'erh' ); ?></span>
            </span>
        </label>
    </div>
</div>
