<?php
/**
 * DiagnosticsNotice recovery and dismiss helpers.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Admin\DiagnosticsNotice;

/**
 * @covers \USA\Admin\DiagnosticsNotice
 */
final class DiagnosticsNoticeTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['usa_test_transients']      = array();
		$GLOBALS['usa_test_current_user_can'] = true;
	}

	protected function tearDown(): void {
		$GLOBALS['usa_test_transients']      = array();
		$GLOBALS['usa_test_current_user_can'] = false;
	}

	public function test_record_failure_stores_code_and_post_id(): void {
		DiagnosticsNotice::record_failure( DiagnosticsNotice::CODE_OVERLAY_TOKEN_SIGNATURE_MISMATCH, 42 );
		$data = get_transient( DiagnosticsNotice::TRANSIENT_KEY );
		$this->assertIsArray( $data );
		$this->assertSame( DiagnosticsNotice::CODE_OVERLAY_TOKEN_SIGNATURE_MISMATCH, $data['code'] );
		$this->assertSame( 42, (int) $data['post_id'] );
	}

	public function test_clear_if_recovered_requires_same_announcement_and_code(): void {
		DiagnosticsNotice::record_failure( DiagnosticsNotice::CODE_OVERLAY_TOKEN_SIGNATURE_MISMATCH, 42 );

		DiagnosticsNotice::clear_if_recovered( DiagnosticsNotice::CODE_OVERLAY_TOKEN_SIGNATURE_MISMATCH, 99 );
		$this->assertNotFalse( get_transient( DiagnosticsNotice::TRANSIENT_KEY ) );

		DiagnosticsNotice::clear_if_recovered( 'template_malformed_merge_tags', 42 );
		$this->assertNotFalse( get_transient( DiagnosticsNotice::TRANSIENT_KEY ) );

		DiagnosticsNotice::clear_if_recovered( DiagnosticsNotice::CODE_OVERLAY_TOKEN_SIGNATURE_MISMATCH, 42 );
		$this->assertFalse( get_transient( DiagnosticsNotice::TRANSIENT_KEY ) );
	}

	public function test_unrelated_successful_recovery_does_not_clear(): void {
		DiagnosticsNotice::record_failure( DiagnosticsNotice::CODE_OVERLAY_TOKEN_SIGNATURE_MISMATCH, 10 );
		DiagnosticsNotice::clear_if_recovered( DiagnosticsNotice::CODE_OVERLAY_TOKEN_SIGNATURE_MISMATCH, 11 );
		$this->assertNotFalse( get_transient( DiagnosticsNotice::TRANSIENT_KEY ) );
	}

	public function test_clear_dismisses_unconditionally(): void {
		DiagnosticsNotice::record_failure( DiagnosticsNotice::CODE_OVERLAY_TOKEN_SIGNATURE_MISMATCH, 7 );
		DiagnosticsNotice::clear();
		$this->assertFalse( get_transient( DiagnosticsNotice::TRANSIENT_KEY ) );
	}

	public function test_collision_code_is_recorded_with_the_winner(): void {
		$code = DiagnosticsNotice::CODE_FIXED_SLOT_COLLISION_PREFIX . 'above:5,6';
		DiagnosticsNotice::record_failure( $code, 5 );

		$data = get_transient( DiagnosticsNotice::TRANSIENT_KEY );
		$this->assertIsArray( $data );
		$this->assertSame( $code, $data['code'] );
		$this->assertSame( 5, (int) $data['post_id'] );
	}

	public function test_clear_if_stale_prefix_replaces_a_changed_candidate_set(): void {
		DiagnosticsNotice::record_failure( DiagnosticsNotice::CODE_FIXED_SLOT_COLLISION_PREFIX . 'above:5,6', 5 );

		DiagnosticsNotice::clear_if_stale_prefix(
			DiagnosticsNotice::CODE_FIXED_SLOT_COLLISION_PREFIX . 'above:',
			DiagnosticsNotice::CODE_FIXED_SLOT_COLLISION_PREFIX . 'above:5,6,7',
			5
		);

		$this->assertFalse( get_transient( DiagnosticsNotice::TRANSIENT_KEY ) );
	}

	public function test_clear_if_stale_prefix_replaces_a_changed_winner(): void {
		$code = DiagnosticsNotice::CODE_FIXED_SLOT_COLLISION_PREFIX . 'above:5,6';
		DiagnosticsNotice::record_failure( $code, 5 );

		DiagnosticsNotice::clear_if_stale_prefix(
			DiagnosticsNotice::CODE_FIXED_SLOT_COLLISION_PREFIX . 'above:',
			$code,
			6
		);

		$this->assertFalse( get_transient( DiagnosticsNotice::TRANSIENT_KEY ) );
	}

	public function test_clear_if_stale_prefix_keeps_an_unchanged_collision(): void {
		$code = DiagnosticsNotice::CODE_FIXED_SLOT_COLLISION_PREFIX . 'above:5,6';
		DiagnosticsNotice::record_failure( $code, 5 );

		DiagnosticsNotice::clear_if_stale_prefix(
			DiagnosticsNotice::CODE_FIXED_SLOT_COLLISION_PREFIX . 'above:',
			$code,
			5
		);

		$this->assertNotFalse( get_transient( DiagnosticsNotice::TRANSIENT_KEY ) );
	}

	public function test_clear_if_stale_prefix_clears_on_recovery(): void {
		DiagnosticsNotice::record_failure( DiagnosticsNotice::CODE_FIXED_SLOT_COLLISION_PREFIX . 'below:5,6', 5 );

		DiagnosticsNotice::clear_if_stale_prefix(
			DiagnosticsNotice::CODE_FIXED_SLOT_COLLISION_PREFIX . 'below:',
			'',
			0
		);

		$this->assertFalse( get_transient( DiagnosticsNotice::TRANSIENT_KEY ) );
	}

	public function test_clear_if_stale_prefix_ignores_other_placements_and_codes(): void {
		DiagnosticsNotice::record_failure( DiagnosticsNotice::CODE_FIXED_SLOT_COLLISION_PREFIX . 'above:5,6', 5 );

		DiagnosticsNotice::clear_if_stale_prefix( DiagnosticsNotice::CODE_FIXED_SLOT_COLLISION_PREFIX . 'below:', '', 0 );
		$this->assertNotFalse( get_transient( DiagnosticsNotice::TRANSIENT_KEY ) );

		DiagnosticsNotice::clear();
		DiagnosticsNotice::record_failure( 'template_malformed_merge_tags', 9 );
		DiagnosticsNotice::clear_if_stale_prefix( DiagnosticsNotice::CODE_FIXED_SLOT_COLLISION_PREFIX . 'above:', '', 0 );
		$this->assertNotFalse( get_transient( DiagnosticsNotice::TRANSIENT_KEY ) );
	}

	public function test_collision_message_is_actionable(): void {
		DiagnosticsNotice::record_failure( DiagnosticsNotice::CODE_FIXED_SLOT_COLLISION_PREFIX . 'above:5,6', 5 );

		$notice  = new DiagnosticsNotice();
		$message = $notice->message_for_code( DiagnosticsNotice::CODE_FIXED_SLOT_COLLISION_PREFIX . 'above:5,6' );

		$this->assertStringContainsString( 'above', $message );
		$this->assertStringContainsString( '#5', $message );
		$this->assertStringContainsString( '5,6', $message );
		$this->assertStringContainsString( 'lowest priority', $message );
	}

	public function test_record_failure_is_throttled_while_present(): void {
		DiagnosticsNotice::record_failure( DiagnosticsNotice::CODE_OVERLAY_TOKEN_SIGNATURE_MISMATCH, 1 );
		DiagnosticsNotice::record_failure( 'template_other', 2 );
		$data = get_transient( DiagnosticsNotice::TRANSIENT_KEY );
		$this->assertIsArray( $data );
		$this->assertSame( DiagnosticsNotice::CODE_OVERLAY_TOKEN_SIGNATURE_MISMATCH, $data['code'] );
		$this->assertSame( 1, (int) $data['post_id'] );
	}
}
