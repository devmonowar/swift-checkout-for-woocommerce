<?php
/**
 * Frontend asset loader: scoped CSS + vanilla/jQuery behaviour + AJAX nonce.
 *
 * Spec: plan §5.1, §5.4 (no CDN — WP-bundled jQuery only).
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues frontend.css / frontend.js on checkout pages.
 */
final class SwCo_Assets {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_public' ) );
	}

	/**
	 * Tiny public CSS for Buy Now buttons anywhere on the frontend.
	 */
	public static function enqueue_public(): void {
		if ( 'yes' !== SwCo_Settings::instance()->get_value( 'swco_general', 'enabled', 'yes' ) ) {
			return;
		}
		wp_enqueue_style(
			'swco-public',
			SWCO_URL . 'assets/css/swco-public.css',
			array(),
			SWCO_VERSION
		);
	}

	/**
	 * Load assets only where they are needed: the checkout page or any
	 * page carrying our shortcode/block.
	 */
	public static function enqueue(): void {
		$settings = SwCo_Settings::instance();
		if ( 'yes' !== $settings->get_value( 'swco_general', 'enabled', 'yes' ) ) {
			return;
		}
		if ( ! function_exists( 'is_checkout' ) ) {
			return;
		}
		if ( ! is_checkout() && ! self::page_contains( array( 'swift_checkout', 'swco/checkout' ) ) ) {
			return;
		}

		wp_enqueue_style(
			'swco-frontend',
			SWCO_URL . 'assets/css/frontend.css',
			array(),
			SWCO_VERSION
		);

		if ( class_exists( 'SwCo_Layout' ) && SwCo_Layout::is_dhaka_skin() ) {
			wp_enqueue_style(
				'swco-bd-dhaka',
				SWCO_URL . 'assets/css/bd-dhaka.css',
				array( 'swco-frontend' ),
				SWCO_VERSION
			);
		}

		wp_enqueue_script(
			'swco-frontend',
			SWCO_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			SWCO_VERSION,
			true
		);

		wp_localize_script(
			'swco-frontend',
			'swco_params',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( SwCo_Settings::NONCE ),
			)
		);
	}

	/**
	 * Does the current page use any of our shortcodes/blocks/widgets?
	 * Checks post_content AND Elementor data (Elementor stores content
	 * in meta, not post_content).
	 *
	 * @param string[] $needles Markers like shortcode tags or block names.
	 * @return bool
	 */
	public static function page_contains( array $needles ): bool {
		global $post;
		if ( ! $post instanceof WP_Post ) {
			return false;
		}
		$haystacks = array( (string) $post->post_content );
		if ( function_exists( 'get_post_meta' ) ) {
			$elementor = get_post_meta( $post->ID, '_elementor_data', true );
			if ( is_string( $elementor ) && '' !== $elementor ) {
				$haystacks[] = $elementor;
			}
		}
		foreach ( $needles as $needle ) {
			foreach ( $haystacks as $haystack ) {
				if ( '' !== $needle && false !== strpos( $haystack, $needle ) ) { // strpos, not str_contains() — PHP 7.4 floor.
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Does the current post contain our checkout shortcode or block?
	 *
	 * @return bool
	 */
	private static function has_shortcode(): bool {
		return self::page_contains( array( 'swift_checkout', 'swco/checkout' ) );
	}
}
