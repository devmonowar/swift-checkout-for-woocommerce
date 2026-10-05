<?php
/**
 * Two-column checkout layout (form left, summary right).
 * Original arrangement of WooCommerce's own checkout hooks.
 * The place-order button ships inside the payment template (WooCommerce standard).
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- file fires WooCommerce core hooks (required for checkout compatibility).

$swco_checkout = isset( $checkout ) ? $checkout : null;

do_action( 'woocommerce_before_checkout_form', $swco_checkout );
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

	<div class="swco-checkout swco-two-col">

		<div class="swco-left">
			<div class="swco-form">
				<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>
				<div class="swco-billing">
					<?php do_action( 'woocommerce_checkout_billing' ); ?>
				</div>
				<div class="swco-shipping">
					<?php do_action( 'woocommerce_checkout_shipping' ); ?>
				</div>
				<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
			</div>
			<?php if ( 'hide' !== SwCo_Fields::coupon_mode() ) : ?>
				<div class="swco-coupon">
					<?php if ( 'toggle' === SwCo_Fields::coupon_mode() ) : ?>
						<details class="swco-coupon-toggle">
							<summary><?php echo esc_html__( 'Have a coupon? Click here to enter your code', 'swift-checkout-for-woocommerce' ); ?></summary>
							<?php if ( function_exists( 'woocommerce_checkout_coupon_form' ) ) { woocommerce_checkout_coupon_form(); } ?>
						</details>
					<?php else : ?>
						<?php if ( function_exists( 'woocommerce_checkout_coupon_form' ) ) { woocommerce_checkout_coupon_form(); } ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="swco-right swco-summary">
			<div class="swco-cart">
				<?php SwCo_Cart_Tweaks::render_cart_section(); ?>
			</div>

			<?php SwCo_Bump::render( 'after-summary' ); ?>
			<div class="swco-payment">
				<?php SwCo_Bump::render( 'before-payment' ); ?>
				<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
				<?php if ( function_exists( 'woocommerce_checkout_payment' ) ) { woocommerce_checkout_payment(); } ?>
			</div>

			<?php SwCo_Layout::skin_extras(); ?>

			<?php SwCo_Layout::sticky_bar(); ?>
		</div>

	</div>

</form>

<?php
do_action( 'woocommerce_after_checkout_form', $swco_checkout );
