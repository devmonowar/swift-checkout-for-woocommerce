<?php
/**
 * Checkout layout override: swaps WooCommerce's form-checkout.php with one
 * of our three original layouts (one-column / two-column / multi-step).
 *
 * Everything is built from WooCommerce's own public hooks
 * (woocommerce_checkout_billing, ..._order_review, ..._payment), only the
 * arrangement and the .swco- CSS are ours. No third-party code is copied.
 *
 * Spec: plan §5.2 row 2, §5.6 skeleton.
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Layout template resolver.
 */
final class SwCo_Layout {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_filter( 'woocommerce_locate_template', array( __CLASS__, 'locate' ), 20, 3 );
		add_action( 'wp', array( __CLASS__, 'reposition_coupon' ) );

		add_shortcode( 'swift_checkout', array( __CLASS__, 'shortcode' ) );

		if ( function_exists( 'register_block_type' ) ) {
			register_block_type( SWCO_DIR . 'blocks/checkout-block' );
		}
	}

	/**
	 * Is the global override active?
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		$settings = SwCo_Settings::instance();
		return 'yes' === $settings->get_value( 'swco_general', 'enabled', 'yes' )
			&& 'yes' === $settings->get_value( 'swco_checkout', 'override_global', 'yes' );
	}

	/**
	 * Current layout style (whitelisted).
	 *
	 * @return string one-column|two-column|multi-step
	 */
	public static function current_layout(): string {
		$funnel_layout = class_exists( 'SwCo_Funnel' ) ? SwCo_Funnel::checkout_layout() : '';
		if ( '' !== $funnel_layout ) {
			return $funnel_layout;
		}
		$layout = SwCo_Settings::instance()->get_value( 'swco_checkout', 'layout', 'two-column' );
		if ( 'one-column' === $layout || 'multi-step' === $layout ) {
			return $layout;
		}
		return 'two-column';
	}

	/**
	 * Shortcode: [swift_checkout class="my-class"].
	 * Renders WooCommerce's own checkout (our layout override applies
	 * automatically) inside a scoped wrapper. Never fatal: falls back to
	 * plain text when WooCommerce is unavailable.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( array $atts ): string {
		$atts  = shortcode_atts( array( 'class' => '' ), $atts, 'swift_checkout' );
		$raw   = $atts['class'];
		$extra = implode(
			' ',
			array_filter(
				array_map( 'sanitize_html_class', is_array( $raw ) ? array() : ( preg_split( '/\s+/', (string) $raw ) ?: array() ) )
			)
		);
		$class = trim( 'swco-shortcode alignwide ' . $extra );

		// Our template prints the coupon itself — unhook the default so it
		// never shows twice on shortcode pages.
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );

		if ( function_exists( 'WC' ) && WC()->cart && WC()->cart->is_empty() ) {
			$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
			return sprintf(
				'<div class="%1$s"><p class="swco-cart-empty">%2$s</p><p><a class="button" href="%3$s">%4$s</a></p></div>',
				esc_attr( $class ),
				esc_html__( 'Your cart is currently empty.', 'swift-checkout-for-woocommerce' ),
				esc_url( $shop_url ),
				esc_html__( 'Return to shop', 'swift-checkout-for-woocommerce' )
			);
		}

		$inner = function_exists( 'WC' ) ? do_shortcode( '[woocommerce_checkout]' ) : '';

		return sprintf(
			'<div class="%1$s">%2$s</div>',
			esc_attr( $class ),
			$inner
		);
	}
	/**
	 * Is the Dhaka (BD) skin active?
	 *
	 * @return bool
	 */
	public static function is_dhaka_skin(): bool {
		return 'dhaka' === SwCo_Settings::instance()->get_value( 'swco_checkout', 'skin', 'default' );
	}

	/**
	 * Dhaka skin extras under the summary: delivery note, trust badges,
	 * reviews line, help line. Each shows only when it has content
	 * (trust badges always show in Dhaka skin).
	 */
	public static function skin_extras(): void {
		if ( ! self::is_dhaka_skin() ) {
			return;
		}
		$settings = SwCo_Settings::instance();
		$delivery = $settings->get_value( 'swco_checkout', 'delivery_note', '' );
		$reviews  = $settings->get_value( 'swco_checkout', 'reviews_text', '' );
		$help     = $settings->get_value( 'swco_checkout', 'help_text', '' );

		if ( '' !== $delivery ) {
			echo '<p class="swco-delivery-note">' . esc_html( $delivery ) . '</p>';
		}
		self::trust_badges();
		if ( '' !== $reviews ) {
			echo '<p class="swco-reviews">' . esc_html( $reviews ) . '</p>';
		}
		if ( '' !== $help ) {
			echo '<p class="swco-help">' . esc_html( $help ) . '</p>';
		}
	}

	/**
	 * Trust badges row (Dhaka skin only). Translatable BD-flavored defaults.
	 */
	public static function trust_badges(): void {
		if ( ! self::is_dhaka_skin() ) {
			return;
		}
		$badges = array(
			__( 'Cash on Delivery', 'swift-checkout-for-woocommerce' ),
			__( 'Fast Delivery in Dhaka', 'swift-checkout-for-woocommerce' ),
			__( 'Easy 7-day Return', 'swift-checkout-for-woocommerce' ),
		);
		echo '<ul class="swco-trust">';
		foreach ( $badges as $badge ) {
			echo '<li>' . esc_html( $badge ) . '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Sticky mobile order bar (total + one-tap order). Total is synced
	 * by frontend.js after every cart refresh.
	 */
	public static function sticky_bar(): void {
		?>
		<div class="swco-sticky-bar">
			<span class="swco-sticky-total" aria-live="polite"></span>
			<button type="button" class="swco-sticky-btn"><?php echo esc_html__( 'Place Order', 'swift-checkout-for-woocommerce' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Point WooCommerce at our template file for the checkout form.
	 *
	 * @param string $template      Resolved template path.
	 * @param string $template_name Requested template (e.g. checkout/form-checkout.php).
	 * @param string $template_path Lookup path (unused).
	 * @return string
	 */
	public static function locate( string $template, string $template_name, string $template_path = '' ): string {
		unset( $template_path );
		if ( 'checkout/form-checkout.php' !== $template_name ) {
			return $template;
		}
		if ( is_admin() || ! self::is_active() ) {
			return $template;
		}
		$file = SWCO_DIR . 'templates/checkout-' . self::current_layout() . '.php';
		return file_exists( $file ) ? $file : $template;
	}

	/**
	 * Our templates print the coupon form inside .swco-coupon, so unhook
	 * WooCommerce's default top-of-form coupon to avoid printing it twice.
	 */
	public static function reposition_coupon(): void {
		if ( ! self::is_active() || ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
	}
}
