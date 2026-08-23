<?php
/**
 * Free-shipping provider / UMC consumer contract tests.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Announcement\Sanitizer;
use USA\Provider\EligibilityGate;
use USA\Provider\UmcThresholdDisplay;
use USA\Provider\WooCommerceFreeShippingProvider;

/**
 * Proves USA does not multiply rates/round; uses formatted_html as-is.
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
	 * Base-currency formatted_html is used unchanged in the message (no rate math).
	 */
	public function test_base_formatted_html_used_as_is(): void {
		$formatted = '<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">€</span>200.00</bdi></span>';
		$seen_base = null;
		$provider  = $this->provider_with_gateway(
			static function ( string $base ) use ( $formatted, &$seen_base ): array {
				$seen_base = $base;
				return array(
					'formatted_html' => $formatted,
					'amount'         => '200.00',
					'currency_code'  => 'EUR',
				);
			}
		);

		$message = $provider->build_message( $formatted );
		$this->assertNull( $seen_base ); // build_message must not call the gateway.
		$this->assertStringContainsString( $formatted, $message );
		$this->assertStringContainsString( 'Free shipping on orders of', $message );
		$this->assertStringContainsString( 'or more', $message );
		// No invented multiplication artifacts.
		$this->assertStringNotContainsString( '220.00', $message );
		$this->assertStringNotContainsString( 'multiply', $message );
	}

	/**
	 * Foreign-currency formatted_html is concatenated unchanged (no re-round).
	 */
	public function test_foreign_formatted_html_unchanged(): void {
		$formatted = '<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">$</span>218.00</bdi></span>';
		$provider  = $this->provider_with_gateway(
			static function () use ( $formatted ): array {
				return array(
					'formatted_html' => $formatted,
					'amount'         => '218.00',
					'currency_code'  => 'USD',
				);
			}
		);

		$message = $provider->build_message( $formatted );
		$this->assertSame(
			'Free shipping on orders of ' . $formatted . ' or more',
			$message
		);
		// Prove builder did not alter the amount digits inside formatted_html.
		$this->assertStringContainsString( '$</span>218.00', $message );
		$this->assertStringNotContainsString( '217.99', $message );
		$this->assertStringNotContainsString( '218.000', $message );
	}

	/**
	 * Message builder only concatenates; never calls conversion helpers.
	 */
	public function test_build_message_does_not_inspect_rates_or_cookies(): void {
		$calls     = array();
		$formatted = '<span class="amount">100</span>';
		$provider  = $this->provider_with_gateway(
			static function ( string $base ) use ( &$calls, $formatted ): array {
				$calls[] = $base;
				return array(
					'formatted_html' => $formatted,
					'amount'         => '100',
					'currency_code'  => 'EUR',
				);
			}
		);

		// build_message must not invoke the gateway at all.
		$out = $provider->build_message( $formatted );
		$this->assertSame( array(), $calls );
		$this->assertStringContainsString( $formatted, $out );
	}

	/**
	 * resolve_message suppresses when UMC function missing (fail closed).
	 */
	public function test_resolve_suppresses_when_umc_missing(): void {
		$provider = new WooCommerceFreeShippingProvider(
			new EligibilityGate(),
			new UmcThresholdDisplay(),
			new Sanitizer()
		);
		// Without WooCommerce, suppresses earlier — still null / fail closed.
		$this->assertNull( $provider->resolve_message() );
		$this->assertNotSame( '', $provider->last_suppression_reason() );
	}

	/**
	 * Template filter still receives only formatted amount HTML via sprintf.
	 */
	public function test_template_filter_uses_formatted_html_placeholder(): void {
		$formatted = '<span class="woocommerce-Price-amount">99</span>';
		$GLOBALS['usa_test_filters']['usa_free_shipping_message_template'] = static function () {
			return 'Ship free from %s today';
		};

		$provider = $this->provider_with_gateway( null );
		$message  = $provider->build_message( $formatted );
		$this->assertSame( 'Ship free from ' . $formatted . ' today', $message );
	}

	/**
	 * Price HTML sanitiser keeps wc_price tags, strips scripts.
	 */
	public function test_price_html_sanitizer_allowlist(): void {
		$s = new Sanitizer();
		$in = '<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">€</span>10</bdi></span><script>x</script>';
		$out = $s->sanitize_price_html( $in );
		$this->assertStringContainsString( 'woocommerce-Price-amount', $out );
		$this->assertStringContainsString( '<bdi>', $out );
		$this->assertStringNotContainsString( '<script>', $out );
	}

	/**
	 * @param callable(string):(?array{formatted_html:string,amount:string,currency_code:string})|null $gateway Gateway.
	 */
	private function provider_with_gateway( ?callable $gateway ): WooCommerceFreeShippingProvider {
		$umc = null === $gateway
			? new UmcThresholdDisplay(
				static function (): array {
					return array(
						'formatted_html' => 'x',
						'amount'         => '0',
						'currency_code'  => 'EUR',
					);
				}
			)
			: new UmcThresholdDisplay( $gateway );

		return new WooCommerceFreeShippingProvider(
			new EligibilityGate(),
			$umc,
			new Sanitizer()
		);
	}
}
