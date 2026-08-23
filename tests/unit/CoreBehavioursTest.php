<?php
/**
 * Unit tests: selector, sanitiser, style helper.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Announcement\Sanitizer;
use USA\Announcement\Selector;
use USA\Lifecycle\SeedDecision;
use USA\Rendering\ContentReplacer;

/**
 * Pure unit coverage.
 */
final class CoreBehavioursTest extends TestCase {

	/**
	 * Priority then ID ordering.
	 */
	public function test_selector_picks_lowest_priority_then_id(): void {
		$pick = Selector::pick_first(
			array(
				array( 'id' => 20, 'priority' => 10, 'content' => 'b' ),
				array( 'id' => 5, 'priority' => 10, 'content' => 'a' ),
				array( 'id' => 9, 'priority' => 5, 'content' => 'first' ),
			)
		);
		$this->assertNotNull( $pick );
		$this->assertSame( 9, $pick['id'] );
		$this->assertSame( 'first', $pick['content'] );
	}

	/**
	 * Empty selection.
	 */
	public function test_selector_empty(): void {
		$this->assertNull( Selector::pick_first( array() ) );
	}

	/**
	 * Sanitizer strips scripts and enforces blank rel.
	 */
	public function test_sanitizer_allowlist_and_blank_rel(): void {
		$s = new Sanitizer();
		$out = $s->sanitize( '<script>alert(1)</script><a href="https://ex.test" target="_blank">Go</a><strong>x</strong>' );
		$this->assertStringNotContainsString( '<script>', $out );
		$this->assertStringContainsString( 'target="_blank"', $out );
		$this->assertStringContainsString( 'noopener', $out );
		$this->assertStringContainsString( 'noreferrer', $out );
		$this->assertStringContainsString( '<strong>x</strong>', $out );
	}

	/**
	 * Style declaration removal helper.
	 */
	public function test_style_display_none_removal(): void {
		$r = new ContentReplacer();
		$this->assertSame( 'color: red', $r->remove_display_none_declarations( 'color: red; display: none;' ) );
		$this->assertSame( 'color: red', $r->remove_display_none_declarations( 'display:none; color: red' ) );
		$this->assertSame( '', $r->remove_display_none_declarations( 'display: none' ) );
	}

	/**
	 * Seed only when fallback sanitises non-empty and no announcements exist.
	 */
	public function test_seed_decision_matrix(): void {
		$s = new Sanitizer();
		$this->assertNull( SeedDecision::content_to_seed( true, 'Hello', $s ) );
		$this->assertNull( SeedDecision::content_to_seed( false, '', $s ) );
		$this->assertNull( SeedDecision::content_to_seed( false, '<script></script>', $s ) );
		$this->assertSame( 'Hello', SeedDecision::content_to_seed( false, 'Hello', $s ) );
	}
}
