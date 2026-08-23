<?php
/**
 * Free-shipping threshold merge tag.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Template;

use USA\Announcement\Sanitizer;
use USA\Provider\UmcActivity;
use USA\Provider\UmcThresholdDisplay;

/**
 * Resolves {{free_shipping_threshold}} via hybrid UMC display rules.
 */
final class FreeShippingThresholdToken implements TokenProvider {

	/**
	 * UMC activity / hybrid gateway.
	 *
	 * @var UmcActivity
	 */
	private UmcActivity $activity;

	/**
	 * UMC API display gateway.
	 *
	 * @var UmcThresholdDisplay
	 */
	private UmcThresholdDisplay $umc;

	/**
	 * Sanitizer.
	 *
	 * @var Sanitizer
	 */
	private Sanitizer $sanitizer;

	/**
	 * Constructor.
	 *
	 * @param UmcActivity         $activity  Activity detector.
	 * @param UmcThresholdDisplay $umc       UMC gateway.
	 * @param Sanitizer           $sanitizer Sanitiser.
	 */
	public function __construct(
		UmcActivity $activity,
		UmcThresholdDisplay $umc,
		Sanitizer $sanitizer
	) {
		$this->activity  = $activity;
		$this->umc       = $umc;
		$this->sanitizer = $sanitizer;
	}

	/**
	 * Whether this provider handles the token.
	 *
	 * @param string      $name Token name.
	 * @param string|null $arg  Optional numeric argument.
	 */
	public function supports( string $name, ?string $arg ): bool {
		return SourceTokenRules::TOKEN_FREE_SHIPPING === $name && null === $arg;
	}

	/**
	 * Resolve to a safe HTML fragment, or null on failure.
	 *
	 * Context keys:
	 * - base_threshold (string, required): discovered min_amount decimal
	 * - threshold_html (string, optional): pre-resolved HTML skips hybrid call
	 *
	 * @param string               $name    Token name.
	 * @param string|null          $arg     Optional argument.
	 * @param array<string, mixed> $context Render context.
	 */
	public function resolve( string $name, ?string $arg, array $context ): ?string {
		unset( $name, $arg );

		if ( isset( $context['threshold_html'] ) && is_string( $context['threshold_html'] ) && '' !== $context['threshold_html'] ) {
			$clean = $this->sanitizer->sanitize_price_html( $context['threshold_html'] );
			return '' !== $clean ? $clean : null;
		}

		$base = isset( $context['base_threshold'] ) ? (string) $context['base_threshold'] : '';
		if ( '' === $base ) {
			return null;
		}

		return $this->activity->resolve_threshold_html( $base, $this->umc, $this->sanitizer );
	}
}
