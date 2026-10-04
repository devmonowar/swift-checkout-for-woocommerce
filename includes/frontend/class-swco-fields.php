<?php
/**
 * Field Editor: hide / required / reorder for the locked rows (WC core 10
 * + our 4 BD address fields), plus order-notes / coupon / terms / shipping
 * toggles.
 *
 * Spec: plan §5.2 row 3, §6.1 swco_fields + swco_checkout toggles.
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Filters WooCommerce checkout fields from settings.
 */
final class SwCo_Fields {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'filter' ), 20, 1 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save_custom_fields' ), 20, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'show_custom_fields_admin' ) );

		$checkout = SwCo_Settings::instance()->get_group( 'swco_checkout' );

		if ( 'yes' === ( $checkout['hide_order_notes'] ?? 'yes' ) ) {
			add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );
		}
		if ( 'yes' === ( $checkout['hide_terms'] ?? 'no' ) ) {
			add_filter( 'woocommerce_checkout_show_terms', '__return_false' );
		}
		if ( 'yes' === ( $checkout['hide_shipping'] ?? 'no' ) ) {
			add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );
		}
		if ( 'hide' === ( $checkout['hide_coupon'] ?? 'show' ) ) {
			remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
		}
	}

	/**
	 * Is the plugin enabled?
	 *
	 * @return bool
	 */
	private static function enabled(): bool {
		return 'yes' === SwCo_Settings::instance()->get_value( 'swco_general', 'enabled', 'yes' );
	}

	/**
	 * Apply hide / required / priority to the locked rows.
	 * Our 4 BD fields don't exist in WooCommerce — they are created here.
	 *
	 * @param array $fields Checkout fields array.
	 * @return array
	 */
	public static function filter( array $fields ): array {
		if ( ! self::enabled() ) {
			return $fields;
		}
		if ( ! isset( $fields['billing'] ) || ! is_array( $fields['billing'] ) ) {
			$fields['billing'] = array();
		}
		foreach ( self::custom_definitions() as $key => $def ) {
			if ( ! isset( $fields['billing'][ $key ] ) ) {
				$fields['billing'][ $key ] = $def;
			}
		}

		$rows = SwCo_Settings::instance()->get_group( 'swco_fields' );
		if ( empty( $rows ) ) {
			return $fields;
		}

		foreach ( $rows as $key => $row ) {
			$group = self::group_for( $key );
			if ( '' === $group || ! isset( $fields[ $group ][ $key ] ) ) {
				continue;
			}
			if ( empty( $row['visible'] ) ) {
				unset( $fields[ $group ][ $key ] );
				continue;
			}
			$fields[ $group ][ $key ]['required'] = ! empty( $row['required'] );
			if ( isset( $row['order'] ) ) {
				$fields[ $group ][ $key ]['priority'] = absint( $row['order'] );
			}
		}

		self::maybe_replace_bd_state( $fields );

		return $fields;
	}

	/**
	 * For Bangladesh, our Division/District/Thana replace WooCommerce's
	 * native State field (which would otherwise duplicate the district).
	 * Other countries keep the native field untouched.
	 *
	 * @param array $fields Checkout fields (by reference).
	 */
	private static function maybe_replace_bd_state( array &$fields ): void {
		$country = '';
		if ( isset( $_POST['billing_country'] ) && is_string( $_POST['billing_country'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- display-time country detection.
			$country = sanitize_text_field( wp_unslash( $_POST['billing_country'] ) );
		} elseif ( function_exists( 'WC' ) && WC()->customer ) {
			$country = WC()->customer->get_billing_country();
		}
		if ( 'BD' !== $country || ! isset( $fields['billing']['billing_state'] ) ) {
			return;
		}
		$fields['billing']['billing_state']['required'] = false;
		$fields['billing']['billing_state']['hidden']   = true;
	}

	/**
	 * Which field group owns this key?
	 *
	 * @param string $key Field key.
	 * @return string billing|order|'' (unknown).
	 */
	private static function group_for( string $key ): string {
		if ( 'order_comments' === $key ) {
			return 'order';
		}
		if ( 0 === strpos( $key, 'billing_' ) ) { // strpos, not str_starts_with() — PHP 7.4 floor.
			return 'billing';
		}
		return '';
	}

	/**
	 * Coupon display mode: show | hide | toggle.
	 *
	 * @return string
	 */
	public static function coupon_mode(): string {
		$mode = SwCo_Settings::instance()->get_value( 'swco_checkout', 'hide_coupon', 'show' );
		return in_array( $mode, array( 'show', 'hide', 'toggle' ), true ) ? $mode : 'show';
	}

	/**
	 * Full definitions for our 4 BD address fields (WooCommerce has none).
	 * Select options are whitelisted again on order creation.
	 *
	 * @return array Field key => WC field definition.
	 */
	public static function custom_definitions(): array {
		$divisions = array(
			''            => __( 'Select division…', 'swift-checkout-for-woocommerce' ),
			'Dhaka'       => __( 'Dhaka', 'swift-checkout-for-woocommerce' ),
			'Chattogram'  => __( 'Chattogram', 'swift-checkout-for-woocommerce' ),
			'Rajshahi'    => __( 'Rajshahi', 'swift-checkout-for-woocommerce' ),
			'Khulna'      => __( 'Khulna', 'swift-checkout-for-woocommerce' ),
			'Barishal'    => __( 'Barishal', 'swift-checkout-for-woocommerce' ),
			'Sylhet'      => __( 'Sylhet', 'swift-checkout-for-woocommerce' ),
			'Rangpur'     => __( 'Rangpur', 'swift-checkout-for-woocommerce' ),
			'Mymensingh'  => __( 'Mymensingh', 'swift-checkout-for-woocommerce' ),
		);
		$districts = array( '' => __( 'Select district…', 'swift-checkout-for-woocommerce' ) );
		foreach ( self::bd_districts() as $district ) {
			$districts[ $district ] = $district;
		}
		return array(
			'billing_division' => array(
				'label'    => __( 'Division', 'swift-checkout-for-woocommerce' ),
				'type'     => 'select',
				'options'  => $divisions,
				'class'    => array( 'form-row-wide' ),
				'priority' => 65,
			),
			'billing_district' => array(
				'label'    => __( 'District', 'swift-checkout-for-woocommerce' ),
				'type'     => 'select',
				'options'  => $districts,
				'class'    => array( 'form-row-wide' ),
				'priority' => 66,
			),
			'billing_thana'    => array(
				'label'       => __( 'Thana / Area', 'swift-checkout-for-woocommerce' ),
				'placeholder' => __( 'e.g. Mirpur, Savar', 'swift-checkout-for-woocommerce' ),
				'class'       => array( 'form-row-wide' ),
				'priority'    => 67,
			),
			'billing_landmark' => array(
				'label'       => __( 'Landmark (optional)', 'swift-checkout-for-woocommerce' ),
				'placeholder' => __( 'e.g. Near Central Mosque', 'swift-checkout-for-woocommerce' ),
				'class'       => array( 'form-row-wide' ),
				'priority'    => 68,
			),
		);
	}

	/**
	 * Bangladesh's 64 districts (English names, alphabetical).
	 *
	 * @return string[]
	 */
	public static function bd_districts(): array {
		return array(
			'Bagerhat', 'Bandarban', 'Barguna', 'Barishal', 'Bhola', 'Bogura',
			'Brahmanbaria', 'Chandpur', 'Chapai Nawabganj', 'Chattogram', 'Chuadanga', 'Cox’s Bazar',
			'Cumilla', 'Dhaka', 'Dinajpur', 'Faridpur', 'Feni', 'Gaibandha',
			'Gazipur', 'Gopalganj', 'Habiganj', 'Jamalpur', 'Jashore',
			'Jhalokati', 'Jhenaidah', 'Joypurhat', 'Khagrachhari', 'Khulna',
			'Kishoreganj', 'Kurigram', 'Kushtia', 'Lakshmipur', 'Lalmonirhat',
			'Madaripur', 'Magura', 'Manikganj', 'Meherpur', 'Moulvibazar',
			'Munshiganj', 'Mymensingh', 'Naogaon', 'Narail', 'Narayanganj',
			'Narsingdi', 'Natore', 'Netrokona', 'Nilphamari', 'Noakhali',
			'Pabna', 'Panchagarh', 'Patuakhali', 'Pirojpur', 'Rajbari',
			'Rajshahi', 'Rangamati', 'Rangpur', 'Satkhira', 'Shariatpur',
			'Sherpur', 'Sirajganj', 'Sunamganj', 'Sylhet', 'Tangail', 'Thakurgaon',
		);
	}

	/**
	 * Persist the BD fields on the order (HPOS-safe meta).
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $data  Posted checkout data (unused — $_POST is canonical).
	 */
	public static function save_custom_fields( $order, array $data ): void {
		unset( $data );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$divisions = array( 'Dhaka', 'Chattogram', 'Rajshahi', 'Khulna', 'Barishal', 'Sylhet', 'Rangpur', 'Mymensingh' );
		if ( isset( $_POST['billing_division'] ) && is_string( $_POST['billing_division'] ) && in_array( wp_unslash( $_POST['billing_division'] ), $divisions, true ) ) {
			$order->update_meta_data( '_billing_division', wp_unslash( $_POST['billing_division'] ) );
		}
		if ( isset( $_POST['billing_district'] ) && is_string( $_POST['billing_district'] ) && in_array( wp_unslash( $_POST['billing_district'] ), self::bd_districts(), true ) ) {
			$order->update_meta_data( '_billing_district', wp_unslash( $_POST['billing_district'] ) );
		}
		if ( isset( $_POST['billing_thana'] ) && is_string( $_POST['billing_thana'] ) ) {
			$order->update_meta_data( '_billing_thana', sanitize_text_field( wp_unslash( $_POST['billing_thana'] ) ) );
		}
		if ( isset( $_POST['billing_landmark'] ) && is_string( $_POST['billing_landmark'] ) ) {
			$order->update_meta_data( '_billing_landmark', sanitize_text_field( wp_unslash( $_POST['billing_landmark'] ) ) );
		}
	}

	/**
	 * Show the BD fields under the billing address in admin order view.
	 *
	 * @param WC_Order $order Order object.
	 */
	public static function show_custom_fields_admin( $order ): void {
		if ( ! $order instanceof WC_Order || ! function_exists( 'get_post_meta' ) ) {
			return;
		}
		$labels = array(
			'_billing_division' => __( 'Division', 'swift-checkout-for-woocommerce' ),
			'_billing_district' => __( 'District', 'swift-checkout-for-woocommerce' ),
			'_billing_thana'    => __( 'Thana / Area', 'swift-checkout-for-woocommerce' ),
			'_billing_landmark' => __( 'Landmark', 'swift-checkout-for-woocommerce' ),
		);
		foreach ( $labels as $key => $label ) {
			$value = $order->get_meta( $key, true );
			if ( '' !== $value ) {
				echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</p>';
			}
		}
	}
}
