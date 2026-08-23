<?php
/**
 * ScheduleEvaluator unit tests (interval + weekly + window).
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use USA\Announcement\ScheduleEvaluator;

/**
 * UTC compare, weekly site-TZ evaluation, and window coverage.
 */
final class ScheduleEvaluatorTest extends TestCase {

	/**
	 * Reset meta / timezone stubs.
	 */
	protected function setUp(): void {
		$GLOBALS['usa_test_post_meta'] = array();
		$GLOBALS['usa_test_timezone']  = 'Europe/Stockholm';
		parent::setUp();
	}

	/**
	 * Exclusive end: active when now &lt; ends_at, inactive at/after end.
	 */
	public function test_exclusive_end_boundary(): void {
		$eval = new ScheduleEvaluator();
		$end  = '2026-01-01 00:00:00';

		$before = new DateTimeImmutable( '2025-12-31 23:59:59', new DateTimeZone( 'UTC' ) );
		$at     = new DateTimeImmutable( '2026-01-01 00:00:00', new DateTimeZone( 'UTC' ) );
		$after  = new DateTimeImmutable( '2026-01-01 00:00:01', new DateTimeZone( 'UTC' ) );

		$this->assertTrue( $eval->is_active( null, $end, $before ) );
		$this->assertFalse( $eval->is_active( null, $end, $at ) );
		$this->assertFalse( $eval->is_active( null, $end, $after ) );
	}

	/**
	 * Empty bounds mean always active.
	 */
	public function test_empty_bounds_always_on(): void {
		$eval = new ScheduleEvaluator();
		$now  = new DateTimeImmutable( '2026-06-15 12:00:00', new DateTimeZone( 'UTC' ) );

		$this->assertTrue( $eval->is_active( null, null, $now ) );
		$this->assertTrue( $eval->is_active( '', '', $now ) );
	}

	/**
	 * Inclusive start: active when now &gt;= starts_at.
	 */
	public function test_inclusive_start_boundary(): void {
		$eval  = new ScheduleEvaluator();
		$start = '2026-03-01 10:00:00';

		$before = new DateTimeImmutable( '2026-03-01 09:59:59', new DateTimeZone( 'UTC' ) );
		$at     = new DateTimeImmutable( '2026-03-01 10:00:00', new DateTimeZone( 'UTC' ) );

		$this->assertFalse( $eval->is_active( $start, null, $before ) );
		$this->assertTrue( $eval->is_active( $start, null, $at ) );
	}

	/**
	 * Site-local datetime-local input converts to UTC for Europe/Stockholm (CET/CEST).
	 */
	public function test_site_local_to_utc_winter_cet(): void {
		$eval = new ScheduleEvaluator();
		$utc  = $eval->site_local_to_utc( '2026-01-15T12:00', 'Europe/Stockholm' );
		$this->assertSame( '2026-01-15 11:00:00', $utc );
	}

	/**
	 * Summer CEST (DST) conversion for Europe/Stockholm.
	 */
	public function test_site_local_to_utc_summer_cest(): void {
		$eval = new ScheduleEvaluator();
		$utc  = $eval->site_local_to_utc( '2026-07-15T12:00', 'Europe/Stockholm' );
		$this->assertSame( '2026-07-15 10:00:00', $utc );
	}

	/**
	 * Round-trip UTC → site local for DST summer.
	 */
	public function test_utc_to_site_local_summer(): void {
		$eval  = new ScheduleEvaluator();
		$local = $eval->utc_to_site_local( '2026-07-15 10:00:00', 'Europe/Stockholm' );
		$this->assertSame( '2026-07-15T12:00', $local );
	}

	/**
	 * Through 31 Dec inclusive: ends at exclusive 1 Jan 00:00 site TZ.
	 */
	public function test_new_year_exclusive_end_fixture(): void {
		$eval     = new ScheduleEvaluator();
		$ends_utc = $eval->site_local_to_utc( '2026-01-01T00:00', 'Europe/Stockholm' );
		$this->assertSame( '2025-12-31 23:00:00', $ends_utc );

		$dec31 = new DateTimeImmutable( '2025-12-31 22:59:59', new DateTimeZone( 'UTC' ) );
		$jan1  = new DateTimeImmutable( '2025-12-31 23:00:00', new DateTimeZone( 'UTC' ) );

		$this->assertTrue( $eval->is_active( null, $ends_utc, $dec31 ) );
		$this->assertFalse( $eval->is_active( null, $ends_utc, $jan1 ) );
	}

	/**
	 * Legacy inference: empty dates → always; either date → interval.
	 */
	public function test_legacy_mode_inference(): void {
		$eval = new ScheduleEvaluator();
		$this->assertSame( 'always', $eval->infer_legacy_mode( '', '' ) );
		$this->assertSame( 'interval', $eval->infer_legacy_mode( '2026-01-01 00:00:00', '' ) );
		$this->assertSame( 'interval', $eval->infer_legacy_mode( '', '2026-01-01 00:00:00' ) );
		$this->assertSame( 'interval', $eval->infer_legacy_mode( '2026-01-01 00:00:00', '2026-02-01 00:00:00' ) );
	}

	/**
	 * Invalid stored mode fails closed.
	 */
	public function test_invalid_mode_suppresses(): void {
		$eval = new ScheduleEvaluator();
		update_post_meta( 1, ScheduleEvaluator::META_MODE, 'monthly' );
		$result = $eval->evaluate_post( 1, new DateTimeImmutable( '2026-06-01 12:00:00', new DateTimeZone( 'UTC' ) ) );
		$this->assertFalse( $result['active'] );
		$this->assertSame( 'schedule_invalid_mode', $result['diagnostic'] );
	}

	/**
	 * Each ISO weekday alone.
	 *
	 * @dataProvider weekday_provider
	 * @param int    $iso      ISO weekday.
	 * @param string $local_dt Local datetime in Europe/Stockholm.
	 */
	public function test_each_weekday_alone( int $iso, string $local_dt ): void {
		$eval = new ScheduleEvaluator();
		update_post_meta( 10, ScheduleEvaluator::META_MODE, 'weekly' );
		update_post_meta( 10, ScheduleEvaluator::META_WEEKDAYS, $eval->encode_weekdays( array( $iso ) ) );

		$local = new DateTimeImmutable( $local_dt, new DateTimeZone( 'Europe/Stockholm' ) );
		$utc   = $local->setTimezone( new DateTimeZone( 'UTC' ) );

		$active = $eval->evaluate_post( 10, $utc );
		$this->assertTrue( $active['active'], 'Expected active on ' . $local_dt );

		$other = $iso === 1 ? 2 : 1;
		update_post_meta( 10, ScheduleEvaluator::META_WEEKDAYS, $eval->encode_weekdays( array( $other ) ) );
		$inactive = $eval->evaluate_post( 10, $utc );
		$this->assertFalse( $inactive['active'] );
	}

	/**
	 * @return array<string, array{0:int,1:string}>
	 */
	public function weekday_provider(): array {
		// Fixed week in June 2026 (Mon 1 … Sun 7).
		return array(
			'monday'    => array( 1, '2026-06-01 12:00:00' ),
			'tuesday'   => array( 2, '2026-06-02 12:00:00' ),
			'wednesday' => array( 3, '2026-06-03 12:00:00' ),
			'thursday'  => array( 4, '2026-06-04 12:00:00' ),
			'friday'    => array( 5, '2026-06-05 12:00:00' ),
			'saturday'  => array( 6, '2026-06-06 12:00:00' ),
			'sunday'    => array( 7, '2026-06-07 12:00:00' ),
		);
	}

	/**
	 * Multiple weekdays (Mon+Fri).
	 */
	public function test_multiple_weekdays_mon_fri(): void {
		$eval = new ScheduleEvaluator();
		update_post_meta( 11, ScheduleEvaluator::META_MODE, 'weekly' );
		update_post_meta( 11, ScheduleEvaluator::META_WEEKDAYS, '[1,5]' );

		$mon = ( new DateTimeImmutable( '2026-06-01 12:00:00', new DateTimeZone( 'Europe/Stockholm' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) );
		$tue = ( new DateTimeImmutable( '2026-06-02 12:00:00', new DateTimeZone( 'Europe/Stockholm' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) );
		$fri = ( new DateTimeImmutable( '2026-06-05 12:00:00', new DateTimeZone( 'Europe/Stockholm' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) );

		$this->assertTrue( $eval->evaluate_post( 11, $mon )['active'] );
		$this->assertFalse( $eval->evaluate_post( 11, $tue )['active'] );
		$this->assertTrue( $eval->evaluate_post( 11, $fri )['active'] );
	}

	/**
	 * Monday / Sunday midnight boundaries in site TZ.
	 */
	public function test_monday_sunday_midnight_boundaries(): void {
		$eval = new ScheduleEvaluator();
		update_post_meta( 12, ScheduleEvaluator::META_MODE, 'weekly' );
		update_post_meta( 12, ScheduleEvaluator::META_WEEKDAYS, '[1,7]' );

		$sun_end = ( new DateTimeImmutable( '2026-06-07 23:59:59', new DateTimeZone( 'Europe/Stockholm' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) );
		$mon_start = ( new DateTimeImmutable( '2026-06-08 00:00:00', new DateTimeZone( 'Europe/Stockholm' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) );

		$this->assertTrue( $eval->evaluate_post( 12, $sun_end )['active'] );
		$this->assertTrue( $eval->evaluate_post( 12, $mon_start )['active'] );

		update_post_meta( 12, ScheduleEvaluator::META_WEEKDAYS, '[7]' );
		$this->assertFalse( $eval->evaluate_post( 12, $mon_start )['active'] );
	}

	/**
	 * Spring-forward local day still that weekday.
	 */
	public function test_dst_spring_forward_weekday(): void {
		// 2026-03-29 Europe/Stockholm spring forward; still Sunday.
		$eval = new ScheduleEvaluator();
		update_post_meta( 13, ScheduleEvaluator::META_MODE, 'weekly' );
		update_post_meta( 13, ScheduleEvaluator::META_WEEKDAYS, '[7]' );

		$before = ( new DateTimeImmutable( '2026-03-29 01:30:00', new DateTimeZone( 'Europe/Stockholm' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) );
		$after  = ( new DateTimeImmutable( '2026-03-29 03:30:00', new DateTimeZone( 'Europe/Stockholm' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) );

		$this->assertTrue( $eval->evaluate_post( 13, $before )['active'] );
		$this->assertTrue( $eval->evaluate_post( 13, $after )['active'] );
	}

	/**
	 * Fall-back local day still that weekday.
	 */
	public function test_dst_fall_back_weekday(): void {
		// 2026-10-25 Europe/Stockholm fall back; still Sunday.
		$eval = new ScheduleEvaluator();
		update_post_meta( 14, ScheduleEvaluator::META_MODE, 'weekly' );
		update_post_meta( 14, ScheduleEvaluator::META_WEEKDAYS, '[7]' );

		$early = ( new DateTimeImmutable( '2026-10-25 01:30:00', new DateTimeZone( 'Europe/Stockholm' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) );
		$late  = ( new DateTimeImmutable( '2026-10-25 02:30:00', new DateTimeZone( 'Europe/Stockholm' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) );

		$this->assertTrue( $eval->evaluate_post( 14, $early )['active'] );
		$this->assertTrue( $eval->evaluate_post( 14, $late )['active'] );
	}

	/**
	 * Weekly window start inclusive; end inclusive whole day; inactive next midnight.
	 */
	public function test_weekly_window_boundaries(): void {
		$eval = new ScheduleEvaluator();
		update_post_meta( 15, ScheduleEvaluator::META_MODE, 'weekly' );
		update_post_meta( 15, ScheduleEvaluator::META_WEEKDAYS, '[1]' ); // Monday.
		update_post_meta( 15, ScheduleEvaluator::META_WEEKLY_STARTS_ON, '2026-06-01' );
		update_post_meta( 15, ScheduleEvaluator::META_WEEKLY_ENDS_ON, '2026-06-08' );

		$before = ( new DateTimeImmutable( '2026-05-25 12:00:00', new DateTimeZone( 'Europe/Stockholm' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) ); // Mon before window.
		$start  = ( new DateTimeImmutable( '2026-06-01 00:00:00', new DateTimeZone( 'Europe/Stockholm' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) );
		$end_day = ( new DateTimeImmutable( '2026-06-08 23:59:59', new DateTimeZone( 'Europe/Stockholm' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) );
		$after  = ( new DateTimeImmutable( '2026-06-09 00:00:00', new DateTimeZone( 'Europe/Stockholm' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) ); // Tue after.

		$this->assertFalse( $eval->evaluate_post( 15, $before )['active'] );
		$this->assertTrue( $eval->evaluate_post( 15, $start )['active'] );
		$this->assertTrue( $eval->evaluate_post( 15, $end_day )['active'] );
		$this->assertFalse( $eval->evaluate_post( 15, $after )['active'] );
	}

	/**
	 * Missing-boundary combinations.
	 */
	public function test_weekly_window_missing_boundaries(): void {
		$eval = new ScheduleEvaluator();
		update_post_meta( 16, ScheduleEvaluator::META_MODE, 'weekly' );
		update_post_meta( 16, ScheduleEvaluator::META_WEEKDAYS, '[3]' ); // Wed 2026-06-03.

		$wed = ( new DateTimeImmutable( '2026-06-03 12:00:00', new DateTimeZone( 'Europe/Stockholm' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) );

		// None.
		$this->assertTrue( $eval->evaluate_post( 16, $wed )['active'] );

		// Start only.
		update_post_meta( 16, ScheduleEvaluator::META_WEEKLY_STARTS_ON, '2026-06-10' );
		$this->assertFalse( $eval->evaluate_post( 16, $wed )['active'] );
		delete_post_meta( 16, ScheduleEvaluator::META_WEEKLY_STARTS_ON );

		update_post_meta( 16, ScheduleEvaluator::META_WEEKLY_STARTS_ON, '2026-06-01' );
		$this->assertTrue( $eval->evaluate_post( 16, $wed )['active'] );
		delete_post_meta( 16, ScheduleEvaluator::META_WEEKLY_STARTS_ON );

		// End only.
		update_post_meta( 16, ScheduleEvaluator::META_WEEKLY_ENDS_ON, '2026-06-02' );
		$this->assertFalse( $eval->evaluate_post( 16, $wed )['active'] );
		update_post_meta( 16, ScheduleEvaluator::META_WEEKLY_ENDS_ON, '2026-06-03' );
		$this->assertTrue( $eval->evaluate_post( 16, $wed )['active'] );
	}

	/**
	 * Malformed / reversed window fail closed.
	 */
	public function test_malformed_and_reversed_weekly_window_fail_closed(): void {
		$eval = new ScheduleEvaluator();
		update_post_meta( 17, ScheduleEvaluator::META_MODE, 'weekly' );
		update_post_meta( 17, ScheduleEvaluator::META_WEEKDAYS, '[1]' );
		$now = new DateTimeImmutable( '2026-06-01 12:00:00', new DateTimeZone( 'UTC' ) );

		update_post_meta( 17, ScheduleEvaluator::META_WEEKLY_STARTS_ON, 'not-a-date' );
		$r = $eval->evaluate_post( 17, $now );
		$this->assertFalse( $r['active'] );
		$this->assertSame( 'schedule_malformed_weekly_window', $r['diagnostic'] );

		update_post_meta( 17, ScheduleEvaluator::META_WEEKLY_STARTS_ON, '2026-06-10' );
		update_post_meta( 17, ScheduleEvaluator::META_WEEKLY_ENDS_ON, '2026-06-01' );
		$r2 = $eval->evaluate_post( 17, $now );
		$this->assertFalse( $r2['active'] );
		$this->assertSame( 'schedule_reversed_weekly_window', $r2['diagnostic'] );
	}

	/**
	 * Malformed / empty weekdays fail closed.
	 */
	public function test_malformed_empty_weekdays_fail_closed(): void {
		$eval = new ScheduleEvaluator();
		update_post_meta( 18, ScheduleEvaluator::META_MODE, 'weekly' );
		$now = new DateTimeImmutable( '2026-06-01 12:00:00', new DateTimeZone( 'UTC' ) );

		update_post_meta( 18, ScheduleEvaluator::META_WEEKDAYS, '{bad' );
		$this->assertSame( 'schedule_malformed_weekdays', $eval->evaluate_post( 18, $now )['diagnostic'] );

		update_post_meta( 18, ScheduleEvaluator::META_WEEKDAYS, '[]' );
		$this->assertSame( 'schedule_empty_weekdays', $eval->evaluate_post( 18, $now )['diagnostic'] );

		update_post_meta( 18, ScheduleEvaluator::META_WEEKDAYS, '[0,8]' );
		$this->assertSame( 'schedule_malformed_weekdays', $eval->evaluate_post( 18, $now )['diagnostic'] );
	}

	/**
	 * Site timezone change affects weekly evaluation immediately.
	 */
	public function test_site_timezone_change_affects_weekly(): void {
		$eval = new ScheduleEvaluator();
		update_post_meta( 19, ScheduleEvaluator::META_MODE, 'weekly' );
		update_post_meta( 19, ScheduleEvaluator::META_WEEKDAYS, '[1]' ); // Monday local.

		// Instant that is Sunday evening in New York and Monday morning in Stockholm.
		$utc = new DateTimeImmutable( '2026-06-01 02:00:00', new DateTimeZone( 'UTC' ) );
		// Stockholm = UTC+2 → 04:00 Monday.
		$GLOBALS['usa_test_timezone'] = 'Europe/Stockholm';
		$this->assertTrue( $eval->evaluate_post( 19, $utc )['active'] );

		// America/New_York = UTC-4 → 22:00 Sunday.
		$GLOBALS['usa_test_timezone'] = 'America/New_York';
		$this->assertFalse( $eval->evaluate_post( 19, $utc )['active'] );
	}

	/**
	 * Interval display helper reformats into new site TZ.
	 */
	public function test_interval_display_follows_site_timezone_change(): void {
		$eval = new ScheduleEvaluator();
		$utc  = '2026-06-01 10:00:00';
		$this->assertSame( '2026-06-01T12:00', $eval->utc_to_site_local( $utc, 'Europe/Stockholm' ) );
		$this->assertSame( '2026-06-01T06:00', $eval->utc_to_site_local( $utc, 'America/New_York' ) );
	}

	/**
	 * Reversed window rejected by parse helper.
	 */
	public function test_parse_weekly_window_rejects_reversed(): void {
		$eval = new ScheduleEvaluator();
		$w    = $eval->parse_weekly_window( '2026-12-31', '2026-01-01' );
		$this->assertFalse( $w['ok'] );
		$this->assertSame( 'schedule_reversed_weekly_window', $w['diagnostic'] );
	}

	/**
	 * Mode always ignores interval and weekly meta.
	 */
	public function test_always_mode_ignores_other_meta(): void {
		$eval = new ScheduleEvaluator();
		update_post_meta( 20, ScheduleEvaluator::META_MODE, 'always' );
		update_post_meta( 20, ScheduleEvaluator::META_STARTS_AT, '2099-01-01 00:00:00' );
		update_post_meta( 20, ScheduleEvaluator::META_WEEKDAYS, '[]' );
		$now = new DateTimeImmutable( '2026-06-01 12:00:00', new DateTimeZone( 'UTC' ) );
		$this->assertTrue( $eval->evaluate_post( 20, $now )['active'] );
	}

	/**
	 * Missing mode with interval meta evaluates as interval (legacy).
	 */
	public function test_legacy_interval_without_mode(): void {
		$eval = new ScheduleEvaluator();
		update_post_meta( 21, ScheduleEvaluator::META_STARTS_AT, '2026-06-01 00:00:00' );
		update_post_meta( 21, ScheduleEvaluator::META_ENDS_AT, '2026-06-02 00:00:00' );
		$inside  = new DateTimeImmutable( '2026-06-01 12:00:00', new DateTimeZone( 'UTC' ) );
		$outside = new DateTimeImmutable( '2026-06-02 00:00:00', new DateTimeZone( 'UTC' ) );
		$this->assertTrue( $eval->evaluate_post( 21, $inside )['active'] );
		$this->assertFalse( $eval->evaluate_post( 21, $outside )['active'] );
		$this->assertSame( 'interval', $eval->evaluate_post( 21, $inside )['mode'] );
	}
}
