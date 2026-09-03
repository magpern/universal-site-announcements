<?php
/**
 * Test StoreNoticeGate activation and diagnostic.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Announcement\StoreNoticeGate;
use USA\Settings;
use USA\Tests\Support\AnnouncementFixture;

/**
 * @covers \USA\Announcement\StoreNoticeGate
 */
final class StoreNoticeGateTest extends TestCase {

	use AnnouncementFixture;

	private array $original_options = array();

	protected function setUp(): void {
		$this->reset_world();
		$this->original_options = array(
			'usa_plugin_enabled'        => get_option( 'usa_plugin_enabled' ),
			'woocommerce_demo_store'    => get_option( 'woocommerce_demo_store' ),
			'usa_render_diagnostic'     => get_transient( 'usa_render_diagnostic' ),
		);

		delete_option( 'usa_plugin_enabled' );
		delete_option( 'woocommerce_demo_store' );
		delete_transient( 'usa_render_diagnostic' );
	}

	protected function tearDown(): void {
		foreach ( $this->original_options as $key => $value ) {
			if ( 'usa_render_diagnostic' === $key ) {
				if ( false !== $value ) {
					set_transient( $key, $value, HOUR_IN_SECONDS );
				}
			} elseif ( false !== $value ) {
				update_option( $key, $value );
			} else {
				delete_option( $key );
			}
		}
	}

	public function test_ensure_gate_enabled_if_needed_requires_usa_enabled(): void {
		Settings::set_enabled( false );
		update_option( 'woocommerce_demo_store', 'no' );

		StoreNoticeGate::ensure_gate_enabled_if_needed();

		$this->assertEquals( 'no', get_option( 'woocommerce_demo_store' ) );
	}

	public function test_ensure_gate_enabled_if_needed_requires_eligible_announcements(): void {
		Settings::set_enabled( true );
		update_option( 'woocommerce_demo_store', 'no' );

		StoreNoticeGate::ensure_gate_enabled_if_needed();

		$this->assertEquals( 'no', get_option( 'woocommerce_demo_store' ) );
	}

	public function test_ensure_gate_enabled_if_needed_does_not_change_enabled_gate(): void {
		Settings::set_enabled( true );
		update_option( 'woocommerce_demo_store', 'yes' );

		StoreNoticeGate::ensure_gate_enabled_if_needed();

		$this->assertEquals( 'yes', get_option( 'woocommerce_demo_store' ) );
	}

	public function test_has_eligible_announcements_returns_false_for_no_posts(): void {
		$this->assertFalse( StoreNoticeGate::has_eligible_announcements() );
	}

	public function test_has_eligible_announcements_returns_false_for_disabled_announcements(): void {
		// Simulate a published announcement without _usa_enabled meta.
		$this->add_announcement( 42, 'Test content', array( '_usa_enabled' => '' ) );

		$this->assertFalse( StoreNoticeGate::has_eligible_announcements() );
	}

	public function test_has_eligible_announcements_returns_true_for_enabled_announcements(): void {
		// Simulate a published announcement with _usa_enabled = 1.
		$this->add_announcement( 42, 'Test content' );

		$this->assertTrue( StoreNoticeGate::has_eligible_announcements() );
	}

	public function test_maybe_record_gate_blocked_diagnostic_requires_usa_enabled(): void {
		Settings::set_enabled( false );
		update_option( 'woocommerce_demo_store', 'no' );

		StoreNoticeGate::maybe_record_gate_blocked_diagnostic();

		$this->assertFalse( get_transient( 'usa_render_diagnostic' ) );
	}

	public function test_maybe_record_gate_blocked_diagnostic_requires_eligible_announcements(): void {
		Settings::set_enabled( true );
		update_option( 'woocommerce_demo_store', 'no' );

		StoreNoticeGate::maybe_record_gate_blocked_diagnostic();

		$this->assertFalse( get_transient( 'usa_render_diagnostic' ) );
	}

	public function test_maybe_record_gate_blocked_diagnostic_requires_gate_disabled(): void {
		Settings::set_enabled( true );
		update_option( 'woocommerce_demo_store', 'yes' );

		$GLOBALS['usa_test_post_content'] = array(
			42 => 'Test content',
		);
		$GLOBALS['usa_test_post_meta'] = array(
			42 => array(
				'_usa_enabled' => '1',
			),
		);

		StoreNoticeGate::maybe_record_gate_blocked_diagnostic();

		$this->assertFalse( get_transient( 'usa_render_diagnostic' ) );
	}

	public function test_maybe_record_gate_blocked_diagnostic_records_when_gate_disabled(): void {
		Settings::set_enabled( true );
		update_option( 'woocommerce_demo_store', 'no' );

		$this->add_announcement( 42, 'Test content' );

		StoreNoticeGate::maybe_record_gate_blocked_diagnostic();

		$data = get_transient( 'usa_render_diagnostic' );
		$this->assertIsArray( $data );
		$this->assertEquals( 'store_notice_gate_disabled', $data['code'] );
	}

	public function test_ensure_gate_enabled_clears_blocked_diagnostic(): void {
		Settings::set_enabled( true );
		update_option( 'woocommerce_demo_store', 'no' );

		$this->add_announcement( 42, 'Test content' );

		// Record the diagnostic.
		StoreNoticeGate::maybe_record_gate_blocked_diagnostic();
		$this->assertNotFalse( get_transient( 'usa_render_diagnostic' ) );

		// Enable the gate.
		update_option( 'woocommerce_demo_store', 'yes' );

		// Check diagnostic is cleared on next check.
		StoreNoticeGate::maybe_record_gate_blocked_diagnostic();
		$this->assertFalse( get_transient( 'usa_render_diagnostic' ) );
	}
}
