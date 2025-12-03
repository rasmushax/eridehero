<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Price_Tracker_Cron {
    private $product_functionality;

    public function __construct($product_functionality) {
        $this->product_functionality = $product_functionality;
    }

	public function run() {
        // Get users who have opted in for price tracker emails
        $users = get_users(array(
            'meta_key' => 'price_trackers_emails',
            'meta_value' => '1'
        ));

        foreach ($users as $user) {
            $deals = $this->collect_deals_for_user($user->ID);
            if (!empty($deals)) {
                $this->send_notification($user->ID, $deals);
            }
        }
    }

	private function collect_deals_for_user($user_id) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'price_trackers';

		$trackers = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM $table_name WHERE user_id = %d",
			$user_id
		), ARRAY_A);

		$deals = [];
		$current_time = current_time('mysql');

		foreach ($trackers as $tracker) {
			$product_id = $tracker['product_id'];
			$start_price = $tracker['start_price'];
			$target_price = $tracker['target_price'];
			$price_drop = $tracker['price_drop'];
			$last_notified_price = $tracker['last_notified_price'];
			$last_notification_time = $tracker['last_notification_time'];

			// Check if 24 hours have passed since the last notification
			if ($last_notification_time) {
				$time_since_last_notification = strtotime($current_time) - strtotime($last_notification_time);
				if ($time_since_last_notification < 24 * 60 * 60) {
					continue; // Skip this product if less than 24 hours have passed
				}
			}

			$prices = getPrices($product_id);
			
			// Skip if no prices returned or if first price is invalid
			if (empty($prices) || !isset($prices[0]['price'])) {
				continue;
			}
			
			$current_price = $prices[0]['price'];
			
			// Skip if current price is 0, null, or empty
			if (empty($current_price) || $current_price <= 0) {
				continue;
			}

			$compare_price = $last_notified_price !== null ? $last_notified_price : $start_price;
			
			// Additional validation for compare price
			if (empty($compare_price) || $compare_price <= 0) {
				continue;
			}

			$should_notify = false;
			$notification_type = '';

			if ($target_price !== null && $current_price <= $target_price && $current_price < $compare_price) {
				$should_notify = true;
				$notification_type = 'target';
			} elseif ($price_drop !== null) {
				$price_difference = $compare_price - $current_price;
				if ($price_difference >= $price_drop) {
					$should_notify = true;
					$notification_type = 'drop';
				}
			}

			if ($should_notify) {
				$deals[] = [
					'product_id' => $product_id,
					'current_price' => $current_price,
					'compare_price' => $compare_price,
					'notification_type' => $notification_type
				];
			}
		}

		return $deals;
	}

    private function send_notification($user_id, $deals) {
    if (empty($deals)) {
        return;
    }

    $user = get_userdata($user_id);
	$user_name = $user->first_name ? $user->first_name : $user->display_name;
    $deal_count = count($deals);
    $subject = $deal_count == 1 
        ? __('Price Drop Alert: 1 Deal for You!')
        : sprintf(__('Price Drop Alert: %d New Deals for You!'), $deal_count);

    $content = '';
    $content .= $this->product_functionality->generate_email_paragraph(
        sprintf(__('Hi %s,'), $user_name)
    );

    $content .= $this->product_functionality->generate_email_paragraph(
        $deal_count == 1
            ? __('Great news! We\'ve spotted a hot deal on an item you\'re tracking:')
            : sprintf(__('Great news! We\'ve spotted %d hot deals on items you\'re tracking:'), $deal_count)
    );

    foreach ($deals as $deal) {
        $product = get_post($deal['product_id']);
        $product_title = $product->post_title;
        $product_url = get_permalink($deal['product_id']);

        $acf_fields = get_fields($deal['product_id']);
        $thumbnail_id = isset($acf_fields['big_thumbnail']) ? $acf_fields['big_thumbnail'] : get_post_thumbnail_id($deal['product_id']);

        $price_difference = $deal['compare_price'] - $deal['current_price'];
        $percentage_change = ($price_difference / $deal['compare_price']) * 100;

        $tracking_users = $this->get_tracking_users_count($deal['product_id']);

        // Start two-column layout
        $content .= '<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 20px;margin-top:20px;padding-top:2em;border-top:1px solid #f4f4f4;"><tr><td width="30%" valign="top">';

        // Left column: Product image
        if ($thumbnail_id) {
            $image_url = wp_get_attachment_image_url($thumbnail_id, 'thumbnail');
            if ($image_url) {
                $content .= sprintf('<img src="%s" alt="%s" style="max-width: 100%%; height: auto; margin-bottom: 10px;">', esc_url($image_url), esc_attr($product_title));
            }
        }

        $content .= '</td><td width="70%" valign="top">';

        // Right column: Price info and CTA button
        $content .= $this->product_functionality->generate_email_paragraph(
            sprintf(__('<strong>%s</strong>'), $product_title)
        );

        $content .= $this->product_functionality->generate_email_paragraph(
            sprintf(__('📉 Price dropped from %s to %s'), $this->format_price($deal['compare_price']), $this->format_price($deal['current_price']))
        );

        $content .= $this->product_functionality->generate_email_paragraph(
            sprintf(__('💰 You save: %s (%s off!)'), $this->format_price($price_difference), number_format($percentage_change, 0) . '%')
        );

        $content .= $this->product_functionality->generate_email_button($product_url, __('View Deal Now'));

        if ($tracking_users > 5) {
            $content .= $this->product_functionality->generate_email_paragraph(
                sprintf(__('🔥 %d other users are tracking this item.'), $tracking_users - 1)
            );
        }

        $content .= '</td></tr></table>';
        // End two-column layout
    }
	
	$content .= '<div style="width:100%;height:1px;background-color:#f4f4f4;margin-top:20px;margin-bottom:20px;"></div>';

    $content .= $this->product_functionality->generate_email_paragraph(
        __('Hurry, prices can change quickly! These alerts are based on your preferences.')
    );

    $content .= $this->product_functionality->generate_email_paragraph(
        __('Ride safe,')
    );

    $content .= $this->product_functionality->generate_email_paragraph(
        __('Rasmus from ERideHero')
    );

    $manage_alerts_url = home_url('/account/'); // Adjust this URL as needed
    $content .= $this->product_functionality->generate_email_paragraph(
        sprintf(__('P.S. Manage your alerts or unsubscribe <a href="%s">here</a>.'), $manage_alerts_url)
    );

    $sent = $this->product_functionality->send_html_email($user->user_email, $subject, $content);

    // Log for debugging
    error_log("Notification sent: " . print_r([
        'user_id' => $user_id,
        'deals_count' => $deal_count,
        'email_sent' => $sent
    ], true));

    // Update last_notified_price for all deals
    $this->update_last_notified_prices($user_id, $deals);
}

	private function update_last_notified_prices($user_id, $deals) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'price_trackers';
		$current_time = current_time('mysql');

		foreach ($deals as $deal) {
			$wpdb->update(
				$table_name,
				array(
					'last_notified_price' => $deal['current_price'],
					'last_notification_time' => $current_time
				),
				array('user_id' => $user_id, 'product_id' => $deal['product_id'])
			);
		}
	}

    private function format_price($price) {
        return '$' . number_format($price, 2);
    }

    private function get_tracking_users_count($product_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'price_trackers';

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $table_name WHERE product_id = %d",
            $product_id
        ));
    }
}