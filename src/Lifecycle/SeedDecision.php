<?php
/**
 * Activation seed decision (pure, testable).
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Lifecycle;

use USA\Announcement\Sanitizer;

/**
 * Decides whether to seed and with what content.
 */
final class SeedDecision {

	/**
	 * Decide seed content.
	 *
	 * @param bool      $announcements_exist Whether any usa_announcement exists.
	 * @param mixed     $fallback_option     Raw WC notice option value.
	 * @param Sanitizer $sanitizer           Sanitiser.
	 * @return string|null Content to seed, or null to skip.
	 */
	public static function content_to_seed( bool $announcements_exist, $fallback_option, Sanitizer $sanitizer ): ?string {
		if ( $announcements_exist ) {
			return null;
		}
		if ( ! is_string( $fallback_option ) ) {
			return null;
		}
		$clean = $sanitizer->sanitize( $fallback_option );
		return '' === $clean ? null : $clean;
	}
}
