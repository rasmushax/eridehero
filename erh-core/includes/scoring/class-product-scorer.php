<?php
/**
 * Product Scorer - orchestrates scoring across product types.
 *
 * This is a slim orchestrator that delegates to product-type-specific scorers:
 * - EscooterScorer: Electric scooters (7 categories)
 * - EbikeScorer: Electric bikes (5 categories)
 * - HoverboardScorer: Hoverboards (5 categories)
 * - EucScorer: Electric unicycles (6 categories)
 *
 * Each product type uses logarithmic scaling where early gains matter more
 * (500W→1000W is huge, 5000W→5500W is marginal). Missing specs redistribute
 * weight to available specs within each category.
 *
 * @package ERH\Scoring
 */

declare(strict_types=1);

namespace ERH\Scoring;

/**
 * Orchestrates product scoring by delegating to type-specific scorers.
 */
class ProductScorer {

    /**
     * E-scooter scorer instance.
     *
     * @var EscooterScorer|null
     */
    private ?EscooterScorer $escooter_scorer = null;

    /**
     * E-bike scorer instance.
     *
     * @var EbikeScorer|null
     */
    private ?EbikeScorer $ebike_scorer = null;

    /**
     * Hoverboard scorer instance.
     *
     * @var HoverboardScorer|null
     */
    private ?HoverboardScorer $hoverboard_scorer = null;

    /**
     * EUC scorer instance.
     *
     * @var EucScorer|null
     */
    private ?EucScorer $euc_scorer = null;

    /**
     * Calculate all category scores for a product.
     *
     * @param array  $specs        The product specs array.
     * @param string $product_type The product type (e.g., 'Electric Scooter').
     * @return array Category scores including overall.
     */
    public function calculate_scores(array $specs, string $product_type): array {
        $scorer = $this->get_scorer($product_type);

        if ($scorer !== null) {
            return $scorer->calculate($specs);
        }

        // Unsupported product types return null scores.
        return $this->get_null_scores($product_type);
    }

    /**
     * Get the appropriate scorer for a product type.
     *
     * @param string $product_type The product type.
     * @return EscooterScorer|EbikeScorer|HoverboardScorer|EucScorer|null The scorer instance or null if unsupported.
     */
    private function get_scorer(string $product_type) {
        switch ($product_type) {
            case 'Electric Scooter':
                if ($this->escooter_scorer === null) {
                    $this->escooter_scorer = new EscooterScorer();
                }
                return $this->escooter_scorer;

            case 'Electric Bike':
                if ($this->ebike_scorer === null) {
                    $this->ebike_scorer = new EbikeScorer();
                }
                return $this->ebike_scorer;

            case 'Hoverboard':
                if ($this->hoverboard_scorer === null) {
                    $this->hoverboard_scorer = new HoverboardScorer();
                }
                return $this->hoverboard_scorer;

            case 'Electric Unicycle':
                if ($this->euc_scorer === null) {
                    $this->euc_scorer = new EucScorer();
                }
                return $this->euc_scorer;

            default:
                return null;
        }
    }

    /**
     * Get null scores structure for unsupported product types.
     *
     * @param string $product_type The product type.
     * @return array Null scores matching expected structure.
     */
    private function get_null_scores(string $product_type): array {
        // E-bikes have different category structure.
        if ($product_type === 'Electric Bike') {
            return [
                'motor_drive'       => null,
                'battery_range'     => null,
                'component_quality' => null,
                'comfort'           => null,
                'practicality'      => null,
                'overall'           => null,
            ];
        }

        // Hoverboards have 5 categories.
        if ($product_type === 'Hoverboard') {
            return [
                'motor_performance' => null,
                'battery_range'     => null,
                'portability'       => null,
                'ride_comfort'      => null,
                'features'          => null,
                'overall'           => null,
            ];
        }

        // EUCs have 6 categories.
        if ($product_type === 'Electric Unicycle') {
            return [
                'motor_performance' => null,
                'battery_range'     => null,
                'ride_quality'      => null,
                'safety'            => null,
                'portability'       => null,
                'features'          => null,
                'overall'           => null,
            ];
        }

        // Default to e-scooter structure for unknown types.
        return [
            'motor_performance' => null,
            'range_battery'     => null,
            'ride_quality'      => null,
            'portability'       => null,
            'safety'            => null,
            'features'          => null,
            'maintenance'       => null,
            'overall'           => null,
        ];
    }
}
