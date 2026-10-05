<?php
/**
 * Single-option settings store: get_option('swco_settings') + defaults + sanitize.
 *
 * Spec: plan §6.1 + v1.0.0-complete additions (swco_bump, swco_maps, swco_optin, min_subtotal).
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings wrapper around one autoloaded option.
 */
final class SwCo_Settings {

	/**
	 * Option name (single option, autoload ON).
	 */
	const OPTION = 'swco_settings';

	/**
	 * Nonce action for frontend AJAX (see plan §5.5).
	 */
	const NONCE = 'swco_nonce';

	/**
	 * Single instance.
	 *
	 * @var SwCo_Settings|null
	 */
	private static ?self $instance = null;

	/**
	 * Cached option value.
	 *
	 * @var array|null
	 */
	private ?array $cache = null;

	/**
	 * Get the single instance.
	 *
	 * @return SwCo_Settings
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
	 * Register the setting with a sanitize callback (Settings API).
	 * Admin form UI lands in Step 5; registration lives here from day one.
	 */
	public function register(): void {
		register_setting(
			'swco_settings_group',
			self::OPTION,
			array( $this, 'sanitize' )
		);
	}

	/**
	 * Default values, exactly per plan §6.1.
	 *
	 * @return array
	 */
	public function defaults(): array {
		return array(
			'version'       => SWCO_VERSION,
			'swco_general'  => array(
				'enabled'             => 'yes',
				'skip_cart'           => 'yes',
				'redirect_to'         => 'checkout',
				'custom_url'          => '',
				'replace_cart_url'    => 'no',
				'atc_text'            => __( 'Buy Now', 'swift-checkout-for-woocommerce' ),
				'auto_create_account' => 'no',
			),
			'swco_checkout' => array(
				'layout'           => 'two-column',
				'skin'             => 'default',
				'delivery_note'    => '',
				'reviews_text'     => '',
				'help_text'        => '',
				'override_global'  => 'yes',
				'cart_in_checkout' => 'yes',
				'cart_ajax'        => 'yes',
				'cart_columns'     => array( 'remove', 'thumbnail', 'price', 'qty' ),
				'hide_order_notes' => 'yes',
				'hide_coupon'      => 'show',
				'hide_terms'       => 'no',
				'hide_shipping'    => 'yes',
			),
			'swco_fields'   => array(
				'billing_first_name' => array( 'label' => 'First name', 'visible' => 1, 'required' => 1, 'order' => 10 ),
				'billing_last_name'  => array( 'label' => 'Last name', 'visible' => 1, 'required' => 1, 'order' => 20 ),
				'billing_phone'      => array( 'label' => 'Phone', 'visible' => 1, 'required' => 1, 'order' => 30 ),
				'billing_country'    => array( 'label' => 'Country', 'visible' => 1, 'required' => 1, 'order' => 40 ),
				'billing_address_1'  => array( 'label' => 'Address', 'visible' => 1, 'required' => 1, 'order' => 50 ),
				'billing_city'       => array( 'label' => 'City', 'visible' => 1, 'required' => 1, 'order' => 60 ),
				'billing_postcode'   => array( 'label' => 'Postcode', 'visible' => 1, 'required' => 1, 'order' => 70 ),
				'billing_company'    => array( 'label' => 'Company', 'visible' => 0, 'required' => 0, 'order' => 80 ),
				'billing_address_2'  => array( 'label' => 'Address 2', 'visible' => 0, 'required' => 0, 'order' => 90 ),
				'billing_division'   => array( 'label' => 'Division (BD)', 'visible' => 1, 'required' => 1, 'order' => 65 ),
				'billing_district'   => array( 'label' => 'District (BD)', 'visible' => 1, 'required' => 1, 'order' => 66 ),
				'billing_thana'      => array( 'label' => 'Thana / Area (BD)', 'visible' => 1, 'required' => 1, 'order' => 67 ),
				'billing_landmark'   => array( 'label' => 'Landmark (BD)', 'visible' => 1, 'required' => 0, 'order' => 68 ),
				'order_comments'     => array( 'label' => 'Order notes', 'visible' => 0, 'required' => 0, 'order' => 100 ),
			),
			'swco_coupon'   => array(
				'auto_coupon'  => '',
				'url_param'    => 'swco_coupon',
				'min_subtotal' => 0,
			),
			'swco_bump'     => array(
				'enabled'      => 'no',
				'product_id'   => 0,
				'discount_pct' => 0,
				'position'     => 'before-payment',
				'title'        => __( 'Add this to your order', 'swift-checkout-for-woocommerce' ),
				'desc'         => '',
			),
			'swco_maps'     => array(
				'api_key' => '',
			),
			'swco_optin'    => array(
				'enabled'     => 'no',
				'title'       => __( 'Get 10% off your first order', 'swift-checkout-for-woocommerce' ),
				'button_text' => __( 'Subscribe', 'swift-checkout-for-woocommerce' ),
				'success_msg' => __( 'Thanks for subscribing!', 'swift-checkout-for-woocommerce' ),
			),
			'swco_landing'  => array(
				'layout'        => 'stacked',
				'show_image'    => 'yes',
				'show_qty'      => 'yes',
				'show_email'    => 'no',
				'show_note'     => 'no',
				'button_text'   => __( 'Order Now', 'swift-checkout-for-woocommerce' ),
			),
			'swco_landing_fields' => array(
				'swco_name'    => array( 'label' => 'Your Name', 'required' => 1, 'order' => 10 ),
				'swco_mobile'  => array( 'label' => 'Mobile Number', 'required' => 1, 'order' => 20 ),
				'swco_address' => array( 'label' => 'Address', 'required' => 1, 'order' => 30 ),
				'swco_email'   => array( 'label' => 'Email (optional)', 'required' => 0, 'order' => 40 ),
				'swco_qty'     => array( 'label' => 'Quantity', 'required' => 0, 'order' => 50 ),
				'swco_note'    => array( 'label' => 'Order Note (optional)', 'required' => 0, 'order' => 60 ),
			),
		);
	}

	/**
	 * Get the full settings array (stored values merged over defaults).
	 *
	 * @return array
	 */
	public function get(): array {
		if ( null === $this->cache ) {
			$stored      = get_option( self::OPTION, array() );
			$this->cache = is_array( $stored )
				? array_replace_recursive( $this->defaults(), $stored )
				: $this->defaults();
		}
		return $this->cache;
	}

	/**
	 * Get one settings group (e.g. 'swco_general').
	 *
	 * @param string $group Group key.
	 * @return array
	 */
	public function get_group( string $group ): array {
		$all = $this->get();
		return isset( $all[ $group ] ) && is_array( $all[ $group ] ) ? $all[ $group ] : array();
	}

	/**
	 * Get one value from a group.
	 *
	 * @param string $group   Group key.
	 * @param string $key     Field key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public function get_value( string $group, string $key, $default = '' ) {
		$values = $this->get_group( $group );
		return array_key_exists( $key, $values ) ? $values[ $key ] : $default;
	}

	/**
	 * String-only text cleaner: arrays/objects become '' instead of
	 * reaching WP string functions (which would fatal on arrays).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function clean_text( $value ): string {
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	/**
	 * Sanitize the whole option on save (Settings API callback).
	 *
	 * Hardening rule: every scalar input is type-checked first, so crafted
	 * arrays (e.g. `name[]=x`) can never reach string-only WP functions.
	 *
	 * @param array $input Raw submitted values.
	 * @return array Clean values.
	 */
	public function sanitize( $input ): array {
		$input  = is_array( $input ) ? $input : array();
		$output = $this->defaults();
		$output['version'] = SWCO_VERSION;

		$yes_no = array( 'yes', 'no' );

		// General.
		if ( isset( $input['swco_general'] ) && is_array( $input['swco_general'] ) ) {
			$g = $input['swco_general'];
			if ( isset( $g['enabled'] ) && in_array( $g['enabled'], $yes_no, true ) ) {
				$output['swco_general']['enabled'] = $g['enabled'];
			}
			if ( isset( $g['skip_cart'] ) && in_array( $g['skip_cart'], $yes_no, true ) ) {
				$output['swco_general']['skip_cart'] = $g['skip_cart'];
			}
			if ( isset( $g['auto_create_account'] ) && in_array( $g['auto_create_account'], $yes_no, true ) ) {
				$output['swco_general']['auto_create_account'] = $g['auto_create_account'];
			}
			$redirects = array( 'checkout', 'cart', 'custom_url' );
			if ( isset( $g['redirect_to'] ) && in_array( $g['redirect_to'], $redirects, true ) ) {
				$output['swco_general']['redirect_to'] = $g['redirect_to'];
			}
			if ( isset( $g['custom_url'] ) && is_string( $g['custom_url'] ) ) {
				$output['swco_general']['custom_url'] = esc_url_raw( $g['custom_url'] );
			}
			$replace = array( 'no', 'checkout', 'custom' );
			if ( isset( $g['replace_cart_url'] ) && in_array( $g['replace_cart_url'], $replace, true ) ) {
				$output['swco_general']['replace_cart_url'] = $g['replace_cart_url'];
			}
			if ( isset( $g['atc_text'] ) ) {
				$output['swco_general']['atc_text'] = $this->clean_text( $g['atc_text'] );
			}
		}

		// Checkout.
		if ( isset( $input['swco_checkout'] ) && is_array( $input['swco_checkout'] ) ) {
			$c = $input['swco_checkout'];
			if ( isset( $c['layout'] ) && in_array( $c['layout'], array( 'one-column', 'two-column', 'multi-step' ), true ) ) {
				$output['swco_checkout']['layout'] = $c['layout'];
			}
			if ( isset( $c['skin'] ) && in_array( $c['skin'], array( 'default', 'dhaka' ), true ) ) {
				$output['swco_checkout']['skin'] = $c['skin'];
			}
			foreach ( array( 'delivery_note', 'reviews_text', 'help_text' ) as $text_field ) {
				if ( isset( $c[ $text_field ] ) ) {
					$output['swco_checkout'][ $text_field ] = $this->clean_text( $c[ $text_field ] );
				}
			}
			foreach ( array( 'override_global', 'cart_in_checkout', 'cart_ajax', 'hide_order_notes', 'hide_terms', 'hide_shipping' ) as $flag ) {
				if ( isset( $c[ $flag ] ) && in_array( $c[ $flag ], $yes_no, true ) ) {
					$output['swco_checkout'][ $flag ] = $c[ $flag ];
				}
			}
			if ( isset( $c['hide_coupon'] ) && in_array( $c['hide_coupon'], array( 'show', 'hide', 'toggle' ), true ) ) {
				$output['swco_checkout']['hide_coupon'] = $c['hide_coupon'];
			}
			if ( isset( $c['cart_columns'] ) && is_array( $c['cart_columns'] ) ) {
				$allowed = array( 'remove', 'thumbnail', 'name', 'price', 'qty' );
				$cols    = array();
				foreach ( $c['cart_columns'] as $col ) {
					if ( ! is_scalar( $col ) ) {
						continue;
					}
					$col = sanitize_key( (string) $col );
					if ( in_array( $col, $allowed, true ) ) {
						$cols[] = $col;
					}
				}
				if ( ! empty( $cols ) ) {
					$output['swco_checkout']['cart_columns'] = array_values( array_unique( $cols ) );
				}
			}
		}

		// Fields (fixed rows: visible + required + order + custom label).
		if ( isset( $input['swco_fields'] ) && is_array( $input['swco_fields'] ) ) {
			foreach ( $output['swco_fields'] as $field_key => $row ) {
				if ( ! isset( $input['swco_fields'][ $field_key ] ) || ! is_array( $input['swco_fields'][ $field_key ] ) ) {
					continue;
				}
				$f = $input['swco_fields'][ $field_key ];
				if ( isset( $f['visible'] ) ) {
					$output['swco_fields'][ $field_key ]['visible'] = $f['visible'] ? 1 : 0;
				}
				if ( isset( $f['required'] ) ) {
					$output['swco_fields'][ $field_key ]['required'] = $f['required'] ? 1 : 0;
				}
				if ( isset( $f['order'] ) ) {
					$output['swco_fields'][ $field_key ]['order'] = absint( $f['order'] );
				}
				if ( isset( $f['label'] ) ) {
					$output['swco_fields'][ $field_key ]['label'] = substr( $this->clean_text( $f['label'] ), 0, 100 );
				}
			}
		}

		// Landing form fields (fixed 6: custom label + required + order;
		// visibility still follows the Landing tab show_* toggles).
		if ( isset( $input['swco_landing_fields'] ) && is_array( $input['swco_landing_fields'] ) ) {
			foreach ( $output['swco_landing_fields'] as $field_key => $row ) {
				if ( ! isset( $input['swco_landing_fields'][ $field_key ] ) || ! is_array( $input['swco_landing_fields'][ $field_key ] ) ) {
					continue;
				}
				$f = $input['swco_landing_fields'][ $field_key ];
				if ( isset( $f['required'] ) ) {
					$output['swco_landing_fields'][ $field_key ]['required'] = $f['required'] ? 1 : 0;
				}
				if ( isset( $f['order'] ) ) {
					$output['swco_landing_fields'][ $field_key ]['order'] = absint( $f['order'] );
				}
				if ( isset( $f['label'] ) ) {
					$output['swco_landing_fields'][ $field_key ]['label'] = substr( $this->clean_text( $f['label'] ), 0, 100 );
				}
			}
		}

		// Coupon.
		if ( isset( $input['swco_coupon'] ) && is_array( $input['swco_coupon'] ) ) {
			$p = $input['swco_coupon'];
			if ( isset( $p['auto_coupon'] ) ) {
				$output['swco_coupon']['auto_coupon'] = strtoupper( $this->clean_text( $p['auto_coupon'] ) );
			}
			if ( isset( $p['url_param'] ) && is_string( $p['url_param'] ) && '' !== $p['url_param'] ) {
				$output['swco_coupon']['url_param'] = sanitize_key( $p['url_param'] );
			}
			if ( isset( $p['min_subtotal'] ) && is_scalar( $p['min_subtotal'] ) ) {
				$output['swco_coupon']['min_subtotal'] = max( 0, round( (float) $p['min_subtotal'], 2 ) );
			}
		}

		// Bump (simple order bump — fixed product, fixed discount %, 2 positions).
		if ( isset( $input['swco_bump'] ) && is_array( $input['swco_bump'] ) ) {
			$b = $input['swco_bump'];
			if ( isset( $b['enabled'] ) && in_array( $b['enabled'], $yes_no, true ) ) {
				$output['swco_bump']['enabled'] = $b['enabled'];
			}
			if ( isset( $b['product_id'] ) ) {
				$output['swco_bump']['product_id'] = absint( $b['product_id'] );
			}
			if ( isset( $b['discount_pct'] ) ) {
				$output['swco_bump']['discount_pct'] = min( 90, max( 0, absint( $b['discount_pct'] ) ) );
			}
			if ( isset( $b['position'] ) && in_array( $b['position'], array( 'before-payment', 'after-summary' ), true ) ) {
				$output['swco_bump']['position'] = $b['position'];
			}
			if ( isset( $b['title'] ) ) {
				$output['swco_bump']['title'] = $this->clean_text( $b['title'] );
			}
			if ( isset( $b['desc'] ) ) {
				$output['swco_bump']['desc'] = $this->clean_text( $b['desc'] );
			}
		}

		// Maps (address autocomplete — owner key; empty = off).
		if ( isset( $input['swco_maps'] ) && is_array( $input['swco_maps'] ) ) {
			$m = $input['swco_maps'];
			if ( isset( $m['api_key'] ) ) {
				$output['swco_maps']['api_key'] = $this->clean_text( $m['api_key'] );
			}
		}

		// Opt-in (lead form copy).
		if ( isset( $input['swco_optin'] ) && is_array( $input['swco_optin'] ) ) {
			$o = $input['swco_optin'];
			if ( isset( $o['enabled'] ) && in_array( $o['enabled'], $yes_no, true ) ) {
				$output['swco_optin']['enabled'] = $o['enabled'];
			}
			foreach ( array( 'title', 'button_text', 'success_msg' ) as $field ) {
				if ( isset( $o[ $field ] ) ) {
					$output['swco_optin'][ $field ] = $this->clean_text( $o[ $field ] );
				}
			}
		}

		// Landing section display (global defaults; shortcode id/button still per-use).
		if ( isset( $input['swco_landing'] ) && is_array( $input['swco_landing'] ) ) {
			$l = $input['swco_landing'];
			if ( isset( $l['layout'] ) && in_array( $l['layout'], array( 'stacked', 'two-column' ), true ) ) {
				$output['swco_landing']['layout'] = $l['layout'];
			}
			foreach ( array( 'show_image', 'show_qty', 'show_email', 'show_note' ) as $flag ) {
				if ( isset( $l[ $flag ] ) && in_array( $l[ $flag ], $yes_no, true ) ) {
					$output['swco_landing'][ $flag ] = $l[ $flag ];
				}
			}
			if ( isset( $l['button_text'] ) ) {
				$output['swco_landing']['button_text'] = $this->clean_text( $l['button_text'] );
			}
		}

		$this->cache = $output;
		return $output;
	}
}
