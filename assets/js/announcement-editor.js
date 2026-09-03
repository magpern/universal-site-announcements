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

		// M6.1: fixed-row colour fields. Each checkbox enables/disables its
		// paired native colour input; a disabled input is never submitted,
		// so unchecked = "inherit host styling" with no hidden-field shadowing.
		function relativeLuminance(hex) {
			var m = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(hex || '');
			if (!m) {
				return null;
			}
			var h = m[1];
			if (h.length === 3) {
				h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
			}
			function linearize(c) {
				return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
			}
			var r = linearize(parseInt(h.substr(0, 2), 16) / 255);
			var g = linearize(parseInt(h.substr(2, 2), 16) / 255);
			var b = linearize(parseInt(h.substr(4, 2), 16) / 255);
			return 0.2126 * r + 0.7152 * g + 0.0722 * b;
		}

		function contrastRatio(hexA, hexB) {
			var lA = relativeLuminance(hexA);
			var lB = relativeLuminance(hexB);
			if (lA === null || lB === null) {
				return null;
			}
			var lighter = Math.max(lA, lB);
			var darker = Math.min(lA, lB);
			return (lighter + 0.05) / (darker + 0.05);
		}

		function fieldState(name) {
			var toggle = document.querySelector('[data-usa-color-toggle="' + name + '"]');
			var input = document.getElementById(name);
			if (!toggle || !input || !toggle.checked) {
				return null;
			}
			return input.value;
		}

		function refreshContrastWarning() {
			var warning = document.getElementById('usa-contrast-warning');
			if (!warning) {
				return;
			}
			var bg = fieldState('usa_fixed_bg');
			var fg = fieldState('usa_fixed_fg');
			var link = fieldState('usa_fixed_link');

			var messages = [];
			if (bg && fg) {
				var textRatio = contrastRatio(bg, fg);
				if (textRatio !== null && textRatio < 4.5) {
					messages.push('text ' + textRatio.toFixed(1) + ':1');
				}
			}
			if (bg && link) {
				var linkRatio = contrastRatio(bg, link);
				if (linkRatio !== null && linkRatio < 4.5) {
					messages.push('links ' + linkRatio.toFixed(1) + ':1');
				}
			}

			// A ratio is only meaningful when both colours in a pair are
			// explicitly configured — when background is left to inherit
			// from the host theme, the actual rendered contrast is unknown
			// at edit time, so no ratio is claimed.
			if (messages.length) {
				warning.textContent =
					'Low contrast — text may be hard to read (' + messages.join(', ') + ', recommend ≥ 4.5:1).';
				warning.hidden = false;
			} else if (bg && (fg || link)) {
				warning.hidden = true;
			} else {
				warning.hidden = true;
			}
		}

		document.querySelectorAll('.usa-color-toggle').forEach(function (toggle) {
			var name = toggle.getAttribute('data-usa-color-toggle');
			var input = document.getElementById(name);
			if (!input) {
				return;
			}
			toggle.addEventListener('change', function () {
				input.disabled = !toggle.checked;
				refreshContrastWarning();
			});
			input.addEventListener('input', refreshContrastWarning);
		});
		refreshContrastWarning();

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
