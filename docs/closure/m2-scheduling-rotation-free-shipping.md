# M2 Closure — Scheduling, Rotation, and Free-Shipping Provider

**Status:** CLOSED  
**Date:** 2026-08-23  
**Version:** 0.2.0  
**Plan:** [docs/plans/M2_SCHEDULING_ROTATION_FREE_SHIPPING.md](../plans/M2_SCHEDULING_ROTATION_FREE_SHIPPING.md) (FROZEN)

---

## Delivered

| Area | Implementation |
|------|----------------|
| Scheduling | `_usa_starts_at` / `_usa_ends_at` UTC; site-TZ admin input; exclusive end; `ScheduleEvaluator` |
| Priority admin | Editor + list columns + Quick Edit |
| Active resolution | Publish + enabled + schedule + source; ordered priority ASC, ID ASC |
| Rotation | Multi-message shell; fade 8s/600ms; pause outside `<p>`; no `aria-live`; reduced-motion / no-JS = first message only |
| Free-shipping provider | Reference package → `requires=min_amount` → eligibility allowlist → UMC public API → `formatted_html` |
| Version | 0.1.0 → **0.2.0** |

---

## UMC prerequisite verification

| Field | Value |
|-------|-------|
| UMC version | **1.2.0** |
| UMC release commit SHA | `a1fe8d842bfbc893e94cc45047a9a7e1f59b37e3` (tag `v1.2.0` annotated → that commit) |
| UMC main (post-release docs) | `bee919ef37d3ea4da464d843e959c14e4841d238` |
| Public API symbol | `umc_get_free_shipping_threshold_display( string $base_threshold ): ?array` |
| Public documentation | `docs/HOOKS.md` (Public PHP API), `docs/adr/0034-free-shipping-threshold-display-api.md`, `docs/architecture/free-shipping-threshold-display-api.md` |
| Implementation | `src/api.php` → `UMC\PublicApi\FreeShippingThresholdDisplayService` → shared `FreeShippingThresholdResolver` (same path as `ShippingConversion` eligibility) |
| UMC tests | `tests/integration/FreeShippingThresholdDisplayApiTest.php` |

### Exact contract consumed by USA

```php
if ( function_exists( 'umc_get_free_shipping_threshold_display' ) ) {
	$threshold = umc_get_free_shipping_threshold_display( $base_threshold ); // e.g. "200.00"
	// success: ['formatted_html' => string, 'amount' => string, 'currency_code' => string]
	// failure: null
}
```

Presentation uses **`formatted_html`** after narrow price `wp_kses` only. USA does not convert, re-round, inspect rates, read `umc_currency` for money math, or call UMC internals.

### USA failure behaviour

| Condition | Behaviour |
|-----------|-----------|
| UMC missing / function absent | Suppress free-shipping announcement only |
| API returns `null` | Suppress free-shipping announcement only |
| Other manuals / schedules | Continue independently |

No `wc_price(base)` fallback for the dynamic provider.

### USA tests proving no conversion/rounding

`tests/unit/FreeShippingProviderContractTest.php` (+ gateway seam `UmcThresholdDisplay`):

- Missing function → unavailable / null
- API null → null
- Base / foreign `formatted_html` concatenated unchanged
- `build_message` does not invoke gateway / rates / cookies
- Price HTML allowlist strips scripts, keeps `wc_price` markup

Automated suite: **34 tests, 100 assertions, OK** (PHPUnit via wpcli container). PHPCS: clean.

### DEV cross-plugin acceptance

Disposable fixtures (provider priority 10 + two manuals) on https://dev.biopentra.eu; restored afterward (only post `6672` Default announcement remains enabled).

| Currency | Announcement amount | UMC API amount | Match |
|----------|---------------------|----------------|-------|
| EUR (base) | € 200,00 | 200.00 EUR | Pass |
| SEK | 2 212,50 kr | 2212.50 SEK | Pass |
| PLN | 861,56 zł | 861.56 PLN | Pass |
| DKK | 1 495,16 kr. | 1495.16 DKK | Pass |

Also verified: rotation shell + three messages + pause button; `data-position="bottom"` retained; CSS/JS enqueued only for multi-message; fixtures deleted and default manual restored.

**Note:** Host Store Notice opening tags may use a self-closing `/>` quirk (pre-existing; USA preserves the opening fragment per M1 contract).

---

## Explicit non-goals / deferred

- Production release tag / ZIP / production deploy
- M3+ audience targeting, analytics, Elementor widgets
- Automated JS unit tests (script behaviour covered manually)
- Modifying UMC from this milestone

---

## Rollback

Disable USA in settings or deactivate the plugin → upstream WooCommerce Store Notice restored; USA CPT data retained. WooCommerce options and shipping settings are not written by USA.
