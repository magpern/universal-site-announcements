<?php
/**
 * StoreNoticeRenderer composition with fixed-slot rows.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Announcement\DisplayMode;
use USA\Settings;
use USA\Template\TokenProvider;
use USA\Tests\Support\AnnouncementFixture;

/**
 * @covers \USA\Rendering\StoreNoticeRenderer
 * @covers \USA\Rendering\ContentReplacer::compose
 */
final class StoreNoticeRendererFixedTest extends TestCase {

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

	/**
	 * Number of paragraph opening tags.
	 *
	 * @param string $html HTML.
	 */
	private function paragraph_count( string $html ): int {
		return (int) preg_match_all( '/<p\b/i', $html );
	}

	public function test_no_announcements_returns_empty_string(): void {
		$this->assertSame( '', $this->make_renderer()->filter_notice( $this->upstream_notice() ) );
	}

	public function test_single_rotating_output_is_unchanged_without_fixed_rows(): void {
		$this->add_announcement( 1, 'Only message' );

		$html = $this->make_renderer()->filter_notice( $this->upstream_notice() );

		$this->assertStringContainsString( '>Only message</p>', $html );
		$this->assertStringNotContainsString( 'usa-announcement-fixed', $html );
		$this->assertStringNotContainsString( 'usa-announcement-shell', $html );
		$this->assertStringNotContainsString( 'display:none', $html );
		$this->assertSame( 1, $this->paragraph_count( $html ) );
	}

	public function test_two_rotating_messages_still_produce_the_rotation_shell(): void {
		$this->add_announcement( 1, 'One' );
		$this->add_announcement( 2, 'Two' );

		$html = $this->make_renderer()->filter_notice( $this->upstream_notice() );

		$this->assertStringContainsString( 'usa-announcement-shell', $html );
		$this->assertStringContainsString( 'usa-announcement-bar__message is-active', $html );
		$this->assertStringContainsString( 'usa-announcement-bar__toggle', $html );
		$this->assertStringNotContainsString( 'usa-announcement-fixed', $html );
	}

	public function test_fixed_above_renders_inside_the_single_host_paragraph(): void {
		$this->add_announcement( 1, 'Rotating' );
		$this->add_announcement( 2, 'KYC notice', $this->fixed( DisplayMode::PLACEMENT_ABOVE ) );

		$html = $this->make_renderer()->filter_notice( $this->upstream_notice() );

		$this->assertSame( 1, $this->paragraph_count( $html ) );
		$this->assertStringContainsString( 'class="usa-announcement-fixed usa-announcement-fixed--above" data-usa-fixed="above">KYC notice</span>', $html );
		$this->assertMatchesRegularExpression( '/usa-announcement-fixed--above.*Rotating/s', $html );
		$this->assertStringContainsString( 'role="complementary"', $html );
		$this->assertStringContainsString( 'data-notice-id="abc123"', $html );
		$this->assertStringNotContainsString( 'usa-announcement-shell', $html );
	}

	public function test_fixed_below_renders_after_the_rotating_content(): void {
		$this->add_announcement( 1, 'Rotating' );
		$this->add_announcement( 2, 'Payment notice', $this->fixed( DisplayMode::PLACEMENT_BELOW ) );

		$html = $this->make_renderer()->filter_notice( $this->upstream_notice() );

		$this->assertSame( 1, $this->paragraph_count( $html ) );
		$this->assertMatchesRegularExpression( '/Rotating.*usa-announcement-fixed--below/s', $html );
	}

	public function test_both_placements_with_rotation_keep_shell_and_one_paragraph(): void {
		$this->add_announcement( 1, 'One' );
		$this->add_announcement( 2, 'Two' );
		$this->add_announcement( 3, 'KYC', $this->fixed( DisplayMode::PLACEMENT_ABOVE ) );
		$this->add_announcement( 4, 'Payments', $this->fixed( DisplayMode::PLACEMENT_BELOW ) );

		$html = $this->make_renderer()->filter_notice( $this->upstream_notice() );

		$this->assertSame( 1, $this->paragraph_count( $html ) );
		$this->assertStringContainsString( 'usa-announcement-shell', $html );
		$this->assertMatchesRegularExpression( '/<\/p><button /', $html );
		$this->assertMatchesRegularExpression(
			'/usa-announcement-fixed--above.*usa-announcement-bar__message is-active.*usa-announcement-fixed--below/s',
			$html
		);
	}

	public function test_fixed_only_renders_without_shell_or_empty_rotating_fragment(): void {
		$this->add_announcement( 3, 'KYC only', $this->fixed( DisplayMode::PLACEMENT_ABOVE ) );

		$html = $this->make_renderer()->filter_notice( $this->upstream_notice() );

		$this->assertSame( 1, $this->paragraph_count( $html ) );
		$this->assertStringNotContainsString( 'usa-announcement-shell', $html );
		$this->assertStringNotContainsString( 'usa-announcement-bar__toggle', $html );
		$this->assertStringNotContainsString( 'usa-announcement-bar__message', $html );
		$this->assertMatchesRegularExpression(
			'/<span class="usa-announcement-fixed usa-announcement-fixed--above" data-usa-fixed="above">KYC only<\/span><\/p>/',
			$html
		);
	}

	public function test_fixed_span_never_carries_the_rotation_message_class(): void {
		$this->add_announcement( 3, 'KYC', $this->fixed( DisplayMode::PLACEMENT_ABOVE ) );

		$html = $this->make_renderer()->filter_notice( $this->upstream_notice() );

		$this->assertDoesNotMatchRegularExpression( '/usa-announcement-fixed[^"]*usa-announcement-bar__message/', $html );
	}

	public function test_losing_fixed_candidate_is_not_rendered_anywhere(): void {
		$this->add_announcement( 5, 'Winner', $this->fixed( DisplayMode::PLACEMENT_ABOVE, array( '_usa_priority' => '5' ) ) );
		$this->add_announcement( 6, 'Loser', $this->fixed( DisplayMode::PLACEMENT_ABOVE, array( '_usa_priority' => '10' ) ) );

		$html = $this->make_renderer()->filter_notice( $this->upstream_notice() );

		$this->assertStringContainsString( 'Winner', $html );
		$this->assertStringNotContainsString( 'Loser', $html );
	}

	public function test_disabled_plugin_returns_upstream_markup_untouched(): void {
		$this->add_announcement( 3, 'KYC', $this->fixed( DisplayMode::PLACEMENT_ABOVE ) );
		update_option( Settings::OPTION_ENABLED, 0 );

		$upstream = $this->upstream_notice();
		$this->assertSame( $upstream, $this->make_renderer()->filter_notice( $upstream ) );
	}

	public function test_unrecognised_markup_leaves_upstream_unchanged(): void {
		$this->add_announcement( 3, 'KYC', $this->fixed( DisplayMode::PLACEMENT_ABOVE ) );

		$upstream = '<div class="not-a-notice">x</div>';
		$this->assertSame( $upstream, $this->make_renderer()->filter_notice( $upstream ) );
	}

	public function test_fixed_body_is_sanitised_and_links_are_kept(): void {
		$this->add_announcement(
			3,
			'KYC <a href="https://example.test/kyc">details</a><script>alert(1)</script>',
			$this->fixed( DisplayMode::PLACEMENT_ABOVE )
		);

		$html = $this->make_renderer()->filter_notice( $this->upstream_notice() );

		$this->assertStringContainsString( 'href="https://example.test/kyc"', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_tokens_resolve_before_sanitization_in_a_fixed_row(): void {
		$provider = new class() implements TokenProvider {

			/**
			 * @param string      $name Token name.
			 * @param string|null $arg  Argument.
			 */
			public function supports( string $name, ?string $arg ): bool {
				unset( $arg );
				return 'product' === $name;
			}

			/**
			 * @param string               $name    Token name.
			 * @param string|null          $arg     Argument.
			 * @param array<string, mixed> $context Context.
			 */
			public function resolve( string $name, ?string $arg, array $context ): ?string {
				unset( $name, $arg, $context );
				return '<strong>Widget</strong><script>alert(1)</script>';
			}
		};

		$this->add_announcement( 3, 'Buy {{product:12}} now', $this->fixed( DisplayMode::PLACEMENT_ABOVE ) );

		$html = $this->make_renderer( $this->make_selector( array( $provider ) ) )
			->filter_notice( $this->upstream_notice() );

		// Token was resolved (so tokens ran after template selection) and the
		// resolved markup was sanitized afterwards.
		$this->assertStringContainsString( '<strong>Widget</strong>', $html );
		$this->assertStringNotContainsString( '{{product:12}}', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'usa-announcement-fixed--above', $html );
	}

	public function test_rotation_disabled_keeps_first_rotating_message_with_fixed_rows(): void {
		update_option(
			Settings::OPTION_ROTATION,
			array(
				'enabled'     => false,
				'interval_ms' => 8000,
				'fade_ms'     => 600,
			)
		);
		$this->add_announcement( 1, 'One' );
		$this->add_announcement( 2, 'Two' );
		$this->add_announcement( 3, 'KYC', $this->fixed( DisplayMode::PLACEMENT_BELOW ) );

		$html = $this->make_renderer()->filter_notice( $this->upstream_notice() );

		$this->assertStringContainsString( 'One', $html );
		$this->assertStringNotContainsString( 'Two', $html );
		$this->assertStringContainsString( 'usa-announcement-fixed--below', $html );
		$this->assertStringNotContainsString( 'usa-announcement-shell', $html );
	}
}
