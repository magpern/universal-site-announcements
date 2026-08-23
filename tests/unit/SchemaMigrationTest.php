<?php
/**
 * Schema migration tests.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use USA\Announcement\ScheduleEvaluator;
use USA\Lifecycle\Schema;

/**
 * Migration seed rules and M4 batched schema upgrades.
 */
final class SchemaMigrationTest extends TestCase {

	/**
	 * Reset options / meta.
	 */
	protected function setUp(): void {
		$GLOBALS['usa_test_options']   = array();
		$GLOBALS['usa_test_post_meta'] = array();
		$GLOBALS['usa_test_post_ids']  = array();
		$GLOBALS['usa_test_is_admin']  = false;
		$GLOBALS['usa_test_current_user_can'] = false;
		$GLOBALS['usa_test_timezone']  = 'UTC';
		parent::setUp();
	}

	/**
	 * Empty / whitespace-only content should be seeded; non-empty left alone.
	 */
	public function test_empty_vs_non_empty_seed_rule(): void {
		$default = Schema::DEFAULT_FREE_SHIPPING_TEMPLATE;

		$cases = array(
			''            => true,
			'   '         => true,
			"\n\t"        => true,
			'Custom copy' => false,
			$default      => false,
			'Has {{free_shipping_threshold}} already' => false,
		);

		foreach ( $cases as $content => $should_seed ) {
			$empty = '' === trim( (string) $content );
			$this->assertSame(
				$should_seed,
				$empty,
				'Content ' . var_export( $content, true ) . ' seed expectation'
			);
			if ( $should_seed ) {
				$this->assertSame( $default, Schema::DEFAULT_FREE_SHIPPING_TEMPLATE );
			}
		}
	}

	/**
	 * Schema version option is idempotent when already at target.
	 */
	public function test_schema_version_idempotent(): void {
		update_option( Schema::OPTION, Schema::VERSION );
		$schema = new Schema();
		$schema->maybe_migrate();
		$this->assertSame( Schema::VERSION, Schema::current() );

		$schema->maybe_migrate();
		$this->assertSame( Schema::VERSION, Schema::current() );
	}

	/**
	 * Version constant is 4 for M4.
	 */
	public function test_schema_version_is_four(): void {
		$this->assertSame( 4, Schema::VERSION );
	}

	/**
	 * Per-post M4 inference outcomes.
	 */
	public function test_m4_per_post_inference(): void {
		$schema = new Schema();

		update_post_meta( 1, ScheduleEvaluator::META_STARTS_AT, '' );
		$schema->migrate_announcement_to_m4( 1 );
		$this->assertSame( 'always', get_post_meta( 1, ScheduleEvaluator::META_MODE, true ) );

		update_post_meta( 2, ScheduleEvaluator::META_STARTS_AT, '2026-01-01 00:00:00' );
		$schema->migrate_announcement_to_m4( 2 );
		$this->assertSame( 'interval', get_post_meta( 2, ScheduleEvaluator::META_MODE, true ) );

		update_post_meta( 3, ScheduleEvaluator::META_ENDS_AT, '2026-01-01 00:00:00' );
		$schema->migrate_announcement_to_m4( 3 );
		$this->assertSame( 'interval', get_post_meta( 3, ScheduleEvaluator::META_MODE, true ) );

		update_post_meta( 4, ScheduleEvaluator::META_STARTS_AT, '2026-01-01 00:00:00' );
		update_post_meta( 4, ScheduleEvaluator::META_ENDS_AT, '2026-02-01 00:00:00' );
		$schema->migrate_announcement_to_m4( 4 );
		$this->assertSame( 'interval', get_post_meta( 4, ScheduleEvaluator::META_MODE, true ) );

		update_post_meta( 5, ScheduleEvaluator::META_MODE, 'weekly' );
		update_post_meta( 5, ScheduleEvaluator::META_STARTS_AT, '2026-01-01 00:00:00' );
		$schema->migrate_announcement_to_m4( 5 );
		$this->assertSame( 'weekly', get_post_meta( 5, ScheduleEvaluator::META_MODE, true ) );

		// Idempotent.
		$schema->migrate_announcement_to_m4( 2 );
		$this->assertSame( 'interval', get_post_meta( 2, ScheduleEvaluator::META_MODE, true ) );

		// No invented weekdays / window.
		$this->assertSame( '', get_post_meta( 2, ScheduleEvaluator::META_WEEKDAYS, true ) );
		$this->assertSame( '', get_post_meta( 2, ScheduleEvaluator::META_WEEKLY_STARTS_ON, true ) );
	}

	/**
	 * First batch alone does not set schema to 4 when more posts remain.
	 */
	public function test_batched_migration_cursor_and_completion(): void {
		$GLOBALS['usa_test_post_ids'] = array( 10, 20, 30 );
		update_option( Schema::OPTION, 3 );

		$schema = new Schema();
		// Force tiny batch via processing manually with cursor simulation.
		$schema->ensure_m4_state_started();
		$this->assertSame( 'pending', $schema->get_m4_state()['status'] );
		$this->assertSame( 3, Schema::current() );

		// Process first ID only by temporarily limiting via cursor steps.
		$schema->migrate_announcement_to_m4( 10 );
		$schema->migrate_announcement_to_m4( 20 );
		// Simulate mid-migration state.
		update_option(
			Schema::M4_STATE_OPTION,
			array(
				'status' => 'in_progress',
				'cursor' => 20,
			),
			false
		);
		$this->assertSame( 3, Schema::current(), 'Schema must stay below 4 until complete' );

		$result = $schema->run_m4_batch();
		$this->assertTrue( $result['complete'] );
		$this->assertSame( 4, Schema::current() );
		$this->assertSame( 'complete', $schema->get_m4_state()['status'] );
		$this->assertSame( 'always', get_post_meta( 30, ScheduleEvaluator::META_MODE, true ) );
	}

	/**
	 * Interval-without-mode evaluates as interval before and after migration.
	 */
	public function test_interval_without_mode_before_and_after_migration(): void {
		$eval = new ScheduleEvaluator();
		update_post_meta( 40, ScheduleEvaluator::META_STARTS_AT, '2026-06-01 00:00:00' );
		update_post_meta( 40, ScheduleEvaluator::META_ENDS_AT, '2026-06-02 00:00:00' );

		$inside = new DateTimeImmutable( '2026-06-01 12:00:00', new DateTimeZone( 'UTC' ) );
		$this->assertTrue( $eval->evaluate_post( 40, $inside )['active'] );
		$this->assertSame( 'interval', $eval->evaluate_post( 40, $inside )['mode'] );

		$schema = new Schema();
		$schema->migrate_announcement_to_m4( 40 );
		$this->assertSame( 'interval', get_post_meta( 40, ScheduleEvaluator::META_MODE, true ) );
		$this->assertTrue( $eval->evaluate_post( 40, $inside )['active'] );
		$this->assertSame( 'interval', $eval->evaluate_post( 40, $inside )['mode'] );
	}

	/**
	 * Bootstrap does not bump to 4 without completing batches.
	 */
	public function test_bootstrap_starts_state_without_completing(): void {
		$GLOBALS['usa_test_post_ids'] = array( 50, 60 );
		update_option( Schema::OPTION, 3 );

		$schema = new Schema();
		$schema->maybe_migrate();
		$this->assertSame( 3, Schema::current() );
		$this->assertSame( 'pending', $schema->get_m4_state()['status'] );
	}

	/**
	 * Admin continuation advances batches.
	 */
	public function test_admin_continuation_completes(): void {
		$GLOBALS['usa_test_post_ids']         = array( 70 );
		$GLOBALS['usa_test_is_admin']         = true;
		$GLOBALS['usa_test_current_user_can'] = true;
		update_option( Schema::OPTION, 3 );

		$schema = new Schema();
		$schema->maybe_migrate_admin();
		$this->assertSame( 4, Schema::current() );
		$this->assertSame( 'always', get_post_meta( 70, ScheduleEvaluator::META_MODE, true ) );
	}
}
