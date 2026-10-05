<?php
/**
 * Fields tab table: the locked rows with Label / Visible / Required / Order.
 * No add-new — the row set is fixed by spec (plan §6.1).
 *
 * @package SwiftCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the field editor tables (checkout + landing).
 */
final class SwCo_Admin_Fields {

	/**
	 * Fallback names for the locked checkout rows (used when no custom
	 * label is stored yet).
	 *
	 * @return array Field key => label.
	 */
	public static function labels(): array {
		return array(
			'billing_first_name' => __( 'First name', 'swift-checkout-for-woocommerce' ),
			'billing_last_name'  => __( 'Last name', 'swift-checkout-for-woocommerce' ),
			'billing_phone'      => __( 'Phone', 'swift-checkout-for-woocommerce' ),
			'billing_country'    => __( 'Country', 'swift-checkout-for-woocommerce' ),
			'billing_address_1'  => __( 'Address', 'swift-checkout-for-woocommerce' ),
			'billing_city'       => __( 'City', 'swift-checkout-for-woocommerce' ),
			'billing_postcode'   => __( 'Postcode', 'swift-checkout-for-woocommerce' ),
			'billing_division'   => __( 'Division (BD)', 'swift-checkout-for-woocommerce' ),
			'billing_district'   => __( 'District (BD)', 'swift-checkout-for-woocommerce' ),
			'billing_thana'      => __( 'Thana / Area (BD)', 'swift-checkout-for-woocommerce' ),
			'billing_landmark'   => __( 'Landmark (BD)', 'swift-checkout-for-woocommerce' ),
			'billing_company'    => __( 'Company', 'swift-checkout-for-woocommerce' ),
			'billing_address_2'  => __( 'Address 2', 'swift-checkout-for-woocommerce' ),
			'order_comments'     => __( 'Order notes', 'swift-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Fallback names for the 6 landing form fields.
	 *
	 * @return array Field key => label.
	 */
	public static function landing_labels(): array {
		return array(
			'swco_name'    => __( 'Your Name', 'swift-checkout-for-woocommerce' ),
			'swco_mobile'  => __( 'Mobile Number', 'swift-checkout-for-woocommerce' ),
			'swco_address' => __( 'Address', 'swift-checkout-for-woocommerce' ),
			'swco_email'   => __( 'Email (optional)', 'swift-checkout-for-woocommerce' ),
			'swco_qty'     => __( 'Quantity', 'swift-checkout-for-woocommerce' ),
			'swco_note'    => __( 'Order Note (optional)', 'swift-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Print the checkout table (Label + Visible + Required + Order).
	 *
	 * @param array $rows Stored swco_fields rows.
	 */
	public static function render_table( array $rows ): void {
		$labels = self::labels();
		?>
		<h3><?php echo esc_html__( 'Checkout fields', 'swift-checkout-for-woocommerce' ); ?></h3>
		<p class="description"><?php echo esc_html__( 'Rename labels, hide, require, or reorder fields. Order = display priority (lower shows first).', 'swift-checkout-for-woocommerce' ); ?></p>
		<table class="widefat striped swco-fields-table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Field', 'swift-checkout-for-woocommerce' ); ?></th>
					<th><?php echo esc_html__( 'Label', 'swift-checkout-for-woocommerce' ); ?></th>
					<th><?php echo esc_html__( 'Visible', 'swift-checkout-for-woocommerce' ); ?></th>
					<th><?php echo esc_html__( 'Required', 'swift-checkout-for-woocommerce' ); ?></th>
					<th><?php echo esc_html__( 'Order', 'swift-checkout-for-woocommerce' ); ?></th>
					<th><span class="screen-reader-text"><?php echo esc_html__( 'Move', 'swift-checkout-for-woocommerce' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $labels as $key => $label ) : ?>
					<?php $row = isset( $rows[ $key ] ) && is_array( $rows[ $key ] ) ? $rows[ $key ] : array(); ?>
					<?php $custom = isset( $row['label'] ) && is_string( $row['label'] ) && '' !== $row['label'] ? $row['label'] : $label; ?>
					<?php
					/* translators: %s: field label */
					$aria_label = sprintf( __( 'Label for %s', 'swift-checkout-for-woocommerce' ), $label );
					/* translators: %s: field label */
					$aria_show = sprintf( __( 'Show %s', 'swift-checkout-for-woocommerce' ), $label );
					/* translators: %s: field label */
					$aria_require = sprintf( __( 'Require %s', 'swift-checkout-for-woocommerce' ), $label );
					?>
					<tr data-field="<?php echo esc_attr( $key ); ?>">
						<td><strong><?php echo esc_html( $label ); ?></strong><br /><code><?php echo esc_html( $key ); ?></code></td>
						<td><input type="text" class="regular-text" name="swco_settings[swco_fields][<?php echo esc_attr( $key ); ?>][label]" value="<?php echo esc_attr( $custom ); ?>" maxlength="100" aria-label="<?php echo esc_attr( $aria_label ); ?>" /></td>
						<td><input type="checkbox" name="swco_settings[swco_fields][<?php echo esc_attr( $key ); ?>][visible]" value="1" <?php checked( ! empty( $row['visible'] ) ); ?> aria-label="<?php echo esc_attr( $aria_show ); ?>" /></td>
						<td><input type="checkbox" name="swco_settings[swco_fields][<?php echo esc_attr( $key ); ?>][required]" value="1" <?php checked( ! empty( $row['required'] ) ); ?> aria-label="<?php echo esc_attr( $aria_require ); ?>" /></td>
						<td><input type="number" class="small-text swco-order-input" name="swco_settings[swco_fields][<?php echo esc_attr( $key ); ?>][order]" value="<?php echo esc_attr( (string) ( isset( $row['order'] ) ? absint( $row['order'] ) : 10 ) ); ?>" min="0" max="9999" step="10" /></td>
						<td>
							<button type="button" class="button button-small swco-move-up" aria-label="<?php echo esc_attr__( 'Move up', 'swift-checkout-for-woocommerce' ); ?>">↑</button>
							<button type="button" class="button button-small swco-move-down" aria-label="<?php echo esc_attr__( 'Move down', 'swift-checkout-for-woocommerce' ); ?>">↓</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<strong><?php echo esc_html__( 'Shortcode:', 'swift-checkout-for-woocommerce' ); ?></strong>
			<code>[swift_checkout]</code>
			<button type="button" class="button button-small swco-copy-btn" data-copy="[swift_checkout]"><?php echo esc_html__( 'Copy', 'swift-checkout-for-woocommerce' ); ?></button>
		</p>
		<?php
	}

	/**
	 * Print the landing form table (Label + Required + Order).
	 * Visibility stays on the Landing tab toggles (show_image/qty/email/note).
	 *
	 * @param array $rows Stored swco_landing_fields rows.
	 */
	public static function render_landing_table( array $rows ): void {
		$labels = self::landing_labels();
		?>
		<h3><?php echo esc_html__( 'Landing form fields', 'swift-checkout-for-woocommerce' ); ?></h3>
		<p class="description"><?php echo esc_html__( 'Rename labels, require, or reorder the landing form. Visibility follows the toggles above.', 'swift-checkout-for-woocommerce' ); ?></p>
		<table class="widefat striped swco-fields-table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Field', 'swift-checkout-for-woocommerce' ); ?></th>
					<th><?php echo esc_html__( 'Label', 'swift-checkout-for-woocommerce' ); ?></th>
					<th><?php echo esc_html__( 'Required', 'swift-checkout-for-woocommerce' ); ?></th>
					<th><?php echo esc_html__( 'Order', 'swift-checkout-for-woocommerce' ); ?></th>
					<th><span class="screen-reader-text"><?php echo esc_html__( 'Move', 'swift-checkout-for-woocommerce' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $labels as $key => $label ) : ?>
					<?php $row = isset( $rows[ $key ] ) && is_array( $rows[ $key ] ) ? $rows[ $key ] : array(); ?>
					<?php $custom = isset( $row['label'] ) && is_string( $row['label'] ) && '' !== $row['label'] ? $row['label'] : $label; ?>
					<?php
					/* translators: %s: field label */
					$aria_label = sprintf( __( 'Label for %s', 'swift-checkout-for-woocommerce' ), $label );
					/* translators: %s: field label */
					$aria_require = sprintf( __( 'Require %s', 'swift-checkout-for-woocommerce' ), $label );
					?>
					<tr data-field="<?php echo esc_attr( $key ); ?>">
						<td><strong><?php echo esc_html( $label ); ?></strong><br /><code><?php echo esc_html( $key ); ?></code></td>
						<td><input type="text" class="regular-text" name="swco_settings[swco_landing_fields][<?php echo esc_attr( $key ); ?>][label]" value="<?php echo esc_attr( $custom ); ?>" maxlength="100" aria-label="<?php echo esc_attr( $aria_label ); ?>" /></td>
						<td><input type="checkbox" name="swco_settings[swco_landing_fields][<?php echo esc_attr( $key ); ?>][required]" value="1" <?php checked( ! empty( $row['required'] ) ); ?> aria-label="<?php echo esc_attr( $aria_require ); ?>" /></td>
						<td><input type="number" class="small-text swco-order-input" name="swco_settings[swco_landing_fields][<?php echo esc_attr( $key ); ?>][order]" value="<?php echo esc_attr( (string) ( isset( $row['order'] ) ? absint( $row['order'] ) : 10 ) ); ?>" min="0" max="9999" step="10" /></td>
						<td>
							<button type="button" class="button button-small swco-move-up" aria-label="<?php echo esc_attr__( 'Move up', 'swift-checkout-for-woocommerce' ); ?>">↑</button>
							<button type="button" class="button button-small swco-move-down" aria-label="<?php echo esc_attr__( 'Move down', 'swift-checkout-for-woocommerce' ); ?>">↓</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<strong><?php echo esc_html__( 'Shortcode:', 'swift-checkout-for-woocommerce' ); ?></strong>
			<code>[swift_landing id="123" button="Order Now"]</code>
			<button type="button" class="button button-small swco-copy-btn" data-copy='[swift_landing id="123" button="Order Now"]'><?php echo esc_html__( 'Copy', 'swift-checkout-for-woocommerce' ); ?></button>
			<span class="description"><?php echo esc_html__( 'Replace 123 with the product ID.', 'swift-checkout-for-woocommerce' ); ?></span>
		</p>
		<?php
	}
}
