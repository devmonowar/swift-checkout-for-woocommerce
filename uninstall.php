<?php
/**
 * Cleanup on plugin uninstall (plugin deleted via wp-admin).
 *
 * Spec: plan §8.2. No custom table/role/cron exists, so only
 * the single option, product meta, and transients are removed.
 *
 * @package SwiftCheckout
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Single settings option (+ template-apply backup).
delete_option( 'swco_settings' );
delete_option( 'swco_settings_backup' );

// Per-product overrides.
delete_post_meta_by_key( '_swco_skip_cart' );
delete_post_meta_by_key( '_swco_quick_buy' );

// Stored leads (private CPT) — remove so nothing orphaned stays behind.
$swco_lead_ids = get_posts(
	array(
		'post_type'   => 'swco_lead',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);
foreach ( $swco_lead_ids as $swco_lead_id ) {
	wp_delete_post( $swco_lead_id, true );
}

// Funnels + their page map (private CPT, same treatment).
$swco_funnel_ids = get_posts(
	array(
		'post_type'   => 'swco_funnel',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);
foreach ( $swco_funnel_ids as $swco_funnel_id ) {
	wp_delete_post( $swco_funnel_id, true );
}
delete_option( 'swco_funnel_pages' );
delete_post_meta_by_key( '_swco_steps' );

// Transients (prefixed swco_), if any were set.
global $wpdb;
$transients = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_swco_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_swco_' ) . '%'
	)
);
foreach ( $transients as $transient ) {
	delete_option( $transient );
}
