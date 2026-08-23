<?php
/**
 * Announcement value object helpers and queries.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Announcement;

use USA\Provider\WooCommerceFreeShippingProvider;

/**
 * Loads announcement posts.
 */
final class Repository {

	/**
	 * Content sanitiser.
	 *
	 * @var Sanitizer
	 */
	private Sanitizer $sanitizer;

	/**
	 * Schedule evaluator.
	 *
	 * @var ScheduleEvaluator
	 */
	private ScheduleEvaluator $schedule;

	/**
	 * Free-shipping provider (optional when WooCommerce unavailable).
	 *
	 * @var WooCommerceFreeShippingProvider|null
	 */
	private ?WooCommerceFreeShippingProvider $provider;

	/**
	 * Constructor.
	 *
	 * @param Sanitizer                            $sanitizer Content sanitiser.
	 * @param ScheduleEvaluator                    $schedule  Schedule evaluator.
	 * @param WooCommerceFreeShippingProvider|null $provider  Free-shipping provider.
	 */
	public function __construct(
		Sanitizer $sanitizer,
		ScheduleEvaluator $schedule,
		?WooCommerceFreeShippingProvider $provider = null
	) {
		$this->sanitizer = $sanitizer;
		$this->schedule  = $schedule;
		$this->provider  = $provider;
	}

	/**
	 * Ordered active announcement contents (priority ASC, ID ASC).
	 *
	 * Considers publish + enabled + schedule + source resolution.
	 * Empty schedule = always on. Provider rows omit themselves when suppressed.
	 *
	 * @return list<array{id:int,priority:int,content:string,source:string}>
	 */
	public function get_active(): array {
		$posts = get_posts(
			array(
				'post_type'      => PostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		$rows = array();
		foreach ( $posts as $post ) {
			$enabled = get_post_meta( $post->ID, '_usa_enabled', true );
			if ( '1' !== (string) $enabled && 'yes' !== (string) $enabled && true !== $enabled ) {
				continue;
			}

			$starts = (string) get_post_meta( $post->ID, ScheduleEvaluator::META_STARTS_AT, true );
			$ends   = (string) get_post_meta( $post->ID, ScheduleEvaluator::META_ENDS_AT, true );
			if ( ! $this->schedule->is_active(
				'' !== $starts ? $starts : null,
				'' !== $ends ? $ends : null
			) ) {
				continue;
			}

			$source = (string) get_post_meta( $post->ID, '_usa_source', true );
			if ( '' === $source ) {
				$source = 'manual';
			}

			$priority = $this->read_priority( (int) $post->ID );

			if ( WooCommerceFreeShippingProvider::SOURCE === $source ) {
				if ( null === $this->provider ) {
					continue;
				}
				$message = $this->provider->resolve_message();
				if ( null === $message || '' === $message ) {
					continue;
				}
				$content = $this->sanitizer->sanitize_output( $message );
				if ( '' === $content ) {
					continue;
				}
			} else {
				$content = $this->sanitizer->sanitize( (string) $post->post_content );
				if ( '' === $content ) {
					continue;
				}
			}

			$rows[] = array(
				'id'       => (int) $post->ID,
				'priority' => $priority,
				'content'  => $content,
				'source'   => $source,
			);
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

		return $rows;
	}

	/**
	 * Eligible published manual announcements ordered for selection (M1 compat).
	 *
	 * @return list<array{id:int,priority:int,content:string}>
	 */
	public function get_eligible_manual(): array {
		$active = $this->get_active();
		$manual = array();
		foreach ( $active as $row ) {
			if ( 'manual' !== $row['source'] ) {
				continue;
			}
			$manual[] = array(
				'id'       => $row['id'],
				'priority' => $row['priority'],
				'content'  => $row['content'],
			);
		}
		return $manual;
	}

	/**
	 * Read priority meta with default 10.
	 *
	 * @param int $post_id Post ID.
	 */
	private function read_priority( int $post_id ): int {
		$raw = get_post_meta( $post_id, '_usa_priority', true );
		if ( '' === (string) $raw ) {
			return 10;
		}
		$priority = (int) $raw;
		return $priority < 0 ? 10 : $priority;
	}
}
