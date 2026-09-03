<?php
/**
 * DisplayMode normalisation and read-time defaults.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Announcement\DisplayMode;

/**
 * @covers \USA\Announcement\DisplayMode
 */
final class DisplayModeTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['usa_test_post_meta'] = array();
	}

	protected function tearDown(): void {
		$GLOBALS['usa_test_post_meta'] = array();
	}

	public function test_absent_values_resolve_to_rotating(): void {
		$this->assertSame(
			array(
				'mode'      => DisplayMode::MODE_ROTATING,
				'placement' => null,
			),
			DisplayMode::normalize( '', '' )
		);
	}

	public function test_rotating_never_carries_a_placement(): void {
		$result = DisplayMode::normalize( DisplayMode::MODE_ROTATING, DisplayMode::PLACEMENT_BELOW );
		$this->assertSame( DisplayMode::MODE_ROTATING, $result['mode'] );
		$this->assertNull( $result['placement'] );
	}

	public function test_fixed_without_placement_defaults_to_above(): void {
		$result = DisplayMode::normalize( DisplayMode::MODE_FIXED, '' );
		$this->assertSame( DisplayMode::MODE_FIXED, $result['mode'] );
		$this->assertSame( DisplayMode::PLACEMENT_ABOVE, $result['placement'] );
	}

	public function test_fixed_below_is_preserved(): void {
		$result = DisplayMode::normalize( DisplayMode::MODE_FIXED, DisplayMode::PLACEMENT_BELOW );
		$this->assertSame( DisplayMode::MODE_FIXED, $result['mode'] );
		$this->assertSame( DisplayMode::PLACEMENT_BELOW, $result['placement'] );
	}

	public function test_garbage_falls_back_to_defaults(): void {
		$this->assertSame( DisplayMode::MODE_ROTATING, DisplayMode::normalize( 'sideways', 'diagonal' )['mode'] );
		$this->assertSame( DisplayMode::PLACEMENT_ABOVE, DisplayMode::normalize( DisplayMode::MODE_FIXED, 'diagonal' )['placement'] );
	}

	public function test_resolve_reads_both_meta_keys(): void {
		update_post_meta( 7, DisplayMode::META_MODE, DisplayMode::MODE_FIXED );
		update_post_meta( 7, DisplayMode::META_PLACEMENT, DisplayMode::PLACEMENT_BELOW );

		$this->assertSame(
			array(
				'mode'      => DisplayMode::MODE_FIXED,
				'placement' => DisplayMode::PLACEMENT_BELOW,
			),
			DisplayMode::resolve( 7 )
		);
	}

	public function test_resolve_defaults_for_pre_m6_posts(): void {
		$this->assertSame(
			array(
				'mode'      => DisplayMode::MODE_ROTATING,
				'placement' => null,
			),
			DisplayMode::resolve( 99 )
		);
	}

	public function test_is_fixed_row_tolerates_rows_without_mode(): void {
		$this->assertFalse( DisplayMode::is_fixed_row( array( 'id' => 1 ) ) );
		$this->assertFalse( DisplayMode::is_fixed_row( array( 'mode' => DisplayMode::MODE_ROTATING ) ) );
		$this->assertTrue( DisplayMode::is_fixed_row( array( 'mode' => DisplayMode::MODE_FIXED ) ) );
	}
}
