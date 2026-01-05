<?php
/**
 * Account Settings Component
 *
 * Settings forms for email, password, and email preferences.
 *
 * @package ERideHero
 *
 * Expected $args:
 *   'user' => WP_User object
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user = $args['user'] ?? wp_get_current_user();
?>

<div class="settings-section" data-settings>
	<h1 class="account-title">Account Settings</h1>

	<!-- Change Email -->
	<section class="settings-card" data-settings-email>
		<h2 class="settings-card-title">Change Email</h2>

		<form class="settings-form" data-email-form>
			<div class="settings-current">
				<span class="settings-current-label">Current email:</span>
				<span class="settings-current-value"><?php echo esc_html( $user->user_email ); ?></span>
			</div>

			<div class="form-group">
				<label for="new-email" class="form-label">New Email Address</label>
				<input
					type="email"
					id="new-email"
					name="new_email"
					class="form-input"
					required
					autocomplete="email"
				>
			</div>

			<div class="form-group">
				<label for="email-password" class="form-label">Current Password</label>
				<div class="password-input-wrapper">
					<input
						type="password"
						id="email-password"
						name="current_password"
						class="form-input"
						required
						autocomplete="current-password"
					>
					<button type="button" class="password-toggle" data-toggle-password aria-label="Show password">
						<?php erh_the_icon( 'eye', 'password-icon password-icon--show' ); ?>
						<?php erh_the_icon( 'eye-off', 'password-icon password-icon--hide' ); ?>
					</button>
				</div>
			</div>

			<div class="form-error" data-email-error hidden></div>

			<button type="submit" class="btn btn-primary" data-email-submit>
				<span class="btn-text">Change Email</span>
				<span class="btn-loading" hidden>
					<svg class="spinner" viewBox="0 0 24 24" width="20" height="20">
						<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-linecap="round"/>
					</svg>
				</span>
			</button>
		</form>
	</section>

	<!-- Change Password -->
	<section class="settings-card" data-settings-password>
		<h2 class="settings-card-title">Change Password</h2>

		<form class="settings-form" data-password-form>
			<!-- Hidden username field for accessibility (password managers) -->
			<input
				type="text"
				name="username"
				value="<?php echo esc_attr( $user->user_login ); ?>"
				autocomplete="username"
				class="visually-hidden"
				tabindex="-1"
				aria-hidden="true"
			>

			<div class="form-group">
				<label for="current-password" class="form-label">Current Password</label>
				<div class="password-input-wrapper">
					<input
						type="password"
						id="current-password"
						name="current_password"
						class="form-input"
						required
						autocomplete="current-password"
					>
					<button type="button" class="password-toggle" data-toggle-password aria-label="Show password">
						<?php erh_the_icon( 'eye', 'password-icon password-icon--show' ); ?>
						<?php erh_the_icon( 'eye-off', 'password-icon password-icon--hide' ); ?>
					</button>
				</div>
			</div>

			<div class="form-group">
				<label for="new-password" class="form-label">New Password</label>
				<div class="password-input-wrapper">
					<input
						type="password"
						id="new-password"
						name="new_password"
						class="form-input"
						required
						minlength="8"
						autocomplete="new-password"
					>
					<button type="button" class="password-toggle" data-toggle-password aria-label="Show password">
						<?php erh_the_icon( 'eye', 'password-icon password-icon--show' ); ?>
						<?php erh_the_icon( 'eye-off', 'password-icon password-icon--hide' ); ?>
					</button>
				</div>
			</div>

			<div class="form-group">
				<label for="confirm-password" class="form-label">Confirm New Password</label>
				<div class="password-input-wrapper">
					<input
						type="password"
						id="confirm-password"
						name="confirm_password"
						class="form-input"
						required
						minlength="8"
						autocomplete="new-password"
					>
					<button type="button" class="password-toggle" data-toggle-password aria-label="Show password">
						<?php erh_the_icon( 'eye', 'password-icon password-icon--show' ); ?>
						<?php erh_the_icon( 'eye-off', 'password-icon password-icon--hide' ); ?>
					</button>
				</div>
			</div>

			<div class="form-error" data-password-error hidden></div>

			<button type="submit" class="btn btn-primary" data-password-submit>
				<span class="btn-text">Change Password</span>
				<span class="btn-loading" hidden>
					<svg class="spinner" viewBox="0 0 24 24" width="20" height="20">
						<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-linecap="round"/>
					</svg>
				</span>
			</button>
		</form>
	</section>

	<!-- Email Preferences -->
	<section class="settings-card" data-settings-preferences>
		<h2 class="settings-card-title">Email Preferences</h2>

		<form class="settings-form" data-preferences-form>
			<div class="settings-preference">
				<label class="settings-checkbox">
					<input
						type="checkbox"
						name="price_trackers_emails"
						data-preference="price_trackers_emails"
					>
					<span class="settings-checkbox-box">
						<?php erh_the_icon( 'check', 'icon' ); ?>
					</span>
					<span class="settings-checkbox-content">
						<span class="settings-checkbox-label">Price Tracker Emails</span>
						<span class="settings-preference-desc">Get notified when prices drop on products you're tracking.</span>
					</span>
				</label>
			</div>

			<div class="settings-preference">
				<label class="settings-checkbox">
					<input
						type="checkbox"
						name="sales_roundup_emails"
						data-preference="sales_roundup_emails"
					>
					<span class="settings-checkbox-box">
						<?php erh_the_icon( 'check', 'icon' ); ?>
					</span>
					<span class="settings-checkbox-content">
						<span class="settings-checkbox-label">Deals Round-up Emails</span>
						<span class="settings-preference-desc">Receive our curated selection of the best deals.</span>
					</span>
				</label>

				<div class="settings-roundup-options" data-roundup-wrapper>
					<div class="settings-roundup-types">
						<span class="form-label">Product Types</span>
						<div class="settings-type-checkboxes" data-roundup-types>
							<label class="settings-type-checkbox">
								<input type="checkbox" name="sales_roundup_types[]" value="escooter" data-roundup-type>
								<span class="settings-type-box">
									<?php erh_the_icon( 'check', 'icon' ); ?>
								</span>
								<span>E-Scooters</span>
							</label>
							<label class="settings-type-checkbox">
								<input type="checkbox" name="sales_roundup_types[]" value="ebike" data-roundup-type>
								<span class="settings-type-box">
									<?php erh_the_icon( 'check', 'icon' ); ?>
								</span>
								<span>E-Bikes</span>
							</label>
							<label class="settings-type-checkbox">
								<input type="checkbox" name="sales_roundup_types[]" value="eskate" data-roundup-type>
								<span class="settings-type-box">
									<?php erh_the_icon( 'check', 'icon' ); ?>
								</span>
								<span>E-Skateboards</span>
							</label>
							<label class="settings-type-checkbox">
								<input type="checkbox" name="sales_roundup_types[]" value="euc" data-roundup-type>
								<span class="settings-type-box">
									<?php erh_the_icon( 'check', 'icon' ); ?>
								</span>
								<span>EUCs</span>
							</label>
							<label class="settings-type-checkbox">
								<input type="checkbox" name="sales_roundup_types[]" value="hoverboard" data-roundup-type>
								<span class="settings-type-box">
									<?php erh_the_icon( 'check', 'icon' ); ?>
								</span>
								<span>Hoverboards</span>
							</label>
						</div>
					</div>

					<div class="settings-frequency">
						<span class="form-label">Frequency</span>
						<select
							name="sales_roundup_frequency"
							class="custom-select-sm"
							data-custom-select
							data-preference="sales_roundup_frequency"
						>
							<option value="weekly">Weekly</option>
							<option value="bi-weekly">Bi-weekly</option>
							<option value="monthly">Monthly</option>
						</select>
					</div>
				</div>
			</div>

			<div class="settings-preference">
				<label class="settings-checkbox">
					<input
						type="checkbox"
						name="newsletter_subscription"
						data-preference="newsletter_subscription"
					>
					<span class="settings-checkbox-box">
						<?php erh_the_icon( 'check', 'icon' ); ?>
					</span>
					<span class="settings-checkbox-content">
						<span class="settings-checkbox-label">General Newsletter</span>
						<span class="settings-preference-desc">Stay updated with the latest reviews, news, and guides.</span>
					</span>
				</label>
			</div>

			<div class="form-error" data-preferences-error hidden></div>

			<button type="submit" class="btn btn-primary" data-preferences-submit>
				<span class="btn-text">Save Preferences</span>
				<span class="btn-loading" hidden>
					<svg class="spinner" viewBox="0 0 24 24" width="20" height="20">
						<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-linecap="round"/>
					</svg>
				</span>
			</button>
		</form>
	</section>
</div>
