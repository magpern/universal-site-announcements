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

	public function test_record_failure_is_throttled_while_present(): void {
		DiagnosticsNotice::record_failure( DiagnosticsNotice::CODE_OVERLAY_TOKEN_SIGNATURE_MISMATCH, 1 );
		DiagnosticsNotice::record_failure( 'template_other', 2 );
		$data = get_transient( DiagnosticsNotice::TRANSIENT_KEY );
		$this->assertIsArray( $data );
		$this->assertSame( DiagnosticsNotice::CODE_OVERLAY_TOKEN_SIGNATURE_MISMATCH, $data['code'] );
		$this->assertSame( 1, (int) $data['post_id'] );
	}
}
