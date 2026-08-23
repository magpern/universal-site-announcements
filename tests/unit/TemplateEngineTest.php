<?php
/**
 * Template engine and token resolution tests.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Announcement\Sanitizer;
use USA\Provider\UmcActivity;
use USA\Provider\UmcThresholdDisplay;
use USA\Template\FreeShippingThresholdToken;
use USA\Template\HtmlPlacementValidator;
use USA\Template\MergeTagParser;
use USA\Template\ProductToken;
use USA\Template\SourceTokenRules;
use USA\Template\TemplateEngine;

/**
 * End-to-end template rendering behaviours.
 */
final class TemplateEngineTest extends TestCase {

	/**
	 * Manual static message without tokens is unchanged (aside from kses).
	 */
	public function test_manual_static_unchanged(): void {
		$engine = $this->engine();
		$out    = $engine->render( 'Hello <strong>world</strong>', 'manual' );
		$this->assertSame( 'Hello <strong>world</strong>', $out );
	}

	/**
	 * Free-shipping token resolves via context threshold_html.
	 */
	public function test_free_shipping_token_from_context(): void {
		$engine = $this->engine();
		$amount = '<span class="woocommerce-Price-amount amount">€10</span>';
		$out    = $engine->render(
			'Free shipping on orders of {{free_shipping_threshold}} or more',
			'woocommerce_free_shipping',
			array(
				'base_threshold' => '10.00',
				'threshold_html' => $amount,
			)
		);
		$this->assertNotNull( $out );
		$this->assertStringContainsString( $amount, $out );
		$this->assertStringNotContainsString( '{{', $out );
	}

	/**
	 * Product token renders safe link.
	 */
	public function test_product_token_link(): void {
		$engine = $this->engine_with_product(
			static function ( int $id ): ?array {
				if ( 42 !== $id ) {
					return null;
				}
				return array(
					'title' => 'Test Product',
					'url'   => 'https://example.test/product/test',
				);
			}
		);
		$out = $engine->render( 'Buy {{product:42}} now', 'manual' );
		$this->assertSame(
			'Buy <a href="https://example.test/product/test">Test Product</a> now',
			$out
		);
	}

	/**
	 * Missing product suppresses.
	 */
	public function test_missing_product_suppresses(): void {
		$engine = $this->engine_with_product(
			static function (): ?array {
				return null;
			}
		);
		$this->assertNull( $engine->render( 'Buy {{product:99}}', 'manual' ) );
		$this->assertStringContainsString( 'token_unresolved', $engine->last_reason() );
	}

	/**
	 * Duplicate product IDs each resolve.
	 */
	public function test_duplicate_product_ids_allowed(): void {
		$engine = $this->engine_with_product(
			static function ( int $id ): ?array {
				return array(
					'title' => 'P' . $id,
					'url'   => 'https://example.test/' . $id,
				);
			}
		);
		$out = $engine->render( '{{product:1}} and {{product:1}}', 'manual' );
		$this->assertNotNull( $out );
		$this->assertSame( 2, substr_count( $out, '<a href="https://example.test/1">P1</a>' ) );
	}

	/**
	 * Build engine with default free-shipping token (hybrid inactive + formatter).
	 */
	private function engine(): TemplateEngine {
		$sanitizer = new Sanitizer();
		$activity  = new UmcActivity(
			static function (): bool {
				return false;
			},
			null,
			static function ( string $base ): string {
				return '<span class="amount">' . $base . '</span>';
			}
		);
		$umc = new UmcThresholdDisplay();

		return new TemplateEngine(
			new MergeTagParser(),
			new HtmlPlacementValidator(),
			new SourceTokenRules(),
			$sanitizer,
			array(
				new FreeShippingThresholdToken( $activity, $umc, $sanitizer ),
				new ProductToken(),
			)
		);
	}

	/**
	 * @param callable(int):(?array{title:string,url:string}) $resolver Product resolver.
	 */
	private function engine_with_product( callable $resolver ): TemplateEngine {
		$sanitizer = new Sanitizer();
		$activity  = new UmcActivity(
			static function (): bool {
				return false;
			}
		);
		$umc = new UmcThresholdDisplay();

		return new TemplateEngine(
			new MergeTagParser(),
			new HtmlPlacementValidator(),
			new SourceTokenRules(),
			$sanitizer,
			array(
				new FreeShippingThresholdToken( $activity, $umc, $sanitizer ),
				new ProductToken( $resolver ),
			)
		);
	}
}
