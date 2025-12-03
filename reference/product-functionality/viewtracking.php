<?php

// Register AJAX endpoints for view tracking
add_action('wp_ajax_track_product_view', 'ajax_track_product_view');
add_action('wp_ajax_nopriv_track_product_view', 'ajax_track_product_view');

function ajax_track_product_view() {
    global $wpdb;
    
    $product_id = intval($_POST['product_id']);
    
    if (!$product_id) {
        wp_die('0');
    }
    
    // Get visitor IP
    $ip_address = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip_address = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip_address = $_SERVER['REMOTE_ADDR'];
    }
    
    $ip_hash = hash('sha256', $ip_address);
    
    // Bot detection from user agent
    if (is_bot_user_agent()) {
        wp_die('0');
    }
    
    // Check if already viewed today
    $existing_view = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}product_views 
        WHERE product_id = %d 
        AND ip_hash = %s 
        AND DATE(view_date) = CURDATE()",
        $product_id,
        $ip_hash
    ));
    
    if ($existing_view > 0) {
        wp_die('already_viewed');
    }
    
    // Record the view
    $wpdb->insert(
        $wpdb->prefix . 'product_views',
        array(
            'product_id' => $product_id,
            'ip_hash' => $ip_hash,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        )
    );
    
    // Cleanup old views (1% chance)
    if (rand(1, 100) === 1) {
        $wpdb->query("DELETE FROM {$wpdb->prefix}product_views WHERE view_date < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    }
    
    wp_die('success');
}

// Enqueue tracking script on product pages
add_action('wp_enqueue_scripts', 'enqueue_product_view_tracker');

function enqueue_product_view_tracker() {
    if (is_singular('products')) {
        wp_enqueue_script(
            'product-view-tracker', 
            plugin_dir_url(__FILE__) . 'assets/js/view-tracker.js', 
            array(), 
            '1.0', 
            true
        );
        
        wp_localize_script('product-view-tracker', 'view_tracker_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'product_id' => get_the_ID()
        ));
    }
}

// Bot detection function (same as before)
function is_bot_user_agent() {
    if (!isset($_SERVER['HTTP_USER_AGENT'])) {
        return true;
    }
    
    $user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
    
    $bot_patterns = array(
        'bot', 'crawler', 'spider', 'scraper', 'facebookexternalhit',
        'slurp', 'mediapartners', 'adsbot', 'googlebot', 'bingbot',
        'yandexbot', 'duckduckbot', 'baiduspider', 'twitterbot',
        'linkedinbot', 'whatsapp', 'slackbot', 'telegrambot',
        'discordbot', 'pinterest', 'vkshare', 'facebot', 'ia_archiver',
        'semrushbot', 'dotbot', 'ahrefsbot', 'mj12bot', 'dataforseo',
        'serpstatbot', 'siteauditbot', 'uptimerobot', 'gtmetrix',
        'lighthouse', 'pagespeed', 'chrome-lighthouse', 'headless',
        'phantomjs', 'selenium', 'puppeteer', 'prerender',
        'wget', 'curl', 'python', 'java/', 'perl/', 'ruby/',
        'go-http-client', 'okhttp', 'apache-httpclient','wp-rocket',"WP-Rocket","Saas"
    );
    
    foreach ($bot_patterns as $pattern) {
        if (strpos($user_agent, $pattern) !== false) {
            return true;
        }
    }
    
    $browser_signatures = array('mozilla', 'chrome', 'safari', 'firefox', 'edge', 'opera');
    $has_browser_signature = false;
    foreach ($browser_signatures as $signature) {
        if (strpos($user_agent, $signature) !== false) {
            $has_browser_signature = true;
            break;
        }
    }
    
    return !$has_browser_signature;
}