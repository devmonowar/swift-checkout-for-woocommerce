<?php
/**
 * Auto-apply coupon: from the URL (?swco_coupon=SAVE10, param name is a
 * setting) or from the global auto_coupon fallback.
 *
 * Spec: plan §5.2 row 5, §6.1 swco_coupon.
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Applies coupons on the frontend before totals are calculated.
 */
final class SwCo_Coupon {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'wp_loaded', array( __CLASS__, 'maybe_apply_url_coupon' ), 20 );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'maybe_apply_global_coupon' ) );
		// Our own layouts don't fire woocommerce_before_checkout_form, so the
		// global coupon is also applied here (cart is ready, apply is idempotent).
		add_action( 'template_redirect', array( __CLASS__, 'maybe_apply_global_coupon' ) );
	}

	/**
	 * Is the plugin enabled?
	 *
	 * @return bool
	 */
	private static function enabled(): bool {
		return 'yes' === SwCo_Settings::instance()->get_value( 'swco_general', 'enabled', 'yes' );
	}

	/**
	 * Coupon code from the URL param (sanitized + uppercased).
	 *
	 * @return string Empty when absent.
	 */
	public static function url_coupon_code(): string {
		$param = SwCo_Settings::instance()->get_value( 'swco_coupon', 'url_param', 'swco_coupon' );
		$param = '' !== $param ? sanitize_key( $param ) : 'swco_coupon';
		if ( ! isset( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only coupon lookup.
			return '';
		}
		return function_exists( 'wc_format_coupon_code' )
			? wc_format_coupon_code( sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only coupon lookup; value sanitized here.
			: strtoupper( sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only coupon lookup; value sanitized here.
	}

	/**
	 * Apply the URL coupon as early as the cart is ready.
	 */
	public static function maybe_apply_url_coupon(): void {
		if ( is_admin() || ! self::enabled() ) {
			return;
		}
		$code = self::url_coupon_code();
		if ( '' !== $code ) {
			self::apply( $code );
		}
	}

	/**
	 * Apply the global fallback coupon on the checkout form.
	 */
	public static function maybe_apply_global_coupon(): void {
		if ( ! self::enabled() ) {
			return;
		}
		$code = SwCo_Settings::instance()->get_value( 'swco_coupon', 'auto_coupon', '' );
		if ( '' !== $code ) {
			self::apply( $code );
		}
	}

	/**
	 * Apply one coupon code when it is valid to do so.
	 *
	 * @param string $code Coupon code (already sanitized).
	 */
	private static function apply( string $code ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		if ( WC()->cart->has_discount( $code ) ) {
			return;
		}
		$min = (float) SwCo_Settings::instance()->get_value( 'swco_coupon', 'min_subtotal', 0 );
		if ( $min > 0 && (float) WC()->cart->get_subtotal() < $min ) {
			return;
		}
		WC()->cart->apply_coupon( $code );
	}
}
