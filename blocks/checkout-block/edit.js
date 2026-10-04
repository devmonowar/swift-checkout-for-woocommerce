/**
 * Swift Checkout block — editor placeholder (no build step).
 * The live checkout renders on the frontend via render.php.
 */
/* global wp */
(function (blocks, element, blockEditor, components, i18n) {
	'use strict';

	if (!blocks || !element) {
		return;
	}

	var el = element.createElement;
	var InspectorControls = blockEditor ? blockEditor.InspectorControls : null;
	var PanelBody = components ? components.PanelBody : null;
	var TextControl = components ? components.TextControl : null;

	blocks.registerBlockType('swco/checkout', {
		edit: function (props) {
			var inspector = null;

			if (InspectorControls && PanelBody && TextControl) {
				inspector = el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: i18n.__('Settings', 'swift-checkout-for-woocommerce') },
						el(TextControl, {
							label: i18n.__('Extra CSS class', 'swift-checkout-for-woocommerce'),
							value: props.attributes.extraClass || '',
							onChange: function (value) {
								props.setAttributes({ extraClass: value });
							}
						})
					)
				);
			}

			return el(
				'div',
				null,
				inspector,
				el(
					'div',
					{ className: 'swco-block-placeholder' },
					el('strong', null, i18n.__('Swift Checkout', 'swift-checkout-for-woocommerce')),
					el('p', null, i18n.__('The checkout form renders here on the frontend.', 'swift-checkout-for-woocommerce'))
				)
			);
		},

		// Server-side render only; nothing is saved.
		save: function () {
			return null;
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n
);
