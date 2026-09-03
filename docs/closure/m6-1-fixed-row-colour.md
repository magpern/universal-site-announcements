# M6.1 — Fixed-Row Visual Styling Closure

**Status:** COMPLETE on `main` (implementation merged; tag/ZIP/production deploy separately authorized)
**Plan:** [M61_FIXED_ROW_VISUAL_STYLING.md](../plans/M61_FIXED_ROW_VISUAL_STYLING.md) (FROZEN — PO APPROVED)
**Plan freeze merge:** `df4655dee4fcfc9040340df213bdfafbe18b177d` (PR #20)
**Implementation:** PR #21, commit `4eb2d67`
**Baseline version:** 0.6.1 → **USA version: 0.6.2**
**Closed:** 2026-09-03

---

## 1. Verdict

**M6.1 IMPLEMENTATION: PASS.**

Each fixed-slot announcement can now optionally set its own background, text, link, and
border/separator colour. All four fields are independently optional; when none is set, a fixed
row renders byte-identical to the pre-M6.1 (0.6.1) output. Rotating announcements, the WooCommerce
host notice paragraph, AI Multilingual translation, and token/sanitization behaviour are
unaffected. No schema migration or `Schema::VERSION` bump was required.

---

## 2. Scope delivered

| Area | Delivered |
|---|---|
| Data model | `_usa_fixed_bg`, `_usa_fixed_fg`, `_usa_fixed_link`, `_usa_fixed_border` — independently nullable hex meta; new pure resolver `src/Announcement/FixedRowStyle.php` mirroring `DisplayMode`'s read-time-defaulting pattern (`normalize()` at save, `resolve()` at read, both funnel through the same `sanitize_hex()`). **No migration, no `Schema::VERSION` bump** (stays `4`). |
| Validation | `sanitize_hex()` wraps WordPress core `sanitize_hex_color()` exclusively — 3/6-digit hex only, anchored regex, no rgb/rgba/hsl/named colours, no free-text CSS. Invalid/empty/tampered values resolve to `null` (inherit) at both save and read time. |
| Rendering | `StoreNoticeRenderer::fixed_fragment()` resolves style **after** `Sanitizer::sanitize_output()` runs on the body and emits it as CSS custom properties (`--usa-fixed-bg/-fg/-link/-border`) in a `style` attribute on the row's own wrapper span — never re-enters `wp_kses` or the AIML/token pipeline. Rotating spans and the host `<p>` never receive this attribute. |
| CSS | `assets/css/announcement-bar.css` consumes the custom properties with `transparent`/`inherit`/`currentColor` fallbacks, reproducing the exact M6 appearance when unconfigured. Link colour is scoped by a descendant selector under the row's own wrapper class — cannot leak to rotating spans or the host element. `prefers-reduced-motion` block untouched. |
| Admin | Four checkbox + native `<input type="color">` pairs inside the existing `data-usa-display="fixed"` panel (no new show/hide container); disabled colour inputs are never submitted, so unchecked means "inherit" by construction. Save persisted immediately after the existing `DisplayMode` block, before any schedule-branch early return — same nonce/capability/ordering conventions as M6. |
| Accessibility | Advisory (non-blocking) WCAG contrast warning in `announcement-editor.js`, computed only when both colours of a pair (bg+fg, bg+link) are explicitly configured — no ratio is claimed when a colour is left to inherit from the host theme. Never blocks save. |
| AIML | **Zero changes.** No file under `src/Integration/` was modified; style resolution is entirely independent of the overlay/translation path. |

---

## 3. Automated validation evidence

Independent full run by the repository `test-runner` sub-agent, entirely through Docker
(no PHP or Composer on the host), on the final implementation commit `4eb2d67`:

| Gate | Command | Result |
|---|---|---|
| Dependencies | `composer install --no-interaction --prefer-dist` | exit 0 |
| Full suite | `vendor/bin/phpunit -c phpunit.xml.dist` | **233 tests, 627 assertions — PASS** |
| Unit suite | `vendor/bin/phpunit -c phpunit.xml.dist --testsuite unit` | 226 tests, 582 assertions — PASS |
| Integration suite | `vendor/bin/phpunit -c phpunit.xml.dist --testsuite integration` | 7 tests, 45 assertions — PASS |
| Coding standards | `composer phpcs` (WordPress-Extra) | exit 0, clean |
| Package build | `bash scripts/build-release-package.sh` | `universal-site-announcements-0.6.2.zip` + `.sha256`, 203 entries, single top-level directory, sha256 verified, no `tests`/`docs`/`scripts`/`.git`/`.github`/`node_modules` paths present |

No test failures occurred at any point, so no pre-existing-versus-regression triage was required.
`tests/bootstrap.php` gained a faithful reimplementation of WordPress core's `sanitize_hex_color()`
(3/6-digit hex only) to keep the unit suite dependency-free.

New coverage: `FixedRowStyleTest` (validation, injection-payload rejection, tamper resistance,
`has_any()`/`persist()`/`to_css_vars()`), `FixedRowStyleRenderingTest` (scoped custom-property
output, independent above/below styling, rotating-span isolation, malicious-meta neutralization,
AIML-overlay independence, collision-selection independence, downgrade equivalence). All
pre-existing suites pass unmodified, including `SchemaMigrationTest::test_schema_version_is_four`
and every M6 test (`DisplayModeTest`, `FixedSlotPartitionTest`, `FixedSlotSelectorTest`,
`StoreNoticeRendererFixedTest`, `StoreNoticeAttributePreservationTest`).

---

## 4. DEV acceptance evidence (2026-09-03)

DEV is bind-mounted directly to this checkout (`/opt/biopentra/dev/universal-site-announcements`
→ `wp-content/plugins/universal-site-announcements`), so the implementation branch was live on DEV
throughout. `composer install --no-dev` was run afterward to restore the production dependency
shape (dev tooling — PHPUnit, PHPCS — is not part of the served plugin). `wp plugin get` reported
0.6.2; site returned HTTP 200 throughout.

Fixture: the pre-existing fixed-above announcement ("Warning", ID 6897) was temporarily disabled
to avoid a placement collision with the fixture. Temporary posts `M61-ACC-PAY` (fixed above,
priority 5, light green/off-white styling), `M61-ACC-BELOW` (fixed below, priority 10, dark
styling with a border), and `M61-ACC-ROT2` (rotating, to exercise the two-message shell) were
created. The pre-existing "Free shipping" rotating announcement (ID 6896) was left unmodified and
used as-is for the "dark rotating shipping" side of the scenario.

| # | Scenario | Result |
|---|---|---|
| 1 | Unstyled fixed row (no style meta) | PASS — wrapper span carries no `style` attribute; byte-identical to the pre-M6.1 (0.6.1) form |
| 2 | Configured fixed row (bg + fg) | PASS — `style="--usa-fixed-bg:#eaf7ea;--usa-fixed-fg:#123456;"` on the wrapper only |
| 3 | Independent above + below styling | PASS — above carries its own bg/fg, below carries a distinct bg/fg/border; each is scoped to its own `<span>` |
| 4 | Rotating spans + pause button never styled | PASS — with two active rotating messages (shell + `is-active`/inactive spans + toggle button), none carry a `style` attribute; only the two fixed spans do |
| 5 | Single host `<p>` preserved | PASS — exactly one `woocommerce-store-notice demo_store` paragraph; original `data-notice-id`/`data-position` (Blocksy) attributes intact; fixed-above, rotating, fixed-below composed inside it in order |
| 6 | Invalid/tampered colour value | PASS — `_usa_fixed_bg` set directly to `red;}body{display:none` (bypassing the admin save path); rendered output dropped only that property (`bg` absent from the custom-property list) while the still-valid `fg` value remained; no `display:none`, `</style>` payload text, or `javascript:` substring reached the response |
| 7 | Non-default-language visitor (`?lang=sv`) | PASS — falls back to source body with the same wrapper `style` attribute intact (no published Swedish overlay exists on DEV, so this exercises the same "overlay unavailable" fallback path M6 verified live; a full live-translation pass carries the same limitation M6 documented — see §5) |
| 8 | Served CSS | PASS — `announcement-bar.css?ver=0.6.2` contains all four `var(--usa-fixed-*, <fallback>)` rules and the unmodified `prefers-reduced-motion` block |
| 9 | Responsive / visual | PASS — screenshots at 1280px and 390px viewports confirm the light fixed-above row, dark rotating bar, and dark fixed-below row render correctly and legibly at both widths; screenshots saved to the session scratchpad (`m61-dev-acceptance-desktop.png`, `m61-dev-acceptance-mobile.png`) |
| — | PHP errors | None — no fatals, warnings, or deprecations in the container log for the whole acceptance window |

**Cleanup:** the three `M61-ACC-*` posts were force-deleted, the pre-existing "Warning" announcement
was restored to enabled, the `usa_render_diagnostic` transient was cleared (already absent), object
cache was flushed, and the front-end notice returned to its exact original content
("Do not have peptides in the sun", unstyled) with HTTP 200.

---

## 5. Limitations

1. **Live-translation acceptance not executed**, for the same reason as M6 scenario 11/13:
   publishing a real overlay body requires driving the AI Multilingual pipeline against a live AI
   provider, outside this task's authorization. What is proven: a unit test
   (`test_translated_body_renders_with_unchanged_wrapper_style`) exercises the overlay seam with
   style meta set and asserts the wrapper style is unaffected, and the DEV "no published overlay"
   fallback path (§4, scenario 7) was verified live, matching M6's own acceptance depth.
2. **Screenshots are static captures**, not an interactive browser/assistive-technology pass. A
   VoiceOver/NVDA smoke check remains advisable but is not claimed as passed (same caveat as M6).

---

## 6. Explicitly not done

No GitHub tag, no release, no ZIP publication, no update-server publish, no production deployment,
no cache-policy change, and no external site configuration change. No typography, spacing,
gradient, animation, or general design-system controls were added. Rotating announcements received
no new styling controls. `Schema::VERSION` remains `4`.

---

## 7. Key paths

| Path | Role |
|---|---|
| `src/Announcement/FixedRowStyle.php` | Style meta normalisation, read-time resolution, CSS custom-property serialisation |
| `src/Rendering/StoreNoticeRenderer.php` | `fixed_fragment()` emits the `style` attribute on the wrapper span |
| `assets/css/announcement-bar.css` | Custom-property consumption with M6-identical fallbacks |
| `src/Admin/AnnouncementMetaBoxes.php` | Four colour fields inside the existing fixed-mode panel; save-flow persistence |
| `assets/js/announcement-editor.js` | Checkbox↔colour-input toggling; advisory contrast warning |
| `tests/unit/FixedRowStyleTest.php` | Validation, injection-payload rejection, tamper resistance |
| `tests/unit/FixedRowStyleRenderingTest.php` | Rendering output, isolation, AIML/collision independence |
