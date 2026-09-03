<?php
/**
 * List-table Mode column formatting.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Announcement\DisplayMode;
use USA\Announcement\ListTable;

/**
 * @covers \USA\Announcement\ListTable::format_mode
 */
final class ListTableModeColumnTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['usa_test_post_meta'] = array();
	}

	protected function tearDown(): void {
		$GLOBALS['usa_test_post_meta'] = array();
	}

	public function test_pre_m6_post_is_rotating(): void {
		$this->assertSame( 'Rotating', ListTable::format_mode( 1 ) );
	}

	public function test_fixed_above(): void {
		update_post_meta( 2, DisplayMode::META_MODE, DisplayMode::MODE_FIXED );
		$this->assertSame( 'Fixed (above)', ListTable::format_mode( 2 ) );
	}

	public function test_fixed_below(): void {
		update_post_meta( 3, DisplayMode::META_MODE, DisplayMode::MODE_FIXED );
		update_post_meta( 3, DisplayMode::META_PLACEMENT, DisplayMode::PLACEMENT_BELOW );
		$this->assertSame( 'Fixed (below)', ListTable::format_mode( 3 ) );
	}

	public function test_retained_placement_is_ignored_while_rotating(): void {
		update_post_meta( 4, DisplayMode::META_MODE, DisplayMode::MODE_ROTATING );
		update_post_meta( 4, DisplayMode::META_PLACEMENT, DisplayMode::PLACEMENT_BELOW );
		$this->assertSame( 'Rotating', ListTable::format_mode( 4 ) );
	}
}
