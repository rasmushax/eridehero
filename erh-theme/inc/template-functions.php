<?php
/**
 * Template Functions
 *
 * Helper functions for use in templates.
 * This file loads modular function files and provides core utilities.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =============================================================================
// LOAD MODULAR FUNCTION FILES
// =============================================================================

// Core utilities (icons, logos).
require_once __DIR__ . '/functions/icons.php';

// Formatting functions (price, date, text utilities).
require_once __DIR__ . '/functions/formatting.php';

// Author helpers (social links from Rank Math).
require_once __DIR__ . '/functions/author-helpers.php';

// Score helpers (labels, attributes).
require_once __DIR__ . '/functions/scores.php';

// Breadcrumb navigation.
require_once __DIR__ . '/functions/breadcrumbs.php';

// Table of contents generation.
require_once __DIR__ . '/functions/toc.php';

// Product type, pricing, and similarity helpers.
require_once __DIR__ . '/functions/product-helpers.php';

// Spec configuration arrays.
require_once __DIR__ . '/functions/specs-config.php';

// Spec retrieval and rendering.
require_once __DIR__ . '/functions/specs-rendering.php';

// Compare component renderers (must load before compare-helpers).
require_once ERH_THEME_DIR . '/template-parts/compare/components/score-ring.php';
require_once ERH_THEME_DIR . '/template-parts/compare/components/product-thumb.php';

// Compare page helpers.
require_once __DIR__ . '/functions/compare-helpers.php';

// Listicle block helpers.
require_once __DIR__ . '/functions/listicle-helpers.php';

// Product schema (Rank Math integration).
require_once __DIR__ . '/functions/product-schema.php';

// =============================================================================
// CORE UTILITIES
// =============================================================================

/**
 * Check if ERH Core plugin is active
 *
 * @return bool True if ERH Core is active.
 */
function erh_is_core_active(): bool {
	return defined( 'ERH_CORE_VERSION' ) && class_exists( 'ERH_Core' );
}

/**
 * Get template part with data
 *
 * Like get_template_part() but allows passing data to the template.
 *
 * @param string $slug Template slug.
 * @param string $name Template name (optional).
 * @param array  $data Data to pass to template.
 */
function erh_get_template_part( string $slug, string $name = '', array $data = array() ): void {
	// Make data available in the template.
	if ( ! empty( $data ) ) {
		extract( $data, EXTR_SKIP );
	}

	// Build template path.
	$templates = array();
	if ( $name ) {
		$templates[] = "{$slug}-{$name}.php";
	}
	$templates[] = "{$slug}.php";

	// Locate and include template.
	$located = locate_template( $templates, false, false );

	if ( $located ) {
		include $located;
	}
}
