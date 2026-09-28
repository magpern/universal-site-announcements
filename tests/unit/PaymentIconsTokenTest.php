<?php
/**
 * {{payment_icons}} merge tag tests.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Announcement\Sanitizer;
use USA\Template\HtmlPlacementValidator;
use USA\Template\MergeTagParser;
use USA\Template\PaymentIconsToken;
use USA\Template\SourceTokenRules;
use USA\Template\TemplateEngine;
use USA\Template\TemplateRequirements;

/**
 * The token renders bundled logos; announcement content still cannot carry images.
 */
final class PaymentIconsTokenTest extends TestCase {

	private const BASE = 'https://example.test/wp-content/plugins/universal-site-announcements/assets/img/payment-icons/';

	/**
	 * The token renders all four logos from the plugin's own assets.
	 */
	public function test_renders_the_four_bundled_icons_in_a_message(): void {
		$out = $this->engine()->render( 'Now accepting {{payment_icons}}', 'manual' );

		$this->assertIsString( $out );
		$this->assertStringStartsWith( 'Now accepting <span class="usa-payment-icons">', $out );
		foreach ( array( 'applepay', 'googlepay', 'visa', 'mastercard' ) as $key ) {
			$this->assertStringContainsString( 'src="' . self::BASE . $key . '.svg"', $out );
		}
		foreach ( array( 'Apple Pay', 'Google Pay', 'Visa', 'Mastercard' ) as $label ) {
			$this->assertStringContainsString( 'alt="' . $label . '"', $out );
		}
		$this->assertSame( 4, substr_count( $out, '<img ' ) );
	}

	/**
	 * The token takes no argument.
	 */
	public function test_argument_is_rejected(): void {
		$this->assertNull( $this->engine()->render( 'x {{payment_icons:5}}', 'manual' ) );
	}

	/**
	 * The token is insertable from the editor.
	 */
	public function test_is_an_insertable_token(): void {
		$this->assertContains( 'payment_icons', ( new SourceTokenRules() )->insertable_names() );
	}

	/**
	 * Editor-supplied images are stripped on save.
	 */
	public function test_saved_announcement_content_cannot_contain_images(): void {
		$clean = ( new Sanitizer() )->sanitize( 'Hi <img src="' . self::BASE . 'visa.svg" alt="x"> there' );

		$this->assertStringNotContainsString( '<img', $clean );
	}

	/**
	 * Output only lets the plugin's own payment icons through.
	 */
	public function test_output_keeps_only_bundled_payment_icons(): void {
		$sanitizer = new Sanitizer();

		$ok = $sanitizer->sanitize_output( '<img src="' . self::BASE . 'visa.svg" alt="Visa" />' );
		$this->assertStringContainsString( '<img', $ok );

		foreach (
			array(
				'<img src="https://evil.test/track.gif" alt="" />',
				'<img src="https://example.test/wp-content/uploads/photo.png" alt="" />',
				'<img src="' . self::BASE . '../../../evil.svg" alt="" />',
				'<img src="' . self::BASE . 'visa.png" alt="" />',
				'<img alt="no src" />',
			) as $bad
		) {
			$this->assertStringNotContainsString( '<img', $sanitizer->sanitize_output( 'a ' . $bad . ' b' ), $bad );
		}
	}

	/**
	 * Build an engine with the token and a fixed icon base URL.
	 */
	private function engine(): TemplateEngine {
		return new TemplateEngine(
			new TemplateRequirements(
				new MergeTagParser(),
				new HtmlPlacementValidator(),
				new SourceTokenRules()
			),
			new Sanitizer(),
			array(
				new PaymentIconsToken(
					static function (): string {
						return self::BASE;
					}
				),
			)
		);
	}
}
