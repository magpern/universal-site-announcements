<?php
/**
 * Plugins screen action links.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Admin;

use USA\Settings;

/**
 * Adds Announcements and Settings links beside Deactivate.
 */
final class PluginActionLinks {

	/**
	 * Register filter.
	 */
	public function register(): void {
		add_filter( 'plugin_action_links_' . USA_PLUGIN_BASENAME, array( $this, 'links' ) );
	}

	/**
	 * Append capability-gated action links.
	 *
	 * @param array<string, string> $links Existing links.
	 * @return array<string, string>
	 */
	public function links( $links ): array {
		if ( ! is_array( $links ) ) {
			$links = array();
		}

		if ( ! current_user_can( Settings::manage_cap() ) ) {
			return $links;
		}

		$extra = array(
			'announcements' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( Settings::announcements_admin_url() ),
				esc_html__( 'Announcements', 'universal-site-announcements' )
			),
			'settings'      => sprintf(
				'<a href="%s">%s</a>',
				esc_url( Settings::settings_admin_url() ),
				esc_html__( 'Settings', 'universal-site-announcements' )
			),
		);

		return array_merge( $extra, $links );
	}
}
