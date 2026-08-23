<?php
/**
 * Source-scoped token allowlists.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Template;

use USA\Provider\WooCommerceFreeShippingProvider;

/**
 * Enforces which merge tags are allowed per announcement source.
 */
final class SourceTokenRules {

	public const TOKEN_FREE_SHIPPING = 'free_shipping_threshold';

	public const TOKEN_PRODUCT = 'product';

	/**
	 * Validate parsed tokens against the source allowlist / cardinality rules.
	 *
	 * @param string       $source Announcement source.
	 * @param MergeToken[] $tokens Parsed tokens.
	 * @return string|null Null when valid; reason code when invalid.
	 */
	public function validate( string $source, array $tokens ): ?string {
		$threshold_count = 0;

		foreach ( $tokens as $token ) {
			if ( self::TOKEN_FREE_SHIPPING === $token->name ) {
				if ( null !== $token->arg ) {
					return 'invalid_token_argument';
				}
				++$threshold_count;
				if ( WooCommerceFreeShippingProvider::SOURCE !== $source ) {
					return 'free_shipping_token_forbidden';
				}
				continue;
			}

			if ( self::TOKEN_PRODUCT === $token->name ) {
				if ( null === $token->arg || ! $this->is_positive_int_string( $token->arg ) ) {
					return 'invalid_product_token';
				}
				continue;
			}

			return 'unknown_token';
		}

		if ( WooCommerceFreeShippingProvider::SOURCE === $source ) {
			if ( 1 !== $threshold_count ) {
				return 'free_shipping_token_count';
			}
		}

		return null;
	}

	/**
	 * Token names allowed for a source (for admin insert UI).
	 *
	 * @param string $source Source.
	 * @return list<string>
	 */
	public function allowed_names( string $source ): array {
		if ( WooCommerceFreeShippingProvider::SOURCE === $source ) {
			return array( self::TOKEN_FREE_SHIPPING, self::TOKEN_PRODUCT );
		}
		return array( self::TOKEN_PRODUCT );
	}

	/**
	 * Whether the string is a positive integer without a leading zero.
	 *
	 * @param string $value Candidate.
	 */
	private function is_positive_int_string( string $value ): bool {
		return 1 === preg_match( '/^[1-9][0-9]*$/', $value );
	}
}
