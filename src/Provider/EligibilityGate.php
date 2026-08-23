<?php
/**
 * Free-shipping eligibility callback allowlist gate.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Provider;

/**
 * Inventories callbacks on woocommerce_shipping_free_shipping_is_available
 * and allowlists the known-compatible UMC method.
 */
final class EligibilityGate {

	public const HOOK = 'woocommerce_shipping_free_shipping_is_available';

	/**
	 * Known-compatible callback identity.
	 */
	public const ALLOWLISTED_CALLBACK = 'UMC\\Integration\\ShippingConversion::filter_free_shipping_availability';

	/**
	 * Whether the provider may claim free-shipping eligibility truthfully.
	 */
	public function passes(): bool {
		/**
		 * Escape hatch after site-specific review of eligibility callbacks.
		 *
		 * @param bool $verified Whether eligibility is deliberately verified.
		 */
		if ( true === (bool) apply_filters( 'usa_free_shipping_provider_eligibility_verified', false ) ) {
			return true;
		}

		$foreign = $this->foreign_callbacks( $this->inventory_callback_identities() );
		return array() === $foreign;
	}

	/**
	 * Human-readable suppression reason when the gate fails; empty when OK.
	 */
	public function failure_reason(): string {
		if ( $this->passes() ) {
			return '';
		}

		$foreign = $this->foreign_callbacks( $this->inventory_callback_identities() );
		if ( array() === $foreign ) {
			return '';
		}

		return sprintf(
			'non-allowlisted callback on %s: %s',
			self::HOOK,
			implode( ', ', $foreign )
		);
	}

	/**
	 * Collect string identities for callbacks registered on the eligibility hook.
	 *
	 * @return list<string>
	 */
	public function inventory_callback_identities(): array {
		if ( ! function_exists( 'has_filter' ) && ! isset( $GLOBALS['wp_filter'][ self::HOOK ] ) ) {
			return array();
		}

		global $wp_filter;
		if ( ! isset( $wp_filter[ self::HOOK ] ) ) {
			return array();
		}

		$hook      = $wp_filter[ self::HOOK ];
		$callbacks = array();

		if ( is_object( $hook ) && isset( $hook->callbacks ) && is_array( $hook->callbacks ) ) {
			foreach ( $hook->callbacks as $priority_group ) {
				if ( ! is_array( $priority_group ) ) {
					continue;
				}
				foreach ( $priority_group as $entry ) {
					if ( ! is_array( $entry ) || ! isset( $entry['function'] ) ) {
						continue;
					}
					$callbacks[] = self::callback_identity( $entry['function'] );
				}
			}
		} elseif ( is_array( $hook ) ) {
			// Legacy WP_Hook array shape (tests / very old WP).
			foreach ( $hook as $priority_group ) {
				if ( ! is_array( $priority_group ) ) {
					continue;
				}
				foreach ( $priority_group as $entry ) {
					if ( ! is_array( $entry ) || ! isset( $entry['function'] ) ) {
						continue;
					}
					$callbacks[] = self::callback_identity( $entry['function'] );
				}
			}
		}

		return $callbacks;
	}

	/**
	 * Callbacks that are not on the allowlist.
	 *
	 * @param array<int, string> $identities Callback identities.
	 * @return array<int, string>
	 */
	public function foreign_callbacks( array $identities ): array {
		$foreign = array();
		foreach ( $identities as $identity ) {
			if ( ! $this->is_allowlisted( $identity ) ) {
				$foreign[] = $identity;
			}
		}
		return $foreign;
	}

	/**
	 * Whether a callback identity is allowlisted.
	 *
	 * @param string $identity Callback identity string.
	 */
	public function is_allowlisted( string $identity ): bool {
		return self::ALLOWLISTED_CALLBACK === $identity;
	}

	/**
	 * Stable string identity for a WordPress filter callback.
	 *
	 * @param mixed $callback Callback as stored by WordPress.
	 */
	public static function callback_identity( $callback ): string {
		if ( is_string( $callback ) ) {
			return $callback;
		}

		if ( is_array( $callback ) && isset( $callback[0], $callback[1] ) ) {
			$class = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
			return $class . '::' . (string) $callback[1];
		}

		if ( $callback instanceof \Closure ) {
			return 'Closure';
		}

		return 'unknown';
	}
}
