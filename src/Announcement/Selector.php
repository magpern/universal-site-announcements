<?php
/**
 * Deterministic announcement selection.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Announcement;

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
	 * Constructor.
	 *
	 * @param Repository $repository Announcement repository.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Ordered list of active announcement HTML contents.
	 *
	 * @return list<string>
	 */
	public function active_contents(): array {
		$rows     = $this->repository->get_active();
		$contents = array();
		foreach ( $rows as $row ) {
			$contents[] = $row['content'];
		}
		return $contents;
	}

	/**
	 * First eligible announcement content, or null (compat).
	 */
	public function first_content(): ?string {
		$contents = $this->active_contents();
		if ( array() === $contents ) {
			return null;
		}
		return $contents[0];
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
