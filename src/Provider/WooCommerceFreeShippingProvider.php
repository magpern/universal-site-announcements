<?php
/**
 * WooCommerce free-shipping announcement provider.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Provider;

use USA\Announcement\Sanitizer;
use USA\Lifecycle\Schema;

/**
 * Discovers free-shipping thresholds and formats them via hybrid UMC rules.
 *
 * Message authoring is template-based (M3); this class no longer sprintf-builds
 * visitor copy as the sole path. {@see Schema::DEFAULT_FREE_SHIPPING_TEMPLATE}.
 */
final class WooCommerceFreeShippingProvider {

	public const SOURCE = 'woocommerce_free_shipping';

	/**
	 * Eligibility gate.
	 *
	 * @var EligibilityGate
	 */
	private EligibilityGate $gate;

	/**
	 * UMC threshold display gateway.
	 *
	 * @var UmcThresholdDisplay
	 */
	private UmcThresholdDisplay $umc;

	/**
	 * UMC activity / hybrid display.
	 *
	 * @var UmcActivity
	 */
	private UmcActivity $activity;

	/**
	 * Content / price sanitiser.
	 *
	 * @var Sanitizer
	 */
	private Sanitizer $sanitizer;

	/**
	 * Last suppression reason for diagnostics.
	 *
	 * @var string
	 */
	private string $last_suppression_reason = '';

	/**
	 * Cached diagnostic snapshot from the last resolve/diagnose call.
	 *
	 * @var array<string, mixed>
	 */
	private array $last_diagnostic = array();

	/**
	 * Constructor.
	 *
	 * @param EligibilityGate     $gate      Eligibility allowlist gate.
	 * @param UmcThresholdDisplay $umc       UMC API gateway.
	 * @param Sanitizer           $sanitizer Sanitiser for price HTML.
	 * @param UmcActivity|null    $activity  Optional hybrid activity detector.
	 */
	public function __construct(
		EligibilityGate $gate,
		UmcThresholdDisplay $umc,
		Sanitizer $sanitizer,
		?UmcActivity $activity = null
	) {
		$this->gate      = $gate;
		$this->umc       = $umc;
		$this->sanitizer = $sanitizer;
		$this->activity  = $activity ?? new UmcActivity();
	}

	/**
	 * Discover the authoritative base min_amount, or null when suppressed.
	 *
	 * Runs eligibility, reference package, requires=min_amount matrix, uniqueness.
	 * Does not format currency or build message HTML.
	 */
	public function resolve_base_threshold(): ?string {
		$this->last_suppression_reason = '';
		$this->last_diagnostic         = array(
			'reference_country'  => '',
			'zone_name'          => '',
			'method_id'          => '',
			'base_min_amount'    => '',
			'umc_active'         => $this->activity->is_umc_active(),
			'umc_api_available'  => $this->activity->is_api_available() || $this->umc->is_available(),
			'suppression_reason' => '',
			'eligibility_ok'     => false,
		);

		if ( ! $this->woocommerce_available() ) {
			return $this->suppress( 'woocommerce_unavailable' );
		}

		$package                                    = $this->reference_package();
		$this->last_diagnostic['reference_country'] = (string) ( $package['destination']['country'] ?? '' );

		$qualifying = $this->find_qualifying_methods( $package );
		if ( array() === $qualifying ) {
			return $this->suppress( 'no_qualifying_free_shipping_method' );
		}

		$amounts = array();
		foreach ( $qualifying as $row ) {
			$amounts[ $row['min_amount'] ] = true;
			if ( '' === $this->last_diagnostic['zone_name'] ) {
				$this->last_diagnostic['zone_name'] = $row['zone_name'];
				$this->last_diagnostic['method_id'] = $row['method_id'];
			}
		}

		if ( count( $amounts ) > 1 ) {
			return $this->suppress( 'conflicting_min_amount_thresholds' );
		}

		$base_min                                 = (string) array_key_first( $amounts );
		$this->last_diagnostic['base_min_amount'] = $base_min;

		if ( ! $this->gate->passes() ) {
			$reason                                  = $this->gate->failure_reason();
			$this->last_diagnostic['eligibility_ok'] = false;
			return $this->suppress( '' !== $reason ? $reason : 'eligibility_gate_failed' );
		}
		$this->last_diagnostic['eligibility_ok'] = true;

		return $base_min;
	}

	/**
	 * Hybrid-format a discovered base threshold to sanitised price HTML.
	 *
	 * @param string $base Base-currency threshold decimal string.
	 */
	public function resolve_threshold_html( string $base ): ?string {
		$html = $this->activity->resolve_threshold_html( $base, $this->umc, $this->sanitizer );
		if ( null === $html ) {
			$reason                                      = $this->activity->failure_reason( $base, $this->umc );
			$this->last_suppression_reason               = '' !== $reason ? $reason : 'threshold_html_unavailable';
			$this->last_diagnostic['suppression_reason'] = $this->last_suppression_reason;
			$this->last_diagnostic['umc_active']         = $this->activity->is_umc_active();
			$this->last_diagnostic['umc_api_available']  = $this->umc->is_available();
			return null;
		}

		$this->last_suppression_reason               = '';
		$this->last_diagnostic['suppression_reason'] = '';
		return $html;
	}

	/**
	 * Legacy helper: discover + format (no template). Prefer template path.
	 *
	 * @return string|null Formatted threshold HTML only (not a full sentence).
	 */
	public function resolve_message(): ?string {
		$base = $this->resolve_base_threshold();
		if ( null === $base ) {
			return null;
		}
		return $this->resolve_threshold_html( $base );
	}

	/**
	 * Diagnostic snapshot for admin preview.
	 *
	 * @return array<string, mixed>
	 */
	public function diagnose(): array {
		$base = $this->resolve_base_threshold();
		if ( null !== $base ) {
			$this->resolve_threshold_html( $base );
		}
		return $this->last_diagnostic;
	}

	/**
	 * Last suppression reason (empty when last resolve succeeded).
	 */
	public function last_suppression_reason(): string {
		return $this->last_suppression_reason;
	}

	/**
	 * UMC activity helper (admin / diagnostics).
	 */
	public function activity(): UmcActivity {
		return $this->activity;
	}

	/**
	 * Default migration seed template (not a runtime sprintf path).
	 */
	public static function default_template(): string {
		return Schema::DEFAULT_FREE_SHIPPING_TEMPLATE;
	}

	/**
	 * Record suppression reason and return null.
	 *
	 * @param string $reason Suppression reason code/message.
	 */
	private function suppress( string $reason ): ?string {
		$this->last_suppression_reason               = $reason;
		$this->last_diagnostic['suppression_reason'] = $reason;
		return null;
	}

	/**
	 * Whether WooCommerce shipping APIs are available.
	 */
	private function woocommerce_available(): bool {
		return function_exists( 'WC' )
			&& class_exists( '\WC_Shipping_Zones' )
			&& is_object( WC() )
			&& isset( WC()->countries )
			&& is_object( WC()->countries );
	}

	/**
	 * Reference package for zone matching.
	 *
	 * @return array<string, mixed>
	 */
	private function reference_package(): array {
		$country = '';
		$state   = '';
		if ( $this->woocommerce_available() ) {
			$base    = (string) WC()->countries->get_base_country();
			$country = $base;
			$state   = (string) WC()->countries->get_base_state();
		}

		$package = array(
			'contents'        => array(),
			'contents_cost'   => 0,
			'applied_coupons' => array(),
			'user'            => array(
				'ID' => get_current_user_id(),
			),
			'destination'     => array(
				'country'   => $country,
				'state'     => $state,
				'postcode'  => '',
				'city'      => '',
				'address'   => '',
				'address_1' => '',
				'address_2' => '',
			),
			'cart_subtotal'   => 0,
		);

		/**
		 * Filters the reference shipping package used to locate free-shipping methods.
		 *
		 * @param array<string, mixed> $package Reference package.
		 */
		$filtered = apply_filters( 'usa_free_shipping_reference_package', $package );
		return is_array( $filtered ) ? $filtered : $package;
	}

	/**
	 * Find enabled free_shipping methods with requires=min_amount and positive min_amount.
	 *
	 * @param array<string, mixed> $package Reference package.
	 * @return list<array{min_amount:string,zone_name:string,method_id:string}>
	 */
	private function find_qualifying_methods( array $package ): array {
		if ( ! class_exists( '\WC_Shipping_Zones' ) ) {
			return array();
		}

		$zone = \WC_Shipping_Zones::get_zone_matching_package( $package );
		if ( ! is_object( $zone ) || ! method_exists( $zone, 'get_shipping_methods' ) ) {
			return array();
		}

		$zone_name = method_exists( $zone, 'get_zone_name' ) ? (string) $zone->get_zone_name() : '';
		$methods   = $zone->get_shipping_methods( true );
		if ( ! is_array( $methods ) ) {
			return array();
		}

		$found = array();
		foreach ( $methods as $method ) {
			if ( ! is_object( $method ) || ! isset( $method->id ) || 'free_shipping' !== $method->id ) {
				continue;
			}
			if ( method_exists( $method, 'is_enabled' ) && ! $method->is_enabled() ) {
				continue;
			}

			$requires = method_exists( $method, 'get_option' )
				? (string) $method->get_option( 'requires' )
				: '';
			if ( 'min_amount' !== $requires ) {
				continue;
			}

			$min_raw = method_exists( $method, 'get_option' )
				? (string) $method->get_option( 'min_amount' )
				: '';
			if ( '' === $min_raw || ! is_numeric( $min_raw ) || (float) $min_raw <= 0 ) {
				continue;
			}

			$min_amount = $this->normalize_decimal_string( $min_raw );

			$method_id = '';
			if ( isset( $method->instance_id ) ) {
				$method_id = 'free_shipping:' . (string) $method->instance_id;
			} elseif ( method_exists( $method, 'get_rate_id' ) ) {
				$method_id = (string) $method->get_rate_id();
			} else {
				$method_id = 'free_shipping';
			}

			$found[] = array(
				'min_amount' => $min_amount,
				'zone_name'  => $zone_name,
				'method_id'  => $method_id,
			);
		}

		return $found;
	}

	/**
	 * Normalise a numeric string without rounding monetary semantics.
	 *
	 * @param string $raw Raw min_amount option.
	 */
	private function normalize_decimal_string( string $raw ): string {
		$raw = trim( $raw );
		if ( is_numeric( $raw ) && false === strpos( $raw, '.' ) ) {
			return $raw . '.00';
		}
		return $raw;
	}
}
