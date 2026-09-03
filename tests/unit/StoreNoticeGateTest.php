<?php
/**
 * Test StoreNoticeGate activation and diagnostic.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests;

use USA\Announcement\StoreNoticeGate;
use USA\Settings;

/**
 * @covers \USA\Announcement\StoreNoticeGate
 */
class StoreNoticeGateTest extends \WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		delete_option( 'usa_plugin_enabled' );
		delete_option( 'woocommerce_demo_store' );
		delete_transient( 'usa_render_diagnostic' );
	}

	protected function tearDown(): void {
		delete_option( 'usa_plugin_enabled' );
		delete_option( 'woocommerce_demo_store' );
		delete_transient( 'usa_render_diagnostic' );
		parent::tearDown();
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

	public function test_ensure_gate_enabled_if_needed_enables_gate_when_conditions_met(): void {
		Settings::set_enabled( true );
		update_option( 'woocommerce_demo_store', 'no' );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'usa_announcement',
				'post_status'  => 'publish',
				'post_title'   => 'Test',
				'post_content' => 'Content',
			)
		);
		update_post_meta( $post_id, '_usa_enabled', '1' );

		StoreNoticeGate::ensure_gate_enabled_if_needed();

		$this->assertEquals( 'yes', get_option( 'woocommerce_demo_store' ) );
	}

	public function test_ensure_gate_enabled_if_needed_does_not_change_enabled_gate(): void {
		Settings::set_enabled( true );
		update_option( 'woocommerce_demo_store', 'yes' );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'usa_announcement',
				'post_status'  => 'publish',
				'post_title'   => 'Test',
				'post_content' => 'Content',
			)
		);
		update_post_meta( $post_id, '_usa_enabled', '1' );

		StoreNoticeGate::ensure_gate_enabled_if_needed();

		$this->assertEquals( 'yes', get_option( 'woocommerce_demo_store' ) );
	}

	public function test_has_eligible_announcements_returns_false_for_no_posts(): void {
		$this->assertFalse( StoreNoticeGate::has_eligible_announcements() );
	}

	public function test_has_eligible_announcements_returns_false_for_disabled_announcements(): void {
		wp_insert_post(
			array(
				'post_type'    => 'usa_announcement',
				'post_status'  => 'publish',
				'post_title'   => 'Test',
				'post_content' => 'Content',
			)
		);

		$this->assertFalse( StoreNoticeGate::has_eligible_announcements() );
	}

	public function test_has_eligible_announcements_returns_true_for_enabled_announcements(): void {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'usa_announcement',
				'post_status'  => 'publish',
				'post_title'   => 'Test',
				'post_content' => 'Content',
			)
		);
		update_post_meta( $post_id, '_usa_enabled', '1' );

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

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'usa_announcement',
				'post_status'  => 'publish',
				'post_title'   => 'Test',
				'post_content' => 'Content',
			)
		);
		update_post_meta( $post_id, '_usa_enabled', '1' );

		StoreNoticeGate::maybe_record_gate_blocked_diagnostic();

		$this->assertFalse( get_transient( 'usa_render_diagnostic' ) );
	}

	public function test_maybe_record_gate_blocked_diagnostic_records_when_gate_disabled(): void {
		Settings::set_enabled( true );
		update_option( 'woocommerce_demo_store', 'no' );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'usa_announcement',
				'post_status'  => 'publish',
				'post_title'   => 'Test',
				'post_content' => 'Content',
			)
		);
		update_post_meta( $post_id, '_usa_enabled', '1' );

		StoreNoticeGate::maybe_record_gate_blocked_diagnostic();

		$data = get_transient( 'usa_render_diagnostic' );
		$this->assertIsArray( $data );
		$this->assertEquals( 'store_notice_gate_disabled', $data['code'] );
	}

	public function test_ensure_gate_enabled_clears_blocked_diagnostic(): void {
		Settings::set_enabled( true );
		update_option( 'woocommerce_demo_store', 'no' );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'usa_announcement',
				'post_status'  => 'publish',
				'post_title'   => 'Test',
				'post_content' => 'Content',
			)
		);
		update_post_meta( $post_id, '_usa_enabled', '1' );

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
