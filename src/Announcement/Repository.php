<?php
/**
 * Announcement value object helpers and queries.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Announcement;

use USA\Admin\DiagnosticsNotice;
use USA\Provider\WooCommerceFreeShippingProvider;
use USA\Template\TemplateEngine;

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
	 * Template engine.
	 *
	 * @var TemplateEngine
	 */
	private TemplateEngine $engine;

	/**
	 * Constructor.
	 *
	 * @param Sanitizer                            $sanitizer Content sanitiser.
	 * @param ScheduleEvaluator                    $schedule  Schedule evaluator.
	 * @param TemplateEngine                       $engine    Template engine.
	 * @param WooCommerceFreeShippingProvider|null $provider  Free-shipping provider.
	 */
	public function __construct(
		Sanitizer $sanitizer,
		ScheduleEvaluator $schedule,
		TemplateEngine $engine,
		?WooCommerceFreeShippingProvider $provider = null
	) {
		$this->sanitizer = $sanitizer;
		$this->schedule  = $schedule;
		$this->engine    = $engine;
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
			$content  = $this->resolve_content( $post, $source );
			if ( null === $content || '' === $content ) {
				continue;
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
	 * Resolve rendered HTML for one announcement, or null when suppressed.
	 *
	 * @param \WP_Post $post   Post.
	 * @param string   $source Source key.
	 */
	public function resolve_content( $post, string $source ): ?string {
		$template = (string) $post->post_content;

		if ( WooCommerceFreeShippingProvider::SOURCE === $source ) {
			if ( null === $this->provider ) {
				DiagnosticsNotice::record_failure( 'provider_unavailable' );
				return null;
			}

			$base = $this->provider->resolve_base_threshold();
			if ( null === $base ) {
				$reason = $this->provider->last_suppression_reason();
				if ( '' !== $reason ) {
					DiagnosticsNotice::record_failure( 'fs_' . $reason );
				}
				return null;
			}

			$inspect = $this->engine->inspect( $template, $source );
			if ( ! $inspect['ok'] ) {
				DiagnosticsNotice::record_failure( 'template_' . $inspect['reason'] );
				return null;
			}

			$threshold_html = $this->provider->resolve_threshold_html( $base );
			if ( null === $threshold_html ) {
				$reason = $this->provider->last_suppression_reason();
				DiagnosticsNotice::record_failure( 'fs_' . ( '' !== $reason ? $reason : 'threshold_html' ) );
				return null;
			}

			$rendered = $this->engine->render(
				$template,
				$source,
				array(
					'base_threshold' => $base,
					'threshold_html' => $threshold_html,
				)
			);
			if ( null === $rendered ) {
				DiagnosticsNotice::record_failure( 'template_' . $this->engine->last_reason() );
				return null;
			}

			return $rendered;
		}

		// Manual: static HTML and/or product tokens.
		$rendered = $this->engine->render( $template, 'manual', array() );
		if ( null === $rendered ) {
			// Templates with no tokens that fail rules shouldn't happen for empty-token
			// valid manuals — but malformed tokens suppress.
			if ( '' !== $this->engine->last_reason() ) {
				DiagnosticsNotice::record_failure( 'template_' . $this->engine->last_reason() );
			}
			return null;
		}

		return $rendered;
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
	 * Template engine accessor (admin preview).
	 */
	public function engine(): TemplateEngine {
		return $this->engine;
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
