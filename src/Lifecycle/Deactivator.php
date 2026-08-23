<?php
/**
 * Deactivation lifecycle.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Lifecycle;

/**
 * Runs on plugin deactivation.
 *
 * Does not delete CPT data or mutate WooCommerce options.
 * Unloading the plugin restores the upstream Store Notice path.
 */
final class Deactivator {

	/**
	 * Flush rewrite rules only.
	 */
	public function deactivate(): void {
		flush_rewrite_rules( false );
	}
}
