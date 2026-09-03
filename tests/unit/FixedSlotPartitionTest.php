<?php
/**
 * Selector::partition() pure behaviour.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Announcement\DisplayMode;
use USA\Announcement\Selector;

/**
 * @covers \USA\Announcement\Selector::partition
 */
final class FixedSlotPartitionTest extends TestCase {

	/**
	 * Build a partition input row.
	 *
	 * @param int         $id        Post ID.
	 * @param int         $priority  Priority.
	 * @param string      $mode      Display mode.
	 * @param string|null $placement Placement.
	 * @return array<string,mixed>
	 */
	private function row( int $id, int $priority, string $mode = DisplayMode::MODE_ROTATING, ?string $placement = null ): array {
		return array(
			'id'        => $id,
			'priority'  => $priority,
			'content'   => 'c' . $id,
			'source'    => 'manual',
			'mode'      => $mode,
			'placement' => $placement,
		);
	}

	public function test_empty_input(): void {
		$set = Selector::partition( array() );
		$this->assertSame( array(), $set['rotating'] );
		$this->assertNull( $set['fixed'][ DisplayMode::PLACEMENT_ABOVE ] );
		$this->assertNull( $set['fixed'][ DisplayMode::PLACEMENT_BELOW ] );
		$this->assertSame( array(), $set['collisions'] );
	}

	public function test_rows_without_mode_key_are_rotating(): void {
		$set = Selector::partition(
			array(
				array( 'id' => 3, 'priority' => 10, 'content' => 'a', 'source' => 'manual' ),
			)
		);
		$this->assertCount( 1, $set['rotating'] );
		$this->assertSame( 3, $set['rotating'][0]['id'] );
	}

	public function test_fixed_rows_are_excluded_from_rotation(): void {
		$set = Selector::partition(
			array(
				$this->row( 1, 10 ),
				$this->row( 2, 10, DisplayMode::MODE_FIXED, DisplayMode::PLACEMENT_ABOVE ),
				$this->row( 3, 20 ),
			)
		);

		$ids = array_map(
			static function ( array $row ): int {
				return (int) $row['id'];
			},
			$set['rotating']
		);
		$this->assertSame( array( 1, 3 ), $ids );
		$this->assertSame( 2, $set['fixed'][ DisplayMode::PLACEMENT_ABOVE ]['id'] );
	}

	public function test_winner_is_lowest_priority_then_lowest_id(): void {
		$set = Selector::partition(
			array(
				$this->row( 9, 10, DisplayMode::MODE_FIXED, DisplayMode::PLACEMENT_ABOVE ),
				$this->row( 4, 5, DisplayMode::MODE_FIXED, DisplayMode::PLACEMENT_ABOVE ),
				$this->row( 2, 5, DisplayMode::MODE_FIXED, DisplayMode::PLACEMENT_ABOVE ),
			)
		);

		$this->assertSame( 2, $set['fixed'][ DisplayMode::PLACEMENT_ABOVE ]['id'] );
		$this->assertSame( array(), $set['rotating'] );
		$this->assertCount( 1, $set['collisions'] );
		$this->assertSame( DisplayMode::PLACEMENT_ABOVE, $set['collisions'][0]['placement'] );
		$this->assertSame( 2, $set['collisions'][0]['winner_id'] );
		$this->assertSame( array( 2, 4, 9 ), $set['collisions'][0]['candidate_ids'] );
	}

	public function test_both_placements_are_independent(): void {
		$set = Selector::partition(
			array(
				$this->row( 1, 10, DisplayMode::MODE_FIXED, DisplayMode::PLACEMENT_ABOVE ),
				$this->row( 2, 10, DisplayMode::MODE_FIXED, DisplayMode::PLACEMENT_BELOW ),
			)
		);

		$this->assertSame( 1, $set['fixed'][ DisplayMode::PLACEMENT_ABOVE ]['id'] );
		$this->assertSame( 2, $set['fixed'][ DisplayMode::PLACEMENT_BELOW ]['id'] );
		$this->assertSame( array(), $set['collisions'] );
	}

	public function test_invalid_placement_falls_back_to_above(): void {
		$set = Selector::partition(
			array( $this->row( 1, 10, DisplayMode::MODE_FIXED, 'diagonal' ) )
		);
		$this->assertSame( 1, $set['fixed'][ DisplayMode::PLACEMENT_ABOVE ]['id'] );
		$this->assertNull( $set['fixed'][ DisplayMode::PLACEMENT_BELOW ] );
	}

	public function test_single_fixed_candidate_is_not_a_collision(): void {
		$set = Selector::partition(
			array( $this->row( 1, 10, DisplayMode::MODE_FIXED, DisplayMode::PLACEMENT_BELOW ) )
		);
		$this->assertSame( array(), $set['collisions'] );
	}

	public function test_collisions_reported_for_each_placement(): void {
		$set = Selector::partition(
			array(
				$this->row( 1, 10, DisplayMode::MODE_FIXED, DisplayMode::PLACEMENT_ABOVE ),
				$this->row( 2, 20, DisplayMode::MODE_FIXED, DisplayMode::PLACEMENT_ABOVE ),
				$this->row( 3, 10, DisplayMode::MODE_FIXED, DisplayMode::PLACEMENT_BELOW ),
				$this->row( 4, 5, DisplayMode::MODE_FIXED, DisplayMode::PLACEMENT_BELOW ),
			)
		);

		$this->assertCount( 2, $set['collisions'] );
		$this->assertSame( DisplayMode::PLACEMENT_ABOVE, $set['collisions'][0]['placement'] );
		$this->assertSame( array( 1, 2 ), $set['collisions'][0]['candidate_ids'] );
		$this->assertSame( DisplayMode::PLACEMENT_BELOW, $set['collisions'][1]['placement'] );
		$this->assertSame( 4, $set['collisions'][1]['winner_id'] );
	}
}
