<?php
/**
 * Free-shipping provider / hybrid UMC contract tests.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Announcement\Sanitizer;
use USA\Lifecycle\Schema;
use USA\Provider\EligibilityGate;
use USA\Provider\UmcActivity;
use USA\Provider\UmcThresholdDisplay;
use USA\Provider\WooCommerceFreeShippingProvider;

/**
 * Proves USA uses formatted_html / wc_price as-is; never invents currency math.
 */
final class FreeShippingProviderContractTest extends TestCase {

	/**
	 * Reset filters.
	 */
	protected function tearDown(): void {
		$GLOBALS['usa_test_filters'] = array();
		parent::tearDown();
	}

	/**
	 * When the global function is missing, gateway returns null.
	 */
	public function test_umc_gateway_null_when_function_missing(): void {
		$this->assertFalse( function_exists( 'umc_get_free_shipping_threshold_display' ) );
		$umc = new UmcThresholdDisplay();
		$this->assertFalse( $umc->is_available() );
		$this->assertNull( $umc->get( '200.00' ) );
	}

	/**
	 * Injected gateway null → null (API returned null).
	 */
	public function test_umc_gateway_null_passthrough(): void {
		$umc = new UmcThresholdDisplay(
			static function ( string $base ): ?array {
				unset( $base );
				return null;
			}
		);
		$this->assertTrue( $umc->is_available() );
		$this->assertNull( $umc->get( '200.00' ) );
	}

	/**
	 * Hybrid: UMC inactive → wc_price base HTML sanitised (no rate math).
	 */
	public function test_hybrid_inactive_uses_wc_price(): void {
		$formatted = '<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">€</span>200.00</bdi></span>';
		$activity  = new UmcActivity(
			static function (): bool {
				return false;
			},
			null,
			static function ( string $base ) use ( $formatted ): string {
				unset( $base );
				return $formatted;
			}
		);
		$umc       = new UmcThresholdDisplay();
		$sanitizer = new Sanitizer();
		$html      = $activity->resolve_threshold_html( '200.00', $umc, $sanitizer );
		$this->assertNotNull( $html );
		$this->assertStringContainsString( '200.00', $html );
		$this->assertStringNotContainsString( '220.00', $html );
	}

	/**
	 * Hybrid: UMC active + API missing → null (no wc_price fallback).
	 */
	public function test_hybrid_active_api_missing_suppresses(): void {
		$activity = new UmcActivity(
			static function (): bool {
				return true;
			},
			static function (): bool {
				return false;
			},
			static function (): string {
				return '<span>SHOULD_NOT_USE</span>';
			}
		);
		$umc = new UmcThresholdDisplay();
		$this->assertNull( $activity->resolve_threshold_html( '200.00', $umc, new Sanitizer() ) );
		$this->assertSame( 'umc_api_unavailable', $activity->failure_reason( '200.00', $umc ) );
	}

	/**
	 * Hybrid: UMC active + API success → formatted_html unchanged.
	 */
	public function test_hybrid_active_api_success(): void {
		$formatted = '<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">$</span>218.00</bdi></span>';
		$activity  = new UmcActivity(
			static function (): bool {
				return true;
			},
			static function (): bool {
				return true;
			}
		);
		$umc = new UmcThresholdDisplay(
			static function () use ( $formatted ): array {
				return array(
					'formatted_html' => $formatted,
					'amount'         => '218.00',
					'currency_code'  => 'USD',
				);
			}
		);
		$html = $activity->resolve_threshold_html( '200.00', $umc, new Sanitizer() );
		$this->assertSame( $formatted, $html );
		$this->assertStringContainsString( '$</span>218.00', $html );
	}

	/**
	 * Hybrid: UMC active + API null → suppress.
	 */
	public function test_hybrid_active_api_null_suppresses(): void {
		$activity = new UmcActivity(
			static function (): bool {
				return true;
			},
			static function (): bool {
				return true;
			}
		);
		$umc = new UmcThresholdDisplay(
			static function (): ?array {
				return null;
			}
		);
		$this->assertNull( $activity->resolve_threshold_html( '200.00', $umc, new Sanitizer() ) );
	}

	/**
	 * Default migration seed constant is the locked English template.
	 */
	public function test_default_template_constant(): void {
		$this->assertSame(
			'Free shipping on orders of {{free_shipping_threshold}} or more',
			Schema::DEFAULT_FREE_SHIPPING_TEMPLATE
		);
		$this->assertSame( Schema::DEFAULT_FREE_SHIPPING_TEMPLATE, WooCommerceFreeShippingProvider::default_template() );
	}

	/**
	 * resolve_message suppresses when WooCommerce missing (fail closed).
	 */
	public function test_resolve_suppresses_when_woocommerce_missing(): void {
		$provider = new WooCommerceFreeShippingProvider(
			new EligibilityGate(),
			new UmcThresholdDisplay(),
			new Sanitizer(),
			new UmcActivity(
				static function (): bool {
					return false;
				}
			)
		);
		$this->assertNull( $provider->resolve_message() );
		$this->assertNotSame( '', $provider->last_suppression_reason() );
	}

	/**
	 * Price HTML sanitiser keeps wc_price tags, strips scripts.
	 */
	public function test_price_html_sanitizer_allowlist(): void {
		$s   = new Sanitizer();
		$in  = '<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">€</span>10</bdi></span><script>x</script>';
		$out = $s->sanitize_price_html( $in );
		$this->assertStringContainsString( 'woocommerce-Price-amount', $out );
		$this->assertStringContainsString( '<bdi>', $out );
		$this->assertStringNotContainsString( '<script>', $out );
	}
}
