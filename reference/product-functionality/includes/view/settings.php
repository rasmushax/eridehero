<?php
// settings.php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Check if user is logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url());
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Check if user has any active trackers
global $wpdb;
$table_name = $wpdb->prefix . 'price_trackers';
$active_trackers = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
    $user_id
));

// Get user's price tracker email preference
$price_trackers_emails = get_user_meta($user_id, 'price_trackers_emails', true);

?>

<div class="pf-settings-container">
    <h2>Account Settings</h2>
    
    <!-- Email Change Section -->
    <section class="pf-settings-section">
        <h3>Change Email</h3>
        <form id="pf-change-email-form">
            <input type="email" id="pf-current-email" name="current_email" value="<?=$current_user->user_email?>" disabled>
            <input type="email" id="pf-new-email" name="new_email" placeholder="New Email Address" required>
            <input type="password" id="pf-current-password" name="current_password" placeholder="Current Password" required>
            <button type="submit" class="pf-btn">Change Email</button>
        </form>
    </section>

    <!-- Password Change Section -->
    <section class="pf-settings-section">
        <h3>Change Password</h3>
        <form id="pf-change-password-form">
            <input type="password" id="pf-current-password-2" name="current_password" placeholder="Current Password" required>
            <input type="password" id="pf-new-password" name="new_password" placeholder="New Password" required>
            <input type="password" id="pf-confirm-new-password" name="confirm_new_password" placeholder="Confirm New Password" required>
            <button type="submit" class="pf-btn">Change Password</button>
        </form>
    </section>

    <!-- Email Preferences Section -->
    <section class="pf-settings-section">
        <h3>Email Preferences</h3>
        <form id="pf-email-preferences-form">
            <div class="pf-checkbox-group">
                <label for="pf-price-trackers" class="checkbox-container">
					<input type="checkbox" id="pf-price-trackers" name="price_trackers" <?php checked(get_user_meta($current_user->ID, 'price_trackers_emails', true), 1); ?>>
					<span class="checkmark"></span>
					Price Trackers Emails
				</label>
            </div>
			 <?php if ($active_trackers > 0): ?>
            <div id="pf-price-trackers-warning" class="pf-warning-message" style="display: <?php echo $price_trackers_emails != '1' ? 'block' : 'none'; ?>;">
				You have active price trackers, but notifications are turned off. You won't receive alerts until you activate Price Trackers Emails and save your preferences.
			</div>
            <?php endif; ?>
            <div class="pf-checkbox-group">
                <label for="pf-sales-roundup" class="checkbox-container">
					<input type="checkbox" id="pf-sales-roundup" name="sales_roundup" <?php checked(get_user_meta($current_user->ID, 'sales_roundup_emails', true), 1); ?>>
					<span class="checkmark"></span>
					Sales Round-up Emails
				</label>
            </div>
            <div id="pf-sales-roundup-frequency" style="display: none;">
                <label>Frequency:</label>
                <select name="sales_roundup_frequency">
                    <option value="weekly" <?php selected(get_user_meta($current_user->ID, 'sales_roundup_frequency', true), 'weekly'); ?>>Weekly</option>
                    <option value="bi-weekly" <?php selected(get_user_meta($current_user->ID, 'sales_roundup_frequency', true), 'bi-weekly'); ?>>Bi-weekly</option>
                    <option value="monthly" <?php selected(get_user_meta($current_user->ID, 'sales_roundup_frequency', true), 'monthly'); ?>>Monthly</option>
                </select>
            </div>
            <div class="pf-checkbox-group">
                <label for="pf-newsletter" class="checkbox-container">
					<input type="checkbox" id="pf-newsletter" name="newsletter" <?php checked(get_user_meta($current_user->ID, 'newsletter_subscription', true), 1); ?>>
					<span class="checkmark"></span>
					General Newsletter
				</label>
            </div>
            <button type="submit" class="pf-btn">Save Preferences</button>
        </form>
    </section>
</div>