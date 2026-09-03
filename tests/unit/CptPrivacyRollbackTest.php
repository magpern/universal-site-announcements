<?php
/**
 * CPT privacy surface and deactivation rollback are untouched by M6.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Announcement\DisplayMode;
use USA\Announcement\PostType;
use USA\Lifecycle\Deactivator;

/**
 * @covers \USA\Announcement\PostType
 * @covers \USA\Lifecycle\Deactivator
 */
final class CptPrivacyRollbackTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['usa_test_registered_post_types'] = array();
		$GLOBALS['usa_test_post_meta']             = array();
		$GLOBALS['usa_test_rewrite_flushed']       = false;
	}

	protected function tearDown(): void {
		$GLOBALS['usa_test_registered_post_types'] = array();
		$GLOBALS['usa_test_post_meta']             = array();
		$GLOBALS['usa_test_rewrite_flushed']       = false;
	}

	public function test_announcement_cpt_has_no_public_surface(): void {
		( new PostType() )->register_post_type();

		$args = $GLOBALS['usa_test_registered_post_types'][ PostType::POST_TYPE ];

		$this->assertFalse( $args['public'] );
		$this->assertFalse( $args['show_in_rest'] );
		$this->assertFalse( $args['has_archive'] );
		$this->assertFalse( $args['rewrite'] );
		$this->assertFalse( $args['query_var'] );
		$this->assertTrue( $args['exclude_from_search'] );
		$this->assertSame( array( 'title', 'editor' ), $args['supports'] );
	}

	public function test_deactivation_only_flushes_rewrites_and_keeps_display_meta(): void {
		update_post_meta( 5, DisplayMode::META_MODE, DisplayMode::MODE_FIXED );
		update_post_meta( 5, DisplayMode::META_PLACEMENT, DisplayMode::PLACEMENT_BELOW );

		( new Deactivator() )->deactivate();

		$this->assertTrue( $GLOBALS['usa_test_rewrite_flushed'] );
		$this->assertSame( DisplayMode::MODE_FIXED, get_post_meta( 5, DisplayMode::META_MODE, true ) );
		$this->assertSame( DisplayMode::PLACEMENT_BELOW, get_post_meta( 5, DisplayMode::META_PLACEMENT, true ) );
	}

	public function test_downgrade_semantics_ignore_unknown_display_meta(): void {
		// A 0.5.x build never reads the M6 keys; rows resolve as rotating there.
		// Locally this is proven by partition() treating a row without a mode as rotating.
		update_post_meta( 6, DisplayMode::META_MODE, DisplayMode::MODE_FIXED );

		$row = array(
			'id'       => 6,
			'priority' => 10,
			'content'  => 'x',
			'source'   => 'manual',
		);

		$this->assertFalse( DisplayMode::is_fixed_row( $row ) );
	}
}
