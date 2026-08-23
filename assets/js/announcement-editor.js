/**
 * Announcement editor: insert merge tags and product picker.
 */
(function () {
	'use strict';

	function getEditor() {
		if (window.tinymce && window.tinymce.get('content')) {
			return window.tinymce.get('content');
		}
		return null;
	}

	function insertAtCursor(text) {
		var editor = getEditor();
		if (editor && !editor.isHidden()) {
			editor.focus();
			editor.execCommand('mceInsertContent', false, text);
			return;
		}
		var textarea = document.getElementById('content');
		if (!textarea) {
			return;
		}
		var start = textarea.selectionStart || 0;
		var end = textarea.selectionEnd || 0;
		var value = textarea.value;
		textarea.value = value.slice(0, start) + text + value.slice(end);
		textarea.focus();
		var pos = start + text.length;
		textarea.setSelectionRange(pos, pos);
		textarea.dispatchEvent(new Event('input', { bubbles: true }));
	}

	function syncTokenButtons() {
		var sourceInput = document.querySelector('input[name="usa_source"]:checked');
		var source = sourceInput ? sourceInput.value : 'manual';
		var isFs = source === 'woocommerce_free_shipping';
		document.querySelectorAll('.usa-insert-token').forEach(function (btn) {
			var required = btn.getAttribute('data-requires-source');
			btn.hidden = !!(required && required !== source);
		});
		var diag = document.getElementById('usa_announcement_provider_diag');
		if (diag) {
			diag.style.display = isFs ? '' : 'none';
		}
	}

	function searchProducts(q) {
		var results = document.getElementById('usa-product-results');
		if (!results || !window.usaAnnouncementEditor) {
			return;
		}
		results.innerHTML = '<li>' + (usaAnnouncementEditor.i18n.searching || '') + '</li>';
		var url =
			usaAnnouncementEditor.ajaxUrl +
			'?action=usa_search_products&nonce=' +
			encodeURIComponent(usaAnnouncementEditor.nonce) +
			'&q=' +
			encodeURIComponent(q);
		fetch(url, { credentials: 'same-origin' })
			.then(function (r) {
				return r.json();
			})
			.then(function (payload) {
				results.innerHTML = '';
				var products =
					payload && payload.success && payload.data && payload.data.products
						? payload.data.products
						: [];
				if (!products.length) {
					results.innerHTML =
						'<li>' + (usaAnnouncementEditor.i18n.noResults || '') + '</li>';
					return;
				}
				products.forEach(function (p) {
					var li = document.createElement('li');
					var btn = document.createElement('button');
					btn.type = 'button';
					btn.className = 'button-link';
					btn.textContent = p.title + ' (#' + p.id + ')';
					btn.addEventListener('click', function () {
						insertAtCursor('{{product:' + p.id + '}}');
						document.getElementById('usa-product-picker').hidden = true;
					});
					li.appendChild(btn);
					results.appendChild(li);
				});
			})
			.catch(function () {
				results.innerHTML =
					'<li>' + (usaAnnouncementEditor.i18n.noResults || '') + '</li>';
			});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('input[name="usa_source"]').forEach(function (el) {
			el.addEventListener('change', syncTokenButtons);
		});
		syncTokenButtons();

		document.querySelectorAll('.usa-insert-token').forEach(function (btn) {
			btn.addEventListener('click', function () {
				insertAtCursor(btn.getAttribute('data-token') || '');
			});
		});

		var productBtn = document.getElementById('usa-insert-product');
		var picker = document.getElementById('usa-product-picker');
		var search = document.getElementById('usa-product-search');
		if (productBtn && picker && search) {
			productBtn.addEventListener('click', function () {
				picker.hidden = !picker.hidden;
				if (!picker.hidden) {
					search.focus();
				}
			});
			var timer = null;
			search.addEventListener('input', function () {
				clearTimeout(timer);
				var q = search.value.trim();
				if (q.length < 2) {
					document.getElementById('usa-product-results').innerHTML = '';
					return;
				}
				timer = setTimeout(function () {
					searchProducts(q);
				}, 250);
			});
		}
	});
})();
