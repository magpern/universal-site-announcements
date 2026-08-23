# Universal Site Announcements

Generic WordPress plugin for a site-wide announcement bar: manual messages, safe inline links, optional scheduling (M2), accessible rotation (M2), and optional dynamic message providers (M2).

**Repository:** [magpern/universal-site-announcements](https://github.com/magpern/universal-site-announcements)  
**Text domain / slug:** `universal-site-announcements`  
**PHP namespace:** `USA\`  
**Version:** 0.2.0 (M2)

## Status

**M1 (Core manual announcement bar) is implemented.** See [docs/plans/M1_CORE_MANUAL_ANNOUNCEMENT_BAR.md](docs/plans/M1_CORE_MANUAL_ANNOUNCEMENT_BAR.md) and [docs/closure/m1-core-manual-announcement-bar.md](docs/closure/m1-core-manual-announcement-bar.md).

**M2 (scheduling, rotation, free-shipping provider) is implemented.** See [docs/plans/M2_SCHEDULING_ROTATION_FREE_SHIPPING.md](docs/plans/M2_SCHEDULING_ROTATION_FREE_SHIPPING.md) and [docs/closure/m2-scheduling-rotation-free-shipping.md](docs/closure/m2-scheduling-rotation-free-shipping.md).

### M2 behaviour (summary)

- **Schedule:** Starts at / Ends at (exclusive); site timezone in admin; UTC store + compare.
- **Rotation:** Two or more active messages fade with pause/resume outside the Store Notice `<p>`.
- **Free shipping:** Optional provider announcement; threshold display requires Universal Multicurrency ≥ 1.2.0 (`umc_get_free_shipping_threshold_display`). Missing/null API → provider suppressed (no base-currency guess).

## Requirements

- WordPress 6.5+
- PHP 8.1+
- Composer dependencies (`composer install`)
- For Store Notice integration: WooCommerce with **Store notice enabled** (WooCommerce → Settings → Site visibility). This plugin does **not** toggle that option.

## Install (development)

1. Place or bind-mount this repository under `wp-content/plugins/universal-site-announcements`.
2. `composer install --no-dev` (or `composer install` for tests).
3. Activate **Universal Site Announcements**.
4. On first activation, if the WooCommerce store-notice text is non-empty, one enabled announcement is seeded from that text (read-only copy).

## Behaviour (M1)

| State | Front-end bar |
|-------|----------------|
| Plugin enabled + ≥1 eligible announcement | USA message (highest priority) |
| Plugin enabled + no eligible announcements | No bar |
| Plugin disabled (settings) | Upstream WooCommerce notice unchanged |
| Plugin deactivated | Upstream WooCommerce notice unchanged; USA data retained |

USA never reads/writes `woocommerce_demo_store` or `woocommerce_demo_store_notice` as its own on/off switch, and never mutates shipping configuration or theme files.

## Admin

- **Announcements → Settings:** enable/disable USA content ownership.
- **Announcements → Announcements:** edit manual messages (inline links allowed).

## Development

```bash
composer install
composer test:unit   # or: vendor/bin/phpunit -c phpunit.xml.dist
composer phpcs
```

## License

GPL-2.0-or-later
