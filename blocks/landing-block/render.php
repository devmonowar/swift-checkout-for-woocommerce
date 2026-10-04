<?php
/**
 * Block server render: reuse the shortcode (single render path).
 *
 * Variables available: $attributes, $content, $block.
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

$product_id = isset( $attributes['productId'] ) ? absint( $attributes['productId'] ) : 0;
$button     = isset( $attributes['buttonText'] ) && is_string( $attributes['buttonText'] ) ? $attributes['buttonText'] : '';

echo SwCo_Landing::shortcode( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode() escapes its own output.
	array(
		'id'     => $product_id,
		'button' => $button,
	)
);
