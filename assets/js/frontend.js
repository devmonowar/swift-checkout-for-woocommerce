/**
 * Swift Checkout — frontend behaviour.
 *
 * Qty +/- and remove go through admin-ajax.php (swco_update_qty /
 * swco_remove_item); the fresh .swco-cart HTML comes back in the response
 * and WooCommerce's own `update_checkout` refreshes totals + payment.
 */
/* global jQuery, swco_params */
(function ($) {
	'use strict';

	if (typeof swco_params === 'undefined') {
		return;
	}

	function refreshFragments(payload) {
		if (payload.fragments && payload.fragments['div.swco-cart']) {
			$('div.swco-cart').html(payload.fragments['div.swco-cart']);
		}
		syncStickyTotal();
		$(document.body).trigger('update_checkout');
	}

	// Keep the sticky bar total in sync with the cart table.
	function syncStickyTotal() {
		var total = $('div.swco-cart .swco-total td').first().html();
		if (total) {
			$('.swco-sticky-total').html(total);
		}
	}

	syncStickyTotal();

	function sendRequest(action, data) {
		var $cart = $('div.swco-cart').first();
		$cart.addClass('swco-loading');

		$.post(
			swco_params.ajax_url,
			$.extend(
				{
					action: action,
					_ajax_nonce: swco_params.nonce
				},
				data
			)
		)
			.done(function (res) {
				if (!res || !res.success) {
					$(document.body).trigger('swco_ajax_failed', [action, res]);
					return;
				}
				if (res.data && res.data.empty && res.data.redirect) {
					window.location.href = res.data.redirect;
					return;
				}
				refreshFragments(res.data || {});
			})
			.always(function () {
				$cart.removeClass('swco-loading');
			});
	}

	$(document.body).on('click', '.swco-qty-btn', function () {
		var $btn = $(this);
		sendRequest('swco_update_qty', {
			cart_key: $btn.data('cart-key'),
			qty: $btn.data('qty')
		});
	});

	$(document.body).on('click', '.swco-remove', function () {
		sendRequest('swco_remove_item', {
			cart_key: $(this).data('cart-key')
		});
	});

	// Applied coupon remove (× next to the coupon row).
	$(document.body).on('click', '.swco-coupon-remove', function () {
		sendRequest('swco_remove_coupon', {
			coupon: $(this).data('coupon')
		});
	});

	// Order bump checkbox: add on check, remove on uncheck.
	// Reverts the tick when the server says no (stays truthful).
	$(document.body).on('change', '.swco-bump-check', function () {
		var $box = $(this);
		var wasChecked = $box.is(':checked');
		sendRequest('swco_toggle_bump', {
			product_id: $box.data('product-id'),
			checked: wasChecked ? 1 : 0
		});
		$(document.body).one('swco_ajax_failed', function () {
			$box.prop('checked', !wasChecked);
		});
	});

	// Shipping method changed by WooCommerce core (update_checkout runs);
	// refresh our fragment once it finishes — without re-triggering.
	var swcoShippingChanged = false;

	$(document.body).on('change', 'input[name^="shipping_method"]', function () {
		swcoShippingChanged = true;
	});

	$(document.body).on('updated_checkout', function () {
		if (!swcoShippingChanged) {
			return;
		}
		swcoShippingChanged = false;
		var $cart = $('div.swco-cart').first();
		if (!$cart.length) {
			return;
		}
		$.post(swco_params.ajax_url, {
			action: 'swco_cart_fragment',
			_ajax_nonce: swco_params.nonce
		}).done(function (res) {
			if (res && res.success && res.data && res.data.fragments && res.data.fragments['div.swco-cart']) {
				$cart.html(res.data.fragments['div.swco-cart']);
				syncStickyTotal();
			}
		});
	});
	// Sticky bar: advance validated step 1, otherwise press the real
	// Place Order button (same validation + submit path).
	$(document.body).on('click', '.swco-sticky-btn', function () {
		var $wrap = $('.swco-checkout').first();
		if ($wrap.hasClass('swco-multi') && $wrap.find('.swco-pane-1').hasClass('swco-active')) {
			$wrap.find('.swco-next').click();
			return;
		}
		var place = document.getElementById('place_order');
		if (place) {
			place.click();
		} else {
			var payment = document.querySelector('.swco-payment');
			if (payment && payment.scrollIntoView) {
				payment.scrollIntoView();
			}
		}
	});
	// Multi-step layout: Details <-> Payment with per-step validation.
	(function initSteps() {
		var $wrap = $('.swco-checkout.swco-multi').first();
		if (!$wrap.length) {
			return;
		}
		$wrap.addClass('swco-js');
		goTo($wrap, 1, false);

		$wrap.on('click', '.swco-next', function () {
			var $pane = $wrap.find('.swco-pane-1');
			var invalid = $pane[0].querySelector('input:invalid, select:invalid, textarea:invalid');
			if (invalid) {
				if (invalid.reportValidity) {
					invalid.reportValidity();
				} else {
					invalid.focus();
				}
				return;
			}
			goTo($wrap, 2);
		});

		$wrap.on('click', '.swco-back', function () {
			goTo($wrap, 1);
		});
	})();

	function goTo($wrap, step, scroll) {
		$wrap.find('.swco-pane').removeClass('swco-active');
		$wrap.find('.swco-pane-' + step).addClass('swco-active');
		$wrap.find('.swco-step').removeAttr('aria-current');
		$wrap.find('.swco-step-' + step).attr('aria-current', 'step');
		if (scroll !== false && $wrap.offset()) {
			$('html, body').scrollTop($wrap.offset().top - 20);
		}
	}
})(jQuery);
