<?php
/**
 * Admin settings: a "Swift Checkout" tab inside WooCommerce → Settings.
 *
 * Four tabs, no more: General | Checkout | Fields | Booster (v1.1 teaser,
 * no inputs). Saves through SwCo_Settings::sanitize() — one code path.
 *
 * Spec: plan §6.
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the settings tab, renders the form, handles save.
 */
final class SwCo_Admin_Menu {

	/**
	 * Nonce action for the settings form.
	 */
	const NONCE = 'swco_save_settings';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'add_tab' ), 50 );
		add_action( 'woocommerce_settings_swco', array( __CLASS__, 'render' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SWCO_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Row links on Plugins → Installed Plugins (Settings first).
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public static function action_links( array $links ): array {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			array_unshift(
				$links,
				sprintf(
					'<a href="%1$s">%2$s</a>',
					esc_url( admin_url( 'admin.php?page=wc-settings&tab=swco' ) ),
					esc_html__( 'Settings', 'swift-checkout-for-woocommerce' )
				)
			);
		}
		return $links;
	}

	/**
	 * Add our tab to WooCommerce → Settings.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public static function add_tab( array $tabs ): array {
		$tabs['swco'] = __( 'Swift Checkout', 'swift-checkout-for-woocommerce' );
		return $tabs;
	}

	/**
	 * Tab slugs and labels.
	 *
	 * @return array Slug => label.
	 */
	public static function tabs(): array {
		return array(
			'general'   => __( 'General', 'swift-checkout-for-woocommerce' ),
			'checkout'  => __( 'Checkout', 'swift-checkout-for-woocommerce' ),
			'fields'    => __( 'Fields', 'swift-checkout-for-woocommerce' ),
			'booster'   => __( 'Booster', 'swift-checkout-for-woocommerce' ),
			'optin'     => __( 'Opt-in', 'swift-checkout-for-woocommerce' ),
			'landing'   => __( 'Landing', 'swift-checkout-for-woocommerce' ),
			'templates' => __( 'Templates', 'swift-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Current sub-tab (whitelisted).
	 *
	 * @return string
	 */
	public static function current_tab(): string {
		$tab  = isset( $_GET['swco_tab'] ) ? sanitize_key( wp_unslash( $_GET['swco_tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab navigation only.
		$tabs = self::tabs();
		return isset( $tabs[ $tab ] ) ? $tab : 'general';
	}

	/**
	 * Load CSS/JS only on our tab.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function assets( string $hook ): void {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}
		if ( ( isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '' ) !== 'swco' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- page detection only.
			return;
		}
		wp_enqueue_style( 'swco-admin', SWCO_URL . 'assets/css/admin.css', array(), SWCO_VERSION );
		wp_enqueue_script( 'swco-admin', SWCO_URL . 'assets/js/admin.js', array(), SWCO_VERSION, true );
		wp_localize_script(
			'swco-admin',
			'swco_admin_params',
			array(
				'copied' => __( 'Copied!', 'swift-checkout-for-woocommerce' ),
				'copy'   => __( 'Copy', 'swift-checkout-for-woocommerce' ),
			)
		);
	}

	/**
	 * Render the tab: save first, then nav + form.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage checkout settings.', 'swift-checkout-for-woocommerce' ) );
		}

		if ( isset( $_POST['swco_save'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified inside save().
			self::save();
		}

		if ( isset( $_POST['swco_apply_template'] ) && isset( $_POST['swco_template'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
			check_admin_referer( self::NONCE );
			$slug = sanitize_key( wp_unslash( $_POST['swco_template'] ) );
			if ( SwCo_Templates::apply( $slug ) ) {
				add_settings_error( 'swco_messages', 'swco_template', __( 'Template applied. Previous settings backed up.', 'swift-checkout-for-woocommerce' ), 'success' );
			} else {
				add_settings_error( 'swco_messages', 'swco_template', __( 'Invalid template.', 'swift-checkout-for-woocommerce' ), 'error' );
			}
		}

		if ( isset( $_POST['swco_restore_template'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
			check_admin_referer( self::NONCE );
			if ( SwCo_Templates::restore() ) {
				add_settings_error( 'swco_messages', 'swco_template', __( 'Previous settings restored.', 'swift-checkout-for-woocommerce' ), 'success' );
			} else {
				add_settings_error( 'swco_messages', 'swco_template', __( 'Nothing to restore.', 'swift-checkout-for-woocommerce' ), 'error' );
			}
		}

		$tab      = self::current_tab();
		$settings = SwCo_Settings::instance()->get();
		?>
		<div class="swco-wrap">
			<h2 class="nav-tab-wrapper swco-nav">
				<?php foreach ( self::tabs() as $slug => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=swco&swco_tab=' . $slug ) ); ?>" class="nav-tab<?php echo $slug === $tab ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</h2>

			<?php settings_errors( 'swco_messages' ); ?>

			<?php if ( 'booster' === $tab ) : ?>
				<form method="post" action="">
					<?php wp_nonce_field( self::NONCE ); ?>
					<?php self::render_bump( $settings['swco_bump'] ); ?>
					<p class="submit">
						<button type="submit" name="swco_save" value="1" class="button button-primary"><?php echo esc_html__( 'Save changes', 'swift-checkout-for-woocommerce' ); ?></button>
					</p>
				</form>
			<?php elseif ( 'optin' === $tab ) : ?>
				<form method="post" action="">
					<?php wp_nonce_field( self::NONCE ); ?>
					<?php self::render_optin( $settings['swco_optin'] ); ?>
					<p class="submit">
						<button type="submit" name="swco_save" value="1" class="button button-primary"><?php echo esc_html__( 'Save changes', 'swift-checkout-for-woocommerce' ); ?></button>
					</p>
				</form>
			<?php elseif ( 'landing' === $tab ) : ?>
				<form method="post" action="">
					<?php wp_nonce_field( self::NONCE ); ?>
					<?php self::render_landing( $settings['swco_landing'], $settings['swco_landing_fields'] ); ?>
					<p class="submit">
						<button type="submit" name="swco_save" value="1" class="button button-primary"><?php echo esc_html__( 'Save changes', 'swift-checkout-for-woocommerce' ); ?></button>
					</p>
				</form>
			<?php elseif ( 'templates' === $tab ) : ?>
				<?php self::render_templates(); ?>
			<?php else : ?>
				<form method="post" action="">
					<?php wp_nonce_field( self::NONCE ); ?>
					<?php
					if ( 'general' === $tab ) {
						self::render_general( $settings['swco_general'] );
					} elseif ( 'checkout' === $tab ) {
						self::render_checkout( $settings['swco_checkout'], $settings['swco_coupon'], $settings['swco_maps'] );
					} elseif ( 'fields' === $tab ) {
						SwCo_Admin_Fields::render_table( $settings['swco_fields'] );
					}
					?>
					<p class="submit">
						<button type="submit" name="swco_save" value="1" class="button button-primary"><?php echo esc_html__( 'Save changes', 'swift-checkout-for-woocommerce' ); ?></button>
					</p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Validate nonce, sanitize through the single choke point, save.
	 */
	private static function save(): void {
		check_admin_referer( self::NONCE );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage checkout settings.', 'swift-checkout-for-woocommerce' ) );
		}
		$input = isset( $_POST['swco_settings'] ) && is_array( $_POST['swco_settings'] ) ? wp_unslash( $_POST['swco_settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by SwCo_Settings::sanitize().
		$clean = SwCo_Settings::instance()->sanitize( $input );
		update_option( SwCo_Settings::OPTION, $clean );
		add_settings_error( 'swco_messages', 'swco_saved', __( 'Settings saved.', 'swift-checkout-for-woocommerce' ), 'success' );
	}

	/**
	 * General tab fields.
	 *
	 * @param array $v Stored swco_general values.
	 */
	private static function render_general( array $v ): void {
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<?php self::row_checkbox( __( 'Enable Swift Checkout', 'swift-checkout-for-woocommerce' ), 'swco_general', 'enabled', $v['enabled'] ?? 'yes', __( 'Master switch. Off = everything behaves like default WooCommerce.', 'swift-checkout-for-woocommerce' ) ); ?>
				<?php self::row_checkbox( __( 'Skip cart globally', 'swift-checkout-for-woocommerce' ), 'swco_general', 'skip_cart', $v['skip_cart'] ?? 'yes', __( 'Add to Cart goes straight to checkout. Per-product override wins.', 'swift-checkout-for-woocommerce' ) ); ?>
				<?php self::row_checkbox( __( 'Auto-create account on order', 'swift-checkout-for-woocommerce' ), 'swco_general', 'auto_create_account', $v['auto_create_account'] ?? 'no', __( 'Guest orders create (or link) a customer account from the billing email. WooCommerce emails the login details.', 'swift-checkout-for-woocommerce' ) ); ?>
				<?php
				self::row_select(
					__( 'Redirect to', 'swift-checkout-for-woocommerce' ),
					'swco_general',
					'redirect_to',
					$v['redirect_to'] ?? 'checkout',
					array(
						'checkout'   => __( 'Checkout', 'swift-checkout-for-woocommerce' ),
						'cart'       => __( 'Cart', 'swift-checkout-for-woocommerce' ),
						'custom_url' => __( 'Custom URL', 'swift-checkout-for-woocommerce' ),
					),
					''
				);
				?>
				<?php self::row_text( __( 'Custom URL', 'swift-checkout-for-woocommerce' ), 'swco_general', 'custom_url', $v['custom_url'] ?? '', __( 'Used when Redirect to = Custom URL. Invalid URLs are rejected on save.', 'swift-checkout-for-woocommerce' ) ); ?>
				<?php
				self::row_select(
					__( 'Replace cart URL', 'swift-checkout-for-woocommerce' ),
					'swco_general',
					'replace_cart_url',
					$v['replace_cart_url'] ?? 'no',
					array(
						'no'       => __( 'No', 'swift-checkout-for-woocommerce' ),
						'checkout' => __( 'Redirect cart page to checkout', 'swift-checkout-for-woocommerce' ),
						'custom'   => __( 'Redirect cart page to Custom URL', 'swift-checkout-for-woocommerce' ),
					),
					''
				);
				?>
				<?php self::row_text( __( 'Buy Now button text', 'swift-checkout-for-woocommerce' ), 'swco_general', 'atc_text', $v['atc_text'] ?? '', '' ); ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Checkout tab fields + coupon section + shortcode box.
	 *
	 * @param array $c     Stored swco_checkout values.
	 * @param array $p     Stored swco_coupon values.
	 * @param array $maps  Stored swco_maps values.
	 */
	private static function render_checkout( array $c, array $p, array $maps ): void {
		?>
		<h3><?php echo esc_html__( 'Layout', 'swift-checkout-for-woocommerce' ); ?></h3>
		<table class="form-table" role="presentation">
			<tbody>
				<?php
				self::row_select(
					__( 'Layout style', 'swift-checkout-for-woocommerce' ),
					'swco_checkout',
					'layout',
					$c['layout'] ?? 'two-column',
					array(
						'one-column' => __( 'One column (mobile-first)', 'swift-checkout-for-woocommerce' ),
						'two-column' => __( 'Two columns (form + summary)', 'swift-checkout-for-woocommerce' ),
						'multi-step' => __( 'Multi-step (Details → Payment)', 'swift-checkout-for-woocommerce' ),
					),
					__( 'Live preview images arrive with the template design.', 'swift-checkout-for-woocommerce' )
				);
				?>
				<?php
				self::row_select(
					__( 'Skin', 'swift-checkout-for-woocommerce' ),
					'swco_checkout',
					'skin',
					$c['skin'] ?? 'default',
					array(
						'default' => __( 'Default (theme-friendly)', 'swift-checkout-for-woocommerce' ),
						'dhaka'   => __( 'Dhaka — teal BD style + trust badges', 'swift-checkout-for-woocommerce' ),
					),
					''
				);
				?>
				<?php self::row_text( __( 'Delivery note (Dhaka skin)', 'swift-checkout-for-woocommerce' ), 'swco_checkout', 'delivery_note', $c['delivery_note'] ?? '', __( 'E.g. Estimated delivery: Tomorrow — Dhaka. Empty = hidden.', 'swift-checkout-for-woocommerce' ) ); ?>
				<?php self::row_text( __( 'Reviews line (Dhaka skin)', 'swift-checkout-for-woocommerce' ), 'swco_checkout', 'reviews_text', $c['reviews_text'] ?? '', __( 'E.g. 10,000+ happy customers in Dhaka. Empty = hidden.', 'swift-checkout-for-woocommerce' ) ); ?>
				<?php self::row_text( __( 'Help line (Dhaka skin)', 'swift-checkout-for-woocommerce' ), 'swco_checkout', 'help_text', $c['help_text'] ?? '', __( 'E.g. Need help? Call 09613-XXXXXX (9AM–9PM). Empty = hidden.', 'swift-checkout-for-woocommerce' ) ); ?>
				<?php self::row_checkbox( __( 'Override global checkout', 'swift-checkout-for-woocommerce' ), 'swco_checkout', 'override_global', $c['override_global'] ?? 'yes', '' ); ?>
				<?php self::row_checkbox( __( 'Show cart in checkout', 'swift-checkout-for-woocommerce' ), 'swco_checkout', 'cart_in_checkout', $c['cart_in_checkout'] ?? 'yes', '' ); ?>
				<?php self::row_checkbox( __( 'AJAX quantity update', 'swift-checkout-for-woocommerce' ), 'swco_checkout', 'cart_ajax', $c['cart_ajax'] ?? 'yes', __( 'Qty +/- and remove without page reload.', 'swift-checkout-for-woocommerce' ) ); ?>
				<?php
				self::row_multiselect(
					__( 'Cart columns', 'swift-checkout-for-woocommerce' ),
					'swco_checkout',
					'cart_columns',
					$c['cart_columns'] ?? array(),
					array(
						'remove'    => __( 'Remove', 'swift-checkout-for-woocommerce' ),
						'thumbnail' => __( 'Thumbnail', 'swift-checkout-for-woocommerce' ),
						'name'      => __( 'Name', 'swift-checkout-for-woocommerce' ),
						'price'     => __( 'Price', 'swift-checkout-for-woocommerce' ),
						'qty'       => __( 'Quantity', 'swift-checkout-for-woocommerce' ),
					),
					__( 'Product name always shows — a cart row without a name would be broken.', 'swift-checkout-for-woocommerce' )
				);
				?>
				<?php self::row_checkbox( __( 'Hide order notes', 'swift-checkout-for-woocommerce' ), 'swco_checkout', 'hide_order_notes', $c['hide_order_notes'] ?? 'yes', '' ); ?>
				<?php
				self::row_select(
					__( 'Coupon form', 'swift-checkout-for-woocommerce' ),
					'swco_checkout',
					'hide_coupon',
					$c['hide_coupon'] ?? 'show',
					array(
						'show'   => __( 'Show', 'swift-checkout-for-woocommerce' ),
						'hide'   => __( 'Hide', 'swift-checkout-for-woocommerce' ),
						'toggle' => __( 'Toggle (collapsed)', 'swift-checkout-for-woocommerce' ),
					),
					''
				);
				?>
				<?php self::row_checkbox( __( 'Hide terms checkbox', 'swift-checkout-for-woocommerce' ), 'swco_checkout', 'hide_terms', $c['hide_terms'] ?? 'no', '' ); ?>
				<?php self::row_checkbox( __( 'Hide shipping address', 'swift-checkout-for-woocommerce' ), 'swco_checkout', 'hide_shipping', $c['hide_shipping'] ?? 'no', '' ); ?>
			</tbody>
		</table>

		<h3><?php echo esc_html__( 'Coupon', 'swift-checkout-for-woocommerce' ); ?></h3>
		<table class="form-table" role="presentation">
			<tbody>
				<?php self::row_text( __( 'Auto-apply coupon code', 'swift-checkout-for-woocommerce' ), 'swco_coupon', 'auto_coupon', $p['auto_coupon'] ?? '', __( 'Applied on every checkout. Saved in UPPERCASE.', 'swift-checkout-for-woocommerce' ) ); ?>
				<?php self::row_text( __( 'URL param name', 'swift-checkout-for-woocommerce' ), 'swco_coupon', 'url_param', $p['url_param'] ?? '', __( 'E.g. SAVE10 via ?swco_coupon=SAVE10', 'swift-checkout-for-woocommerce' ) ); ?>
				<?php self::row_text( __( 'Minimum subtotal', 'swift-checkout-for-woocommerce' ), 'swco_coupon', 'min_subtotal', (string) ( $p['min_subtotal'] ?? 0 ), __( 'Auto-coupon applies only above this cart subtotal. 0 = always.', 'swift-checkout-for-woocommerce' ) ); ?>
			</tbody>
		</table>

		<h3><?php echo esc_html__( 'Address Autocomplete', 'swift-checkout-for-woocommerce' ); ?></h3>
		<p class="description"><?php echo esc_html__( 'Paste your Google Places API key to suggest addresses on checkout. Shoppers need no key. Empty = feature off (no Google request). Tip: restrict the key to your domain in Google Cloud Console.', 'swift-checkout-for-woocommerce' ); ?></p>
		<table class="form-table" role="presentation">
			<tbody>
				<?php self::row_text( __( 'Google Places API key', 'swift-checkout-for-woocommerce' ), 'swco_maps', 'api_key', $maps['api_key'] ?? '', '' ); ?>
			</tbody>
		</table>

		<h3><?php echo esc_html__( 'Shortcode', 'swift-checkout-for-woocommerce' ); ?></h3>
		<p>
			<input type="text" readonly value="[swift_checkout]" class="regular-text code swco-shortcode-input" onclick="this.select();" />
			<button type="button" class="button swco-copy-btn" data-copy="[swift_checkout]"><?php echo esc_html__( 'Copy', 'swift-checkout-for-woocommerce' ); ?></button>
		</p>
		<p class="description"><?php echo esc_html__( 'Place the checkout on any page. Buy Now buttons: [swift_buy_now id="123"]. Landing order section: [swift_landing id="123"].', 'swift-checkout-for-woocommerce' ); ?></p>
		<?php
	}

	/**
	 * Booster tab: simple order bump fields (fixed product + discount %, live in v1.0.0).
	 *
	 * @param array $b Stored swco_bump values.
	 */
	private static function render_bump( array $b ): void {
		?>
		<h3><?php echo esc_html__( 'Order Bump', 'swift-checkout-for-woocommerce' ); ?></h3>
		<p class="description"><?php echo esc_html__( 'Offer one fixed product with a checkbox on checkout. Simple products only.', 'swift-checkout-for-woocommerce' ); ?></p>
		<table class="form-table" role="presentation">
			<tbody>
				<?php self::row_checkbox( __( 'Enable order bump', 'swift-checkout-for-woocommerce' ), 'swco_bump', 'enabled', $b['enabled'] ?? 'no', '' ); ?>
				<?php self::row_text( __( 'Product ID', 'swift-checkout-for-woocommerce' ), 'swco_bump', 'product_id', (string) ( $b['product_id'] ?? 0 ), __( 'Simple, purchasable, in-stock product. 0 = off.', 'swift-checkout-for-woocommerce' ) ); ?>
				<?php self::row_text( __( 'Discount %', 'swift-checkout-for-woocommerce' ), 'swco_bump', 'discount_pct', (string) ( $b['discount_pct'] ?? 0 ), __( '0–90. Applied to the bump line in cart totals.', 'swift-checkout-for-woocommerce' ) ); ?>
				<?php
				self::row_select(
					__( 'Position', 'swift-checkout-for-woocommerce' ),
					'swco_bump',
					'position',
					$b['position'] ?? 'before-payment',
					array(
						'before-payment' => __( 'Before payment', 'swift-checkout-for-woocommerce' ),
						'after-summary'  => __( 'After order summary', 'swift-checkout-for-woocommerce' ),
					),
					''
				);
				?>
				<?php self::row_text( __( 'Box title', 'swift-checkout-for-woocommerce' ), 'swco_bump', 'title', $b['title'] ?? '', '' ); ?>
				<?php self::row_text( __( 'Box description', 'swift-checkout-for-woocommerce' ), 'swco_bump', 'desc', $b['desc'] ?? '', '' ); ?>
			</tbody>
		</table>

		<h3><?php echo esc_html__( 'Funnels (max 3 steps)', 'swift-checkout-for-woocommerce' ); ?></h3>
		<p class="description"><?php echo esc_html__( 'Landing → Checkout → Thank you. Link pages in a funnel; shoppers entering at the checkout page finish on your thank-you page.', 'swift-checkout-for-woocommerce' ); ?></p>
		<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=swco_funnel' ) ); ?>" class="button"><?php echo esc_html__( 'Manage funnels', 'swift-checkout-for-woocommerce' ); ?></a></p>
		<?php
	}

	/**
	 * Opt-in tab: form copy + leads count with admin link.
	 *
	 * @param array $o Stored swco_optin values.
	 */
	private static function render_optin( array $o ): void {
		?>
		<h3><?php echo esc_html__( 'Lead Opt-in', 'swift-checkout-for-woocommerce' ); ?></h3>
		<p class="description"><?php echo esc_html__( 'Show [swift_optin] anywhere to collect names and emails. Leads stay private in wp-admin — no external service.', 'swift-checkout-for-woocommerce' ); ?></p>
		<table class="form-table" role="presentation">
			<tbody>
				<?php self::row_checkbox( __( 'Enable opt-in form', 'swift-checkout-for-woocommerce' ), 'swco_optin', 'enabled', $o['enabled'] ?? 'no', '' ); ?>
				<?php self::row_text( __( 'Form title', 'swift-checkout-for-woocommerce' ), 'swco_optin', 'title', $o['title'] ?? '', '' ); ?>
				<?php self::row_text( __( 'Button text', 'swift-checkout-for-woocommerce' ), 'swco_optin', 'button_text', $o['button_text'] ?? '', '' ); ?>
				<?php self::row_text( __( 'Success message', 'swift-checkout-for-woocommerce' ), 'swco_optin', 'success_msg', $o['success_msg'] ?? '', '' ); ?>
			</tbody>
		</table>
		<p>
			<?php
			$count = SwCo_Optin::lead_count();
			printf(
				/* translators: %d: number of leads */
				esc_html__( 'Stored leads: %d', 'swift-checkout-for-woocommerce' ),
				esc_html( (string) $count )
			);
			?>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=swco_lead' ) ); ?>" class="button"><?php echo esc_html__( 'View leads', 'swift-checkout-for-woocommerce' ); ?></a>
			<button type="button" class="button swco-copy-btn" data-copy="[swift_optin]"><?php echo esc_html__( 'Copy shortcode', 'swift-checkout-for-woocommerce' ); ?></button>
		</p>
		<?php
	}

	/**
	 * Landing tab: section display defaults + form field editor.
	 *
	 * @param array $l Stored swco_landing values.
	 * @param array $fields Stored swco_landing_fields rows.
	 */
	private static function render_landing( array $l, array $fields ): void {
		?>
		<h3><?php echo esc_html__( 'Landing Order Section', 'swift-checkout-for-woocommerce' ); ?></h3>
		<p class="description"><?php echo esc_html__( 'Defaults for [swift_landing]. The shortcode button attribute still wins per use.', 'swift-checkout-for-woocommerce' ); ?></p>
		<table class="form-table" role="presentation">
			<tbody>
				<?php
				self::row_select(
					__( 'Layout', 'swift-checkout-for-woocommerce' ),
					'swco_landing',
					'layout',
					$l['layout'] ?? 'stacked',
					array(
						'stacked'    => __( 'Stacked (product, then form)', 'swift-checkout-for-woocommerce' ),
						'two-column' => __( 'Two columns (product + form side by side)', 'swift-checkout-for-woocommerce' ),
					),
					''
				);
				?>
				<?php self::row_checkbox( __( 'Show product image', 'swift-checkout-for-woocommerce' ), 'swco_landing', 'show_image', $l['show_image'] ?? 'yes', '' ); ?>
				<?php self::row_checkbox( __( 'Show quantity field', 'swift-checkout-for-woocommerce' ), 'swco_landing', 'show_qty', $l['show_qty'] ?? 'yes', '' ); ?>
				<?php self::row_checkbox( __( 'Show email field (optional)', 'swift-checkout-for-woocommerce' ), 'swco_landing', 'show_email', $l['show_email'] ?? 'no', __( 'When filled, the order carries the email and account auto-link works.', 'swift-checkout-for-woocommerce' ) ); ?>
				<?php self::row_checkbox( __( 'Show order note field (optional)', 'swift-checkout-for-woocommerce' ), 'swco_landing', 'show_note', $l['show_note'] ?? 'no', '' ); ?>
				<?php self::row_text( __( 'Default button text', 'swift-checkout-for-woocommerce' ), 'swco_landing', 'button_text', $l['button_text'] ?? '', '' ); ?>
			</tbody>
		</table>
		<?php SwCo_Admin_Fields::render_landing_table( $fields ); ?>
		<?php
	}

	/**
	 * Templates tab: preset cards with Apply + one-click restore.
	 */
	private static function render_templates(): void {
		$templates = SwCo_Templates::list();
		?>
		<h3><?php echo esc_html__( 'Design Templates', 'swift-checkout-for-woocommerce' ); ?></h3>
		<p class="description"><?php echo esc_html__( 'Applying backs up your current settings first — restore anytime.', 'swift-checkout-for-woocommerce' ); ?></p>
		<?php if ( empty( $templates ) ) : ?>
			<p><?php echo esc_html__( 'No templates found.', 'swift-checkout-for-woocommerce' ); ?></p>
		<?php else : ?>
			<div class="swco-templates">
				<?php foreach ( $templates as $slug => $template ) : ?>
					<div class="swco-template-card">
						<?php if ( '' !== $template['preview'] ) : ?>
							<img class="swco-template-shot" src="<?php echo esc_url( $template['preview'] ); ?>" alt="" loading="lazy" />
						<?php else : ?>
							<div class="swco-template-mock swco-mock-skin-<?php echo esc_attr( $template['skin'] ); ?>" aria-hidden="true">
								<span class="swco-mock-bar"></span>
								<span class="swco-mock-body swco-mock-landing-<?php echo esc_attr( $template['landing'] ); ?>">
									<span class="swco-mock-product"></span>
									<span class="swco-mock-form"><i></i><i></i><i></i></span>
								</span>
							</div>
						<?php endif; ?>
						<h4><?php echo esc_html( $template['name'] ); ?></h4>
						<?php if ( '' !== $template['description'] ) : ?>
							<p class="description"><?php echo esc_html( $template['description'] ); ?></p>
						<?php endif; ?>
						<p class="swco-template-tags">
							<?php if ( '' !== $template['skin'] ) : ?>
								<span class="swco-tag"><?php echo esc_html( ucfirst( $template['skin'] ) ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $template['landing'] ) : ?>
								<span class="swco-tag"><?php echo esc_html( ucwords( str_replace( '-', ' ', $template['landing'] ) ) ); ?> landing</span>
							<?php endif; ?>
							<?php if ( '' !== $template['checkout'] ) : ?>
								<span class="swco-tag"><?php echo esc_html( ucwords( str_replace( '-', ' ', $template['checkout'] ) ) ); ?> checkout</span>
							<?php endif; ?>
						</p>
						<form method="post" action="">
							<?php wp_nonce_field( self::NONCE ); ?>
							<input type="hidden" name="swco_template" value="<?php echo esc_attr( $slug ); ?>" />
							<p>
								<button type="submit" name="swco_apply_template" value="1" class="button button-primary"><?php echo esc_html__( 'Apply', 'swift-checkout-for-woocommerce' ); ?></button>
							</p>
						</form>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php if ( SwCo_Templates::has_backup() ) : ?>
			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE ); ?>
				<p>
					<button type="submit" name="swco_restore_template" value="1" class="button"><?php echo esc_html__( 'Restore previous settings', 'swift-checkout-for-woocommerce' ); ?></button>
				</p>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * One checkbox row.
	 *
	 * @param string $label Label text.
	 * @param string $group Settings group.
	 * @param string $key   Field key.
	 * @param string $value Stored value (yes/no).
	 * @param string $desc  Description.
	 */
	private static function row_checkbox( string $label, string $group, string $key, string $value, string $desc ): void {
		$name = 'swco_settings[' . $group . '][' . $key . ']';
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="yes" <?php checked( $value, 'yes' ); ?> />
					<?php echo esc_html__( 'Enable', 'swift-checkout-for-woocommerce' ); ?>
				</label>
				<?php if ( '' !== $desc ) : ?>
					<p class="description"><?php echo esc_html( $desc ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * One select row.
	 *
	 * @param string   $label   Label text.
	 * @param string   $group   Settings group.
	 * @param string   $key     Field key.
	 * @param string   $value   Stored value.
	 * @param string[] $options Value => label.
	 * @param string   $desc    Description.
	 */
	private static function row_select( string $label, string $group, string $key, string $value, array $options, string $desc ): void {
		$name = 'swco_settings[' . $group . '][' . $key . ']';
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( 'swco-' . $group . '-' . $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<select id="<?php echo esc_attr( 'swco-' . $group . '-' . $key ); ?>" name="<?php echo esc_attr( $name ); ?>">
					<?php foreach ( $options as $opt_value => $opt_label ) : ?>
						<option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( $value, $opt_value ); ?>><?php echo esc_html( $opt_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( '' !== $desc ) : ?>
					<p class="description"><?php echo esc_html( $desc ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * One multiselect row.
	 *
	 * @param string   $label   Label text.
	 * @param string   $group   Settings group.
	 * @param string   $key     Field key.
	 * @param string[] $values  Stored values.
	 * @param string[] $options Value => label.
	 * @param string   $desc    Description.
	 */
	private static function row_multiselect( string $label, string $group, string $key, array $values, array $options, string $desc ): void {
		$name = 'swco_settings[' . $group . '][' . $key . '][]';
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( 'swco-' . $group . '-' . $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<select id="<?php echo esc_attr( 'swco-' . $group . '-' . $key ); ?>" name="<?php echo esc_attr( $name ); ?>" multiple style="min-height: 110px; min-width: 200px;">
					<?php foreach ( $options as $opt_value => $opt_label ) : ?>
						<option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( in_array( $opt_value, $values, true ) ); ?>><?php echo esc_html( $opt_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( '' !== $desc ) : ?>
					<p class="description"><?php echo esc_html( $desc ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * One text row.
	 *
	 * @param string $label Label text.
	 * @param string $group Settings group.
	 * @param string $key   Field key.
	 * @param string $value Stored value.
	 * @param string $desc  Description.
	 */
	private static function row_text( string $label, string $group, string $key, string $value, string $desc ): void {
		$name = 'swco_settings[' . $group . '][' . $key . ']';
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( 'swco-' . $group . '-' . $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="text" id="<?php echo esc_attr( 'swco-' . $group . '-' . $key ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
				<?php if ( '' !== $desc ) : ?>
					<p class="description"><?php echo esc_html( $desc ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}
}
