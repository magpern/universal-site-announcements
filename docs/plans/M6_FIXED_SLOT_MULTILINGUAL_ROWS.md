# M6 — Fixed-Slot Multilingual Announcement Rows

**Status:** DRAFT — pending PO approval  
**Repository:** [magpern/universal-site-announcements](https://github.com/magpern/universal-site-announcements)  
**Plugin:** Universal Site Announcements (`universal-site-announcements`)  
**Namespace:** `USA\`  
**Parent architecture:** [MASTER_PLAN.md](MASTER_PLAN.md) (M0 frozen)  
**M1–M5 baselines:** plans and closure documents under `docs/plans/` and `docs/closure/`; multilingual baseline [M5B_AIML_TEMPLATE_OVERLAYS.md](M5B_AIML_TEMPLATE_OVERLAYS.md) (frozen) and [m5b-aiml-template-overlays.md](../closure/m5b-aiml-template-overlays.md)  
**Planning baseline `main` SHA:** `d752309ed8e7d77cad4032571f4b01664b34e5b2`  
**Baseline version:** 0.5.2 (M5-B shipped in 0.5.0, merge `0720018c9203bdeeddb6bc7fa5b6d0824c526754`; 0.5.1 diagnostics fix; 0.5.2 self-update wiring, no announcement runtime change)  
**AIML prerequisite:** `AimlCompatibility::MIN_AIML_VERSION = '1.7.0'` (unchanged); DEV runs Universal Multilingual 1.11.0  
**Recommended implementation version:** **0.6.0**  
**Implementation branch:** `feature/m6-fixed-slot-multilingual-rows` (from the post-freeze `main`)  
**Drafted:** 2026-09-03

---

## 1. Objective

Add one **optional fixed announcement row** that renders independently of the rotating announcement bar, for persistent operational messages (payment-provider notice, KYC notice) while ordinary announcements keep rotating.

| Aspect | Decision |
|---|---|
| Content model | A fixed row **is** a normal `usa_announcement` post. No new post type, no free-text Settings option, no option-backed message. |
| Modes | `Rotating` (existing behaviour, default for every existing and new post) or `Fixed slot` (excluded from rotation, rendered in one dedicated row). |
| Placement | Fixed slot only: `Above rotating announcements` or `Below rotating announcements`. |
| Cardinality | At most one rendered fixed row per placement. No empty row when no eligible fixed announcement exists. |
| Eligibility | Identical to rotating: `publish` status, `_usa_enabled`, schedule mode / date window / weekdays, valid source template, free-shipping rules. |
| Multilingual | Reuses the M5-B AIML body overlay path unchanged (§7). |
| Styling | Inherits the host WooCommerce Store Notice styling exactly as the rotating bar does (§9). |

### Explicit non-goals

- No new content type, taxonomy, Settings-page message field, or option-backed announcement model.
- No change to the rotation algorithm, rotation timing settings, pause control, or the deterministic priority/ID ordering.
- No second rendering pipeline, no sibling WooCommerce Store Notice elements, no change to WooCommerce core notice data (`woocommerce_demo_store_notice`, `is_store_notice_showing`), no Blocksy/Elementor dependency.
- No per-announcement text colour, background colour, typography, icon, or layout controls (§9, §16 decision A). CSS ownership stays as frozen in MASTER_PLAN §6.
- No second AIML integration, descriptor type, editor, or option-based translation source; no change to AIML identity constants, extraction field, dirty rule, or fallback semantics.
- No new merge tags or token grammar; no change to `SourceTokenRules`, `MergeTagParser`, `HtmlPlacementValidator`.
- No caching or cache-policy change: no transients/object cache for selection results, no new invalidation policy (a request-local memo is not a cache, §5.4).
- No UMC (Universal Multicurrency) truth-source change, no free-shipping provider change.
- No database migration, no `usa_schema_version` bump, no rewrite of existing post meta.
- No REST, archive, permalink, search, or public visibility change for `usa_announcement`.
- No production deployment, GitHub tag, release, ZIP, update-server publish, or external site configuration change.

> **In scope for M6:** two new post-meta keys with read-time defaults; a partition of the existing eligible set into rotating rows and per-placement fixed winners; composition of fixed-above / rotating / fixed-below fragments inside the single host Store Notice paragraph; a collision diagnostic; editor fields and a list-table column; tests; DEV acceptance.

---

## 2. Audited M5 baseline (facts this plan relies on)

All statements below were verified against `main` at the planning baseline SHA. The implementation must re-verify them before changing code.

### 2.1 Content model and privacy

- [`src/Announcement/PostType.php`](../../src/Announcement/PostType.php): `usa_announcement`, `public => false`, `show_ui => true`, `show_in_rest => false`, `has_archive => false`, `rewrite => false`, `query_var => false`, `exclude_from_search => true`, `supports => ['title','editor']`, `capability_type => 'post'`, `map_meta_cap => true`. Classic editor.
- Post meta in use: `_usa_enabled` (`'1'`/`'yes'`; blank treated as enabled in the editor, absent skipped at render), `_usa_priority` (default `10`, lower first), `_usa_source` (legacy, **not** authoritative — source is derived from `post_content`), schedule keys `_usa_schedule_mode` (`always|interval|weekly`), `_usa_starts_at`, `_usa_ends_at`, `_usa_weekdays` (JSON ISO weekdays), `_usa_weekly_starts_on`, `_usa_weekly_ends_on`.
- Every missing meta value is defaulted **at read time** (`Repository::read_priority`, `ScheduleEvaluator::resolve_mode`, editor). The M4 migration wrote `_usa_schedule_mode` only because legacy inference needed persisting; that is the sole precedent for a schema bump (`Schema::VERSION = 4`).

### 2.2 Selection pipeline (single seam, no caching)

[`src/Announcement/Repository.php`](../../src/Announcement/Repository.php) `get_active()`:

1. `get_posts( post_type usa_announcement, post_status publish, orderby ID ASC, -1 )`.
2. `_usa_enabled` gate.
3. `ScheduleEvaluator::evaluate_post()`; inactive ⇒ skipped, diagnostics recorded where the schedule is malformed.
4. `TemplateRequirements::analyse( post_content )` on the **source** body; invalid ⇒ skipped with `template_<reason>`.
5. Global free-shipping uniqueness: more than one active announcement requiring free shipping ⇒ **all** suppressed, `duplicate_free_shipping_announcements`.
6. `resolve_content()`: analyse source (authoritative for `derived_source` / free-shipping dependency) → `TemplateOverlay::apply( post_id, source_template )` (AIML body overlay, §7) → provider threshold resolution → `TemplateEngine::render()` (token resolution, then `Sanitizer::sanitize_output()`).
7. Rows `{ id, priority, content, source }` sorted by `priority ASC, id ASC` (comparator duplicated in [`Selector::pick_first()`](../../src/Announcement/Selector.php)).

[`src/Announcement/Selector.php`](../../src/Announcement/Selector.php) is a thin wrapper: `active_contents(): list<string>`, `first_content()`, static `pick_first()`. There is no caching; `active_contents()` is evaluated twice per front-end request (`maybe_enqueue_assets` and `filter_notice`).

### 2.3 Store Notice rendering seam

[`src/Rendering/StoreNoticeRenderer.php`](../../src/Rendering/StoreNoticeRenderer.php): registered only when `is_store_notice_showing()` exists; `add_filter( 'woocommerce_demo_store', filter_notice, 20, 2 )`, `add_action( 'wp_enqueue_scripts', maybe_enqueue_assets, 20 )`. `should_rotate( $count ) = $count >= 2 && Settings::is_rotation_enabled()`.

- 0 contents ⇒ returns `''`.
- Not rotating ⇒ `ContentReplacer::replace( $html, first content )`; no shell, no CSS/JS.
- Rotating ⇒ `<span class="usa-announcement-bar__message[ is-active]">…</span>` per message → `replace()` → `wrap_shell()` adds `<div class="usa-announcement-shell">` + pause `<button class="usa-announcement-bar__toggle">`. CSS/JS enqueued only here.

[`src/Rendering/ContentReplacer.php`](../../src/Rendering/ContentReplacer.php): locates the **single** `<p>` whose class list contains both `woocommerce-store-notice` and `demo_store`, strips only `display:none` from its `style`, preserves every other attribute (`role`, `aria-label`, `data-notice-id`, Blocksy `data-position`, theme classes), replaces the inner HTML, and can wrap the paragraph in the rotation shell. The upstream WooCommerce dismiss link is not retained (existing behaviour).

Host facts relevant to composition (WooCommerce 11.0.1 on DEV; Blocksy): WooCommerce core CSS positions every `.woocommerce-store-notice` absolutely at the top; Blocksy positions `.demo_store[data-position=bottom]` fixed at the bottom and adds a `:before` icon and padding; WooCommerce JS shows/hides **all** `.woocommerce-store-notice` elements and reads `data-notice-id` from the first. Two sibling Store Notice paragraphs would therefore overlap and duplicate landmarks — the fixed row **must** live inside the single host paragraph (§6).

### 2.4 Assets

- [`assets/css/announcement-bar.css`](../../assets/css/announcement-bar.css) owns only `.usa-announcement-shell`, `.usa-announcement-bar__message` (display/opacity/transition), `.usa-announcement-shell--rotating…`, `.usa-announcement-bar__toggle` (`border: 1px solid currentColor; background: transparent; color: inherit`) and a `prefers-reduced-motion` block. Header comment: the shell must not restyle the host bar.
- [`assets/js/announcement-bar.js`](../../assets/js/announcement-bar.js) boots per `.usa-announcement-shell`, bails on reduced motion, fewer than two `.usa-announcement-bar__message` elements, or a missing toggle; only then adds `usa-announcement-shell--rotating`.

### 2.5 Settings and styling controls

[`src/Settings.php`](../../src/Settings.php) holds exactly two options: `usa_plugin_enabled` and `usa_rotation` (`enabled`, `interval_ms`, `fade_ms`). **There are no text-colour, background-colour, or other per-announcement styling controls anywhere in USA.** Colours, typography and in-flow layout are host-theme owned (MASTER_PLAN §6). [`Sanitizer`](../../src/Announcement/Sanitizer.php) allows `a[href,target,rel]`, `strong`, `em`, `br` in manual content plus price-fragment tags in output.

### 2.6 Diagnostics

[`src/Admin/DiagnosticsNotice.php`](../../src/Admin/DiagnosticsNotice.php): single transient `usa_render_diagnostic` = `{ code, post_id, at }`, TTL one hour, **single slot** (`record_failure()` no-ops while any diagnostic is stored), `clear_if_recovered( code, post_id )`, `clear_if_recovered_prefix( prefix, post_id )`, `clear()`, AJAX dismiss, `message_for_code()` dispatch by exact code or prefix.

### 2.7 M5-B AIML integration (unchanged by M6)

- [`AimlCompatibility`](../../src/Integration/AimlCompatibility.php): version gate `>= 1.7.0` plus public-symbol probe; fail-closed.
- [`AimlIntegration`](../../src/Integration/AimlIntegration.php): locked identity `universal_site_announcements` / `announcement` / `body`; `extract_for_post()` extracts **only `post_content`**; `register_output_hooks()` intentionally empty.
- [`TemplateOverlay::apply( int $post_id, string $source_template ): string`](../../src/Integration/TemplateOverlay.php): visitor language via `aiml_visitor_language()`; default language ⇒ source; `ExtensionServices::resolver()->resolve( SourceSegmentReference, LanguageReference )`; unavailable/stale/unpublished/missing ⇒ source; overlay failing `TemplateRequirements::analyse()` ⇒ source + `template_<reason>`; merge-tag multiset mismatch (names, arguments, occurrence counts) ⇒ source + `overlay_token_signature_mismatch`; recovery clears that post's diagnostic only. Mode-agnostic: it receives a post ID and the source body, nothing else.
- Dirty marking ([`AnnouncementMetaBoxes::maybe_mark_aiml_dirty`](../../src/Admin/AnnouncementMetaBoxes.php)): `save_post_usa_announcement` @20, not autosave/revision, compat probe passes, and **`post_content` actually changed**. Meta-only changes never dirty translations.

---

## 3. Data model

### 3.1 New post meta (read-time defaults, no migration)

| Meta key | Values | Absent / invalid | Written by |
|---|---|---|---|
| `_usa_display_mode` | `rotating` \| `fixed` | `rotating` | editor save only |
| `_usa_fixed_placement` | `above` \| `below` | `above` (meaningful only when `fixed`) | editor save only (retained when switching back to rotating, M4 "retain hidden values" convention) |

Name rationale: "mode" is already the schedule vocabulary (`_usa_schedule_mode`, `resolve_mode`, `MODES`); `_usa_display_mode` avoids ambiguity.

### 3.2 New pure class `src/Announcement/DisplayMode.php`

```php
final class DisplayMode {
    public const META_MODE       = '_usa_display_mode';
    public const META_PLACEMENT  = '_usa_fixed_placement';
    public const MODE_ROTATING   = 'rotating';
    public const MODE_FIXED      = 'fixed';
    public const PLACEMENT_ABOVE = 'above';
    public const PLACEMENT_BELOW = 'below';
    public const MODES      = array( self::MODE_ROTATING, self::MODE_FIXED );
    public const PLACEMENTS = array( self::PLACEMENT_ABOVE, self::PLACEMENT_BELOW );

    /** @return array{mode:string,placement:?string} placement is null when rotating. */
    public static function normalize( string $mode_raw, string $placement_raw ): array;
    /** Reads both meta keys and normalizes. */
    public static function resolve( int $post_id ): array;
}
```

### 3.3 Defaults and compatibility

- Every existing announcement has no `_usa_display_mode` ⇒ resolves to `rotating` ⇒ **identical behaviour after upgrade**.
- New posts default to `rotating` (radio pre-selected; `Activator` seed unchanged — does not write display meta).
- **No `Schema::VERSION` bump.** No row needs a written value; `SchemaMigrationTest::test_schema_version_is_four` stays valid. Traceability is provided by version 0.6.0, `readme.txt` changelog and the closure document.
- `_usa_source`, schedule meta, priority semantics unchanged.

---

## 4. Selection: partition, fixed-slot winner, collision

### 4.1 Repository (additive only)

`Repository::get_active()` adds `mode` and `placement` (from `DisplayMode::resolve( $post->ID )`, read next to `read_priority`) to each row: `{ id, priority, content, source, mode, placement }`. Nothing else in `get_active()` / `resolve_content()` changes: enabled, schedule, source-authoritative requirements, free-shipping uniqueness (applies **across** fixed and rotating candidates), overlay, tokens, sanitization, and the `priority ASC, id ASC` sort are untouched.

### 4.2 Selector partition (pure, deterministic)

```php
/** @return array{
 *   rotating: list<row>,
 *   fixed: array{above: ?row, below: ?row},
 *   collisions: list<array{placement:string, winner_id:int, candidate_ids:list<int>}>
 * } */
public static function partition( array $rows ): array
```

- Rows with `mode !== 'fixed'` (including rows lacking the key) ⇒ `rotating`, in their existing order.
- For each placement, the fixed candidates are ordered with the **existing** comparator (`pick_first()`: priority ASC, then post ID ASC). The first is the **winner**; the rest are **suppressed** (never rendered, never rotated as a fallback).
- A placement with two or more eligible fixed candidates yields one `collisions` entry with `candidate_ids` sorted ascending (winner included).

### 4.3 Collision policy (recommended: runtime suppression + diagnostic; editor does not block)

Static prevention in the editor is rejected: two fixed announcements on the same placement with **disjoint schedules** (weekday KYC notice, weekend maintenance notice) are legitimate and cannot be distinguished from a true conflict at save time. Therefore:

- Runtime renders only the winner per placement (deterministic, §4.2).
- A collision **diagnostic** is recorded (§4.4).
- The editor shows a **non-blocking** warning listing other enabled fixed announcements on the same placement (§8.4). Saving is never blocked; no mode/placement combination is invalid.
- No global "fixed row enabled" switch: an eligible fixed announcement is sufficient; none ⇒ no row.

### 4.4 Collision diagnostic identity (correct under winner/set changes)

The single-slot transient model of `DiagnosticsNotice` is preserved. Collision identity = **placement + current competing eligible candidate set**, encoded in the code string:

```
fixed_slot_collision:<placement>:<id1,id2,...>     // ids ascending, winner included
```

- `DiagnosticsNotice::CODE_FIXED_SLOT_COLLISION_PREFIX = 'fixed_slot_collision:'`; `post_id` stored = winner (for the admin link).
- On every `render_set()` evaluation (§5.4 — once per request), per placement:
  1. Compute the current identity code, or none if fewer than two eligible fixed candidates.
  2. If the stored transient code starts with `fixed_slot_collision:<placement>:` and **differs** from the current code (set changed, winner changed, or collision ended) ⇒ **clear** it (new helper `clear_if_stale_prefix( $prefix, $current_code )`).
  3. If a collision exists and no transient is stored ⇒ `record_failure( $current_code, $winner_id )`.
- Result: a stale collision notice is replaced when the competing set or winner changes; recovery clears exactly the collision notice for that placement; the existing throttle for unrelated codes is unchanged; two placements colliding simultaneously share the single slot (documented limitation, same as all M2–M5 codes).
- `message_for_code()` gains a prefix branch producing one actionable sentence: placement, winning announcement, hidden announcement IDs, the rule "lowest priority, then lowest ID, wins", and a pointer to the **Mode** column / editor warning.
- Residual masking risk (another code occupying the slot for up to one hour) is accepted and mitigated by the live editor warning, which does not depend on the transient.

### 4.5 Free-shipping interaction

Unchanged. The global "exactly one free-shipping-dependent active announcement" rule runs before mode is read and therefore spans fixed and rotating candidates; a fixed free-shipping notice and a rotating one together are both suppressed with `duplicate_free_shipping_announcements`. The editor gate `has_other_enabled_free_shipping_dependent()` remains mode-agnostic.

---

## 5. Rendering composition

### 5.1 Principle

One host Store Notice paragraph in, one out. Fixed rows are composed **inside** the existing single `<p class="woocommerce-store-notice demo_store …">` as block-level `<span>` children (phrasing content, valid inside `<p>`), before and/or after the rotating content. No sibling Store Notices, no second pipeline.

### 5.2 Markup

Non-rotating (0 or 1 rotating row, or rotation disabled), with fixed rows:

```html
<p role="complementary" aria-label="Store notice" class="woocommerce-store-notice demo_store" data-notice-id="…" data-position="top">
  <span class="usa-announcement-fixed usa-announcement-fixed--above" data-usa-fixed="above">KYC notice …</span>
  first rotating content (sanitized)
  <span class="usa-announcement-fixed usa-announcement-fixed--below" data-usa-fixed="below">…</span>
</p>
```

Rotating (≥2 rotating rows and rotation enabled): the same paragraph with the existing `.usa-announcement-bar__message` spans in the middle, then `wrap_shell()` as today:

```html
<div class="usa-announcement-shell">
  <p …>[fixed above][<span class="usa-announcement-bar__message is-active">…</span><span class="usa-announcement-bar__message">…</span>][fixed below]</p>
  <button type="button" class="usa-announcement-bar__toggle" …>Pause announcements</button>
</div>
```

| State | Output |
|---|---|
| No rotating, no fixed | `''` (unchanged) |
| No fixed | byte-identical to 0.5.x output (regression requirement) |
| No rotating, one fixed | `<p …><span class="usa-announcement-fixed …">…</span></p>`; no shell, no button, no empty rotating fragment |
| Rotating + fixed | shell and button exactly as today; fixed spans inside the `<p>` |

Wrappers are emitted only when content exists. Fixed spans never carry `usa-announcement-bar__message`, so rotation CSS/JS cannot touch them. Data attribute `data-usa-fixed="above|below"` and the two classes are the only new public hooks (scoped styling and tests).

### 5.3 ContentReplacer contract (intact, minimally extended)

`ContentReplacer`'s **single-host-element and attribute-preservation contract is unchanged**: `locate_notice_paragraph()`, `opening_has_required_classes()`, `strip_display_none_from_opening_tag()`, `replace()` and `wrap_shell()` keep their behaviour, and `tests/integration/StoreNoticeAttributePreservationTest.php` must keep passing unmodified. It is **minimally extended** with one composition method:

```php
/** Compose fixed-above, rotating, and fixed-below fragments into one inner HTML string. */
public function compose( ?string $above_html, string $rotating_inner_html, ?string $below_html ): string
```

`StoreNoticeRenderer::filter_notice()` calls `compose()` and passes the result to the existing `replace()`; the shell path calls `wrap_shell()` unchanged. Failure handling (`unrecognised_outer_markup` ⇒ upstream HTML returned untouched) is unchanged.

### 5.4 Request-local `render_set()` memo (pure optimisation, strictly bounded)

`Selector::render_set()` memoizes `partition( repository->get_active() )` in a private property for the lifetime of the current PHP request; `active_contents()` (now rotating-only, same signature) and the new `fixed_contents(): array{above:?string, below:?string}` read from it; `reset()` exists for tests.

Constraints (mandatory):

- No transient, object cache, option, static-across-requests storage, or invalidation policy.
- No change to candidate eligibility, priority, rotation, translation-overlay resolution, token resolution, sanitization, diagnostics semantics, or rendered output. The memo only removes the duplicate evaluation between `maybe_enqueue_assets` and `filter_notice` (and the duplicate diagnostic recording that implies).
- Automated proof required (§11.1 `RenderSetMemoEquivalenceTest`): memoized and non-memoized evaluation produce byte-identical results for M1–M5 scenarios and every M6 combination.

### 5.5 Renderer changes

- `filter_notice()`: `$set = selector->render_set()`; sanitize rotating and fixed contents with `sanitize_output()` (as today); drop empties; nothing left ⇒ `''`; `$rotate = should_rotate( count( rotating ) )` (unchanged rule, **rotating rows only**); build the middle fragment (plain first content, or `__message` spans); `compose()` → `replace()` → `wrap_shell()` when rotating.
- `maybe_enqueue_assets()`: enqueue `usa-announcement-bar` CSS when rotating **or** a fixed row exists; inline `transition-duration`, JS and `wp_localize_script` only when rotating.
- `should_rotate()` semantics and `Settings` are unchanged; a lone rotating message plus a fixed row is still the non-rotating path.

### 5.6 Ordering guarantee (unchanged)

Per announcement: schedule gate → source requirements → AIML overlay → token multiset check → token resolution → sanitization → partition/priority → composition. Translation never runs after tokens; partition never runs before overlay.

---

## 6. CSS and JS

`assets/css/announcement-bar.css` additions (layout only; no colour/background; no animation):

```css
.usa-announcement-fixed { display: block; transition: none; animation: none; }
.usa-announcement-fixed--above { padding-bottom: .4em; margin-bottom: .4em; border-bottom: 1px solid currentColor; }
.usa-announcement-fixed--below { padding-top: .4em; margin-top: .4em; border-top: 1px solid currentColor; }
@media (prefers-reduced-motion: reduce) { .usa-announcement-fixed { transition: none; } } /* explicit; already none */
```

`currentColor` follows the toggle's existing inherit-from-host approach. Header comment updated to mention fixed rows.

`assets/js/announcement-bar.js`: **no change**. It scopes to `.usa-announcement-bar__message`; fixed spans are invisible to it. Reduced-motion and no-JS guarantees are unchanged: the fixed row is static server-rendered content with no transition, and the rotating region behaves exactly as in 0.5.x.

Known host-cosmetic items (record, not plugin defects): Blocksy's `.demo_store:before` icon precedes a block-level above-row; the pause toggle is vertically centred on the whole paragraph. Any adjustment belongs in `biopentra-blocksy-child`, outside M6.

---

## 7. Multilingual (M5-B reuse — mandatory invariants)

Fixed rows are `usa_announcement` posts, so **no AIML change of any kind is required or permitted** in M6:

| Invariant | How M6 satisfies it |
|---|---|
| Same integration, descriptor, editor | `AimlIntegration` untouched; identity constants locked (M5-B §5); extraction remains `post_content` only. |
| Same overlay resolution | `Repository::resolve_content()` calls `TemplateOverlay::apply( post_id, source )` before partition; mode/placement are not inputs to the overlay. |
| Same language context, compatibility gate, privacy, resolver | `aiml_visitor_language()`, `AimlCompatibility`, `ExtensionServices::resolver()` — unchanged, no new lookups. |
| Source body authoritative | Eligibility, schedule, token requirements, free-shipping dependency, provider behaviour, uniqueness are computed from the source body (§2.2 step 4/6). |
| Exact merge-tag multiset | `TemplateOverlay::token_signature_matches()` unchanged (names, arguments, occurrence counts). |
| Fallback | Stale, missing, unpublished, invalid, unsupported, or token-corrupt overlay ⇒ source body **for that fixed row only**; other rows unaffected. |
| Dirty marking | Body change ⇒ `aiml_mark_source_dirty( 'post', id )` under M5-B §10.3; changes to `_usa_display_mode`, `_usa_fixed_placement`, schedule, priority, enabled ⇒ **never** dirty (meta is not compared). |
| Runtime order | Overlay → tokens → sanitize → partition (§5.6). |

---

## 8. Administration UX

### 8.1 Editor fields (`AnnouncementMetaBoxes::render_box()`, after Priority, before Schedule)

- Fieldset **Display mode**, radios `usa_display_mode`:
  - `Rotating` — "Takes part in the rotating announcement bar (default)."
  - `Fixed slot` — "Always shown in its own row, outside the rotation."
- Conditional panel `<div class="usa-display-panel" data-usa-display="fixed" [hidden]>` with radios `usa_fixed_placement`: `Above rotating announcements` / `Below rotating announcements`. Help text: "Only one fixed announcement is shown per position. If several are active at the same time, the lowest priority (then lowest ID) wins. Enabled, schedule and template rules still apply."
- Priority help amended: "(lower first; for fixed slots decides which one is shown)".
- Server renders the correct `hidden` state; `assets/js/announcement-editor.js` gains `syncDisplayPanels()` mirroring `syncSchedulePanels()`.

### 8.2 Save (`AnnouncementMetaBoxes::save()`)

- Reuses the existing nonce `usa_announcement_meta` and `Settings::manage_cap()` check already at the top of `save()`.
- Display meta is persisted **before** the schedule-mode branches (which `return` early), immediately after the priority block.
- `DisplayMode::normalize( sanitize_key( $_POST['usa_display_mode'] ?? '' ), sanitize_key( $_POST['usa_fixed_placement'] ?? '' ) )`; invalid or missing ⇒ `rotating`; placement written only when fixed; never blocks save; no new error path.
- Switching rotating ⇄ fixed is a single radio change; the announcement immediately leaves/joins rotation on the next render. Body untouched ⇒ AIML not dirtied.

### 8.3 List table (`ListTable`)

New column **Mode** after Schedule: `Rotating` / `Fixed (above)` / `Fixed (below)` via `DisplayMode::resolve()`. Not sortable; no Quick Edit.

### 8.4 Editor collision warning

`render_fixed_collision_notice()` on `admin_notices` (modelled on `render_invalid_template_notice()`): when the current post is enabled and fixed, list other `publish` + enabled + fixed announcements with the same placement (helper modelled on `has_other_enabled_free_shipping_dependent()`), as a `notice-warning` with edit links: "Other enabled fixed announcements use the same position: …. Only one is shown at a time (lowest priority wins) unless their schedules do not overlap." Non-blocking; computed live (independent of the diagnostic transient).

### 8.5 Settings page

No new options. The **Operational status** table's "Last render diagnostic" row shows collision codes via the existing mechanism; no change required.

---

## 9. Styling boundary (locked for M6)

- Fixed rows render inside the host Store Notice paragraph and therefore inherit the theme's colour, background, font and padding exactly as the rotating bar does.
- USA has **no** per-announcement text-colour or background-colour controls today (§2.5); M6 does not add any and does not describe them as existing capabilities.
- The plugin owns only block layout and a `currentColor` separator on `.usa-announcement-fixed`. CSS ownership as frozen in MASTER_PLAN §6 is unchanged.
- Per-announcement colour/background settings would require new meta, a sanitised style-output path, admin controls, and a contrast/accessibility policy: a separate milestone requiring PO approval (§16 decision A). Out of M6 unless separately approved.

---

## 10. Compatibility, migration, rollback, privacy

- **Upgrade:** no migration; all existing announcements rotate as before; `usa_schema_version` stays `4`.
- **Deactivation:** `Deactivator` flushes rewrite rules only; posts and meta persist (unchanged). No `uninstall.php` exists; the two new keys are orphaned harmlessly like all other `_usa_*` meta.
- **Downgrade to 0.5.x:** new meta ignored; fixed announcements rejoin rotation; no errors, no data loss; re-upgrade restores fixed behaviour. Documented and tested as the rollback path.
- **Kill switch:** `usa_plugin_enabled = 0` returns upstream WooCommerce HTML untouched (unchanged).
- **Privacy / public surface:** CPT registration unchanged (`public`, `show_in_rest`, `has_archive`, `rewrite`, `exclude_from_search`); no REST, archive, permalink or search exposure; front-end exposes only the two new classes and `data-usa-fixed`.
- **Host neutrality:** no Blocksy/Elementor dependency; works with any theme that prints the WooCommerce Store Notice paragraph; unrecognised markup falls back exactly as today.
- **No cache-policy change; no UMC change; no WooCommerce option change.**

---

## 11. Test and acceptance strategy

### 11.1 Automated (PHPUnit, stub bootstrap; PHPCS WordPress-Extra)

Test infrastructure: `tests/bootstrap.php` `get_posts` stub gains per-post content via `$GLOBALS['usa_test_post_content']`. No WordPress install is required.

| File | Coverage |
|---|---|
| `tests/unit/DisplayModeTest.php` | `normalize()` matrix (absent ⇒ rotating/null; fixed+absent placement ⇒ above; fixed+below; garbage ⇒ defaults); `resolve()` via meta stub. |
| `tests/unit/FixedSlotPartitionTest.php` | `Selector::partition()`: fixed excluded from rotating; winner by priority then ID per placement; `collisions` with sorted `candidate_ids`; above+below; empty input; rows lacking `mode` ⇒ rotating. |
| `tests/unit/FixedSlotSelectorTest.php` | Real `Repository` + `Selector` over stub posts: `active_contents()` excludes fixed; `fixed_contents()` winner; disabled / schedule-inactive / weekday-inactive fixed row absent; >1 free-shipping across fixed+rotating suppresses all; collision records `fixed_slot_collision:<placement>:<ids>` with winner ID; set change replaces the code; recovery clears it; unrelated codes untouched. |
| `tests/unit/RenderSetMemoEquivalenceTest.php` | **Memo proof:** for each scenario, fresh `Selector` (reset) vs memoized second call ⇒ byte-identical `render_set()`, `active_contents()`, `fixed_contents()`, `filter_notice()` output and identical diagnostic transient state. Scenarios: M1–M5 (0/1/N rotating, rotation disabled, schedule-inactive, free-shipping duplicate suppression, invalid template, overlay-incompatible fallback) and all M6 combinations (above / below / both, zero rotating + fixed, collision overlapping and disjoint). |
| `tests/unit/StoreNoticeRendererFixedTest.php` | `filter_notice()` end-to-end: no fixed ⇒ output byte-identical to 0.5.x fixtures; above only / below only / both with 0, 1, 2 rotating; exactly one `<p`; attributes preserved; no shell when not rotating; shell + button when rotating; `''` when nothing eligible; fixed span never contains `usa-announcement-bar__message`; upstream unchanged when plugin disabled; token resolution and sanitization order (token output present, disallowed markup stripped) for a fixed row. |
| `tests/unit/DiagnosticsNoticeTest.php` (extend) | collision code record / stale-prefix replacement / clear on recovery / message mapping. |
| `tests/unit/ListTableModeColumnTest.php` | `format_mode()` strings. |
| `tests/integration/StoreNoticeAttributePreservationTest.php` (extend, existing cases unmodified) | `compose()` + `replace()` on a fixture with `role`, `aria-label`, `data-position="bottom"`, `style="color:red; display:none;"`: single `<p`, attributes preserved, `display:none` stripped, fixed spans present, `wrap_shell` still places the button after `</p>`. |

Must remain green and unmodified: `CoreBehavioursTest`, `AdminDiscoverabilitySettingsTest`, `SchemaMigrationTest` (incl. `test_schema_version_is_four`), `ScheduleEvaluatorTest`, `TemplateEngineTest`, `TemplateRequirementsTest`, `TemplateOverlaySignatureTest`, `AimlCompatibilityTest`, `EligibilityGateTest`, `FreeShippingProviderContractTest`, `MergeTagTemplateTest`, `DiagnosticsNoticeTest` existing cases.

### 11.2 Validation gates (implementation → merge)

| Gate | Requirement |
|---|---|
| Local | `composer phpcs` clean; `vendor/bin/phpunit -c phpunit.xml.dist` (both suites) green; run via Docker (no host PHP on the VPS). |
| **Independent full run** | The implementation agent hands the **final implementation commit / working-tree state** to the repository `test-runner` sub-agent, which independently runs the **complete** automated suite (`composer install`, `vendor/bin/phpunit -c phpunit.xml.dist`, `composer phpcs`) and reports pass/fail with output. **Targeted tests alone are insufficient for merge.** |
| Failure handling | Any failure ⇒ stop before merge; distinguish pre-existing failures from regressions **with evidence** (same command on the baseline SHA); resolve every introduced regression before proceeding. |
| CI | `phpcs`, `unit` matrix (8.1/8.3/8.4) and `package` jobs green on the PR. |
| Review | PR review confirms non-goals (§1) and invariants (§7, §9, §5.4) hold. |

### 11.3 Manual DEV acceptance (controlled fixture)

Preconditions: DEV updated to the implementation build (bind-mounted checkout fast-forwarded, `composer install` for vendor); Universal Multilingual ≥ 1.7.0 active with a non-default language configured; record existing announcements (IDs, enabled state) before starting.

Fixture (all created as new posts, titles prefixed `M6-ACC`): `A` rotating prio 10, `B` rotating prio 20, `K` fixed-above prio 5 (KYC text with a link), `P` fixed-below prio 10 (payment-provider text), `K2` fixed-above prio 10.

| # | Scenario | Expected |
|---|---|---|
| 1 | Only A, B (no fixed) | Output and rotation identical to 0.5.x; no `.usa-announcement-fixed`. |
| 2 | + K enabled | Above row once; A/B still rotate; K absent from rotation; CSS enqueued, JS unchanged. |
| 3 | K → placement below | Row moves below; nothing else changes. |
| 4 | + P | Above and below rows; single `<p>`; shell/button only around rotating region. |
| 5 | Disable B | Non-rotating path: A plain + fixed rows; no shell, no JS. |
| 6 | Disable A and B | `<p>` contains only fixed rows; no empty rotating fragment. |
| 7 | K schedule weekly, non-today weekday; then date window past | K disappears; no empty row; diagnostic-free. |
| 8 | K2 enabled (same placement, overlapping) | Only K shown (prio 5 < 10); admin notice `fixed_slot_collision:above:<K,K2>`; editor warning on K and K2. |
| 9 | Change K prio to 15 | K2 now wins; diagnostic replaced with the new identity (same IDs, new winner link). |
| 10 | Give K2 a disjoint weekly schedule (inactive today) | Only K; collision diagnostic cleared; editor warning still lists K2 (informational). |
| 11 | Translate K body in AIML workspace, publish; visit non-default language | Fixed row shows translation; rotating rows behave as in M5-B. |
| 12 | Edit K body (mark dirty); visit non-default language | Source body shown for K only; rotating translations unaffected. |
| 13 | Corrupt K translation merge tags (add `{{product:N}}`); visit | Source body shown; `overlay_token_signature_mismatch` for K; other rows unaffected. |
| 14 | Change K placement/priority only | AIML translation **not** marked dirty. |
| 15 | Blocksy store notice position top and bottom | Fixed rows inside the bar in both positions; no overlap; no duplicated bar. |
| 16 | `prefers-reduced-motion: reduce`; JS disabled | Fixed rows static; rotating shows first message only; no animation added. |
| 17 | Downgrade checkout to 0.5.2, then back to 0.6.0 | Fixed rows rotate on 0.5.2 without error; fixed behaviour returns on 0.6.0. |

Cleanup / restoration: trash and permanently delete the `M6-ACC` posts; delete transient `usa_render_diagnostic`; restore the recorded enabled state of pre-existing announcements; remove the test translations in AIML; confirm `usa_schema_version = 4` and `usa_rotation` unchanged. No production deployment.

---

## 12. Work packages

| WP | Content |
|---|---|
| WP1 | `DisplayMode` class + `DisplayModeTest`. |
| WP2 | `Repository::get_active()` additive row fields; `Selector::partition()`, `render_set()` memo, `fixed_contents()`, rotating-only `active_contents()`; `DiagnosticsNotice` collision prefix/helper/message; `FixedSlotPartitionTest`, `FixedSlotSelectorTest`, `DiagnosticsNoticeTest` extension, `RenderSetMemoEquivalenceTest` (M1–M5 scenarios first). |
| WP3 | `ContentReplacer::compose()`; `StoreNoticeRenderer::filter_notice()` / `maybe_enqueue_assets()`; CSS additions; bootstrap post-content stub; `StoreNoticeRendererFixedTest`; integration test extension; extend memo-equivalence test with M6 combinations. |
| WP4 | Editor fields, save ordering, `announcement-editor.js` panel sync, list-table Mode column, live collision warning; `ListTableModeColumnTest`. |
| WP5 | Version 0.6.0 in the three canonical places + `readme.txt` changelog + README "Fixed-slot rows (M6)" section; PHPCS; full-suite run via `test-runner`; implementation PR. |
| WP6 | DEV acceptance (§11.3), closure document `docs/closure/m6-fixed-slot-multilingual-rows.md`, plan §17 closure evidence. |

---

## 13. Risk register

| Risk | Mitigation |
|---|---|
| Host theme CSS floats/absolutely positions `.woocommerce-store-notice` children unexpectedly | Fixed rows are inline-to-block spans inside the host paragraph; no positioning owned by USA; DEV acceptance on Blocksy top/bottom. |
| Collision notice masked by another diagnostic in the single slot | Documented; live editor warning independent of the transient. |
| Memo hides a behavioural difference | Equivalence test mandatory (§5.4/§11.1); memo is request-scoped only. |
| Save ordering regression (display meta not persisted for interval/weekly) | Persist before the schedule branches; tests cover all three schedule modes. |
| Free-shipping token in a fixed row collides with an existing rotating one | Existing uniqueness rule + editor gate apply; documented behaviour. |
| Blocksy icon/padding cosmetics with an above row | Host-owned; child-theme follow-up outside M6. |

---

## 14. Architecture decisions (proposed, to be locked at freeze)

| Topic | Decision |
|---|---|
| Content model | `usa_announcement` only; two meta keys with read-time defaults; no migration, no schema bump. |
| Partition | `Selector::partition()` pure; winner by existing `priority ASC, id ASC`. |
| Collision | Runtime suppression + identity-encoded diagnostic (placement + candidate set) + non-blocking editor warning; editor never blocks. |
| Composition | Inside the single host `<p>`; `ContentReplacer` contract intact, extended by `compose()`; no sibling notices; no second pipeline. |
| Memo | Request-local only; equivalence-tested; not a cache. |
| Styling | Inherit host; no colour controls; CSS ownership unchanged. |
| AIML | Zero changes; M5-B invariants reaffirmed. |
| Version / branches | 0.6.0; `feature/m6-fixed-slot-multilingual-rows`; closure doc as in M4/M5-B. |

---

## 15. Explicit exclusions (release / deployment)

- No production deployment, no GitHub tag, no release, no ZIP, no update-server publish in M6 implementation or acceptance.
- No DEV configuration change beyond fast-forwarding the bind-mounted checkout to the implementation build for acceptance (and restoring it as recorded).
- No cache-policy, UMC, WooCommerce-option, theme, or unrelated-plugin change.

---

## 16. Remaining PO decisions (genuine)

| Decision | Recommendation |
|---|---|
| A. Per-announcement text/background colour controls | **Exclude from M6** (would reverse MASTER_PLAN §6 CSS ownership; separate milestone if wanted). Fixed rows inherit host styling. |
| B. Collision handling | **Runtime suppression + diagnostic + non-blocking editor warning** (disjoint schedules on one placement stay legitimate). |
| C. Version and baseline | **0.6.0** on top of `origin/main` 0.5.2; DEV must be updated to 0.5.2 (incl. vendor) before M6 acceptance. |

---

## 17. Document history

| Version | Date | Notes |
|---|---|---|
| 0.1-draft | 2026-09-03 | Initial draft from architecture audit at `d752309e`; pending PO approval. |
