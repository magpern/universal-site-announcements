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
}
