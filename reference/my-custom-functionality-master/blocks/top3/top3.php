<?php
// Dynamically load fields
$fields = get_fields();

// Pre-fetch all product data
$products = [];
for ($i = 1; $i <= 3; $i++) {
    $item_key = "item_$i";
    $product_id = $fields[$item_key];
    $products[$i] = [
        'id' => $product_id,
        'name' => get_the_title($product_id),
        'prices' => getPrices($product_id),
        'review' => get_field('review', $product_id)['review_post'],
        'image_id' => $fields["item_{$i}_img"],
    ];
}

// Start generating the HTML table
echo '<div class="comparison-table-container scrollbar"><table class="comparison-table">';
echo '<thead><tr><th></th>';
foreach (['item_1_title', 'item_2_title', 'item_3_title'] as $title_key) {
    echo "<th>{$fields[$title_key]}</th>";
}
echo '</tr></thead>';

echo '<tbody>';

// Product images row
echo '<tr class="comparison-table-top"><td></td>';
foreach ($products as $product) {
    echo '<td>';
    if (!empty($product['prices'][0]['price'])) {
        $aff_link = afflink($product['prices'][0]['url'], $product['id']);
        echo "<a target='_blank' rel='nofollow external noopener' href='{$aff_link}'>";
    }
    
    if ($product['image_id']) {
        $image_url = wp_get_attachment_image_url($product['image_id'], 'medium');
        echo "<img src='{$image_url}' alt='{$product['name']}' title='{$product['name']}'>";
    }
    
    if (!empty($product['prices'][0]['price'])) {
        echo "</a>";
    }
    echo '</td>';
}
echo '</tr>';

// Product names row
echo '<tr><td></td>';
foreach ($products as $product) {
    echo '<td>';
    if (!empty($product['prices'][0]['price'])) {
        $aff_link = afflink($product['prices'][0]['url'], $product['id']);
        echo "<a target='_blank' rel='nofollow external noopener' href='{$aff_link}'>";
    }
    
    echo $product['name'];
    
    if (!empty($product['prices'][0]['price'])) {
        echo "</a>";
    }
    echo '</td>';
}
echo '</tr>';

// Specs rows
foreach ($fields['specs'] as $spec) {
    echo '<tr>';
    echo "<td>{$spec['spec']}</td>";
    echo "<td>{$spec['item_1']}</td>";
    echo "<td>{$spec['item_2']}</td>";
    echo "<td>{$spec['item_3']}</td>";
    echo '</tr>';
}

// ERideHero review row
echo '<tr>';
echo '<td>ERideHero Review</td>';
foreach ($products as $product) {
    echo '<td>';
    if ($product['review']) {
        $review_link = get_permalink($product['review']);
        echo "<a href=\"{$review_link}\">{$product['name']} Review</a>";
    } else {
        echo "-";
    }
    echo '</td>';
}
echo '</tr>';

// Price row
echo '<tr>';
echo '<td>Price</td>';
foreach ($products as $product) {
    echo '<td>';
    if (!empty($product['prices'][0]['price'])) {
        $formatted_price = number_format($product['prices'][0]['price'], 2);
        $aff_link = afflink($product['prices'][0]['url'], $product['id']);
        echo "<a target='_blank' rel='nofollow external noopener' class='comparison-table-external' href='{$aff_link}'>\${$formatted_price}<svg><use xlink:href='#icon-external-link'></use></svg></a>";
    } else {
        echo '-';
    }
    echo '</td>';
}
echo '</tr>';

echo '</tbody></table></div>';
?>