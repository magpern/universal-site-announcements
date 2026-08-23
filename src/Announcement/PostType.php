<?php
/**
 * Announcement CPT registration.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Announcement;

use USA\Settings;

/**
 * Registers the private announcement post type.
 */
final class PostType {

	public const POST_TYPE = 'usa_announcement';

	/**
	 * Hooks registration.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Registers CPT.
	 */
	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Announcements', 'universal-site-announcements' ),
					'singular_name' => __( 'Announcement', 'universal-site-announcements' ),
					'add_new_item'  => __( 'Add Announcement', 'universal-site-announcements' ),
					'edit_item'     => __( 'Edit Announcement', 'universal-site-announcements' ),
					'menu_name'     => __( 'Announcements', 'universal-site-announcements' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'usa-settings',
				'show_in_rest'        => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'hierarchical'        => false,
				'supports'            => array( 'title', 'editor' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'exclude_from_search' => true,
			)
		);

		// Ensure menu parent exists before CPT submenu when Settings registers later.
		if ( ! current_user_can( Settings::manage_cap() ) ) {
			return;
		}
	}
}
