# M4 Closure — Weekly Recurring Schedule

**Status:** CLOSED  
**Date:** 2026-08-23  
**Version:** 0.4.0  
**Plan:** [docs/plans/M4_WEEKLY_RECURRING_SCHEDULE.md](../plans/M4_WEEKLY_RECURRING_SCHEDULE.md) (FROZEN)

---

## Delivered

| Area | Implementation |
|------|----------------|
| Schedule modes | `_usa_schedule_mode`: `always` \| `interval` \| `weekly` |
| Weekly | ISO weekdays JSON `_usa_weekdays`; all-day in WordPress site timezone |
| Weekly window | Optional `_usa_weekly_starts_on` / `_usa_weekly_ends_on` as local `Y-m-d` |
| Interval | Unchanged UTC `_usa_starts_at` / `_usa_ends_at` (exclusive end); unused outside interval mode |
| Dispatch | Central `ScheduleEvaluator::evaluate_post()` used by Repository |
| Fail-closed | Invalid mode / weekdays / weekly window → suppress + `schedule_*` diagnostic |
| Migration | Schema **4**; resumable batches with `usa_m4_migration` cursor; admin-request continuation; legacy read inference |
| Admin | Mode selector; weekday checkboxes; weekly window dates; list summaries |
| Version | 0.3.0 → **0.4.0** |

---

## Migration evidence

- DEV: `usa_schema_version` advanced to **4** via `Schema::run_m4_batch()`; announcement `6672` received `mode=always` (no interval dates).
- Empty weekly-window fields left unset (indefinite weekly when mode is weekly).
- Schema version remains below 4 until the final batch completes (unit-tested).

---

## DEV acceptance (fixtures restored)

| Check | Result |
|-------|--------|
| Always mode | Pass |
| Interval exclusive end | Pass |
| Weekly Mon+Fri + Monday midnight (Europe/Stockholm) | Pass |
| Mode switch retains weekdays / window / interval meta | Pass |
| Empty weekdays / reversed window rejected | Pass |
| Store Notice attribute preservation | Pass |
| Temporary fixtures deleted; `6672` FS announcement restored | Pass |

Automated: **92** PHPUnit tests OK; PHPCS clean.

---

## Rollback

Disable Universal Site Announcements or deactivate the plugin → upstream WooCommerce Store Notice restored. Schedule meta retained. No WooCommerce / shipping / UMC settings written by this milestone.

---

## Explicit non-goals confirmed

No time-of-day recurrence, monthly/yearly rules, WP-Cron schedule workers, general RRULE engine, production tag/ZIP/deploy.

**The derived announcement requirements / source-inference UX correction was not changed in M4 and is the next task.**
