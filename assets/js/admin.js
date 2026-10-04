/**
 * Swift Checkout — admin behaviour: copy shortcode + field row up/down.
 * Vanilla JS, no dependencies.
 */
/* global swco_admin_params */
(function () {
	'use strict';

	function toast(btn, text) {
		var original = btn.textContent;
		btn.textContent = text;
		window.setTimeout(function () {
			btn.textContent = original;
		}, 1200);
	}

	// Copy shortcode buttons.
	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('.swco-copy-btn') : null;
		if (!btn) {
			return;
		}
		var text = btn.getAttribute('data-copy') || '';
		var done = function () {
			var label = (typeof swco_admin_params !== 'undefined' && swco_admin_params.copied) || 'Copied!';
			toast(btn, label);
		};
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done, done);
		} else {
			var input = document.createElement('textarea');
			input.value = text;
			document.body.appendChild(input);
			input.select();
			try {
				document.execCommand('copy');
			} catch (err) {
				// Clipboard unavailable — the readonly input is still selectable.
			}
			document.body.removeChild(input);
			done();
		}
	});

	// Move field rows up/down (swaps rows AND their order values).
	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('.swco-move-up, .swco-move-down') : null;
		if (!btn) {
			return;
		}
		var row = btn.closest('tr');
		if (!row || !row.parentNode) {
			return;
		}
		var sibling = btn.classList.contains('swco-move-up') ? row.previousElementSibling : row.nextElementSibling;
		if (!sibling) {
			return;
		}
		var rowOrder = row.querySelector('.swco-order-input');
		var sibOrder = sibling.querySelector('.swco-order-input');
		if (rowOrder && sibOrder) {
			var tmp = rowOrder.value;
			rowOrder.value = sibOrder.value;
			sibOrder.value = tmp;
		}
		if (btn.classList.contains('swco-move-up')) {
			row.parentNode.insertBefore(row, sibling);
		} else {
			row.parentNode.insertBefore(sibling, row);
		}
	});
})();
