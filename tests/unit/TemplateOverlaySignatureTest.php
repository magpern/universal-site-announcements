<?php
/**
 * TemplateOverlay merge-tag signature tests.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Integration\AimlCompatibility;
use USA\Integration\TemplateOverlay;
use USA\Template\HtmlPlacementValidator;
use USA\Template\MergeTagParser;
use USA\Template\SourceTokenRules;
use USA\Template\TemplateRequirements;

/**
 * @covers \USA\Integration\TemplateOverlay
 */
final class TemplateOverlaySignatureTest extends TestCase {

	private TemplateRequirements $requirements;

	protected function setUp(): void {
		$this->requirements = new TemplateRequirements(
			new MergeTagParser(),
			new HtmlPlacementValidator(),
			new SourceTokenRules()
		);
	}

	public function test_matching_free_shipping_signature(): void {
		$source  = $this->requirements->analyse( 'Ship from {{free_shipping_threshold}} today' );
		$overlay = $this->requirements->analyse( 'Envío desde {{free_shipping_threshold}} hoy' );
		$this->assertTrue(
			TemplateOverlay::token_signature_matches( $source['token_list'], $overlay['token_list'] )
		);
	}

	public function test_matching_product_id_signature(): void {
		$source  = $this->requirements->analyse( 'See {{product:123}}' );
		$overlay = $this->requirements->analyse( 'Ver {{product:123}}' );
		$this->assertTrue(
			TemplateOverlay::token_signature_matches( $source['token_list'], $overlay['token_list'] )
		);
	}

	public function test_altered_product_id_mismatches(): void {
		$source  = $this->requirements->analyse( 'See {{product:123}}' );
		$overlay = $this->requirements->analyse( 'See {{product:456}}' );
		$this->assertFalse(
			TemplateOverlay::token_signature_matches( $source['token_list'], $overlay['token_list'] )
		);
	}

	public function test_added_token_mismatches(): void {
		$source  = $this->requirements->analyse( 'Orders of {{free_shipping_threshold}}' );
		$overlay = $this->requirements->analyse( 'Orders of {{free_shipping_threshold}} plus {{product:1}}' );
		$this->assertFalse(
			TemplateOverlay::token_signature_matches( $source['token_list'], $overlay['token_list'] )
		);
	}

	public function test_removed_token_mismatches(): void {
		$source  = $this->requirements->analyse( 'Orders of {{free_shipping_threshold}}' );
		$overlay = $this->requirements->analyse( 'Orders of fifty' );
		$this->assertFalse(
			TemplateOverlay::token_signature_matches( $source['token_list'], $overlay['token_list'] )
		);
	}

	public function test_apply_returns_source_when_aiml_incompatible(): void {
		$overlay = new TemplateOverlay( new AimlCompatibility(), $this->requirements, null );
		$source  = 'Hello {{product:10}}';
		$this->assertSame( $source, $overlay->apply( 42, $source ) );
	}
}
