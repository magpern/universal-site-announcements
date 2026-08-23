<?php
/**
 * Announcement meta boxes and editor UX.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Admin;

use USA\Announcement\PostType;
use USA\Announcement\Sanitizer;
use USA\Announcement\ScheduleEvaluator;
use USA\Lifecycle\Schema;
use USA\Provider\WooCommerceFreeShippingProvider;
use USA\Settings;
use USA\Template\TemplateEngine;

/**
 * Enabled / priority / schedule / source / template meta for announcements.
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
	 * Template engine.
	 *
	 * @var TemplateEngine
	 */
	private TemplateEngine $engine;

	/**
	 * Constructor.
	 *
	 * @param Sanitizer                            $sanitizer Content sanitiser.
	 * @param ScheduleEvaluator                    $schedule  Schedule helper.
	 * @param WooCommerceFreeShippingProvider|null $provider  Provider for diagnostics.
	 * @param TemplateEngine                       $engine    Template engine.
	 */
	public function __construct(
		Sanitizer $sanitizer,
		ScheduleEvaluator $schedule,
		?WooCommerceFreeShippingProvider $provider,
		TemplateEngine $engine
	) {
		$this->sanitizer = $sanitizer;
		$this->schedule  = $schedule;
		$this->provider  = $provider;
		$this->engine    = $engine;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_boxes' ) );
		add_action( 'edit_form_after_title', array( $this, 'render_source_selector' ) );
		add_action( 'save_post_' . PostType::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_filter( 'content_save_pre', array( $this, 'sanitize_content_on_save' ), 10, 1 );
		add_action( 'admin_notices', array( $this, 'render_admin_errors' ) );
		add_action( 'admin_notices', array( $this, 'render_invalid_template_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'wp_ajax_usa_search_products', array( $this, 'ajax_search_products' ) );
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
			'usa_announcement_tokens',
			__( 'Dynamic values', 'universal-site-announcements' ),
			array( $this, 'render_tokens_box' ),
			PostType::POST_TYPE,
			'normal',
			'high'
		);
		add_meta_box(
			'usa_announcement_preview',
			__( 'Template preview', 'universal-site-announcements' ),
			array( $this, 'render_preview_box' ),
			PostType::POST_TYPE,
			'normal',
			'default'
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
	 * Prominent source selector above the content editor.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_source_selector( $post ): void {
		if ( ! $post instanceof \WP_Post || PostType::POST_TYPE !== $post->post_type ) {
			return;
		}

		$source = (string) get_post_meta( $post->ID, '_usa_source', true );
		if ( '' === $source ) {
			$source = 'manual';
		}

		wp_nonce_field( 'usa_announcement_meta', 'usa_announcement_meta_nonce' );
		?>
		<div class="usa-source-selector notice notice-info inline" style="padding:12px 16px;margin:12px 0;">
			<p style="margin:0 0 8px;font-weight:600;">
				<?php echo esc_html__( 'Announcement source', 'universal-site-announcements' ); ?>
			</p>
			<p style="margin:0 0 8px;">
				<label style="margin-right:16px;">
					<input type="radio" name="usa_source" value="manual" <?php checked( $source, 'manual' ); ?> />
					<?php echo esc_html__( 'Manual message', 'universal-site-announcements' ); ?>
				</label>
				<label>
					<input type="radio" name="usa_source" value="<?php echo esc_attr( WooCommerceFreeShippingProvider::SOURCE ); ?>" <?php checked( $source, WooCommerceFreeShippingProvider::SOURCE ); ?> />
					<?php echo esc_html__( 'WooCommerce free shipping', 'universal-site-announcements' ); ?>
				</label>
			</p>
			<p class="description" style="margin:0;">
				<?php echo esc_html__( 'Choose the source before editing the message. Dynamic values available in the editor depend on this selection.', 'universal-site-announcements' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render settings meta box (enabled / priority / schedule).
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_box( $post ): void {
		$enabled  = get_post_meta( $post->ID, '_usa_enabled', true );
		$priority = get_post_meta( $post->ID, '_usa_priority', true );
		if ( '' === $priority ) {
			$priority = '10';
		}
		$is_enabled = ( '' === $enabled ) ? true : ( '1' === (string) $enabled || 'yes' === (string) $enabled );

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
		<?php
	}

	/**
	 * Insert dynamic value controls.
	 *
	 * @param \WP_Post $post Post.
	 */
	/**
	 * Insert dynamic value controls.
	 *
	 * @param \WP_Post $post Post (unused; metabox signature).
	 */
	public function render_tokens_box( $post ): void {
		unset( $post );
		?>
		<p class="description">
			<?php echo esc_html__( 'Insert a dynamic value at the cursor in the content editor. Only values allowed for the selected source are shown.', 'universal-site-announcements' ); ?>
		</p>
		<p class="usa-token-buttons">
			<button type="button" class="button usa-insert-token" data-token="{{free_shipping_threshold}}" data-requires-source="woocommerce_free_shipping">
				<?php echo esc_html__( 'Free shipping threshold', 'universal-site-announcements' ); ?>
			</button>
			<button type="button" class="button usa-insert-product" id="usa-insert-product">
				<?php echo esc_html__( 'Product link…', 'universal-site-announcements' ); ?>
			</button>
		</p>
		<div id="usa-product-picker" hidden style="margin-top:8px;">
			<label for="usa-product-search"><?php echo esc_html__( 'Search products', 'universal-site-announcements' ); ?></label>
			<input type="search" id="usa-product-search" class="regular-text" autocomplete="off" />
			<ul id="usa-product-results" style="margin:8px 0;max-height:180px;overflow:auto;"></ul>
		</div>
		<p class="description">
			<code>{{free_shipping_threshold}}</code>
			—
			<?php echo esc_html__( 'Required exactly once for free-shipping source.', 'universal-site-announcements' ); ?>
			<br />
			<code>{{product:123}}</code>
			—
			<?php echo esc_html__( 'Public product title linked to its current URL.', 'universal-site-announcements' ); ?>
		</p>
		<?php
	}

	/**
	 * Preview / status panel.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_preview_box( $post ): void {
		$source = (string) get_post_meta( $post->ID, '_usa_source', true );
		if ( '' === $source ) {
			$source = 'manual';
		}
		$template = (string) $post->post_content;

		$inspect = $this->engine->inspect( $template, $source );

		echo '<p class="description">' . esc_html__(
			'Promotional wording (for example “Buy 2… get one free”) is editorial. This plugin resolves product names and links only; it does not verify promotion configuration.',
			'universal-site-announcements'
		) . '</p>';

		echo '<h4>' . esc_html__( 'Detected tokens', 'universal-site-announcements' ) . '</h4>';
		if ( array() === $inspect['tokens'] ) {
			echo '<p>' . esc_html__( 'None', 'universal-site-announcements' ) . '</p>';
		} else {
			echo '<ul>';
			foreach ( $inspect['tokens'] as $token ) {
				printf( '<li><code>%s</code></li>', esc_html( $token['raw'] ) );
			}
			echo '</ul>';
		}

		if ( ! $inspect['ok'] ) {
			printf(
				'<p><strong>%s</strong> %s</p>',
				esc_html__( 'Invalid template:', 'universal-site-announcements' ),
				esc_html( $this->humanize_reason( $inspect['reason'] ) )
			);
			return;
		}

		$context = array();
		if ( WooCommerceFreeShippingProvider::SOURCE === $source && null !== $this->provider ) {
			$base = $this->provider->resolve_base_threshold();
			if ( null === $base ) {
				printf(
					'<p><strong>%s</strong> %s</p>',
					esc_html__( 'Suppressed:', 'universal-site-announcements' ),
					esc_html( $this->provider->last_suppression_reason() )
				);
				return;
			}
			$html = $this->provider->resolve_threshold_html( $base );
			if ( null === $html ) {
				printf(
					'<p><strong>%s</strong> %s</p>',
					esc_html__( 'Suppressed:', 'universal-site-announcements' ),
					esc_html( $this->provider->last_suppression_reason() )
				);
				return;
			}
			$context = array(
				'base_threshold' => $base,
				'threshold_html' => $html,
			);
		}

		$rendered = $this->engine->render( $template, $source, $context );
		if ( null === $rendered ) {
			printf(
				'<p><strong>%s</strong> %s</p>',
				esc_html__( 'Suppressed:', 'universal-site-announcements' ),
				esc_html( $this->humanize_reason( $this->engine->last_reason() ) )
			);
			return;
		}

		echo '<h4>' . esc_html__( 'Rendered preview', 'universal-site-announcements' ) . '</h4>';
		echo '<div class="usa-template-preview" style="padding:8px;border:1px solid #c3c4c7;background:#fff;">';
		echo wp_kses( $rendered, $this->sanitizer->output_allowed_html() );
		echo '</div>';
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

		$diag     = $this->provider->diagnose();
		$inspect  = $this->engine->inspect( (string) $post->post_content, $source );
		$template = '' !== trim( (string) $post->post_content )
			? (string) $post->post_content
			: Schema::DEFAULT_FREE_SHIPPING_TEMPLATE;

		echo '<table class="widefat striped"><tbody>';
		$rows = array(
			__( 'Reference country', 'universal-site-announcements' ) => (string) ( $diag['reference_country'] ?? '' ),
			__( 'Zone', 'universal-site-announcements' )   => (string) ( $diag['zone_name'] ?? '' ),
			__( 'Method', 'universal-site-announcements' ) => (string) ( $diag['method_id'] ?? '' ),
			__( 'Base min amount', 'universal-site-announcements' ) => (string) ( $diag['base_min_amount'] ?? '' ),
			__( 'UMC active', 'universal-site-announcements' ) => ! empty( $diag['umc_active'] ) ? __( 'Yes', 'universal-site-announcements' ) : __( 'No', 'universal-site-announcements' ),
			__( 'UMC API available', 'universal-site-announcements' ) => ! empty( $diag['umc_api_available'] ) ? __( 'Yes', 'universal-site-announcements' ) : __( 'No', 'universal-site-announcements' ),
			__( 'Eligibility OK', 'universal-site-announcements' ) => ! empty( $diag['eligibility_ok'] ) ? __( 'Yes', 'universal-site-announcements' ) : __( 'No', 'universal-site-announcements' ),
			__( 'Template valid', 'universal-site-announcements' ) => $inspect['ok'] ? __( 'Yes', 'universal-site-announcements' ) : __( 'No', 'universal-site-announcements' ),
			__( 'Template issue', 'universal-site-announcements' ) => $inspect['ok'] ? '' : $this->humanize_reason( $inspect['reason'] ),
			__( 'Last suppression', 'universal-site-announcements' ) => (string) ( $diag['suppression_reason'] ?? '' ),
			__( 'Default seed template', 'universal-site-announcements' ) => ( Schema::DEFAULT_FREE_SHIPPING_TEMPLATE === $template ) ? __( 'Matches migration default', 'universal-site-announcements' ) : __( 'Custom', 'universal-site-announcements' ),
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
				return;
			}
		}

		$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( is_string( $content ) ) {
			$content = $this->sanitizer->sanitize( $content );
			$inspect = $this->engine->inspect( $content, $source );
			if ( ! $inspect['ok'] ) {
				$this->queue_error(
					sprintf(
						/* translators: %s: validation reason */
						__( 'Invalid announcement template: %s', 'universal-site-announcements' ),
						$this->humanize_reason( $inspect['reason'] )
					)
				);
				// Still persist source/settings; front-end will suppress until fixed.
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
	 * Sanitize post content for announcement CPT (both sources keep merge tags).
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
	 * Notice when editing an invalid legacy free-shipping template.
	 */
	public function render_invalid_template_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || PostType::POST_TYPE !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}
		if ( ! current_user_can( Settings::manage_cap() ) ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $post_id < 1 ) {
			return;
		}

		$source = (string) get_post_meta( $post_id, '_usa_source', true );
		if ( WooCommerceFreeShippingProvider::SOURCE !== $source ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$content = (string) $post->post_content;
		if ( '' === trim( $content ) ) {
			return;
		}

		$inspect = $this->engine->inspect( $content, $source );
		if ( $inspect['ok'] ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: reason */
					__( 'This free-shipping announcement has an invalid template and will be suppressed on the front end until corrected: %s', 'universal-site-announcements' ),
					$this->humanize_reason( $inspect['reason'] )
				)
			)
		);
	}

	/**
	 * Enqueue editor script + styles.
	 *
	 * @param string $hook Admin page hook.
	 */
	public function enqueue_editor_assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || PostType::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$version = defined( 'USA_VERSION' ) ? USA_VERSION : '0.3.0';
		$js      = USA_PLUGIN_DIR . 'assets/js/announcement-editor.js';
		$url     = plugins_url( 'assets/js/announcement-editor.js', USA_PLUGIN_FILE );

		wp_enqueue_script(
			'usa-announcement-editor',
			$url,
			array(),
			is_readable( $js ) ? (string) filemtime( $js ) : $version,
			true
		);

		wp_localize_script(
			'usa-announcement-editor',
			'usaAnnouncementEditor',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'usa_search_products' ),
				'i18n'    => array(
					'noResults' => __( 'No products found.', 'universal-site-announcements' ),
					'searching' => __( 'Searching…', 'universal-site-announcements' ),
				),
			)
		);
	}

	/**
	 * AJAX product search for the insert picker.
	 */
	public function ajax_search_products(): void {
		if ( ! current_user_can( Settings::manage_cap() ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		check_ajax_referer( 'usa_search_products', 'nonce' );

		$term = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		if ( '' === $term || ! function_exists( 'wc_get_products' ) ) {
			wp_send_json_success( array( 'products' => array() ) );
		}

		$products = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 20,
				's'      => $term,
				'return' => 'objects',
			)
		);

		$out = array();
		foreach ( $products as $product ) {
			if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
				continue;
			}
			$out[] = array(
				'id'    => (int) $product->get_id(),
				'title' => method_exists( $product, 'get_name' ) ? (string) $product->get_name() : '',
			);
		}

		wp_send_json_success( array( 'products' => $out ) );
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

	/**
	 * Human-readable reason for admin UI.
	 *
	 * @param string $reason Machine reason.
	 */
	private function humanize_reason( string $reason ): string {
		$map = array(
			'malformed_merge_tags'          => __( 'Malformed merge tags (unmatched or invalid {{…}}).', 'universal-site-announcements' ),
			'token_in_attribute'            => __( 'Merge tags are not allowed inside HTML attributes.', 'universal-site-announcements' ),
			'product_token_inside_anchor'   => __( 'Product tokens cannot appear inside an existing link.', 'universal-site-announcements' ),
			'token_not_in_text_node'        => __( 'Merge tags must appear in text content.', 'universal-site-announcements' ),
			'free_shipping_token_forbidden' => __( 'Free-shipping threshold tokens are not allowed on manual announcements.', 'universal-site-announcements' ),
			'free_shipping_token_count'     => __( 'Free-shipping announcements require exactly one {{free_shipping_threshold}} token.', 'universal-site-announcements' ),
			'unknown_token'                 => __( 'Unknown merge tag.', 'universal-site-announcements' ),
			'invalid_product_token'         => __( 'Invalid product token (use a positive numeric ID).', 'universal-site-announcements' ),
			'invalid_token_argument'        => __( 'Invalid token argument.', 'universal-site-announcements' ),
		);

		return $map[ $reason ] ?? $reason;
	}
}
