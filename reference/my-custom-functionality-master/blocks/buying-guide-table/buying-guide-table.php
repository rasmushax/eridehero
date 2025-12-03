<?php
/**
 * Buying Guide Table Block Template (Flexible Columns).
 *
 * @param   array $block The block settings and attributes.
 * @param   string $content The block inner HTML (empty).
 * @param   bool $is_preview True during backend preview render.
 * @param   int $post_id The post ID this block is saved to.
 */

// Create id attribute allowing for custom "anchor" value.
$id = 'buying-guide-table-' . $block['id'];
if ( ! empty( $block['anchor'] ) ) {
    $id = $block['anchor'];
}

// Create class attribute allowing for custom "className" and "align" values.
$class_name = 'buying-guide-table-block revised-layout';
if ( ! empty( $block['className'] ) ) {
    $class_name .= ' ' . $block['className'];
}
if ( ! empty( $block['align'] ) ) {
    $class_name .= ' align' . $block['align'];
}

// --- Get Block Settings ---
$table_rows = get_field('table_rows') ?: []; // Repeater field for scooter selection
// Get the array of selected columns to display from the Checkbox field
$visible_columns = get_field('visible_spec_columns') ?: [];
// Ensure it's an array even if empty or not set
if (!is_array($visible_columns)) {
    $visible_columns = [];
}
// --- End Block Settings ---


// --- Helper Function for Price Data (Using getPrices) ---
if (!function_exists('get_best_price_data')) {
    function get_best_price_data($product_id) {
        if (!function_exists('getPrices')) { return null; }
        $prices = getPrices($product_id);
        $best_offer = (!empty($prices) && is_array($prices)) ? $prices[0] : null;
        if ($best_offer && isset($best_offer['url']) && !empty($best_offer['url'])) {
            return [
                'url'           => $best_offer['url'],
                'price_value'   => isset($best_offer['price']) ? floatval($best_offer['price']) : 0,
                'currency_symbol' => isset($best_offer['currencySymbol']) ? $best_offer['currencySymbol'] : '$',
            ];
        } else {
            $manual_aff_link = get_field('aff_link', $product_id);
            if (!empty($manual_aff_link)) {
                 return [ 'url' => $manual_aff_link, 'price_value' => 0, 'currency_symbol' => '$' ];
            }
        }
        return null;
    }
}
// --- End Helper Function ---


// Check rows exists.
if ( $table_rows ) :
?>
<div id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $class_name ); ?>">
    <div class="bgt-table-container">
        <table>
            <thead>
                <tr>
                    <th class="bgt-col-image"></th>
                    <th class="bgt-col-scooter-info">Scooter</th> 

                    <?php // Conditionally display spec headers based on ACF Checkbox field ?>
                    <?php if (in_array('tested_speed', $visible_columns)) : ?>
                        <th class="bgt-col-speed">Tested Speed</th>
                    <?php endif; ?>

                    <?php if (in_array('tested_range', $visible_columns)) : ?>
                        <th class="bgt-col-range">Tested Range</th>
                    <?php endif; ?>

                    <?php if (in_array('weight', $visible_columns)) : ?>
                        <th class="bgt-col-weight">Weight</th>
                    <?php endif; ?>
                    
                    <?php if (in_array('max_load', $visible_columns)) : ?>
                        <th class="bgt-col-weight-limit">Weight Limit</th>
                    <?php endif; ?>
                    
                    <?php if (in_array('weather_resistance', $visible_columns)) : ?>
                        <th class="bgt-col-ip-rating">IP Rating</th>
                    <?php endif; ?>
                    
                    <?php // Add more conditional headers here if you add more checkbox options ?>

                    <th class="bgt-col-price">Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $table_rows as $row ) :
                    $product_id = $row['product'];
                    $highlight_text = $row['highlight_text'];

                    if ( !$product_id ) continue;

                    // Get product data
                    $thumbnail_id = get_field('thumbnail', $product_id);
                    if ( !$thumbnail_id ) {
                        $thumbnail_id = get_field('big_thumbnail', $product_id); // Fallback
                    }
                    $product_title = get_the_title($product_id);
                    $product_link = get_permalink($product_id);

                    // Get spec data only if needed (saves a tiny bit of processing)
                    $tested_speed = in_array('tested_speed', $visible_columns) ? get_field('tested_top_speed', $product_id) : null;
                    $tested_range = in_array('tested_range', $visible_columns) ? get_field('tested_range_regular', $product_id) : null;
                    $weight = in_array('weight', $visible_columns) ? get_field('weight', $product_id) : null;
                    $max_load = in_array('max_load', $visible_columns) ? get_field('max_load', $product_id) : null;
                    $weather_resistance = in_array('weather_resistance', $visible_columns) ? get_field('weather_resistance', $product_id) : null;
                    // Add more conditional data fetching here if needed

                    $price_data = get_best_price_data($product_id);
                ?>
                <tr>
                    <td class="bgt-col-image">
                        <?php if ( $thumbnail_id ) : ?>
                            <a href="<?php echo esc_url($product_link); ?>">
                                <?php echo wp_get_attachment_image( $thumbnail_id, 'thumbnail' ); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                    <td class="bgt-col-scooter-info" data-label="Scooter">
                        <div class="scooter-name">
                            <a href="<?php echo esc_url($product_link); ?>">
                                <strong><?php echo esc_html($product_title); ?></strong>
                            </a>
                        </div>
                        <?php if ($highlight_text) : ?>
                        <div class="scooter-highlight">
                            <?php echo esc_html($highlight_text); ?>
                        </div>
                        <?php endif; ?>
                    </td>

                    <?php // Conditionally display spec data cells ?>
                    <?php if (in_array('tested_speed', $visible_columns)) : ?>
                        <td class="bgt-col-speed" data-label="Tested Speed">
                            <?php echo $tested_speed ? esc_html($tested_speed) . ' MPH' : 'N/A'; ?>
                        </td>
                    <?php endif; ?>

                    <?php if (in_array('tested_range', $visible_columns)) : ?>
                        <td class="bgt-col-range" data-label="Tested Range">
                             <?php echo $tested_range ? esc_html($tested_range) . ' miles' : 'N/A'; ?>
                        </td>
                    <?php endif; ?>

                     <?php if (in_array('weight', $visible_columns)) : ?>
                        <td class="bgt-col-weight" data-label="Weight">
                             <?php echo $weight ? esc_html($weight) . ' lbs' : 'N/A'; ?>
                        </td>
                    <?php endif; ?>
                    
                    <?php if (in_array('max_load', $visible_columns)) : ?>
                        <td class="bgt-col-weight-limit" data-label="Weight Limit">
                             <?php echo $max_load ? esc_html($max_load) . ' lbs' : 'N/A'; ?>
                        </td>
                    <?php endif; ?>
                    
                    <?php if (in_array('weather_resistance', $visible_columns)) : ?>
                        <td class="bgt-col-ip-rating" data-label="IP Rating">
                             <?php echo $weather_resistance[0] ? esc_html($weather_resistance[0]) : 'N/A'; ?>
                        </td>
                    <?php endif; ?>

                    <?php // Add more conditional cells here ?>

                    <td class="bgt-col-price" data-label="Price">
                        <?php
                        // Price logic remains the same
                        if ($price_data) {
                            $price_output = 'Check Price';
                            if ($price_data['price_value'] > 0) {
                                $formatted_price = $price_data['currency_symbol'] . number_format_i18n($price_data['price_value'], 2);
                                $price_output = esc_html($formatted_price);
                            }
                            echo '<a href="' . esc_url($price_data['url']) . '" target="_blank" rel="nofollow sponsored" class="bgt-price-link afftrigger">' . $price_output . '</a>';
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif ($is_preview) : ?>
    <p>Please add scooter rows and select visible columns.</p> <?php // Updated preview message ?>
<?php endif; ?>