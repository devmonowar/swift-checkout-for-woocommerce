<?php
/**
 * Simple Order Bump: a checkbox on checkout offering one fixed product,
 * optionally at a fixed discount %. Two positions: before-payment and
 * after-summary. No smart rules (final scope).
 *
 * Spec: plan §12.2 (v1.0.0-complete), positions §5.6.
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the bump box, toggles it via AJAX, prices the discount.
 */
final class SwCo_Bump {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'wp_ajax_swco_toggle_bump', array( __CLASS__, 'toggle' ) );
		add_action( 'wp_ajax_nopriv_swco_toggle_bump', array( __CLASS__, 'toggle' ) );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_discount' ), 20, 1 );
	}

	/**
	 * Bump enabled in settings?
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		$settings = SwCo_Settings::instance();
		return 'yes' === $settings->get_value( 'swco_general', 'enabled', 'yes' )
			&& 'yes' === $settings->get_value( 'swco_bump', 'enabled', 'no' );
	}

	/**
	 * The configured product, or null when unusable.
	 * Simple, purchasable, in-stock only — anything else cannot be
	 * quick-added from a checkbox.
	 *
	 * @return WC_Product|null
	 */
	public static function bump_product() {
		$id = absint( SwCo_Settings::instance()->get_value( 'swco_bump', 'product_id', 0 ) );
		if ( $id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$product = wc_get_product( $id );
		if ( ! $product instanceof WC_Product ) {
			return null;
		}
		if ( ! $product->is_type( 'simple' ) || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return null;
		}
		return $product;
	}

	/**
	 * Discount percent, clamped 0–90.
	 *
	 * @return int
	 */
	public static function discount_pct(): int {
		$settings = SwCo_Settings::instance();
		return min( 90, max( 0, absint( $settings->get_value( 'swco_bump', 'discount_pct', 0 ) ) ) );
	}

	/**
	 * Configured position (whitelisted).
	 *
	 * @return string before-payment|after-summary
	 */
	public static function position(): string {
		$pos = SwCo_Settings::instance()->get_value( 'swco_bump', 'position', 'before-payment' );
		return 'after-summary' === $pos ? 'after-summary' : 'before-payment';
	}

	/**
	 * Cart key when the bump product is already in the cart.
	 *
	 * @return string Empty when absent.
	 */
	public static function cart_key_for_bump(): string {
		$product = self::bump_product();
		if ( ! $product || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}
		foreach ( WC()->cart->get_cart() as $cart_key => $item ) {
			if ( (int) ( $item['product_id'] ?? 0 ) === $product->get_id() ) {
				return (string) $cart_key;
			}
		}
		return '';
	}

	/**
	 * Show the box at this slot? Hidden when already in cart (the cart
	 * table itself is the source of truth then).
	 *
	 * @param string $position Slot being rendered.
	 * @return bool
	 */
	public static function should_show( string $position ): bool {
		return self::enabled()
			&& $position === self::position()
			&& null !== self::bump_product()
			&& '' === self::cart_key_for_bump();
	}

	/**
	 * Discounted unit price for display and cart pricing.
	 *
	 * @param WC_Product $product Bump product.
	 * @return float
	 */
	public static function discounted_price( $product ): float {
		$price = (float) $product->get_price();
		$disc  = self::discount_pct();
		if ( $disc <= 0 ) {
			return $price;
		}
		return round( $price * ( 100 - $disc ) / 100, 2 );
	}

	/**
	 * Print the bump box at a template slot. Called from both layouts.
	 *
	 * @param string $position before-payment|after-summary.
	 */
	public static function render( string $position ): void {
		if ( ! self::should_show( $position ) ) {
			return;
		}
		$product  = self::bump_product();
		$settings = SwCo_Settings::instance();
		$title    = $settings->get_value( 'swco_bump', 'title', '' );
		$desc     = $settings->get_value( 'swco_bump', 'desc', '' );
		$price    = self::discounted_price( $product );
		?>
		<div class="swco-bump swco-bump-<?php echo esc_attr( $position ); ?>">
			<label class="swco-bump-label">
				<input type="checkbox" class="swco-bump-check" data-product-id="<?php echo esc_attr( (string) $product->get_id() ); ?>" />
				<span class="swco-bump-text">
					<?php if ( '' !== $title ) : ?>
						<strong class="swco-bump-title"><?php echo esc_html( $title ); ?></strong>
					<?php endif; ?>
					<span class="swco-bump-product"><?php echo esc_html( $product->get_name() ); ?></span>
					<span class="swco-bump-price">
						<?php if ( $price < (float) $product->get_price() ) : ?>
							<del><?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?></del>
						<?php endif; ?>
						<ins><?php echo wp_kses_post( wc_price( $price ) ); ?></ins>
					</span>
					<?php if ( '' !== $desc ) : ?>
						<span class="swco-bump-desc"><?php echo esc_html( $desc ); ?></span>
					<?php endif; ?>
				</span>
			</label>
		</div>
		<?php
	}

	/**
	 * AJAX: check = add bump (qty 1), uncheck = remove it.
	 */
	public static function toggle(): void {
		if ( ! check_ajax_referer( SwCo_Settings::NONCE, '_ajax_nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'swift-checkout-for-woocommerce' ) ), 403 );
			return;
		}
		if ( ! self::enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Order bump is disabled.', 'swift-checkout-for-woocommerce' ) ), 403 );
			return;
		}
		$posted_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product   = self::bump_product();
		if ( ! $product || $posted_id !== $product->get_id() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'swift-checkout-for-woocommerce' ) ) );
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'Cart unavailable.', 'swift-checkout-for-woocommerce' ) ) );
			return;
		}

		$cart_key = self::cart_key_for_bump();
		$checked  = ! empty( $_POST['checked'] );
		if ( $checked && '' === $cart_key ) {
			WC()->cart->add_to_cart( $product->get_id(), 1 );
		} elseif ( ! $checked && '' !== $cart_key ) {
			WC()->cart->remove_cart_item( $cart_key );
		}
		WC()->cart->calculate_totals();

		ob_start();
		SwCo_Cart_Tweaks::render_cart_table();
		$cart_html = ob_get_clean();

		wp_send_json_success(
			array(
				'fragments' => array(
					'div.swco-cart' => $cart_html,
				),
				'in_cart'   => '' !== self::cart_key_for_bump(),
			)
		);
	}

	/**
	 * Price the bump line at the discounted rate during totals.
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	public static function apply_discount( $cart ): void {
		if ( ( function_exists( 'is_admin' ) && is_admin() && ! wp_doing_ajax() ) || ! self::enabled() ) {
			return;
		}
		$product = self::bump_product();
		$disc    = self::discount_pct();
		if ( ! $product || $disc <= 0 ) {
			return;
		}
		$new_price = self::discounted_price( $product );
		foreach ( $cart->get_cart() as $item ) {
			if ( (int) ( $item['product_id'] ?? 0 ) === $product->get_id()
				&& isset( $item['data'] ) && $item['data'] instanceof WC_Product ) {
				$item['data']->set_price( $new_price );
			}
		}
	}
}
