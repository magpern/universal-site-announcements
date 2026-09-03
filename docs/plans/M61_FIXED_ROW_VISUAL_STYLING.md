# M6.1 — Fixed-Row Visual Styling

**Status:** FROZEN — PO APPROVED
**Repository:** [magpern/universal-site-announcements](https://github.com/magpern/universal-site-announcements)
**Plugin:** Universal Site Announcements (`universal-site-announcements`)
**Namespace:** `USA\`
**Parent architecture:** [MASTER_PLAN.md](MASTER_PLAN.md); supersedes M6 §9/§16 decision A (no colour controls) for **fixed rows only**
**M6 baseline:** [M6_FIXED_SLOT_MULTILINGUAL_ROWS.md](M6_FIXED_SLOT_MULTILINGUAL_ROWS.md) (frozen), closure [m6-fixed-slot-multilingual-rows.md](../closure/m6-fixed-slot-multilingual-rows.md)
**Planning baseline `main` SHA:** `48694022f26c98d6c62d2b9c845c17c9927960a3`
**Baseline version:** 0.6.1 (`universal-site-announcements.php:23`, `USA_VERSION`); `Schema::VERSION = 4` unchanged since M6
**Target version:** **0.6.2**
**Implementation branch:** `feature/m61-fixed-row-visual-styling` (from the post-freeze `main`)
**Frozen:** 2026-09-03

---

## 1. Context

M6 shipped "fixed-slot" announcements: an announcement can render as a fixed
row `above` or `below` the rotating content, inside WooCommerce's single
`<p class="woocommerce-store-notice demo_store">` host element. M6
deliberately left styling untouched — fixed rows inherit host-theme colour
via `currentColor` borders only (`assets/css/announcement-bar.css` header
comment: "colour, background and typography stay with the host theme"; M6
plan §16 decision A: no per-announcement colour controls, out of scope).

The gap: a merchant wants a fixed row visually distinct from the rotating bar
it's paired with — e.g. a light green/off-white payment notice fixed
**above** a dark rotating shipping bar. M6 has no mechanism for that.

M6.1 is a narrow, additive follow-on: let each **fixed-slot** announcement
optionally set its own background, text, link, and border colour, with zero
effect on rotating announcements and zero visual change when unconfigured.

This document is a **planning-only** deliverable: no code, tests, or deploys
are produced by this PR. It exists so the design can be reviewed and frozen
as a repository artifact before a separate implementation task executes it.

### Non-goals

- No styling controls for rotating announcements (this scope).
- No custom-CSS free-text field, no typography, spacing, gradients,
  animations, layout builders, or general design system.
- No new AIML/translation fields — styling meta is entirely independent of
  translated body content; translated bodies render with unchanged style.
- No blocking contrast enforcement — advisory only, never blocks save.
- No database migration, no `Schema::VERSION` bump.
- No tag, GitHub release, ZIP build, update-server publish, production
  deployment, or external configuration change (this planning task, and not
  authorized without separate PO sign-off for a future implementation task).

---

## 2. Audited M6 baseline (facts this plan relies on)

All statements below were verified against `main` at the planning baseline
SHA. The implementation must re-verify them before changing code.

- **Rendering seam** — [`StoreNoticeRenderer::fixed_fragment()`](../../src/Rendering/StoreNoticeRenderer.php)
  (lines 165-177) builds exactly one
  `<span class="usa-announcement-fixed usa-announcement-fixed--{above|below}" data-usa-fixed="{above|below}">{sanitized body}</span>`
  per placement. Only one fixed row per placement ever renders
  (`Selector::partition()` picks a single winner), so class-level (not
  per-post) CSS targeting is sufficient.
- **Sanitize/AIML boundary** — body sanitization (`Sanitizer::sanitize_output()`,
  [`src/Announcement/Sanitizer.php`](../../src/Announcement/Sanitizer.php)
  lines 95-98, `wp_kses()`) runs on the row's body content *before*
  `fixed_fragment()` wraps it in the outer span. The wrapper span itself is
  never passed through `wp_kses`. This is the critical seam: anything M6.1
  attaches to the wrapper bypasses body sanitization and must be
  independently, strictly validated in PHP — and by using this seam, M6.1
  touches nothing in the AIML overlay
  ([`src/Integration/TemplateOverlay.php`](../../src/Integration/TemplateOverlay.php))
  or token-resolution pipeline
  (`src/Announcement/Repository.php::resolve_content()`).
- **Existing dynamic-CSS precedent** — `wp_add_inline_style()` at
  `StoreNoticeRenderer.php:214-217` (rotation fade duration) is the only
  place the plugin emits computed CSS today. No inline `style=""` attributes
  or CSS custom properties exist anywhere in the codebase yet.
- **Admin save flow** — [`AnnouncementMetaBoxes::save()`](../../src/Admin/AnnouncementMetaBoxes.php)
  (~line 579+): nonce check (582-587), capability check (589-591), autosave
  guard (593-595), body sanitize (597-601), then `DisplayMode::normalize()` +
  `update_post_meta` for mode/placement (644-649), persisted **before** any
  schedule-branch early return.
- **No colour sanitize helper exists** in the plugin today. WP core provides
  `sanitize_hex_color()` (accepts 3/6-digit hex, returns `null` otherwise) —
  reuse it directly rather than writing a custom validator.
- **`DisplayMode` is the pattern to mirror** —
  [`src/Announcement/DisplayMode.php`](../../src/Announcement/DisplayMode.php)
  is a pure resolver; absent/invalid meta defaults safely (no migration
  needed); `normalize()` used at save, `resolve()` used at read.
- **Schema** — [`Schema::VERSION = 4`](../../src/Lifecycle/Schema.php)
  (line 30), unchanged since M6; M6 added its two meta keys without a bump
  because both default safely at read time — the same precedent applies here.

---

## 3. Data model

New optional post meta, one per colour, hex only (`sanitize_hex_color()`
output, e.g. `#a1b2c3`), each independently nullable:

| Meta key | Purpose |
|---|---|
| `_usa_fixed_bg` | Background colour |
| `_usa_fixed_fg` | Text colour |
| `_usa_fixed_link` | Link colour |
| `_usa_fixed_border` | Border/separator colour |

New pure resolver `USA\Announcement\FixedRowStyle`
(`src/Announcement/FixedRowStyle.php`), parallel to `DisplayMode`:

- `sanitize_hex( ?string $value ): ?string` — thin wrapper around WP core
  `sanitize_hex_color()`.
- `normalize( array $raw ): array{bg:?string,fg:?string,link:?string,border:?string}`
  — save-time; each field validated independently, invalid/empty → `null`.
- `resolve( int $post_id ): array{bg:?string,fg:?string,link:?string,border:?string}`
  — read-time; re-validates on read (defence-in-depth against direct DB
  edits or stale data), invalid → `null`.
- `has_any( array $style ): bool` — whether to emit any styling at all.

Blank/invalid resolves identically at save and read time because both paths
funnel through the same `sanitize_hex()` — no divergent inherit logic to
keep in sync. Absence of all four keys is the M6-identical default: the
fixed row renders exactly as it does today.

---

## 4. Sanitize/validation contract

Hex-only via WP core `sanitize_hex_color()` — no `rgba()`/`hsl()`/named
colours, no custom-CSS text field. The anchored hex regex
(`^#([A-Fa-f0-9]{3}){1,2}$`) structurally excludes `;`, `}`, `<`, `"`, `:` —
injection is impossible by character-set exclusion, not by escaping. Values
are only ever placed as the **value** of a CSS custom property, never in a
selector or property-name position, and never round-tripped through
`wp_kses`/AIML.

Invalid, empty, tampered, or unsupported values (at save or at read) resolve
to `null`, i.e. absent/inherit behaviour — never to a fallback colour, never
to an error.

---

## 5. Rendering mechanism

CSS custom properties on the existing `usa-announcement-fixed--{placement}`
wrapper span, built in `fixed_fragment()` **after** `sanitize_output()` runs
on the body (so it never touches the sanitize/AIML pipeline):

```php
$style      = FixedRowStyle::resolve( (int) $row['id'] );
$style_attr = FixedRowStyle::has_any( $style )
    ? ' style="' . esc_attr( FixedRowStyle::to_css_vars( $style ) ) . '"'
    : '';
```

emitting only the non-null properties, e.g.
`--usa-fixed-bg:#xxxxxx;--usa-fixed-fg:#xxxxxx;`.

`assets/css/announcement-bar.css` additions (appended near the existing
fixed block; the `prefers-reduced-motion` block is untouched):

```css
.usa-announcement-fixed {
    background: var(--usa-fixed-bg, transparent);
    color: var(--usa-fixed-fg, inherit);
}
.usa-announcement-fixed a {
    color: var(--usa-fixed-link, inherit);
}
.usa-announcement-fixed--above { border-bottom-color: var(--usa-fixed-border, currentColor); }
.usa-announcement-fixed--below { border-top-color: var(--usa-fixed-border, currentColor); }
```

Fallback values (`transparent`, `inherit`, `currentColor`) exactly reproduce
today's appearance when unconfigured. `.usa-announcement-fixed a` is scoped
by descendant selector under the row's own wrapper class, so link colour can
only ever match anchors inside that one fixed row's sanitized body — no
global rule, no leakage to rotating spans or the host `<p>`. Above/below
independence falls out for free: they're different DOM nodes with their own
`style` attributes.

**Rejected alternative:** per-row `wp_add_inline_style()` keyed by post ID
(mirroring the fade-duration precedent) — rejected because it requires
building a CSS **selector** string in PHP (heavier, needs a new `data-id`
attribute, duplicated between admin preview and front-end) for no additional
safety over custom properties, which stay a pure attribute-value
substitution.

---

## 6. Admin UX

Add 4 fields inside the **existing** `data-usa-display="fixed"` panel (no
new show/hide container — reuses `syncDisplayPanels()` as-is; controls are
never shown for rotating announcements). Each field is a checkbox + native
`<input type="color">` pair: checkbox unchecked → color input `disabled`
(disabled inputs aren't submitted, giving natural "absent" semantics that
match the meta model exactly, no hidden-field shadowing needed). Checkbox
initial state is derived server-side from whether the meta value is
currently set. Native `<input type="color">` avoids adding a
`wp-color-picker` script/style dependency.

Blank/unchecked is clearly labelled as "inherit the existing Store Notice
styling." Each field has its own reset/clear behaviour via the checkbox.

Save integration: immediately after the existing `DisplayMode::normalize()`
/ `update_post_meta` block (~line 644-649), add a matching block that calls
`FixedRowStyle::normalize()` on the 4 checkbox+color POST pairs and
`update_post_meta`/`delete_post_meta` per field — persisted unconditionally,
before any schedule-branch early return, matching M6's ordering guarantee.
Reuses the existing nonce (`usa_announcement_meta`) and capability check
(`Settings::manage_cap()`); no new nonce/capability introduced.

No separate preview system is introduced — the native colour swatches
themselves are the only "preview," which reuses existing WordPress controls.

---

## 7. Accessibility / contrast

Client-side only, advisory, never blocking. Admin JS (extending
`assets/js/announcement-editor.js`) computes the WCAG relative-luminance
contrast ratio between bg/fg and bg/link **only when both colours in the
pair are explicitly configured** (checkbox enabled + valid hex on both
sides). A meaningful ratio cannot be computed when the background is left to
inherit from the host theme (e.g. Blocksy/WooCommerce), since the actual
rendered background is unknown at edit time.

- When both sides of a pair are explicitly set and the ratio is below
  4.5:1, show an inline `role="status"` low-contrast warning next to the
  fields.
- When either side is inherited (unset), show no computed ratio — at most a
  small advisory note that contrast depends on host theme styling.
- Never disables Publish/Update, never blocks the POST. No server-side
  contrast check.

---

## 8. Schema / versioning decision

**No `Schema::VERSION` bump.** Same additive-defaulting-meta pattern as M6's
`_usa_display_mode`/`_usa_fixed_placement`: absence is the safe default, no
migration/backfill needed, and nothing elsewhere assumes the new meta's
presence. This will be stated explicitly in the M6.1 closure document,
mirroring M6's own note on the same decision.

---

## 9. Downgrade / rollback safety

Reverting to 0.6.1 code: 0.6.1's `fixed_fragment()` never reads the new meta
keys, so the wrapper span reverts to its unstyled M6 form. Leftover postmeta
rows are inert (never read, never rendered, no fatal). Re-upgrading to 0.6.2
picks the values back up automatically. Zero data loss either direction, no
manual cleanup required for a rollback.

---

## 10. Work packages (for the future implementation task)

- **WP1** — Data model & sanitize helper: `src/Announcement/FixedRowStyle.php` + `tests/unit/FixedRowStyleTest.php`.
- **WP2** — Renderer & CSS: `StoreNoticeRenderer::fixed_fragment()` + `assets/css/announcement-bar.css`; rendering tests.
- **WP3** — Admin UI: `AnnouncementMetaBoxes` render + `save()`; `assets/js/announcement-editor.js` checkbox-toggle + contrast warning.
- **WP4** — Tests: full suite (§11).
- **WP5** — Docs/closure: this document (frozen) + `docs/closure/m6-1-fixed-row-colour.md`.

The future implementation task must run the repository's `test-runner`
sub-agent for complete automated validation before merge. Targeted/focused
tests during implementation are necessary but not sufficient on their own.

---

## 11. Test plan (for the future implementation task)

`tests/unit/FixedRowStyleTest.php` (`@covers \USA\Announcement\FixedRowStyle`):
normalize accepts valid hex; rejects invalid/malformed/empty to `null`;
rejects CSS-injection payloads (`red;}body{display:none`,
`#fff</style><script>`, `javascript:alert(1)`) to `null`; resolve defaults to
`null` when meta absent; resolve ignores tampered/direct-DB-written malicious
meta (defence-in-depth); `has_any()` true/false cases.

Extend/add rendering tests (`@covers \USA\Rendering\StoreNoticeRenderer::filter_notice`):
unstyled fixed row renders byte-identical wrapper markup to pre-M6.1
baseline; styled fixed row emits scoped custom properties only for set
fields; above/below styled independently; rotating spans never receive a
`style` attribute regardless of fixed-row meta; malicious postmeta never
reaches output unneutralized (no `style` attribute emitted, no
`</style>`/`<script`/`javascript:` substrings anywhere); translated body
renders with unchanged wrapper style (AIML independence); scheduling/
collision winner-selection unaffected by style meta; downgrade-equivalence
(absent style meta ⇒ current M6 rendering, proving 0.6.1 compatibility).

Extend `tests/integration/StoreNoticeAttributePreservationTest.php`: assert
the outer WooCommerce host `<p>`'s own attributes remain untouched — the new
`style` only ever appears on the inner fixed-row `<span>`.

DEV fixture (executed only in the future implementation task): a light
"operational payment notice" fixed row `above` a dark rotating shipping-bar
message on DEV, screenshot both light/dark rows together, then restore the
fixture (delete/reset the demo announcement and any temporary style meta) as
a cleanup step.

---

## 12. Risk register

1. **CSS custom-property injection via non-hex fallback confusion** —
   mitigated by anchored-regex hex-only validation at both save and read;
   explicit malicious-payload unit tests.
2. **Admin JS show/hide regression** — new fields nested in the existing
   fixed-mode panel (not a second toggle container); distinct prefixed
   selectors (`data-usa-color-toggle`) to avoid colliding with
   `syncDisplayPanels()`; manual QA across mode transitions.
3. **Confusion with `price_allowed_html()`'s existing `style` allowance** on
   body price spans (`src/Announcement/Sanitizer.php:38-59`) — that governs
   sanitized body content passed through `wp_kses`; the new wrapper `style`
   is a different attribute on a different (outer) span, built outside
   `wp_kses` entirely. Mitigate with cross-referencing code comments and a
   test asserting both `style` attributes coexist without collision.
4. **Contrast footgun** — advisory-only by design; accepted risk, documented
   in metabox help text.
5. **Native `<input type="color">` has no "unset" state** — resolved via
   checkbox+disabled pairing; re-sync checkbox state from server-rendered
   PHP state on load (not client-only assumptions) to avoid bfcache/
   back-forward desync.

---

## 13. Release / deployment exclusions

This plan, and the future implementation task it authorizes, explicitly
exclude: a Git tag, a GitHub Release, a ZIP build/publication, an
update-server publication, a production deployment, or any external
configuration change. Version bump to `0.6.2` and DEV acceptance are
in scope for the implementation task; release packaging is a separate,
later, independently authorized step.

---

## 14. Commit and PR history

- Planning PR [#20](https://github.com/magpern/universal-site-announcements/pull/20)
  (branch `plan/m61-fixed-row-visual-styling`): documentation-only, added
  this plan as `DRAFT — pending PO approval`.
- Freeze commit (this section's edit) marks the plan `FROZEN — PO APPROVED`
  and merges PR #20 to `main`. No code, tag, release, ZIP, or DEV change is
  authorized by the freeze itself — only the separate implementation task
  described in §10-§13 above, executed on `feature/m61-fixed-row-visual-styling`
  from the post-freeze `main`.
