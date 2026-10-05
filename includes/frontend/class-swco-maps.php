<?php
/**
 * Address Autocomplete via Google Places (New web service, no extra SDK).
 *
 * The store owner pastes their own API key once in settings; shoppers do
 * nothing — they just type and pick a suggestion. Empty key = feature
 * fully off (no Google request leaves the shop).
 *
 * Spec: plan §12.2 (v1.0.0-complete).
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads the Places library on checkout and wires the address fields.
 */
final class SwCo_Maps {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Stored API key (empty = off).
	 *
	 * @return string
	 */
	public static function api_key(): string {
		$settings = SwCo_Settings::instance();
		if ( 'yes' !== $settings->get_value( 'swco_general', 'enabled', 'yes' ) ) {
			return '';
		}
		return trim( (string) $settings->get_value( 'swco_maps', 'api_key', '' ) );
	}

	/**
	 * Load the Places library + init script on checkout pages only.
	 */
	public static function enqueue(): void {
		$key = self::api_key();
		if ( '' === $key ) {
			return;
		}
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		wp_register_script(
			'swco-maps',
			'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode( $key ) . '&libraries=places&loading=async&callback=swcoMapsInit',
			array(),
			SWCO_VERSION,
			true
		);
		wp_add_inline_script( 'swco-maps', self::init_js(), 'before' );
		wp_enqueue_script( 'swco-maps' );
	}

	/**
	 * Inline init: autocomplete on the billing street field, fill
	 * street / city / postcode / country on select. Silent when the
	 * library failed to load (bad key) or fields are hidden.
	 *
	 * @return string JS code.
	 */
	public static function init_js(): string {
		return <<<'JS'
window.swcoMapsInit = function () {
	try {
		if (!window.google || !google.maps || !google.maps.places) { return; }
		var street = document.getElementById('billing_address_1');
		if (!street || street.disabled || street.offsetParent === null) { return; }
		var auto = new google.maps.places.Autocomplete(street, {
			types: ['address'],
			fields: ['address_components']
		});
		auto.addListener('place_changed', function () {
			var place = auto.getPlace();
			if (!place || !place.address_components) { return; }
			var parts = { street: '', city: '', postcode: '', country: '' };
			place.address_components.forEach(function (c) {
				var t = c.types || [];
				if (t.indexOf('street_number') !== -1) { parts.street = c.long_name + ' ' + parts.street; }
				if (t.indexOf('route') !== -1) { parts.street += c.long_name; }
				if (t.indexOf('locality') !== -1) { parts.city = c.long_name; }
				if (t.indexOf('postal_code') !== -1) { parts.postcode = c.long_name; }
				if (t.indexOf('country') !== -1) { parts.country = c.short_name; }
			});
			parts.street = parts.street.trim();
			setVal('billing_address_1', parts.street);
			setVal('billing_city', parts.city);
			setVal('billing_postcode', parts.postcode);
			var country = document.getElementById('billing_country');
			if (country && parts.country) {
				country.value = parts.country;
				country.dispatchEvent(new Event('change', { bubbles: true }));
				if (window.jQuery) { jQuery(document.body).trigger('update_checkout'); }
			}
		});
		function setVal(id, val) {
			if (!val) { return; }
			var el = document.getElementById(id);
			if (el) {
				el.value = val;
				el.dispatchEvent(new Event('change', { bubbles: true }));
			}
		}
	} catch (e) { /* Autocomplete is a bonus — checkout works without it. */ }
};
JS;
	}
}
