<?php
/**
 * Announcement meta boxes.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Admin;

use USA\Announcement\PostType;
use USA\Announcement\Sanitizer;
use USA\Settings;

/**
 * Enabled / priority meta for announcements.
 */
final class AnnouncementMetaBoxes {

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
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_boxes' ) );
		add_action( 'save_post_' . PostType::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_filter( 'content_save_pre', array( $this, 'sanitize_content_on_save' ), 10, 1 );
	}

	/**
	 * Meta boxes.
	 */
	public function add_boxes(): void {
		add_meta_box(
			'usa_announcement_meta',
			__( 'Announcement settings', 'universal-site-announcements' ),
			array( $this, 'render_box' ),
			PostType::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Render meta box.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_box( $post ): void {
		wp_nonce_field( 'usa_announcement_meta', 'usa_announcement_meta_nonce' );
		$enabled  = get_post_meta( $post->ID, '_usa_enabled', true );
		$priority = get_post_meta( $post->ID, '_usa_priority', true );
		if ( '' === $priority ) {
			$priority = '10';
		}
		$is_enabled = ( '' === $enabled ) ? true : ( '1' === (string) $enabled || 'yes' === (string) $enabled );
		?>
		<p>
			<label>
				<input type="checkbox" name="usa_enabled" value="1" <?php checked( $is_enabled ); ?> />
				<?php echo esc_html__( 'Enabled', 'universal-site-announcements' ); ?>
			</label>
		</p>
		<p>
			<label for="usa_priority"><?php echo esc_html__( 'Priority (lower first)', 'universal-site-announcements' ); ?></label><br />
			<input type="number" id="usa_priority" name="usa_priority" value="<?php echo esc_attr( (string) $priority ); ?>" class="small-text" />
		</p>
		<p class="description">
			<?php echo esc_html__( 'Use inline links in the content editor. Scheduling is not available in this version.', 'universal-site-announcements' ); ?>
		</p>
		<?php
	}

	/**
	 * Save meta.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save( int $post_id, $post ): void {
		unset( $post );

		if ( ! isset( $_POST['usa_announcement_meta_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['usa_announcement_meta_nonce'] ) ), 'usa_announcement_meta' )
		) {
			return;
		}

		if ( ! current_user_can( Settings::manage_cap() ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$enabled = isset( $_POST['usa_enabled'] ) ? '1' : '0';
		update_post_meta( $post_id, '_usa_enabled', $enabled );

		$priority = isset( $_POST['usa_priority'] ) ? (int) $_POST['usa_priority'] : 10;
		update_post_meta( $post_id, '_usa_priority', (string) $priority );
		update_post_meta( $post_id, '_usa_source', 'manual' );
	}

	/**
	 * Sanitize post content for announcement CPT only.
	 *
	 * @param string $content Content.
	 */
	public function sanitize_content_on_save( $content ) {
		if ( ! is_string( $content ) ) {
			return $content;
		}

		$post_type = '';
		if ( isset( $_POST['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post_type = sanitize_key( wp_unslash( $_POST['post_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		if ( PostType::POST_TYPE !== $post_type ) {
			return $content;
		}

		return $this->sanitizer->sanitize( $content );
	}
}
