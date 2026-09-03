# M6 — Fixed-Slot Multilingual Announcement Rows Closure

**Status:** COMPLETE on `main` (implementation merged; tag/ZIP/production deploy separately authorized)  
**Plan:** [M6_FIXED_SLOT_MULTILINGUAL_ROWS.md](../plans/M6_FIXED_SLOT_MULTILINGUAL_ROWS.md) (FROZEN — PO APPROVED)  
**Plan freeze merge:** `0fc80e06bf26bfeb31892735a28d21f061093659` (PR #17)  
**Implementation:** PR #18, commit `08eb33e42f92825307cd2e65d059f70c7725d3a7`  
**Baseline version:** 0.5.2 → **USA version: 0.6.0**  
**AIML prerequisite:** unchanged (`MIN_AIML_VERSION = 1.7.0`); DEV runs Universal Multilingual 1.11.0  
**Closed:** 2026-09-03

---

## 1. Verdict

**M6 IMPLEMENTATION: PASS WITH LIMITATIONS.**

An announcement can be set to **Fixed slot** with position **Above** or **Below** the rotating
bar. Fixed announcements are ordinary `usa_announcement` posts: enabled state, priority,
schedule, source-authoritative template requirements, free-shipping uniqueness, AI Multilingual
body overlay, token resolution and sanitization behave exactly as for rotating announcements.
At most one fixed announcement renders per position (lowest priority, then lowest ID); losing
candidates are suppressed and never rotate. Fixed rows are composed inside the single
WooCommerce Store Notice paragraph, preserving the host element and its attributes.

Limitations are recorded in §5: two multilingual acceptance scenarios could not be executed on
DEV without driving the AI Multilingual translation pipeline against the live provider, and the
browser/assistive-technology observations are HTML/CSS-level rather than rendered-browser checks.

---

## 2. Scope delivered

| Area | Delivered |
|---|---|
| Data model | `_usa_display_mode` (`rotating`\|`fixed`), `_usa_fixed_placement` (`above`\|`below`); read-time defaults in new pure class `src/Announcement/DisplayMode.php`. **No migration, no `Schema::VERSION` bump** (`usa_schema_version` stays `4`). |
| Selection | `Repository::get_active()` additively returns `mode`/`placement`; `Selector::partition()` splits rotating rows from one fixed winner per placement using the existing `priority ASC, id ASC` comparator. |
| Collision | Identity `fixed_slot_collision:<placement>:<ids>` with the winner in `post_id`; `DiagnosticsNotice::clear_if_stale_prefix()` replaces the notice when the candidate set **or** the winner changes and clears it on recovery. Non-blocking editor warning; saving is never blocked. |
| Memo | `Selector::render_set()` memoizes the partition for the current request only — no persistence, object cache, or invalidation policy, and no semantic change. |
| Rendering | `ContentReplacer` contract intact plus `compose()`; fixed rows render as `span.usa-announcement-fixed` inside the one host `<p>`. No sibling notices, no second pipeline. |
| Assets | CSS gains block layout, a `currentColor` separator and an explicit reduced-motion rule. `announcement-bar.js` unchanged. CSS is enqueued when rotating **or** fixed content exists; JS and the inline transition only when rotating. |
| Admin | Display-mode radios with conditional placement panel, save persisted before the schedule early returns, `syncDisplayPanels()` in `announcement-editor.js`, list-table **Mode** column, live collision warning. |
| AIML | **Zero changes.** No file under `src/Integration/` was modified. |

---

## 3. Automated validation evidence

Independent full run by the repository `test-runner` sub-agent, entirely through Docker
(no PHP or Composer on the host), on the final implementation commit `08eb33e`:

| Gate | Command | Result |
|---|---|---|
| Dependencies | `composer install --no-interaction --prefer-dist` | exit 0 |
| Full suite | `vendor/bin/phpunit -c phpunit.xml.dist` | **187 tests, 557 assertions — PASS** |
| Unit suite | `vendor/bin/phpunit -c phpunit.xml.dist --testsuite unit` | 180 tests, 512 assertions — PASS |
| Integration suite | `vendor/bin/phpunit -c phpunit.xml.dist --testsuite integration` | 7 tests, 45 assertions — PASS |
| Coding standards | `composer phpcs` (WordPress-Extra) | exit 0, clean |
| Package build | `bash scripts/build-release-package.sh` | `universal-site-announcements-0.6.0.zip` + `.sha256`, 201 entries, single top-level directory |

The package build initially reported NOT RUN because the `composer:latest` image lacks `rsync`;
it was then executed with `rsync` present and passed. No test failures occurred at any point,
so no pre-existing-versus-regression triage was required.

GitHub Actions on PR #18: `PHPCS (WordPress-Extra)`, `Unit tests (PHP 8.1 / 8.3 / 8.4)` and
`Release package (build validation, no publish)` — all green.

New coverage: `DisplayModeTest`, `FixedSlotPartitionTest`, `FixedSlotSelectorTest`,
`RenderSetMemoEquivalenceTest`, `StoreNoticeRendererFixedTest`, `ListTableModeColumnTest`,
`CptPrivacyRollbackTest`, plus extensions to `DiagnosticsNoticeTest` and
`tests/integration/StoreNoticeAttributePreservationTest.php`. All pre-existing suites pass
unmodified, including `SchemaMigrationTest::test_schema_version_is_four`.

`RenderSetMemoEquivalenceTest` proves memoized and freshly evaluated selectors produce
byte-identical `render_set()`, `active_contents()`, `fixed_contents()`, `filter_notice()` output
and identical diagnostic transient state across 15 scenarios spanning M1–M5 behaviour and every
M6 combination.

---

## 4. DEV acceptance evidence (2026-09-03)

DEV updated from 0.5.1 to the implementation build: bind-mounted checkout fast-forwarded and
`composer install --no-dev` run through Docker (installs `yahnis-elsts/plugin-update-checker`
v5.7, required since 0.5.2 because DEV defines `PRIVATE_UPDATE_SERVER`). Site returned HTTP 200
and `wp plugin get` reported 0.6.0.

Fixture: temporary posts `M6-ACC A` (rotating, prio 10), `B` (rotating, prio 20), `K` (fixed
above, prio 5, KYC text with a link), `P` (fixed below, prio 10), `K2` (fixed above, prio 10).
The pre-existing announcement was disabled for the duration and restored afterwards.

| # | Scenario | Result |
|---|---|---|
| 1 | A + B only, no fixed | PASS — output identical in shape to 0.5.x: shell, two `__message` spans, pause button, no `.usa-announcement-fixed` |
| 2 | + K fixed above | PASS — one above row before the rotating spans; K absent from rotation |
| 3 | K moved to below | PASS — row moves after the rotating spans; nothing else changes |
| 4 | K above + P below | PASS — both rows, exactly one `<p>`, shell and button only around the rotating region |
| 5 | B disabled | PASS — non-rotating path: plain first message plus both fixed rows, no shell, no JS |
| 6 | A and B disabled | PASS — `<p>` carries only the two fixed spans; no empty rotating fragment; CSS enqueued, JS **not** enqueued |
| 7 | K weekly on a non-today weekday, then a past date window | PASS — the above row disappears entirely; no empty row; no diagnostic |
| 8 | K2 enabled, same placement, overlapping | PASS — only K rendered; diagnostic `fixed_slot_collision:above:6890,6892`, `post_id` 6890 |
| 9 | K priority raised to 15 | PASS — K2 wins; diagnostic replaced with the same code and `post_id` 6892 (stale-winner replacement proven live) |
| 10 | K2 given a disjoint weekly schedule | PASS — only K rendered; collision diagnostic cleared |
| 11 | Published Swedish overlay for the fixed body | **NOT EXECUTED** — see §5 |
| 12 | Swedish visitor with no published overlay | PASS — `?lang=sv` renders the source body for the fixed row and for rotating rows; no diagnostic recorded |
| 13 | Token-corrupt Swedish overlay | **NOT EXECUTED** — see §5 |
| 14 | Metadata-only change must not dirty translations | PASS (structural) — `git diff` confirms `AnnouncementMetaBoxes::maybe_mark_aiml_dirty` and every file under `src/Integration/` are unmodified; dirty marking still compares `post_content` only |
| 15 | Blocksy notice position top and bottom | PASS — both positions render exactly one store-notice paragraph containing both fixed rows; `data-position` preserved. The temporary `store_notice_position` theme mod was removed afterwards, restoring the original state (key absent) |
| 16 | Reduced motion / JavaScript off | PASS (HTML/CSS level) — served CSS carries `transition: none; animation: none` on `.usa-announcement-fixed` plus an explicit `prefers-reduced-motion` block; served HTML has the first rotating message `is-active` and both fixed rows as static content |
| 17 | Downgrade to 0.5.2, then back to 0.6.0 | PASS — on 0.5.2 the M6 meta is ignored and the fixed announcements rejoin the rotation with no error; 0.6.0 restores fixed behaviour |
| — | Editor save ordering | PASS — display meta persisted for all three schedule modes (`always`, `interval`, `weekly`), i.e. before the schedule branches' early returns; invalid input falls back to `rotating` without blocking the save |
| — | List-table Mode column | PASS — `Rotating` / `Fixed (above)` / `Fixed (below)` |
| — | PHP errors | None — no fatals, warnings, notices or deprecations in the container log for the whole acceptance window |

**Cleanup:** the five `M6-ACC` posts were force-deleted, the pre-existing announcement was
restored to enabled, the `usa_render_diagnostic` transient was cleared, `usa_schema_version`
remains `4`, `usa_rotation` remains unset (defaults), and the front-end notice returned to its
original content with HTTP 200.

---

## 5. Limitations

1. **Acceptance scenarios 11 and 13 were not executed.** Publishing a real Swedish translation of
   an announcement body — and then corrupting its merge tags — requires driving the AI Multilingual
   workspace/job/publication pipeline, which calls the configured live AI provider. That was
   outside M6's authorization. What *is* proven: the overlay seam is shared and mode-agnostic
   (`Repository::resolve_content()` calls `TemplateOverlay::apply( post_id, source )` before
   partition, and no AIML file changed), a unit test asserts a fixed row and a rotating row take
   the identical overlay path, and the missing-overlay fallback was verified live on DEV
   (scenario 12). The merge-tag multiset gate (`TemplateOverlay::token_signature_matches`) is
   unchanged and still covered by `TemplateOverlaySignatureTest`.
2. **Scenario 16 was verified at the HTML/CSS level, not in a browser.** No rendered-browser or
   screen-reader pass was performed. A VoiceOver/NVDA smoke check remains advisable but is not
   claimed as passed.
3. **Single-slot diagnostic.** As documented in the plan, one collision notice can be masked for
   up to an hour by an unrelated diagnostic occupying the transient. The live editor warning is
   independent of the transient and mitigates this.

---

## 6. Explicitly not done

No GitHub tag, no release, no ZIP publication, no update-server publish, no production
deployment, no cache-policy change, and no external site configuration change. No colour or
background settings were added — fixed rows inherit the host Store Notice styling, as approved
in plan §16 decision A. CPT registration (`public`, `show_in_rest`, `has_archive`, `rewrite`,
`query_var`, `exclude_from_search`) is unchanged, and `Deactivator` still only flushes rewrite
rules.

---

## 7. Key paths

| Path | Role |
|---|---|
| `src/Announcement/DisplayMode.php` | Mode/placement normalisation and read-time defaults |
| `src/Announcement/Selector.php` | `partition()`, `render_set()` memo, `fixed_contents()`, collision diagnostic sync |
| `src/Announcement/Repository.php` | Additive `mode`/`placement` row fields |
| `src/Rendering/ContentReplacer.php` | `compose()` alongside the unchanged host-element contract |
| `src/Rendering/StoreNoticeRenderer.php` | Composition and conditional asset enqueue |
| `src/Admin/DiagnosticsNotice.php` | Collision prefix, `clear_if_stale_prefix()`, actionable message |
| `src/Admin/AnnouncementMetaBoxes.php` | Editor fields, save ordering, collision warning |
| `src/Announcement/ListTable.php` | Mode column |
| `assets/css/announcement-bar.css` | Fixed-row layout and separator |
| `tests/support/AnnouncementFixture.php` | Shared stub-post fixture for pipeline tests |
