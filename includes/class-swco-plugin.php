<?php
/**
 * Singleton bootstrap: constants, file loader, init hooks.
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class. One class = one job (bootstrap only).
 */
final class SwCo_Plugin {

	/**
	 * Single instance.
	 *
	 * @var SwCo_Plugin|null
	 */
	private static ?self $instance = null;

	/**
	 * Get the single instance.
	 *
	 * @return SwCo_Plugin
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {}

	/**
	 * Load files and register hooks.
	 *
	 * Step 1+ classes (redirect, layout, fields, coupon, cart-tweaks, ajax,
	 * admin menu) are required here as they land, one require per class.
	 */
	public function init(): void {
		// Settings are always available (defaults merged on read).
		SwCo_Settings::instance()->register();

		// Step 1: skip-cart redirect + Buy Now.
		require_once SWCO_DIR . 'includes/frontend/class-swco-redirect.php';
		SwCo_Redirect::init();

		// Step 2: layout override + frontend assets.
		require_once SWCO_DIR . 'includes/class-swco-assets.php';
		require_once SWCO_DIR . 'includes/frontend/class-swco-layout.php';
		SwCo_Assets::init();
		SwCo_Layout::init();

		// Step 3: fields + AJAX cart + auto-coupon.
		require_once SWCO_DIR . 'includes/frontend/class-swco-fields.php';
		require_once SWCO_DIR . 'includes/frontend/class-swco-ajax.php';
		require_once SWCO_DIR . 'includes/frontend/class-swco-cart-tweaks.php';
		require_once SWCO_DIR . 'includes/frontend/class-swco-coupon.php';
		SwCo_Fields::init();
		SwCo_Ajax::init();
		SwCo_Coupon::init();

		// v1.0.0-complete: simple order bump.
		require_once SWCO_DIR . 'includes/frontend/class-swco-bump.php';
		SwCo_Bump::init();

		// v1.0.0-complete: address autocomplete (owner key only).
		require_once SWCO_DIR . 'includes/frontend/class-swco-maps.php';
		SwCo_Maps::init();

		// v1.0.0-complete: lead opt-in (private CPT + form).
		require_once SWCO_DIR . 'includes/frontend/class-swco-optin.php';
		SwCo_Optin::init();

		// v1.0.0-complete: funnels, max 3 steps.
		require_once SWCO_DIR . 'includes/frontend/class-swco-funnel.php';
		SwCo_Funnel::init();

		// v1.0.0: auto-create account for guest orders (opt-in setting).
		require_once SWCO_DIR . 'includes/frontend/class-swco-accounts.php';
		SwCo_Accounts::init();

		// v1.0.0: landing order section (product + direct COD form).
		require_once SWCO_DIR . 'includes/frontend/class-swco-landing.php';
		SwCo_Landing::init();

		// v1.0.0: Elementor widgets (inert without Elementor).
		require_once SWCO_DIR . 'includes/integrations/class-swco-elementor.php';
		SwCo_Elementor::init();

		// Step 5: admin settings tab (admin only).
		if ( is_admin() ) {
			require_once SWCO_DIR . 'includes/admin/class-swco-admin-menu.php';
			require_once SWCO_DIR . 'includes/admin/class-swco-admin-fields.php';
			require_once SWCO_DIR . 'includes/admin/class-swco-templates.php';
			SwCo_Admin_Menu::init();
		}

		/**
		 * Fires after Swift Checkout is initialized.
		 * Later steps hook their classes here.
		 */
		do_action( 'swco_init' );
	}
}
