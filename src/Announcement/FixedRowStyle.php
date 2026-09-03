<?php
/**
 * Optional per-fixed-row colour styling (M6.1).
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Announcement;

/**
 * Pure resolver for the M6.1 fixed-row colour meta.
 *
 * Each colour is independently optional hex-only meta with a read-time
 * default of "absent" (inherit the host Store Notice styling), exactly
 * mirroring the DisplayMode meta pattern: no migration is required because
 * absence is always the safe, pre-M6.1 behaviour.
 */
final class FixedRowStyle {

	public const META_BG = '_usa_fixed_bg';

	public const META_FG = '_usa_fixed_fg';

	public const META_LINK = '_usa_fixed_link';

	public const META_BORDER = '_usa_fixed_border';

	/**
	 * Meta key by style field.
	 *
	 * @var array<string,string>
	 */
	private const META_KEYS = array(
		'bg'     => self::META_BG,
		'fg'     => self::META_FG,
		'link'   => self::META_LINK,
		'border' => self::META_BORDER,
	);

	/**
	 * CSS custom property name by style field.
	 *
	 * @var array<string,string>
	 */
	private const CSS_VARS = array(
		'bg'     => '--usa-fixed-bg',
		'fg'     => '--usa-fixed-fg',
		'link'   => '--usa-fixed-link',
		'border' => '--usa-fixed-border',
	);

	/**
	 * Validate and normalise one colour value.
	 *
	 * Delegates entirely to WordPress core's sanitize_hex_color(), which
	 * only ever returns a 3- or 4-6-digit hex string beginning with "#" or
	 * null. No other colour format (rgb/rgba/hsl/named/custom CSS) is
	 * accepted; the returned value is safe to place as the value of a CSS
	 * custom property.
	 *
	 * @param string|null $value Raw candidate value.
	 */
	public static function sanitize_hex( ?string $value ): ?string {
		if ( null === $value || '' === trim( $value ) ) {
			return null;
		}

		$clean = sanitize_hex_color( trim( $value ) );

		return '' !== (string) $clean ? $clean : null;
	}

	/**
	 * Normalise raw save-time input (one string per field) into validated hex or null.
	 *
	 * @param array{bg?:string,fg?:string,link?:string,border?:string} $raw Raw submitted values.
	 * @return array{bg:?string,fg:?string,link:?string,border:?string}
	 */
	public static function normalize( array $raw ): array {
		return array(
			'bg'     => self::sanitize_hex( $raw['bg'] ?? null ),
			'fg'     => self::sanitize_hex( $raw['fg'] ?? null ),
			'link'   => self::sanitize_hex( $raw['link'] ?? null ),
			'border' => self::sanitize_hex( $raw['border'] ?? null ),
		);
	}

	/**
	 * Resolve stored style meta for one announcement.
	 *
	 * Re-validates on read (defence in depth against direct DB edits or
	 * stale/tampered data); invalid values resolve to null exactly as at
	 * save time.
	 *
	 * @param int $post_id Post ID.
	 * @return array{bg:?string,fg:?string,link:?string,border:?string}
	 */
	public static function resolve( int $post_id ): array {
		$out = array();
		foreach ( self::META_KEYS as $field => $meta_key ) {
			$out[ $field ] = self::sanitize_hex( (string) get_post_meta( $post_id, $meta_key, true ) );
		}
		return $out;
	}

	/**
	 * Whether at least one style field is set.
	 *
	 * @param array{bg:?string,fg:?string,link:?string,border:?string} $style Resolved style.
	 */
	public static function has_any( array $style ): bool {
		foreach ( $style as $value ) {
			if ( null !== $value ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Persist a normalised style set as post meta (delete absent fields).
	 *
	 * @param int                                                      $post_id Post ID.
	 * @param array{bg:?string,fg:?string,link:?string,border:?string} $style   Normalised style.
	 */
	public static function persist( int $post_id, array $style ): void {
		foreach ( self::META_KEYS as $field => $meta_key ) {
			$value = $style[ $field ] ?? null;
			if ( null === $value ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, $value );
			}
		}
	}

	/**
	 * Build a `--usa-fixed-*:#hex;` custom-property string for the non-null fields.
	 *
	 * Every value has already passed sanitize_hex() (anchored hex-only
	 * regex), so no character is present that could break out of the
	 * custom-property value position.
	 *
	 * @param array{bg:?string,fg:?string,link:?string,border:?string} $style Resolved style.
	 */
	public static function to_css_vars( array $style ): string {
		$out = '';
		foreach ( self::CSS_VARS as $field => $var_name ) {
			$value = $style[ $field ] ?? null;
			if ( null === $value ) {
				continue;
			}
			$out .= $var_name . ':' . $value . ';';
		}
		return $out;
	}
}
