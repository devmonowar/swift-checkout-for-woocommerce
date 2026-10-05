<?php
/**
 * Multi-step checkout layout: Step 1 Details → Step 2 Payment.
 * JS-only stepping with native per-step validation (reportValidity).
 * Without JS both panes stay visible, so checkout never dead-ends.
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- file fires WooCommerce core hooks (required for checkout compatibility).

$swco_checkout = isset( $checkout ) ? $checkout : null;

do_action( 'woocommerce_before_checkout_form', $swco_checkout );
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

	<div class="swco-checkout swco-multi">

		<ol class="swco-steps" aria-label="<?php echo esc_attr__( 'Checkout steps', 'swift-checkout-for-woocommerce' ); ?>">
			<li class="swco-step swco-step-1" data-goto="1" aria-current="step">
				<span class="swco-step-num">1</span>
				<span class="swco-step-label"><?php echo esc_html__( 'Details', 'swift-checkout-for-woocommerce' ); ?></span>
			</li>
			<li class="swco-step swco-step-2" data-goto="2">
				<span class="swco-step-num">2</span>
				<span class="swco-step-label"><?php echo esc_html__( 'Payment', 'swift-checkout-for-woocommerce' ); ?></span>
			</li>
		</ol>

		<div class="swco-pane swco-pane-1" data-pane="1">
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
			<div class="swco-cart">
				<?php SwCo_Cart_Tweaks::render_cart_section(); ?>
			</div>

			<?php SwCo_Bump::render( 'after-summary' ); ?>

			<div class="swco-pane-nav">
				<button type="button" class="button swco-next"><?php echo esc_html__( 'Continue to payment', 'swift-checkout-for-woocommerce' ); ?></button>
			</div>
		</div>

		<div class="swco-pane swco-pane-2" data-pane="2">
			<?php SwCo_Bump::render( 'before-payment' ); ?>
			<div class="swco-payment">
				<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
				<?php if ( function_exists( 'woocommerce_checkout_payment' ) ) { woocommerce_checkout_payment(); } ?>
			</div>

			<?php SwCo_Layout::skin_extras(); ?>
			<div class="swco-pane-nav">
				<button type="button" class="button swco-back"><?php echo esc_html__( 'Back to details', 'swift-checkout-for-woocommerce' ); ?></button>
			</div>
		</div>

		<?php SwCo_Layout::sticky_bar(); ?>

	</div>

</form>

<?php
do_action( 'woocommerce_after_checkout_form', $swco_checkout );
