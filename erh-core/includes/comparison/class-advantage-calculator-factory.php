<?php
/**
 * Advantage Calculator Factory.
 *
 * Creates the appropriate advantage calculator based on product type.
 * Each product type (escooter, ebike, euc, etc.) has its own calculator
 * with type-specific logic.
 *
 * @package ERH\Comparison
 */

declare(strict_types=1);

namespace ERH\Comparison;

use ERH\Comparison\Calculators\EscooterAdvantages;

/**
 * Factory for creating advantage calculators.
 */
class AdvantageCalculatorFactory {

    /**
     * Calculator instances cache.
     *
     * @var array<string, AdvantageCalculatorInterface>
     */
    private static array $calculators = [];

    /**
     * Get a calculator for the given product type.
     *
     * @param string $product_type Product type slug (escooter, ebike, etc.).
     * @return AdvantageCalculatorInterface|null Calculator or null if not supported.
     */
    public static function get(string $product_type): ?AdvantageCalculatorInterface {
        // Normalize product type.
        $type = self::normalize_product_type($product_type);

        // Check cache.
        if (isset(self::$calculators[$type])) {
            return self::$calculators[$type];
        }

        // Create calculator.
        $calculator = self::create_calculator($type);
        if ($calculator) {
            self::$calculators[$type] = $calculator;
        }

        return $calculator;
    }

    /**
     * Check if a product type has an advantage calculator.
     *
     * @param string $product_type Product type slug.
     * @return bool True if supported.
     */
    public static function supports(string $product_type): bool {
        $type = self::normalize_product_type($product_type);
        return in_array($type, self::get_supported_types(), true);
    }

    /**
     * Get list of supported product types.
     *
     * @return array<string> List of product type slugs.
     */
    public static function get_supported_types(): array {
        return [
            'escooter',
            // Future: 'ebike', 'euc', 'eskateboard', 'hoverboard'
        ];
    }

    /**
     * Create a calculator instance for the given type.
     *
     * @param string $type Normalized product type.
     * @return AdvantageCalculatorInterface|null Calculator or null.
     */
    private static function create_calculator(string $type): ?AdvantageCalculatorInterface {
        switch ($type) {
            case 'escooter':
                return new EscooterAdvantages();

            // Future implementations:
            // case 'ebike':
            //     return new EbikeAdvantages();
            // case 'euc':
            //     return new EucAdvantages();
            // case 'eskateboard':
            //     return new EskateboardAdvantages();
            // case 'hoverboard':
            //     return new HoverboardAdvantages();

            default:
                return null;
        }
    }

    /**
     * Normalize product type string.
     *
     * Converts various formats to canonical slug:
     * - "Electric Scooter" → "escooter"
     * - "e-scooter" → "escooter"
     * - "escooter" → "escooter"
     *
     * @param string $product_type Raw product type.
     * @return string Normalized type slug.
     */
    private static function normalize_product_type(string $product_type): string {
        $type = strtolower(trim($product_type));

        // Map common variations to canonical slugs.
        $mappings = [
            'electric scooter'    => 'escooter',
            'e-scooter'           => 'escooter',
            'escooter'            => 'escooter',
            'electric bike'       => 'ebike',
            'e-bike'              => 'ebike',
            'ebike'               => 'ebike',
            'electric unicycle'   => 'euc',
            'euc'                 => 'euc',
            'electric skateboard' => 'eskateboard',
            'e-skateboard'        => 'eskateboard',
            'eskateboard'         => 'eskateboard',
            'hoverboard'          => 'hoverboard',
        ];

        return $mappings[$type] ?? $type;
    }

    /**
     * Clear cached calculator instances.
     *
     * Useful for testing or when configuration changes.
     *
     * @return void
     */
    public static function clear_cache(): void {
        self::$calculators = [];
    }

    /**
     * Calculate advantages using the appropriate calculator.
     *
     * Convenience method that handles type detection and mode selection.
     *
     * @param array  $products     Array of product data arrays.
     * @param string $product_type Product type (optional, auto-detected if not provided).
     * @return array Advantages array.
     */
    public static function calculate(array $products, string $product_type = ''): array {
        // Auto-detect type from first product if not provided.
        if (empty($product_type) && !empty($products[0])) {
            $product_type = $products[0]['product_type'] ?? 'escooter';
        }

        $calculator = self::get($product_type);
        if (!$calculator) {
            return array_fill(0, count($products), []);
        }

        $count = count($products);

        if ($count === 1) {
            $result = $calculator->calculate_single($products[0]);
            return $result !== null ? [$result] : [[]];
        }

        if ($count === 2) {
            return $calculator->calculate_head_to_head($products);
        }

        // 3+ products.
        $result = $calculator->calculate_multi($products);
        return $result !== null ? $result : array_fill(0, $count, []);
    }
}
