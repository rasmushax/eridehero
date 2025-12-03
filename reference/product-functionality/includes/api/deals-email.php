<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Deals_Email {
    private $product_functionality;

    public function __construct($product_functionality) {
        $this->product_functionality = $product_functionality;
    }

    public function send_deals_email() {
        $deals = getDeals(); // Assuming this function is available

        if (empty($deals)) {
            error_log('No deals available for weekly email');
            return;
        }

        $email_content = $this->generate_deals_email_content($deals);
        $subject = 'The Biggest E-Scooter Deals This Week 🔥';

        $users = get_users(array(
            'meta_key' => 'sales_roundup_emails',
            'meta_value' => '1'
        ));

        foreach ($users as $user) {
            $frequency = get_user_meta($user->ID, 'sales_roundup_frequency', true);
            if ($this->should_send_email($user->ID, $frequency)) {
                $this->product_functionality->send_html_email($user->user_email, $subject, $email_content);
                update_user_meta($user->ID, 'last_deals_email_sent', current_time('mysql'));
            }
        }
    }

private function generate_deals_email_content($deals) {
    ob_start();
    $deals_count = count($deals);
    ?>
    <h1 style="font-size: 24px; color: #21273a; margin-bottom: 20px;">The Biggest E-Scooter Deals This Week</h1>
    <p style="font-size: 18px; color: #4b5166; margin-bottom: 20px;">
        These deals are based on real 6-month historical pricing data - no fake deals or inflated 'before' prices, just genuine savings on e-scooters. 🫡
    </p>
    <h2 style="font-size: 20px; color: #21273a; margin-bottom: 20px;">🛴 Top 20 Deals (out of <?=$deals_count?> total)</h2>
    <table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse: separate; border-spacing: 0;overflow:hidden;border:1px solid #e3e8ed;border-radius:10px;">
    <?php
    
    $displayed_deals = array_slice($deals, 0, 20);
    foreach ($displayed_deals as $product) {
        $price_history = maybe_unserialize($product->price_history);
        if (!is_array($price_history)) continue;
        
        // Skip if no 6-month data
        if (!isset($price_history['average_price_6m'])) continue;
        
        $price = splitPrice($product->price);
        $avg_price = number_format($price_history['average_price_6m'], 2); // CHANGED: Use 6-month average
        $discount_percentage = abs(round($product->price_diff_6m)); // CHANGED: Use the pre-calculated 6-month difference
        $image_url = !empty($product->image_url) ? $product->image_url : 'https://eridehero.com/wp-content/uploads/2024/09/Placeholder.png';
        ?>
        <tr>
            <td style="padding: 13px;border-top:1px solid #e3e8ed">
                <table cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                        <td width="1px" style="padding: 5px;background: white;border-radius: 5px;">
                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($product->name); ?>" style="max-width:75px; height:auto;">
                        </td>
                        <td style="padding-left:20px">
                            <h2 style="font-size: 18px; color: #21273a; margin: 0 0 10px 0;">
                                <?php echo $this->product_functionality->generate_email_link($product->permalink, esc_html($product->name), 'color: #21273a; text-decoration: none; font-weight: bold;'); ?>
                            </h2>
                            <p style="font-size: 18px; color: #21273a; font-weight: bold; margin: 0 0 5px 0;">$<?php echo $price['whole']; ?><sup><?php echo $price['fractional']; ?></sup><span style="font-size: 14px; font-weight:400;padding-left:20px;color: #6f768f; text-decoration: line-through;">$<?php echo $avg_price; ?></span></p>
                            <p style="font-size: 14px; color: #2ea961; margin: 0;"><?php echo $discount_percentage; ?>% below 6-month average</p> <!-- CHANGED: Added "6-month" -->
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <?php
    }
    ?>
    </table>
    <?php if ($deals_count > 20) : ?>
    <table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top: 20px;">
        <tr>
            <td align="center">
                <?php 
                $view_all_link = 'https://eridehero.com/tool/electric-scooter-deals/';
                echo $this->product_functionality->generate_email_button($view_all_link, "View All $deals_count Deals");
                ?>
            </td>
        </tr>
    </table>
    <?php endif; ?>
    <p style="font-size: 16px; color: #6f768f; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">
        Don't want to receive these deals anymore or want to change how often you get them? 
        <?php echo $this->product_functionality->generate_email_link('https://eridehero.com/account/?view=settings', 'Update your preferences in your Account Settings', 'color: #1e88e5; text-decoration: underline;'); ?>.
    </p>
    <?php
    $content = ob_get_clean();

    // Use the existing email template
    return $this->product_functionality->generate_email_paragraph($content);
}

    private function should_send_email($user_id, $frequency) {
        $last_sent = get_user_meta($user_id, 'last_deals_email_sent', true);
        if (!$last_sent) return true;

        $weeks_since_last_sent = floor((time() - strtotime($last_sent)) / WEEK_IN_SECONDS);

        switch ($frequency) {
            case 'weekly':
                return true; // Always send for weekly subscribers
            case 'bi-weekly':
                return $weeks_since_last_sent >= 2;
            case 'monthly':
                return $weeks_since_last_sent >= 4;
            default:
                return false;
        }
    }
	
private function get_max_discount($deals) {
    $max_discount = 0;
    foreach ($deals as $deal) {
        // Use the pre-calculated 6-month difference from getDeals()
        if (isset($deal->price_diff_6m)) {
            $discount = abs(round($deal->price_diff_6m));
            $max_discount = max($max_discount, $discount);
        }
    }
    return $max_discount;
}
	
}