<?php
/**
 * Announcement display mode (rotating or fixed slot).
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Announcement;

/**
 * Pure resolver for the M6 display-mode and fixed-placement meta.
 *
 * Both meta keys default at read time; absent or invalid values resolve to the
 * pre-M6 behaviour (rotating), so no migration is required.
 */
final class DisplayMode {

	public const META_MODE = '_usa_display_mode';

	public const META_PLACEMENT = '_usa_fixed_placement';

	public const MODE_ROTATING = 'rotating';

	public const MODE_FIXED = 'fixed';

	public const PLACEMENT_ABOVE = 'above';

	public const PLACEMENT_BELOW = 'below';

	/**
	 * Supported display modes.
	 *
	 * @var list<string>
	 */
	public const MODES = array( self::MODE_ROTATING, self::MODE_FIXED );

	/**
	 * Supported fixed placements.
	 *
	 * @var list<string>
	 */
	public const PLACEMENTS = array( self::PLACEMENT_ABOVE, self::PLACEMENT_BELOW );

	/**
	 * Normalise raw mode/placement input.
	 *
	 * Placement is null for rotating announcements.
	 *
	 * @param string $mode_raw      Raw mode value.
	 * @param string $placement_raw Raw placement value.
	 * @return array{mode:string,placement:?string}
	 */
	public static function normalize( string $mode_raw, string $placement_raw ): array {
		if ( ! in_array( $mode_raw, self::MODES, true ) || self::MODE_FIXED !== $mode_raw ) {
			return array(
				'mode'      => self::MODE_ROTATING,
				'placement' => null,
			);
		}

		$placement = in_array( $placement_raw, self::PLACEMENTS, true )
			? $placement_raw
			: self::PLACEMENT_ABOVE;

		return array(
			'mode'      => self::MODE_FIXED,
			'placement' => $placement,
		);
	}

	/**
	 * Resolve stored meta for one announcement.
	 *
	 * @param int $post_id Post ID.
	 * @return array{mode:string,placement:?string}
	 */
	public static function resolve( int $post_id ): array {
		return self::normalize(
			(string) get_post_meta( $post_id, self::META_MODE, true ),
			(string) get_post_meta( $post_id, self::META_PLACEMENT, true )
		);
	}

	/**
	 * Whether a resolved row is a fixed-slot announcement.
	 *
	 * @param array<string,mixed> $row Row with an optional mode key.
	 */
	public static function is_fixed_row( array $row ): bool {
		return self::MODE_FIXED === (string) ( $row['mode'] ?? self::MODE_ROTATING );
	}
}
