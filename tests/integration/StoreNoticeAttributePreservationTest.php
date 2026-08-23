<?php
/**
 * ContentReplacer contract tests (verification spike).
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Integration;

use PHPUnit\Framework\TestCase;
use USA\Rendering\ContentReplacer;

/**
 * Proves outer attribute preservation and style surgery.
 */
final class StoreNoticeAttributePreservationTest extends TestCase {

	/**
	 * Multi-attribute fixture with mixed style declarations.
	 */
	public function test_preserves_attributes_and_strips_only_display_none(): void {
		$upstream = '<p role="complementary" aria-label="Store notice" class="woocommerce-store-notice demo_store" data-position="bottom" data-notice-id="abc123" style="color: red; display: none;">OLD</p>';
		$inner    = 'Free shipping — <a href="https://example.com/shipping">details</a>';

		$replacer = new ContentReplacer();
		$result   = $replacer->replace( $upstream, $inner );

		$this->assertTrue( $result['ok'] );
		$html = $result['html'];

		$this->assertStringContainsString( 'data-position="bottom"', $html );
		$this->assertStringContainsString( 'data-notice-id="abc123"', $html );
		$this->assertStringContainsString( 'role="complementary"', $html );
		$this->assertStringContainsString( 'aria-label="Store notice"', $html );
		$this->assertStringContainsString( 'class="woocommerce-store-notice demo_store"', $html );
		$this->assertStringContainsString( 'color: red', $html );
		$this->assertStringNotContainsString( 'display: none', $html );
		$this->assertStringNotContainsString( 'display:none', $html );
		$this->assertStringContainsString( $inner, $html );
		$this->assertStringNotContainsString( 'OLD', $html );
		$this->assertMatchesRegularExpression( '/^<p\b[^>]*>/', $html );
	}

	/**
	 * Unrecognised markup passes through unchanged.
	 */
	public function test_unrecognised_markup_passthrough(): void {
		$upstream = '<div class="not-a-notice">x</div>';
		$replacer = new ContentReplacer();
		$result   = $replacer->replace( $upstream, 'NEW' );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( $upstream, $result['html'] );
		$this->assertSame( 'unrecognised_outer_markup', $result['error'] );
	}

	/**
	 * Self-closing opening quirk with trailing content and closing tag.
	 */
	public function test_self_closing_opening_tag_quirk(): void {
		$upstream = '<p class="woocommerce-store-notice demo_store" data-position="bottom" style="display:none;" />OLD TEXT</p>';
		$replacer = new ContentReplacer();
		$result   = $replacer->replace( $upstream, 'NEW' );

		$this->assertTrue( $result['ok'] );
		$this->assertStringContainsString( 'data-position="bottom"', $result['html'] );
		$this->assertStringContainsString( 'NEW', $result['html'] );
		$this->assertStringNotContainsString( 'OLD TEXT', $result['html'] );
		$this->assertStringNotContainsString( 'display:none', $result['html'] );
	}

	/**
	 * Opening tag fragment is preserved aside from style edit (attribute order).
	 */
	public function test_preserves_opening_tag_attribute_order(): void {
		$upstream = '<p data-z="1" class="woocommerce-store-notice demo_store" data-a="2" style="display:none;">x</p>';
		$replacer = new ContentReplacer();
		$result   = $replacer->replace( $upstream, 'y' );
		$this->assertTrue( $result['ok'] );
		$this->assertMatchesRegularExpression(
			'/<p data-z="1" class="woocommerce-store-notice demo_store" data-a="2">y<\/p>/',
			$result['html']
		);
	}

	/**
	 * Multi-message shell preserves data-position and keeps button outside &lt;p&gt;.
	 */
	public function test_shell_preserves_attributes_and_sibling_button(): void {
		$upstream = '<p role="complementary" class="woocommerce-store-notice demo_store" data-position="bottom">OLD</p>';
		$inner    = '<span class="usa-announcement-bar__message is-active">One</span><span class="usa-announcement-bar__message">Two</span>';
		$replacer = new ContentReplacer();
		$result   = $replacer->replace( $upstream, $inner );
		$this->assertTrue( $result['ok'] );

		$html = $replacer->wrap_shell(
			$result['html'],
			'<button type="button" class="usa-announcement-bar__toggle" aria-pressed="false">Pause announcements</button>'
		);

		$this->assertStringContainsString( 'data-position="bottom"', $html );
		$this->assertStringContainsString( 'role="complementary"', $html );
		$this->assertStringContainsString( 'usa-announcement-shell', $html );
		$this->assertStringContainsString( 'usa-announcement-bar__toggle', $html );
		$this->assertMatchesRegularExpression( '/<\/p><button /', $html );
		$this->assertDoesNotMatchRegularExpression(
			'/<div class="usa-announcement-shell"[^>]*woocommerce-store-notice/',
			$html
		);
	}
}
