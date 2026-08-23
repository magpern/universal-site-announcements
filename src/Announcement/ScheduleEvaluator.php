<?php
/**
 * Schedule evaluation (interval UTC + weekly site-TZ).
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Announcement;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * UTC interval compare, weekly site-TZ evaluation, and site-TZ ↔ UTC helpers.
 */
final class ScheduleEvaluator {

	public const META_STARTS_AT = '_usa_starts_at';
	public const META_ENDS_AT   = '_usa_ends_at';

	public const META_MODE             = '_usa_schedule_mode';
	public const META_WEEKDAYS         = '_usa_weekdays';
	public const META_WEEKLY_STARTS_ON = '_usa_weekly_starts_on';
	public const META_WEEKLY_ENDS_ON   = '_usa_weekly_ends_on';

	public const MODE_ALWAYS   = 'always';
	public const MODE_INTERVAL = 'interval';
	public const MODE_WEEKLY   = 'weekly';

	/**
	 * Valid schedule modes.
	 *
	 * @var list<string>
	 */
	public const MODES = array( self::MODE_ALWAYS, self::MODE_INTERVAL, self::MODE_WEEKLY );

	/**
	 * Whether the legacy interval window includes $now_utc.
	 *
	 * Empty start = already started. Empty end = no end.
	 * Exclusive end: active iff now &lt; ends_at.
	 *
	 * @param string|null            $starts_at_utc MySQL UTC datetime or empty.
	 * @param string|null            $ends_at_utc   MySQL UTC datetime or empty.
	 * @param DateTimeImmutable|null $now_utc       Instant to evaluate (UTC). Defaults to now.
	 */
	public function is_active(
		?string $starts_at_utc,
		?string $ends_at_utc,
		?DateTimeImmutable $now_utc = null
	): bool {
		$now = $this->normalize_now_utc( $now_utc );

		$start = $this->parse_utc( $starts_at_utc );
		if ( null !== $start && $now < $start ) {
			return false;
		}

		$end = $this->parse_utc( $ends_at_utc );
		if ( null !== $end && ! ( $now < $end ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Evaluate schedule for one announcement (central dispatch).
	 *
	 * @param int                    $post_id Post ID.
	 * @param DateTimeImmutable|null $now_utc Instant (UTC).
	 * @return array{active:bool,diagnostic:?string,mode:?string}
	 */
	public function evaluate_post( int $post_id, ?DateTimeImmutable $now_utc = null ): array {
		$mode_raw = (string) get_post_meta( $post_id, self::META_MODE, true );
		$starts   = (string) get_post_meta( $post_id, self::META_STARTS_AT, true );
		$ends     = (string) get_post_meta( $post_id, self::META_ENDS_AT, true );

		$resolved = $this->resolve_mode( $mode_raw, $starts, $ends );
		if ( ! $resolved['ok'] ) {
			return array(
				'active'     => false,
				'diagnostic' => $resolved['diagnostic'],
				'mode'       => null,
			);
		}

		$mode = $resolved['mode'];
		$now  = $this->normalize_now_utc( $now_utc );

		if ( self::MODE_ALWAYS === $mode ) {
			return array(
				'active'     => true,
				'diagnostic' => null,
				'mode'       => $mode,
			);
		}

		if ( self::MODE_INTERVAL === $mode ) {
			$active = $this->is_active(
				'' !== $starts ? $starts : null,
				'' !== $ends ? $ends : null,
				$now
			);
			return array(
				'active'     => $active,
				'diagnostic' => null,
				'mode'       => $mode,
			);
		}

		// Weekly.
		$weekdays_raw = (string) get_post_meta( $post_id, self::META_WEEKDAYS, true );
		$weekdays     = $this->parse_weekdays_json( $weekdays_raw );
		if ( null === $weekdays ) {
			return array(
				'active'     => false,
				'diagnostic' => 'schedule_malformed_weekdays',
				'mode'       => $mode,
			);
		}
		if ( array() === $weekdays ) {
			return array(
				'active'     => false,
				'diagnostic' => 'schedule_empty_weekdays',
				'mode'       => $mode,
			);
		}

		$starts_on = (string) get_post_meta( $post_id, self::META_WEEKLY_STARTS_ON, true );
		$ends_on   = (string) get_post_meta( $post_id, self::META_WEEKLY_ENDS_ON, true );

		$window = $this->parse_weekly_window( $starts_on, $ends_on );
		if ( ! $window['ok'] ) {
			return array(
				'active'     => false,
				'diagnostic' => $window['diagnostic'],
				'mode'       => $mode,
			);
		}

		$tz_string = $this->site_timezone_string();
		try {
			$tz    = new DateTimeZone( $tz_string );
			$local = $now->setTimezone( $tz );
		} catch ( Exception $e ) {
			unset( $e );
			return array(
				'active'     => false,
				'diagnostic' => 'schedule_invalid_timezone',
				'mode'       => $mode,
			);
		}

		$local_date = $local->format( 'Y-m-d' );
		$iso_day    = (int) $local->format( 'N' );

		if ( ! $this->local_date_in_window( $local_date, $window['starts_on'], $window['ends_on'] ) ) {
			return array(
				'active'     => false,
				'diagnostic' => null,
				'mode'       => $mode,
			);
		}

		$active = in_array( $iso_day, $weekdays, true );
		return array(
			'active'     => $active,
			'diagnostic' => null,
			'mode'       => $mode,
		);
	}

	/**
	 * Resolve effective mode (explicit or legacy inference).
	 *
	 * @param string $mode_raw Stored mode (may be blank).
	 * @param string $starts   Interval start UTC.
	 * @param string $ends     Interval end UTC.
	 * @return array{ok:bool,mode:?string,diagnostic:?string}
	 */
	public function resolve_mode( string $mode_raw, string $starts, string $ends ): array {
		$mode_raw = trim( $mode_raw );
		if ( '' === $mode_raw ) {
			$inferred = $this->infer_legacy_mode( $starts, $ends );
			return array(
				'ok'         => true,
				'mode'       => $inferred,
				'diagnostic' => null,
			);
		}

		if ( ! in_array( $mode_raw, self::MODES, true ) ) {
			return array(
				'ok'         => false,
				'mode'       => null,
				'diagnostic' => 'schedule_invalid_mode',
			);
		}

		return array(
			'ok'         => true,
			'mode'       => $mode_raw,
			'diagnostic' => null,
		);
	}

	/**
	 * Legacy inference when mode meta is absent.
	 *
	 * @param string $starts Interval start.
	 * @param string $ends   Interval end.
	 */
	public function infer_legacy_mode( string $starts, string $ends ): string {
		if ( '' === trim( $starts ) && '' === trim( $ends ) ) {
			return self::MODE_ALWAYS;
		}
		return self::MODE_INTERVAL;
	}

	/**
	 * Parse weekday JSON to ordered unique ISO 1–7 integers, or null if malformed.
	 *
	 * Empty array is valid structurally (rejected separately for weekly mode).
	 *
	 * @param string $raw JSON string.
	 * @return list<int>|null
	 */
	public function parse_weekdays_json( string $raw ): ?array {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return array();
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return null;
		}

		$out = array();
		foreach ( $decoded as $item ) {
			if ( is_int( $item ) ) {
				$n = $item;
			} elseif ( is_string( $item ) && is_numeric( $item ) ) {
				$n = (int) $item;
			} else {
				return null;
			}
			if ( $n < 1 || $n > 7 ) {
				return null;
			}
			$out[] = $n;
		}

		$out = array_values( array_unique( $out ) );
		sort( $out, SORT_NUMERIC );
		return $out;
	}

	/**
	 * Normalize submitted weekday list (ints/strings) → ordered unique 1–7, or null if any invalid.
	 *
	 * @param mixed $raw Raw list.
	 * @return list<int>|null
	 */
	public function normalize_weekdays_input( $raw ): ?array {
		if ( null === $raw || '' === $raw ) {
			return array();
		}
		if ( ! is_array( $raw ) ) {
			return null;
		}
		$json = wp_json_encode( array_values( $raw ) );
		if ( ! is_string( $json ) ) {
			$json = json_encode( array_values( $raw ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		}
		if ( ! is_string( $json ) ) {
			return null;
		}
		return $this->parse_weekdays_json( $json );
	}

	/**
	 * Encode weekdays for storage.
	 *
	 * @param array<int, int> $weekdays Weekday integers.
	 */
	public function encode_weekdays( array $weekdays ): string {
		$weekdays = array_values( array_unique( array_map( 'intval', $weekdays ) ) );
		sort( $weekdays, SORT_NUMERIC );
		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $weekdays ) : json_encode( $weekdays ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		return is_string( $json ) ? $json : '[]';
	}

	/**
	 * Parse optional weekly window dates.
	 *
	 * @param string $starts_on Raw start date.
	 * @param string $ends_on   Raw end date.
	 * @return array{ok:bool,starts_on:?string,ends_on:?string,diagnostic:?string}
	 */
	public function parse_weekly_window( string $starts_on, string $ends_on ): array {
		$starts_on = trim( $starts_on );
		$ends_on   = trim( $ends_on );

		$start = null;
		$end   = null;

		if ( '' !== $starts_on ) {
			$start = $this->normalize_local_date( $starts_on );
			if ( null === $start ) {
				return array(
					'ok'         => false,
					'starts_on'  => null,
					'ends_on'    => null,
					'diagnostic' => 'schedule_malformed_weekly_window',
				);
			}
		}

		if ( '' !== $ends_on ) {
			$end = $this->normalize_local_date( $ends_on );
			if ( null === $end ) {
				return array(
					'ok'         => false,
					'starts_on'  => null,
					'ends_on'    => null,
					'diagnostic' => 'schedule_malformed_weekly_window',
				);
			}
		}

		if ( null !== $start && null !== $end && $start > $end ) {
			return array(
				'ok'         => false,
				'starts_on'  => null,
				'ends_on'    => null,
				'diagnostic' => 'schedule_reversed_weekly_window',
			);
		}

		return array(
			'ok'         => true,
			'starts_on'  => $start,
			'ends_on'    => $end,
			'diagnostic' => null,
		);
	}

	/**
	 * Normalize a local calendar date to Y-m-d, or null if invalid.
	 *
	 * @param string $value Raw date.
	 */
	public function normalize_local_date( string $value ): ?string {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}
		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return null;
		}
		$y  = (int) $m[1];
		$mo = (int) $m[2];
		$d  = (int) $m[3];
		if ( ! checkdate( $mo, $d, $y ) ) {
			return null;
		}
		return sprintf( '%04d-%02d-%02d', $y, $mo, $d );
	}

	/**
	 * Whether local Y-m-d falls in the optional window.
	 *
	 * @param string      $local_date Local Y-m-d.
	 * @param string|null $starts_on  Inclusive start or null.
	 * @param string|null $ends_on    Inclusive end day or null.
	 */
	public function local_date_in_window( string $local_date, ?string $starts_on, ?string $ends_on ): bool {
		if ( null !== $starts_on && $local_date < $starts_on ) {
			return false;
		}
		if ( null !== $ends_on && $local_date > $ends_on ) {
			return false;
		}
		return true;
	}

	/**
	 * Convert a site-timezone local datetime string to UTC MySQL datetime.
	 *
	 * Accepts `Y-m-d\TH:i` (datetime-local) or `Y-m-d H:i` / `Y-m-d H:i:s`.
	 *
	 * @param string $local         Site-local datetime.
	 * @param string $site_timezone Timezone identifier (e.g. Europe/Stockholm).
	 * @return string|null UTC `Y-m-d H:i:s`, or null if empty/invalid.
	 */
	public function site_local_to_utc( string $local, string $site_timezone ): ?string {
		$local = trim( $local );
		if ( '' === $local ) {
			return null;
		}

		$normalized = str_replace( 'T', ' ', $local );
		if ( 1 === preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized ) ) {
			$normalized .= ':00';
		}

		try {
			$tz  = new DateTimeZone( $site_timezone );
			$dt  = new DateTimeImmutable( $normalized, $tz );
			$utc = $dt->setTimezone( new DateTimeZone( 'UTC' ) );
			return $utc->format( 'Y-m-d H:i:s' );
		} catch ( Exception $e ) {
			unset( $e );
			return null;
		}
	}

	/**
	 * Convert a stored UTC MySQL datetime to site-local `Y-m-d\TH:i` for datetime-local inputs.
	 *
	 * @param string $utc_datetime  UTC `Y-m-d H:i:s`.
	 * @param string $site_timezone Timezone identifier.
	 * @return string|null Site-local datetime-local value, or null if empty/invalid.
	 */
	public function utc_to_site_local( string $utc_datetime, string $site_timezone ): ?string {
		$utc_datetime = trim( $utc_datetime );
		if ( '' === $utc_datetime ) {
			return null;
		}

		try {
			$dt  = new DateTimeImmutable( $utc_datetime, new DateTimeZone( 'UTC' ) );
			$loc = $dt->setTimezone( new DateTimeZone( $site_timezone ) );
			return $loc->format( 'Y-m-d\TH:i' );
		} catch ( Exception $e ) {
			unset( $e );
			return null;
		}
	}

	/**
	 * WordPress site timezone string.
	 */
	public function site_timezone_string(): string {
		if ( function_exists( 'wp_timezone_string' ) ) {
			$tz = wp_timezone_string();
			if ( is_string( $tz ) && '' !== $tz ) {
				return $tz;
			}
		}
		return 'UTC';
	}

	/**
	 * Short weekday labels for list summaries (ISO 1–7).
	 *
	 * @return array<int, string>
	 */
	public function weekday_labels(): array {
		return array(
			1 => __( 'Mon', 'universal-site-announcements' ),
			2 => __( 'Tue', 'universal-site-announcements' ),
			3 => __( 'Wed', 'universal-site-announcements' ),
			4 => __( 'Thu', 'universal-site-announcements' ),
			5 => __( 'Fri', 'universal-site-announcements' ),
			6 => __( 'Sat', 'universal-site-announcements' ),
			7 => __( 'Sun', 'universal-site-announcements' ),
		);
	}

	/**
	 * Full weekday labels for admin checkboxes.
	 *
	 * @return array<int, string>
	 */
	public function weekday_full_labels(): array {
		return array(
			1 => __( 'Monday', 'universal-site-announcements' ),
			2 => __( 'Tuesday', 'universal-site-announcements' ),
			3 => __( 'Wednesday', 'universal-site-announcements' ),
			4 => __( 'Thursday', 'universal-site-announcements' ),
			5 => __( 'Friday', 'universal-site-announcements' ),
			6 => __( 'Saturday', 'universal-site-announcements' ),
			7 => __( 'Sunday', 'universal-site-announcements' ),
		);
	}

	/**
	 * Normalize evaluation instant to UTC.
	 *
	 * @param DateTimeImmutable|null $now_utc Instant.
	 */
	private function normalize_now_utc( ?DateTimeImmutable $now_utc ): DateTimeImmutable {
		$now = $now_utc ?? new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		if ( 'UTC' !== $now->getTimezone()->getName() ) {
			$now = $now->setTimezone( new DateTimeZone( 'UTC' ) );
		}
		return $now;
	}

	/**
	 * Parse a UTC MySQL datetime string.
	 *
	 * @param string|null $value Raw meta value.
	 */
	private function parse_utc( ?string $value ): ?DateTimeImmutable {
		if ( null === $value ) {
			return null;
		}
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}

		try {
			return new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
		} catch ( Exception $e ) {
			unset( $e );
			return null;
		}
	}
}
