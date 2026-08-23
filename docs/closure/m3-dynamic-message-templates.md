# M3 Closure — Dynamic Message Templates

**Status:** CLOSED  
**Date:** 2026-08-23  
**Version:** 0.3.0  
**Plan:** [docs/plans/M3_DYNAMIC_MESSAGE_TEMPLATES.md](../plans/M3_DYNAMIC_MESSAGE_TEMPLATES.md) (FROZEN)

---

## Delivered

| Area | Implementation |
|------|----------------|
| Merge tags | `{{free_shipping_threshold}}`, `{{product:ID}}`; HTML-aware placement validation before resolve; sanitise after |
| Free-shipping templates | Editable `post_content`; exactly one threshold token; M2 eligibility retained |
| Manual templates | Product tokens only; threshold token forbidden |
| UMC hybrid | Inactive → `wc_price(base)`; active + API missing/null → suppress; success → `formatted_html` |
| Migration | `usa_schema_version=3`; seed empty provider bodies only; never overwrite non-empty |
| Admin | Prominent source selector; insert dynamic value; product AJAX picker; preview/diagnostics |
| Version | 0.2.1 → **0.3.0** |

---

## Migration evidence

- Schema option `usa_schema_version` set to **3** on DEV after `Schema::maybe_migrate()`.
- Empty free-shipping bodies receive default template; non-empty bodies untouched.

---

## DEV acceptance (fixtures restored)

| Check | Result |
|-------|--------|
| FS template + EUR amount | Pass |
| FS template + SEK (~2212.50) | Pass |
| Product token title + public link | Pass (`M21-POSTRELEASE-VARIABLE`) |
| Invalid FS body without threshold (marketing-only) | Suppressed (not shown) |
| Fixtures deleted; default announcement `6672` re-enabled | Pass |

Automated: **65** PHPUnit tests OK; PHPCS clean.

---

## Rollback

Disable Universal Site Announcements or deactivate the plugin → upstream WooCommerce Store Notice restored. Template posts retained. No WooCommerce / shipping / UMC settings written by this milestone.

---

## Explicit non-goals confirmed

No shortcodes, public token hooks, product prices/stock/promo validation, production tag/ZIP/deploy.
