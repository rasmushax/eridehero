<?php
declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HFT_Admin_Settings' ) ) {
	/**
	 * Class HFT_Admin_Settings.
	 *
	 * Handles the plugin admin settings page.
	 */
	class HFT_Admin_Settings {

		private string $settings_page_slug = 'housefresh-tools-settings';
		private string $main_option_group = 'hft_settings_group'; // For Tab 1 settings
		private string $main_settings_option_name = 'hft_settings'; // For Tab 1 settings

		/**
		 * Constructor.
		 */
		public function __construct() {
			add_action( 'admin_notices', [ $this, 'maybe_show_migration_notice' ] );
		}

		/**
		 * Show admin notice if old PA-API credentials exist but new Creators API ones don't.
		 */
		public function maybe_show_migration_notice(): void {
			$options = get_option( $this->main_settings_option_name, [] );
			$has_old_keys = ! empty( $options['amazon_api_key'] ) || ! empty( $options['amazon_api_secret'] );
			$has_new_creds = ! empty( $options['amazon_credentials'] );

			if ( $has_old_keys && ! $has_new_creds ) {
				$settings_url = admin_url( 'admin.php?page=' . $this->settings_page_slug );
				echo '<div class="notice notice-warning is-dismissible">';
				echo '<p><strong>' . esc_html__( 'Housefresh Tools: Amazon API Migration Required', 'housefresh-tools' ) . '</strong></p>';
				echo '<p>' . sprintf(
					/* translators: %s: URL to settings page */
					esc_html__( 'Amazon has replaced the Product Advertising API (PA-API) with the new Creators API. Your old AWS access keys will no longer work. Please enter your new Creators API credentials on the %s.', 'housefresh-tools' ),
					'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings page', 'housefresh-tools' ) . '</a>'
				) . '</p>';
				echo '</div>';
			}
		}

		/**
		 * Add the admin menu item for the settings page.
		 */
		public function add_admin_menu(): void {
			add_submenu_page(
				'housefresh-tools-main-menu',       // Parent slug (the top-level CPT menu)
				__( 'Settings', 'housefresh-tools' ),    // Page title
				__( 'Settings', 'housefresh-tools' ),    // Menu title
				'manage_options',                  // Capability
				$this->settings_page_slug,         // Menu slug
				[ $this, 'render_settings_page_html' ] // Callback function to render the page
			);

		}

		/**
		 * Register plugin settings, sections, and fields.
		 */
		public function admin_init_settings(): void {
			// Register settings for Tab 1 (API Keys & General)
			register_setting(
				$this->main_option_group,          // Option group
				$this->main_settings_option_name,  // Option name (where all Tab 1 settings are stored as an array)
				[ $this, 'sanitize_main_settings' ] // Sanitization callback
			);

			// --- Tab 1: API Keys & General ---
			$tab1_page_group = $this->settings_page_slug . '_tab1';

			// Section: Amazon Creators API Settings
			add_settings_section(
				'hft_amazon_api_section',
				__( 'Amazon Creators API Settings', 'housefresh-tools' ),
				[ $this, 'render_section_callback' ],
				$tab1_page_group
			);
			add_settings_field(
				'amazon_credentials',
				__( 'API Credentials by Region', 'housefresh-tools' ),
				[ $this, 'render_amazon_credentials_repeater' ],
				$tab1_page_group,
				'hft_amazon_api_section',
				[ 'name' => $this->main_settings_option_name . '[amazon_credentials]', 'description' => __( 'Enter your Amazon Creators API credentials for each region. Credentials are region-specific: NA (Americas), EU (Europe/Middle East/India), FE (Far East/Pacific).', 'housefresh-tools' ) ]
			);
			add_settings_field(
				'amazon_associate_tags',
				__( 'Amazon Associate Tags by GEO', 'housefresh-tools' ),
				[ $this, 'render_amazon_associate_tags_repeater' ],
				$tab1_page_group,
				'hft_amazon_api_section',
				[ 'name' => $this->main_settings_option_name . '[amazon_associate_tags]', 'description' => __( 'Add your Amazon Associate Tags for different GEOs (e.g., US, UK, DE). Use ISO country codes or recognized continent codes.', 'housefresh-tools' ) ]
			);

			// Section: Scraping Configuration
			add_settings_section(
				'hft_scraping_config_section',
				__( 'Scraping Configuration', 'housefresh-tools' ),
				[ $this, 'render_section_callback' ],
				$tab1_page_group
			);
			add_settings_field(
				'scrape_interval',
				__( 'Scrape Interval', 'housefresh-tools' ),
				[ $this, 'render_select_field' ],
				$tab1_page_group,
				'hft_scraping_config_section',
				[
					'id' => 'scrape_interval',
					'name' => $this->main_settings_option_name . '[scrape_interval]',
					'options' => [
						'hourly'     => __( 'Hourly', 'housefresh-tools' ),
						'twicedaily' => __( 'Twice Daily', 'housefresh-tools' ),
						'daily'      => __( 'Daily', 'housefresh-tools' ),
					],
					'description' => __( 'How often should the plugin attempt to scrape product prices?', 'housefresh-tools' )
				]
			);
			add_settings_field(
				'scrapingrobot_api_key',
				__( 'ScrapingRobot API Key', 'housefresh-tools' ),
				[ $this, 'render_password_field' ],
				$tab1_page_group,
				'hft_scraping_config_section',
				[ 'id' => 'scrapingrobot_api_key', 'name' => $this->main_settings_option_name . '[scrapingrobot_api_key]', 'description' => __( 'Enter your ScrapingRobot API key for JavaScript-rendered sites. Get your key at scrapingrobot.com', 'housefresh-tools' ) ]
			);

			// Section: IPInfo GeoIP Configuration
			add_settings_section(
				'hft_ipinfo_config_section',
				__( 'IPInfo GeoIP Configuration', 'housefresh-tools' ),
				[ $this, 'render_section_callback' ],
				$tab1_page_group
			);
			add_settings_field(
				'ipinfo_api_token',
				__( 'IPInfo API Token', 'housefresh-tools' ),
				[ $this, 'render_password_field' ],
				$tab1_page_group,
				'hft_ipinfo_config_section',
				[ 'id' => 'ipinfo_api_token', 'name' => $this->main_settings_option_name . '[ipinfo_api_token]', 'description' => __( 'Enter your IPInfo API token for IP geolocation. Get your token at ipinfo.io. Leave blank to use the free tier (1000 requests/day).', 'housefresh-tools' ) ]
			);

			// --- Tab 2: Default Affiliate Rules (Custom Parsers) ---
			// This tab will have its own form and save handling mechanism due to direct table interaction.
			// So, we don't use add_settings_section/field in the same way as Tab 1.

			// --- Tab 3: Notifications (Placeholder) ---
			// No settings fields for this tab yet.
		}

		/**
		 * Render the main settings page HTML with tabs.
		 */
		public function render_settings_page_html(): void {
			$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'api_keys_general';
			$options = get_option( $this->main_settings_option_name );


			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Housefresh Tools Settings', 'housefresh-tools' ); ?></h1>
				<?php settings_errors(); // Display settings errors registered by WordPress ?>

				<h2 class="nav-tab-wrapper">
					<a href="?page=<?php echo esc_attr( $this->settings_page_slug ); ?>&tab=api_keys_general" class="nav-tab <?php echo 'api_keys_general' === $active_tab ? 'nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'API Keys & General', 'housefresh-tools' ); ?>
					</a>
				</h2>

				<form method="post" action="options.php">
					<?php
					settings_fields( $this->main_option_group );
					do_settings_sections( $this->settings_page_slug . '_tab1' );
					submit_button( __( 'Save API & General Settings', 'housefresh-tools' ) );
					?>
				</form>
			</div>
			<?php
		}

		/**
		 * Sanitize main settings (Tab 1).
		 *
		 * @param array $input The input array from the form.
		 * @return array The sanitized array.
		 */
		public function sanitize_main_settings( array $input ): array {
			$sanitized_input = [];
			$options = get_option($this->main_settings_option_name);

			// Sanitize Amazon Creators API Credentials (per-region)
			if ( isset( $input['amazon_credentials'] ) && is_array( $input['amazon_credentials'] ) ) {
				$sanitized_creds = [];
				$valid_regions = ['NA', 'EU', 'FE'];
				$existing_creds = $options['amazon_credentials'] ?? [];

				foreach ( $valid_regions as $region ) {
					if ( ! isset( $input['amazon_credentials'][$region] ) ) {
						// Preserve existing if not submitted
						if ( isset( $existing_creds[$region] ) ) {
							$sanitized_creds[$region] = $existing_creds[$region];
						}
						continue;
					}

					$region_input = $input['amazon_credentials'][$region];
					$region_existing = $existing_creds[$region] ?? [];

					// Handle credential_id
					$cred_id = '';
					if ( isset( $region_input['credential_id'] ) ) {
						$cred_id = sanitize_text_field( $region_input['credential_id'] );
					} elseif ( isset( $region_existing['credential_id'] ) ) {
						$cred_id = $region_existing['credential_id'];
					}

					// Handle credential_secret with remove functionality
					$cred_secret = '';
					if ( isset( $region_input['credential_secret_removed'] ) && $region_input['credential_secret_removed'] === 'true' ) {
						$cred_secret = '';
					} elseif ( isset( $region_input['credential_secret'] ) ) {
						if ( ! empty( $region_input['credential_secret'] ) ) {
							$cred_secret = $region_input['credential_secret'];
						} elseif ( isset( $region_existing['credential_secret'] ) ) {
							$cred_secret = $region_existing['credential_secret'];
						}
					} elseif ( isset( $region_existing['credential_secret'] ) ) {
						$cred_secret = $region_existing['credential_secret'];
					}

					// Only store the region if at least one field has a value
					if ( ! empty( $cred_id ) || ! empty( $cred_secret ) ) {
						$sanitized_creds[$region] = [
							'credential_id'     => $cred_id,
							'credential_secret' => $cred_secret,
						];
					}
				}

				$sanitized_input['amazon_credentials'] = $sanitized_creds;

				// Clear cached tokens when credentials are updated
				if ( class_exists('Housefresh\Tools\Libs\Amazon\HFT_Creators_Api_Auth') ) {
					\Housefresh\Tools\Libs\Amazon\HFT_Creators_Api_Auth::clear_all_cached_tokens();
				}
			} elseif ( isset( $options['amazon_credentials'] ) ) {
				$sanitized_input['amazon_credentials'] = $options['amazon_credentials'];
			}

			if ( isset( $input['scrape_interval'] ) ) {
				$allowed_intervals = [ 'hourly', 'twicedaily', 'daily' ];
				if ( in_array( $input['scrape_interval'], $allowed_intervals, true ) ) {
					$sanitized_input['scrape_interval'] = $input['scrape_interval'];
				}
			}

			// Handle scrapingrobot_api_key with remove functionality
			if ( isset( $input['scrapingrobot_api_key_removed'] ) && $input['scrapingrobot_api_key_removed'] === 'true' ) {
				// User clicked remove button
				$sanitized_input['scrapingrobot_api_key'] = '';
			} elseif ( isset( $input['scrapingrobot_api_key'] ) ) {
				if ( ! empty( $input['scrapingrobot_api_key'] ) ) {
					// A new key has been entered. Store it directly.
					$sanitized_input['scrapingrobot_api_key'] = $input['scrapingrobot_api_key'];
				} elseif ( isset( $options['scrapingrobot_api_key'] ) ) {
					// No new key was entered, but an old one exists. Keep the old one.
					$sanitized_input['scrapingrobot_api_key'] = $options['scrapingrobot_api_key'];
				} else {
					// No new key, and no old key. Store an empty string.
					$sanitized_input['scrapingrobot_api_key'] = '';
				}
			} elseif ( isset( $options['scrapingrobot_api_key'] ) ) {
				// The field might not be in $input if it was empty and the form didn't send it.
				// Preserve the old value if it exists and the field wasn't part of the submission.
				$sanitized_input['scrapingrobot_api_key'] = $options['scrapingrobot_api_key'];
			}

			// Sanitize Amazon Associate Tags repeater
			if ( isset( $input['amazon_associate_tags'] ) && is_array( $input['amazon_associate_tags'] ) ) {
				$sanitized_input['amazon_associate_tags'] = array_map( function( $tag_row ) {
					$sanitized_row = [];
					if ( isset( $tag_row['geo'] ) ) {
						$sanitized_row['geo'] = sanitize_text_field( strtoupper( $tag_row['geo'] ) );
					}
					if ( isset( $tag_row['tag'] ) ) {
						$sanitized_row['tag'] = sanitize_text_field( $tag_row['tag'] );
					}
					return array_filter($sanitized_row); // Remove empty sub-fields if any
				}, $input['amazon_associate_tags'] );
				$sanitized_input['amazon_associate_tags'] = array_filter($sanitized_input['amazon_associate_tags']); // Remove empty rows
			}

			// Handle IPInfo API Token with remove functionality
			if ( isset( $input['ipinfo_api_token_removed'] ) && $input['ipinfo_api_token_removed'] === 'true' ) {
				// User clicked remove button
				$sanitized_input['ipinfo_api_token'] = '';
			} elseif ( isset( $input['ipinfo_api_token'] ) ) {
				if ( ! empty( $input['ipinfo_api_token'] ) ) {
					// A new token has been entered. Store it directly.
					$sanitized_input['ipinfo_api_token'] = $input['ipinfo_api_token'];
				} elseif ( isset( $options['ipinfo_api_token'] ) ) {
					// No new token was entered, but an old one exists. Keep the old one.
					$sanitized_input['ipinfo_api_token'] = $options['ipinfo_api_token'];
				} else {
					// No new token, and no old token. Store an empty string.
					$sanitized_input['ipinfo_api_token'] = '';
				}
			} elseif ( isset( $options['ipinfo_api_token'] ) ) {
				// The field might not be in $input if it was empty and the form didn't send it.
				// Preserve the old value if it exists and the field wasn't part of the submission.
				$sanitized_input['ipinfo_api_token'] = $options['ipinfo_api_token'];
			}

			return $sanitized_input;
		}

		/**
		 * Render a settings section description (if any).
		 *
		 * @param array $args Section arguments.
		 */
		public function render_section_callback( array $args ): void {
			if ( isset( $args['description'] ) && !empty( $args['description'] ) ) {
				echo '<p>' . esc_html( $args['description'] ) . '</p>';
			} elseif (isset($args['title']) && $args['title'] === __( 'Amazon Creators API Settings', 'housefresh-tools' )) {
                echo '<p>' . esc_html__( 'Configure your Amazon Creators API credentials and associate tags here. Credentials are region-specific (NA/EU/FE).', 'housefresh-tools' ) . '</p>';
            } elseif (isset($args['title']) && $args['title'] === __( 'Scraping Configuration', 'housefresh-tools' )) {
                 echo '<p>' . esc_html__( 'Set general scraping parameters.', 'housefresh-tools' ) . '</p>';
            }
		}

		/**
		 * Render a simple text input field.
		 */
		public function render_text_field( array $args ): void {
			$options = get_option( $this->main_settings_option_name );
			$value = isset( $options[ $args['id'] ] ) ? esc_attr( $options[ $args['id'] ] ) : '';
			echo '<input type="text" id="' . esc_attr( $args['id'] ) . '" name="' . esc_attr( $args['name'] ) . '" value="' . $value . '" class="regular-text">';
			if ( isset( $args['description'] ) ) {
				echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
			}
		}

		/**
		 * Render a password input field.
		 */
		public function render_password_field( array $args ): void {
			$options = get_option( $this->main_settings_option_name );
			$has_value = isset( $options[ $args['id'] ] ) && ! empty( $options[ $args['id'] ] );

			echo '<div class="hft-password-field-wrapper">';

			if ( $has_value ) {
				// Show masked value and remove button
				echo '<input type="text" value="' . esc_attr( str_repeat('*', 16) ) . '" class="regular-text hft-masked-secret" readonly style="background-color: #f0f0f1; color: #72777c;">';
				echo ' <button type="button" class="button button-secondary hft-remove-secret-key" data-field="' . esc_attr( $args['id'] ) . '">' . __( 'Remove', 'housefresh-tools' ) . '</button>';
				// Keep the original field but hidden to preserve the value if not removing
				echo '<input type="hidden" name="' . esc_attr( $args['name'] ) . '" value="' . esc_attr( $options[ $args['id'] ] ) . '">';
			} else {
				// Show password input
				echo '<input type="password" id="' . esc_attr( $args['id'] ) . '" name="' . esc_attr( $args['name'] ) . '" value="" class="regular-text">';
			}

			echo '</div>';

			if ( isset( $args['description'] ) ) {
				echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
			}
		}

		/**
		 * Render a select dropdown field.
		 */
		public function render_select_field( array $args ): void {
			$options = get_option( $this->main_settings_option_name );
			$value = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : '';
			echo '<select id="' . esc_attr( $args['id'] ) . '" name="' . esc_attr( $args['name'] ) . '">';
			foreach ( $args['options'] as $option_value => $option_label ) {
				echo '<option value="' . esc_attr( $option_value ) . '"' . selected( $value, $option_value, false ) . '>' . esc_html( $option_label ) . '</option>';
			}
			echo '</select>';
			if ( isset( $args['description'] ) ) {
				echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
			}
		}

		/**
		 * Render the per-region credentials fields for Amazon Creators API.
		 */
		public function render_amazon_credentials_repeater( array $args ): void {
			$options = get_option( $this->main_settings_option_name );
			$credentials = isset( $options['amazon_credentials'] ) && is_array( $options['amazon_credentials'] ) ? $options['amazon_credentials'] : [];
			$field_name_base = esc_attr( $args['name'] );

			$regions = [
				'NA' => [
					'label'   => __( 'NA (Americas)', 'housefresh-tools' ),
					'version' => '2.1',
					'geos'    => 'US, CA, MX, BR',
				],
				'EU' => [
					'label'   => __( 'EU (Europe/Middle East/India)', 'housefresh-tools' ),
					'version' => '2.2',
					'geos'    => 'GB, DE, FR, ES, IT, NL, BE, PL, SE, TR, AE, EG, SA, IN',
				],
				'FE' => [
					'label'   => __( 'FE (Far East/Pacific)', 'housefresh-tools' ),
					'version' => '2.3',
					'geos'    => 'JP, AU, SG',
				],
			];

			foreach ( $regions as $region_code => $region_info ) :
				$cred_id = $credentials[$region_code]['credential_id'] ?? '';
				$cred_secret = $credentials[$region_code]['credential_secret'] ?? '';
				$has_secret = ! empty( $cred_secret );
			?>
				<div class="hft-region-credentials" style="margin-bottom: 20px; padding: 12px 16px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
					<h4 style="margin: 0 0 8px 0;">
						<?php echo esc_html( $region_info['label'] ); ?>
						<span style="font-weight: normal; color: #666; font-size: 12px;">
							&mdash; <?php printf( esc_html__( 'Version %s', 'housefresh-tools' ), esc_html( $region_info['version'] ) ); ?>
							&mdash; <?php echo esc_html( $region_info['geos'] ); ?>
						</span>
					</h4>

					<p style="margin: 4px 0;">
						<label>
							<?php esc_html_e( 'Credential ID:', 'housefresh-tools' ); ?>
							<input type="text"
								name="<?php echo $field_name_base; ?>[<?php echo esc_attr( $region_code ); ?>][credential_id]"
								value="<?php echo esc_attr( $cred_id ); ?>"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'Enter Credential ID', 'housefresh-tools' ); ?>">
						</label>
					</p>

					<p style="margin: 4px 0;">
						<label>
							<?php esc_html_e( 'Credential Secret:', 'housefresh-tools' ); ?>
						</label>
						<div class="hft-password-field-wrapper">
						<?php if ( $has_secret ) : ?>
							<input type="text" value="<?php echo esc_attr( str_repeat('*', 16) ); ?>" class="regular-text hft-masked-secret" readonly style="background-color: #f0f0f1; color: #72777c;">
							<button type="button" class="button button-secondary hft-remove-region-secret" data-region="<?php echo esc_attr( $region_code ); ?>"><?php esc_html_e( 'Remove', 'housefresh-tools' ); ?></button>
							<input type="hidden"
								name="<?php echo $field_name_base; ?>[<?php echo esc_attr( $region_code ); ?>][credential_secret]"
								value="<?php echo esc_attr( $cred_secret ); ?>">
						<?php else : ?>
							<input type="password"
								name="<?php echo $field_name_base; ?>[<?php echo esc_attr( $region_code ); ?>][credential_secret]"
								value=""
								class="regular-text"
								placeholder="<?php esc_attr_e( 'Enter Credential Secret', 'housefresh-tools' ); ?>">
						<?php endif; ?>
						</div>
					</p>
				</div>
			<?php endforeach;

			if ( isset( $args['description'] ) ) {
				echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
			}
			?>
			<script type="text/javascript">
				jQuery(document).ready(function($) {
					$('.hft-remove-region-secret').on('click', function(e) {
						e.preventDefault();
						var $button = $(this);
						var region = $button.data('region');
						var $wrapper = $button.closest('.hft-password-field-wrapper');
						var fieldNameBase = '<?php echo $field_name_base; ?>';

						$wrapper.html(
							'<input type="password" name="' + fieldNameBase + '[' + region + '][credential_secret]" value="" class="regular-text" placeholder="<?php esc_attr_e( 'Enter Credential Secret', 'housefresh-tools' ); ?>">' +
							'<input type="hidden" name="' + fieldNameBase + '[' + region + '][credential_secret_removed]" value="true">'
						);
					});
				});
			</script>
			<?php
		}

		/**
		 * Render the repeater field for Amazon Associate Tags.
		 */
		public function render_amazon_associate_tags_repeater( array $args ): void {
			$options = get_option( $this->main_settings_option_name );
			$tags = isset( $options['amazon_associate_tags'] ) && is_array( $options['amazon_associate_tags'] ) ? $options['amazon_associate_tags'] : [];
			$field_name_base = esc_attr( $args['name'] );
			?>
			<div id="hft-amazon-tags-repeater">
				<?php if ( empty( $tags ) ) : $tags[] = ['geo' => '', 'tag' => '']; endif; // Start with one empty row if none exist ?>
				<?php foreach ( $tags as $index => $tag_row ) : ?>
					<div class="hft-repeater-row hft-amazon-tag-row">
						<label><?php esc_html_e( 'GEO:', 'housefresh-tools' ); ?>
							<input type="text" name="<?php echo $field_name_base; ?>[<?php echo $index; ?>][geo]" value="<?php echo esc_attr( $tag_row['geo'] ?? '' ); ?>" placeholder="US" size="5">
						</label>
						<label><?php esc_html_e( 'Associate Tag:', 'housefresh-tools' ); ?>
							<input type="text" name="<?php echo $field_name_base; ?>[<?php echo $index; ?>][tag]" value="<?php echo esc_attr( $tag_row['tag'] ?? '' ); ?>" placeholder="yourtag-20" size="20">
						</label>
						<button type="button" class="button hft-remove-row"><?php esc_html_e( 'Remove', 'housefresh-tools' ); ?></button>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" id="hft-add-amazon-tag-row" class="button"><?php esc_html_e( '+ Add GEO Tag', 'housefresh-tools' ); ?></button>
			<?php
			if ( isset( $args['description'] ) ) {
				echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
			}
			// Inline JS for this simple repeater. More complex repeaters might need a separate .js file.
			?>
			<script type="text/javascript">
				jQuery(document).ready(function($) {
					var repeater = $('#hft-amazon-tags-repeater');
					var fieldNameBase = '<?php echo $field_name_base; ?>';

					$('#hft-add-amazon-tag-row').on('click', function() {
						var newIndex = repeater.find('.hft-amazon-tag-row').length;
						var newRow = '<div class="hft-repeater-row hft-amazon-tag-row">';
						newRow += '<label><?php esc_html_e( "GEO:", "housefresh-tools" ); ?> ';
						newRow += '<input type="text" name="' + fieldNameBase + '[' + newIndex + '][geo]" value="" placeholder="US" size="5">';
						newRow += '</label> ';
						newRow += '<label><?php esc_html_e( "Associate Tag:", "housefresh-tools" ); ?> ';
						newRow += '<input type="text" name="' + fieldNameBase + '[' + newIndex + '][tag]" value="" placeholder="yourtag-20" size="20">';
						newRow += '</label> ';
						newRow += '<button type="button" class="button hft-remove-row"><?php esc_html_e( "Remove", "housefresh-tools" ); ?></button>';
						newRow += '</div>';
						repeater.append(newRow);
					});

					repeater.on('click', '.hft-remove-row', function() {
						$(this).closest('.hft-amazon-tag-row').remove();
						// Re-index if necessary, though for simple array saving, WordPress might handle it okay.
					});
				});
			</script>
			<?php
		}




		private static function get_predefined_geo_whitelist(): array {
			return [
				'US', 'GB', 'CA', 'DE', 'FR', 'ES', 'IT', 'AU', // Common Amazon TLDs
				// Add other relevant GEO codes as needed
			];
		}

		public function enqueue_admin_assets(string $hook_suffix): void {
			// Only enqueue on our specific settings page and for the products CPT edit screen
			global $pagenow, $typenow;
			// $current_screen = get_current_screen(); // Not strictly needed for this logic

			// Check if current post type is a product post type (supports configurable post types)
			$is_product_cpt = class_exists( 'HFT_Post_Type_Helper' )
				? HFT_Post_Type_Helper::is_product_post_type( $typenow )
				: ( 'hf_product' === $typenow );

			if (
				($pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === $this->settings_page_slug) ||
				($pagenow === 'post.php' && $is_product_cpt) ||
				($pagenow === 'post-new.php' && $is_product_cpt)
			) {
				wp_enqueue_style( 'hft-admin-styles', HFT_PLUGIN_URL . 'admin/css/hft-admin-styles.css', [], HFT_VERSION );
				wp_enqueue_script( 'hft-admin-scripts', HFT_PLUGIN_URL . 'admin/js/hft-admin-scripts.js', [ 'jquery', 'wp-util' ], HFT_VERSION, true );

				// Enqueue Tagify from local files only on the settings page
				if ($pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === $this->settings_page_slug) {
					wp_enqueue_style( 'tagify', HFT_PLUGIN_URL . 'assets/css/tagify.css', [], HFT_VERSION );
					wp_enqueue_script( 'tagify', HFT_PLUGIN_URL . 'assets/js/tagify.min.js', [], HFT_VERSION, true );
				}

				wp_localize_script( 'hft-admin-scripts', 'hft_admin_data', [
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'hft_ajax_nonce' ),
					'strings'  => [
						'confirm_delete' => __( 'Are you sure you want to delete this row?', 'housefresh-tools' ),
						'error_occurred' => __( 'An error occurred.', 'housefresh-tools' ),
					],
					'geo_whitelist' => self::get_predefined_geo_whitelist(),
					'settings_page_slug' => $this->settings_page_slug,
				] );
			}
		}

	}
}
