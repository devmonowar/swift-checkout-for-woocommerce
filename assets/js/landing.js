/**
 * Swift Checkout — landing order form: live line total as qty changes.
 * Progressive enhancement only; the form submits normally without JS.
 */
(function () {
	'use strict';

	document.addEventListener('input', function (e) {
		var qty = e.target && e.target.id === 'swco-landing-qty' ? e.target : null;
		if (!qty) {
			return;
		}
		var wrap = qty.closest ? qty.closest('.swco-landing') : null;
		if (!wrap) {
			return;
		}
		var priceEl = wrap.querySelector('.swco-landing-price');
		var totalEl = wrap.querySelector('.swco-landing-total');
		if (!priceEl || !totalEl) {
			return;
		}
		var unit = parseFloat(priceEl.getAttribute('data-unit-price')) || 0;
		var qtyNum = parseInt(qty.value, 10) || 1;
		if (qtyNum < 1) {
			qtyNum = 1;
		}
		if (qtyNum > 99) {
			qtyNum = 99;
		}
		try {
			totalEl.textContent = (unit * qtyNum).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
		} catch (err) {
			totalEl.textContent = String(unit * qtyNum);
		}
	});
})();
