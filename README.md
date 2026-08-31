# Universal Site Announcements

Generic WordPress plugin for a site-wide announcement bar: manual messages, safe inline links, scheduling, accessible rotation, and optional dynamic message providers with merge-tag templates.

**Repository:** [magpern/universal-site-announcements](https://github.com/magpern/universal-site-announcements)  
**Text domain / slug:** `universal-site-announcements`  
**PHP namespace:** `USA\`  
**Version:** 0.5.1

## Status

**M1**–**M5** are implemented. **0.5.1** makes render diagnostics dismissible and auto-clears overlay merge-tag mismatch warnings only when the same announcement recovers.

**0.5.0** adds optional Universal Multilingual (AIML) template-body overlays for visitor-facing announcements when AIML ≥ 1.7.0 with public descriptor factory (1.8.0+) is available; source templates remain the fallback.

**0.4.1** corrects announcement editor UX: dynamic requirements are derived from the template (no source radio).

**0.4.0** adds schedule modes (always / one-time interval / weekly recurring) with optional weekly date windows in the WordPress site timezone.

**0.3.0** adds dynamic message templates (merge tags), HTML-aware placement validation, product link tokens, and hybrid Universal Multicurrency threshold display.

## Requirements

- WordPress 6.5+
- PHP 8.1+
- Composer dependencies (`composer install`)
- For Store Notice integration: WooCommerce with **Store notice enabled** (WooCommerce → Settings → Site visibility). This plugin does **not** toggle that option.
- Free-shipping threshold display: Universal Multicurrency when active (≥ API with `umc_get_free_shipping_threshold_display`); if UMC is **not** active, base-currency `wc_price()` is used. If UMC is active but the API is missing or fails, the announcement is suppressed (no guessed amounts).

## Message templates (M3)

Announcement `post_content` is a template with optional merge tags:

| Token | Allowed on | Output |
|-------|------------|--------|
| `{{free_shipping_threshold}}` | WooCommerce free shipping only (exactly one required) | Formatted threshold HTML |
| `{{product:ID}}` | Manual and free shipping | Public product title → permalink link |

- Tokens may appear only in text content (including inside `<strong>` / `<em>`).
- Tokens in HTML attributes, or `{{product:…}}` inside an existing `<a>`, are rejected and the announcement is suppressed.
- Malformed `{{…}}` braces suppress the announcement (no literal-brace escape language).
- Migration: empty free-shipping bodies are seeded with `Free shipping on orders of {{free_shipping_threshold}} or more` once (`usa_schema_version` = 3). Non-empty bodies are never overwritten.

## Admin navigation

| Location | What |
|----------|------|
| **Announcements** (top-level menu) | All Announcements, Add New, Settings |
| Plugins screen row | **Announcements**, **Settings**, Deactivate (capability-gated) |

### Announcement editor

Source is **derived automatically** from the message template (read-only Dynamic requirements status). Insert free-shipping threshold or product links via Dynamic values — no source radio. Template preview, enabled, priority, **schedule mode** (Always active / One-time date interval / Weekly recurring), optional weekly weekdays and Weekly starts on / Weekly ends after, provider diagnostics when free shipping is required or the template is invalid.

### Settings

- Global announcement-bar enable/disable
- Rotation enable/disable (multi-message only)
- Rotation interval (seconds, whole number)
- Fade duration (milliseconds, whole number; must be shorter than interval)
- Operational status / diagnostics

Defaults preserve M2 behaviour: rotation on, 8 s interval, 600 ms fade.

## Behaviour

| State | Front-end bar |
|-------|----------------|
| Plugin enabled + ≥1 eligible announcement | USA message(s) |
| Plugin enabled + no eligible announcements | No bar |
| Plugin disabled (settings) | Upstream WooCommerce notice unchanged |
| Plugin deactivated | Upstream WooCommerce notice unchanged; USA data retained |
| Rotation off + multiple active | Highest-priority message only (no JS/shell) |
| One message / no-JS / reduced-motion | Static highest-priority message |

USA never reads/writes `woocommerce_demo_store` or `woocommerce_demo_store_notice` as its own on/off switch, and never mutates shipping configuration or theme files.

## Scheduling (M4)

| Mode | Behaviour |
|------|-----------|
| Always active | No date/weekday restrictions |
| One-time date interval | Starts at / Ends at (exclusive) in site timezone; stored and compared as UTC |
| Weekly recurring | Selected ISO weekdays, all day in the site timezone; optional `Weekly starts on` / `Weekly ends after` (`Y-m-d` local dates) |

Weekly activity requires both a selected local weekday and a local date inside the optional window (absent bounds = indefinite). Invalid stored schedule data suppresses the announcement (never silently “Always”).

## Development

```bash
composer install
composer test:unit   # or: vendor/bin/phpunit -c phpunit.xml.dist
composer phpcs
```

## License

GPL-2.0-or-later
