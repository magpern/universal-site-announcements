<?php
/**
 * Schema version and non-destructive migrations.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Lifecycle;

use USA\Announcement\PostType;
use USA\Announcement\ScheduleEvaluator;
use USA\Provider\WooCommerceFreeShippingProvider;
use USA\Settings;

/**
 * Tracks USA option schema and runs idempotent upgrades.
 */
final class Schema {

	/**
	 * Option storing the applied schema version.
	 */
	public const OPTION = 'usa_schema_version';

	/**
	 * Current schema version (M4 = 4).
	 */
	public const VERSION = 4;

	/**
	 * Persisted M4 migration state (status + cursor).
	 */
	public const M4_STATE_OPTION = 'usa_m4_migration';

	/**
	 * Posts processed per batch.
	 */
	public const M4_BATCH_SIZE = 50;

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
		add_action( 'admin_init', array( $this, 'maybe_migrate_admin' ), 5 );
	}

	/**
	 * Bootstrap: finish M3 if needed; start M4 state. Does not mark schema 4 complete.
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
				update_option( self::OPTION, 3, false );
			}

			if ( (int) get_option( self::OPTION, 0 ) < 4 ) {
				$this->ensure_m4_state_started();
			}
		} finally {
			self::$running = false;
		}
	}

	/**
	 * Authorized admin continuation for resumable M4 batches (no WP-Cron).
	 */
	public function maybe_migrate_admin(): void {
		if ( self::$running ) {
			return;
		}

		if ( (int) get_option( self::OPTION, 0 ) >= self::VERSION ) {
			return;
		}

		if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return;
		}

		if ( ! current_user_can( Settings::manage_cap() ) ) {
			return;
		}

		self::$running = true;
		try {
			$current = (int) get_option( self::OPTION, 0 );
			if ( $current < 3 ) {
				$this->migrate_to_m3();
				update_option( self::OPTION, 3, false );
			}

			if ( (int) get_option( self::OPTION, 0 ) < 4 ) {
				$this->run_m4_batch();
			}
		} finally {
			self::$running = false;
		}
	}

	/**
	 * Ensure M4 migration state exists as pending (schema version stays below 4).
	 */
	public function ensure_m4_state_started(): void {
		$state = $this->get_m4_state();
		if ( in_array( $state['status'], array( 'pending', 'in_progress', 'complete' ), true ) ) {
			return;
		}
		$this->save_m4_state(
			array(
				'status' => 'pending',
				'cursor' => 0,
			)
		);
	}

	/**
	 * Process one bounded M4 batch; bump schema to 4 only when complete.
	 *
	 * @return array{processed:int,complete:bool,cursor:int}
	 */
	public function run_m4_batch(): array {
		$this->ensure_m4_state_started();
		$state  = $this->get_m4_state();
		$cursor = (int) $state['cursor'];

		$ids = $this->next_announcement_ids_after( $cursor, self::M4_BATCH_SIZE );

		if ( array() === $ids ) {
			$this->complete_m4_migration( $cursor );
			return array(
				'processed' => 0,
				'complete'  => true,
				'cursor'    => $cursor,
			);
		}

		$last_id = $cursor;
		foreach ( $ids as $id ) {
			$this->migrate_announcement_to_m4( $id );
			$last_id = $id;
		}

		$more = $this->next_announcement_ids_after( $last_id, 1 );
		if ( array() === $more ) {
			$this->complete_m4_migration( $last_id );
			return array(
				'processed' => count( $ids ),
				'complete'  => true,
				'cursor'    => $last_id,
			);
		}

		$this->save_m4_state(
			array(
				'status' => 'in_progress',
				'cursor' => $last_id,
			)
		);

		return array(
			'processed' => count( $ids ),
			'complete'  => false,
			'cursor'    => $last_id,
		);
	}

	/**
	 * Announcement post IDs strictly greater than $cursor, ascending, limited.
	 *
	 * @param int $cursor Last processed ID.
	 * @param int $limit  Max IDs.
	 * @return list<int>
	 */
	public function next_announcement_ids_after( int $cursor, int $limit ): array {
		$posts = get_posts(
			array(
				'post_type'              => PostType::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$out = array();
		foreach ( $posts as $id ) {
			$id = (int) $id;
			if ( $id <= $cursor ) {
				continue;
			}
			$out[] = $id;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Migrate one announcement to explicit schedule mode (idempotent).
	 *
	 * @param int $post_id Post ID.
	 */
	public function migrate_announcement_to_m4( int $post_id ): void {
		$existing = (string) get_post_meta( $post_id, ScheduleEvaluator::META_MODE, true );
		if ( '' !== trim( $existing ) ) {
			return;
		}

		$starts = (string) get_post_meta( $post_id, ScheduleEvaluator::META_STARTS_AT, true );
		$ends   = (string) get_post_meta( $post_id, ScheduleEvaluator::META_ENDS_AT, true );
		$eval   = new ScheduleEvaluator();
		$mode   = $eval->infer_legacy_mode( $starts, $ends );
		update_post_meta( $post_id, ScheduleEvaluator::META_MODE, $mode );
	}

	/**
	 * M4 state for tests / diagnostics.
	 *
	 * @return array{status:string,cursor:int}
	 */
	public function get_m4_state(): array {
		$raw = get_option( self::M4_STATE_OPTION, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		$status = isset( $raw['status'] ) ? (string) $raw['status'] : '';
		$cursor = isset( $raw['cursor'] ) ? (int) $raw['cursor'] : 0;
		if ( ! in_array( $status, array( 'pending', 'in_progress', 'complete' ), true ) ) {
			$status = '';
		}
		return array(
			'status' => $status,
			'cursor' => max( 0, $cursor ),
		);
	}

	/**
	 * Persist M4 migration status and cursor.
	 *
	 * @param array{status:string,cursor:int} $state State.
	 */
	private function save_m4_state( array $state ): void {
		update_option(
			self::M4_STATE_OPTION,
			array(
				'status' => (string) $state['status'],
				'cursor' => (int) $state['cursor'],
			),
			false
		);
	}

	/**
	 * Mark M4 migration complete and set schema version to 4.
	 *
	 * @param int $cursor Final cursor.
	 */
	private function complete_m4_migration( int $cursor ): void {
		$this->save_m4_state(
			array(
				'status' => 'complete',
				'cursor' => $cursor,
			)
		);
		update_option( self::OPTION, 4, false );
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
