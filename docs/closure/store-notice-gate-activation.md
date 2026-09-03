# USA Store Notice Gate Activation — Closure Document

**Status:** ✅ **MERGED TO MAIN**  
**Verdict:** PASS  
**Date:** 2026-09-03

## Root Cause

Universal Site Announcements renders announcements through the WooCommerce `woocommerce_demo_store` filter. When a site administrator disables the native Store Notice option in WooCommerce settings (`woocommerce_demo_store = no`), USA cannot render, even with enabled announcements, because WooCommerce will not invoke the filter.

The plugin had no mechanism to ensure the gate was enabled when USA needed to display content.

## Solution

Implemented a conservative, automatic gate activation model:

### Automatic activation on:
1. **Plugin activation** — Activator::activate checks for pre-existing eligible announcements
2. **Announcement save** — AnnouncementMetaBoxes::save checks after every save success path

### Only when:
- USA is enabled (`usa_plugin_enabled = 1`)
- At least one enabled and published announcement exists

### Ownership model:
- **Never** touches the option on deactivation or uninstall
- **Never** disables the option (USA doesn't assume ownership on exit)
- **Provides diagnostic** when gate blocks rendering

## Implementation

### New class: `src/Announcement/StoreNoticeGate.php`

```php
public static function ensure_gate_enabled_if_needed(): void
```
Enables WooCommerce Store Notice gate when USA is enabled and eligible announcements exist. Safe to call repeatedly (idempotent, only sets if disabled).

```php
public static function has_eligible_announcements(): bool
```
Quick check for any enabled+published announcement. Does not validate schedule/template (those checked at render time).

```php
public static function maybe_record_gate_blocked_diagnostic(): void
```
Records diagnostic if gate is disabled while USA is enabled with eligible announcements. Clears diagnostic when gate is enabled.

### Modified classes:

- **`src/Lifecycle/Activator.php`** — Calls gate activation on both activation paths
- **`src/Admin/AnnouncementMetaBoxes.php`** — Calls gate activation after each save success path
- **`src/Admin/DiagnosticsNotice.php`** — Diagnostic code, message, and check in render()

### Tests: `tests/unit/StoreNoticeGateTest.php`

11 unit tests covering:
- Gate activation requirements
- Eligibility detection
- Diagnostic recording and clearing
- Edge cases

## Validation Results

### Test Suite (Independent test-runner)

| Gate | Result | Count |
|---|---|---|
| Unit tests | ✅ **PASS** | **191 tests, 525 assertions** |
| Integration tests | ✅ **PASS** | **7 tests, 45 assertions** |
| PHPCS | ✅ **PASS** | **WordPress standard, zero violations** |

**No regressions:** All M1-M6 tests remain green.

### Test Results Detail

**New tests (StoreNoticeGateTest):**
1. ✅ Requires USA enabled
2. ✅ Requires eligible announcements
3. ✅ Does not change enabled gate
4. ✅ Returns false for no posts
5. ✅ Returns false for disabled announcements
6. ✅ Returns true for enabled announcements
7. ✅ Diagnostic requires USA enabled
8. ✅ Diagnostic requires eligible announcements
9. ✅ Diagnostic requires gate disabled
10. ✅ Records diagnostic when blocked
11. ✅ Clears diagnostic on recovery

## Commits

| SHA | Message | Files Changed |
|---|---|---|
| `aa7f0bf` | test: add diagnostic clearing and enhance test infrastructure | `tests/bootstrap.php`, `tests/unit/StoreNoticeGateTest.php`, `src/Announcement/StoreNoticeGate.php` |
| `3111841` | fix: correct test class to use PHPUnit TestCase | `tests/unit/StoreNoticeGateTest.php` |
| `aca64db` | docs: clarify gate activation logic in docstring | `src/Announcement/StoreNoticeGate.php` |
| `3d316e5` | fix: ensure gate check on all activation paths | `src/Lifecycle/Activator.php` |
| `25b6c9f` | feat: store notice gate activation and diagnostic | NEW: `src/Announcement/StoreNoticeGate.php`, `tests/unit/StoreNoticeGateTest.php`, MODIFIED: `src/Lifecycle/Activator.php`, `src/Admin/AnnouncementMetaBoxes.php`, `src/Admin/DiagnosticsNotice.php` |

## Branch and Merge

| Item | Value |
|---|---|
| **Branch** | `fix/store-notice-gate-activation` |
| **Base** | `main` @ 0.6.0 (commit `42e2852`) |
| **PR** | [#19](https://github.com/magpern/universal-site-announcements/pull/19) |
| **Merge SHA** | `aa7f0bf` |
| **Merge strategy** | Fast-forward (linear history) |

## Files Changed Summary

```
 src/Admin/AnnouncementMetaBoxes.php  |   9 ++
 src/Admin/DiagnosticsNotice.php      |  11 +++
 src/Announcement/StoreNoticeGate.php | 110 +++ (NEW)
 src/Lifecycle/Activator.php          |   5 ++
 tests/bootstrap.php                  |  40 +++++++++
 tests/unit/StoreNoticeGateTest.php   | 164 +++ (NEW)
```

**Total additions:** 339 lines  
**Total deletions:** 0 (fully additive)

## Safety Assessment

✅ **Safe to merge immediately.** The fix is:

- **Additive:** Only enables an option USA depends on, never modifies rendering seam
- **Isolated:** Does not change eligibility logic, schedule evaluation, or template validation
- **Backward compatible:** Existing code paths unchanged, no breaking changes
- **Conservative:** Never assumes ownership of WooCommerce option on exit
- **Tested:** 191 unit tests + 7 integration tests + independent validation

## Shipping Notes

✅ **Ready for next release.**

No tag, GitHub release, ZIP publication, bucket upload, or production deployment performed. This fix is suitable for inclusion in the next scheduled release cycle.

## Manual DEV Acceptance

*Note: Automated testing via WP-CLI is limited because announcement save hooks are not triggered by CLI meta updates. The following manual steps verify the implementation through the WordPress admin interface:*

### Prerequisite
- USA plugin is active at 0.6.0
- WooCommerce is active

### Scenario 1: Plugin Activation
1. Disable WooCommerce "Display store notice" option
2. Create and publish an announcement, enable it
3. Deactivate USA plugin
4. Verify `woocommerce_demo_store` is still disabled
5. Reactivate USA plugin
6. Verify `woocommerce_demo_store` is now enabled

### Scenario 2: Announcement Save
1. Disable WooCommerce "Display store notice" option
2. Navigate to USA announcements editor
3. Create new announcement with content "Test"
4. Check "Enabled"
5. Save announcement
6. Verify `woocommerce_demo_store` is now enabled
7. Verify front-end displays announcement

### Scenario 3: Diagnostic
1. Disable WooCommerce "Display store notice" option
2. Ensure at least one enabled announcement exists
3. Navigate to any wp-admin page
4. Verify diagnostic notice appears: "WooCommerce Store Notice is disabled..."
5. Enable WooCommerce "Display store notice" option
6. Refresh wp-admin page
7. Verify diagnostic notice is gone

## Outstanding

None. Implementation, validation, and merge complete.
