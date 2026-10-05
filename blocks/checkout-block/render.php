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

$swco_extra = isset( $attributes['extraClass'] ) && is_string( $attributes['extraClass'] ) ? sanitize_html_class( $attributes['extraClass'] ) : '';

echo SwCo_Layout::shortcode( array( 'class' => $swco_extra ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- input sanitized above; shortcode() escapes its own output.
