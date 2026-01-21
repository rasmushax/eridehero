<?php
/**
 * Advantage Calculator Interface.
 *
 * Contract for product-type-specific advantage calculators.
 * Each product type (escooter, ebike, euc, etc.) implements this
 * interface with its own logic for determining spec advantages.
 *
 * @package ERH\Comparison
 */

declare(strict_types=1);

namespace ERH\Comparison;

/**
 * Interface for advantage calculators.
 */
interface AdvantageCalculatorInterface {

    /**
     * Calculate advantages for head-to-head (2 product) comparison.
     *
     * Returns an array of advantages for each product, showing
     * concrete spec-based reasons why each product wins.
     *
     * @param array $products Array of 2 product data arrays.
     * @return array Array with 2 elements, each containing advantages for that product.
     *               Format: [
     *                   [ // Product 1 advantages
     *                       ['text' => 'Faster', 'comparison' => '23 mph vs 22 mph', 'tooltip' => '...'],
     *                       ...
     *                   ],
     *                   [ // Product 2 advantages
     *                       ['text' => 'Better range', 'comparison' => '40 mi vs 35 mi', 'tooltip' => '...'],
     *                       ...
     *                   ],
     *               ]
     */
    public function calculate_head_to_head(array $products): array;

    /**
     * Calculate advantages for multi-product (3+) comparison.
     *
     * For 3+ products, shows which product wins each spec category.
     *
     * @param array $products Array of 3+ product data arrays.
     * @return array|null Advantages array or null if not implemented.
     */
    public function calculate_multi(array $products): ?array;

    /**
     * Calculate advantages and weaknesses for single product display.
     *
     * Compares product against others in the same price bracket.
     * Returns both strengths and weaknesses for product pages.
     *
     * @param array  $product Single product data array with specs and price_history.
     * @param string $geo     Geo region code for price-based bracketing (US, GB, EU, CA, AU).
     * @return array|null Analysis array with advantages/weaknesses, or null if not implemented.
     */
    public function calculate_single(array $product, string $geo = 'US'): ?array;

    /**
     * Get the product type this calculator handles.
     *
     * @return string Product type slug (e.g., 'escooter', 'ebike').
     */
    public function get_product_type(): string;
}
