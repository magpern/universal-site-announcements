<?php
/**
 * Announcement CPT registration.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Announcement;

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
					'name'               => __( 'Announcements', 'universal-site-announcements' ),
					'singular_name'      => __( 'Announcement', 'universal-site-announcements' ),
					'add_new'            => __( 'Add New', 'universal-site-announcements' ),
					'add_new_item'       => __( 'Add New Announcement', 'universal-site-announcements' ),
					'edit_item'          => __( 'Edit Announcement', 'universal-site-announcements' ),
					'new_item'           => __( 'New Announcement', 'universal-site-announcements' ),
					'view_item'          => __( 'View Announcement', 'universal-site-announcements' ),
					'search_items'       => __( 'Search Announcements', 'universal-site-announcements' ),
					'not_found'          => __( 'No announcements found.', 'universal-site-announcements' ),
					'not_found_in_trash' => __( 'No announcements found in Trash.', 'universal-site-announcements' ),
					'all_items'          => __( 'All Announcements', 'universal-site-announcements' ),
					'menu_name'          => __( 'Announcements', 'universal-site-announcements' ),
					'name_admin_bar'     => __( 'Announcement', 'universal-site-announcements' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_icon'           => 'dashicons-megaphone',
				'menu_position'       => 58,
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
	}
}
