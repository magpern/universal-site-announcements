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
use USA\Announcement\ScheduleEvaluator;
use USA\Provider\WooCommerceFreeShippingProvider;
use USA\Settings;

/**
 * Enabled / priority / schedule / source meta for announcements.
 */
final class AnnouncementMetaBoxes {

	public const ERROR_TRANSIENT = 'usa_announcement_admin_error';

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
	 * Free-shipping provider for diagnostics.
	 *
	 * @var WooCommerceFreeShippingProvider|null
	 */
	private ?WooCommerceFreeShippingProvider $provider;

	/**
	 * Constructor.
	 *
	 * @param Sanitizer                            $sanitizer Content sanitiser.
	 * @param ScheduleEvaluator                    $schedule  Schedule helper.
	 * @param WooCommerceFreeShippingProvider|null $provider  Provider for diagnostics.
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
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_boxes' ) );
		add_action( 'save_post_' . PostType::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_filter( 'content_save_pre', array( $this, 'sanitize_content_on_save' ), 10, 1 );
		add_action( 'admin_notices', array( $this, 'render_admin_errors' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_script' ) );
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
		add_meta_box(
			'usa_announcement_provider_diag',
			__( 'Free shipping diagnostics', 'universal-site-announcements' ),
			array( $this, 'render_diagnostics_box' ),
			PostType::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Render settings meta box.
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

		$source = (string) get_post_meta( $post->ID, '_usa_source', true );
		if ( '' === $source ) {
			$source = 'manual';
		}

		$tz           = $this->schedule->site_timezone_string();
		$starts_utc   = (string) get_post_meta( $post->ID, ScheduleEvaluator::META_STARTS_AT, true );
		$ends_utc     = (string) get_post_meta( $post->ID, ScheduleEvaluator::META_ENDS_AT, true );
		$starts_local = '' !== $starts_utc ? (string) $this->schedule->utc_to_site_local( $starts_utc, $tz ) : '';
		$ends_local   = '' !== $ends_utc ? (string) $this->schedule->utc_to_site_local( $ends_utc, $tz ) : '';
		?>
		<p>
			<label>
				<input type="checkbox" name="usa_enabled" value="1" <?php checked( $is_enabled ); ?> />
				<?php echo esc_html__( 'Enabled', 'universal-site-announcements' ); ?>
			</label>
		</p>
		<p>
			<label for="usa_priority"><?php echo esc_html__( 'Priority (lower first)', 'universal-site-announcements' ); ?></label><br />
			<input type="number" id="usa_priority" name="usa_priority" value="<?php echo esc_attr( (string) $priority ); ?>" class="small-text" required />
		</p>
		<p>
			<label for="usa_source"><?php echo esc_html__( 'Source', 'universal-site-announcements' ); ?></label><br />
			<select id="usa_source" name="usa_source">
				<option value="manual" <?php selected( $source, 'manual' ); ?>><?php echo esc_html__( 'Manual', 'universal-site-announcements' ); ?></option>
				<option value="<?php echo esc_attr( WooCommerceFreeShippingProvider::SOURCE ); ?>" <?php selected( $source, WooCommerceFreeShippingProvider::SOURCE ); ?>>
					<?php echo esc_html__( 'WooCommerce free shipping', 'universal-site-announcements' ); ?>
				</option>
			</select>
		</p>
		<p>
			<label for="usa_starts_at"><?php echo esc_html__( 'Starts at', 'universal-site-announcements' ); ?></label><br />
			<input type="datetime-local" id="usa_starts_at" name="usa_starts_at" value="<?php echo esc_attr( $starts_local ); ?>" />
		</p>
		<p>
			<label for="usa_ends_at"><?php echo esc_html__( 'Ends at (exclusive)', 'universal-site-announcements' ); ?></label><br />
			<input type="datetime-local" id="usa_ends_at" name="usa_ends_at" value="<?php echo esc_attr( $ends_local ); ?>" />
		</p>
		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: site timezone */
					__( 'Times use the site timezone (%s) and are stored as UTC. Leave empty for always-on. For a notice through 31 Dec, set Ends at to 1 Jan 00:00.', 'universal-site-announcements' ),
					$tz
				)
			);
			?>
		</p>
		<p class="description usa-provider-hint" <?php echo WooCommerceFreeShippingProvider::SOURCE === $source ? '' : 'hidden'; ?>>
			<?php echo esc_html__( 'Provider announcements generate content at render time. The content editor is unused.', 'universal-site-announcements' ); ?>
		</p>
		<?php
	}

	/**
	 * Provider diagnostic panel.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_diagnostics_box( $post ): void {
		$source = (string) get_post_meta( $post->ID, '_usa_source', true );
		if ( WooCommerceFreeShippingProvider::SOURCE !== $source ) {
			echo '<p class="description">' . esc_html__( 'Diagnostics appear when Source is WooCommerce free shipping.', 'universal-site-announcements' ) . '</p>';
			return;
		}

		if ( null === $this->provider ) {
			echo '<p>' . esc_html__( 'Provider unavailable.', 'universal-site-announcements' ) . '</p>';
			return;
		}

		$diag = $this->provider->diagnose();
		echo '<table class="widefat striped"><tbody>';
		$rows = array(
			__( 'Reference country', 'universal-site-announcements' ) => (string) ( $diag['reference_country'] ?? '' ),
			__( 'Zone', 'universal-site-announcements' )   => (string) ( $diag['zone_name'] ?? '' ),
			__( 'Method', 'universal-site-announcements' ) => (string) ( $diag['method_id'] ?? '' ),
			__( 'Base min amount', 'universal-site-announcements' ) => (string) ( $diag['base_min_amount'] ?? '' ),
			__( 'UMC API available', 'universal-site-announcements' ) => ! empty( $diag['umc_available'] ) ? __( 'Yes', 'universal-site-announcements' ) : __( 'No', 'universal-site-announcements' ),
			__( 'Eligibility OK', 'universal-site-announcements' ) => ! empty( $diag['eligibility_ok'] ) ? __( 'Yes', 'universal-site-announcements' ) : __( 'No', 'universal-site-announcements' ),
			__( 'Last suppression', 'universal-site-announcements' ) => (string) ( $diag['suppression_reason'] ?? '' ),
		);
		foreach ( $rows as $label => $value ) {
			printf(
				'<tr><th scope="row">%s</th><td>%s</td></tr>',
				esc_html( (string) $label ),
				esc_html( '' !== $value ? $value : '—' )
			);
		}
		echo '</tbody></table>';
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
		$source  = isset( $_POST['usa_source'] ) ? sanitize_key( wp_unslash( $_POST['usa_source'] ) ) : 'manual';
		if ( WooCommerceFreeShippingProvider::SOURCE !== $source ) {
			$source = 'manual';
		}

		if ( WooCommerceFreeShippingProvider::SOURCE === $source && '1' === $enabled ) {
			if ( $this->has_other_enabled_provider( $post_id ) ) {
				$this->queue_error(
					__( 'Only one enabled WooCommerce free shipping announcement is allowed.', 'universal-site-announcements' )
				);
				// Keep previous source/enabled rather than creating a second enabled provider.
				return;
			}
		}

		update_post_meta( $post_id, '_usa_enabled', $enabled );
		update_post_meta( $post_id, '_usa_source', $source );

		if ( isset( $_POST['usa_priority'] ) ) {
			$raw = sanitize_text_field( wp_unslash( $_POST['usa_priority'] ) );
			if ( ! is_numeric( $raw ) ) {
				$this->queue_error(
					__( 'Priority must be a number.', 'universal-site-announcements' )
				);
			} else {
				update_post_meta( $post_id, '_usa_priority', (string) (int) $raw );
			}
		} else {
			update_post_meta( $post_id, '_usa_priority', '10' );
		}

		$tz = $this->schedule->site_timezone_string();

		$starts_raw = isset( $_POST['usa_starts_at'] ) ? sanitize_text_field( wp_unslash( $_POST['usa_starts_at'] ) ) : '';
		$ends_raw   = isset( $_POST['usa_ends_at'] ) ? sanitize_text_field( wp_unslash( $_POST['usa_ends_at'] ) ) : '';

		$starts_utc = '' !== $starts_raw ? $this->schedule->site_local_to_utc( $starts_raw, $tz ) : null;
		$ends_utc   = '' !== $ends_raw ? $this->schedule->site_local_to_utc( $ends_raw, $tz ) : null;

		if ( '' !== $starts_raw && null === $starts_utc ) {
			$this->queue_error( __( 'Invalid Starts at value.', 'universal-site-announcements' ) );
		} elseif ( null === $starts_utc ) {
			delete_post_meta( $post_id, ScheduleEvaluator::META_STARTS_AT );
		} else {
			update_post_meta( $post_id, ScheduleEvaluator::META_STARTS_AT, $starts_utc );
		}

		if ( '' !== $ends_raw && null === $ends_utc ) {
			$this->queue_error( __( 'Invalid Ends at value.', 'universal-site-announcements' ) );
		} elseif ( null === $ends_utc ) {
			delete_post_meta( $post_id, ScheduleEvaluator::META_ENDS_AT );
		} else {
			update_post_meta( $post_id, ScheduleEvaluator::META_ENDS_AT, $ends_utc );
		}
	}

	/**
	 * Sanitize post content for announcement CPT only (manual source).
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

		$source = isset( $_POST['usa_source'] ) ? sanitize_key( wp_unslash( $_POST['usa_source'] ) ) : 'manual'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( WooCommerceFreeShippingProvider::SOURCE === $source ) {
			return $content;
		}

		return $this->sanitizer->sanitize( $content );
	}

	/**
	 * Admin error notices from save validation.
	 */
	public function render_admin_errors(): void {
		if ( ! current_user_can( Settings::manage_cap() ) ) {
			return;
		}

		$errors = get_transient( self::ERROR_TRANSIENT );
		if ( ! is_array( $errors ) || array() === $errors ) {
			return;
		}
		delete_transient( self::ERROR_TRANSIENT );

		foreach ( $errors as $message ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( (string) $message )
			);
		}
	}

	/**
	 * Hide the content editor when provider source is selected.
	 *
	 * @param string $hook Admin page hook.
	 */
	public function enqueue_editor_script( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || PostType::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$script = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
	var source = document.getElementById('usa_source');
	if (!source) { return; }
	var editor = document.getElementById('postdivrich') || document.getElementById('postdiv');
	var hint = document.querySelector('.usa-provider-hint');
	var diag = document.getElementById('usa_announcement_provider_diag');
	function sync() {
		var isProvider = source.value === 'woocommerce_free_shipping';
		if (editor) { editor.style.display = isProvider ? 'none' : ''; }
		if (hint) { hint.hidden = !isProvider; }
		if (diag) { diag.style.display = isProvider ? '' : 'none'; }
	}
	source.addEventListener('change', sync);
	sync();
});
JS;
		wp_register_script( 'usa-announcement-admin', false, array(), defined( 'USA_VERSION' ) ? USA_VERSION : '0.2.0', true );
		wp_enqueue_script( 'usa-announcement-admin' );
		wp_add_inline_script( 'usa-announcement-admin', $script );
	}

	/**
	 * Whether another enabled free-shipping provider announcement exists.
	 *
	 * @param int $post_id Current post ID.
	 */
	private function has_other_enabled_provider( int $post_id ): bool {
		$posts = get_posts(
			array(
				'post_type'      => PostType::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => 1,
				'post__not_in'   => array( $post_id ),
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'   => '_usa_source',
						'value' => WooCommerceFreeShippingProvider::SOURCE,
					),
					array(
						'key'   => '_usa_enabled',
						'value' => '1',
					),
				),
			)
		);

		return array() !== $posts;
	}

	/**
	 * Queue an admin error message.
	 *
	 * @param string $message Error text.
	 */
	private function queue_error( string $message ): void {
		$errors = get_transient( self::ERROR_TRANSIENT );
		if ( ! is_array( $errors ) ) {
			$errors = array();
		}
		$errors[] = $message;
		set_transient( self::ERROR_TRANSIENT, $errors, 300 );
	}
}
