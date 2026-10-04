/**
 * Swift Landing block — editor fields inline in the block (no sidebar).
 * Product picker is a dropdown (list localized from the server); falls
 * back to a Product ID field when the list is unavailable.
 * The live section renders on the frontend via render.php.
 */
/* global wp */
(function (blocks, element, components, i18n) {
	'use strict';

	if (!blocks || !element) {
		return;
	}

	var el = element.createElement;
	var TextControl = components ? components.TextControl : null;
	var SelectControl = components ? components.SelectControl : null;

	function productField(productId, onChange) {
		var data = (typeof swcoLandingData !== 'undefined' && swcoLandingData.products) || null;

		if (SelectControl && data && data.length) {
			var options = [{ value: 0, label: swcoLandingData.placeholder || '— Select product —' }];
			data.forEach(function (p) {
				options.push({ value: p.id, label: p.name });
			});
			return el(SelectControl, {
				label: i18n.__('Product', 'swift-checkout-for-woocommerce'),
				value: productId || 0,
				options: options,
				onChange: function (value) {
					onChange(parseInt(value, 10) || 0);
				}
			});
		}

		if (!TextControl) {
			return el('p', null, i18n.__('Product ID', 'swift-checkout-for-woocommerce') + ': ' + (productId || ''));
		}
		return el(TextControl, {
			label: i18n.__('Product ID', 'swift-checkout-for-woocommerce'),
			type: 'number',
			value: productId || '',
			onChange: function (value) {
				onChange(parseInt(value, 10) || 0);
			}
		});
	}

	blocks.registerBlockType('swco/landing', {
		edit: function (props) {
			return el(
				'div',
				{ className: 'swco-block-placeholder' },
				el('strong', null, i18n.__('Swift Landing Order', 'swift-checkout-for-woocommerce')),
				productField(props.attributes.productId, function (id) {
					props.setAttributes({ productId: id });
				}),
				TextControl
					? el(TextControl, {
						label: i18n.__('Button text', 'swift-checkout-for-woocommerce'),
						value: props.attributes.buttonText || '',
						onChange: function (value) {
							props.setAttributes({ buttonText: value });
						}
					})
					: el('p', null, i18n.__('Button text', 'swift-checkout-for-woocommerce') + ': ' + (props.attributes.buttonText || '')),
				el('p', { className: 'description' }, i18n.__('Product + order form renders here on the frontend.', 'swift-checkout-for-woocommerce'))
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
	window.wp.components,
	window.wp.i18n
);
