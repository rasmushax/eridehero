<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

require_once(plugin_dir_path(__FILE__) . 'mailchimp.php');
add_filter('send_email_change_email', '__return_false');

class Product_Functionality {
	private $deals_email;
    public function init() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_pf_get_reviews', array($this, 'get_reviews'));
        add_action('wp_ajax_nopriv_pf_get_reviews', array($this, 'get_reviews'));
        add_action('wp_ajax_pf_user_review_status', array($this, 'user_review_status'));
        add_action('wp_ajax_nopriv_pf_user_review_status', array($this, 'user_review_status'));
        add_action('wp_ajax_pf_login', array($this, 'login'));
		add_action('wp_ajax_nopriv_pf_login', array($this, 'login'));
        add_action('wp_ajax_nopriv_pf_login', array($this, 'login'));
        add_action('wp_ajax_refresh_pf_nonce', array($this, 'refresh_nonce'));
        add_action('wp_ajax_nopriv_refresh_pf_nonce', array($this, 'refresh_nonce'));
		add_action('wp_ajax_pf_user_review_status', array($this, 'user_review_status'));
		add_action('wp_ajax_nopriv_pf_user_review_status', array($this, 'user_review_status'));
		add_action('wp_ajax_pf_submit_review', array($this, 'submit_review'));
		add_action('wp_ajax_nopriv_pf_submit_review', array($this, 'submit_review'));
		add_action('wp_ajax_nopriv_pf_register', array($this, 'register'));
		add_filter('wp_new_user_notification_email_admin', array($this, 'custom_new_user_notification_email'), 10, 3);
		add_action('user_register', array($this, 'send_new_user_notification'));
		add_action('wp_ajax_pf_lost_password', array($this, 'handle_lost_password'));
		add_action('wp_ajax_nopriv_pf_lost_password', array($this, 'handle_lost_password'));
		add_action('wp_ajax_nopriv_pf_reset_password', array($this, 'handle_reset_password'));
		add_action('wp_ajax_pf_logout', array($this, 'logout'));
        add_action('wp_ajax_nopriv_pf_logout', array($this, 'logout'));
		add_action('wp_ajax_pf_check_price_data', array($this, 'check_price_data'));
		add_action('wp_ajax_pf_check_price_tracker', array($this, 'check_price_tracker'));
		add_action('wp_ajax_pf_set_price_tracker', array($this, 'set_price_tracker'));
		add_action('wp_ajax_pf_delete_price_tracker', array($this, 'delete_price_tracker'));
		add_action('pf_price_tracker_cron', array($this, 'run_price_tracker_cron'));
		add_action('wp_ajax_pf_update_email_preferences', array($this, 'update_email_preferences'));
		add_action('wp_ajax_pf_change_password', array($this, 'change_password'));
		add_action('wp_ajax_pf_change_email', array($this, 'change_email'));
		add_action('template_redirect', array($this, 'check_user_preferences'));
		if (!wp_next_scheduled('pf_price_tracker_cron')) {
            wp_schedule_event(time(), 'hourly', 'pf_price_tracker_cron');
        }
		
		
		require_once PF_PLUGIN_DIR . 'includes/api/deals-email.php';
        $this->deals_email = new Deals_Email($this);

        add_action('wp', array($this, 'schedule_deals_email_cron'));
        add_action('send_deals_emails_cron', array($this, 'send_deals_emails_cron'));
		
    }
	
	
	public function schedule_deals_email_cron() {
        if (!wp_next_scheduled('send_deals_emails_cron')) {
            // Schedule for next Tuesday at 5 PM USA time (assuming Eastern Time)
            $schedule_time = strtotime('next Tuesday 5:00pm America/New_York');
            wp_schedule_event($schedule_time, 'weekly', 'send_deals_emails_cron');
        }
    }

    public function send_deals_emails_cron() {
        $this->deals_email->send_deals_email();
    }
	
	
	public function run_price_tracker_cron() {
        require_once PF_PLUGIN_DIR . 'price-tracker-cron.php';
        $cron = new Price_Tracker_Cron($this);
        $cron->run();
    }

    public function enqueue_scripts() {
    wp_enqueue_style('product-functionality', PF_PLUGIN_URL . 'assets/css/product-functionality.css', array(), PF_PLUGIN_VERSION);
    
    // Enqueue general functionality script on all pages
    wp_enqueue_script('product-functionality-general', PF_PLUGIN_URL . 'assets/js/general-functionality.js', array(), PF_PLUGIN_VERSION, true);
    wp_localize_script('product-functionality-general', 'pf_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pf_nonce')
    ));
    
    // Enqueue product reviews script only on product pages
        if(is_singular('products')){
			wp_enqueue_script('product-functionality-reviews', PF_PLUGIN_URL . 'assets/js/product-reviews.js', array('product-functionality-general'), PF_PLUGIN_VERSION, true);
			wp_localize_script('product-functionality-reviews', 'pf_reviews_ajax', array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('pf_nonce')
			));
		}
		
		if(is_singular('products') || is_page('account') || has_tag('review') || is_single('14699')){
			wp_enqueue_style('price-tracker', PF_PLUGIN_URL . 'assets/css/price-tracker.css', array(), PF_PLUGIN_VERSION);
			wp_enqueue_script('price-tracker', PF_PLUGIN_URL . 'assets/js/price-tracker.js', array('product-functionality-general'), PF_PLUGIN_VERSION, true);
			wp_localize_script('price-tracker', 'pf_tracker_ajax', array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('pf_nonce')
			));
		}
    
		if (is_page(14630)) {
            wp_enqueue_script('pf-reset-password', PF_PLUGIN_URL . 'assets/js/reset-password.js', array(), PF_PLUGIN_VERSION, true);
            wp_localize_script('pf-reset-password', 'pfResetVars', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('pf_nonce'),
                'loginUrl' => wp_login_url()
            ));
        }
		
		if (is_page('account') || is_page('email-preferences')) {
			wp_enqueue_style('account', PF_PLUGIN_URL . 'assets/css/account.css', array(), PF_PLUGIN_VERSION);
			wp_enqueue_style('datatables', PF_PLUGIN_URL . 'assets/css/datatables.min.css', array(), PF_PLUGIN_VERSION);
            wp_enqueue_script('account', PF_PLUGIN_URL . 'assets/js/account.js', array(), PF_PLUGIN_VERSION, true);
            wp_enqueue_script('datatables', PF_PLUGIN_URL . 'assets/js/datatables.min.js', array('jquery'), PF_PLUGIN_VERSION, true);
            wp_localize_script('account', 'pfResetVars', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('pf_nonce')
            ));
			
        }

}

private function update_mailchimp_subscription($user_id, $new_status) {
    // Mailchimp API credentials
    $api_key = 'c6c57faaa405c282b02ced6a31fd97db-us21';
    $list_id = '94b93c485d';

    // Get user data
    $user = get_userdata($user_id);
    $email = $user->user_email;
    $first_name = $user->first_name;
    $last_name = $user->last_name;

    error_log("Updating Mailchimp subscription for user ID: $user_id, Email: $email, New Status: $new_status");

    try {
        $mailchimp = new MailchimpAPI($api_key);
        $subscriber_hash = md5(strtolower($email));

        $mailchimp_status = $new_status === 1 ? 'subscribed' : 'unsubscribed';
        
        $data = [
            'email_address' => $email,
            'status' => $mailchimp_status,
            'merge_fields' => [
                'FNAME' => $first_name,
                'LNAME' => $last_name
            ]
        ];

        error_log("Sending request to Mailchimp API: " . json_encode($data));
        $result = $mailchimp->addOrUpdateListMember($list_id, $subscriber_hash, $data);

        if ($result && isset($result->id)) {
            error_log("Mailchimp API request successful. Member ID: " . $result->id . ", New Status: " . $result->status);
            return true; // Indicate success
        } else {
            error_log("Mailchimp API request failed. Response: " . json_encode($result));
            return false; // Indicate failure
        }
    } catch (Exception $e) {
        error_log('Mailchimp API Error: ' . $e->getMessage());
        error_log('Mailchimp API Error Stack Trace: ' . $e->getTraceAsString());
        return false; // Indicate failure
    }
}


public function change_email() {
    error_log("change_email function called");
    error_log("POST data: " . print_r($_POST, true));

    try {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'pf_nonce')) {
            error_log("Nonce verification failed");
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }

        if (!is_user_logged_in()) {
            error_log("User not logged in");
            wp_send_json_error(array('message' => 'User not logged in'));
            return;
        }

        $user = wp_get_current_user();
        $new_email = sanitize_email($_POST['new_email']);
        $current_password = $_POST['current_password'];

        error_log("User ID: " . $user->ID . ", Current email: " . $user->user_email . ", New email: " . $new_email);

        // Server-side validation
        if (!is_email($new_email)) {
            error_log("Invalid email address: " . $new_email);
            wp_send_json_error(array('message' => 'Invalid email address'));
            return;
        }

        if (email_exists($new_email)) {
            error_log("Email already in use: " . $new_email);
            wp_send_json_error(array('message' => 'This email address is already in use'));
            return;
        }

        // Verify current password
        if (!wp_check_password($current_password, $user->user_pass, $user->ID)) {
            error_log("Incorrect password for user ID: " . $user->ID);
            wp_send_json_error(array('message' => 'Current password is incorrect'));
            return;
        }

        // Change the email
        $old_email = $user->user_email;
        $update_result = wp_update_user(array('ID' => $user->ID, 'user_email' => $new_email));

        if (is_wp_error($update_result)) {
            error_log("Failed to update email. WP_Error: " . $update_result->get_error_message());
            wp_send_json_error(array('message' => 'Failed to update email. Please try again.'));
            return;
        }

        error_log("Email updated successfully in WordPress");

        // Update Mailchimp subscription if user is subscribed
		$is_subscribed = (int)get_user_meta($user->ID, 'newsletter_subscription', true) === 1;
		if ($is_subscribed) {
			error_log("Attempting to update Mailchimp subscription");
			try {
				$this->update_mailchimp_email($old_email, $new_email, $user);
				error_log("Mailchimp subscription updated successfully");
			} catch (Exception $e) {
				error_log("Error updating Mailchimp subscription: " . $e->getMessage());
				error_log("Mailchimp error stack trace: " . $e->getTraceAsString());
				// Continue execution, as the email change was successful in WordPress
			}
		} else {
			error_log("User not subscribed to newsletter, skipping Mailchimp update");
		}

        // Send confirmation emails
        error_log("Attempting to send confirmation emails");
        try {
            $this->send_email_change_notifications($user, $old_email, $new_email);
            error_log("Confirmation emails sent successfully");
        } catch (Exception $e) {
            error_log("Error sending confirmation emails: " . $e->getMessage());
            error_log("Email error stack trace: " . $e->getTraceAsString());
            // Continue execution, as the email change was successful
        }

        error_log("Email change process completed successfully");
        wp_send_json_success(array('message' => 'Email changed successfully. Please check your new email for confirmation.'));
    } catch (Exception $e) {
        error_log('Unexpected error in change_email: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        wp_send_json_error(array('message' => 'An unexpected error occurred while changing the email'));
    }
}

private function update_mailchimp_email($old_email, $new_email, $user) {
    error_log("Starting Mailchimp email update process");
    // Mailchimp API credentials
    $api_key = 'c6c57faaa405c282b02ced6a31fd97db-us21';
    $list_id = '94b93c485d';

    try {
        $mailchimp = new MailchimpAPI($api_key);

        // Unsubscribe old email
        $old_subscriber_hash = md5(strtolower($old_email));
        error_log("Attempting to unsubscribe old email: " . $old_email);
        $unsubscribe_result = $mailchimp->makeRequest('PATCH', "/lists/$list_id/members/$old_subscriber_hash", [
            'status' => 'unsubscribed'
        ]);
        error_log("Unsubscribe result: " . json_encode($unsubscribe_result));

        // Subscribe new email
        $new_subscriber_hash = md5(strtolower($new_email));
        error_log("Attempting to subscribe new email: " . $new_email);
        $subscribe_result = $mailchimp->makeRequest('PUT', "/lists/$list_id/members/$new_subscriber_hash", [
            'email_address' => $new_email,
            'status_if_new' => 'subscribed',
            'status' => 'subscribed',
            'merge_fields' => [
                'FNAME' => $user->first_name,
                'LNAME' => $user->last_name
            ]
        ]);
        error_log("Subscribe result: " . json_encode($subscribe_result));

        error_log("Mailchimp subscription updated successfully");
    } catch (Exception $e) {
        error_log('Error updating Mailchimp subscription: ' . $e->getMessage());
        error_log('Mailchimp error stack trace: ' . $e->getTraceAsString());
        // Don't throw the exception, just log it
    }
}


private function send_email_change_notifications($user, $old_email, $new_email) {
    error_log("Starting email change notification process");
    $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    $site_url = get_option('siteurl');

    // Email to old address
    $subject_old = sprintf('[%s] Notice of Email Change', $site_name);
    $content_old = $this->generate_email_paragraph(sprintf("Hi %s,", $user->display_name));
    $content_old .= $this->generate_email_paragraph(sprintf("This notice confirms that your email address on %s was changed from %s to %s.", $site_name, $old_email, $new_email));
    $content_old .= $this->generate_email_paragraph("If you did not make this change, please contact the site administrator immediately.");
    $content_old .= $this->generate_email_button($site_url, "Visit Site");

    error_log("Attempting to send email to old address: " . $old_email);
    $sent_old = $this->send_html_email($old_email, $subject_old, $content_old);
    error_log("Email to old address sent: " . ($sent_old ? "Yes" : "No"));

    // Email to new address
    $subject_new = sprintf('[%s] Email Changed', $site_name);
    $content_new = $this->generate_email_paragraph(sprintf("Hi %s,", $user->display_name));
    $content_new .= $this->generate_email_paragraph(sprintf("This notice confirms that your email address on %s was changed to %s.", $site_name, $new_email));
    $content_new .= $this->generate_email_paragraph("If you did not make this change, please contact the site administrator immediately.");
    $content_new .= $this->generate_email_button($site_url, "Visit Site");

    error_log("Attempting to send email to new address: " . $new_email);
    $sent_new = $this->send_html_email($new_email, $subject_new, $content_new);
    error_log("Email to new address sent: " . ($sent_new ? "Yes" : "No"));

    if (!$sent_old || !$sent_new) {
        throw new Exception("Failed to send one or both confirmation emails");
    }

    error_log("Email change notifications sent successfully");
}

    public function get_reviews() {
        check_ajax_referer('pf_nonce', '_wpnonce');
        require_once PF_PLUGIN_DIR . 'includes/api/reviews.php';
    }
	
public function update_email_preferences() {
    try {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'pf_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'User not logged in'));
            return;
        }
        
        $user_id = get_current_user_id();
        
        // Validate and update price trackers emails
        $price_trackers = isset($_POST['price_trackers']) && $_POST['price_trackers'] === '1' ? 1 : 0;
        update_user_meta($user_id, 'price_trackers_emails', $price_trackers);
        
        // Validate and update sales roundup emails
        $sales_roundup = isset($_POST['sales_roundup']) && $_POST['sales_roundup'] === '1' ? 1 : 0;
        update_user_meta($user_id, 'sales_roundup_emails', $sales_roundup);
        
        // Validate and update sales roundup frequency
        $valid_frequencies = ['weekly', 'bi-weekly', 'monthly'];
        $sales_roundup_frequency = in_array($_POST['sales_roundup_frequency'], $valid_frequencies) 
            ? $_POST['sales_roundup_frequency'] 
            : 'weekly'; // Default to weekly if invalid input
        update_user_meta($user_id, 'sales_roundup_frequency', $sales_roundup_frequency);
        
        // Handle newsletter subscription
        $new_newsletter_status = isset($_POST['newsletter']) && $_POST['newsletter'] === '1' ? 1 : 0;
        $current_newsletter_status = (int)get_user_meta($user_id, 'newsletter_subscription', true);
        
        // Check if there's a change in newsletter subscription
        if ($new_newsletter_status !== $current_newsletter_status) {
            $mailchimp_update_success = $this->update_mailchimp_subscription($user_id, $new_newsletter_status);
            
            if ($mailchimp_update_success) {
                update_user_meta($user_id, 'newsletter_subscription', $new_newsletter_status);
            } else {
                wp_send_json_error(array('message' => 'Failed to update newsletter subscription'));
                return;
            }
        }
        
        // Set the email_preferences_set flag
        update_user_meta($user_id, 'email_preferences_set', '1');
        
        wp_send_json_success(array('message' => 'Email preferences updated successfully'));
    } catch (Exception $e) {
        error_log('Error in update_email_preferences: ' . $e->getMessage());
        wp_send_json_error(array('message' => 'An error occurred while updating email preferences'));
    }
}
	
public function change_password() {
    error_log('change_password function called');
    error_log('POST data: ' . print_r($_POST, true));

    try {
        if (!check_ajax_referer('pf_nonce', '_wpnonce', false)) {
            error_log('Nonce verification failed');
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }

        if (!is_user_logged_in()) {
            error_log('User not logged in');
            wp_send_json_error(array('message' => 'User not logged in'));
            return;
        }

        $user = wp_get_current_user();
        $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_new_password = isset($_POST['confirm_new_password']) ? $_POST['confirm_new_password'] : '';

        error_log('User ID: ' . $user->ID);
        error_log('Current password provided: ' . (empty($current_password) ? 'No' : 'Yes'));
        error_log('New password length: ' . strlen($new_password));

        // Server-side validation
        if (strlen($new_password) < 8) {
            error_log('New password too short');
            wp_send_json_error(array('message' => 'New password must be at least 8 characters long'));
            return;
        }

        if ($new_password !== $confirm_new_password) {
            error_log('New passwords do not match');
            wp_send_json_error(array('message' => 'New passwords do not match'));
            return;
        }

        // Verify current password
        if (!wp_check_password($current_password, $user->user_pass, $user->ID)) {
            error_log('Current password is incorrect');
            wp_send_json_error(array('message' => 'Current password is incorrect'));
            return;
        }

        // Change the password
        wp_set_password($new_password, $user->ID);
        error_log('Password changed successfully');

        // Log the user out of all sessions
        wp_logout();
        error_log('User logged out');

        wp_send_json_success(array('message' => 'Password changed successfully. Please log in with your new password.'));
    } catch (Exception $e) {
        error_log('Error in change_password: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        wp_send_json_error(array('message' => 'An error occurred while changing the password: ' . $e->getMessage()));
    }
}
	
	

/**public function restrict_login_access() {
        global $pagenow;
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        $loginSocial = isset($_GET['loginSocial']) ? $_GET['loginSocial'] : '';

        // Check for wp-login.php or the custom login page
        if (($pagenow == 'wp-login.php' || $_SERVER['REQUEST_URI'] === '/ML6pXX0UIX322323dfs/') 
            && !is_user_logged_in() 
            && $action != 'logout' 
            && empty($loginSocial)) {
            wp_redirect(home_url()); // Redirect to home page
            exit();
        }
    }**/

public function logout() {
        if (!check_ajax_referer('pf_nonce', '_wpnonce', false)) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        if (!file_exists(PF_PLUGIN_DIR . 'includes/api/logout.php')) {
            wp_send_json_error('Logout file not found');
            return;
        }

        require_once PF_PLUGIN_DIR . 'includes/api/logout.php';
    }

    public function user_review_status() {
		
    
    if (!check_ajax_referer('pf_nonce', '_wpnonce', false)) {
        wp_die('Invalid nonce');
    }

		
        check_ajax_referer('pf_nonce', '_wpnonce');
        require_once PF_PLUGIN_DIR . 'includes/api/user_review_status.php';
    }

    public function submit_review() {
  
  if (!check_ajax_referer('pf_nonce', '_wpnonce', false)) {
    wp_send_json_error('Invalid nonce');
  }

        check_ajax_referer('pf_nonce', '_wpnonce');
        require_once PF_PLUGIN_DIR . 'includes/api/submit_review.php';
		wp_send_json_success('Review submitted successfully');
    }
	
public function check_price_data() {
    require_once PF_PLUGIN_DIR . 'includes/api/price_tracker.php';
    pf_check_price_data();
}

public function check_price_tracker() {
    require_once PF_PLUGIN_DIR . 'includes/api/price_tracker.php';
    pf_check_price_tracker();
}

public function set_price_tracker() {
    require_once PF_PLUGIN_DIR . 'includes/api/price_tracker.php';
    pf_set_price_tracker();
}

public function delete_price_tracker() {
    require_once PF_PLUGIN_DIR . 'includes/api/price_tracker.php';
    pf_delete_price_tracker();
}

public function login() {
    
    if (!check_ajax_referer('pf_nonce', '_wpnonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    if (!check_ajax_referer('pf_nonce', '_wpnonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    
    if (!file_exists(PF_PLUGIN_DIR . 'includes/api/login.php')) {
        wp_send_json_error('Login file not found');
        return;
    }
    
    require_once PF_PLUGIN_DIR . 'includes/api/login.php';
}

    function refresh_pf_nonce() {
    wp_send_json_success(array('nonce' => wp_create_nonce('pf_nonce')));
}

    public static function truncate_review($text, $max_length = 150) {
        if (strlen($text) <= $max_length) return $text;
        $truncated = substr($text, 0, $max_length);
        return '
            <span class="pf-truncated-text">' . nl2br($truncated) . '...</span>
            <span class="pf-full-text" style="display:none;">' . nl2br($text) . '</span>
            <a href="#" class="pf-show-more">Show more</a>
        ';
    }
	
	public function register() {
		
		if (!check_ajax_referer('pf_nonce', '_wpnonce', false)) {
			wp_send_json_error('Invalid nonce');
			return;
		}
		
		
		if (!file_exists(PF_PLUGIN_DIR . 'includes/api/register.php')) {
			wp_send_json_error('Registration file not found');
			return;
		}
		
		require_once PF_PLUGIN_DIR . 'includes/api/register.php';
	}
	
	public function handle_lost_password() {
		require_once PF_PLUGIN_DIR . 'includes/api/lost_password.php';
	}
	
public function custom_new_user_notification_email($wp_new_user_notification_email, $user, $blogname) {
    $user_login = stripslashes($user->user_login);
    $user_email = stripslashes($user->user_email);
    
    $content = $this->generate_email_paragraph(sprintf(__('New user registration on your site %s:'), $blogname));
    $content .= $this->generate_email_paragraph(sprintf(__('Username: %s'), $user_login));
    $content .= $this->generate_email_paragraph(sprintf(__('Email: %s'), $user_email));
    
    $wp_new_user_notification_email['subject'] = sprintf(__('[%s] New User Registration'), $blogname);
    $wp_new_user_notification_email['message'] = $content;
    $wp_new_user_notification_email['headers'] = array('Content-Type: text/html; charset=UTF-8');
    
    return $wp_new_user_notification_email;
}

public function custom_user_notification_email($wp_new_user_notification_email, $user, $blogname) {
    $user_login = stripslashes($user->user_login);
    $site_url = 'https://eridehero.com'; // Base URL of your site
    
    $content = $this->generate_email_paragraph(sprintf(__('Welcome to %s!'), $blogname));
    $content .= $this->generate_email_paragraph(sprintf(__('Hello %s,'), $user_login));
    $content .= $this->generate_email_paragraph(__('Thank you for joining our community of e-scooter enthusiasts! Your account has been successfully created, and I\'m thrilled to have you on board.'));
    
    $content .= $this->generate_email_paragraph(__('Here\'s what you can do now on ERideHero:'));
    
    $features = array(
        '🔍 ' . __('E-Scooter Finder') => $site_url . '/tool/electric-scooter-finder/',
        '⚔️ ' . __('Head-To-Head Comparison') => $site_url . '/tool/electric-scooter-comparison/',
        '💰 ' . __('E-Scooter Deals') => $site_url . '/tool/electric-scooter-deals/',
    );
    
    $content .= '<ul style="padding-left: 20px; margin-bottom: 16px;">';
    foreach ($features as $feature => $url) {
        $content .= '<li style="margin-bottom: 8px;"><a href="' . esc_url($url) . '" style="color: #5e2ced; text-decoration: none;"><b>' . $feature . '</b></a>: ';
        switch ($feature) {
            case '🔍 ' . __('E-Scooter Finder'):
                $content .= __('Search and compare over 200 models using 40+ filters to find your perfect ride.');
                break;
            case '⚔️ ' . __('Head-To-Head Comparison'):
                $content .= __('Pit models against each other to see which one comes out on top.');
                break;
            case '💰 ' . __('E-Scooter Deals'):
                $content .= __('Discover REAL deals based on real historical pricing data from the last 6 months (avoid fake sales/deals).');
                break;
        }
        $content .= '</li>';
    }
    $content .= '<li style="margin-bottom: 8px;">' . __('<b>🔔 Price Trackers:</b> Set alerts for your favorite models and get notified when they drop to your target price.') . '</li>';
    $content .= '<li style="margin-bottom: 8px;">' . __('<b>⭐ User Reviews:</b> Explore real reviews from other riders and contribute with your own opinions to help other consumers.') . '</li>';
    $content .= '<li style="margin-bottom: 8px;">' . __('And much more!') . '</li>';
    $content .= '</ul>';
    
    $content .= $this->generate_email_paragraph(__('As the sole creator of ERideHero, I urge all our members to participate in building a strong community that\'ll help consumers find the right tide. Review the scooters you\'ve tried and share your feedback about the platform with me to help improve it.'));
    
    // Add a login button
    $login_url = $site_url . '/login-register';
    $content .= $this->generate_email_button($login_url, __('Log In to ERideHero'));
    
    $content .= $this->generate_email_paragraph(__('If you have any questions or need assistance, don\'t hesitate to reach out. I\'m always here to help.'));
    $content .= $this->generate_email_paragraph(__('Ride on!'));
    $content .= $this->generate_email_paragraph(__('Rasmus Barslund'));
    $content .= $this->generate_email_paragraph(__('Founder of ERideHero'));
    
    return array(
        'subject' => sprintf(__('Welcome to %s - Your E-Scooter Adventure Begins!'), $blogname),
        'message' => $content,
        'headers' => array('Content-Type: text/html; charset=UTF-8')
    );
}

public function send_new_user_notification($user_id) {
    $user = get_userdata($user_id);
    $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

    // Check if this is a social login registration
    $is_social_login = get_user_meta($user_id, 'social_login_provider', true);

    $custom_email = $this->custom_user_notification_email(array(), $user, $blogname);
    
    // Modify email content based on registration type
    if ($is_social_login) {
        $custom_email['message'] = str_replace(
            __('You can now log in to your account using the password you set during registration.'),
            __('You can now log in to your account using your social media credentials.'),
            $custom_email['message']
        );
    }

    $sent = $this->send_html_email($user->user_email, $custom_email['subject'], $custom_email['message']);
    
    if ($sent) {
        error_log("Welcome email sent successfully to " . $user->user_email);
    } else {
        error_log("Failed to send welcome email to " . $user->user_email);
    }
}
	
	public function handle_reset_password() {
    if (is_user_logged_in()) {
        wp_send_json_error(array('message' => 'You are already logged in.'));
        return;
    }
    try {
        require_once PF_PLUGIN_DIR . 'includes/api/reset-password.php';
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'An error occurred on the server.'));
    }
}





public function generate_email_paragraph($text) {
    return '<p style="font-family: Helvetica, sans-serif; font-size: 16px; font-weight: normal; margin: 0; margin-bottom: 16px;">' . $text . '</p>';
}

public function generate_email_button($url, $text) {
    return '
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: auto; margin-bottom: 16px;">
        <tr>
            <td style="border-radius: 4px; background-color: #5e2ced; text-align: center;">
                <a href="' . esc_url($url) . '" target="_blank" style="border: solid 2px #5e2ced; border-radius: 4px; box-sizing: border-box; cursor: pointer; display: inline-block; font-size: 16px; font-weight: bold; margin: 0; padding: 12px 24px; text-decoration: none; background-color: #5e2ced; color: #ffffff;" class="emailbtn">' . esc_html($text) . '</a>
            </td>
        </tr>
    </table>';
}

public function generate_email_link($url, $text) {
    return '<a href="' . esc_url($url) . '" style="color: #5e2ced; text-decoration: underline;">' . esc_html($text) . '</a>';
}

public function send_html_email($to, $subject, $content) {
    require_once PF_PLUGIN_DIR . 'email-template.php';
    $html = get_email_template($content);
    
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    return wp_mail($to, $subject, $html, $headers);
}


public function check_user_preferences() {
    // Only proceed if the user is logged in
    if (!is_user_logged_in()) {
        return;
    }

    // Get the current user
    $user_id = get_current_user_id();

    // Check if email preferences are set
    $email_preferences_set = get_user_meta($user_id, 'email_preferences_set', true);

    // If preferences are not set, redirect to the email preferences page
    if ($email_preferences_set !== '1') {
        // Don't redirect if already on the email preferences page
        if (!is_page('email-preferences')) {
            $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            $redirect_url = add_query_arg('redirect', urlencode($current_url), home_url('/email-preferences/'));
            wp_safe_redirect($redirect_url);
            exit;
        }
    }
}

	
}