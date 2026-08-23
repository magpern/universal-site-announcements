# Closure — Derived announcement requirements UX (0.4.1)

**Status:** CLOSED  
**Date:** 2026-08-23  
**Version:** 0.4.1  
**Type:** Corrective patch (post-M4; no separate planning PR)

---

## Delivered

| Area | Change |
|------|--------|
| Derivation | `TemplateRequirements` analyses templates with the same parser/placement/token grammar as render |
| Runtime | Repository derives free-shipping vs manual from `post_content`, not `_usa_source` |
| Editor | Removed Announcement source radio; read-only **Dynamic requirements** status |
| Tokens | Free-shipping threshold and product picker always available without a source choice |
| Legacy meta | `_usa_source` synced on successful save for compatibility; not authoritative at render |
| Uniqueness | At most one enabled free-shipping-dependent announcement (derived); duplicates fail closed at render |
| Version | 0.4.0 → **0.4.1** |

---

## DEV acceptance (fixtures restored)

| Check | Result |
|-------|--------|
| Stale `_usa_source=manual` ignored when template has threshold | Pass |
| Plain message → no special requirements | Pass |
| Insert threshold → requires free shipping | Pass |
| Remove threshold → manual again | Pass |
| Invalid template fail-closed | Pass |
| Store Notice attribute preservation | Pass |
| Only announcement `6672` remains | Pass |

Automated: **100** PHPUnit tests OK; PHPCS clean.

---

## Unchanged

M4 schedule modes / weekly window, rotation, UMC hybrid path, Store Notice outer contract, disable/deactivate rollback. No production tag/ZIP/deploy. No WooCommerce / UMC / theme / host config changes.
