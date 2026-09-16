=== Universal Site Announcements ===
Contributors: magpern
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generic site-wide announcement bar with safe inline links for WordPress.

== Description ==

Universal Site Announcements manages site-wide announcement messages and can integrate with the WooCommerce Store Notice rendering seam. Dynamic requirements (including free shipping) are derived from the message template. Milestone 4 adds schedule modes (always, one-time interval, weekly recurring with optional date window). Milestone 5-B optionally localizes announcement template bodies through AI Multilingual when available. Milestone 6 adds optional fixed-slot rows for persistent operational messages.

Admin navigation: **Announcements** (All Announcements, Add New, Settings). Plugin row links: Announcements, Settings, Deactivate.

== Installation ==

1. Install Composer dependencies (`composer install --no-dev` for production).
2. Activate the plugin.
3. If using WooCommerce Store Notice integration, keep WooCommerce → Settings → Site visibility → Store notice enabled so the host bar renders and existing styles apply.
4. Open **Announcements** in wp-admin (or use the Plugins screen links) to manage messages and Settings.

== Changelog ==

= 0.7.0 =
* New `{{page:ID}}` merge tag with a "Page link…" picker in the editor (mirrors the existing "Product link…" picker): inserts a page title linked to its current URL, resolved via `get_permalink()` at render time. Use it instead of pasting a page URL directly — a page URL baked into the template as plain text can't pick up a routing layer's translated slug (e.g. Universal Multilingual), while a token resolved at render time does.
* Placement rules extended to the new token: rejected inside an HTML attribute, and rejected nested inside an existing `<a>` (same as the product token).
* New AJAX page search (`usa_search_pages`) reusing the existing product-search nonce; no new capability required.

= 0.6.2 =
* Optional per-fixed-row colour styling (M6.1): background, text, link, and border/separator colour for fixed-slot announcements only, via CSS custom properties on the row's own wrapper.
* Strict hex-only validation (WordPress sanitize_hex_color()); absent or invalid values inherit the existing Store Notice styling exactly as before.
* Rotating announcements, the WooCommerce host notice, AI Multilingual translation, and token/sanitization behaviour are unchanged; no migration and no schema change.
* Advisory (non-blocking) low-contrast warning in the editor when both colours of a pair are explicitly configured.

= 0.6.1 =
* Automatic WooCommerce Store Notice gate activation: ensures the gate is enabled when USA is activated or an announcement is saved, so announcements render even if the WooCommerce option was disabled.
* Diagnostic warning when gate blocks rendering: explains the WooCommerce dependency and offers remediation.
* Enhanced test infrastructure: delete_option() stub, post type filtering in get_posts() mock.

= 0.6.0 =
* Fixed-slot announcement rows (M6): any announcement can be set to Fixed slot and shown above or below the rotating bar, outside the rotation.
* At most one fixed announcement renders per position; the lowest priority (then the lowest ID) wins, losers are suppressed and never rotate.
* An admin diagnostic and a non-blocking editor warning identify competing fixed announcements.
* Fixed rows are composed inside the single WooCommerce Store Notice paragraph and inherit the host bar styling; no new colour settings.
* Fixed rows use the existing schedule, template, free-shipping and AI Multilingual body-overlay behaviour unchanged; no migration and no schema change.

= 0.5.2 =
* Automatic updates from a private update server (bundled Plugin Update Checker v5); base URL read from the PRIVATE_UPDATE_SERVER constant, inert when it is not defined.

= 0.5.1 =
* Admin render diagnostic is dismissible (deletes the diagnostic transient).
* Overlay merge-tag mismatch diagnostics auto-clear only when the same announcement recovers.

= 0.5.0 =
* Optional AIML Integration adapter for announcement body overlays (chrome CPT admission, extract, visitor overlay, dirty invalidation).
* Protected merge-tag multiset gate; source requirements remain authoritative for free-shipping eligibility.
* Falls back to source templates when AIML is absent, incompatible, or translations are missing/stale/mismatched.

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
