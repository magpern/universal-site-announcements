# M4 — Weekly Recurring Schedule

**Status:** FROZEN — PO APPROVED  
**Repository:** [magpern/universal-site-announcements](https://github.com/magpern/universal-site-announcements)  
**Plugin:** Universal Site Announcements (`universal-site-announcements`)  
**Namespace:** `USA\`  
**Parent architecture:** [MASTER_PLAN.md](MASTER_PLAN.md) (M0 frozen)  
**M1–M3 baselines:** M1–M3 plans and closure documents under `docs/plans/` and `docs/closure/`  
**Planning baseline `main` SHA:** `0c38245337f37fe6e73404adfb46cc6b73be86bc`  
**Baseline version:** 0.3.0  
**Implementation version:** **0.4.0**  
**Frozen:** 2026-08-23

---

## 1. Objective

Add a third announcement scheduling mode so administrators can run all-day weekly recurrence, optionally bounded by a date-only recurrence window, without building a general recurrence engine.

| Mode | Behaviour |
|------|-----------|
| **Always active** | No date or weekday restrictions (current empty-schedule behaviour) |
| **One-time date interval** | Existing Starts at / Ends at (exclusive) in site timezone, stored and compared in UTC |
| **Weekly recurring** | Active on one or more selected ISO weekdays, all day, in the WordPress site timezone; optionally limited by a date-only weekly recurrence window |

Example: active every Monday and Friday; or every Monday and Friday from 2026-09-01 through 2026-12-31 (inclusive calendar days).

### Explicit non-goals

- Time-of-day recurrence (e.g. “Mondays 09:00–17:00”).
- Monthly / yearly recurrence.
- Cron jobs or scheduled WP-Cron workers for schedule evaluation.
- A general RRULE / iCal recurrence engine.
- WooCommerce, Universal Multicurrency, provider, or merge-tag changes.
- Changes to the M3 announcement-source radio or source/token inference UX (deferred; see §7.1).
- Targeting, analytics, Elementor, design overhaul, production release/tag/ZIP.

> **In scope for M4:** optional date-only bounds on weekly recurrence (`Weekly starts on` / `Weekly ends after`). This is **not** a general “bounded RRULE” engine — it is a simple local-calendar window around weekday selection.

---

## 2. Audited M3 schedule baseline

| Item | Current behaviour |
|------|-------------------|
| Meta | `_usa_starts_at`, `_usa_ends_at` — MySQL UTC `Y-m-d H:i:s` |
| Empty both | Treated as always active |
| Evaluation | `ScheduleEvaluator::is_active( $starts, $ends, $now_utc )` — UTC compare; exclusive end |
| Admin | Site-timezone `datetime-local` inputs; convert to UTC on save |
| Selection | `Repository::get_active()` gates on schedule before source/template resolution |
| List UI | Schedule column summarises start/end in site timezone |
| Tests | Unit coverage for exclusive end, empty bounds, DST-oriented TZ fixtures |

One-time interval semantics and site-TZ ↔ UTC helpers remain the authority for the **interval** mode only. M4 must **not** overload or reinterpret `_usa_starts_at` / `_usa_ends_at` for weekly mode.

---

## 3. Data model

### 3.1 Schedule mode

| Meta key | Type | Values |
|----------|------|--------|
| `_usa_schedule_mode` | string | `always` \| `interval` \| `weekly` |

### 3.2 Weekdays (weekly mode)

| Meta key | Storage |
|----------|---------|
| `_usa_weekdays` | Ordered unique list of ISO weekday integers **1–7** (Monday=1 … Sunday=7), stored as a JSON array of integers (e.g. `[1,5]`) |

Validation: in `weekly` mode, at least one weekday in `1..7` is required on a successful save. Invalid integers rejected. Duplicates normalised away on save.

### 3.3 Interval meta (unchanged keys — interval mode only)

`_usa_starts_at` / `_usa_ends_at` continue to hold **UTC instants** for **`interval` mode only**. They are **ignored at evaluation time** when mode is `always` or `weekly`. Do not reuse these keys for the weekly recurrence window.

### 3.4 Weekly recurrence window (weekly mode — date-only)

| Meta key | Storage | Meaning |
|----------|---------|---------|
| `_usa_weekly_starts_on` | Canonical local calendar date `Y-m-d`, or empty | Optional “Weekly starts on” |
| `_usa_weekly_ends_on` | Canonical local calendar date `Y-m-d`, or empty | Optional “Weekly ends after” |

Rules:

- Dates are **WordPress site-timezone local calendar dates**, not UTC timestamps.
- Empty / missing = that boundary is absent.
- When **both** bounds are present, `weekly_starts_on` must be **≤** `weekly_ends_on`. Invalid reversed ranges are rejected without altering previously persisted schedule values.
- Do **not** invent window dates during migration; existing announcements keep indefinite weekly behaviour when these fields are empty.

### 3.5 New-post defaults

- `_usa_schedule_mode` = `always`
- `_usa_weekdays` absent or `[]`
- `_usa_weekly_starts_on` / `_usa_weekly_ends_on` absent or empty
- The UI must not present an invalid weekly configuration until the administrator selects Weekly recurring and chooses days.

---

## 4. Active-state algorithms

### 4.1 Effective mode resolution (read path)

```text
stored = _usa_schedule_mode
if stored is missing/blank:
  // Legacy inference until migration completes (and as permanent fallback for unread rows)
  if both starts and ends empty → treat as always
  else → treat as interval
else if stored not in {always, interval, weekly}:
  INVALID → suppress announcement + admin diagnostic
else:
  use stored
```

Corrupt `weekly` data must **never** be treated as Always active.

### 4.2 Dispatch

```text
mode = effective mode (§4.1)
if mode == always  → schedule-active
if mode == interval → UTC is_active(starts, ends, now_utc)
if mode == weekly  → weekly_is_active(weekdays, weekly_starts_on, weekly_ends_on, now, site_timezone)
                     (malformed/empty weekdays OR malformed weekly-window dates
                      → INVALID → suppress + diagnostic)
```

Enabled flag, publish status, priority, rotation, templates, and free-shipping rules are unchanged and apply only when the schedule says active.

### 4.3 One-time interval (unchanged)

- Admin enters site-local datetimes; save converts to UTC via existing helpers.
- Active iff `(starts empty OR now_utc >= starts)` AND `(ends empty OR now_utc < ends)`.
- Inclusive calendar end still uses exclusive “Ends at” = next day 00:00 site-local (existing M2 help text).

### 4.4 Weekly recurring (all-day + optional date window)

1. Obtain `now` as a UTC instant.
2. Convert `now` to the WordPress site timezone.
3. Read the local calendar date `Y-m-d` and the local ISO weekday `N` (Monday=1 … Sunday=7).
4. Parse `_usa_weekdays` JSON to a set of integers in `1..7`. If JSON is malformed, contains no valid values, or the set is empty while mode is `weekly` → **suppress** + diagnostic (not Always active).
5. Parse each present weekly-window field (`_usa_weekly_starts_on` / `_usa_weekly_ends_on`). Empty / missing remains a valid “absent boundary.” A **non-empty** value that is not a canonical local `Y-m-d` calendar date (or that fails calendar validation, e.g. `2026-02-31`) → **suppress** + authorized admin diagnostic (not Always active; do not ignore the corrupt bound or fall through to indefinite weekly). If both bounds parse successfully and `weekly_starts_on` > `weekly_ends_on` in stored data → likewise **suppress** + diagnostic.
6. Evaluate the optional recurrence window against the **local calendar date** (site TZ):
   - **No boundaries** → window always passes.
   - **Start only** → pass iff `local_date >= weekly_starts_on` (start inclusive at local midnight of that date).
   - **End only** → pass iff `local_date <= weekly_ends_on` (end inclusive for the whole local calendar day; recurrence ends at the following local midnight).
   - **Both** → pass iff `weekly_starts_on <= local_date <= weekly_ends_on`.
7. Active iff **both** are true:
   - the current local date falls within the optional recurrence window;
   - the current local ISO weekday `N` is in the selected weekday set.

Do not calculate weekdays in UTC or persist UTC weekday offsets. Do not use `_usa_starts_at` / `_usa_ends_at` for this window.

### 4.5 Boundary, DST, and timezone-change cases

| Case | Expected |
|------|----------|
| Monday 00:00:00 site-local | Active if Monday selected (and window allows); inactive if not |
| Sunday 23:59:59 site-local | Active if Sunday selected (and window allows) |
| Instant of next Monday 00:00:00 | Leaves Sunday; enters Monday |
| Local date == `weekly_starts_on` at 00:00 | Inside window (inclusive start) |
| Local date == `weekly_ends_on` any time that day | Inside window (inclusive end day) |
| Local date == day after `weekly_ends_on` at 00:00 | Outside window |
| Spring-forward / fall-back | Calendar weekday/date via site-TZ |
| WordPress site timezone changed | Weekly evaluation uses the new local weekday/date immediately; interval **display** in list/editor reformats stored UTC into the new site timezone |

---

## 5. Migration (schema v4 — batched, idempotent, resumable)

Target schema: bump `usa_schema_version` from **3** to **4** only when the v4 post migration has **fully completed** (every announcement post processed). Do **not** mark schema migration complete merely because the first batch ran.

### 5.1 Per-post inference (write)

For each announcement **without** an existing `_usa_schedule_mode`:

| Existing `_usa_starts_at` / `_usa_ends_at` | Set `_usa_schedule_mode` |
|-------------------------------------------|--------------------------|
| Both empty (missing or blank) | `always` |
| Either start or end non-empty | `interval` |

Rules:

- Do **not** invent weekdays.
- Do **not** invent `_usa_weekly_starts_on` / `_usa_weekly_ends_on` (leave empty → indefinite weekly if later switched to weekly).
- Do **not** overwrite posts that already have `_usa_schedule_mode`.
- Manual/free-shipping content, enable, priority, and interval values preserved.
- Process in **bounded batches** (paginated by post ID) suitable for larger sites; each batch write is idempotent.

### 5.2 Resumable batching without WP-Cron

Migration must resume without WP-Cron or background workers:

1. **Persisted migration state** (WordPress options, names chosen consistently with existing conventions), for example:
   - a status such as `pending` | `in_progress` | `complete`;
   - a **cursor** (last processed announcement post ID, or equivalent pagination token);
   - optional progress counters for diagnostics.
2. On plugin upgrade / bootstrap, if `usa_schema_version < 4` and status is not `complete`, run **one bounded batch** and persist the advanced cursor.
3. **Continuation trigger:** while status is `pending` / `in_progress`, run another bounded batch on authorized admin requests (e.g. USA admin screens / `load-*` for announcement list or settings — capability-gated). Each request advances at most one batch.
4. When a batch finds no remaining posts to migrate, set status to `complete` and only then set `usa_schema_version = 4`.
5. Re-entry after a partial upgrade (PHP timeout, deploy mid-migration) must continue from the persisted cursor, never reset to “schema 4 done.”
6. Front-end storefront requests must not be required to drive migration; admin-request continuation is sufficient. Read-time legacy inference (§5.3) covers behaviour until completion.

### 5.3 Read-time compatibility during partial upgrade

Until every post has mode meta (and as a permanent safety net for any missed row), **legacy inference on read** (§4.1) must preserve:

- no dates → always  
- either date → interval  

A partially completed migration must not change existing behaviour. Empty weekly-window fields remain compatible (indefinite weekly when mode is weekly).

### 5.4 Migration regression (required)

Announcements with interval metadata but **no** `_usa_schedule_mode` must evaluate as **intervals both before and after** the schema migration runs (including mid-migration while the cursor has not yet reached that post).

---

## 6. Mode switching and save failure

**Retain hidden values; do not clear on mode switch.**

| When admin selects… | Visible controls | On successful save |
|---------------------|------------------|--------------------|
| Always active | Hide dates, weekdays, and weekly window | Persist `mode=always`; leave interval + weekday + weekly-window meta as-is |
| One-time date interval | Show Starts at / Ends at | Persist `mode=interval` + interval dates; leave weekday + weekly-window meta as-is |
| Weekly recurring | Show weekdays + Weekly starts on / Weekly ends after | Persist `mode=weekly` + weekdays + weekly-window dates; leave interval meta as-is |

### 6.1 Save failures (retain prior schedule)

Show a validation error and **retain the previously persisted schedule configuration entirely** — including prior mode, interval dates, weekdays, and weekly-window dates. Do **not** save any submitted schedule fields from that request. Never coerce to `always`.

Applies at least to:

- Weekly mode with zero weekdays selected.
- Weekly mode with both window dates present and `weekly_starts_on` > `weekly_ends_on`.
- Malformed weekly-window date input that cannot be normalised to `Y-m-d`.

---

## 7. Administration UX

1. **Prominent schedule-mode selector** before date/weekday controls:
   - Always active  
   - One-time date interval  
   - Weekly recurring  
2. **Always active:** hide interval, weekday, and weekly-window controls.  
3. **One-time date interval:** existing Starts at / Ends at (exclusive) + timezone help.  
4. **Weekly recurring:**
   - Seven checkboxes (Monday–Sunday); help: all-day in the WordPress site timezone; no clock times for weekday selection.
   - **Weekly starts on** (optional date input).
   - **Weekly ends after** (optional date input).
   - Help text: bounds are all-day local calendar dates in the WordPress site timezone; start is inclusive; end includes the whole selected day; either may be left blank for indefinite / open-ended weekly recurrence.
5. Preserve template editor, merge-tag insert, product picker, and diagnostics from M3.  
6. List table schedule column:
   - `Always`
   - interval range (site-TZ formatted)
   - `Weekly: Mon, Fri` when no window
   - `Weekly: Mon, Fri (from 2026-09-01)` / `(until 2026-12-31)` / `(2026-09-01 – 2026-12-31)` when a window is present
7. Accessible labelling for mode, weekdays, and weekly-window fields.  
8. Server-side validation is authoritative; JS may assist only.

### 7.1 Sequencing note — source selector (deferred)

The M3 **Announcement source** radio remains **unchanged in M4**. Preserving it here is a compatibility constraint, **not** an endorsement of that UX as final. The derived-requirements / source-inference UX correction is the **next corrective patch immediately after M4** (expected 0.4.1-style follow-up) and must not be implemented in this milestone.

---

## 8. Compatibility

- M1–M3 Store Notice outer contract, rotation, reduced-motion, no-JS, templates, tokens, free-shipping hybrid UMC path: unchanged.
- Disable / deactivate rollback unchanged.
- Existing announcements after migration behave as before (always or interval).
- Existing posts later switched to weekly without window dates behave as indefinite weekly recurrence.

---

## 9. Work packages

| WP | Scope |
|----|--------|
| WP1 | Schema v4 batched migration with persisted cursor + admin-request continuation; read-time legacy inference |
| WP2 | `ScheduleEvaluator` weekly + optional window + invalid fail-closed; Repository dispatch |
| WP3 | Admin mode UI, weekday + weekly-window controls, save-failure retention, list column |
| WP4 | Tests, acceptance, README, closure; ship **0.4.0** |

---

## 10. Test and acceptance strategy

### Automated

- Always + interval behaviour unchanged (exclusive end); interval keys unused by weekly mode.
- Weekly: each weekday alone; multiple weekdays.
- Empty weekly selection rejected at save; prior schedule unchanged.
- Weekly window:
  - start boundary (inclusive local midnight of `weekly_starts_on`);
  - inclusive end-day boundary (active on `weekly_ends_on`; inactive at following local midnight);
  - each missing-boundary combination (none / start-only / end-only / both);
  - invalid reversed date range rejected at save; prior schedule unchanged;
  - **malformed persisted** `_usa_weekly_starts_on` / `_usa_weekly_ends_on` (and stored reversed bounds) → suppress + diagnostic at render time;
  - migration compatibility with empty new weekly-window fields (indefinite weekly).
- ISO Monday/Sunday and local midnight boundaries.
- DST spring-forward and fall-back (`Europe/Stockholm`) for weekday and window evaluation.
- **Site timezone change:** interval list/editor display uses new TZ; weekly eval follows new local weekday/date immediately.
- Unrecognised mode / malformed weekdays / empty weekly set → suppress + diagnostic (not Always).
- Migration: no dates → always; start-only / end-only / full → interval; explicit mode preserved; idempotent; **interval-without-mode evaluates as interval before and after migration**; partial-migration legacy read inference; **cursor resume** across multiple batches; **`usa_schema_version` remains below 4 until the final batch completes**.
- Priority / rotation with multiple announcements on the same weekday.
- Manual and free-shipping-template announcements under all three modes.
- Template / UMC / reduced-motion / no-JS / disable / deactivate regressions.

### Manual (DEV)

- Always, interval, Mon+Fri weekly (indefinite and with a window) via controlled fixtures.
- Mode switch hides but does not erase prior interval dates, weekdays, or weekly-window dates.
- Empty weekly selection and reversed weekly window cannot save.
- Host Store Notice attributes preserved.
- Restore DEV fixtures afterward.

---

## 11. Risk register

| Risk | Mitigation |
|------|------------|
| Evaluating weekdays in UTC | Always convert `now` to site TZ before `N` / local date |
| Reusing interval UTC meta for weekly window | Dedicated `_usa_weekly_starts_on` / `_usa_weekly_ends_on` as `Y-m-d` |
| Silent wipe of dates on mode switch | Retain meta; ignore unused keys |
| Empty weekday list coerced to always | Hard validation; abort schedule save |
| Reversed weekly window partially saved | Reject; retain prior persisted schedule |
| Corrupt weekdays treated as always | Fail closed + diagnostic |
| Corrupt / non-`Y-m-d` weekly-window meta treated as indefinite weekly | Fail closed + diagnostic at render |
| Large-site migration timeout / first-batch-only “complete” | Persisted cursor; admin-request continuation; schema version bumps only when status is complete |
| Scope creep / source UX | Explicit deferral to post-M4 corrective patch |

---

## 12. Architecture decisions

**No open PO decisions.** Locked recommendations:

| Topic | Decision |
|-------|----------|
| Mode enum | `always` \| `interval` \| `weekly` on `_usa_schedule_mode` |
| Weekdays | ISO 1–7 in `_usa_weekdays` JSON; ≥1 required on successful weekly save |
| Weekly timing | All-day local calendar day via site-TZ ISO weekday |
| Weekly window | Optional `_usa_weekly_starts_on` / `_usa_weekly_ends_on` as local `Y-m-d`; start inclusive; end inclusive whole day; either/both/none allowed |
| Interval meta | `_usa_starts_at` / `_usa_ends_at` remain UTC for `interval` only — never overloaded for weekly |
| Invalid stored data | Suppress + diagnostic for bad mode, weekdays, **and** malformed/reversed weekly-window dates |
| Mode switch | Retain hidden meta (interval, weekdays, weekly window) |
| Failed weekly save | Keep prior persisted schedule entirely |
| Migration | Schema 4 only when fully complete; batched with persisted cursor; continue on authorized admin requests (no WP-Cron); empty weekly-window fields; read-time legacy inference |
| Source UX | Untouched in M4; next task after M4 |
| Version | **0.4.0** |

---

## 13. Document history

| Version | Date | Notes |
|---------|------|-------|
| 0.1-draft | 2026-08-23 | Initial M4 draft for PO approval |
| 0.2-draft | 2026-08-23 | Incorporate fail-closed invalid data; batched migration; save-failure retention; source UX deferred; TZ/migration tests (pre-freeze) |
| 0.3-draft | 2026-08-23 | Add optional weekly recurrence window (`_usa_weekly_starts_on` / `_usa_weekly_ends_on`); remove bounded recurrence from non-goals |
| 1.0-frozen | 2026-08-23 | PO freeze: malformed weekly-window fail-closed; resumable batched migration with persisted cursor (no WP-Cron); schema version bumps only on full completion |

---

## 14. Closure evidence

**Implemented:** 0.4.0 — see [m4-weekly-recurring-schedule.md](../closure/m4-weekly-recurring-schedule.md).

| Evidence | Result |
|----------|--------|
| PHPUnit | 92 tests OK |
| PHPCS | Clean |
| DEV acceptance | Always / interval / Mon+Fri weekly / retention / validation / Store Notice attrs; fixtures restored |
| Schema | `usa_schema_version` = 4 on DEV after resumable batch completion |

Source-inference UX was **not** changed in M4 (next task).

