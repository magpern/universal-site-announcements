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

	/**
	 * Shell wraps the notice paragraph with pause button as sibling.
	 */
	public function test_wrap_shell_places_button_outside_paragraph(): void {
		$upstream = '<p class="woocommerce-store-notice demo_store" data-position="bottom">OLD</p>';
		$replacer = new ContentReplacer();
		$replaced = $replacer->replace( $upstream, '<span class="usa-announcement-bar__message is-active">A</span><span class="usa-announcement-bar__message">B</span>' );
		$this->assertTrue( $replaced['ok'] );

		$button = '<button type="button" class="usa-announcement-bar__toggle" aria-pressed="false">Pause</button>';
		$html   = $replacer->wrap_shell( $replaced['html'], $button );

		$this->assertStringContainsString( 'class="usa-announcement-shell"', $html );
		$this->assertStringContainsString( 'data-position="bottom"', $html );
		$this->assertMatchesRegularExpression(
			'/<div class="usa-announcement-shell"><p class="woocommerce-store-notice demo_store" data-position="bottom">.*<\/p><button type="button" class="usa-announcement-bar__toggle"/s',
			$html
		);
		$this->assertStringNotContainsString( 'woocommerce-store-notice usa-announcement-shell', $html );
		// Button must not be nested inside the notice <p>.
		$this->assertDoesNotMatchRegularExpression(
			'/<p\b[^>]*>[^<]*<button/i',
			$html
		);
	}

	/**
	 * Price + manual output allowlist merge.
	 */
	public function test_sanitize_output_keeps_price_and_links(): void {
		$s    = new Sanitizer();
		$html = 'From <span class="woocommerce-Price-amount"><bdi>10</bdi></span> — <a href="https://ex.test" target="_blank">info</a>';
		$out  = $s->sanitize_output( $html );
		$this->assertStringContainsString( 'woocommerce-Price-amount', $out );
		$this->assertStringContainsString( 'noopener', $out );
	}
}
