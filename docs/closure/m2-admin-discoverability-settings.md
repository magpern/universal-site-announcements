# M2 Corrective Patch Closure — Admin Discoverability & Rotation Settings

**Status:** CLOSED  
**Date:** 2026-08-23  
**Version:** 0.2.1  
**Branch:** `fix/m2-admin-discoverability-settings`  
**Baseline:** `main` @ `3fb350f` (M2 / 0.2.0)

---

## Problem

On the Plugins screen, USA exposed only **Deactivate**. Operators could not discover Announcements or Settings without an internal URL. Rotation interval/fade were hard-coded.

## Delivered

| Item | Detail |
|------|--------|
| Top-level menu | **Announcements** → All Announcements, Add New, Settings (CPT `show_in_menu`; Settings submenu `usa-settings`) |
| Plugin action links | Announcements + Settings (before Deactivate); gated by `usa_manage_cap` / `manage_options` |
| Rotation settings | Enable, interval (seconds→ms), fade (ms); defaults 8000 / 600; strict validation |
| Rotation off | Highest-priority message only; no shell/JS |
| Version | 0.2.0 → **0.2.1** |

## Validation

- PHPUnit including `AdminDiscoverabilitySettingsTest`
- PHPCS clean
- DEV: Plugins links, menu IA, editor fields, rotation settings effect, single-message static, fixtures restored

## Non-goals confirmed

No production deploy, tag, ZIP, UMC/theme/host/shipping/cache/WooCommerce option changes; announcement data unchanged except disposable DEV fixtures restored.
