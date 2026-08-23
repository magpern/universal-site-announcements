<?php
/**
 * Announcement value object helpers and queries.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Announcement;

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
	 * Constructor.
	 *
	 * @param Sanitizer $sanitizer Content sanitiser.
	 */
	public function __construct( Sanitizer $sanitizer ) {
		$this->sanitizer = $sanitizer;
	}

	/**
	 * Eligible published manual announcements ordered for selection.
	 *
	 * @return list<array{id:int,priority:int,content:string}>
	 */
	public function get_eligible_manual(): array {
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

			$source = (string) get_post_meta( $post->ID, '_usa_source', true );
			if ( '' !== $source && 'manual' !== $source ) {
				continue;
			}

			$priority = (int) get_post_meta( $post->ID, '_usa_priority', true );
			if ( $priority < 0 ) {
				$priority = 10;
			}
			if ( '' === (string) get_post_meta( $post->ID, '_usa_priority', true ) ) {
				$priority = 10;
			}

			$content = $this->sanitizer->sanitize( (string) $post->post_content );
			if ( '' === $content ) {
				continue;
			}

			$rows[] = array(
				'id'       => (int) $post->ID,
				'priority' => $priority,
				'content'  => $content,
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
}
