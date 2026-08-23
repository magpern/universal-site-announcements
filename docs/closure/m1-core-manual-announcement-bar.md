# M1 Closure — Core Manual Announcement Bar

**Status:** CLOSED  
**Date:** 2026-08-23  
**Version:** 0.1.0  
**Plan:** [docs/plans/M1_CORE_MANUAL_ANNOUNCEMENT_BAR.md](../plans/M1_CORE_MANUAL_ANNOUNCEMENT_BAR.md)

---

## Selected content-replacement mechanism

**Constrained opening-tag fragment splicing** implemented in `USA\Rendering\ContentReplacer`:

1. Locate the first `<p …>` whose `class` tokens include both `woocommerce-store-notice` and `demo_store` (regex over opening tags; supports self-closing opening quirks).
2. Preserve the **original opening-tag string** (attribute order and names unchanged).
3. Surgically edit only the `style` attribute value to remove `display:none` / `display: none` declarations; leave other declarations intact; drop the attribute if empty.
4. Replace bytes between the opening tag and the matching `</p>` with sanitised announcement HTML.
5. On failure to recognise a safe outer paragraph: return upstream HTML unchanged and record a throttled admin diagnostic.

`WP_HTML_Tag_Processor` was evaluated as a candidate for attribute work but was **not** used as the sole solution because M1 requires preserving the validated opening-tag fragment and replacing arbitrary sanitised inner HTML. Fragment splicing meets the frozen contract with explicit tests.

---

## Verification evidence

PHPUnit suite (`phpunit.xml.dist`), including integration fixtures:

- Multi-attribute outer `<p>` with `style="color: red; display: none;"` → retains attributes and `color: red`, removes only display none, inserts inline link HTML.
- Unrecognised markup → passthrough + error code `unrecognised_outer_markup`.
- Self-closing opening quirk + trailing text + `</p>` → content replaced; `data-position` retained.
- Opening-tag attribute order preserved when style is removed entirely.

Automated result: **9 tests, 38 assertions, OK**. PHPCS: clean under project ruleset.

---

## Development-site acceptance

| Check | Result |
|-------|--------|
| Activate seeds one announcement from WC notice text | Pass |
| `woocommerce_demo_store` / `woocommerce_demo_store_notice` unchanged after activate/deactivate | Pass (byte-identical) |
| Live homepage retains `data-position` and host classes | Pass |
| USA disabled → upstream HTML passthrough | Pass |
| Zero enabled announcements → empty string (no bar) | Pass |
| Deactivate → WC notice path restored; CPT data retained | Pass |
| No shipping/theme edits by USA | Pass |

---

## Explicit M2 deferrals

- Schedule fields and evaluation
- Multi-message rotation, fade, pause/resume, front-end JS
- WooCommerce free-shipping provider
- Universal Multicurrency threshold-display API
- Public release tag / ZIP packaging / production deploy
