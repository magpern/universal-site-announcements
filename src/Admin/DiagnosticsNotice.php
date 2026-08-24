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
 * Throttled admin notice when content replacement or template resolution fails.
 */
final class DiagnosticsNotice {

	public const TRANSIENT_KEY = 'usa_render_diagnostic';

	public const CODE_OVERLAY_TOKEN_SIGNATURE_MISMATCH = 'overlay_token_signature_mismatch';

	public const AJAX_ACTION = 'usa_dismiss_render_diagnostic';

	/**
	 * Register admin notice and dismiss handlers.
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_dismiss_script' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_dismiss' ) );
	}

	/**
	 * Record a failure (throttled to once per hour).
	 *
	 * @param string $code    Machine-readable failure code.
	 * @param int    $post_id Announcement post ID when the failure is announcement-scoped (0 = unscoped).
	 */
	public static function record_failure( string $code, int $post_id = 0 ): void {
		if ( false !== get_transient( self::TRANSIENT_KEY ) ) {
			return;
		}
		set_transient(
			self::TRANSIENT_KEY,
			array(
				'code'    => $code,
				'post_id' => max( 0, $post_id ),
				'at'      => time(),
			),
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Clear a stored diagnostic only when it matches this announcement and code.
	 *
	 * Unrelated successful renders must not clear another announcement's warning.
	 *
	 * @param string $code    Expected failure code.
	 * @param int    $post_id Announcement post ID that recovered.
	 */
	public static function clear_if_recovered( string $code, int $post_id ): void {
		if ( $post_id <= 0 || '' === $code ) {
			return;
		}

		$data = get_transient( self::TRANSIENT_KEY );
		if ( ! is_array( $data ) || empty( $data['code'] ) ) {
			return;
		}

		if ( (string) $data['code'] !== $code ) {
			return;
		}

		if ( (int) ( $data['post_id'] ?? 0 ) !== $post_id ) {
			return;
		}

		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Clear a stored diagnostic when it matches this announcement and a code prefix.
	 *
	 * @param string $prefix  Failure code prefix (e.g. template_).
	 * @param int    $post_id Announcement post ID that recovered.
	 */
	public static function clear_if_recovered_prefix( string $prefix, int $post_id ): void {
		if ( $post_id <= 0 || '' === $prefix ) {
			return;
		}

		$data = get_transient( self::TRANSIENT_KEY );
		if ( ! is_array( $data ) || empty( $data['code'] ) ) {
			return;
		}

		$code = (string) $data['code'];
		if ( 0 !== strpos( $code, $prefix ) ) {
			return;
		}

		if ( (int) ( $data['post_id'] ?? 0 ) !== $post_id ) {
			return;
		}

		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Dismiss / clear the stored diagnostic unconditionally.
	 */
	public static function clear(): void {
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * AJAX dismiss handler — deletes the diagnostic transient.
	 */
	public function ajax_dismiss(): void {
		if ( ! current_user_can( Settings::manage_cap() ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		check_ajax_referer( self::AJAX_ACTION, 'nonce' );
		self::clear();
		wp_send_json_success();
	}

	/**
	 * Enqueue dismiss script only when the notice is present.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function maybe_enqueue_dismiss_script( string $hook_suffix ): void {
		unset( $hook_suffix );

		if ( ! current_user_can( Settings::manage_cap() ) ) {
			return;
		}

		$data = get_transient( self::TRANSIENT_KEY );
		if ( ! is_array( $data ) || empty( $data['code'] ) ) {
			return;
		}

		$handle = 'usa-diagnostic-notice';
		wp_enqueue_script(
			$handle,
			plugins_url( 'assets/js/diagnostic-notice.js', USA_PLUGIN_FILE ),
			array(),
			defined( 'USA_VERSION' ) ? USA_VERSION : '0.5.1',
			true
		);
		wp_localize_script(
			$handle,
			'usaDiagnosticNotice',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
				'nonce'   => wp_create_nonce( self::AJAX_ACTION ),
			)
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

		$code    = (string) $data['code'];
		$message = $this->message_for_code( $code );

		printf(
			'<div class="notice notice-warning is-dismissible usa-render-diagnostic" data-usa-diagnostic="1"><p>%s</p></div>',
			esc_html( $message )
		);
	}

	/**
	 * Human-readable message for a failure code.
	 *
	 * @param string $code Failure code.
	 */
	private function message_for_code( string $code ): string {
		if ( self::CODE_OVERLAY_TOKEN_SIGNATURE_MISMATCH === $code ) {
			return __(
				'Universal Site Announcements fell back to the source announcement template because a translation changed protected merge tags. Edit the translation so tokens match the source exactly.',
				'universal-site-announcements'
			);
		}

		if ( 0 === strpos( $code, 'duplicate_free_shipping' ) ) {
			return __(
				'Universal Site Announcements suppressed free-shipping announcements because more than one enabled free-shipping-dependent message was active. Keep only one announcement that uses {{free_shipping_threshold}}.',
				'universal-site-announcements'
			);
		}

		if ( 0 === strpos( $code, 'schedule_' ) ) {
			return __(
				'Universal Site Announcements suppressed one or more announcements due to an invalid schedule configuration. Check the announcement schedule mode, weekdays, and weekly date window.',
				'universal-site-announcements'
			);
		}

		if ( 0 === strpos( $code, 'template_' ) || 0 === strpos( $code, 'fs_' ) ) {
			return __(
				'Universal Site Announcements suppressed one or more announcements due to an invalid template, unresolved merge tag, or free-shipping threshold failure. Check announcement diagnostics on the edit screen.',
				'universal-site-announcements'
			);
		}

		return __(
			'Universal Site Announcements could not safely replace the Store Notice markup. Upstream HTML was left unchanged. Check that the host notice still uses a paragraph with classes woocommerce-store-notice and demo_store.',
			'universal-site-announcements'
		);
	}
}
