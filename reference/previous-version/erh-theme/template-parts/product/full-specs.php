<?php
/**
 * Product Full Specifications
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// This will be expanded to show all specs in collapsible groups
// For now, show a placeholder
?>
<section class="full-specs">
    <h2 class="full-specs-title"><?php esc_html_e( 'Full specifications', 'erh' ); ?></h2>
    <div class="full-specs-content">
        <!-- Spec groups will be rendered here -->
        <p class="empty-state"><?php esc_html_e( 'Full specifications coming soon.', 'erh' ); ?></p>
    </div>
</section>
