<?php
/**
 * Design templates: JSON presets in templates/presets/*.json.
 *
 * Each preset is a settings snapshot (layout + skin + fields + extras).
 * Applying backs up the current settings first, validates through
 * SwCo_Settings::sanitize(), and offers one-click restore.
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lists, applies, and restores design presets.
 */
final class SwCo_Templates {

	/**
	 * Backup option name.
	 */
	const BACKUP_OPTION = 'swco_settings_backup';

	/**
	 * Preset directory.
	 *
	 * @return string
	 */
	public static function dir(): string {
		return SWCO_DIR . 'templates/presets';
	}

	/**
	 * All valid presets, keyed by file slug.
	 *
	 * @return array Slug => ['name' => string, 'description' => string, ...].
	 */
	public static function list(): array {
		$out   = array();
		$files = glob( self::dir() . '/*.json' );
		if ( ! is_array( $files ) ) {
			return $out;
		}
		foreach ( $files as $file ) {
			$slug = basename( $file, '.json' );
			if ( '' === $slug || ! preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
				continue;
			}
			$data = json_decode( file_get_contents( $file ), true );
			if ( ! is_array( $data ) || empty( $data['name'] ) || ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
				continue;
			}
			$settings = $data['settings'];
			$landing  = isset( $settings['swco_landing']['layout'] ) && is_string( $settings['swco_landing']['layout'] ) ? $settings['swco_landing']['layout'] : '';
			$checkout = isset( $settings['swco_checkout']['layout'] ) && is_string( $settings['swco_checkout']['layout'] ) ? $settings['swco_checkout']['layout'] : '';
			$skin     = isset( $settings['swco_checkout']['skin'] ) && is_string( $settings['swco_checkout']['skin'] ) ? $settings['swco_checkout']['skin'] : '';
			$out[ $slug ] = array(
				'name'        => sanitize_text_field( $data['name'] ),
				'description' => isset( $data['description'] ) && is_string( $data['description'] ) ? sanitize_text_field( $data['description'] ) : '',
				'landing'     => in_array( $landing, array( 'stacked', 'two-column' ), true ) ? $landing : '',
				'checkout'    => in_array( $checkout, array( 'one-column', 'two-column', 'multi-step' ), true ) ? $checkout : '',
				'skin'        => in_array( $skin, array( 'default', 'dhaka' ), true ) ? $skin : '',
				'preview'     => self::preview_url( $slug ),
			);
		}
		return $out;
	}

	/**
	 * Preview screenshot URL when the author dropped one in
	 * templates/presets/previews/<slug>.png (800x600 recommended).
	 *
	 * @param string $slug Preset slug.
	 * @return string Empty when no screenshot exists.
	 */
	public static function preview_url( string $slug ): string {
		if ( '' === $slug || ! preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
			return '';
		}
		if ( ! file_exists( self::dir() . '/previews/' . $slug . '.png' ) ) {
			return '';
		}
		return SWCO_URL . 'templates/presets/previews/' . $slug . '.png';
	}

	/**
	 * Read one preset's settings.
	 *
	 * @param string $slug Preset slug.
	 * @return array Empty when invalid.
	 */
	public static function read( string $slug ): array {
		if ( '' === $slug || ! preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
			return array();
		}
		$file = self::dir() . '/' . $slug . '.json';
		if ( ! file_exists( $file ) ) {
			return array();
		}
		$data = json_decode( file_get_contents( $file ), true );
		if ( ! is_array( $data ) || ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			return array();
		}
		return $data['settings'];
	}

	/**
	 * Apply a preset: backup current, merge, sanitize, save.
	 *
	 * @param string $slug Preset slug.
	 * @return bool
	 */
	public static function apply( string $slug ): bool {
		$preset = self::read( $slug );
		if ( empty( $preset ) ) {
			return false;
		}
		$settings = SwCo_Settings::instance();
		update_option( self::BACKUP_OPTION, $settings->get() );
		$merged = array_replace_recursive( $settings->get(), $preset );
		update_option( SwCo_Settings::OPTION, $settings->sanitize( $merged ) );
		return true;
	}

	/**
	 * Restore the pre-apply backup.
	 *
	 * @return bool False when no backup exists.
	 */
	public static function restore(): bool {
		$backup = get_option( self::BACKUP_OPTION, false );
		if ( ! is_array( $backup ) || empty( $backup ) ) {
			return false;
		}
		update_option( SwCo_Settings::OPTION, SwCo_Settings::instance()->sanitize( $backup ) );
		delete_option( self::BACKUP_OPTION );
		return true;
	}

	/**
	 * Is there a backup to restore?
	 *
	 * @return bool
	 */
	public static function has_backup(): bool {
		$backup = get_option( self::BACKUP_OPTION, false );
		return is_array( $backup ) && ! empty( $backup );
	}
}
