/**
 * AI Assistance settings.
 *
 * Every save goes through msh.saveOption(), the same inline-save path as the
 * rest of the Settings page. The only thing this file adds is the behaviour a
 * credential needs: the key is sent once, the server answers with a masked
 * form, and the raw key is cleared from the page immediately.
 *
 * @package MySiteHand
 */

(function () {
	'use strict';

	var card = document.getElementById('msh-set-ai');

	if (!card || !window.msh || !window.msh.saveOption) {
		return;
	}

	var wpI18n = (window.wp && window.wp.i18n) || {};

	var __ = wpI18n.__ || function (text) {
		return text;
	};

	var admin = window.mysitehandAdmin || {};

	var el = {
		provider: document.getElementById('msh-ai-provider'),
		model: document.getElementById('msh-ai-model'),
		stored: document.getElementById('msh-ai-key-stored'),
		mask: document.getElementById('msh-ai-key-mask'),
		entry: document.getElementById('msh-ai-key-entry'),
		key: document.getElementById('msh-ai-key'),
		replace: document.getElementById('msh-ai-key-replace'),
		remove: document.getElementById('msh-ai-key-remove'),
		test: document.getElementById('msh-ai-test'),
		testResult: document.getElementById('msh-ai-test-result')
	};

	var models = {};

	try {
		models = JSON.parse(card.getAttribute('data-models') || '{}');
	} catch (e) {
		models = {};
	}

	function showStored(masked) {
		el.mask.textContent = masked;
		el.stored.hidden = false;
		el.entry.hidden = true;
	}

	function showEntry() {
		el.stored.hidden = true;
		el.entry.hidden = false;
		el.key.value = '';
		el.key.focus();
	}

	function fillModels(providerId) {
		var list = models[providerId] || {};

		el.model.innerHTML = '';

		Object.keys(list).forEach(function (id) {
			var option = document.createElement('option');
			option.value = id;
			option.textContent = list[id];
			el.model.appendChild(option);
		});

		el.model.disabled = !providerId;
	}

	el.provider.addEventListener('change', function () {
		var providerId = el.provider.value;

		fillModels(providerId);

		// The provider has to be stored before the model, because the server
		// only accepts a model the stored provider actually offers.
		window.msh.saveOption('mysitehand_ai_provider', providerId).then(function (data) {
			if (data && providerId && el.model.value) {
				window.msh.saveOption('mysitehand_ai_model', el.model.value);
			}
		});
	});

	el.model.addEventListener('change', function () {
		window.msh.saveOption('mysitehand_ai_model', el.model.value);
	});

	function saveKey() {
		var value = el.key.value.trim();

		if ('' === value) {
			return;
		}

		// Cleared before the request even returns: the raw key should spend
		// as little time in the page as possible.
		el.key.value = '';

		window.msh.saveOption('mysitehand_ai_api_key', value).then(function (data) {
			if (data && data.masked) {
				showStored(data.masked);
			}
		});
	}

	el.key.addEventListener('blur', saveKey);
	el.key.addEventListener('keydown', function (event) {
		if (event.key === 'Enter') {
			event.preventDefault();
			saveKey();
		}
	});

	el.replace.addEventListener('click', showEntry);

	el.remove.addEventListener('click', function () {
		if (!window.confirm(__('Remove the stored API key? AI bulk fixing will disappear until you add one again.', 'my-site-hand'))) {
			return;
		}

		window.msh.saveOption('mysitehand_ai_api_key', '').then(function (data) {
			if (data) {
				el.mask.textContent = '';
				showEntry();
			}
		});
	});

	el.test.addEventListener('click', function () {
		el.test.disabled = true;
		el.testResult.className = 'msh-ai-test-result';
		el.testResult.textContent = __('Testing…', 'my-site-hand');

		fetch(admin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({
				action: 'my_site_hand_ai_test_connection',
				nonce: admin.nonce
			})
		}).then(function (response) {
			return response.json();
		}).then(function (data) {
			var ok = data && data.success;

			el.testResult.className = 'msh-ai-test-result msh-ai-test-result--' + (ok ? 'ok' : 'error');
			el.testResult.textContent = (data && data.data && data.data.message) ||
				__('The test could not be completed. Reload the page and try again.', 'my-site-hand');
		}).catch(function () {
			el.testResult.className = 'msh-ai-test-result msh-ai-test-result--error';
			el.testResult.textContent = __('The test could not be completed. Reload the page and try again.', 'my-site-hand');
		}).then(function () {
			el.test.disabled = false;
		});
	});
})();
