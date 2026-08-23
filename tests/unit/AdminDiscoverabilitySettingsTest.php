<?php
/**
 * Unit tests: settings defaults, validation, admin discoverability helpers.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Admin\PluginActionLinks;
use USA\Admin\SettingsPage;
use USA\Announcement\PostType;
use USA\Rendering\StoreNoticeRenderer;
use USA\Settings;

/**
 * Admin discoverability and rotation settings.
 */
final class AdminDiscoverabilitySettingsTest extends TestCase {

	/**
	 * @var array<string, mixed>
	 */
	private array $option_store = array();

	/**
	 * Reset stubs.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->option_store                    = array();
		$GLOBALS['usa_test_options']           = &$this->option_store;
		$GLOBALS['usa_test_current_user_can']  = true;
		$GLOBALS['usa_test_filters']           = array();
	}

	/**
	 * Rotation defaults match M2 constants.
	 */
	public function test_rotation_defaults(): void {
		$defaults = Settings::rotation_defaults();
		$this->assertTrue( $defaults['enabled'] );
		$this->assertSame( 8000, $defaults['interval_ms'] );
		$this->assertSame( 600, $defaults['fade_ms'] );
		$this->assertSame( $defaults, Settings::get_rotation() );
	}

	/**
	 * Valid settings persist via sanitize.
	 */
	public function test_valid_rotation_persists(): void {
		$result = Settings::sanitize_rotation(
			array(
				'enabled'          => '1',
				'interval_seconds' => '10',
				'fade_ms'          => '400',
			)
		);
		$this->assertTrue( $result['enabled'] );
		$this->assertSame( 10000, $result['interval_ms'] );
		$this->assertSame( 400, $result['fade_ms'] );
	}

	/**
	 * Invalid interval rejected — fallback kept.
	 */
	public function test_invalid_interval_rejected(): void {
		$this->option_store[ Settings::OPTION_ROTATION ] = array(
			'enabled'     => true,
			'interval_ms' => 8000,
			'fade_ms'     => 600,
		);

		$result = Settings::sanitize_rotation(
			array(
				'enabled'          => '1',
				'interval_seconds' => '1.5',
				'fade_ms'          => '400',
			)
		);
		$this->assertSame( 8000, $result['interval_ms'] );
		$this->assertSame( 600, $result['fade_ms'] );
	}

	/**
	 * Out-of-bounds interval rejected.
	 */
	public function test_out_of_bounds_interval_rejected(): void {
		$result = Settings::sanitize_rotation(
			array(
				'enabled'          => '1',
				'interval_seconds' => '1',
				'fade_ms'          => '100',
			)
		);
		$this->assertSame( Settings::DEFAULT_INTERVAL_MS, $result['interval_ms'] );
	}

	/**
	 * Fade equal to interval rejected.
	 */
	public function test_fade_equal_interval_rejected(): void {
		$result = Settings::sanitize_rotation(
			array(
				'enabled'      => '1',
				'interval_ms'  => 8000,
				'fade_ms'      => 8000,
			)
		);
		$this->assertSame( Settings::DEFAULT_FADE_MS, $result['fade_ms'] );
		$this->assertSame( Settings::DEFAULT_INTERVAL_MS, $result['interval_ms'] );
	}

	/**
	 * Fade exceeding interval rejected.
	 */
	public function test_fade_exceeds_interval_rejected(): void {
		$result = Settings::sanitize_rotation(
			array(
				'enabled'      => '1',
				'interval_ms'  => 5000,
				'fade_ms'      => 6000,
			)
		);
		$this->assertSame( Settings::rotation_defaults(), $result );
	}

	/**
	 * Corrupt stored option falls back to defaults.
	 */
	public function test_corrupt_stored_rotation_falls_back(): void {
		$this->option_store[ Settings::OPTION_ROTATION ] = array(
			'enabled'     => true,
			'interval_ms' => 100,
			'fade_ms'     => 9000,
		);
		$this->assertSame( Settings::rotation_defaults(), Settings::get_rotation() );
	}

	/**
	 * should_rotate requires 2+ messages and setting enabled.
	 */
	public function test_should_rotate_gate(): void {
		$this->assertFalse( StoreNoticeRenderer::should_rotate( 1 ) );
		$this->assertFalse( StoreNoticeRenderer::should_rotate( 0 ) );

		$this->assertTrue( StoreNoticeRenderer::should_rotate( 2 ) );

		$this->option_store[ Settings::OPTION_ROTATION ] = array(
			'enabled'     => false,
			'interval_ms' => 8000,
			'fade_ms'     => 600,
		);
		$this->assertFalse( StoreNoticeRenderer::should_rotate( 3 ) );
	}

	/**
	 * Settings submenu slug and CPT menu parent.
	 */
	public function test_menu_slugs(): void {
		$this->assertSame( 'usa-settings', SettingsPage::MENU_SLUG );
		$this->assertSame( 'edit.php?post_type=' . PostType::POST_TYPE, SettingsPage::menu_parent() );
		$this->assertStringContainsString( 'post_type=usa_announcement', Settings::announcements_admin_url() );
		$this->assertStringContainsString( 'page=usa-settings', Settings::settings_admin_url() );
	}

	/**
	 * Plugin action links for capable users.
	 */
	public function test_plugin_action_links_for_capable_user(): void {
		$GLOBALS['usa_test_current_user_can'] = true;
		$links = ( new PluginActionLinks() )->links( array( 'deactivate' => '<a>Deactivate</a>' ) );
		$this->assertArrayHasKey( 'announcements', $links );
		$this->assertArrayHasKey( 'settings', $links );
		$this->assertArrayHasKey( 'deactivate', $links );
		$this->assertStringContainsString( 'Announcements', $links['announcements'] );
		$this->assertStringContainsString( 'Settings', $links['settings'] );
		$this->assertStringContainsString( 'post_type=usa_announcement', $links['announcements'] );
		$this->assertStringContainsString( 'page=usa-settings', $links['settings'] );
	}

	/**
	 * Plugin action links hidden without capability.
	 */
	public function test_plugin_action_links_capability_gated(): void {
		$GLOBALS['usa_test_current_user_can'] = false;
		$original = array( 'deactivate' => '<a>Deactivate</a>' );
		$links    = ( new PluginActionLinks() )->links( $original );
		$this->assertSame( $original, $links );
		$this->assertArrayNotHasKey( 'announcements', $links );
		$this->assertArrayNotHasKey( 'settings', $links );
	}

	/**
	 * Whole-number helper.
	 */
	public function test_is_whole_number(): void {
		$this->assertTrue( Settings::is_whole_number( 8 ) );
		$this->assertTrue( Settings::is_whole_number( '8' ) );
		$this->assertFalse( Settings::is_whole_number( '8.5' ) );
		$this->assertFalse( Settings::is_whole_number( true ) );
		$this->assertFalse( Settings::is_whole_number( 'abc' ) );
	}
}
