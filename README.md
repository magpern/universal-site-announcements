# Universal Site Announcements

Generic WordPress plugin for a site-wide announcement bar: manual messages, safe inline links, scheduling, accessible rotation, and optional dynamic message providers with merge-tag templates.

**Repository:** [magpern/universal-site-announcements](https://github.com/magpern/universal-site-announcements)  
**Text domain / slug:** `universal-site-announcements`  
**PHP namespace:** `USA\`  
**Version:** 0.5.1

## Status

**M1**–**M6** are implemented. **0.6.0** adds fixed-slot announcement rows (see below).

**0.5.1** makes render diagnostics dismissible and auto-clears overlay merge-tag mismatch warnings only when the same announcement recovers.

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
| Fixed-slot announcement eligible | Static row above and/or below the rotating content, inside the same notice |

USA never reads/writes `woocommerce_demo_store` or `woocommerce_demo_store_notice` as its own on/off switch, and never mutates shipping configuration or theme files.

## Scheduling (M4)

| Mode | Behaviour |
|------|-----------|
| Always active | No date/weekday restrictions |
| One-time date interval | Starts at / Ends at (exclusive) in site timezone; stored and compared as UTC |
| Weekly recurring | Selected ISO weekdays, all day in the site timezone; optional `Weekly starts on` / `Weekly ends after` (`Y-m-d` local dates) |

Weekly activity requires both a selected local weekday and a local date inside the optional window (absent bounds = indefinite). Invalid stored schedule data suppresses the announcement (never silently “Always”).

## Fixed-slot rows (M6)

Any announcement can be switched from **Rotating** (default) to **Fixed slot** in
the editor, with a position of **Above** or **Below** the rotating announcements.
A fixed announcement is an ordinary announcement post: enabled state, priority,
schedule, template validation, free-shipping uniqueness and AI Multilingual body
overlays all behave exactly as they do for rotating announcements.

| Rule | Behaviour |
|------|-----------|
| Rotation | Fixed announcements never take part in the rotation and never fall back into it |
| One per position | At most one fixed announcement renders per position; lowest priority, then lowest ID, wins |
| Losing candidates | Suppressed, with an admin diagnostic and a non-blocking editor warning listing the competitors |
| Nothing eligible | No row is rendered — never an empty row |
| Markup | Composed inside the single `woocommerce-store-notice demo_store` paragraph as `span.usa-announcement-fixed`; the host's attributes are preserved |
| Styling | Inherits the host Store Notice colours and typography; the plugin owns block layout and a `currentColor` separator only |
| Motion | Static server-rendered content: no animation, no transition, unchanged reduced-motion and no-JS behaviour |

Existing announcements are unaffected: the display-mode meta defaults to
`rotating` at read time, so no migration runs and downgrading to 0.5.x simply
returns fixed announcements to the rotation.

## Development

```bash
composer install
composer test:unit   # or: vendor/bin/phpunit -c phpunit.xml.dist
composer phpcs
bash scripts/build-release-package.sh   # build the deployable plugin ZIP + checksum
```

CI (`.github/workflows/ci.yml`) runs PHPCS, the unit suite (PHP 8.1/8.3/8.4),
and a non-publishing packaging validation on every push and pull request.

## Releases

Pushing an annotated `vX.Y.Z` tag on `main` runs
`.github/workflows/release.yml`, which builds
`universal-site-announcements-<version>.zip` + `.zip.sha256` and publishes them
as GitHub Release assets. Nothing generated is committed. The canonical version
source (plugin header + `USA_VERSION` + `readme.txt` `Stable tag`), package
contents, tag format, and recovery steps are documented in
[`docs/RELEASE.md`](docs/RELEASE.md).

## License

GPL-2.0-or-later
