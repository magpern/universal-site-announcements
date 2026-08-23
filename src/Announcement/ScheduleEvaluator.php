<?php
/**
 * Schedule window evaluation (UTC).
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Announcement;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * UTC compare and site-TZ ↔ UTC helpers for announcement schedules.
 */
final class ScheduleEvaluator {

	public const META_STARTS_AT = '_usa_starts_at';
	public const META_ENDS_AT   = '_usa_ends_at';

	/**
	 * Whether the schedule window includes $now_utc.
	 *
	 * Empty start = already started. Empty end = no end.
	 * Exclusive end: active iff now &lt; ends_at.
	 *
	 * @param string|null            $starts_at_utc MySQL UTC datetime or empty.
	 * @param string|null            $ends_at_utc   MySQL UTC datetime or empty.
	 * @param DateTimeImmutable|null $now_utc   Instant to evaluate (UTC). Defaults to now.
	 */
	public function is_active(
		?string $starts_at_utc,
		?string $ends_at_utc,
		?DateTimeImmutable $now_utc = null
	): bool {
		$now = $now_utc ?? new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		if ( 'UTC' !== $now->getTimezone()->getName() ) {
			$now = $now->setTimezone( new DateTimeZone( 'UTC' ) );
		}

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
	 * Convert a site-timezone local datetime string to UTC MySQL datetime.
	 *
	 * Accepts `Y-m-d\TH:i` (datetime-local) or `Y-m-d H:i` / `Y-m-d H:i:s`.
	 *
	 * @param string $local           Site-local datetime.
	 * @param string $site_timezone   Timezone identifier (e.g. Europe/Stockholm).
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
