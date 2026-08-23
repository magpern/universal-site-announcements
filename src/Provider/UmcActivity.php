<?php
/**
 * UMC activity detection and hybrid threshold display.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Provider;

use USA\Announcement\Sanitizer;

/**
 * Distinguishes UMC inactive vs active-but-API-missing for hybrid display.
 */
final class UmcActivity {

	/**
	 * Optional override for is_umc_active (tests).
	 *
	 * @var callable():bool|null
	 */
	private $active_gate;

	/**
	 * Optional override for is_api_available (tests).
	 *
	 * @var callable():bool|null
	 */
	private $api_gate;

	/**
	 * Optional override for base wc_price HTML (tests).
	 *
	 * @var callable(string):(?string)|null
	 */
	private $base_price_formatter;

	/**
	 * Constructor.
	 *
	 * @param callable|null $active_gate          Optional is_umc_active seam.
	 * @param callable|null $api_gate             Optional is_api_available seam.
	 * @param callable|null $base_price_formatter Optional wc_price seam: function(string): ?string.
	 */
	public function __construct(
		?callable $active_gate = null,
		?callable $api_gate = null,
		?callable $base_price_formatter = null
	) {
		$this->active_gate          = $active_gate;
		$this->api_gate             = $api_gate;
		$this->base_price_formatter = $base_price_formatter;
	}

	/**
	 * Whether Universal Multicurrency is active (canonical signal).
	 */
	public function is_umc_active(): bool {
		if ( null !== $this->active_gate ) {
			return (bool) ( $this->active_gate )();
		}

		return defined( 'UMC_PLUGIN_FILE' )
			&& class_exists( \UMC\Plugin::class );
	}

	/**
	 * Whether the public UMC threshold display API function exists.
	 */
	public function is_api_available(): bool {
		if ( null !== $this->api_gate ) {
			return (bool) ( $this->api_gate )();
		}

		return function_exists( 'umc_get_free_shipping_threshold_display' );
	}

	/**
	 * Hybrid threshold HTML for a base-currency decimal string.
	 *
	 * | UMC not active                | wc_price(base) + sanitize |
	 * | UMC active + API missing      | null (suppress)           |
	 * | UMC active + API null/invalid | null                      |
	 * | UMC active + API success      | sanitize formatted_html   |
	 *
	 * @param string              $base_threshold Base-currency threshold decimal.
	 * @param UmcThresholdDisplay $umc            UMC gateway.
	 * @param Sanitizer           $sanitizer      Price HTML sanitiser.
	 * @return string|null Sanitised price HTML, or null on failure.
	 */
	public function resolve_threshold_html(
		string $base_threshold,
		UmcThresholdDisplay $umc,
		Sanitizer $sanitizer
	): ?string {
		if ( ! $this->is_umc_active() ) {
			$html = $this->format_base_price( $base_threshold );
			if ( null === $html || '' === $html ) {
				return null;
			}
			$clean = $sanitizer->sanitize_price_html( $html );
			return '' !== $clean ? $clean : null;
		}

		// UMC active: never degrade to wc_price; require public API.
		if ( ! $this->is_api_available() ) {
			return null;
		}

		$display = $umc->get( $base_threshold );
		if ( null === $display ) {
			return null;
		}

		$clean = $sanitizer->sanitize_price_html( $display['formatted_html'] );
		return '' !== $clean ? $clean : null;
	}

	/**
	 * Hybrid failure reason when resolve_threshold_html would return null.
	 *
	 * @param string              $base_threshold Base threshold.
	 * @param UmcThresholdDisplay $umc            Gateway.
	 */
	public function failure_reason( string $base_threshold, UmcThresholdDisplay $umc ): string {
		if ( ! $this->is_umc_active() ) {
			$html = $this->format_base_price( $base_threshold );
			return ( null === $html || '' === $html ) ? 'wc_price_unavailable' : '';
		}
		if ( ! $this->is_api_available() ) {
			return 'umc_api_unavailable';
		}
		if ( null === $umc->get( $base_threshold ) ) {
			return 'umc_api_returned_null';
		}
		return '';
	}

	/**
	 * Format base currency via wc_price (or test seam).
	 *
	 * @param string $base_threshold Base decimal string.
	 */
	private function format_base_price( string $base_threshold ): ?string {
		if ( null !== $this->base_price_formatter ) {
			$result = ( $this->base_price_formatter )( $base_threshold );
			return is_string( $result ) ? $result : null;
		}

		if ( ! function_exists( 'wc_price' ) ) {
			return null;
		}

		$html = wc_price( $base_threshold );
		return is_string( $html ) ? $html : null;
	}
}
