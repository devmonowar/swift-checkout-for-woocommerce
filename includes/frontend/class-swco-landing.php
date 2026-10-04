<?php
/**
 * Landing order section: product summary + simple order form.
 *
 * For single-product landing pages (BD style): the owner designs the page,
 * drops [swift_landing id="123"], and shoppers order with name + mobile +
 * address — no cart, no account needed. Orders come in as Cash on Delivery.
 *
 * Spec: owner request 2026-10-04 (v1.0.0).
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the landing section and processes its orders.
 */
final class SwCo_Landing {

	/**
	 * Form nonce action.
	 */
	const NONCE = 'swco_landing_order';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_shortcode( 'swift_landing', array( __CLASS__, 'shortcode' ) );
		add_action( 'admin_post_swco_landing_order', array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_swco_landing_order', array( __CLASS__, 'handle_submit' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'localize_products' ) );
		if ( function_exists( 'register_block_type' ) ) {
			register_block_type( SWCO_DIR . 'blocks/landing-block' );
		}
	}

	/**
	 * Load the tiny landing script only where the shortcode/block sits.
	 */
	public static function assets(): void {
		if ( ! function_exists( 'has_shortcode' ) ) {
			return;
		}
		global $post;
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( class_exists( 'SwCo_Assets' ) && SwCo_Assets::page_contains( array( 'swift_landing', 'swco/landing' ) ) ) {
			$found = true;
		} else {
			$found = function_exists( 'has_shortcode' ) && has_shortcode( (string) $post->post_content, 'swift_landing' );
		}
		if ( ! $found ) {
			return;
		}
		wp_enqueue_script(
			'swco-landing',
			SWCO_URL . 'assets/js/landing.js',
			array(),
			SWCO_VERSION,
			true
		);
		wp_enqueue_style(
			'swco-landing',
			SWCO_URL . 'assets/css/landing.css',
			array(),
			SWCO_VERSION
		);
	}

	/**
	 * Published product list for pickers (block + Elementor).
	 * Capped at 100 (latest) so giant catalogs stay fast.
	 *
	 * @return array[] Each: ['id' => int, 'name' => string].
	 */
	public static function product_options(): array {
		$list = array();
		if ( ! function_exists( 'wc_get_products' ) ) {
			return $list;
		}
		$products = wc_get_products(
			array(
				'limit'   => 100,
				'status'  => 'publish',
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);
		foreach ( $products as $product ) {
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			$list[] = array(
				'id'   => $product->get_id(),
				'name' => $product->get_name(),
			);
		}
		return $list;
	}

	/**
	 * Send the product list to the block editor (dropdown source).
	 */
	public static function localize_products(): void {
		$handle = 'swco-landing-editor-script';
		if ( ! function_exists( 'wp_script_is' ) || ! wp_script_is( $handle, 'registered' ) ) {
			return;
		}
		wp_localize_script(
			$handle,
			'swcoLandingData',
			array(
				'products'    => self::product_options(),
				'placeholder' => __( '— Select product —', 'swift-checkout-for-woocommerce' ),
			)
		);
	}
	/**
	 * Resolve a usable product (simple + purchasable + in stock).
	 *
	 * @param int $product_id Product ID.
	 * @return WC_Product|null
	 */
	public static function landing_product( int $product_id ) {
		if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product ) {
			return null;
		}
		if ( ! $product->is_type( 'simple' ) || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return null;
		}
		return $product;
	}

	/**
	 * Shortcode: [swift_landing id="123" button="Order Now"].
	 * Display (fields, layout, default button) follows the Landing settings.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( array $atts ): string {
		$atts    = shortcode_atts(
			array(
				'id'     => 0,
				'button' => '',
			),
			$atts,
			'swift_landing'
		);
		$product = self::landing_product( absint( $atts['id'] ) );
		if ( ! $product ) {
			return '';
		}

		$cfg    = SwCo_Settings::instance()->get_group( 'swco_landing' );
		$status = isset( $_GET['swco_landing'] ) ? sanitize_key( wp_unslash( $_GET['swco_landing'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flag.
		$button = is_string( $atts['button'] ) && '' !== trim( $atts['button'] )
			? sanitize_text_field( $atts['button'] )
			: (string) ( $cfg['button_text'] ?? '' );
		if ( '' === $button ) {
			$button = __( 'Order Now', 'swift-checkout-for-woocommerce' );
		}
		$layout     = ( $cfg['layout'] ?? 'stacked' ) === 'two-column' ? ' swco-landing-two-col' : '';
		$show_image = 'yes' === ( $cfg['show_image'] ?? 'yes' );
		$show_qty   = 'yes' === ( $cfg['show_qty'] ?? 'yes' );
		$show_email = 'yes' === ( $cfg['show_email'] ?? 'no' );
		$show_note  = 'yes' === ( $cfg['show_note'] ?? 'no' );

		ob_start();
		?>
		<div class="swco-landing<?php echo esc_attr( $layout ); ?>" data-product-id="<?php echo esc_attr( (string) $product->get_id() ); ?>">
			<?php if ( 'success' === $status ) : ?>
				<p class="swco-landing-success"><?php echo esc_html__( 'Thanks! Your order has been received. We will call you to confirm.', 'swift-checkout-for-woocommerce' ); ?></p>
			<?php else : ?>
				<div class="swco-landing-product">
					<?php if ( $show_image ) : ?>
						<?php echo $product->get_image( 'woocommerce_thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce escapes product images. ?>
					<?php endif; ?>
					<div class="swco-landing-info">
						<h3 class="swco-landing-name"><?php echo esc_html( $product->get_name() ); ?></h3>
						<p class="swco-landing-price" data-unit-price="<?php echo esc_attr( (string) $product->get_price() ); ?>"><?php echo wp_kses_post( $product->get_price_html() ); ?></p>
						<p class="swco-landing-total-wrap"><?php echo esc_html__( 'Total:', 'swift-checkout-for-woocommerce' ); ?> <span class="swco-landing-total"><?php echo wp_kses_post( $product->get_price_html() ); ?></span></p>
					</div>
				</div>
				<?php if ( 'error' === $status ) : ?>
					<p class="swco-landing-error"><?php echo esc_html__( 'Please fill in your name, a valid mobile number, and address.', 'swift-checkout-for-woocommerce' ); ?></p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="swco-landing-form">
					<input type="hidden" name="action" value="swco_landing_order" />
					<input type="hidden" name="swco_product_id" value="<?php echo esc_attr( (string) $product->get_id() ); ?>" />
					<?php wp_nonce_field( self::NONCE ); ?>
					<p>
						<label for="swco-landing-name"><?php echo esc_html__( 'Your Name', 'swift-checkout-for-woocommerce' ); ?></label>
						<input type="text" id="swco-landing-name" name="swco_name" required maxlength="100" autocomplete="name" />
					</p>
					<p>
						<label for="swco-landing-mobile"><?php echo esc_html__( 'Mobile Number', 'swift-checkout-for-woocommerce' ); ?></label>
						<input type="tel" id="swco-landing-mobile" name="swco_mobile" required minlength="6" maxlength="20" autocomplete="tel" />
					</p>
					<p>
						<label for="swco-landing-address"><?php echo esc_html__( 'Address', 'swift-checkout-for-woocommerce' ); ?></label>
						<textarea id="swco-landing-address" name="swco_address" required maxlength="500" rows="3"></textarea>
					</p>
					<?php if ( $show_email ) : ?>
						<p>
							<label for="swco-landing-email"><?php echo esc_html__( 'Email (optional)', 'swift-checkout-for-woocommerce' ); ?></label>
							<input type="email" id="swco-landing-email" name="swco_email" maxlength="100" autocomplete="email" />
						</p>
					<?php endif; ?>
					<?php if ( $show_qty ) : ?>
						<p>
							<label for="swco-landing-qty"><?php echo esc_html__( 'Quantity', 'swift-checkout-for-woocommerce' ); ?></label>
							<input type="number" id="swco-landing-qty" name="swco_qty" value="1" min="1" max="99" />
						</p>
					<?php endif; ?>
					<?php if ( $show_note ) : ?>
						<p>
							<label for="swco-landing-note"><?php echo esc_html__( 'Order Note (optional)', 'swift-checkout-for-woocommerce' ); ?></label>
							<textarea id="swco-landing-note" name="swco_note" maxlength="500" rows="2"></textarea>
						</p>
					<?php endif; ?>
					<p class="swco-hp" aria-hidden="true">
						<label><?php echo esc_html__( 'Leave this empty', 'swift-checkout-for-woocommerce' ); ?>
							<input type="text" name="swco_company" value="" tabindex="-1" autocomplete="off" />
						</label>
					</p>
					<p>
						<button type="submit" class="button swco-landing-btn"><?php echo esc_html( $button ); ?></button>
					</p>
				</form>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Handle the form POST (admin-post.php). Redirects back with a status.
	 */
	public static function handle_submit(): void {
		$result = self::process( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified inside process().
		$back   = ! empty( $_POST['_wp_http_referer'] ) ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) ) : home_url( '/' );
		$back   = '' !== $back ? $back : home_url( '/' );
		wp_safe_redirect( add_query_arg( 'swco_landing', $result, $back ) );
		exit;
	}

	/**
	 * Validate + create a COD order. Pure logic, directly testable.
	 *
	 * @param array $data Raw POST data.
	 * @return string success|error
	 */
	public static function process( array $data ): string {
		if ( ! isset( $data['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $data['_wpnonce'] ) ), self::NONCE ) ) {
			return 'error';
		}
		if ( '' !== ( $data['swco_company'] ?? '' ) ) {
			return 'error'; // Honeypot caught a bot.
		}
		$product = self::landing_product( isset( $data['swco_product_id'] ) ? absint( $data['swco_product_id'] ) : 0 );
		if ( ! $product ) {
			return 'error';
		}
		$name    = isset( $data['swco_name'] ) && is_string( $data['swco_name'] ) ? sanitize_text_field( wp_unslash( $data['swco_name'] ) ) : '';
		$mobile  = isset( $data['swco_mobile'] ) && is_string( $data['swco_mobile'] ) ? sanitize_text_field( wp_unslash( $data['swco_mobile'] ) ) : '';
		$address = isset( $data['swco_address'] ) && is_string( $data['swco_address'] ) ? sanitize_textarea_field( wp_unslash( $data['swco_address'] ) ) : '';
		$email_raw = isset( $data['swco_email'] ) && is_string( $data['swco_email'] ) ? wp_unslash( $data['swco_email'] ) : '';
		$email     = '' !== $email_raw ? sanitize_email( $email_raw ) : '';
		$note      = isset( $data['swco_note'] ) && is_string( $data['swco_note'] ) ? sanitize_textarea_field( wp_unslash( $data['swco_note'] ) ) : '';
		$qty       = isset( $data['swco_qty'] ) ? absint( $data['swco_qty'] ) : 1;
		if ( '' === $name || strlen( preg_replace( '/\D/', '', $mobile ) ) < 6 || '' === $address || $qty < 1 || $qty > 99 ) {
			return 'error';
		}
		if ( '' !== trim( $email_raw ) && '' === $email ) {
			return 'error'; // Typed but invalid.
		}

		$order = function_exists( 'wc_create_order' ) ? wc_create_order() : null;
		if ( ! $order instanceof WC_Order ) {
			return 'error';
		}
		$order->add_product( $product, $qty );
		$order->set_address(
			array(
				'first_name' => $name,
				'phone'      => $mobile,
				'address_1'  => $address,
			),
			'billing'
		);
		$order->set_address(
			array(
				'first_name' => $name,
				'phone'      => $mobile,
				'address_1'  => $address,
			),
			'shipping'
		);
		$order->set_payment_method( 'cod' );
		if ( '' !== $email ) {
			$order->set_billing_email( $email );
		}
		$customer_note = __( 'Landing page order (Swift Checkout).', 'swift-checkout-for-woocommerce' );
		if ( '' !== $note ) {
			$customer_note .= ' ' . $note;
		}
		$order->set_customer_note( $customer_note );
		$order->calculate_totals();
		$order->update_status( 'processing', __( 'Landing page COD order.', 'swift-checkout-for-woocommerce' ) );

		if ( class_exists( 'SwCo_Accounts' ) ) {
			SwCo_Accounts::maybe_create_account( $order->get_id(), array(), $order );
		}

		return 'success';
	}
}
