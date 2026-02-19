<?php
/**
 * Form Helper Functions
 *
 * Server-rendered form components for zero-FOUC custom selects.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render a server-side custom select with trigger button.
 *
 * Outputs the full .custom-select wrapper so JS only needs to hydrate
 * (attach events + build dropdown) instead of creating everything from scratch.
 * This eliminates FOUC and CLS because the trigger button is in the HTML from first paint.
 *
 * @param array $args {
 *     @type string $name        Required. Name attribute for the native select.
 *     @type array  $options     Required. Array of [ value => label ] pairs.
 *     @type string $id          Optional. ID for the native select.
 *     @type string $placeholder Optional. Placeholder text when no value selected.
 *     @type string $selected    Optional. Currently selected value.
 *     @type string $variant     Optional. 'inline', 'sm', 'lg', or empty for default.
 *     @type string $align       Optional. 'right' for right-aligned dropdown.
 *     @type string $class       Optional. Extra CSS classes on the wrapper.
 *     @type array  $attrs       Optional. Extra attributes on the native select as key => value.
 *     @type bool   $required    Optional. Whether the select is required.
 *     @type bool   $disabled_first Optional. Whether the first option is disabled (for "Select a topic" pattern).
 * }
 */
function erh_custom_select( array $args ): void {
	$defaults = [
		'name'           => '',
		'options'        => [],
		'id'             => '',
		'placeholder'    => '',
		'selected'       => '',
		'variant'        => '',
		'align'          => '',
		'class'          => '',
		'attrs'          => [],
		'required'       => false,
		'disabled_first' => false,
	];

	$args = wp_parse_args( $args, $defaults );

	// Build wrapper classes.
	$wrapper_classes = [ 'custom-select' ];

	switch ( $args['variant'] ) {
		case 'inline':
			$wrapper_classes[] = 'custom-select--inline';
			break;
		case 'sm':
			$wrapper_classes[] = 'custom-select-sm';
			break;
		case 'lg':
			$wrapper_classes[] = 'custom-select-lg';
			break;
	}

	if ( 'right' === $args['align'] ) {
		$wrapper_classes[] = 'custom-select--align-right';
	}

	if ( ! empty( $args['class'] ) ) {
		$wrapper_classes[] = $args['class'];
	}

	// Determine display text for the trigger button.
	$display_text   = $args['placeholder'] ?: 'Select...';
	$is_placeholder = true;

	if ( '' !== $args['selected'] && isset( $args['options'][ $args['selected'] ] ) ) {
		$display_text   = $args['options'][ $args['selected'] ];
		$is_placeholder = false;
	}

	// Build extra attributes string for the native select.
	$extra_attrs = '';
	foreach ( $args['attrs'] as $attr_name => $attr_value ) {
		if ( true === $attr_value ) {
			$extra_attrs .= ' ' . esc_attr( $attr_name );
		} else {
			$extra_attrs .= ' ' . esc_attr( $attr_name ) . '="' . esc_attr( $attr_value ) . '"';
		}
	}

	$id_attr = $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : '';
	$required_attr = $args['required'] ? ' required' : '';

	?>
	<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>">
		<button type="button" class="custom-select-trigger" aria-haspopup="listbox" aria-expanded="false">
			<span class="custom-select-value<?php echo $is_placeholder ? ' is-placeholder' : ''; ?>"><?php echo esc_html( $display_text ); ?></span>
			<svg class="icon" aria-hidden="true"><use href="#icon-chevron-down"></use></svg>
		</button>
		<select<?php echo $id_attr; ?> name="<?php echo esc_attr( $args['name'] ); ?>" data-custom-select<?php echo $extra_attrs . $required_attr; ?><?php
			if ( $args['placeholder'] ) {
				echo ' data-placeholder="' . esc_attr( $args['placeholder'] ) . '"';
			}
		?>>
			<?php
			$first = true;
			foreach ( $args['options'] as $value => $label ) :
				$is_selected = ( (string) $value === (string) $args['selected'] );
				$is_disabled = $first && $args['disabled_first'];
				?>
				<option value="<?php echo esc_attr( $value ); ?>"<?php
					selected( $is_selected );
					if ( $is_disabled ) {
						echo ' disabled';
					}
				?>><?php echo esc_html( $label ); ?></option>
				<?php
				$first = false;
			endforeach;
			?>
		</select>
	</div>
	<?php
}
