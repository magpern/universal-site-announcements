(function () {
	'use strict';

	function bindDismiss(notice) {
		if (!notice || !window.usaDiagnosticNotice) {
			return;
		}

		notice.addEventListener('click', function (event) {
			var target = event.target;
			if (!target || !target.classList || !target.classList.contains('notice-dismiss')) {
				return;
			}

			var payload = new window.FormData();
			payload.append('action', window.usaDiagnosticNotice.action);
			payload.append('nonce', window.usaDiagnosticNotice.nonce);

			window.fetch(window.usaDiagnosticNotice.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: payload
			}).catch(function () {
				// Notice is already hidden client-side; transient may remain until TTL.
			});
		});
	}

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	ready(function () {
		var notices = document.querySelectorAll('.usa-render-diagnostic[data-usa-diagnostic]');
		for (var i = 0; i < notices.length; i++) {
			bindDismiss(notices[i]);
		}
	});
})();
