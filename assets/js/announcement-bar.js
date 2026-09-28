/**
 * Universal Site Announcements — accessible fade rotation.
 *
 * Interval and fade duration come from usaAnnouncementBar (wp_localize_script).
 * No manual pause control: auto-advance pauses itself on hover, keyboard
 * focus, and a hidden tab, and never starts when prefers-reduced-motion is
 * set — each of those is itself a WCAG 2.2.2-compliant pause mechanism.
 * No aria-live on automatic ticks.
 */
(function () {
	'use strict';

	var config = window.usaAnnouncementBar || {};
	var INTERVAL_MS = parseInt(config.intervalMs, 10);
	var FADE_MS = parseInt(config.fadeMs, 10);

	if (!INTERVAL_MS || INTERVAL_MS < 1) {
		INTERVAL_MS = 8000;
	}
	if (!FADE_MS || FADE_MS < 1) {
		FADE_MS = 600;
	}
	if (FADE_MS >= INTERVAL_MS) {
		FADE_MS = Math.min(600, Math.floor(INTERVAL_MS / 2) || 1);
	}

	function prefersReducedMotion() {
		return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	function initShell(shell) {
		if (prefersReducedMotion()) {
			return;
		}

		var messages = shell.querySelectorAll('.usa-announcement-bar__message');
		if (messages.length < 2) {
			return;
		}

		shell.classList.add('usa-announcement-shell--rotating');

		var index = 0;
		var timer = null;
		var fading = false;

		function show(i) {
			for (var n = 0; n < messages.length; n++) {
				messages[n].classList.remove('is-active', 'is-fading-out');
			}
			messages[i].classList.add('is-active');
			index = i;
		}

		function advance() {
			if (fading || document.visibilityState === 'hidden') {
				return;
			}
			fading = true;
			var current = messages[index];
			var next = (index + 1) % messages.length;
			current.classList.add('is-fading-out');
			window.setTimeout(function () {
				show(next);
				fading = false;
			}, FADE_MS);
		}

		function start() {
			stop();
			timer = window.setInterval(advance, INTERVAL_MS);
		}

		function stop() {
			if (timer !== null) {
				window.clearInterval(timer);
				timer = null;
			}
		}

		document.addEventListener('visibilitychange', function () {
			if (document.visibilityState === 'hidden') {
				stop();
			} else {
				start();
			}
		});

		shell.addEventListener('mouseenter', function () {
			stop();
		});
		shell.addEventListener('mouseleave', function () {
			if (document.visibilityState !== 'hidden') {
				start();
			}
		});
		shell.addEventListener('focusin', function () {
			stop();
		});
		shell.addEventListener('focusout', function (event) {
			if (!shell.contains(event.relatedTarget) && document.visibilityState !== 'hidden') {
				start();
			}
		});

		start();
	}

	function boot() {
		var shells = document.querySelectorAll('.usa-announcement-shell');
		for (var i = 0; i < shells.length; i++) {
			initShell(shells[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}());
