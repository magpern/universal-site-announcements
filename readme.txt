=== Universal Site Announcements ===
Contributors: magpern
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generic site-wide announcement bar with safe inline links for WordPress.

== Description ==

Universal Site Announcements manages site-wide announcement messages and can integrate with the WooCommerce Store Notice rendering seam. Dynamic requirements (including free shipping) are derived from the message template. Milestone 4 adds schedule modes (always, one-time interval, weekly recurring with optional date window).

Admin navigation: **Announcements** (All Announcements, Add New, Settings). Plugin row links: Announcements, Settings, Deactivate.

== Installation ==

1. Install Composer dependencies (`composer install --no-dev` for production).
2. Activate the plugin.
3. If using WooCommerce Store Notice integration, keep WooCommerce → Settings → Site visibility → Store notice enabled so the host bar renders and existing styles apply.
4. Open **Announcements** in wp-admin (or use the Plugins screen links) to manage messages and Settings.

== Changelog ==

= 0.4.1 =
* Derive free-shipping vs manual requirements from the template; remove source radio.
* Read-only Dynamic requirements status in the editor.

= 0.4.0 =
* Schedule modes: always, one-time date interval, weekly recurring (site timezone).
* Optional weekly recurrence window (Weekly starts on / Weekly ends after).
* Resumable schema v4 migration with persisted cursor (admin-request continuation).

= 0.3.0 =
* Dynamic message templates with {{free_shipping_threshold}} and {{product:ID}} merge tags.
* HTML-aware placement validation (no tokens in attributes; no product tokens inside existing links).
* Hybrid UMC threshold display; empty free-shipping bodies migrated to the default template.
* Editor: source selector above content, insert dynamic values, product search, template preview.

= 0.2.1 =
* Top-level Announcements menu (All Announcements, Add New, Settings).
* Plugins screen action links for Announcements and Settings.
* Administrator-configurable rotation enable/interval/fade with validation.

= 0.2.0 =
* Scheduling (site TZ input, UTC storage, exclusive end).
* Multi-message accessible fade rotation with pause control.
* WooCommerce free-shipping provider consuming umc_get_free_shipping_threshold_display().

= 0.1.0 =
* Initial M1 release: manual announcements, Store Notice content replacement, enable toggle.
