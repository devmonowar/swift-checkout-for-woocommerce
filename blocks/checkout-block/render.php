<?php
/**
 * Block server render: reuse the shortcode so editor and frontend
 * can never drift apart (single render path).
 *
 * Variables available: $attributes, $content, $block.
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

$extra = isset( $attributes['extraClass'] ) && is_string( $attributes['extraClass'] ) ? $attributes['extraClass'] : '';

echo SwCo_Layout::shortcode( array( 'class' => $extra ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode() escapes its own output.
