<?php
/**
 * Token allowlists and cardinality rules.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Template;

use USA\Provider\WooCommerceFreeShippingProvider;

/**
 * Enforces merge-tag structure and (legacy) source-scoped cardinality.
 */
final class SourceTokenRules {

	public const TOKEN_FREE_SHIPPING = 'free_shipping_threshold';

	public const TOKEN_PRODUCT = 'product';

	public const TOKEN_PAGE = 'page';

	/**
	 * Validate token structure independent of persisted source.
	 *
	 * Allows zero or one free-shipping threshold token. Duplicates / unknowns fail.
	 *
	 * @param MergeToken[] $tokens Parsed tokens.
	 * @return string|null Null when valid; reason code when invalid.
	 */
	public function validate_structure( array $tokens ): ?string {
		$threshold_count = 0;

		foreach ( $tokens as $token ) {
			if ( self::TOKEN_FREE_SHIPPING === $token->name ) {
				if ( null !== $token->arg ) {
					return 'invalid_token_argument';
				}
				++$threshold_count;
				if ( $threshold_count > 1 ) {
					return 'free_shipping_token_count';
				}
				continue;
			}

			if ( self::TOKEN_PRODUCT === $token->name ) {
				if ( null === $token->arg || ! $this->is_positive_int_string( $token->arg ) ) {
					return 'invalid_product_token';
				}
				continue;
			}

			if ( self::TOKEN_PAGE === $token->name ) {
				if ( null === $token->arg || ! $this->is_positive_int_string( $token->arg ) ) {
					return 'invalid_page_token';
				}
				continue;
			}

			return 'unknown_token';
		}

		return null;
	}

	/**
	 * Whether validated tokens require the free-shipping path.
	 *
	 * @param MergeToken[] $tokens Parsed tokens (must already pass validate_structure).
	 */
	public function requires_free_shipping( array $tokens ): bool {
		foreach ( $tokens as $token ) {
			if ( self::TOKEN_FREE_SHIPPING === $token->name ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Validate parsed tokens against a derived or legacy source.
	 *
	 * @param string       $source Announcement source.
	 * @param MergeToken[] $tokens Parsed tokens.
	 * @return string|null Null when valid; reason code when invalid.
	 */
	public function validate( string $source, array $tokens ): ?string {
		$structure = $this->validate_structure( $tokens );
		if ( null !== $structure ) {
			return $structure;
		}

		$threshold_count = 0;
		foreach ( $tokens as $token ) {
			if ( self::TOKEN_FREE_SHIPPING === $token->name ) {
				++$threshold_count;
			}
		}

		if ( WooCommerceFreeShippingProvider::SOURCE === $source ) {
			if ( 1 !== $threshold_count ) {
				return 'free_shipping_token_count';
			}
		} elseif ( $threshold_count > 0 ) {
			return 'free_shipping_token_forbidden';
		}

		return null;
	}

	/**
	 * Token names insertable in the editor (no source choice required).
	 *
	 * @return list<string>
	 */
	public function insertable_names(): array {
		return array( self::TOKEN_FREE_SHIPPING, self::TOKEN_PRODUCT, self::TOKEN_PAGE );
	}

	/**
	 * Token names allowed for a source (legacy helper).
	 *
	 * @param string $source Source.
	 * @return list<string>
	 */
	public function allowed_names( string $source ): array {
		unset( $source );
		return $this->insertable_names();
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
