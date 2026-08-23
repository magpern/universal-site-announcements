<?php
/**
 * Thin UMC public-API gateway for free-shipping threshold display.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Provider;

/**
 * Feature-detects and calls umc_get_free_shipping_threshold_display only.
 *
 * Never converts, multiplies rates, rounds, or formats money itself.
 */
final class UmcThresholdDisplay {

	/**
	 * Optional injectable callable for tests: function(string): ?array.
	 *
	 * @var callable(string):(?array{formatted_html:string,amount:string,currency_code:string})|null
	 */
	private $gateway;

	/**
	 * Constructor.
	 *
	 * @param callable|null $gateway Optional test seam replacing the global function.
	 */
	public function __construct( ?callable $gateway = null ) {
		$this->gateway = $gateway;
	}

	/**
	 * Whether the UMC public function is available (or a test gateway is injected).
	 */
	public function is_available(): bool {
		if ( null !== $this->gateway ) {
			return true;
		}
		return function_exists( 'umc_get_free_shipping_threshold_display' );
	}

	/**
	 * Fetch display payload for a base-currency threshold decimal string.
	 *
	 * @param string $base_threshold_decimal_string Base-currency threshold.
	 * @return array{formatted_html:string,amount:string,currency_code:string}|null
	 */
	public function get( string $base_threshold_decimal_string ): ?array {
		if ( null !== $this->gateway ) {
			$result = ( $this->gateway )( $base_threshold_decimal_string );
			return $this->normalize( $result );
		}

		if ( ! function_exists( 'umc_get_free_shipping_threshold_display' ) ) {
			return null;
		}

		$result = umc_get_free_shipping_threshold_display( $base_threshold_decimal_string );
		return $this->normalize( $result );
	}

	/**
	 * Normalise and validate a gateway / API payload.
	 *
	 * @param mixed $result Raw API / gateway return.
	 * @return array{formatted_html:string,amount:string,currency_code:string}|null
	 */
	private function normalize( $result ): ?array {
		if ( ! is_array( $result ) ) {
			return null;
		}
		if (
			! isset( $result['formatted_html'], $result['amount'], $result['currency_code'] )
			|| ! is_string( $result['formatted_html'] )
			|| ! is_string( $result['amount'] )
			|| ! is_string( $result['currency_code'] )
		) {
			return null;
		}

		return array(
			'formatted_html' => $result['formatted_html'],
			'amount'         => $result['amount'],
			'currency_code'  => $result['currency_code'],
		);
	}
}
