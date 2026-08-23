# M4 — Weekly Recurring Schedule

**Status:** DRAFT — pending PO approval  
**Repository:** [magpern/universal-site-announcements](https://github.com/magpern/universal-site-announcements)  
**Plugin:** Universal Site Announcements (`universal-site-announcements`)  
**Namespace:** `USA\`  
**Parent architecture:** [MASTER_PLAN.md](MASTER_PLAN.md) (M0 frozen)  
**M1–M3 baselines:** M1–M3 plans and closure documents under `docs/plans/` and `docs/closure/`  
**Planning baseline `main` SHA:** `0c38245337f37fe6e73404adfb46cc6b73be86bc`  
**Baseline version:** 0.3.0  
**Recommended implementation version:** **0.4.0** (minor: new scheduling capability; non-breaking migration of existing announcements)

---

## 1. Objective

Add a third announcement scheduling mode so administrators can run all-day weekly recurrence without building a general recurrence engine.

| Mode | Behaviour |
|------|-----------|
| **Always active** | No date or weekday restrictions (current empty-schedule behaviour) |
| **One-time date interval** | Existing Starts at / Ends at (exclusive) in site timezone, stored and compared in UTC |
| **Weekly recurring** | Active on one or more selected ISO weekdays, all day, in the WordPress site timezone |

Example: active every Monday, or every Monday and Friday.

### Explicit non-goals

- Time-of-day recurrence (e.g. “Mondays 09:00–17:00”).
- Bounded recurrence (e.g. “every Monday only in December”).
- Monthly / yearly recurrence.
- Cron jobs or scheduled WP-Cron workers for schedule evaluation.
- WooCommerce, Universal Multicurrency, provider, or merge-tag changes.
- Targeting, analytics, Elementor, design overhaul, production release/tag/ZIP.

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

One-time interval semantics and site-TZ ↔ UTC helpers remain the authority for the interval mode. M4 extends the evaluator with an explicit mode rather than overloading empty dates.

---

## 3. Data model

### 3.1 Schedule mode

| Meta key | Type | Values |
|----------|------|--------|
| `_usa_schedule_mode` | string | `always` \| `interval` \| `weekly` |

Default after migration: inferred from existing date meta (see §5). New posts default to `always`.

### 3.2 Weekdays (weekly mode only)

| Meta key | Storage |
|----------|---------|
| `_usa_weekdays` | Ordered unique list of ISO weekday integers **1–7** (Monday=1 … Sunday=7), stored as a JSON array of integers (e.g. `[1,5]`) |

Validation: in `weekly` mode, at least one weekday in `1..7` is required. Invalid integers rejected. Duplicates normalised away on save.

### 3.3 Interval meta (unchanged keys)

`_usa_starts_at` / `_usa_ends_at` continue to hold UTC instants for **interval** mode only. They are **ignored at evaluation time** when mode is `always` or `weekly`.

---

## 4. Active-state algorithms

### 4.1 Dispatch

```text
mode = _usa_schedule_mode (after migration)
if mode == always  → active (schedule-wise)
if mode == interval → existing UTC is_active(starts, ends, now_utc)
if mode == weekly  → weekly_is_active(weekdays, now, site_timezone)
```

Enabled flag, publish status, priority, rotation, templates, and free-shipping rules are unchanged and apply only when the schedule says active.

### 4.2 One-time interval (unchanged)

- Admin enters site-local datetimes; save converts to UTC via existing helpers.
- Active iff `(starts empty OR now_utc >= starts)` AND `(ends empty OR now_utc < ends)`.
- Inclusive calendar end still uses exclusive “Ends at” = next day 00:00 site-local (existing M2 help text).

### 4.3 Weekly recurring (all-day)

1. Obtain `now` as a UTC instant (same as today).
2. Convert `now` to the WordPress site timezone (`wp_timezone_string()` / `DateTimeZone`).
3. Read the local ISO weekday: `N` where Monday=1 … Sunday=7 (`DateTimeImmutable::format( 'N' )` in the site TZ).
4. Active iff `N` is in the stored weekday set.

**Day bounds (conceptual):** a selected weekday is the local calendar day `[local 00:00, next local 00:00)`. No need to materialise UTC day bounds for evaluation if the ISO weekday of “now in site TZ” is used; that definition is equivalent and DST-safe.

### 4.4 Boundary and DST cases (required test fixtures)

| Case | Expected |
|------|----------|
| Monday 00:00:00 site-local | Active if Monday selected; inactive if not |
| Sunday 23:59:59 site-local | Active if Sunday selected |
| Instant of next Monday 00:00:00 | Leaves Sunday; enters Monday |
| Spring-forward local day (missing hour) | Still that calendar weekday via site-TZ `N` |
| Fall-back local day (repeated hour) | Still that calendar weekday via site-TZ `N` |
| Site timezone change | Subsequent evaluations use the new site TZ (no persisted “weekday in UTC”) |

Do not store weekdays as UTC offsets or fixed UTC hour ranges.

---

## 5. Migration (versioned, idempotent)

Extend `usa_schema_version` (M3 = 3) to **4** on M4 upgrade.

For each announcement post:

| Existing `_usa_starts_at` / `_usa_ends_at` | Set `_usa_schedule_mode` |
|-------------------------------------------|--------------------------|
| Both empty (missing or blank) | `always` |
| Either start or end non-empty | `interval` |

Additional rules:

- Do **not** invent weekdays during migration.
- Partially populated intervals (only start, or only end) map to `interval` and keep existing evaluator semantics.
- Posts that already have `_usa_schedule_mode` are left unchanged (idempotent re-run).
- Manual and free-shipping template content untouched.

---

## 6. Mode switching and retained values (recommendation locked)

**Recommendation: retain hidden values; do not clear on mode switch.**

| When admin selects… | Visible controls | On save |
|---------------------|------------------|---------|
| Always active | Hide dates and weekdays | Persist `mode=always`; leave existing start/end/weekday meta as-is (ignored at runtime) |
| One-time date interval | Show Starts at / Ends at | Persist `mode=interval` + date fields; leave weekday meta as-is |
| Weekly recurring | Show weekday checkboxes | Persist `mode=weekly` + weekdays; leave date meta as-is |

Rationale: switching modes accidentally must not destroy a carefully built interval or weekday set. Evaluation ignores irrelevant meta. Optional later “Clear unused schedule fields” is out of M4 scope.

Save-time validation must not silently rewrite the administrator’s mode: invalid weekly save (zero weekdays) shows an error and does not persist a coerced `always`.

---

## 7. Administration UX

1. **Prominent schedule-mode selector** in the announcement settings (before date/weekday controls), labelled clearly:
   - Always active  
   - One-time date interval  
   - Weekly recurring  
2. **Always active:** hide date and weekday controls; short description that the announcement follows only enable/priority/source rules.  
3. **One-time date interval:** existing Starts at / Ends at (exclusive) controls and timezone help text.  
4. **Weekly recurring:** seven checkboxes (Monday–Sunday) or equivalent multi-select; help text: “All day on selected weekdays, using the WordPress site timezone. No start/end clock times in this version.”  
5. Preserve source selector, template editor, merge-tag insert, product picker, and diagnostics from M3.  
6. List table schedule column: summarise mode (`Always` / interval range / `Weekly: Mon, Fri`).  
7. Accessible labelling (`fieldset`/`legend` or associated labels for mode and weekdays).

---

## 8. Compatibility

- M1–M3 Store Notice outer contract, rotation, reduced-motion, no-JS, templates, tokens, free-shipping hybrid UMC path: unchanged.
- Disable / deactivate rollback unchanged.
- Existing announcements after migration behave as before (always or interval).

---

## 9. Work packages (future implementation)

| WP | Scope |
|----|--------|
| WP1 | Schema v4 migration + mode meta defaults |
| WP2 | `ScheduleEvaluator` weekly algorithm + Repository dispatch |
| WP3 | Admin mode UI, weekday controls, validation, list column |
| WP4 | Tests, acceptance, README, closure; ship **0.4.0** |

---

## 10. Test and acceptance strategy

### Automated

- Always + interval behaviour unchanged (existing fixtures).
- Weekly: each weekday alone; multiple weekdays; empty selection rejected on save/validate.
- ISO Monday/Sunday boundaries; local midnight transitions.
- DST spring-forward and fall-back in a configured site timezone (e.g. `Europe/Stockholm`).
- Priority / rotation when two weekly announcements share a day.
- Manual and free-shipping-template announcements still schedule correctly under all three modes.
- Migration: empty dates → always; partial/full dates → interval; idempotent schema bump.
- No-JS / reduced-motion rendering regressions; disable/deactivate rollback.

### Manual (implementation milestone)

- Toggle modes in the editor; confirm controls show/hide without clearing data.
- Set Mon+Fri; verify front-end only on those local days (or clock-skewed fixtures).
- Confirm interval help text and exclusive end still correct.

---

## 11. Risk register

| Risk | Mitigation |
|------|------------|
| Evaluating weekdays in UTC | Always convert `now` to site TZ before `N` |
| Silent wipe of dates on mode switch | Retain meta; ignore unused keys |
| Empty weekday list stored as always | Hard validation error; no coerce |
| Scope creep into full RRULE engine | Explicit non-goals; all-day weekly only |

---

## 12. Architecture decisions

**No open PO decisions.** Locked recommendations:

| Topic | Decision |
|-------|----------|
| Mode enum | `always` \| `interval` \| `weekly` on `_usa_schedule_mode` |
| Weekdays | ISO 1–7 in `_usa_weekdays` JSON array; ≥1 required in weekly mode |
| Weekly timing | All-day local calendar day via site-TZ ISO weekday |
| Mode switch | Retain hidden meta; evaluate only the active mode’s fields |
| Migration | Empty dates → always; any date → interval; schema version 4 |
| Version | Implementation ships as **0.4.0** |

---

## 13. Document history

| Version | Date | Notes |
|---------|------|-------|
| 0.1-draft | 2026-08-23 | Initial M4 draft for PO approval |
