# Universal Site Announcements

Generic WordPress plugin for a site-wide announcement bar: manual messages, safe inline links, scheduling, accessible rotation, and optional dynamic message providers.

**Repository:** [magpern/universal-site-announcements](https://github.com/magpern/universal-site-announcements)  
**Text domain / slug:** `universal-site-announcements`  
**PHP namespace:** `USA\`  
**Version:** 0.2.1

## Status

**M1** and **M2** are implemented. See plans and closure docs under `docs/`.

**0.2.1** corrects admin discoverability: top-level **Announcements** menu, Plugins-screen action links, and configurable rotation settings.

## Requirements

- WordPress 6.5+
- PHP 8.1+
- Composer dependencies (`composer install`)
- For Store Notice integration: WooCommerce with **Store notice enabled** (WooCommerce → Settings → Site visibility). This plugin does **not** toggle that option.
- Free-shipping provider: Universal Multicurrency ≥ 1.2.0 (`umc_get_free_shipping_threshold_display`)

## Admin navigation

| Location | What |
|----------|------|
| **Announcements** (top-level menu) | All Announcements, Add New, Settings |
| Plugins screen row | **Announcements**, **Settings**, Deactivate (capability-gated) |

### Announcement editor

Title, enabled, source (manual / WooCommerce free shipping), content (manual), priority, Starts at, Ends at (exclusive), provider diagnostics.

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

## Development

```bash
composer install
composer test:unit   # or: vendor/bin/phpunit -c phpunit.xml.dist
composer phpcs
```

## License

GPL-2.0-or-later
