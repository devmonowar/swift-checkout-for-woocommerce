<?php
/**
 * Plugin Name: Swift Checkout for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/swift-checkout-for-woocommerce/
 * Description: Turn the default WooCommerce checkout into a fast, distraction-free, high-converting checkout — skip cart, Buy Now buttons, AJAX cart in checkout, and a field editor lite. No code needed.
 * Version: 1.0.0
 * Author: Monowar Hossain
 * Author URI: https://devmonowar.github.io/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: swift-checkout-for-woocommerce
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * Requires at least: 6.8
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * WC requires at least: 10.0
 * WC tested up to: 11.1
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

// Declare HPOS + Cart/Checkout Blocks compatibility before WooCommerce loads.
// Blocks: false = this plugin supports the classic (shortcode) checkout in v1.0.
add_action(
	'before_woocommerce_init',
	function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, false );
		}
	}
);

define( 'SWCO_VERSION', '1.0.0' );
define( 'SWCO_FILE', __FILE__ );
define( 'SWCO_DIR', plugin_dir_path( __FILE__ ) );
define( 'SWCO_URL', plugin_dir_url( __FILE__ ) );
define( 'SWCO_SLUG', 'swift-checkout-for-woocommerce' );

require_once SWCO_DIR . 'includes/class-swco-plugin.php';
require_once SWCO_DIR . 'includes/class-swco-settings.php';

/**
 * Boot the plugin after all plugins are loaded so WooCommerce is available.
 */
function swco_boot(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'swco_missing_wc_notice' );
		return;
	}

	load_plugin_textdomain( 'swift-checkout-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	SwCo_Plugin::instance()->init();
}
add_action( 'plugins_loaded', 'swco_boot' );

/**
 * Admin notice when WooCommerce is not active. Frontend stays silent.
 */
function swco_missing_wc_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'Swift Checkout requires WooCommerce to be installed and active.', 'swift-checkout-for-woocommerce' );
	echo '</p></div>';
}
