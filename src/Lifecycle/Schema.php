<?php
/**
 * Schema version and non-destructive migrations.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Lifecycle;

use USA\Announcement\PostType;
use USA\Provider\WooCommerceFreeShippingProvider;

/**
 * Tracks USA option schema and runs idempotent upgrades.
 */
final class Schema {

	/**
	 * Option storing the applied schema version.
	 */
	public const OPTION = 'usa_schema_version';

	/**
	 * Current schema version (M3 = 3).
	 */
	public const VERSION = 3;

	/**
	 * Default free-shipping body seeded only for empty provider posts (migration).
	 * Not used as a runtime sprintf authoring path.
	 */
	public const DEFAULT_FREE_SHIPPING_TEMPLATE = 'Free shipping on orders of {{free_shipping_threshold}} or more';

	/**
	 * Whether a migration run is in progress (avoids re-entrancy).
	 *
	 * @var bool
	 */
	private static bool $running = false;

	/**
	 * Register migration hooks.
	 */
	public function register(): void {
		add_action( 'plugins_loaded', array( $this, 'maybe_migrate' ), 20 );
		add_action( 'admin_init', array( $this, 'maybe_migrate' ), 5 );
	}

	/**
	 * Run pending migrations once when schema is behind.
	 */
	public function maybe_migrate(): void {
		if ( self::$running ) {
			return;
		}

		$current = (int) get_option( self::OPTION, 0 );
		if ( $current >= self::VERSION ) {
			return;
		}

		self::$running = true;
		try {
			if ( $current < 3 ) {
				$this->migrate_to_m3();
			}
			update_option( self::OPTION, self::VERSION, false );
		} finally {
			self::$running = false;
		}
	}

	/**
	 * M3: seed empty free-shipping announcement bodies with the default template.
	 * Never overwrites non-empty content (including whitespace-only after trim).
	 */
	private function migrate_to_m3(): void {
		$posts = get_posts(
			array(
				'post_type'      => PostType::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_key'       => '_usa_source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => WooCommerceFreeShippingProvider::SOURCE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		foreach ( $posts as $post ) {
			$content = isset( $post->post_content ) ? (string) $post->post_content : '';
			if ( '' !== trim( $content ) ) {
				continue;
			}

			wp_update_post(
				array(
					'ID'           => (int) $post->ID,
					'post_content' => self::DEFAULT_FREE_SHIPPING_TEMPLATE,
				)
			);
		}
	}

	/**
	 * Current stored schema version (for tests / diagnostics).
	 */
	public static function current(): int {
		return (int) get_option( self::OPTION, 0 );
	}
}
