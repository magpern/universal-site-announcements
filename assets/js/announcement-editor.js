/**
 * Announcement editor: insert merge tags, product picker, requirements hint.
 */
(function () {
	'use strict';

	function getEditor() {
		if (window.tinymce && window.tinymce.get('content')) {
			return window.tinymce.get('content');
		}
		return null;
	}

	function getContent() {
		var editor = getEditor();
		if (editor && !editor.isHidden()) {
			return editor.getContent({ format: 'raw' }) || '';
		}
		var textarea = document.getElementById('content');
		return textarea ? textarea.value : '';
	}

	function insertAtCursor(text) {
		var editor = getEditor();
		if (editor && !editor.isHidden()) {
			editor.focus();
			editor.execCommand('mceInsertContent', false, text);
			refreshRequirements();
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
		refreshRequirements();
	}

	function refreshRequirements() {
		var statusEl = document.getElementById('usa-dynamic-requirements-status');
		var box = document.getElementById('usa-dynamic-requirements');
		var diag = document.getElementById('usa_announcement_provider_diag');
		if (!statusEl || !window.usaAnnouncementEditor || !usaAnnouncementEditor.i18n) {
			return;
		}
		var content = getContent();
		var hasFs = content.indexOf('{{free_shipping_threshold}}') !== -1;
		var open = (content.match(/\{\{/g) || []).length;
		var close = (content.match(/\}\}/g) || []).length;
		var malformed = open !== close;
		var i18n = usaAnnouncementEditor.i18n;
		var label;
		var css = 'notice-info';
		var state = 'none';

		if (malformed) {
			label = i18n.reqInvalid || 'Template is invalid';
			css = 'notice-error';
			state = 'invalid';
		} else if (hasFs) {
			label = i18n.reqShipping || 'Requires WooCommerce free shipping';
			css = 'notice-warning';
			state = 'free_shipping';
		} else {
			label = i18n.reqNone || 'No special requirements';
			css = 'notice-info';
			state = 'none';
		}

		statusEl.textContent = label;
		statusEl.setAttribute('data-status', state);
		if (box) {
			box.className = 'usa-dynamic-requirements notice ' + css + ' inline';
		}
		if (diag) {
			diag.style.display = state === 'none' ? 'none' : '';
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
		function syncSchedulePanels() {
			var modeInput = document.querySelector('input[name="usa_schedule_mode"]:checked');
			var mode = modeInput ? modeInput.value : 'always';
			document.querySelectorAll('.usa-schedule-panel').forEach(function (panel) {
				var panelMode = panel.getAttribute('data-usa-mode');
				if (panelMode === mode) {
					panel.removeAttribute('hidden');
				} else {
					panel.setAttribute('hidden', 'hidden');
				}
			});
		}
		document.querySelectorAll('input[name="usa_schedule_mode"]').forEach(function (el) {
			el.addEventListener('change', syncSchedulePanels);
		});
		syncSchedulePanels();

		function syncDisplayPanels() {
			var displayInput = document.querySelector('input[name="usa_display_mode"]:checked');
			var displayMode = displayInput ? displayInput.value : 'rotating';
			document.querySelectorAll('.usa-display-panel').forEach(function (panel) {
				var panelMode = panel.getAttribute('data-usa-display');
				if (panelMode === displayMode) {
					panel.removeAttribute('hidden');
				} else {
					panel.setAttribute('hidden', 'hidden');
				}
			});
		}
		document.querySelectorAll('input[name="usa_display_mode"]').forEach(function (el) {
			el.addEventListener('change', syncDisplayPanels);
		});
		syncDisplayPanels();

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

		var textarea = document.getElementById('content');
		if (textarea) {
			textarea.addEventListener('input', refreshRequirements);
		}
		if (window.tinymce) {
			tinymce.on('AddEditor', function (e) {
				if (e.editor && e.editor.id === 'content') {
					e.editor.on('change keyup SetContent', refreshRequirements);
				}
			});
		}
		refreshRequirements();
	});
})();
