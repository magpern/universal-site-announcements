# Universal Site Announcements

Generic WordPress plugin for a site-wide announcement bar: manual messages, safe inline links, optional scheduling, accessible rotation, and optional dynamic message providers (including a WooCommerce free-shipping threshold provider).

**Repository:** [magpern/universal-site-announcements](https://github.com/magpern/universal-site-announcements)  
**Text domain / slug:** `universal-site-announcements`  
**PHP namespace (reserved):** `USA\`

## Status

**Milestone M0 (architecture freeze) is complete.** This repository currently contains documentation only. No functional plugin code, assets, Composer scaffolding, or WordPress integration exists yet.

Implementation begins with **M1** only after explicit project instruction. See [docs/plans/MASTER_PLAN.md](docs/plans/MASTER_PLAN.md) for the frozen specification.

## Milestones

| Milestone | Scope |
|-----------|--------|
| **M0** | Discovery, repository bootstrap, architecture freeze (documentation only) |
| **M1** | Core manual announcement bar MVP |
| **M2** | Scheduling, accessible rotation, WooCommerce free-shipping provider |

## Integration overview

On sites that use WooCommerce’s global Store Notice, Universal Site Announcements supplies announcement content through the `woocommerce_demo_store` filter while preserving the upstream outer `<p>` element and its attributes (including theme-provided attributes such as `data-position`). The WooCommerce store-notice **enable** option remains an installation prerequisite and rollback mechanism; this plugin does not read or write it as its own on/off switch.

## License

GPL-2.0-or-later (to be applied with plugin implementation in M1).
