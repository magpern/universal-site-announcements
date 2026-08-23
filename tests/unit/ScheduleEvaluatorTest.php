<?php
/**
 * ScheduleEvaluator unit tests.
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
 * UTC compare and site-TZ conversion coverage.
 */
final class ScheduleEvaluatorTest extends TestCase {

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
		// 2026-01-15 12:00 Europe/Stockholm = UTC+1 → 11:00 UTC.
		$utc = $eval->site_local_to_utc( '2026-01-15T12:00', 'Europe/Stockholm' );
		$this->assertSame( '2026-01-15 11:00:00', $utc );
	}

	/**
	 * Summer CEST (DST) conversion for Europe/Stockholm.
	 */
	public function test_site_local_to_utc_summer_cest(): void {
		$eval = new ScheduleEvaluator();
		// 2026-07-15 12:00 Europe/Stockholm = UTC+2 → 10:00 UTC.
		$utc = $eval->site_local_to_utc( '2026-07-15T12:00', 'Europe/Stockholm' );
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
		$eval = new ScheduleEvaluator();
		// Ends at 1 Jan 00:00 Stockholm → 2025-12-31 23:00:00 UTC (CET).
		$ends_utc = $eval->site_local_to_utc( '2026-01-01T00:00', 'Europe/Stockholm' );
		$this->assertSame( '2025-12-31 23:00:00', $ends_utc );

		$dec31 = new DateTimeImmutable( '2025-12-31 22:59:59', new DateTimeZone( 'UTC' ) );
		$jan1  = new DateTimeImmutable( '2025-12-31 23:00:00', new DateTimeZone( 'UTC' ) );

		$this->assertTrue( $eval->is_active( null, $ends_utc, $dec31 ) );
		$this->assertFalse( $eval->is_active( null, $ends_utc, $jan1 ) );
	}
}
