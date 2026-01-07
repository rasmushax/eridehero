<?php
/**
 * Comparison Bar Component
 *
 * Fixed bottom bar showing selected products for comparison.
 * Used by: finder page, deals page, product archive pages.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="comparison-bar" data-comparison-bar hidden>
    <div class="container">
        <div class="comparison-bar-inner">
            <div class="comparison-bar-products" data-comparison-products></div>
            <div class="comparison-bar-actions">
                <span class="comparison-bar-count"><span data-comparison-count>0</span> selected</span>
                <button type="button" class="btn btn-secondary btn-sm" data-comparison-clear>Clear</button>
                <a href="#" class="btn btn-primary btn-sm" data-comparison-link>Compare</a>
            </div>
        </div>
    </div>
</div>
