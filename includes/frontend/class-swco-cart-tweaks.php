<?php
/**
 * Cart-in-checkout table: an original, minimal table with qty +/- and
 * remove buttons that the AJAX handlers (SwCo_Ajax) refresh in place.
 *
 * Column toggles come from `cart_columns`; the product name is always shown
 * (a cart row without a name would be broken).
 *
 * Spec: plan §5.6 .swco-cart, §6.1 cart_columns.
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the cart section inside checkout templates.
 */
final class SwCo_Cart_Tweaks {

	/**
	 * Print either the native order review or our AJAX table.
	 */
	public static function render_cart_section(): void {
		$settings = SwCo_Settings::instance();
		if ( 'yes' !== $settings->get_value( 'swco_checkout', 'cart_in_checkout', 'yes' ) ) {
			do_action( 'woocommerce_checkout_order_review' );
			return;
		}
		self::render_cart_table();
		self::render_shipping_methods();
	}

	/**
	 * Shipping method radios (one group per package). Names and classes
	 * match WooCommerce core, so its own update_checkout recalculates;
	 * we then refresh our fragment on the updated_checkout event.
	 */
	public static function render_shipping_methods(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->cart->needs_shipping() ) {
			return;
		}
		$packages = WC()->cart->get_shipping_packages();
		if ( empty( $packages ) ) {
			return;
		}
		$chosen = function_exists( 'WC' ) && WC()->session ? (array) WC()->session->get( 'chosen_shipping_methods', array() ) : array();
		?>
		<div class="swco-shipping-methods">
			<h3><?php echo esc_html__( 'Shipping Method', 'swift-checkout-for-woocommerce' ); ?></h3>
			<?php foreach ( $packages as $index => $package ) : ?>
				<?php
				$rates = isset( $package['rates'] ) && is_array( $package['rates'] ) ? $package['rates'] : array();
				if ( empty( $rates ) ) {
					continue;
				}
				if ( 1 === count( $rates ) ) {
					$only = reset( $rates );
					echo '<p class="swco-shipping-single">' . esc_html( $only->get_label() ) . ': ' . wp_kses_post( wc_price( $only->get_cost() + array_sum( $only->get_taxes() ) ) ) . '</p>';
					continue;
				}
				?>
				<ul>
					<?php foreach ( $rates as $rate ) : ?>
						<?php
						$rate_id = $rate->get_id();
						$is_set  = isset( $chosen[ $index ] ) && $chosen[ $index ] === $rate_id;
						?>
						<li>
							<label>
								<input type="radio" name="shipping_method[<?php echo esc_attr( (string) $index ); ?>]" value="<?php echo esc_attr( $rate_id ); ?>" <?php checked( $is_set ); ?> class="shipping_method" />
								<?php echo esc_html( $rate->get_label() ); ?>
								<span class="swco-shipping-cost"><?php echo wp_kses_post( wc_price( $rate->get_cost() + array_sum( $rate->get_taxes() ) ) ); ?></span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Active columns (whitelisted). Name is always on.
	 *
	 * @return string[]
	 */
	public static function columns(): array {
		$cols    = SwCo_Settings::instance()->get_value( 'swco_checkout', 'cart_columns', array( 'remove', 'thumbnail', 'price', 'qty' ) );
		$allowed = array( 'remove', 'thumbnail', 'price', 'qty' );
		$out     = array();
		foreach ( (array) $cols as $col ) {
			$col = sanitize_key( $col );
			if ( in_array( $col, $allowed, true ) ) {
				$out[] = $col;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Print the cart table. No echo before this point on error — returns
	 * a notice instead so AJAX fragments stay valid HTML.
	 */
	public static function render_cart_table(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			echo '<p class="swco-cart-empty">' . esc_html__( 'Your cart is empty.', 'swift-checkout-for-woocommerce' ) . '</p>';
			return;
		}

		$cols     = self::columns();
		$ajax     = 'yes' === SwCo_Settings::instance()->get_value( 'swco_checkout', 'cart_ajax', 'yes' );
		$show_qty = $ajax && in_array( 'qty', $cols, true );
		?>
		<table class="swco-cart-table shop_table">
			<tbody>
				<?php foreach ( WC()->cart->get_cart() as $cart_key => $item ) : ?>
					<?php
					$product = $item['data'] ?? null;
					if ( ! $product instanceof WC_Product ) {
						continue;
					}
					$qty = (int) ( $item['quantity'] ?? 0 );
					?>
					<tr class="swco-cart-row" data-cart-key="<?php echo esc_attr( $cart_key ); ?>">
						<?php if ( $ajax && in_array( 'remove', $cols, true ) ) : ?>
							<td class="swco-col-remove">
								<button type="button" class="swco-remove" data-cart-key="<?php echo esc_attr( $cart_key ); ?>" aria-label="<?php echo esc_attr__( 'Remove item', 'swift-checkout-for-woocommerce' ); ?>">&times;</button>
							</td>
						<?php endif; ?>
						<?php if ( in_array( 'thumbnail', $cols, true ) ) : ?>
							<td class="swco-col-thumb"><?php echo $product->get_image( 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce escapes product images. ?></td>
						<?php endif; ?>
						<td class="swco-col-name">
							<span class="swco-name"><?php echo esc_html( $product->get_name() ); ?></span>
							<?php if ( $show_qty ) : ?>
								<span class="swco-qty">
									<button type="button" class="swco-qty-btn" data-cart-key="<?php echo esc_attr( $cart_key ); ?>" data-qty="<?php echo esc_attr( (string) max( 0, $qty - 1 ) ); ?>" aria-label="<?php echo esc_attr__( 'Decrease quantity', 'swift-checkout-for-woocommerce' ); ?>">−</button>
									<span class="swco-qty-num"><?php echo esc_html( (string) $qty ); ?></span>
									<button type="button" class="swco-qty-btn" data-cart-key="<?php echo esc_attr( $cart_key ); ?>" data-qty="<?php echo esc_attr( (string) min( 99, $qty + 1 ) ); ?>" aria-label="<?php echo esc_attr__( 'Increase quantity', 'swift-checkout-for-woocommerce' ); ?>">+</button>
								</span>
							<?php elseif ( in_array( 'qty', $cols, true ) ) : ?>
								<span class="swco-qty-static">&times; <?php echo esc_html( (string) $qty ); ?></span>
							<?php endif; ?>
						</td>
						<?php if ( in_array( 'price', $cols, true ) ) : ?>
							<td class="swco-col-price"><?php echo wp_kses_post( WC()->cart->get_product_subtotal( $product, $qty ) ); ?></td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr class="swco-subtotal">
					<th><?php echo esc_html__( 'Subtotal', 'swift-checkout-for-woocommerce' ); ?></th>
					<td><?php echo wp_kses_post( WC()->cart->get_cart_subtotal() ); ?></td>
				</tr>
				<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
					<tr class="swco-coupon-row">
						<th>
							<?php echo esc_html( sprintf( __( 'Coupon: %s', 'swift-checkout-for-woocommerce' ), $code ) ); ?>
							<button type="button" class="swco-coupon-remove" data-coupon="<?php echo esc_attr( $code ); ?>" aria-label="<?php echo esc_attr__( 'Remove coupon', 'swift-checkout-for-woocommerce' ); ?>">&times;</button>
						</th>
						<td><?php echo wp_kses_post( wc_cart_totals_coupon_html( $coupon ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				<tr class="swco-total">
					<th><?php echo esc_html__( 'Total', 'swift-checkout-for-woocommerce' ); ?></th>
					<td><?php echo wp_kses_post( WC()->cart->get_total() ); ?></td>
				</tr>
			</tfoot>
		</table>
		<?php
	}
}
