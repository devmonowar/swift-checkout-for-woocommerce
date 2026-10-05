<?php
/**
 * AJAX cart tweaks: quantity +/- and remove without page reload.
 *
 * Spec: plan §5.5 — exactly 2 actions on admin-ajax.php, nonce `swco_nonce`.
 * Every response re-renders the .swco-cart fragment; the JS then fires
 * WooCommerce's own `update_checkout` so totals stay correct.
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles swco_update_qty + swco_remove_item.
 */
final class SwCo_Ajax {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'wp_ajax_swco_update_qty', array( __CLASS__, 'update_qty' ) );
		add_action( 'wp_ajax_nopriv_swco_update_qty', array( __CLASS__, 'update_qty' ) );
		add_action( 'wp_ajax_swco_remove_item', array( __CLASS__, 'remove_item' ) );
		add_action( 'wp_ajax_nopriv_swco_remove_item', array( __CLASS__, 'remove_item' ) );
		add_action( 'wp_ajax_swco_remove_coupon', array( __CLASS__, 'remove_coupon' ) );
		add_action( 'wp_ajax_nopriv_swco_remove_coupon', array( __CLASS__, 'remove_coupon' ) );
		add_action( 'wp_ajax_swco_cart_fragment', array( __CLASS__, 'cart_fragment' ) );
		add_action( 'wp_ajax_nopriv_swco_cart_fragment', array( __CLASS__, 'cart_fragment' ) );
	}

	/**
	 * Update an item's quantity (0 removes it). Clamped to 0–99.
	 */
	public static function update_qty(): void {
		if ( ! check_ajax_referer( SwCo_Settings::NONCE, '_ajax_nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'swift-checkout-for-woocommerce' ) ), 403 );
			return;
		}
		if ( ! self::tweaks_allowed() ) {
			wp_send_json_error( array( 'message' => __( 'Checkout tweaks are disabled.', 'swift-checkout-for-woocommerce' ) ), 403 );
			return;
		}
		$cart_key = isset( $_POST['cart_key'] ) ? sanitize_text_field( wp_unslash( $_POST['cart_key'] ) ) : '';
		$qty      = isset( $_POST['qty'] ) ? absint( $_POST['qty'] ) : 0;

		if ( '' === $cart_key || $qty > 99 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid quantity.', 'swift-checkout-for-woocommerce' ) ) );
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'Cart unavailable.', 'swift-checkout-for-woocommerce' ) ) );
			return;
		}

		WC()->cart->set_quantity( $cart_key, $qty, true );
		WC()->cart->calculate_totals();

		self::send_cart_response();
	}

	/**
	 * Remove one item from the cart.
	 */
	public static function remove_item(): void {
		if ( ! check_ajax_referer( SwCo_Settings::NONCE, '_ajax_nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'swift-checkout-for-woocommerce' ) ), 403 );
			return;
		}
		if ( ! self::tweaks_allowed() ) {
			wp_send_json_error( array( 'message' => __( 'Checkout tweaks are disabled.', 'swift-checkout-for-woocommerce' ) ), 403 );
			return;
		}
		$cart_key = isset( $_POST['cart_key'] ) ? sanitize_text_field( wp_unslash( $_POST['cart_key'] ) ) : '';

		if ( '' === $cart_key ) {
			wp_send_json_error( array( 'message' => __( 'Invalid item.', 'swift-checkout-for-woocommerce' ) ) );
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'Cart unavailable.', 'swift-checkout-for-woocommerce' ) ) );
			return;
		}

		WC()->cart->remove_cart_item( $cart_key );
		WC()->cart->calculate_totals();

		self::send_cart_response();
	}

	/**
	 * Plugin enabled AND the AJAX cart tweak switched on?
	 *
	 * @return bool
	 */
	private static function tweaks_allowed(): bool {
		$settings = SwCo_Settings::instance();
		return 'yes' === $settings->get_value( 'swco_general', 'enabled', 'yes' )
			&& 'yes' === $settings->get_value( 'swco_checkout', 'cart_ajax', 'yes' );
	}

	/**
	 * Remove an applied coupon by code.
	 */
	public static function remove_coupon(): void {
		if ( ! check_ajax_referer( SwCo_Settings::NONCE, '_ajax_nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'swift-checkout-for-woocommerce' ) ), 403 );
			return;
		}
		$raw  = $_POST['coupon'] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified with check_ajax_referer() above; unslashed + sanitized on the next line.
		$code = is_string( $raw ) ? sanitize_text_field( wp_unslash( $raw ) ) : '';
		if ( '' === $code ) {
			wp_send_json_error( array( 'message' => __( 'Invalid coupon.', 'swift-checkout-for-woocommerce' ) ) );
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'Cart unavailable.', 'swift-checkout-for-woocommerce' ) ) );
			return;
		}
		if ( function_exists( 'wc_format_coupon_code' ) ) {
			$code = wc_format_coupon_code( $code );
		}

		WC()->cart->remove_coupon( $code );
		WC()->cart->calculate_totals();

		self::send_cart_response();
	}

	/**
	 * Return a fresh cart fragment (used after WooCommerce's own
	 * update_checkout, e.g. shipping method changes). No totals trigger
	 * here — the caller already recalculated.
	 */
	public static function cart_fragment(): void {
		if ( ! check_ajax_referer( SwCo_Settings::NONCE, '_ajax_nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'swift-checkout-for-woocommerce' ) ), 403 );
			return;
		}
		if ( 'yes' !== SwCo_Settings::instance()->get_value( 'swco_general', 'enabled', 'yes' ) ) {
			wp_send_json_error( array( 'message' => __( 'Checkout tweaks are disabled.', 'swift-checkout-for-woocommerce' ) ), 403 );
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'Cart unavailable.', 'swift-checkout-for-woocommerce' ) ) );
			return;
		}

		ob_start();
		SwCo_Cart_Tweaks::render_cart_table();
		$cart_html = ob_get_clean();

		wp_send_json_success(
			array(
				'fragments' => array(
					'div.swco-cart' => $cart_html,
				),
			)
		);
	}

	/**
	 * Shared success payload: fresh .swco-cart HTML + totals.
	 * Empty cart reports back so the JS can redirect to the cart page.
	 */
	private static function send_cart_response(): void {
		if ( WC()->cart->is_empty() ) {
			wp_send_json_success(
				array(
					'empty'    => true,
					'redirect' => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' ),
				)
			);
			return;
		}

		ob_start();
		SwCo_Cart_Tweaks::render_cart_table();
		$cart_html = ob_get_clean();

		$count = WC()->cart->get_cart_contents_count();

		wp_send_json_success(
			array(
				'fragments'  => array(
					'div.swco-cart' => $cart_html,
				),
				'cart_total' => WC()->cart->get_cart_subtotal(),
				'cart_count' => $count,
			)
		);
	}
}
