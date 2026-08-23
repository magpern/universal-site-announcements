/**
 * Universal Site Announcements — accessible fade rotation.
 *
 * ~8s visible / ~600ms fade. Pause via button + sessionStorage.
 * No aria-live on automatic ticks. Respects prefers-reduced-motion.
 */
(function () {
	'use strict';

	var STORAGE_KEY = 'usa_announcement_bar_paused';
	var INTERVAL_MS = 8000;
	var FADE_MS = 600;

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

		var toggle = shell.querySelector('.usa-announcement-bar__toggle');
		if (!toggle) {
			return;
		}

		shell.classList.add('usa-announcement-shell--rotating');

		var index = 0;
		var timer = null;
		var paused = sessionStorage.getItem(STORAGE_KEY) === '1';
		var fading = false;

		function setPaused(next) {
			paused = next;
			toggle.setAttribute('aria-pressed', paused ? 'true' : 'false');
			toggle.textContent = paused ? toggle.getAttribute('data-label-resume') : toggle.getAttribute('data-label-pause');
			try {
				sessionStorage.setItem(STORAGE_KEY, paused ? '1' : '0');
			} catch (e) {
				/* ignore quota / private mode */
			}
			if (paused) {
				stop();
			} else if (document.visibilityState !== 'hidden') {
				start();
			}
		}

		var pauseLabel = toggle.textContent || 'Pause announcements';
		var resumeLabel = toggle.getAttribute('data-label-resume') || 'Resume announcements';
		toggle.setAttribute('data-label-pause', pauseLabel);
		toggle.setAttribute('data-label-resume', resumeLabel);

		function show(i) {
			for (var n = 0; n < messages.length; n++) {
				messages[n].classList.remove('is-active', 'is-fading-out');
			}
			messages[i].classList.add('is-active');
			index = i;
		}

		function advance() {
			if (fading || paused || document.visibilityState === 'hidden') {
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

		toggle.addEventListener('click', function () {
			setPaused(!paused);
		});

		document.addEventListener('visibilitychange', function () {
			if (document.visibilityState === 'hidden') {
				stop();
			} else if (!paused) {
				start();
			}
		});

		shell.addEventListener('mouseenter', function () {
			stop();
		});
		shell.addEventListener('mouseleave', function () {
			if (!paused && document.visibilityState !== 'hidden') {
				start();
			}
		});
		shell.addEventListener('focusin', function () {
			stop();
		});
		shell.addEventListener('focusout', function (event) {
			if (!shell.contains(event.relatedTarget) && !paused && document.visibilityState !== 'hidden') {
				start();
			}
		});

		setPaused(paused);
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
})();
