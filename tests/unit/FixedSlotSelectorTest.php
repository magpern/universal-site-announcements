<?php
/**
 * Repository + Selector integration over stub announcement posts.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Admin\DiagnosticsNotice;
use USA\Announcement\DisplayMode;
use USA\Announcement\ScheduleEvaluator;
use USA\Announcement\Selector;
use USA\Tests\Support\AnnouncementFixture;

/**
 * @covers \USA\Announcement\Selector
 * @covers \USA\Announcement\Repository
 */
final class FixedSlotSelectorTest extends TestCase {

	use AnnouncementFixture;

	protected function setUp(): void {
		$this->reset_world();
	}

	protected function tearDown(): void {
		$this->reset_world();
	}

	/**
	 * Fixed-slot meta helper.
	 *
	 * @param string               $placement Placement.
	 * @param array<string,string> $extra     Extra meta.
	 * @return array<string,string>
	 */
	private function fixed( string $placement, array $extra = array() ): array {
		return array_merge(
			array(
				DisplayMode::META_MODE      => DisplayMode::MODE_FIXED,
				DisplayMode::META_PLACEMENT => $placement,
			),
			$extra
		);
	}

	public function test_fixed_announcement_is_absent_from_rotation(): void {
		$this->add_announcement( 1, 'Rotating one' );
		$this->add_announcement( 2, 'KYC notice', $this->fixed( DisplayMode::PLACEMENT_ABOVE ) );

		$selector = $this->make_selector();

		$this->assertSame( array( 'Rotating one' ), $selector->active_contents() );
		$this->assertSame( 'KYC notice', $selector->fixed_contents()[ DisplayMode::PLACEMENT_ABOVE ] );
		$this->assertNull( $selector->fixed_contents()[ DisplayMode::PLACEMENT_BELOW ] );
	}

	public function test_disabled_fixed_announcement_is_not_rendered(): void {
		$this->add_announcement( 1, 'Rotating one' );
		$this->add_announcement( 2, 'KYC notice', $this->fixed( DisplayMode::PLACEMENT_ABOVE, array( '_usa_enabled' => '0' ) ) );

		$selector = $this->make_selector();

		$this->assertNull( $selector->fixed_contents()[ DisplayMode::PLACEMENT_ABOVE ] );
		$this->assertSame( array( 'Rotating one' ), $selector->active_contents() );
	}

	public function test_schedule_inactive_fixed_announcement_is_not_rendered(): void {
		$this->add_announcement(
			2,
			'KYC notice',
			$this->fixed(
				DisplayMode::PLACEMENT_ABOVE,
				array(
					ScheduleEvaluator::META_MODE     => ScheduleEvaluator::MODE_INTERVAL,
					ScheduleEvaluator::META_STARTS_AT => '2000-01-01 00:00:00',
					ScheduleEvaluator::META_ENDS_AT   => '2000-01-02 00:00:00',
				)
			)
		);

		$selector = $this->make_selector();

		$this->assertNull( $selector->fixed_contents()[ DisplayMode::PLACEMENT_ABOVE ] );
		$this->assertSame( array(), $selector->active_contents() );
	}

	public function test_weekly_schedule_without_today_hides_the_fixed_row(): void {
		$today    = (int) gmdate( 'N' );
		$other    = 7 === $today ? '1' : (string) ( $today + 1 );
		$this->add_announcement(
			2,
			'KYC notice',
			$this->fixed(
				DisplayMode::PLACEMENT_ABOVE,
				array(
					ScheduleEvaluator::META_MODE     => ScheduleEvaluator::MODE_WEEKLY,
					ScheduleEvaluator::META_WEEKDAYS => '[' . $other . ']',
				)
			)
		);

		$this->assertNull( $this->make_selector()->fixed_contents()[ DisplayMode::PLACEMENT_ABOVE ] );
	}

	public function test_priority_decides_the_fixed_winner_and_losers_never_rotate(): void {
		$this->add_announcement( 5, 'KYC low prio', $this->fixed( DisplayMode::PLACEMENT_ABOVE, array( '_usa_priority' => '5' ) ) );
		$this->add_announcement( 6, 'KYC high prio', $this->fixed( DisplayMode::PLACEMENT_ABOVE, array( '_usa_priority' => '10' ) ) );

		$selector = $this->make_selector();

		$this->assertSame( 'KYC low prio', $selector->fixed_contents()[ DisplayMode::PLACEMENT_ABOVE ] );
		$this->assertSame( array(), $selector->active_contents() );
	}

	public function test_collision_records_identity_encoded_diagnostic(): void {
		$this->add_announcement( 5, 'KYC A', $this->fixed( DisplayMode::PLACEMENT_ABOVE, array( '_usa_priority' => '5' ) ) );
		$this->add_announcement( 6, 'KYC B', $this->fixed( DisplayMode::PLACEMENT_ABOVE, array( '_usa_priority' => '10' ) ) );

		$this->make_selector()->render_set();

		$data = get_transient( DiagnosticsNotice::TRANSIENT_KEY );
		$this->assertIsArray( $data );
		$this->assertSame( 'fixed_slot_collision:above:5,6', $data['code'] );
		$this->assertSame( 5, (int) $data['post_id'] );
	}

	public function test_diagnostic_is_replaced_when_the_winner_changes(): void {
		$this->add_announcement( 5, 'KYC A', $this->fixed( DisplayMode::PLACEMENT_ABOVE, array( '_usa_priority' => '5' ) ) );
		$this->add_announcement( 6, 'KYC B', $this->fixed( DisplayMode::PLACEMENT_ABOVE, array( '_usa_priority' => '10' ) ) );

		$this->make_selector()->render_set();
		$this->assertSame( 5, (int) get_transient( DiagnosticsNotice::TRANSIENT_KEY )['post_id'] );

		// Same candidate set, new winner: the stale notice must be replaced.
		update_post_meta( 5, '_usa_priority', '20' );
		$this->make_selector()->render_set();

		$data = get_transient( DiagnosticsNotice::TRANSIENT_KEY );
		$this->assertIsArray( $data );
		$this->assertSame( 'fixed_slot_collision:above:5,6', $data['code'] );
		$this->assertSame( 6, (int) $data['post_id'] );
	}

	public function test_diagnostic_is_replaced_when_the_candidate_set_changes(): void {
		$this->add_announcement( 5, 'KYC A', $this->fixed( DisplayMode::PLACEMENT_ABOVE, array( '_usa_priority' => '5' ) ) );
		$this->add_announcement( 6, 'KYC B', $this->fixed( DisplayMode::PLACEMENT_ABOVE, array( '_usa_priority' => '10' ) ) );
		$this->make_selector()->render_set();

		$this->add_announcement( 7, 'KYC C', $this->fixed( DisplayMode::PLACEMENT_ABOVE, array( '_usa_priority' => '30' ) ) );
		$this->make_selector()->render_set();

		$this->assertSame( 'fixed_slot_collision:above:5,6,7', get_transient( DiagnosticsNotice::TRANSIENT_KEY )['code'] );
	}

	public function test_diagnostic_clears_when_the_collision_ends(): void {
		$this->add_announcement( 5, 'KYC A', $this->fixed( DisplayMode::PLACEMENT_ABOVE, array( '_usa_priority' => '5' ) ) );
		$this->add_announcement( 6, 'KYC B', $this->fixed( DisplayMode::PLACEMENT_ABOVE, array( '_usa_priority' => '10' ) ) );
		$this->make_selector()->render_set();
		$this->assertNotFalse( get_transient( DiagnosticsNotice::TRANSIENT_KEY ) );

		update_post_meta( 6, '_usa_enabled', '0' );
		$this->make_selector()->render_set();

		$this->assertFalse( get_transient( DiagnosticsNotice::TRANSIENT_KEY ) );
	}

	public function test_collisions_on_the_other_placement_do_not_clear_each_other(): void {
		$this->add_announcement( 5, 'A', $this->fixed( DisplayMode::PLACEMENT_ABOVE, array( '_usa_priority' => '5' ) ) );
		$this->add_announcement( 6, 'B', $this->fixed( DisplayMode::PLACEMENT_ABOVE, array( '_usa_priority' => '10' ) ) );
		$this->add_announcement( 7, 'C', $this->fixed( DisplayMode::PLACEMENT_BELOW, array( '_usa_priority' => '5' ) ) );
		$this->add_announcement( 8, 'D', $this->fixed( DisplayMode::PLACEMENT_BELOW, array( '_usa_priority' => '10' ) ) );

		$this->make_selector()->render_set();

		$this->assertSame( 'fixed_slot_collision:above:5,6', get_transient( DiagnosticsNotice::TRANSIENT_KEY )['code'] );
	}

	public function test_unrelated_diagnostic_is_not_cleared_by_the_collision_sync(): void {
		DiagnosticsNotice::record_failure( DiagnosticsNotice::CODE_OVERLAY_TOKEN_SIGNATURE_MISMATCH, 3 );

		$this->add_announcement( 5, 'A', $this->fixed( DisplayMode::PLACEMENT_ABOVE, array( '_usa_priority' => '5' ) ) );
		$this->add_announcement( 6, 'B', $this->fixed( DisplayMode::PLACEMENT_ABOVE, array( '_usa_priority' => '10' ) ) );
		$this->make_selector()->render_set();

		$this->assertSame(
			DiagnosticsNotice::CODE_OVERLAY_TOKEN_SIGNATURE_MISMATCH,
			get_transient( DiagnosticsNotice::TRANSIENT_KEY )['code']
		);
	}

	public function test_free_shipping_uniqueness_spans_fixed_and_rotating(): void {
		$this->add_announcement( 1, 'Free shipping over {{free_shipping_threshold}}' );
		$this->add_announcement( 2, 'Also {{free_shipping_threshold}}', $this->fixed( DisplayMode::PLACEMENT_ABOVE ) );

		$selector = $this->make_selector();

		$this->assertSame( array(), $selector->active_contents() );
		$this->assertNull( $selector->fixed_contents()[ DisplayMode::PLACEMENT_ABOVE ] );
	}

	public function test_fixed_row_uses_the_same_overlay_seam_and_falls_back_to_source(): void {
		$this->add_announcement( 1, 'Rotating source' );
		$this->add_announcement( 2, 'Fixed source', $this->fixed( DisplayMode::PLACEMENT_ABOVE ) );

		// A real TemplateOverlay with AIML unavailable: both rows fall back to
		// their source body, identically — the overlay never sees mode/placement.
		$selector = $this->make_selector( array(), $this->make_overlay() );

		$this->assertSame( array( 'Rotating source' ), $selector->active_contents() );
		$this->assertSame( 'Fixed source', $selector->fixed_contents()[ DisplayMode::PLACEMENT_ABOVE ] );
		$this->assertFalse( get_transient( DiagnosticsNotice::TRANSIENT_KEY ) );
	}

	public function test_collision_code_helper_matches_recorded_identity(): void {
		$this->assertSame(
			'fixed_slot_collision:below:3,4',
			Selector::collision_code( DisplayMode::PLACEMENT_BELOW, array( 3, 4 ) )
		);
	}
}
