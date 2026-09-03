<?php
/**
 * StoreNoticeRenderer fixed-row colour styling output (M6.1).
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Announcement\DisplayMode;
use USA\Announcement\FixedRowStyle;
use USA\Tests\Support\AnnouncementFixture;

/**
 * @covers \USA\Rendering\StoreNoticeRenderer::filter_notice
 * @covers \USA\Announcement\FixedRowStyle
 */
final class FixedRowStyleRenderingTest extends TestCase {

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

	public function test_unstyled_fixed_row_emits_no_style_attribute(): void {
		$this->add_announcement( 3, 'KYC only', $this->fixed( DisplayMode::PLACEMENT_ABOVE ) );

		$html = $this->make_renderer()->filter_notice( $this->upstream_notice() );

		$this->assertMatchesRegularExpression(
			'/<span class="usa-announcement-fixed usa-announcement-fixed--above" data-usa-fixed="above">KYC only<\/span>/',
			$html
		);
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_styled_fixed_row_emits_only_configured_custom_properties(): void {
		$this->add_announcement(
			3,
			'Payment notice',
			$this->fixed(
				DisplayMode::PLACEMENT_ABOVE,
				array(
					FixedRowStyle::META_BG => '#eaf7ea',
					FixedRowStyle::META_FG => '#123456',
				)
			)
		);

		$html = $this->make_renderer()->filter_notice( $this->upstream_notice() );

		$this->assertStringContainsString(
			'data-usa-fixed="above" style="--usa-fixed-bg:#eaf7ea;--usa-fixed-fg:#123456;"',
			$html
		);
		$this->assertStringNotContainsString( '--usa-fixed-link', $html );
		$this->assertStringNotContainsString( '--usa-fixed-border', $html );
	}

	public function test_all_four_fields_can_be_configured(): void {
		$this->add_announcement(
			3,
			'Payment notice',
			$this->fixed(
				DisplayMode::PLACEMENT_ABOVE,
				array(
					FixedRowStyle::META_BG     => '#eaf7ea',
					FixedRowStyle::META_FG     => '#123456',
					FixedRowStyle::META_LINK   => '#0000ff',
					FixedRowStyle::META_BORDER => '#cccccc',
				)
			)
		);

		$html = $this->make_renderer()->filter_notice( $this->upstream_notice() );

		$this->assertStringContainsString(
			'style="--usa-fixed-bg:#eaf7ea;--usa-fixed-fg:#123456;--usa-fixed-link:#0000ff;--usa-fixed-border:#cccccc;"',
			$html
		);
	}

	public function test_above_and_below_are_styled_independently(): void {
		$this->add_announcement(
			3,
			'Above row',
			$this->fixed( DisplayMode::PLACEMENT_ABOVE, array( FixedRowStyle::META_BG => '#eaf7ea' ) )
		);
		$this->add_announcement(
			4,
			'Below row',
			$this->fixed( DisplayMode::PLACEMENT_BELOW, array( FixedRowStyle::META_BG => '#222222' ) )
		);

		$html = $this->make_renderer()->filter_notice( $this->upstream_notice() );

		$this->assertMatchesRegularExpression(
			'/usa-announcement-fixed--above" data-usa-fixed="above" style="--usa-fixed-bg:#eaf7ea;">Above row/',
			$html
		);
		$this->assertMatchesRegularExpression(
			'/usa-announcement-fixed--below" data-usa-fixed="below" style="--usa-fixed-bg:#222222;">Below row/',
			$html
		);
	}

	public function test_rotating_spans_never_receive_a_style_attribute(): void {
		$this->add_announcement( 1, 'One' );
		$this->add_announcement( 2, 'Two' );
		$this->add_announcement(
			3,
			'Fixed',
			$this->fixed( DisplayMode::PLACEMENT_ABOVE, array( FixedRowStyle::META_BG => '#eaf7ea' ) )
		);

		$html = $this->make_renderer()->filter_notice( $this->upstream_notice() );

		$this->assertMatchesRegularExpression(
			'/<span class="usa-announcement-bar__message[^"]*">(?:(?!style=).)*<\/span>/s',
			$html
		);
		$this->assertDoesNotMatchRegularExpression(
			'/usa-announcement-bar__message[^"]*"\s+style=/',
			$html
		);
	}

	public function test_malicious_style_meta_never_reaches_output(): void {
		$this->add_announcement(
			3,
			'Payment notice',
			$this->fixed( DisplayMode::PLACEMENT_ABOVE )
		);
		// Simulate a value written directly to the DB, bypassing the admin
		// save-time normalize() call.
		update_post_meta( 3, FixedRowStyle::META_BG, 'red;}body{display:none' );
		update_post_meta( 3, FixedRowStyle::META_FG, '#fff</style><script>alert(1)</script>' );

		$html = $this->make_renderer()->filter_notice( $this->upstream_notice() );

		$this->assertStringNotContainsString( 'style=', $html );
		$this->assertStringNotContainsString( '</style>', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	public function test_translated_body_renders_with_unchanged_wrapper_style(): void {
		$this->add_announcement( 1, 'Rotating source' );
		$this->add_announcement(
			2,
			'Fixed source',
			$this->fixed( DisplayMode::PLACEMENT_ABOVE, array( FixedRowStyle::META_BG => '#eaf7ea' ) )
		);

		// A real TemplateOverlay with AIML unavailable: falls back to source
		// body, identically to the M6 overlay-seam test — style resolution is
		// entirely independent of the overlay/translation path.
		$selector = $this->make_selector( array(), $this->make_overlay() );
		$html     = $this->make_renderer( $selector )->filter_notice( $this->upstream_notice() );

		$this->assertStringContainsString( 'Fixed source', $html );
		$this->assertStringContainsString( 'style="--usa-fixed-bg:#eaf7ea;"', $html );
	}

	public function test_collision_winner_selection_unaffected_by_style_meta(): void {
		$this->add_announcement(
			5,
			'Winner',
			$this->fixed(
				DisplayMode::PLACEMENT_ABOVE,
				array(
					'_usa_priority'      => '5',
					FixedRowStyle::META_BG => '#eaf7ea',
				)
			)
		);
		$this->add_announcement(
			6,
			'Loser',
			$this->fixed(
				DisplayMode::PLACEMENT_ABOVE,
				array(
					'_usa_priority'      => '10',
					FixedRowStyle::META_BG => '#000000',
				)
			)
		);

		$html = $this->make_renderer()->filter_notice( $this->upstream_notice() );

		$this->assertStringContainsString( 'Winner', $html );
		$this->assertStringContainsString( '#eaf7ea', $html );
		$this->assertStringNotContainsString( 'Loser', $html );
		$this->assertStringNotContainsString( '#000000', $html );
	}

	public function test_downgrade_equivalence_absent_style_meta_matches_m6_baseline(): void {
		// No FixedRowStyle meta at all: rendering must be byte-identical to
		// what 0.6.1 (pre-M6.1) code produced for the same fixed-row input.
		$this->add_announcement( 2, 'KYC notice', $this->fixed( DisplayMode::PLACEMENT_ABOVE ) );

		$html = $this->make_renderer()->filter_notice( $this->upstream_notice() );

		$this->assertStringContainsString(
			'class="usa-announcement-fixed usa-announcement-fixed--above" data-usa-fixed="above">KYC notice</span>',
			$html
		);
	}
}
