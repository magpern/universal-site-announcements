<?php
/**
 * TemplateRequirements unit tests.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Template\HtmlPlacementValidator;
use USA\Template\MergeTagParser;
use USA\Template\SourceTokenRules;
use USA\Template\TemplateRequirements;

/**
 * Derived requirements from validated templates.
 */
final class TemplateRequirementsTest extends TestCase {

	/**
	 * Analyser helper.
	 */
	private function analyser(): TemplateRequirements {
		return new TemplateRequirements(
			new MergeTagParser(),
			new HtmlPlacementValidator(),
			new SourceTokenRules()
		);
	}

	/**
	 * Static / product-only → no special requirements.
	 */
	public function test_manual_and_product_only(): void {
		$a = $this->analyser();
		$static = $a->analyse( 'Hello world' );
		$this->assertTrue( $static['ok'] );
		$this->assertFalse( $static['requires_free_shipping'] );
		$this->assertSame( 'manual', $static['derived_source'] );

		$product = $a->analyse( 'Buy {{product:12}}' );
		$this->assertTrue( $product['ok'] );
		$this->assertFalse( $product['requires_free_shipping'] );
	}

	/**
	 * Threshold token derives free shipping.
	 */
	public function test_threshold_derives_shipping(): void {
		$a = $this->analyser()->analyse( 'Orders over {{free_shipping_threshold}}' );
		$this->assertTrue( $a['ok'] );
		$this->assertTrue( $a['requires_free_shipping'] );
		$this->assertSame( 'woocommerce_free_shipping', $a['derived_source'] );
	}

	/**
	 * Invalid templates do not derive permissive manual mode.
	 */
	public function test_invalid_fail_closed(): void {
		$a = $this->analyser()->analyse( 'Bad {{free_shipping_threshold' );
		$this->assertFalse( $a['ok'] );
		$this->assertFalse( $a['requires_free_shipping'] );

		$dup = $this->analyser()->analyse( '{{free_shipping_threshold}} {{free_shipping_threshold}}' );
		$this->assertFalse( $dup['ok'] );
		$this->assertSame( 'free_shipping_token_count', $dup['reason'] );
	}

	/**
	 * Status labels for admin.
	 */
	public function test_status_labels(): void {
		$req = $this->analyser();
		$humanize = static function ( string $r ): string {
			return $r;
		};
		$this->assertSame(
			'No special requirements',
			$req->status_label( $req->analyse( 'Hi' ), $humanize )
		);
		$this->assertSame(
			'Requires WooCommerce free shipping',
			$req->status_label( $req->analyse( '{{free_shipping_threshold}}' ), $humanize )
		);
		$invalid = $req->analyse( '{{broken' );
		$this->assertStringContainsString( 'Template is invalid', $req->status_label( $invalid, $humanize ) );
	}
}
