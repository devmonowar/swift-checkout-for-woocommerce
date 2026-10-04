<?php
/**
 * Guest-friendly checkout: anyone can order without an account, and (when
 * enabled) an account is auto-created/linked from the order email.
 * WooCommerce sends the new-account email itself via wc_create_new_customer().
 *
 * Spec: owner request 2026-10-04 (v1.0.0).
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Auto-creates (or links) a customer account after a guest order.
 */
final class SwCo_Accounts {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'maybe_create_account' ), 20, 3 );
	}

	/**
	 * Is auto-create enabled (plugin + master switch)?
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		$settings = SwCo_Settings::instance();
		return 'yes' === $settings->get_value( 'swco_general', 'enabled', 'yes' )
			&& 'yes' === $settings->get_value( 'swco_general', 'auto_create_account', 'no' );
	}

	/**
	 * Create/link an account for guest orders.
	 * Existing email = link order only, never duplicate users.
	 *
	 * @param int      $order_id    Order ID.
	 * @param array    $posted_data Checkout fields (unused).
	 * @param WC_Order $order       Order object.
	 */
	public static function maybe_create_account( int $order_id, array $posted_data, $order ): void {
		unset( $posted_data );
		if ( ! self::enabled() ) {
			return;
		}
		if ( ! $order instanceof WC_Order ) {
			$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		}
		if ( ! $order instanceof WC_Order || 0 !== (int) $order->get_customer_id() ) {
			return;
		}
		$email = $order->get_billing_email();
		if ( ! is_email( $email ) ) {
			return;
		}

		$user_id = email_exists( $email );
		if ( ! $user_id && function_exists( 'wc_create_new_customer' ) ) {
			$user_id = wc_create_new_customer( $email );
			if ( is_wp_error( $user_id ) ) {
				return;
			}
		}
		if ( ! $user_id ) {
			return;
		}

		$order->set_customer_id( (int) $user_id );
		$order->save();
		$order->add_order_note( __( 'Customer account auto-created/linked by Swift Checkout.', 'swift-checkout-for-woocommerce' ) );
	}
}
