<?php
/**
 * Funnels, max 3 steps: Landing → Checkout → Thankyou.
 *
 * Steps are plain Pages linked from the funnel (no child posts, nothing
 * orphaned). Visiting a funnel's checkout page stores the funnel in the
 * WC session; the thank-you redirect then lands on the funnel's thank-you
 * page. Landing pages are owner's content built with our shortcodes.
 *
 * Spec: plan Step 6f (v1.0.0-complete).
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Funnel CPT + step mapping + flow wiring.
 */
final class SwCo_Funnel {

	/**
	 * Funnel CPT slug.
	 */
	const CPT = 'swco_funnel';

	/**
	 * Reverse map option: checkout page ID => flow data.
	 */
	const MAP_OPTION = 'swco_funnel_pages';

	/**
	 * Hard limit: 3 steps per funnel.
	 */
	const MAX_STEPS = 3;

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_steps_box' ) );
		add_action( 'save_post_' . self::CPT, array( __CLASS__, 'save_steps' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_set_context' ) );
		add_filter( 'woocommerce_get_checkout_order_received_url', array( __CLASS__, 'thankyou_url' ), 20, 2 );
	}

	/**
	 * Register the funnel CPT under the WooCommerce menu.
	 */
	public static function register_cpt(): void {
		register_post_type(
			self::CPT,
			array(
				'labels'              => array(
					'name'          => __( 'Funnels', 'swift-checkout-for-woocommerce' ),
					'singular_name' => __( 'Funnel', 'swift-checkout-for-woocommerce' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'woocommerce',
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'capability_type'     => 'post',
				'supports'            => array( 'title' ),
				'menu_icon'           => 'dashicons-filter',
			)
		);
	}

	/**
	 * Step types (whitelist).
	 *
	 * @return array Slug => label.
	 */
	public static function step_types(): array {
		return array(
			'none'     => __( '— None —', 'swift-checkout-for-woocommerce' ),
			'landing'  => __( 'Landing', 'swift-checkout-for-woocommerce' ),
			'checkout' => __( 'Checkout', 'swift-checkout-for-woocommerce' ),
			'thankyou' => __( 'Thank you', 'swift-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Layouts for checkout steps (whitelist, mirrors global setting).
	 *
	 * @return array Slug => label.
	 */
	public static function step_layouts(): array {
		return array(
			'global'     => __( 'Use global layout', 'swift-checkout-for-woocommerce' ),
			'one-column' => __( 'One column', 'swift-checkout-for-woocommerce' ),
			'two-column' => __( 'Two columns', 'swift-checkout-for-woocommerce' ),
			'multi-step' => __( 'Multi-step', 'swift-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Add the steps meta box.
	 */
	public static function add_steps_box(): void {
		add_meta_box(
			'swco_funnel_steps',
			__( 'Funnel Steps (max 3)', 'swift-checkout-for-woocommerce' ),
			array( __CLASS__, 'render_steps_box' ),
			self::CPT,
			'normal',
			'high'
		);
	}

	/**
	 * Render 3 step slots: type + page + layout.
	 *
	 * @param WP_Post $post Funnel post.
	 */
	public static function render_steps_box( $post ): void {
		$steps   = self::get_steps( $post->ID );
		$types   = self::step_types();
		$layouts = self::step_layouts();
		wp_nonce_field( 'swco_funnel_steps', 'swco_funnel_nonce' );
		?>
		<p class="description"><?php echo esc_html__( 'Link up to 3 pages: Landing (your content) → Checkout (with [swift_checkout]) → Thank you. Shoppers entering at the checkout page finish on your thank-you page.', 'swift-checkout-for-woocommerce' ); ?></p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Step', 'swift-checkout-for-woocommerce' ); ?></th>
					<th><?php echo esc_html__( 'Type', 'swift-checkout-for-woocommerce' ); ?></th>
					<th><?php echo esc_html__( 'Page', 'swift-checkout-for-woocommerce' ); ?></th>
					<th><?php echo esc_html__( 'Layout (checkout only)', 'swift-checkout-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php for ( $i = 0; $i < self::MAX_STEPS; $i++ ) : ?>
					<?php $row = $steps[ $i ] ?? array( 'type' => 'none', 'page_id' => 0, 'layout' => 'global' ); ?>
					<tr>
						<td><?php echo esc_html( (string) ( $i + 1 ) ); ?></td>
						<td>
							<select name="swco_steps[<?php echo esc_attr( (string) $i ); ?>][type]">
								<?php foreach ( $types as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $row['type'] ?? 'none', $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'             => esc_attr( sprintf( 'swco_steps[%d][page_id]', $i ) ),
									'selected'         => absint( $row['page_id'] ?? 0 ),
									'show_option_none' => __( '— Select —', 'swift-checkout-for-woocommerce' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_dropdown_pages() escapes its arguments.
								)
							);
							?>
						</td>
						<td>
							<select name="swco_steps[<?php echo esc_attr( (string) $i ); ?>][layout]">
								<?php foreach ( $layouts as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $row['layout'] ?? 'global', $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endfor; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Stored steps for a funnel (sanitized shape, max 3).
	 *
	 * @param int $funnel_id Funnel post ID.
	 * @return array[]
	 */
	public static function get_steps( int $funnel_id ): array {
		$steps = get_post_meta( $funnel_id, '_swco_steps', true );
		return is_array( $steps ) ? array_slice( array_values( $steps ), 0, self::MAX_STEPS ) : array();
	}

	/**
	 * Save handler (WP hook wrapper).
	 *
	 * @param int     $post_id Funnel post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save_steps( int $post_id, $post ): void {
		if ( ! isset( $_POST['swco_funnel_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['swco_funnel_nonce'] ) ), 'swco_funnel_steps' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( $post instanceof WP_Post && 'auto-draft' === $post->post_status ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$raw = isset( $_POST['swco_steps'] ) && is_array( $_POST['swco_steps'] ) ? wp_unslash( $_POST['swco_steps'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in clean_steps().
		update_post_meta( $post_id, '_swco_steps', self::clean_steps( $raw ) );
		self::rebuild_map();
	}

	/**
	 * Sanitize raw slot rows: whitelist types/layouts, valid pages only,
	 * drop empty slots, hard-cap at 3.
	 *
	 * @param array $raw Raw submitted rows.
	 * @return array[]
	 */
	public static function clean_steps( array $raw ): array {
		$types   = array( 'landing', 'checkout', 'thankyou' );
		$layouts = array( 'global', 'one-column', 'two-column', 'multi-step' );
		$clean   = array();

		foreach ( array_slice( array_values( $raw ), 0, self::MAX_STEPS ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$type = isset( $row['type'] ) ? sanitize_key( $row['type'] ) : 'none';
			$page = isset( $row['page_id'] ) ? absint( $row['page_id'] ) : 0;
			if ( ! in_array( $type, $types, true ) || $page <= 0 ) {
				continue;
			}
			if ( function_exists( 'get_post_type' ) && 'page' !== get_post_type( $page ) ) {
				continue;
			}
			$layout = isset( $row['layout'] ) ? sanitize_key( $row['layout'] ) : 'global';
			$clean[] = array(
				'type'    => $type,
				'page_id' => $page,
				'layout'  => in_array( $layout, $layouts, true ) ? $layout : 'global',
			);
		}

		return $clean;
	}

	/**
	 * Rebuild the reverse map: checkout page ID => flow data.
	 * Lets template_redirect find the funnel without querying.
	 */
	public static function rebuild_map(): void {
		$map = array();
		if ( ! function_exists( 'get_posts' ) ) {
			return;
		}
		$funnels = get_posts(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		foreach ( (array) $funnels as $funnel_id ) {
			$thankyou = 0;
			foreach ( self::get_steps( (int) $funnel_id ) as $step ) {
				if ( 'thankyou' === $step['type'] ) {
					$thankyou = $step['page_id'];
				}
			}
			foreach ( self::get_steps( (int) $funnel_id ) as $step ) {
				if ( 'checkout' === $step['type'] ) {
					$map[ $step['page_id'] ] = array(
						'funnel_id' => (int) $funnel_id,
						'layout'    => $step['layout'],
						'thankyou'  => $thankyou,
					);
				}
			}
		}
		update_option( self::MAP_OPTION, $map );
	}

	/**
	 * On a funnel checkout page, remember the flow in the WC session.
	 */
	public static function maybe_set_context(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session || ! function_exists( 'is_page' ) || ! is_page() ) {
			return;
		}
		$page_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
		if ( $page_id <= 0 ) {
			return;
		}
		$map = get_option( self::MAP_OPTION, array() );
		if ( isset( $map[ $page_id ] ) ) {
			WC()->session->set( 'swco_funnel', $map[ $page_id ] );
		}
	}

	/**
	 * Current funnel flow from the session, if any.
	 *
	 * @return array Empty when not in a funnel.
	 */
	public static function context(): array {
		if ( function_exists( 'WC' ) && WC()->session ) {
			$ctx = WC()->session->get( 'swco_funnel', array() );
			return is_array( $ctx ) ? $ctx : array();
		}
		return array();
	}

	/**
	 * Checkout-step layout override for the active funnel.
	 *
	 * @return string Layout slug or empty for global.
	 */
	public static function checkout_layout(): string {
		$ctx    = self::context();
		$layout = $ctx['layout'] ?? 'global';
		return in_array( $layout, array( 'one-column', 'two-column', 'multi-step' ), true ) ? $layout : '';
	}

	/**
	 * Send funnel checkouts to the funnel's thank-you page.
	 *
	 * @param string   $url   Default received-order URL.
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	public static function thankyou_url( string $url, $order ): string {
		unset( $order );
		$ctx = self::context();
		if ( empty( $ctx['thankyou'] ) || ! function_exists( 'get_permalink' ) ) {
			return $url;
		}
		$thankyou_url = get_permalink( (int) $ctx['thankyou'] );
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'swco_funnel', null );
		}
		return '' !== $thankyou_url ? $thankyou_url : $url;
	}
}
