<?php
/**
 * Proof that the request-local render_set() memo changes nothing.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Admin\DiagnosticsNotice;
use USA\Announcement\DisplayMode;
use USA\Announcement\ScheduleEvaluator;
use USA\Announcement\Selector;
use USA\Settings;
use USA\Tests\Support\AnnouncementFixture;

/**
 * @covers \USA\Announcement\Selector::render_set
 */
final class RenderSetMemoEquivalenceTest extends TestCase {

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
	private static function fixed( string $placement, array $extra = array() ): array {
		return array_merge(
			array(
				DisplayMode::META_MODE      => DisplayMode::MODE_FIXED,
				DisplayMode::META_PLACEMENT => $placement,
			),
			$extra
		);
	}

	/**
	 * Scenarios covering M1–M5 behaviour and every M6 combination.
	 *
	 * Each scenario is a callable that populates the stub world.
	 *
	 * @return array<string, array{0:callable}>
	 */
	public function scenarios(): array {
		$fixed_above = self::fixed( DisplayMode::PLACEMENT_ABOVE );
		$fixed_below = self::fixed( DisplayMode::PLACEMENT_BELOW );

		return array(
			'M1 no announcements'          => array(
				static function ( self $t ): void {
					unset( $t );
				},
			),
			'M1 single rotating'           => array(
				static function ( self $t ): void {
					$t->add_announcement( 1, 'One' );
				},
			),
			'M2 three rotating'            => array(
				static function ( self $t ): void {
					$t->add_announcement( 1, 'One' );
					$t->add_announcement( 2, 'Two', array( '_usa_priority' => '5' ) );
					$t->add_announcement( 3, 'Three' );
				},
			),
			'M2 rotation disabled'         => array(
				static function ( self $t ): void {
					update_option(
						Settings::OPTION_ROTATION,
						array(
							'enabled'     => false,
							'interval_ms' => 8000,
							'fade_ms'     => 600,
						)
					);
					$t->add_announcement( 1, 'One' );
					$t->add_announcement( 2, 'Two' );
				},
			),
			'M2 schedule inactive'         => array(
				static function ( self $t ): void {
					$t->add_announcement(
						1,
						'Expired',
						array(
							ScheduleEvaluator::META_MODE      => ScheduleEvaluator::MODE_INTERVAL,
							ScheduleEvaluator::META_STARTS_AT => '2000-01-01 00:00:00',
							ScheduleEvaluator::META_ENDS_AT   => '2000-01-02 00:00:00',
						)
					);
					$t->add_announcement( 2, 'Live' );
				},
			),
			'M2 duplicate free shipping'   => array(
				static function ( self $t ): void {
					$t->add_announcement( 1, 'A {{free_shipping_threshold}}' );
					$t->add_announcement( 2, 'B {{free_shipping_threshold}}' );
				},
			),
			'M3 invalid template'          => array(
				static function ( self $t ): void {
					$t->add_announcement( 1, 'Broken {{ tag' );
					$t->add_announcement( 2, 'Fine' );
				},
			),
			'M5 overlay absent fallback'   => array(
				static function ( self $t ): void {
					$t->add_announcement( 1, 'Source body only' );
				},
			),
			'M6 fixed above only'          => array(
				static function ( self $t ) use ( $fixed_above ): void {
					$t->add_announcement( 1, 'KYC', $fixed_above );
				},
			),
			'M6 fixed below only'          => array(
				static function ( self $t ) use ( $fixed_below ): void {
					$t->add_announcement( 1, 'Payments', $fixed_below );
				},
			),
			'M6 both placements'           => array(
				static function ( self $t ) use ( $fixed_above, $fixed_below ): void {
					$t->add_announcement( 1, 'Rotating' );
					$t->add_announcement( 2, 'KYC', $fixed_above );
					$t->add_announcement( 3, 'Payments', $fixed_below );
				},
			),
			'M6 rotating plus both fixed'  => array(
				static function ( self $t ) use ( $fixed_above, $fixed_below ): void {
					$t->add_announcement( 1, 'One' );
					$t->add_announcement( 2, 'Two' );
					$t->add_announcement( 3, 'KYC', $fixed_above );
					$t->add_announcement( 4, 'Payments', $fixed_below );
				},
			),
			'M6 collision overlapping'     => array(
				static function ( self $t ): void {
					$t->add_announcement( 5, 'A', self::fixed( DisplayMode::PLACEMENT_ABOVE, array( '_usa_priority' => '5' ) ) );
					$t->add_announcement( 6, 'B', self::fixed( DisplayMode::PLACEMENT_ABOVE, array( '_usa_priority' => '10' ) ) );
				},
			),
			'M6 collision disjoint'        => array(
				static function ( self $t ): void {
					$t->add_announcement( 5, 'A', self::fixed( DisplayMode::PLACEMENT_ABOVE, array( '_usa_priority' => '5' ) ) );
					$t->add_announcement(
						6,
						'B',
						self::fixed(
							DisplayMode::PLACEMENT_ABOVE,
							array(
								ScheduleEvaluator::META_MODE      => ScheduleEvaluator::MODE_INTERVAL,
								ScheduleEvaluator::META_STARTS_AT => '2000-01-01 00:00:00',
								ScheduleEvaluator::META_ENDS_AT   => '2000-01-02 00:00:00',
							)
						)
					);
				},
			),
			'M6 fixed with invalid sibling' => array(
				static function ( self $t ) use ( $fixed_above ): void {
					$t->add_announcement( 1, 'Broken {{ tag' );
					$t->add_announcement( 2, 'KYC', $fixed_above );
				},
			),
		);
	}

	/**
	 * Memoized and freshly evaluated selectors must agree exactly.
	 *
	 * @dataProvider scenarios
	 *
	 * @param callable $build World builder.
	 */
	public function test_memo_is_equivalent_to_fresh_evaluation( callable $build ): void {
		// Non-memoized path: reset before every access so each call re-evaluates.
		$this->reset_world();
		$build( $this );
		$fresh          = $this->make_selector();
		$fresh_set      = $this->call_fresh( $fresh, 'render_set' );
		$fresh_rotating = $this->call_fresh( $fresh, 'active_contents' );
		$fresh_fixed    = $this->call_fresh( $fresh, 'fixed_contents' );
		$fresh->reset();
		$fresh_html        = $this->make_renderer( $fresh )->filter_notice( $this->upstream_notice() );
		$fresh_diagnostic  = get_transient( DiagnosticsNotice::TRANSIENT_KEY );

		// Memoized path: a single selector evaluated once per request.
		$this->reset_world();
		$build( $this );
		$memo          = $this->make_selector();
		$memo_set      = $memo->render_set();
		$memo_set_2    = $memo->render_set();
		$memo_rotating = $memo->active_contents();
		$memo_fixed    = $memo->fixed_contents();
		$memo_html     = $this->make_renderer( $memo )->filter_notice( $this->upstream_notice() );
		$memo_diagnostic = get_transient( DiagnosticsNotice::TRANSIENT_KEY );

		$this->assertSame( $memo_set, $memo_set_2, 'Repeated memo reads must be identical.' );
		$this->assertSame( $fresh_set, $memo_set );
		$this->assertSame( $fresh_rotating, $memo_rotating );
		$this->assertSame( $fresh_fixed, $memo_fixed );
		$this->assertSame( $fresh_html, $memo_html );
		$this->assertSame( $fresh_diagnostic, $memo_diagnostic );
	}

	/**
	 * Call a selector accessor with the memo cleared first.
	 *
	 * @param Selector $selector Selector.
	 * @param string   $method   Accessor name.
	 * @return mixed
	 */
	private function call_fresh( Selector $selector, string $method ) {
		$selector->reset();
		return $selector->{$method}();
	}
}
