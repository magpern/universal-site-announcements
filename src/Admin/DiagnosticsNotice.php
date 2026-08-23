<?php
/**
 * Admin diagnostics for render failures.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Admin;

use USA\Settings;

/**
 * Throttled admin notice when content replacement fails.
 */
final class DiagnosticsNotice {

	public const TRANSIENT_KEY = 'usa_render_diagnostic';

	/**
	 * Register admin notice hook.
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Record a failure (throttled to once per hour).
	 *
	 * @param string $code Machine-readable failure code.
	 */
	public static function record_failure( string $code ): void {
		if ( false !== get_transient( self::TRANSIENT_KEY ) ) {
			return;
		}
		set_transient(
			self::TRANSIENT_KEY,
			array(
				'code' => $code,
				'at'   => time(),
			),
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Display notice for capable admins.
	 */
	public function render(): void {
		if ( ! current_user_can( Settings::manage_cap() ) ) {
			return;
		}

		$data = get_transient( self::TRANSIENT_KEY );
		if ( ! is_array( $data ) || empty( $data['code'] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__(
				'Universal Site Announcements could not safely replace the Store Notice markup. Upstream HTML was left unchanged. Check that the host notice still uses a paragraph with classes woocommerce-store-notice and demo_store.',
				'universal-site-announcements'
			)
		);
	}
}
