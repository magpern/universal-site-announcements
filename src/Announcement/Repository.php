<?php
/**
 * Announcement value object helpers and queries.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Announcement;

use USA\Admin\DiagnosticsNotice;
use USA\Integration\TemplateOverlay;
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
	 * Optional AIML template overlay.
	 *
	 * @var TemplateOverlay|null
	 */
	private ?TemplateOverlay $overlay;

	/**
	 * Constructor.
	 *
	 * @param Sanitizer                            $sanitizer Content sanitiser.
	 * @param ScheduleEvaluator                    $schedule  Schedule evaluator.
	 * @param TemplateEngine                       $engine    Template engine.
	 * @param WooCommerceFreeShippingProvider|null $provider  Free-shipping provider.
	 * @param TemplateOverlay|null                 $overlay   AIML overlay helper.
	 */
	public function __construct(
		Sanitizer $sanitizer,
		ScheduleEvaluator $schedule,
		TemplateEngine $engine,
		?WooCommerceFreeShippingProvider $provider = null,
		?TemplateOverlay $overlay = null
	) {
		$this->sanitizer = $sanitizer;
		$this->schedule  = $schedule;
		$this->engine    = $engine;
		$this->provider  = $provider;
		$this->overlay   = $overlay;
	}

	/**
	 * Ordered active announcement contents (priority ASC, ID ASC).
	 *
	 * Considers publish + enabled + schedule + derived template requirements.
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

		$rows             = array();
		$pending          = array();
		$fs_candidate_ids = array();

		foreach ( $posts as $post ) {
			$enabled = get_post_meta( $post->ID, '_usa_enabled', true );
			if ( '1' !== (string) $enabled && 'yes' !== (string) $enabled && true !== $enabled ) {
				continue;
			}

			$schedule = $this->schedule->evaluate_post( (int) $post->ID );
			if ( null !== $schedule['diagnostic'] ) {
				DiagnosticsNotice::record_failure( (string) $schedule['diagnostic'] );
			}
			if ( ! $schedule['active'] ) {
				continue;
			}

			$analysis = $this->engine->requirements()->analyse( (string) $post->post_content );
			if ( ! $analysis['ok'] ) {
				DiagnosticsNotice::record_failure( 'template_' . $analysis['reason'] );
				continue;
			}

			if ( $analysis['requires_free_shipping'] ) {
				$fs_candidate_ids[] = (int) $post->ID;
			}

			$pending[] = array(
				'post'     => $post,
				'analysis' => $analysis,
			);
		}

		$suppress_fs = count( $fs_candidate_ids ) > 1;
		if ( $suppress_fs ) {
			DiagnosticsNotice::record_failure( 'duplicate_free_shipping_announcements' );
		}

		foreach ( $pending as $item ) {
			$post     = $item['post'];
			$analysis = $item['analysis'];
			if ( $suppress_fs && $analysis['requires_free_shipping'] ) {
				continue;
			}

			$source   = $analysis['derived_source'];
			$priority = $this->read_priority( (int) $post->ID );
			$content  = $this->resolve_content( $post, $source, $analysis );
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
	 * @param \WP_Post                                                              $post     Post.
	 * @param string                                                                $source   Derived source.
	 * @param array{ok:bool,requires_free_shipping:bool,derived_source:string}|null $analysis Optional precomputed analysis.
	 */
	public function resolve_content( $post, string $source = '', ?array $analysis = null ): ?string {
		$source_template = (string) $post->post_content;

		if ( null === $analysis ) {
			$analysis = $this->engine->requirements()->analyse( $source_template );
		}
		if ( ! $analysis['ok'] ) {
			DiagnosticsNotice::record_failure( 'template_' . $analysis['reason'] );
			return null;
		}

		// Source requirements remain authoritative; overlay may only change editorial body.
		$source   = $analysis['derived_source'];
		$template = null !== $this->overlay
			? $this->overlay->apply( (int) $post->ID, $source_template )
			: $source_template;

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

		$rendered = $this->engine->render( $template, 'manual', array() );
		if ( null === $rendered ) {
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
