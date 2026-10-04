<?php
/**
 * Lead Opt-in: a simple form ([swift_optin]) storing leads in a private
 * CPT. No email integrations, no external calls — the owner reads leads
 * in wp-admin and exports whenever they like.
 *
 * Spec: plan §12.2 (v1.0.0-complete).
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * CPT + shortcode + form handler.
 */
final class SwCo_Optin {

	/**
	 * Leads CPT slug.
	 */
	const CPT = 'swco_lead';

	/**
	 * Form nonce action.
	 */
	const NONCE = 'swco_optin_submit';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
		add_shortcode( 'swift_optin', array( __CLASS__, 'shortcode' ) );
		add_action( 'admin_post_swco_optin_submit', array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_swco_optin_submit', array( __CLASS__, 'handle_submit' ) );
	}

	/**
	 * Is the opt-in form enabled?
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		$settings = SwCo_Settings::instance();
		return 'yes' === $settings->get_value( 'swco_general', 'enabled', 'yes' )
			&& 'yes' === $settings->get_value( 'swco_optin', 'enabled', 'no' );
	}

	/**
	 * Register the private leads CPT (admin list only, never public).
	 */
	public static function register_cpt(): void {
		register_post_type(
			self::CPT,
			array(
				'labels'              => array(
					'name'          => __( 'Leads', 'swift-checkout-for-woocommerce' ),
					'singular_name' => __( 'Lead', 'swift-checkout-for-woocommerce' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'capability_type'     => 'post',
				'supports'            => array( 'title' ),
				'menu_icon'           => 'dashicons-email',
			)
		);
	}

	/**
	 * Shortcode: [swift_optin].
	 *
	 * @param array $atts Unused (settings drive the copy).
	 * @return string
	 */
	public static function shortcode( array $atts ): string {
		unset( $atts );
		if ( ! self::enabled() ) {
			return '';
		}
		$settings = SwCo_Settings::instance();
		$title    = $settings->get_value( 'swco_optin', 'title', '' );
		$button   = $settings->get_value( 'swco_optin', 'button_text', __( 'Subscribe', 'swift-checkout-for-woocommerce' ) );
		$status   = isset( $_GET['swco_optin'] ) ? sanitize_key( wp_unslash( $_GET['swco_optin'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flag.

		ob_start();
		?>
		<div class="swco-optin">
			<?php if ( 'success' === $status ) : ?>
				<p class="swco-optin-success"><?php echo esc_html( $settings->get_value( 'swco_optin', 'success_msg', __( 'Thanks for subscribing!', 'swift-checkout-for-woocommerce' ) ) ); ?></p>
			<?php else : ?>
				<?php if ( '' !== $title ) : ?>
					<h3 class="swco-optin-title"><?php echo esc_html( $title ); ?></h3>
				<?php endif; ?>
				<?php if ( 'error' === $status ) : ?>
					<p class="swco-optin-error"><?php echo esc_html__( 'Please enter a valid name and email.', 'swift-checkout-for-woocommerce' ); ?></p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="swco-optin-form">
					<input type="hidden" name="action" value="swco_optin_submit" />
					<?php wp_nonce_field( self::NONCE ); ?>
					<p>
						<label for="swco-optin-name"><?php echo esc_html__( 'Name', 'swift-checkout-for-woocommerce' ); ?></label>
						<input type="text" id="swco-optin-name" name="swco_name" required maxlength="100" autocomplete="name" />
					</p>
					<p>
						<label for="swco-optin-email"><?php echo esc_html__( 'Email', 'swift-checkout-for-woocommerce' ); ?></label>
						<input type="email" id="swco-optin-email" name="swco_email" required maxlength="100" autocomplete="email" />
					</p>
					<p class="swco-hp" aria-hidden="true">
						<label><?php echo esc_html__( 'Leave this empty', 'swift-checkout-for-woocommerce' ); ?>
							<input type="text" name="swco_company" value="" tabindex="-1" autocomplete="off" />
						</label>
					</p>
					<p>
						<button type="submit" class="button"><?php echo esc_html( '' !== $button ? $button : __( 'Subscribe', 'swift-checkout-for-woocommerce' ) ); ?></button>
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
		wp_safe_redirect( add_query_arg( 'swco_optin', $result, $back ) );
		exit;
	}

	/**
	 * Validate + store one submission. Pure logic, directly testable.
	 *
	 * @param array $data Raw POST data.
	 * @return string success|error
	 */
	public static function process( array $data ): string {
		if ( ! self::enabled() ) {
			return 'error';
		}
		if ( ! isset( $data['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $data['_wpnonce'] ) ), self::NONCE ) ) {
			return 'error';
		}
		if ( '' !== ( $data['swco_company'] ?? '' ) ) {
			return 'error'; // Honeypot caught a bot.
		}
		$name  = isset( $data['swco_name'] ) ? sanitize_text_field( wp_unslash( $data['swco_name'] ) ) : '';
		$email = isset( $data['swco_email'] ) ? sanitize_email( wp_unslash( $data['swco_email'] ) ) : '';
		if ( '' === $name || ! is_email( $email ) ) {
			return 'error';
		}

		$lead_id = wp_insert_post(
			array(
				'post_type'   => self::CPT,
				'post_title'  => $email,
				'post_status' => 'publish',
			)
		);
		if ( ! $lead_id ) {
			return 'error';
		}
		update_post_meta( $lead_id, '_swco_lead_name', $name );
		update_post_meta( $lead_id, '_swco_lead_email', $email );
		update_post_meta( $lead_id, '_swco_lead_source', esc_url_raw( wp_get_referer() ) );

		return 'success';
	}

	/**
	 * Count stored leads for the admin tab.
	 *
	 * @return int
	 */
	public static function lead_count(): int {
		if ( ! function_exists( 'wp_count_posts' ) ) {
			return 0;
		}
		$counts = wp_count_posts( self::CPT );
		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}
}
