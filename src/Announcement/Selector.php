<?php
/**
 * Deterministic announcement selection.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Announcement;

/**
 * Selects the single announcement to render in M1.
 */
final class Selector {

	/**
	 * Selector repository.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Announcement repository.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * First eligible announcement content, or null.
	 */
	public function first_content(): ?string {
		$rows = $this->repository->get_eligible_manual();
		if ( array() === $rows ) {
			return null;
		}
		return $rows[0]['content'];
	}

	/**
	 * Pure selection helper for tests (priority ASC, ID ASC).
	 *
	 * @param list<array{id:int,priority:int,content:string}> $rows Rows.
	 * @return array{id:int,priority:int,content:string}|null
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
