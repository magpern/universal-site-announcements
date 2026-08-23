<?php
/**
 * Activation lifecycle.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Lifecycle;

use USA\Announcement\PostType;
use USA\Announcement\Sanitizer;
use USA\Settings;

/**
 * Runs on plugin activation.
 */
final class Activator {

	/**
	 * Enable plugin and optionally seed a manual announcement.
	 */
	public function activate(): void {
		Settings::set_enabled( true );

		$post_type = new PostType();
		$post_type->register();
		flush_rewrite_rules( false );

		( new Schema() )->maybe_migrate();

		$existing = get_posts(
			array(
				'post_type'      => PostType::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		$sanitizer = new Sanitizer();
		$content   = SeedDecision::content_to_seed(
			array() !== $existing,
			get_option( 'woocommerce_demo_store_notice', '' ),
			$sanitizer
		);

		if ( null === $content ) {
			return;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => PostType::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => __( 'Default announcement', 'universal-site-announcements' ),
				'post_content' => $content,
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return;
		}

		update_post_meta( (int) $post_id, '_usa_enabled', '1' );
		update_post_meta( (int) $post_id, '_usa_priority', '10' );
		update_post_meta( (int) $post_id, '_usa_source', 'manual' );
	}
}
