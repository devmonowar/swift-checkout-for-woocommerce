<?php
/**
 * Elementor integration: Checkout, Landing Order, and Buy Now widgets.
 *
 * Each widget is a thin UI over the matching shortcode — one render path,
 * so editor and frontend can never drift apart. Loads only when Elementor
 * is active; otherwise this file is inert.
 *
 * Spec: owner request 2026-10-04 (v1.0.0).
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the widget category + widgets on Elementor init.
 */
final class SwCo_Elementor {

	/**
	 * Register hooks (safe without Elementor — callbacks bail early).
	 */
	public static function init(): void {
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'add_category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
	}

/**
 * Registers the widget category + widgets on Elementor init.
 *
 * This file never references Elementor classes at parse time, so it is
 * safe to load on every request. The widget classes (which extend
 * Elementor\Widget_Base) live in class-swco-elementor-widgets.php and are
 * required only after Elementor proves it is loaded.
 *
 * @param object $manager Widgets manager.
 */
	public static function add_category( $manager ): void {
		if ( ! method_exists( $manager, 'add_category' ) ) {
			return;
		}
		$manager->add_category(
			'swift-checkout',
			array(
				'title' => __( 'Swift Checkout', 'swift-checkout-for-woocommerce' ),
				'icon'  => 'fa fa-cart-plus',
			)
		);
	}

	/**
	 * Register our three widgets.
	 *
	 * @param object $manager Widgets manager.
	 */
	public static function register_widgets( $manager ): void {
		if ( ! method_exists( $manager, 'register' ) || ! class_exists( 'Elementor\Widget_Base' ) ) {
			return;
		}
		require_once __DIR__ . '/class-swco-elementor-widgets.php';
		$manager->register( new SwCo_Elementor_Checkout_Widget() );
		$manager->register( new SwCo_Elementor_Landing_Widget() );
		$manager->register( new SwCo_Elementor_Buy_Now_Widget() );
	}
}
