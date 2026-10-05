<?php
/**
 * Block server render: reuse the shortcode (single render path).
 *
 * Variables available: $attributes, $content, $block.
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

$swco_product_id = isset( $attributes['productId'] ) ? absint( $attributes['productId'] ) : 0;
$swco_button     = isset( $attributes['buttonText'] ) && is_string( $attributes['buttonText'] ) ? sanitize_text_field( $attributes['buttonText'] ) : '';

echo SwCo_Landing::shortcode( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inputs sanitized above; shortcode() escapes its own output.
	array(
		'id'     => $swco_product_id, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- absint() above.
		'button' => $swco_button, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitize_text_field() above.
	)
);
