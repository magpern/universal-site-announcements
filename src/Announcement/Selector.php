<?php
/**
 * Deterministic announcement selection.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Announcement;

use USA\Admin\DiagnosticsNotice;

/**
 * Selects active announcement content(s) for the store notice bar.
 */
final class Selector {

	/**
	 * Selector repository.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Request-local memo of the partitioned render set.
	 *
	 * Pure per-request optimisation: it only removes the duplicate evaluation
	 * between wp_enqueue_scripts and the woocommerce_demo_store filter within
	 * one PHP request. It is not a cache — nothing is persisted, no object
	 * cache is used, and there is no invalidation policy.
	 *
	 * @var array{rotating:list<array<string,mixed>>,fixed:array{above:?array<string,mixed>,below:?array<string,mixed>},collisions:list<array{placement:string,winner_id:int,candidate_ids:list<int>}>}|null
	 */
	private ?array $render_set = null;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Announcement repository.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Partitioned render set for the current request.
	 *
	 * @return array{rotating:list<array<string,mixed>>,fixed:array{above:?array<string,mixed>,below:?array<string,mixed>},collisions:list<array{placement:string,winner_id:int,candidate_ids:list<int>}>}
	 */
	public function render_set(): array {
		if ( null !== $this->render_set ) {
			return $this->render_set;
		}

		$set = self::partition( $this->repository->get_active() );
		self::sync_collision_diagnostics( $set['collisions'] );

		$this->render_set = $set;

		return $set;
	}

	/**
	 * Drop the request-local memo (tests, long-running processes).
	 */
	public function reset(): void {
		$this->render_set = null;
	}

	/**
	 * Ordered list of rotating announcement HTML contents.
	 *
	 * Fixed-slot announcements are excluded; they never take part in rotation.
	 *
	 * @return list<string>
	 */
	public function active_contents(): array {
		$contents = array();
		foreach ( $this->render_set()['rotating'] as $row ) {
			$contents[] = (string) $row['content'];
		}
		return $contents;
	}

	/**
	 * Winning fixed-slot content per placement (null when none is eligible).
	 *
	 * @return array{above:?string,below:?string}
	 */
	public function fixed_contents(): array {
		$fixed = $this->render_set()['fixed'];
		return array(
			DisplayMode::PLACEMENT_ABOVE => null === $fixed[ DisplayMode::PLACEMENT_ABOVE ]
				? null
				: (string) $fixed[ DisplayMode::PLACEMENT_ABOVE ]['content'],
			DisplayMode::PLACEMENT_BELOW => null === $fixed[ DisplayMode::PLACEMENT_BELOW ]
				? null
				: (string) $fixed[ DisplayMode::PLACEMENT_BELOW ]['content'],
		);
	}

	/**
	 * First eligible rotating announcement content, or null (compat).
	 */
	public function first_content(): ?string {
		$contents = $this->active_contents();
		if ( array() === $contents ) {
			return null;
		}
		return $contents[0];
	}

	/**
	 * Split active rows into rotating rows and one fixed winner per placement.
	 *
	 * Rows without a mode key are rotating (pre-M6 data). Fixed winners use the
	 * existing comparator (priority ASC, then post ID ASC); losing candidates are
	 * suppressed and never fall back into rotation.
	 *
	 * @param list<array<string,mixed>> $rows Active rows.
	 * @return array{rotating:list<array<string,mixed>>,fixed:array{above:?array<string,mixed>,below:?array<string,mixed>},collisions:list<array{placement:string,winner_id:int,candidate_ids:list<int>}>}
	 */
	public static function partition( array $rows ): array {
		$rotating   = array();
		$candidates = array(
			DisplayMode::PLACEMENT_ABOVE => array(),
			DisplayMode::PLACEMENT_BELOW => array(),
		);

		foreach ( $rows as $row ) {
			if ( ! DisplayMode::is_fixed_row( $row ) ) {
				$rotating[] = $row;
				continue;
			}

			$placement = (string) ( $row['placement'] ?? DisplayMode::PLACEMENT_ABOVE );
			if ( ! in_array( $placement, DisplayMode::PLACEMENTS, true ) ) {
				$placement = DisplayMode::PLACEMENT_ABOVE;
			}

			$candidates[ $placement ][] = $row;
		}

		$fixed      = array(
			DisplayMode::PLACEMENT_ABOVE => null,
			DisplayMode::PLACEMENT_BELOW => null,
		);
		$collisions = array();

		foreach ( DisplayMode::PLACEMENTS as $placement ) {
			$group = $candidates[ $placement ];
			if ( array() === $group ) {
				continue;
			}

			$winner              = self::pick_first( $group );
			$fixed[ $placement ] = $winner;

			if ( count( $group ) < 2 ) {
				continue;
			}

			$ids = array();
			foreach ( $group as $row ) {
				$ids[] = (int) $row['id'];
			}
			sort( $ids, SORT_NUMERIC );

			$collisions[] = array(
				'placement'     => $placement,
				'winner_id'     => (int) $winner['id'],
				'candidate_ids' => array_values( $ids ),
			);
		}

		return array(
			'rotating'   => array_values( $rotating ),
			'fixed'      => $fixed,
			'collisions' => $collisions,
		);
	}

	/**
	 * Diagnostic code identifying one placement's current competing candidate set.
	 *
	 * @param string     $placement     Placement.
	 * @param array<int> $candidate_ids Ascending candidate IDs.
	 */
	public static function collision_code( string $placement, array $candidate_ids ): string {
		return DiagnosticsNotice::CODE_FIXED_SLOT_COLLISION_PREFIX
			. $placement . ':' . implode( ',', $candidate_ids );
	}

	/**
	 * Record, replace, or clear the collision diagnostic per placement.
	 *
	 * @param list<array{placement:string,winner_id:int,candidate_ids:list<int>}> $collisions Collisions.
	 */
	private static function sync_collision_diagnostics( array $collisions ): void {
		foreach ( DisplayMode::PLACEMENTS as $placement ) {
			$current   = '';
			$winner_id = 0;
			foreach ( $collisions as $collision ) {
				if ( $collision['placement'] !== $placement ) {
					continue;
				}
				$current   = self::collision_code( $placement, $collision['candidate_ids'] );
				$winner_id = (int) $collision['winner_id'];
			}

			$prefix = DiagnosticsNotice::CODE_FIXED_SLOT_COLLISION_PREFIX . $placement . ':';
			DiagnosticsNotice::clear_if_stale_prefix( $prefix, $current, $winner_id );

			if ( '' !== $current ) {
				DiagnosticsNotice::record_failure( $current, $winner_id );
			}
		}
	}

	/**
	 * Pure selection helper for tests (priority ASC, ID ASC).
	 *
	 * @param list<array<string,mixed>> $rows Rows.
	 * @return array<string,mixed>|null
	 */
	public static function pick_first( array $rows ): ?array {
		if ( array() === $rows ) {
			return null;
		}
		usort(
			$rows,
			static function ( array $a, array $b ): int {
				if ( $a['priority'] === $b['priority'] ) {
					return $a['id'] <=> $b['id'];
				}
				return $a['priority'] <=> $b['priority'];
			}
		);
		return $rows[0];
	}
}
