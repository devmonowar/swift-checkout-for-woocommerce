<?php
/**
 * Elementor widget classes. Required ONLY after Elementor proves it is
 * loaded (see SwCo_Elementor::register_widgets) — never directly, or the
 * `extends \Elementor\Widget_Base` declarations fatal without Elementor.
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shared helpers for the three widgets.
 */
abstract class SwCo_Elementor_Base_Widget extends \Elementor\Widget_Base {

	/**
	 * All widgets live in our panel category.
	 *
	 * @return string[]
	 */
	public function get_categories(): array {
		return array( 'swift-checkout' );
	}

	/**
	 * Product-ID control reused by product widgets.
	 */
	protected function product_id_control(): void {
		$this->add_control(
			'swco_product_id',
			array(
				'label'   => __( 'Product ID', 'swift-checkout-for-woocommerce' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'default' => 0,
			)
		);
	}

	/**
	 * Button-text control.
	 *
	 * @param string $default Default label.
	 */
	protected function button_text_control( string $default ): void {
		$this->add_control(
			'swco_button_text',
			array(
				'label'   => __( 'Button text', 'swift-checkout-for-woocommerce' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => $default,
			)
		);
	}
}

/**
 * Full checkout form widget → [swift_checkout].
 */
class SwCo_Elementor_Checkout_Widget extends SwCo_Elementor_Base_Widget {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'swco-checkout';
	}

	/**
	 * Panel title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Swift Checkout Form', 'swift-checkout-for-woocommerce' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-checkout';
	}

	/**
	 * Search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'swift', 'checkout', 'woocommerce' );
	}

	/**
	 * No settings — the global layout applies.
	 */
	protected function register_controls(): void {}

	/**
	 * Render via the shortcode (single render path).
	 */
	protected function render(): void {
		echo SwCo_Layout::shortcode( array( 'class' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode() escapes its own output.
	}
}

/**
 * Landing order section widget → [swift_landing].
 */
class SwCo_Elementor_Landing_Widget extends SwCo_Elementor_Base_Widget {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'swco-landing';
	}

	/**
	 * Panel title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Swift Landing Order', 'swift-checkout-for-woocommerce' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-cart-medium';
	}

	/**
	 * Search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'swift', 'landing', 'order', 'cod' );
	}

	/**
	 * Product + button controls (dropdown when products exist).
	 */
	protected function register_controls(): void {
		$options = array( 0 => __( '— Select product —', 'swift-checkout-for-woocommerce' ) );
		if ( class_exists( 'SwCo_Landing' ) ) {
			foreach ( SwCo_Landing::product_options() as $item ) {
				$options[ (int) $item['id'] ] = $item['name'];
			}
		}
		$this->add_control(
			'swco_product_id',
			array(
				'label'   => __( 'Product', 'swift-checkout-for-woocommerce' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $options,
				'default' => 0,
			)
		);
		$this->button_text_control( __( 'Order Now', 'swift-checkout-for-woocommerce' ) );
	}

	/**
	 * Render via the shortcode (single render path).
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		echo SwCo_Landing::shortcode( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode() escapes its own output.
			array(
				'id'     => absint( $settings['swco_product_id'] ?? 0 ),
				'button' => $settings['swco_button_text'] ?? '',
			)
		);
	}
}

/**
 * Buy Now button widget → [swift_buy_now].
 */
class SwCo_Elementor_Buy_Now_Widget extends SwCo_Elementor_Base_Widget {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'swco-buy-now';
	}

	/**
	 * Panel title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Swift Buy Now Button', 'swift-checkout-for-woocommerce' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-button';
	}

	/**
	 * Search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'swift', 'buy now', 'button' );
	}

	/**
	 * Product control (label text follows the global setting).
	 */
	protected function register_controls(): void {
		$this->product_id_control();
	}

	/**
	 * Render via the shortcode (single render path).
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		echo SwCo_Redirect::buy_now_shortcode( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode escapes its own output.
			array(
				'id' => absint( $settings['swco_product_id'] ?? 0 ),
			)
		);
	}
}
