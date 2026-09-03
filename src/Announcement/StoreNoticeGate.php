<?php
/**
 * Store Notice gate helper — ensures USA can render when needed.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Announcement;

use USA\Settings;

/**
 * Manages WooCommerce Store Notice gate for USA rendering.
 */
final class StoreNoticeGate {

	/**
	 * WooCommerce option key.
	 */
	private const WOO_OPTION_KEY = 'woocommerce_demo_store';

	/**
	 * Ensure the WooCommerce Store Notice gate is enabled when USA has eligible announcements.
	 *
	 * Call this after announcement meta is persisted and when the plugin is activated.
	 * Safe to call repeatedly; only sets the option if USA is enabled and eligible
	 * announcements exist, and the option is currently off.
	 */
	public static function ensure_gate_enabled_if_needed(): void {
		if ( ! Settings::is_enabled() ) {
			return;
		}

		if ( ! self::has_eligible_announcements() ) {
			return;
		}

		$current = get_option( self::WOO_OPTION_KEY, 'no' );
		if ( 'yes' === (string) $current ) {
			return;
		}

		update_option( self::WOO_OPTION_KEY, 'yes', false );
	}

	/**
	 * Whether any enabled and published announcements exist.
	 *
	 * Quick check used to enable the gate optimistically. The gate is enabled
	 * when an admin marks an announcement as enabled, even if its schedule or
	 * template is currently invalid. The diagnostic will only appear if the
	 * announcement is actually eligible to render (valid schedule + template).
	 *
	 * @return bool True if at least one enabled and published announcement exists.
	 */
	public static function has_eligible_announcements(): bool {
		$posts = get_posts(
			array(
				'post_type'      => PostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( empty( $posts ) ) {
			return false;
		}

		foreach ( $posts as $post_id ) {
			$enabled = get_post_meta( $post_id, '_usa_enabled', true );
			if ( '1' !== (string) $enabled && 'yes' !== (string) $enabled && true !== $enabled ) {
				continue;
			}

			return true;
		}

		return false;
	}

	/**
	 * Check and record diagnostic if the Store Notice gate is blocking USA.
	 *
	 * Call this from the diagnostics notice to warn admin when USA cannot render
	 * because the WooCommerce option is off.
	 */
	public static function maybe_record_gate_blocked_diagnostic(): void {
		if ( ! Settings::is_enabled() ) {
			return;
		}

		if ( ! self::has_eligible_announcements() ) {
			return;
		}

		$current = get_option( self::WOO_OPTION_KEY, 'no' );
		if ( 'yes' === (string) $current ) {
			return;
		}

		\USA\Admin\DiagnosticsNotice::record_failure( 'store_notice_gate_disabled' );
	}
}
