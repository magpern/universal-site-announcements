<?php
/**
 * WooCommerce free-shipping announcement provider.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Provider;

use USA\Announcement\Sanitizer;

/**
 * Builds a currency-aware free-shipping announcement via the UMC public API.
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
	 */
	public function __construct(
		EligibilityGate $gate,
		UmcThresholdDisplay $umc,
		Sanitizer $sanitizer
	) {
		$this->gate      = $gate;
		$this->umc       = $umc;
		$this->sanitizer = $sanitizer;
	}

	/**
	 * Resolve visitor-facing message HTML, or null when suppressed.
	 */
	public function resolve_message(): ?string {
		$this->last_suppression_reason = '';
		$this->last_diagnostic         = array(
			'reference_country'  => '',
			'zone_name'          => '',
			'method_id'          => '',
			'base_min_amount'    => '',
			'umc_available'      => $this->umc->is_available(),
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

		if ( ! $this->umc->is_available() ) {
			return $this->suppress( 'umc_api_unavailable' );
		}

		$display = $this->umc->get( $base_min );
		if ( null === $display ) {
			return $this->suppress( 'umc_api_returned_null' );
		}

		$message                                     = $this->build_message( $display['formatted_html'] );
		$this->last_diagnostic['suppression_reason'] = '';
		return $message;
	}

	/**
	 * Diagnostic snapshot for admin preview.
	 *
	 * @return array<string, mixed>
	 */
	public function diagnose(): array {
		$this->resolve_message();
		return $this->last_diagnostic;
	}

	/**
	 * Last suppression reason (empty when last resolve succeeded).
	 */
	public function last_suppression_reason(): string {
		return $this->last_suppression_reason;
	}

	/**
	 * Build the announcement from formatted_html only (no money math).
	 *
	 * @param string $formatted_html UMC formatted_html (wc_price markup).
	 */
	public function build_message( string $formatted_html ): string {
		$amount_html = $this->sanitizer->sanitize_price_html( $formatted_html );

		/**
		 * Filters the free-shipping announcement message template.
		 *
		 * Must contain a single %s placeholder for the amount HTML.
		 *
		 * @param string $template Message template.
		 */
		$template = (string) apply_filters(
			'usa_free_shipping_message_template',
			/* translators: %s: formatted free-shipping threshold HTML from UMC */
			__( 'Free shipping on orders of %s or more', 'universal-site-announcements' )
		);

		if ( ! is_string( $template ) || '' === $template ) {
			$template = 'Free shipping on orders of %s or more';
		}

		return sprintf( $template, $amount_html );
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

			// Canonical decimal string without inventing conversion.
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
