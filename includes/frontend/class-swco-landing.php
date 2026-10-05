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
		add_action( 'init', array( __CLASS__, 'register_pattern' ) );
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
	 * One-click sales page: "Swift Sales Page" block pattern with hero,
	 * benefits, the order form, and trust points. Duplicate per product,
	 * then set the product in the Swift Landing Order block.
	 */
	public static function register_pattern(): void {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}
		register_block_pattern(
			'swco/sales-page',
			array(
				'title'       => __( 'Swift Sales Page', 'swift-checkout-for-woocommerce' ),
				'description' => __( 'Single-product sales page: hero, benefits, order form, trust points. One page, order completes here.', 'swift-checkout-for-woocommerce' ),
				'categories'  => array( 'pages' ),
				'content'     => '<!-- wp:heading {"textAlign":"center","level":1} --><h1 class="wp-block-heading has-text-align-center">' . esc_html__( 'Your Product Name — Limited Offer', 'swift-checkout-for-woocommerce' ) . '</h1><!-- /wp:heading -->'
					. '<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">' . esc_html__( 'One line that makes them want it. Cash on Delivery available.', 'swift-checkout-for-woocommerce' ) . '</p><!-- /wp:paragraph -->'
					. '<!-- wp:swco/landing {"productId":0,"buttonText":"Order Now"} /-->'
					. '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">' . esc_html__( 'Why you will love it', 'swift-checkout-for-woocommerce' ) . '</h2><!-- /wp:heading -->'
					. '<!-- wp:list --><ul><!-- wp:list-item --><li>' . esc_html__( 'Benefit one — what problem does it solve?', 'swift-checkout-for-woocommerce' ) . '</li><!-- /wp:list-item --><!-- wp:list-item --><li>' . esc_html__( 'Benefit two — what makes it different?', 'swift-checkout-for-woocommerce' ) . '</li><!-- /wp:list-item --><!-- wp:list-item --><li>' . esc_html__( 'Benefit three — guarantee or delivery promise.', 'swift-checkout-for-woocommerce' ) . '</li><!-- /wp:list-item --></ul><!-- /wp:list -->'
					. '<!-- wp:paragraph --><p><strong>' . esc_html__( 'Order above — it takes less than a minute. We call to confirm every order.', 'swift-checkout-for-woocommerce' ) . '</strong></p><!-- /wp:paragraph -->',
			)
		);
		register_block_pattern(
			'swco/bd-long-sales',
			array(
				'title'       => __( 'Swift BD Long Sales Page', 'swift-checkout-for-woocommerce' ),
				'description' => __( 'Long-form BD campaign page: offer bar, hero, problems, features, specs, reviews, FAQ, order form.', 'swift-checkout-for-woocommerce' ),
				'categories'  => array( 'pages' ),
				'content'     => self::bd_long_sales_content(),
			)
		);
	}

	/**
	 * Long-form BD sales content with placeholder copy (store owners replace
	 * with their own product text and images). Same section flow as popular
	 * BD campaign pages: offer bar, hero, problems, solution, features,
	 * specs, trust, reviews, FAQ, final order form.
	 *
	 * @return string Block markup.
	 */
	private static function bd_long_sales_content(): string {
		$c  = '<!-- wp:paragraph {"align":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"700"}}} --><p class="has-text-align-center"><strong>' . esc_html__( 'SPECIAL OFFER — Cash on Delivery available', 'swift-checkout-for-woocommerce' ) . '</strong></p><!-- /wp:paragraph -->';
		$c .= '<!-- wp:heading {"textAlign":"center","level":1} --><h1 class="wp-block-heading has-text-align-center">' . esc_html__( 'Your Product Headline — Why They Need It', 'swift-checkout-for-woocommerce' ) . '</h1><!-- /wp:heading -->';
		$c .= '<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">' . esc_html__( 'Two lines: what it is, who it is for, and the main promise.', 'swift-checkout-for-woocommerce' ) . '</p><!-- /wp:paragraph -->';
		$c .= '<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center"><strong>' . esc_html__( 'Regular price CUT — campaign price + delivery note here.', 'swift-checkout-for-woocommerce' ) . '</strong></p><!-- /wp:paragraph -->';
		$c .= '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#swco-order">' . esc_html__( 'Order Now', 'swift-checkout-for-woocommerce' ) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
		$c .= '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html__( 'Is this problem yours too?', 'swift-checkout-for-woocommerce' ) . '</h2><!-- /wp:heading -->';
		$c .= '<!-- wp:list --><ul><!-- wp:list-item --><li>' . esc_html__( 'Problem one your buyers feel daily.', 'swift-checkout-for-woocommerce' ) . '</li><!-- /wp:list-item --><!-- wp:list-item --><li>' . esc_html__( 'Problem two that wastes their time or money.', 'swift-checkout-for-woocommerce' ) . '</li><!-- /wp:list-item --><!-- wp:list-item --><li>' . esc_html__( 'Problem three your product removes.', 'swift-checkout-for-woocommerce' ) . '</li><!-- /wp:list-item --></ul><!-- /wp:list -->';
		$c .= '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html__( 'The simple solution', 'swift-checkout-for-woocommerce' ) . '</h2><!-- /wp:heading -->';
		$c .= '<!-- wp:paragraph --><p>' . esc_html__( 'Short paragraph: how your product solves the problems above, in plain words.', 'swift-checkout-for-woocommerce' ) . '</p><!-- /wp:paragraph -->';
		$c .= '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html__( 'Why buy this one?', 'swift-checkout-for-woocommerce' ) . '</h2><!-- /wp:heading -->';
		$c .= '<!-- wp:list --><ul><!-- wp:list-item --><li>' . esc_html__( 'Feature one + one-line benefit.', 'swift-checkout-for-woocommerce' ) . '</li><!-- /wp:list-item --><!-- wp:list-item --><li>' . esc_html__( 'Feature two + one-line benefit.', 'swift-checkout-for-woocommerce' ) . '</li><!-- /wp:list-item --><!-- wp:list-item --><li>' . esc_html__( 'Feature three + one-line benefit.', 'swift-checkout-for-woocommerce' ) . '</li><!-- /wp:list-item --></ul><!-- /wp:list -->';
		$c .= '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html__( 'Specifications', 'swift-checkout-for-woocommerce' ) . '</h2><!-- /wp:heading -->';
		$c .= '<!-- wp:paragraph --><p>' . esc_html__( 'Size / material / battery / warranty — one line each, keep it scannable.', 'swift-checkout-for-woocommerce' ) . '</p><!-- /wp:paragraph -->';
		$c .= '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html__( 'Why buy from us?', 'swift-checkout-for-woocommerce' ) . '</h2><!-- /wp:heading -->';
		$c .= '<!-- wp:list --><ul><!-- wp:list-item --><li>' . esc_html__( 'Cash on Delivery — pay after checking the product.', 'swift-checkout-for-woocommerce' ) . '</li><!-- /wp:list-item --><!-- wp:list-item --><li>' . esc_html__( 'Fast delivery all over Bangladesh.', 'swift-checkout-for-woocommerce' ) . '</li><!-- /wp:list-item --><!-- wp:list-item --><li>' . esc_html__( 'Easy support on phone and WhatsApp.', 'swift-checkout-for-woocommerce' ) . '</li><!-- /wp:list-item --></ul><!-- /wp:list -->';
		$c .= '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html__( 'Customer reviews', 'swift-checkout-for-woocommerce' ) . '</h2><!-- /wp:heading -->';
		$c .= '<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>' . esc_html__( 'Replace with a real review from your first buyer.', 'swift-checkout-for-woocommerce' ) . '</p><!-- /wp:paragraph --><cite>' . esc_html__( 'Buyer name, Dhaka', 'swift-checkout-for-woocommerce' ) . '</cite></blockquote><!-- /wp:quote -->';
		$c .= '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html__( 'Common questions', 'swift-checkout-for-woocommerce' ) . '</h2><!-- /wp:heading -->';
		$c .= '<!-- wp:html --><details><summary>' . esc_html__( 'Do you deliver outside Dhaka?', 'swift-checkout-for-woocommerce' ) . '</summary><p>' . esc_html__( 'Yes — we deliver to all districts of Bangladesh.', 'swift-checkout-for-woocommerce' ) . '</p></details><details><summary>' . esc_html__( 'Is Cash on Delivery available?', 'swift-checkout-for-woocommerce' ) . '</summary><p>' . esc_html__( 'Yes — check the product first, then pay.', 'swift-checkout-for-woocommerce' ) . '</p></details><!-- /wp:html -->';
		$c .= '<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center" id="swco-order">' . esc_html__( 'Fill the form below to order', 'swift-checkout-for-woocommerce' ) . '</h2><!-- /wp:heading -->';
		$c .= '<!-- wp:swco/landing {"productId":0,"buttonText":"Order Now"} /-->';
		return $c;
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
	 * Landing form field config: type + visibility + custom label +
	 * required + order, sorted for display. Visibility still follows the
	 * Landing tab show_* toggles; name/mobile/address always show.
	 *
	 * @return array Field key => config array.
	 */
	public static function form_fields(): array {
		$cfg  = SwCo_Settings::instance()->get_group( 'swco_landing' );
		$rows = SwCo_Settings::instance()->get_group( 'swco_landing_fields' );
		$defs = array(
			'swco_name'    => array( 'type' => 'text', 'visible' => true ),
			'swco_mobile'  => array( 'type' => 'tel', 'visible' => true ),
			'swco_address' => array( 'type' => 'textarea', 'visible' => true ),
			'swco_email'   => array( 'type' => 'email', 'visible' => 'yes' === ( $cfg['show_email'] ?? 'no' ) ),
			'swco_qty'     => array( 'type' => 'number', 'visible' => 'yes' === ( $cfg['show_qty'] ?? 'yes' ) ),
			'swco_note'    => array( 'type' => 'textarea', 'visible' => 'yes' === ( $cfg['show_note'] ?? 'no' ) ),
		);
		$out = array();
		$fallback = array(
			'swco_name'    => __( 'Your Name', 'swift-checkout-for-woocommerce' ),
			'swco_mobile'  => __( 'Mobile Number', 'swift-checkout-for-woocommerce' ),
			'swco_address' => __( 'Address', 'swift-checkout-for-woocommerce' ),
			'swco_email'   => __( 'Email (optional)', 'swift-checkout-for-woocommerce' ),
			'swco_qty'     => __( 'Quantity', 'swift-checkout-for-woocommerce' ),
			'swco_note'    => __( 'Order Note (optional)', 'swift-checkout-for-woocommerce' ),
		);
		foreach ( $defs as $key => $def ) {
			if ( empty( $def['visible'] ) ) {
				continue;
			}
			$row = isset( $rows[ $key ] ) && is_array( $rows[ $key ] ) ? $rows[ $key ] : array();
			$out[ $key ] = array(
				'type'     => $def['type'],
				'label'    => isset( $row['label'] ) && is_string( $row['label'] ) && '' !== $row['label'] ? $row['label'] : ( $fallback[ $key ] ?? $key ),
				'required' => ! empty( $row['required'] ),
				'order'    => isset( $row['order'] ) ? absint( $row['order'] ) : 10,
			);
		}
		uasort(
			$out,
			function ( array $a, array $b ): int {
				return $a['order'] <=> $b['order'];
			}
		);
		return $out;
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
		$form_fields = self::form_fields();

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
					<?php foreach ( $form_fields as $key => $field ) : ?>
						<?php self::render_form_field( $key, $field ); ?>
					<?php endforeach; ?>
					<p class="swco-hp" aria-hidden="true">
						<label><?php echo esc_html__( 'Leave this empty', 'swift-checkout-for-woocommerce' ); ?>
							<input type="text" name="swco_company" value="" tabindex="-1" autocomplete="off" />
						</label>
					</p>
					<p>
						<button type="submit" class="button swco-landing-btn"><?php echo esc_html( $button ); ?></button>
					</p>
					<p class="swco-landing-assure"><?php echo esc_html__( 'Cash on Delivery available — no advance payment needed.', 'swift-checkout-for-woocommerce' ); ?></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Print one landing form row (ids stay fixed for landing.js).
	 *
	 * @param string $key   Field key (swco_name, ...).
	 * @param array  $field Config from form_fields().
	 */
	private static function render_form_field( string $key, array $field ): void {
		$ids = array(
			'swco_name'    => 'swco-landing-name',
			'swco_mobile'  => 'swco-landing-mobile',
			'swco_address' => 'swco-landing-address',
			'swco_email'   => 'swco-landing-email',
			'swco_qty'     => 'swco-landing-qty',
			'swco_note'    => 'swco-landing-note',
		);
		if ( ! isset( $ids[ $key ] ) ) {
			return;
		}
		$id       = $ids[ $key ];
		$label    = isset( $field['label'] ) && is_string( $field['label'] ) ? $field['label'] : $key;
		$required = ! empty( $field['required'] ) ? ' required' : '';
		?>
		<p>
			<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<?php if ( 'swco_address' === $key ) : ?>
				<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $key ); ?>" maxlength="500" rows="3"<?php echo esc_attr( $required ); ?>></textarea>
			<?php elseif ( 'swco_note' === $key ) : ?>
				<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $key ); ?>" maxlength="500" rows="2"<?php echo esc_attr( $required ); ?>></textarea>
			<?php elseif ( 'swco_qty' === $key ) : ?>
				<input type="number" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $key ); ?>" value="1" min="1" max="99" />
			<?php elseif ( 'swco_mobile' === $key ) : ?>
				<input type="tel" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $key ); ?>" minlength="6" maxlength="20" autocomplete="tel"<?php echo esc_attr( $required ); ?> />
			<?php elseif ( 'swco_email' === $key ) : ?>
				<input type="email" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $key ); ?>" maxlength="100" autocomplete="email"<?php echo esc_attr( $required ); ?> />
			<?php else : ?>
				<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $key ); ?>" maxlength="100" autocomplete="name"<?php echo esc_attr( $required ); ?> />
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Handle the form POST (admin-post.php). Redirects back with a status.
	 */
	public static function handle_submit(): void {
		$result = self::process( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified + values sanitized inside process().
		$back   = ! empty( $_POST['_wp_http_referer'] ) ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) ) : home_url( '/' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- same submission already verified in process().
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
		$fields    = self::form_fields();
		$qty_shown = isset( $fields['swco_qty'] );
		$qty       = ( $qty_shown && isset( $data['swco_qty'] ) ) ? absint( $data['swco_qty'] ) : 1;
		$mobile_digits = strlen( preg_replace( '/\D/', '', $mobile ) );
		if ( $qty < 1 || $qty > 99 ) {
			return 'error';
		}
		foreach ( array( 'swco_name' => $name, 'swco_mobile' => $mobile, 'swco_address' => $address ) as $req_key => $value ) {
			if ( isset( $fields[ $req_key ] ) && ! empty( $fields[ $req_key ]['required'] ) && '' === $value ) {
				return 'error';
			}
		}
		if ( '' !== $mobile && $mobile_digits < 6 ) {
			return 'error'; // Typed but too short.
		}
		if ( isset( $fields['swco_mobile'] ) && ! empty( $fields['swco_mobile']['required'] ) && $mobile_digits < 6 ) {
			return 'error';
		}
		if ( isset( $fields['swco_email'] ) && ! empty( $fields['swco_email']['required'] ) && '' === $email ) {
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
