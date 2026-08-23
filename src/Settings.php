<?php
/**
 * Settings helpers.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA;

/**
 * Plugin settings accessors.
 */
final class Settings {

	public const OPTION_ENABLED = 'usa_plugin_enabled';

	public const OPTION_ROTATION = 'usa_rotation';

	public const DEFAULT_ROTATION_ENABLED = true;

	public const DEFAULT_INTERVAL_MS = 8000;

	public const DEFAULT_FADE_MS = 600;

	/** Minimum rotation interval (ms). */
	public const MIN_INTERVAL_MS = 2000;

	/** Maximum rotation interval (ms). */
	public const MAX_INTERVAL_MS = 120000;

	/** Minimum fade duration (ms). */
	public const MIN_FADE_MS = 50;

	/** Maximum fade duration (ms). */
	public const MAX_FADE_MS = 5000;

	/**
	 * Whether the plugin should supply announcement content.
	 */
	public static function is_enabled(): bool {
		return (bool) get_option( self::OPTION_ENABLED, true );
	}

	/**
	 * Persist enable flag.
	 *
	 * @param bool $enabled Whether USA should supply content.
	 */
	public static function set_enabled( bool $enabled ): void {
		update_option( self::OPTION_ENABLED, $enabled ? 1 : 0, false );
	}

	/**
	 * Default rotation settings (M2 behaviour).
	 *
	 * @return array{enabled:bool,interval_ms:int,fade_ms:int}
	 */
	public static function rotation_defaults(): array {
		return array(
			'enabled'     => self::DEFAULT_ROTATION_ENABLED,
			'interval_ms' => self::DEFAULT_INTERVAL_MS,
			'fade_ms'     => self::DEFAULT_FADE_MS,
		);
	}

	/**
	 * Current rotation settings with safe defaults.
	 *
	 * @return array{enabled:bool,interval_ms:int,fade_ms:int}
	 */
	public static function get_rotation(): array {
		$defaults = self::rotation_defaults();
		$stored   = get_option( self::OPTION_ROTATION, null );

		if ( ! is_array( $stored ) ) {
			return $defaults;
		}

		$normalized = self::normalize_rotation_array( $stored );
		return null !== $normalized ? $normalized : $defaults;
	}

	/**
	 * Whether multi-message rotation is enabled.
	 */
	public static function is_rotation_enabled(): bool {
		return (bool) self::get_rotation()['enabled'];
	}

	/**
	 * Rotation interval in milliseconds.
	 */
	public static function rotation_interval_ms(): int {
		return (int) self::get_rotation()['interval_ms'];
	}

	/**
	 * Fade duration in milliseconds.
	 */
	public static function rotation_fade_ms(): int {
		return (int) self::get_rotation()['fade_ms'];
	}

	/**
	 * Sanitize rotation settings from Settings API input.
	 *
	 * Invalid values are not persisted: the previously stored (or default) set is returned.
	 *
	 * @param mixed $input Raw option input.
	 * @return array{enabled:bool,interval_ms:int,fade_ms:int}
	 */
	public static function sanitize_rotation( $input ): array {
		$fallback = self::get_rotation();

		if ( ! is_array( $input ) ) {
			return $fallback;
		}

		$enabled = ! empty( $input['enabled'] );

		// Admin UI posts interval in whole seconds; optional interval_ms for tests/API.
		if ( array_key_exists( 'interval_seconds', $input ) ) {
			if ( ! self::is_whole_number( $input['interval_seconds'] ) ) {
				return $fallback;
			}
			$interval_ms = (int) $input['interval_seconds'] * 1000;
		} elseif ( array_key_exists( 'interval_ms', $input ) ) {
			if ( ! self::is_whole_number( $input['interval_ms'] ) ) {
				return $fallback;
			}
			$interval_ms = (int) $input['interval_ms'];
		} else {
			return $fallback;
		}

		if ( ! array_key_exists( 'fade_ms', $input ) || ! self::is_whole_number( $input['fade_ms'] ) ) {
			return $fallback;
		}
		$fade_ms = (int) $input['fade_ms'];

		$normalized = self::normalize_rotation_array(
			array(
				'enabled'     => $enabled,
				'interval_ms' => $interval_ms,
				'fade_ms'     => $fade_ms,
			)
		);

		return null !== $normalized ? $normalized : $fallback;
	}

	/**
	 * Validate and normalise a rotation settings array.
	 *
	 * @param array<string, mixed> $raw Raw values (enabled, interval_ms, fade_ms).
	 * @return array{enabled:bool,interval_ms:int,fade_ms:int}|null Null when invalid.
	 */
	public static function normalize_rotation_array( array $raw ): ?array {
		if ( ! isset( $raw['interval_ms'], $raw['fade_ms'] ) ) {
			return null;
		}
		if ( ! self::is_whole_number( $raw['interval_ms'] ) || ! self::is_whole_number( $raw['fade_ms'] ) ) {
			return null;
		}

		$interval = (int) $raw['interval_ms'];
		$fade     = (int) $raw['fade_ms'];
		$enabled  = ! empty( $raw['enabled'] );

		if ( $interval < self::MIN_INTERVAL_MS || $interval > self::MAX_INTERVAL_MS ) {
			return null;
		}
		if ( $fade < self::MIN_FADE_MS || $fade > self::MAX_FADE_MS ) {
			return null;
		}
		if ( $fade >= $interval ) {
			return null;
		}

		return array(
			'enabled'     => $enabled,
			'interval_ms' => $interval,
			'fade_ms'     => $fade,
		);
	}

	/**
	 * Whether a value is a whole number (int or integer numeric string).
	 *
	 * @param mixed $value Value.
	 */
	public static function is_whole_number( $value ): bool {
		if ( is_bool( $value ) ) {
			return false;
		}
		if ( is_int( $value ) ) {
			return true;
		}
		if ( is_float( $value ) ) {
			return is_finite( $value ) && floor( $value ) === $value;
		}
		if ( ! is_string( $value ) ) {
			return false;
		}
		$string = trim( $value );
		return 1 === preg_match( '/^-?\d+$/', $string );
	}

	/**
	 * Capability required to manage USA.
	 */
	public static function manage_cap(): string {
		/**
		 * Filters the capability required to manage Universal Site Announcements.
		 *
		 * @param string $cap Capability. Default manage_options.
		 */
		return (string) apply_filters( 'usa_manage_cap', 'manage_options' );
	}

	/**
	 * Admin URL for the announcements list.
	 */
	public static function announcements_admin_url(): string {
		return admin_url( 'edit.php?post_type=usa_announcement' );
	}

	/**
	 * Admin URL for the settings page.
	 */
	public static function settings_admin_url(): string {
		return admin_url( 'edit.php?post_type=usa_announcement&page=usa-settings' );
	}
}
