<?php
/*
Plugin Name:	My Custom Functionality
Plugin URI:		https://example.com
Description:	My custom functions.
Version:		1.0.0
Author:			Your Name
Author URI:		https://example.com
License:		GPL-2.0+
License URI:	http://www.gnu.org/licenses/gpl-2.0.txt

This plugin is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

This plugin is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with This plugin. If not, see {URI to Plugin License}.
*/


if ( ! defined( 'WPINC' ) ) {
	die;
}

//add_action( 'wp_head', 'sk_google_tag_manager1', 1 );
/**
* Adds Google Tag Manager code in <head> below the <title>.
*/
require_once plugin_dir_path(__FILE__) . 'product_daily_prices.php';



global $pagenow;
if (( $pagenow == 'post.php' ) || (get_post_type() == 'post')) {

    wp_enqueue_style( 'Admin-styles', plugin_dir_url( __FILE__ ).'assets/css/editor.css' );

}
/**
function cwp_register_block_script() {
wp_register_script( 'acf-video', plugin_dir_url(__FILE__) . 'blocks/video/video.js', [ 'jquery' ] );
}
add_action( 'init', 'cwp_register_block_script' );**/


// Our custom post type function
function create_posttype() {
 
    register_post_type( 'Products',
    // CPT Options
        array(
            'labels' => array(
                'name' => __( 'Products' ),
                'singular_name' => __( 'Product' )
            ),
            'public' => true,
            'has_archive' => false,
            'rewrite' => array('slug' => 'products'),
            'show_in_rest' => true,
			'menu_icon' => 'https://eridehero.com/wp-content/uploads/2021/09/electric-scooter-2.png',
			'supports' => array('title')
 
        )
    );
}
// Hooking up our function to theme setup
add_action( 'init', 'create_posttype' );

function getRelationField($var){
	return get_field($var,get_field('relationship')[0]);
}

function format_spec_value($spec_details) {
    if (!isset($spec_details['value']) || $spec_details['value'] === '' || $spec_details['value'] === null) {
        return '<span class="spec-na">N/A</span>'; // Indicate Not Available
    }

    $value = $spec_details['value'];
    $prefix = $spec_details['prefix'] ?? '';
    $suffix = $spec_details['suffix'] ?? '';
    $type = $spec_details['type'] ?? 'text';

    switch ($type) {
        case 'boolean':
            return $value == 1 ? '<span class="spec-yes"><svg><use xlink:href="#icon-check-circle"></use></svg>Yes</span>' : '<span class="spec-no"><svg><use xlink:href="#icon-x-circle"></use></svg>No</span>';
        case 'number':
            // Basic number formatting, could be expanded (e.g., decimals)
            return esc_html($prefix) . number_format(floatval($value), ($value == intval($value)) ? 0 : 2) . esc_html($suffix);
        case 'checkbox':
        case 'multiselect':
        case 'select':
            if (is_array($value)) {
                return esc_html(implode(', ', $value));
            }
            return esc_html($value);
        case 'text':
        default:
            return esc_html($prefix) . esc_html($value) . esc_html($suffix);
    }
}

add_filter( 'rocket_defer_inline_exclusions', function( $inline_exclusions_list ) {
  if ( ! is_array( $inline_exclusions_list ) ) {
    $inline_exclusions_list = array();
  }

  // Duplicate this line if you need to exclude more
  $inline_exclusions_list[] = 'ct_code_block_js_2';

  return $inline_exclusions_list;
} );

/** ToC **/
function auto_id_headings( $content ) {

	$content = preg_replace_callback( '/(\<h[2-3](.*?))\>(.*)(<\/h[2-3]>)/i', function( $matches ) {
		if ( ! stripos( $matches[0], 'id=' ) ) :
			$matches[0] = $matches[1] . $matches[2] . ' id="' . sanitize_title( $matches[3] ) . '">' . $matches[3] . $matches[4];
		endif;
		return $matches[0];
	}, $content );

    return $content;

}

function split_money($amount) {
    // Convert the amount to a float and round to 2 decimal places
    $amount = round((float)$amount, 2);
    
    // Split the amount into whole and fractional parts
    $parts = explode('.', (string)$amount);
    
    // Ensure we always have a fractional part
    $whole = $parts[0];
    $fractional = isset($parts[1]) ? str_pad($parts[1], 2, '0', STR_PAD_RIGHT) : '00';
    
    return [
        'whole' => $whole,
        'fractional' => $fractional
    ];
}

if(1 == 1){
	add_filter( 'the_content', 'auto_id_headings' );
	/**wp_enqueue_script(
        'toc',
        plugin_dir_url( __FILE__ ) . 'assets/js/toc.js',
        array(),
        '1.0',
        true
    );	**/
}

/****/

function getFieldLowercase($var){
	return strtolower(get_field($var));
}

function getTheTag(){
	return get_the_tags()[0]->slug;
}

function getCats(){
	$string = "";
	foreach(get_the_category() as $cat){
		$string .= ",".$cat->cat_ID;
	}
	return substr($string,1);
}

function getCatWithLink(){

	$categories = get_the_category();

	if ( ! empty( $categories ) ) {
		
		$link = get_field('hub_page','category_'.$categories[0]->cat_ID);
   	 	echo '<a class="fat" href="'.esc_html($link).'">'.esc_html( $categories[0]->name ).'</a>';   
	}
}

function getTag(){
	$post_tags = get_the_tags();
	if ( $post_tags ) {
		echo $post_tags[0]->name; 
	}
}

function updated() { 
        return get_the_modified_date();
    }  
add_shortcode("updated", "updated");

function year() { 
        return date("Y");
    }  
add_shortcode("year", "year");  


function getRatingAvg(){
	return get_field('rating:_overall')*10;
}

function getMixitup(){
    wp_enqueue_script(
        'mixitup',
        plugin_dir_url( __FILE__ ) . 'assets/js/mixitup.min.js',
        array(),
        '3.3.1',
        true
    );
}

	
	add_image_size( '500px', 500 );
	add_image_size( '400px', 400 );
	add_image_size( '600px', 600 );
	add_image_size( '800px', 800 );
	
	add_filter( 'image_size_names_choose', 'child_custom_sizes' );

function child_custom_sizes( $sizes ) {

return array_merge( $sizes, array(
	'500px' => 			__( '500 PX' ),
	'400px' => 			__( '400 PX' ),
	'600px' => 			__( '600 PX' ),
	'800px' => 			__( '800 PX' ),
) );
}

add_filter('xmlrpc_enabled', '__return_false');


function getthumbnail(){
	return wp_get_attachment_image_src(get_post_thumbnail_id(),'800px')[0];
}

function getavgrating(){
		round((get_field('rating:_speed') + get_field('rating:_acceleration_hills') + get_field('rating:_range') + get_field('rating:_portability') + get_field('rating:_ride_quality') + get_field('rating:_build_quality') + get_field('rating:_safety') + get_field('rating:_value')) / 8,1);
}

function load_specifications_callback() {
    
    if (!isset($_POST['product_id']) || !is_numeric($_POST['product_id'])) {
        wp_send_json_error('Invalid Product ID.');
        wp_die();
    }
    
    $product_id = intval($_POST['product_id']);
    
    // Verify the product actually exists and is published
    $product = get_post($product_id);
    if (!$product || $product->post_type !== 'products' || $product->post_status !== 'publish') {
        wp_send_json_error('Product not found.');
        wp_die();
    }

    // Check cache
    $cache_key = 'listicle_specs_' . $product_id;
    $cached_html = get_transient($cache_key);

    if (false !== $cached_html) {
        wp_send_json_success(['html' => $cached_html]);
        wp_die();
    }
    
    // Get product specifications
    $product_specs = getSpecs($product_id);
    
    if (empty($product_specs)) {
        wp_send_json_error('Detailed specifications are not available for this product.');
        wp_die();
    }

    // Generate HTML output
    ob_start();
    
    // Extract special sections
    $performance_tests = $product_specs['ERideHero Tests'] ?? null;
    $advanced_comparisons = $product_specs['Advanced Comparison'] ?? null;
    unset($product_specs['ERideHero Tests']);
    unset($product_specs['Advanced Comparison']);

    if (!empty($performance_tests)): ?>
        <div class="specs-section">
            <h4 class="specs-section-title">Performance Tests</h4>
            <div class="specs-category-group performance-tests-group">
                <?php foreach ($performance_tests as $spec_details): ?>
                    <div class="spec-item">
                        <span class="spec-item-label"><?php echo esc_html($spec_details['title']); ?></span>
                        <span class="spec-item-value"><?php echo format_spec_value($spec_details); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="specs-section">
        <h4 class="specs-section-title">Full Specifications</h4>
        <?php foreach ($product_specs as $category_name => $specs_in_category): ?>
             <?php if (!empty($specs_in_category)): ?>
                <div class="specs-category specs-accordion-item">
					<button type="button" class="specs-category-title specs-accordion-trigger" aria-expanded="false">
						<?php echo esc_html($category_name); ?>
						<svg class="specs-accordion-icon" width="20" height="20" viewBox="0 0 24 24">
							<use xlink:href="#icon-chevron-down"></use>
						</svg>
					</button>
					<div class="specs-category-group specs-accordion-content" hidden>
                        <?php foreach ($specs_in_category as $spec_details): ?>
                            <?php
                                $should_render = true;
                                if ($spec_details['type'] === 'boolean' && $spec_details['value'] == 0) { $should_render = true; }
                                elseif ($spec_details['type'] !== 'boolean' && ($spec_details['value'] === '' || $spec_details['value'] === null)) { $should_render = false; }
                            ?>
                            <?php if ($should_render): ?>
                                <div class="spec-item">
                                    <span class="spec-item-label"><?php echo esc_html($spec_details['title']); ?></span>
                                    <span class="spec-item-value"><?php echo format_spec_value($spec_details); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

     <?php if (!empty($advanced_comparisons)): ?>
       <div class="specs-section">
           <h4 class="specs-section-title">Advanced Comparisons</h4>
           <div class="specs-category-group advanced-comparisons-group">
               <?php foreach ($advanced_comparisons as $spec_details): ?>
                   <div class="spec-item">
                       <span class="spec-item-label"><?php echo esc_html($spec_details['title']); ?></span>
                       <span class="spec-item-value"><?php echo format_spec_value($spec_details); ?></span>
                   </div>
               <?php endforeach; ?>
           </div>
       </div>
    <?php endif;
    
    $html_output = ob_get_clean();

    // Cache the HTML for 1 day
    set_transient($cache_key, $html_output, DAY_IN_SECONDS);
    
    // Send response
    wp_send_json_success(['html' => $html_output]);
    wp_die();
}

// Hook the new AJAX action
add_action( 'wp_ajax_load_specifications', 'load_specifications_callback' );
add_action( 'wp_ajax_nopriv_load_specifications', 'load_specifications_callback' );


class MySettingsPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page(
            'Settings Admin', 
            'My Settings', 
            'manage_options', 
            'my-setting-admin', 
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'my_option_name' );
        ?>
        <div class="wrap">
            <h1>My Settings</h1>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'my_option_group' );
                do_settings_sections( 'my-setting-admin' );
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'my_option_group', // Option group
            'my_option_name', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'setting_section_id', // ID
            'My Custom Settings', // Title
            array( $this, 'print_section_info' ), // Callback
            'my-setting-admin' // Page
        );  

        add_settings_field(
            'miles_ridden', // ID
            'Miles ridden', // Title 
            array( $this, 'miles_ridden_callback' ), // Callback
            'my-setting-admin', // Page
            'setting_section_id' // Section           
        );      

        add_settings_field(
            'hours_spent', 
            'Hours spent', 
            array( $this, 'hours_spent_callback' ), 
            'my-setting-admin', 
            'setting_section_id'
        );      
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();
        if( isset( $input['miles_ridden'] ) )
            $new_input['miles_ridden'] = sanitize_text_field( $input['miles_ridden'] );

        if( isset( $input['hours_spent'] ) )
            $new_input['hours_spent'] = sanitize_text_field( $input['hours_spent'] );

        return $new_input;
    }

    /** 
     * Print the Section text
     */
    public function print_section_info()
    {
        print 'Enter your settings below:';
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function miles_ridden_callback()
    {
        printf(
            '<input type="text" id="miles_ridden" name="my_option_name[miles_ridden]" value="%s" />',
            isset( $this->options['miles_ridden'] ) ? esc_attr( $this->options['miles_ridden']) : ''
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function hours_spent_callback()
    {
        printf(
            '<input type="text" id="hours_spent" name="my_option_name[hours_spent]" value="%s" />',
            isset( $this->options['hours_spent'] ) ? esc_attr( $this->options['hours_spent']) : ''
        );
    }
}

if( is_admin() )
    $my_settings_page = new MySettingsPage();


function my_relationship_query( $args, $field, $post_id ) {
	
    // only show children of the current post being edited
    $args['posts_per_page'] = 3;
	// return
    return $args;
    
}
// filter for every field
add_filter('acf/fields/relationship/query', 'my_relationship_query', 10, 3);



// customize admin bar css
function override_admin_bar_css() { 

   if ( is_admin_bar_showing() ) { 

?>

      <style type="text/css">
		  body.admin-bar div.mega-menu-dropdown {
			  top:82px;
		  }
		  header#section-214-5 {
			  top:32px;
		  }
		  
		  section.stickyproductbar {
			  margin-top:32px;
		  }
      </style>

   <?php }

}
// on frontend area
add_action( 'wp_head', 'override_admin_bar_css' );

include( plugin_dir_path( __FILE__ ) . 'graphs.php');
include( plugin_dir_path( __FILE__ ) . 'schema.php');
include( plugin_dir_path( __FILE__ ) . 'affiliatelinks.php');



/*** ADD CUSTOM BLOCKS ***/
add_action( 'init', 'register_acf_blocks' );
function register_acf_blocks() {
    register_block_type( __DIR__ . '/blocks/relatedproducts' );
	register_block_type( __DIR__ . '/blocks/thisprodprice' );
	register_block_type( __DIR__ . '/blocks/video' );
	register_block_type( __DIR__ . '/blocks/bfdeal' );
	register_block_type( __DIR__ . '/blocks/simplebgitem' );
	register_block_type( __DIR__ . '/blocks/jumplinks' );
	register_block_type( __DIR__ . '/blocks/toppicks' );
	register_block_type( __DIR__ . '/blocks/bgoverview' );
	register_block_type( __DIR__ . '/blocks/top3' );
	register_block_type( __DIR__ . '/blocks/accordion' );
	register_block_type( __DIR__ . '/blocks/buying-guide-table' );
	register_block_type( __DIR__ . '/blocks/super-accordion' );
	register_block_type( __DIR__ . '/blocks/checklist' );
	register_block_type( __DIR__ . '/blocks/listicle-item' );
}


function getTocbot(){
    wp_enqueue_script(
        'tocbot',
        plugin_dir_url( __FILE__ ) . 'assets/js/tocbot.min.js',
        array(),
        '4.3.1',
        true
    );
}


add_action('init','add_get_val');
function add_get_val() { 
    global $wp; 
    $wp->add_query_var('src'); 
}

// --- Helper function for Textarea Lists ---
function render_textarea_list( $textarea_content, $list_class = '', $item_class = '', $icon = 'pros' ) {
    if ( empty( trim( $textarea_content ) ) ) {
        return '';
    }
    $output = '<ul class="' . esc_attr( $list_class ) . '">';
    $lines = explode( "\n", trim( $textarea_content ) );
	if($icon == "pros") {
		$icon = '<svg viewBox="0 0 24 24"><title>checkmark</title><path d="M19.293 5.293l-10.293 10.293-4.293-4.293c-0.391-0.391-1.024-0.391-1.414 0s-0.391 1.024 0 1.414l5 5c0.391 0.391 1.024 0.391 1.414 0l11-11c0.391-0.391 0.391-1.024 0-1.414s-1.024-0.391-1.414 0z"></path></svg>';
	} elseif($icon == "cons"){
		$icon = '<svg viewBox="0 0 24 24"><title>cross</title><path d="M5.293 6.707l5.293 5.293-5.293 5.293c-0.391 0.391-0.391 1.024 0 1.414s1.024 0.391 1.414 0l5.293-5.293 5.293 5.293c0.391 0.391 1.024 0.391 1.414 0s0.391-1.024 0-1.414l-5.293-5.293 5.293-5.293c0.391-0.391 0.391-1.024 0-1.414s-1.024-0.391-1.414 0l-5.293 5.293-5.293-5.293c-0.391-0.391-1.024-0.391-1.414 0s-0.391 1.024 0 1.414z"></path></svg>';
	}
    foreach ( $lines as $line ) {
        $line = trim( $line );
        if ( ! empty( $line ) ) {
            $output .= '<li class="' . esc_attr( $item_class ) . '"><span class="list-icon">' . $icon . '</span> ' . esc_html( $line ) . '</li>';
        }
    }
    $output .= '</ul>';
    return $output;
}


/*** FUNCTION TO HANDLE AFF LINKS ***/

function afflink($link, $product_id = null) {
    $url = parse_url($link);
    
    if (isset($url["query"])) {
        $link = $url["scheme"] . "://" . $url["host"] . $url["path"] . "?" . str_replace("?", "&", $url["query"]);
    }
    
    // Check if amazon_overwrite is set and the link is from amazon.com
    if (isset($product_id) && get_field('amazon_overwrite',$product_id) && isset($url['host']) && $url['host'] === 'www.amazon.com') {
        return get_field('amazon_overwrite',$product_id);
    }
    
    if (get_query_var('src')) {
        if (strpos($link, "shareasale.com") !== false) {
            $link .= (parse_url($link, PHP_URL_QUERY) ? '&' : '?') . "afftrack=search";
        }
    }
    
    return $link;
}


/*** dequeue unneccesary scripts **/
function wpdocs_dequeue_script() {
    wp_dequeue_script('affegg-price-alert');
	wp_dequeue_script('cegg-price-alert');
}
add_action( 'wp_print_scripts', 'wpdocs_dequeue_script', 100 );




/**** RANKMATH VARS ****/

add_action( 'rank_math/vars/register_extra_replacements', function(){
 rank_math_register_var_replacement(
 'jobtitle',
 [
 'name'        => esc_html__( 'Job Title', 'rank-math' ),
 'description' => esc_html__( 'Gets the job title of the author', 'rank-math' ),
 'variable'    => 'job_title',
 'example'     => 'varjobtitle()',
 ],
 'varjobtitle'
 );
});

function varjobtitle() {
    global $post;
    $author_id = $post->post_author;
    $return = get_field('job_title', 'user_'. $author_id );
    return $return;
}

add_action( 'rank_math/vars/register_extra_replacements', function(){
 rank_math_register_var_replacement(
 'knowsabout',
 [
 'name'        => esc_html__( 'Knows About', 'rank-math' ),
 'description' => esc_html__( 'Gets the knows about of the author', 'rank-math' ),
 'variable'    => 'knows_about',
 'example'     => 'varknowsabout()',
 ],
 'varknowsabout'
 );
});

function varknowsabout() {
    global $post;
    $author_id = $post->post_author;
    $return = get_field('knows_about', 'user_'. $author_id );
    return $return;
}

add_action( 'wp_enqueue_scripts', 'custom_enqueue_files' );

function custom_enqueue_files() {

	wp_enqueue_script( 'bestprice', plugin_dir_url( __FILE__ ) . 'blocks/video/video.js', '', '1.0', true );

}


function getPrices($id) {
    // Validate input
    if (!$id || !is_numeric($id)) {
        return null;
    }
    
    $metas = get_post_meta($id);
    if (empty($metas)) {
        return null;
    }
    
    $items = array();
    
    // Extract price data from post meta
    foreach ($metas as $key => $meta) {
        if (strpos($key, "_cegg_data") !== false) {
            $unserialized = maybe_unserialize($meta[0]);
            if (is_array($unserialized)) {
                foreach ($unserialized as $item) {
                    $items[] = $item;
                }
            }
        }
    }
    
    // Return early if no items found
    if (empty($items)) {
        return null;
    }
    
    // Get domain priority from ACF options
    $domain_priorities = array();
    if (function_exists('get_field')) {
        $domains_repeater = get_field('domains', 'option');
        if (is_array($domains_repeater)) {
            $priority_index = 0;
            foreach ($domains_repeater as $row) {
                // Try both 'domain' (singular) and 'domains' (plural) field names
                $domain_value = null;
                if (isset($row['domain']) && !empty($row['domain'])) {
                    $domain_value = $row['domain'];
                } elseif (isset($row['domains']) && !empty($row['domains'])) {
                    $domain_value = $row['domains'];
                } elseif (is_string($row) && !empty($row)) {
                    // Sometimes ACF returns just the value if it's a simple text field
                    $domain_value = $row;
                }
                
                if ($domain_value) {
                    // Store domain with its priority index (lower = higher priority)
                    $domain_priorities[$domain_value] = $priority_index;
                    $priority_index++;
                }
            }
        }
    }
    
    // Debug: Uncomment to see what priorities were loaded
    // error_log('Domain priorities: ' . print_r($domain_priorities, true));
    
    // Function to get domain priority (returns high number if not in list)
    $getDomainPriority = function($item) use ($domain_priorities) {
        if (isset($item['domain']) && isset($domain_priorities[$item['domain']])) {
            return $domain_priorities[$item['domain']];
        }
        // Return a high number for domains not in the priority list
        return 999999;
    };
    
    // Define priority groups based on stock status and price
    $priority_groups = array(
        // In stock with price > 0
        'in_stock_with_price' => array(),
        // Unknown stock with price > 0
        'unknown_stock_with_price' => array(),
        // Unknown stock with price = 0
        'unknown_stock_no_price' => array(),
        // Out of stock with price > 0
        'out_of_stock_with_price' => array(),
        // Out of stock with price = 0
        'out_of_stock_no_price' => array(),
        // Any remaining items
        'other' => array()
    );
    
    // Categorize items into priority groups
    foreach ($items as $item) {
        $price = isset($item['price']) ? (float)$item['price'] : 0;
        $stock_status = isset($item['stock_status']) ? (int)$item['stock_status'] : 0;
        
        if ($stock_status === 1 && $price > 0) {
            $priority_groups['in_stock_with_price'][] = $item;
        } elseif ($stock_status === 0 && $price > 0) {
            $priority_groups['unknown_stock_with_price'][] = $item;
        } elseif ($stock_status === 0 && $price === 0) {
            $priority_groups['unknown_stock_no_price'][] = $item;
        } elseif ($stock_status === -1 && $price > 0) {
            $priority_groups['out_of_stock_with_price'][] = $item;
        } elseif ($stock_status === -1 && $price === 0) {
            $priority_groups['out_of_stock_no_price'][] = $item;
        } else {
            $priority_groups['other'][] = $item;
        }
    }
    
    // Sort each group by price first, then by domain priority for same prices
    foreach ($priority_groups as &$group) {
        if (!empty($group)) {
            usort($group, function($a, $b) use ($getDomainPriority) {
                // First compare by price
                $price_a = isset($a['price']) ? (float)$a['price'] : 0;
                $price_b = isset($b['price']) ? (float)$b['price'] : 0;
                
                $price_comparison = $price_a <=> $price_b;
                
                // If prices are equal, compare by domain priority
                if ($price_comparison === 0) {
                    $priority_a = $getDomainPriority($a);
                    $priority_b = $getDomainPriority($b);
                    
                    // Debug: Uncomment to see priority comparison
                    // error_log("Comparing {$a['domain']} (priority: $priority_a) vs {$b['domain']} (priority: $priority_b)");
                    
                    return $priority_a <=> $priority_b;
                }
                
                return $price_comparison;
            });
        }
    }
    
    // Merge groups in priority order
    $result = array_merge(
        $priority_groups['in_stock_with_price'],
        $priority_groups['unknown_stock_with_price'],
        $priority_groups['unknown_stock_no_price'],
        $priority_groups['out_of_stock_with_price'],
        $priority_groups['out_of_stock_no_price'],
        $priority_groups['other']
    );
    
    return $result;
}

function remove_comment_url($arg) {
    $arg['url'] = '';
    return $arg;
}
add_filter('comment_form_default_fields', 'remove_comment_url');

add_action('wp_head', 'header_code');

    function header_code() {
        echo '<meta name="partnerboostverifycode" content="32dc01246faccb7f5b3cad5016dd5033" />';
    }


function analyzeProductHistory($productHistory) {
    if (empty($productHistory)) {
        return null;
    }

    $firstDate = new DateTime($productHistory[0]->date);
    $lowestPrice = PHP_FLOAT_MAX;
    $highestPrice = 0;
    $lowestPriceDate = null;
    $highestPriceDate = null;
    $totalPrice = 0;
    $count = count($productHistory);

    foreach ($productHistory as $entry) {
        $price = floatval($entry->price);
        $totalPrice += $price;
        $currentDate = new DateTime($entry->date);

        if ($price < $lowestPrice) {
            $lowestPrice = $price;
            $lowestPriceDate = $currentDate;
        }

        if ($price > $highestPrice) {
            $highestPrice = $price;
            $highestPriceDate = $currentDate;
        }
    }

    $averagePrice = $totalPrice / $count;

    return [
        'tracking_start_date' => $firstDate->format('F j, Y'),
        'lowest_price' => [
            'amount' => $lowestPrice,
            'date' => $lowestPriceDate->format('F j, Y')
        ],
        'highest_price' => [
            'amount' => $highestPrice,
            'date' => $highestPriceDate->format('F j, Y')
        ],
        'average_price' => $averagePrice
    ];
}


/*** CHART ***/
function get_product_price_history($product_id, $chart = true) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'product_daily_prices';

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT date, price, domain FROM $table_name WHERE product_id = %d ORDER BY date ASC",
        $product_id
    ));
	
	if($chart == false){
		return $results;
	}

    $data = array(
        'labels' => array(),
        'datasets' => array(
            array(
                'label' => 'Price History',
                'data' => array(),
                'borderColor' => 'rgb(75, 192, 192)',
                'tension' => 0.1
            )
        )
    );

    $domains = array();

    foreach ($results as $row) {
        $data['labels'][] = $row->date;
        $data['datasets'][0]['data'][] = array(
            'x' => $row->date,
            'y' => $row->price,
            'domain' => $row->domain
        );
    }

    return $data;
}

// Display price chart
function display_price_chart($id) {
    if (is_singular('products')) {
        $product_id = $id;
		
		wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.1', true);
        wp_enqueue_script('chartjs-adapter-date-fns', 'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js', array('chart-js'), '3.7.1', true);
        wp_enqueue_script('price-chart', plugin_dir_url(__FILE__) . 'assets/js/price-chart.js', array('chart-js', 'chartjs-adapter-date-fns'), '1.0', true);
        $price_history = get_product_price_history($product_id);
        
        wp_localize_script('price-chart', 'priceChartData', $price_history);
		
        $price_history = get_product_price_history($product_id);

        if (!empty($price_history['labels'])) {
            echo '<div><canvas id="priceChart"></canvas></div>';
        }
    }
}

function load_price_history_callback() {
    
    if (!isset($_POST['product_id']) || !is_numeric($_POST['product_id'])) {
        wp_send_json_error('Invalid Product ID.');
        wp_die();
    }
    $product_id = intval($_POST['product_id']);
	$product = get_post($product_id);
	if (!$product || $product->post_type !== 'products' || $product->post_status !== 'publish') {
        wp_send_json_error('Product not found.');
        wp_die();
    }

    // Check cache
    $cache_key = 'listicle_phist_' . $product_id;
    $cached_data = get_transient($cache_key);

    if (false !== $cached_data) {
        wp_send_json_success($cached_data);
        wp_die();
    }
    
    // Get data
    $product_title = get_the_title($product_id) ?: 'This Product';
    $raw_history = get_product_price_history($product_id, false);
    $chart_data = get_product_price_history($product_id, true);
    $summary_stats = analyzeProductHistory($raw_history);

    // Get current best price
    $current_best_price_info = null;
    $product_prices = getPrices($product_id);
    if (is_array($product_prices) && !empty($product_prices)) {
        $first_price_item = $product_prices[0];
        $is_available = isset($first_price_item['price']) && $first_price_item['price'] > 0 && 
                        isset($first_price_item['stock_status']) && $first_price_item['stock_status'] != -1;

        if ($is_available) {
            $vendor_name = 'Store';
            if (!empty($first_price_item['url'])) {
                $vendor_name = prettydomain(extractDomain($first_price_item['url']));
            }
            $current_best_price_info = [
                'isAvailable' => true,
                'price' => $first_price_item['price'],
                'vendor' => $vendor_name,
                'url' => $first_price_item['url'] ?? '#'
            ];
        }
    }

    // Build response data
    $has_chart_data = !empty($chart_data) && is_array($chart_data) && !empty($chart_data['labels']) && !empty($chart_data['datasets']);
    $has_summary_data = !empty($summary_stats);

    if ($has_chart_data || $has_summary_data || $current_best_price_info) {
        $response_data = [
            'productTitle' => $product_title,
            'chartData' => $has_chart_data ? $chart_data : null,
            'summaryStats' => $has_summary_data ? $summary_stats : null,
            'currentBestPrice' => $current_best_price_info
        ];
        
        // Cache the data for 6 hours
        set_transient($cache_key, $response_data, 6 * HOUR_IN_SECONDS);
        
        wp_send_json_success($response_data);
    } else {
        wp_send_json_error('No price history data found.');
    }

    wp_die();
}

// Hook for logged-in users
add_action( 'wp_ajax_load_price_history', 'load_price_history_callback' );
add_action( 'wp_ajax_nopriv_load_price_history', 'load_price_history_callback' );


/**
 * Enqueue scripts and localize data for the Listicle Item block
 * only when the block is present on the page.
 */
function my_plugin_enqueue_listicle_scripts() {
    global $post;
    if ( is_singular() && has_block( 'acf/listicle-item', $post->ID ?? null ) ) {

        // Enqueue Chart.js and Date Adapter (needed for Price History)
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.1', true);
        wp_enqueue_script('chartjs-adapter-date-fns', 'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js', array('chart-js'), '3.7.1', true);

        // Determine the correct script handle for listicle-item.js
        $script_handle = 'acf-listicle-item-script'; // Adjust if needed

        // Check if the script is actually registered/enqueued before localizing
        if ( wp_script_is( $script_handle, 'registered' ) || wp_script_is( $script_handle, 'enqueued' ) ) {
             wp_localize_script(
                $script_handle,
                'listicleItemData', // Object name in JS
                array(
                    'ajax_url' => admin_url( 'admin-ajax.php' )
                )
            );
        } else {
            // error_log("Could not localize listicleItemData: Script handle '{$script_handle}' not found.");
        }
    }
}
add_action( 'wp_enqueue_scripts', 'my_plugin_enqueue_listicle_scripts' );

function getReviews($id) {
    $reviews = [];
    $ratings_distribution = [
        'ratings_count' => 0,
        'average_rating' => 0,
        'count_1_star' => 0,
        'count_2_star' => 0,
        'count_3_star' => 0,
        'count_4_star' => 0,
        'count_5_star' => 0
    ];
    $total_score = 0;
    $args = array(
        'post_type' => 'review',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => 'product',
                'value' => $id,
                'compare' => '='
            )
        )
    );
    $query = new WP_Query($args);
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $author_id = get_post_field('post_author', $post_id);
            $score = get_field('score', $post_id);
            $text = get_field('text', $post_id);
            
            // Get review image URLs
            $image_id = get_post_meta($post_id, 'review_image', true);
            $thumbnail_url = '';
            $large_url = '';
            if ($image_id) {
                $thumbnail_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                $large_url = wp_get_attachment_image_url($image_id, 'large');
            }
            
            $reviews[] = array(
                'id' => $post_id,
                'text' => $text,
                'score' => $score,
                'author' => get_the_author_meta('display_name', $author_id),
                'date' => get_the_date('Y-m-d H:i:s'),
                'thumbnail_url' => $thumbnail_url,
                'large_url' => $large_url
            );
            
            // Update ratings distribution
            $ratings_distribution['ratings_count']++;
            $total_score += $score;
            $star_count_key = 'count_' . $score . '_star';
            if (isset($ratings_distribution[$star_count_key])) {
                $ratings_distribution[$star_count_key]++;
            }
        }
        wp_reset_postdata();
        // Calculate average rating
        if ($ratings_distribution['ratings_count'] > 0) {
            $ratings_distribution['average_rating'] = number_format(round($total_score / $ratings_distribution['ratings_count'], 1), 1);
        }
    }
    return [
        'ratings_distribution' => $ratings_distribution,
        'reviews' => $reviews
    ];
}

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

// Show Subscribers in the 'author' drop down on the classic post editor
function wpdocs_add_subscribers_to_dropdown( $query_args ) {
    $query_args['who'] = ''; // reset the query
    $query_args['capability'] = ''; // reset the query
    $query_args['role__in'] = array( 'administrator', 'subscriber', 'author', 'editor' );
    $query_args['capability__in'] = array( 'edit_own_posts' ); // Custom capability for subscribers

    return $query_args;
}
add_filter( 'wp_dropdown_users_args', 'wpdocs_add_subscribers_to_dropdown' );







function get_product_offers_ajax() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'product_offers_nonce')) {
        wp_send_json_error('Security check failed');
        wp_die();
    }
    
    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        wp_send_json_error('Invalid product ID');
        wp_die();
    }
    
    $product_id = intval($_POST['id']);
    $post = get_post($product_id);
    
    if (empty($post) || $post->post_type !== 'products') {
        wp_send_json_error('Product not found');
        wp_die();
    }
    
    // Get product data
    $fields = get_fields($product_id);
    $useful = getPrices($product_id);
    $coupons = get_field('coupon', $product_id);
    
    ob_start();
    ?>
    <div class="pc-modal-offers">
        <?php
        if (!empty($useful) && is_array($useful)) {
            foreach ($useful as $item) {
                // Skip items without URLs
                if (!isset($item['url'])) continue;
                
                $price = isset($item['price']) ? (float)$item['price'] : 0;
                $stock_status = isset($item['stock_status']) ? (int)$item['stock_status'] : 0;
                $currency = isset($item['currency']) ? $item['currency'] : '$';
                $currency_code = isset($item['currencyCode']) ? $item['currencyCode'] : 'USD';
                $domain = isset($item['domain']) ? $item['domain'] : '';
                $percentage_saved = isset($item['percentageSaved']) ? (float)$item['percentageSaved'] : 0;
                $old_price = isset($item['priceOld']) ? (float)$item['priceOld'] : 0;
                
                ?>
                <a href="<?php echo esc_url(afflink($item['url'])); ?>" target="_blank" rel="nofollow external noopener" class="pc-modal-offer afftrigger">
                    <div class="pc-modal-offer-container">
                        <div class="pc-modal-offer-content-left">
                            <div class="pc-modal-domain">
                                <?php echo esc_html(ucfirst($domain)); ?>
                                <?php if ($stock_status == 1): ?>
                                    <div class="pc-modal-instock isinstock">
                                        <svg aria-hidden="true" width="20" height="20" preserveAspectRatio="none" viewBox="0 0 24 24">
                                            <use href="#InStock"></use>
                                        </svg>
                                        <span>In stock</span>
                                    </div>
                                <?php else: ?>
                                    <div class="pc-modal-instock isoutofstock">
                                        <svg aria-hidden="true" width="20" height="20" preserveAspectRatio="none" viewBox="0 0 24 24">
                                            <use href="#OutOfStock"></use>
                                        </svg>
                                        <span>Out of stock</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php
                            // Check for coupons
                            if (!empty($coupons) && is_array($coupons)) {
                                $coupon_key = array_search($domain, array_column($coupons, 'website'));
                                if ($coupon_key !== false && isset($coupons[$coupon_key])) {
                                    $coupon = $coupons[$coupon_key];
                                    echo '<div class="pc-modal-coupon">Coupon: <span class="pc-modal-coupon-code">' . 
                                         esc_html($coupon['code']) . '</span> (-';
                                    if ($coupon['discount_type'] == "Money") {
                                        echo esc_html($currency . $coupon['discount_value'] . " " . $currency_code);
                                    } else {
                                        echo esc_html($coupon['discount_value'] . "%");
                                    }
                                    echo ')</div>';
                                }
                            }
                            ?>
                        </div>
                        
                        <div class="pc-modal-offer-content-right">
                            <div class="pc-item-price">
                                <span class="pc-item-pricenow">
                                    <?php
                                    if ($price <= 0) {
                                        echo "Price Unknown";
                                    } else {
                                        echo esc_html($currency . number_format($price, 2) . " " . $currency_code);
                                    }
                                    ?>
                                </span>
                                <?php if ($percentage_saved > 0 && $old_price > 0): ?>
                                    <span class="pc-item-pricewas">
                                        <span class="pc-item-pricewas-price">
                                            <?php echo esc_html($currency . number_format($old_price, 2) . " " . $currency_code); ?>
                                        </span>
                                        <span class="pc-item-saved">(-<?php echo number_format($percentage_saved, 0); ?>%)</span>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pc-modal-chevron">
                        <svg aria-hidden="true" width="30" height="30" preserveAspectRatio="none" viewBox="0 0 24 24">
                            <use href="#Chevron"></use>
                        </svg>
                    </div>
                </a>
                <?php
            }
        } else {
            echo '<p style="padding: 1em;">No offers available!</p>';
        }
        ?>
    </div>
    <?php
    
    $html = ob_get_clean();
    wp_send_json_success($html);
    wp_die();
}

add_action('wp_ajax_get_product_offers', 'get_product_offers_ajax');
add_action('wp_ajax_nopriv_get_product_offers', 'get_product_offers_ajax');