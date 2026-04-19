/**
 * CCLEE Shipping - Async address validation on checkout.
 */
(function () {
	'use strict';

	var debounceTimer = null;
	var validating = false;

	function getFormData() {
		var fields = ['address_1', 'city', 'state', 'postcode', 'country'];
		var data = {};

		var shipToDiff = document.querySelector('#ship-to-different-address input[type="checkbox"]');
		var prefix = (shipToDiff && shipToDiff.checked) ? 'shipping_' : 'billing_';

		fields.forEach(function (field) {
			var el = document.querySelector('#' + prefix + field);
			data[field] = el ? el.value : '';
		});

		data.nonce = ccleeShipping.nonce;
		data.action = 'cclee_shipping_validate_address';
		return data;
	}

	function ensureContainer() {
		var container = document.querySelector('.cclee-address-validation');
		if (container) return container;

		container = document.createElement('div');
		container.className = 'cclee-address-validation';

		var addressField = document.querySelector('#shipping_address_1, #billing_address_1');
		if (addressField && addressField.parentNode && addressField.parentNode.parentNode) {
			addressField.parentNode.parentNode.appendChild(container);
		}
		return container;
	}

	function clearContainer(container) {
		while (container.firstChild) {
			container.removeChild(container.firstChild);
		}
	}

	function showNotice(valid, message) {
		var container = ensureContainer();
		clearContainer(container);

		if (valid || !message) {
			container.style.display = 'none';
			return;
		}

		var notice = document.createElement('span');
		notice.className = 'cclee-av-notice';
		notice.textContent = message;
		container.appendChild(notice);
		container.style.display = 'block';
	}

	function validateAddress() {
		if (validating) return;

		var data = getFormData();
		if (!data.country) return;

		validating = true;

		fetch(ccleeShipping.ajax_url, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams(data).toString(),
		})
			.then(function (res) { return res.json(); })
			.then(function (res) {
				if (res.success && res.data) {
					showNotice(res.data.valid, res.data.message || '');
				}
			})
			.catch(function () { /* non-blocking */ })
			.finally(function () { validating = false; });
	}

	function onAddressChange() {
		clearTimeout(debounceTimer);
		debounceTimer = setTimeout(validateAddress, 1500);
	}

	if (document.querySelector('form.checkout')) {
		var fields = [
			'billing_address_1', 'billing_city', 'billing_postcode', 'billing_country',
			'shipping_address_1', 'shipping_city', 'shipping_postcode', 'shipping_country'
		];
		fields.forEach(function (id) {
			var el = document.querySelector('#' + id);
			if (el) {
				el.addEventListener('change', onAddressChange);
				el.addEventListener('input', onAddressChange);
			}
		});
	}
})();
