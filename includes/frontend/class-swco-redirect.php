<?php
/**
 * Skip-cart redirect + Buy Now buttons + per-product overrides.
 *
 * Spec: plan §5.2 row 1, settings §6.1 swco_general, product meta
 * `_swco_skip_cart` / `_swco_quick_buy` (yes/no/global).
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Redirects Add to Cart to checkout and renders Buy Now buttons.
 */
final class SwCo_Redirect {

	/**
	 * Register all hooks.
	 */
	public static function init(): void {
		// Send add-to-cart straight to the target (checkout by default).
		add_filter( 'woocommerce_add_to_cart_redirect', array( __CLASS__, 'maybe_redirect_to_checkout' ), 10, 1 );

		// Optionally replace the cart page itself (template_redirect).
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_cart_page' ) );

		// Buy Now shortcode: [swift_buy_now id="123"].
		add_shortcode( 'swift_buy_now', array( __CLASS__, 'buy_now_shortcode' ) );

		// Buy Now button on the single product page (after Add to Cart).
		add_action( 'woocommerce_after_add_to_cart_button', array( __CLASS__, 'render_single_buy_now' ) );

		// Label the Buy Now buttons + (simple products only) the Add to Cart text.
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( __CLASS__, 'filter_single_atc_text' ) );
		add_filter( 'woocommerce_product_add_to_cart_text', array( __CLASS__, 'filter_loop_atc_text' ) );

		// Per-product overrides tab.
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_product_tab' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product_meta' ) );
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
	 * Should this add-to-cart skip the cart?
	 *
	 * Per-product meta wins: 'yes' forces skip, 'no' forces cart,
	 * anything else falls back to the global setting.
	 *
	 * @param int $product_id Product ID (0 = unknown).
	 * @return bool
	 */
	public static function should_skip( int $product_id = 0 ): bool {
		if ( ! self::enabled() ) {
			return false;
		}
		if ( $product_id > 0 ) {
			$override = get_post_meta( $product_id, '_swco_skip_cart', true );
			if ( 'yes' === $override ) {
				return true;
			}
			if ( 'no' === $override ) {
				return false;
			}
		}
		return 'yes' === SwCo_Settings::instance()->get_value( 'swco_general', 'skip_cart', 'yes' );
	}

	/**
	 * Target URL for the redirect (checkout / cart / custom URL).
	 *
	 * @return string
	 */
	public static function get_target_url(): string {
		$settings = SwCo_Settings::instance();
		$to       = $settings->get_value( 'swco_general', 'redirect_to', 'checkout' );

		if ( 'cart' === $to && function_exists( 'wc_get_cart_url' ) ) {
			return wc_get_cart_url();
		}
		if ( 'custom_url' === $to ) {
			$custom = esc_url_raw( $settings->get_value( 'swco_general', 'custom_url', '' ) );
			if ( '' !== $custom ) {
				return $custom;
			}
		}
		if ( function_exists( 'wc_get_checkout_url' ) ) {
			return wc_get_checkout_url();
		}
		return home_url( '/' );
	}

	/**
	 * Filter: redirect after a successful add-to-cart.
	 *
	 * @param string $url Default URL (usually cart).
	 * @return string
	 */
	public static function maybe_redirect_to_checkout( string $url ): string {
		$raw        = $_REQUEST['add-to-cart'] ?? 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only add-to-cart detection; absint() applied below.
		$product_id = is_array( $raw ) ? 0 : absint( wp_unslash( $raw ) );
		if ( self::should_skip( $product_id ) ) {
			return self::get_target_url();
		}
		return $url;
	}

	/**
	 * Redirect the cart page itself when "Replace cart URL" is set.
	 * Runs on template_redirect; never in admin or AJAX.
	 */
	public static function maybe_redirect_cart_page(): void {
		if ( is_admin() || wp_doing_ajax() || ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return;
		}
		if ( ! self::enabled() ) {
			return;
		}
		$mode = SwCo_Settings::instance()->get_value( 'swco_general', 'replace_cart_url', 'no' );
		if ( 'checkout' === $mode && function_exists( 'wc_get_checkout_url' ) ) {
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}
		if ( 'custom' === $mode ) {
			$custom = esc_url_raw( SwCo_Settings::instance()->get_value( 'swco_general', 'custom_url', '' ) );
			if ( '' !== $custom ) {
				wp_safe_redirect( $custom );
				exit;
			}
		}
	}

	/**
	 * Buy Now button label (global setting, HTML stripped on save).
	 *
	 * @return string
	 */
	public static function buy_now_label(): string {
		$label = SwCo_Settings::instance()->get_value( 'swco_general', 'atc_text', __( 'Buy Now', 'swift-checkout-for-woocommerce' ) );
		return '' !== $label ? $label : __( 'Buy Now', 'swift-checkout-for-woocommerce' );
	}

	/**
	 * Button markup for a product. Adds ?add-to-cart=ID so the redirect
	 * filter above sends the shopper to the target page.
	 *
	 * @param int $product_id Product ID.
	 * @return string HTML (empty when the product cannot be quick-bought).
	 */
	public static function get_buy_now_html( int $product_id ): string {
		$product_id = absint( $product_id );
		if ( $product_id <= 0 || ! self::enabled() ) {
			return '';
		}
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( $product ) {
			if ( ! $product->is_purchasable() || ! $product->is_in_stock() || ! $product->is_type( 'simple' ) ) {
				return '';
			}
		}
		$url = add_query_arg( 'add-to-cart', $product_id );

		return sprintf(
			'<a href="%1$s" class="button swco-buy-now" data-swco-buy-now="%2$d">%3$s</a>',
			esc_url( $url ),
			$product_id,
			esc_html( self::buy_now_label() )
		);
	}

	/**
	 * Shortcode: [swift_buy_now id="123"].
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function buy_now_shortcode( array $atts ): string {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'swift_buy_now' );
		return self::get_buy_now_html( absint( $atts['id'] ) );
	}

	/**
	 * Print the Buy Now button on single product pages.
	 * Per-product 'no' hides it; everything else follows the global default
	 * (simple, purchasable, in-stock products only).
	 */
	public static function render_single_buy_now(): void {
		global $product;
		if ( ! ( $product instanceof WC_Product ) ) {
			return;
		}
		if ( ! self::single_button_eligible( $product ) ) {
			return;
		}
		echo self::get_buy_now_html( $product->get_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped.
	}

	/**
	 * May our dedicated Buy Now button print for this product?
	 *
	 * @param WC_Product $product Product in context.
	 * @return bool
	 */
	private static function single_button_eligible( WC_Product $product ): bool {
		if ( ! $product->is_type( 'simple' ) ) {
			return false;
		}
		return 'no' !== get_post_meta( $product->get_id(), '_swco_quick_buy', true );
	}

	/**
	 * Relabel single-product Add to Cart only when our dedicated button
	 * is hidden (per-product 'no'), so shoppers never see two identical
	 * "Buy Now" buttons side by side. Non-simple types never change.
	 *
	 * @param string $text Default button text.
	 * @return string
	 */
	public static function filter_single_atc_text( string $text ): string {
		global $product;
		if ( ! self::enabled() ) {
			return $text;
		}
		if ( $product instanceof WC_Product && $product->is_type( 'simple' ) && ! self::single_button_eligible( $product ) ) {
			return self::buy_now_label();
		}
		return $text;
	}

	/**
	 * Loop/shop pages have no dedicated button, so simple products are
	 * always relabeled there (skip-cart redirect still applies).
	 *
	 * @param string $text Default button text.
	 * @return string
	 */
	public static function filter_loop_atc_text( string $text ): string {
		global $product;
		if ( ! self::enabled() ) {
			return $text;
		}
		if ( $product instanceof WC_Product && $product->is_type( 'simple' ) ) {
			return self::buy_now_label();
		}
		return $text;
	}

	/**
	 * Add the "Swift Checkout" tab to the product data panel.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public static function add_product_tab( array $tabs ): array {
		$tabs['swco'] = array(
			'label'    => __( 'Swift Checkout', 'swift-checkout-for-woocommerce' ),
			'target'   => 'swco_product_data',
			'class'    => array(),
			'priority' => 80,
		);
		return $tabs;
	}

	/**
	 * Render the per-product override fields.
	 */
	public static function render_product_tab(): void {
		global $post;
		$skip      = get_post_meta( $post->ID, '_swco_skip_cart', true );
		$quick_buy = get_post_meta( $post->ID, '_swco_quick_buy', true );
		?>
		<div id="swco_product_data" class="panel woocommerce_options_panel">
			<?php
			woocommerce_wp_select(
				array(
					'id'      => '_swco_skip_cart',
					'label'   => __( 'Skip cart', 'swift-checkout-for-woocommerce' ),
					'options' => array(
						'global' => __( 'Use global setting', 'swift-checkout-for-woocommerce' ),
						'yes'    => __( 'Yes — go straight to checkout', 'swift-checkout-for-woocommerce' ),
						'no'     => __( 'No — keep cart for this product', 'swift-checkout-for-woocommerce' ),
					),
					'value'   => in_array( $skip, array( 'yes', 'no' ), true ) ? $skip : 'global',
				)
			);
			woocommerce_wp_select(
				array(
					'id'      => '_swco_quick_buy',
					'label'   => __( 'Buy Now button', 'swift-checkout-for-woocommerce' ),
					'options' => array(
						'global' => __( 'Use global setting', 'swift-checkout-for-woocommerce' ),
						'yes'    => __( 'Show', 'swift-checkout-for-woocommerce' ),
						'no'     => __( 'Hide', 'swift-checkout-for-woocommerce' ),
					),
					'value'   => in_array( $quick_buy, array( 'yes', 'no' ), true ) ? $quick_buy : 'global',
				)
			);
			?>
		</div>
		<?php
	}

	/**
	 * Save the per-product overrides (whitelist only).
	 *
	 * @param int $post_id Product ID.
	 */
	public static function save_product_meta( int $post_id ): void {
		$allowed = array( 'yes', 'no' );
		foreach ( array( '_swco_skip_cart', '_swco_quick_buy' ) as $key ) {
			if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by WooCommerce on the product screen.
				continue;
			}
			$value = sanitize_key( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by WooCommerce on the product screen; value sanitized here.
			if ( in_array( $value, $allowed, true ) ) {
				update_post_meta( $post_id, $key, $value );
			} else {
				delete_post_meta( $post_id, $key );
			}
		}
	}
}
