<?php
/**
 * Announcement list table columns.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Announcement;

use USA\Provider\WooCommerceFreeShippingProvider;
use USA\Settings;

/**
 * Priority and schedule columns on the announcements list table.
 */
final class ListTable {

	/**
	 * Schedule evaluator for display.
	 *
	 * @var ScheduleEvaluator
	 */
	private ScheduleEvaluator $schedule;

	/**
	 * Constructor.
	 *
	 * @param ScheduleEvaluator $schedule Schedule helper.
	 */
	public function __construct( ScheduleEvaluator $schedule ) {
		$this->schedule = $schedule;
	}

	/**
	 * Register list-table hooks.
	 */
	public function register(): void {
		add_filter( 'manage_' . PostType::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . PostType::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-' . PostType::POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );
		add_action( 'quick_edit_custom_box', array( $this, 'quick_edit_box' ), 10, 2 );
		add_action( 'save_post_' . PostType::POST_TYPE, array( $this, 'save_quick_edit_priority' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_quick_edit_script' ) );
	}

	/**
	 * Insert priority and schedule columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['usa_priority'] = __( 'Priority', 'universal-site-announcements' );
				$new['usa_schedule'] = __( 'Schedule', 'universal-site-announcements' );
				$new['usa_source']   = __( 'Source', 'universal-site-announcements' );
			}
		}
		return $new;
	}

	/**
	 * Sortable columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function sortable_columns( array $columns ): array {
		$columns['usa_priority'] = 'usa_priority';
		return $columns;
	}

	/**
	 * Render custom columns.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'usa_priority':
				$priority = get_post_meta( $post_id, '_usa_priority', true );
				if ( '' === $priority ) {
					$priority = '10';
				}
				printf(
					'<span class="usa-priority-value" data-priority="%1$s">%2$s</span>',
					esc_attr( (string) $priority ),
					esc_html( (string) $priority )
				);
				break;

			case 'usa_schedule':
				echo esc_html( $this->format_schedule( $post_id ) );
				break;

			case 'usa_source':
				$source = (string) get_post_meta( $post_id, '_usa_source', true );
				if ( WooCommerceFreeShippingProvider::SOURCE === $source ) {
					echo esc_html__( 'Free shipping', 'universal-site-announcements' );
				} else {
					echo esc_html__( 'Manual', 'universal-site-announcements' );
				}
				break;
		}
	}

	/**
	 * Quick Edit field for priority.
	 *
	 * @param string $column_name Column.
	 * @param string $post_type   Post type.
	 */
	public function quick_edit_box( string $column_name, string $post_type ): void {
		if ( PostType::POST_TYPE !== $post_type || 'usa_priority' !== $column_name ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<label class="alignleft">
					<span class="title"><?php echo esc_html__( 'Priority', 'universal-site-announcements' ); ?></span>
					<input type="number" name="usa_priority_quick" class="usa-priority-quick" value="" />
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Persist priority from Quick Edit.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save_quick_edit_priority( int $post_id, $post ): void {
		unset( $post );

		if ( ! isset( $_POST['usa_priority_quick'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! current_user_can( Settings::manage_cap() ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- core quick-edit nonce already verified by WP.
		$raw = sanitize_text_field( wp_unslash( $_POST['usa_priority_quick'] ) );
		if ( ! is_numeric( $raw ) ) {
			return;
		}

		update_post_meta( $post_id, '_usa_priority', (string) (int) $raw );
	}

	/**
	 * Populate Quick Edit priority from the list row.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_quick_edit_script( string $hook ): void {
		if ( 'edit.php' !== $hook ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || PostType::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$script = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
	if (typeof inlineEditPost === 'undefined') { return; }
	var original = inlineEditPost.edit;
	inlineEditPost.edit = function (id) {
		original.apply(this, arguments);
		var postId = 0;
		if (typeof id === 'object') { postId = parseInt(this.getId(id), 10); }
		else { postId = parseInt(id, 10); }
		var row = document.getElementById('post-' + postId);
		if (!row) { return; }
		var cell = row.querySelector('.usa-priority-value');
		var input = document.querySelector('.inline-edit-row input.usa-priority-quick');
		if (cell && input) { input.value = cell.getAttribute('data-priority') || ''; }
	};
});
JS;
		wp_add_inline_script( 'inline-edit-post', $script );
	}

	/**
	 * Human-readable schedule summary in site timezone.
	 *
	 * @param int $post_id Post ID.
	 */
	private function format_schedule( int $post_id ): string {
		$tz      = $this->schedule->site_timezone_string();
		$start   = (string) get_post_meta( $post_id, ScheduleEvaluator::META_STARTS_AT, true );
		$end     = (string) get_post_meta( $post_id, ScheduleEvaluator::META_ENDS_AT, true );
		$start_l = '' !== $start ? $this->schedule->utc_to_site_local( $start, $tz ) : null;
		$end_l   = '' !== $end ? $this->schedule->utc_to_site_local( $end, $tz ) : null;

		if ( null === $start_l && null === $end_l ) {
			return __( 'Always', 'universal-site-announcements' );
		}

		$parts = array();
		if ( null !== $start_l ) {
			/* translators: %s: local datetime */
			$parts[] = sprintf( __( 'From %s', 'universal-site-announcements' ), str_replace( 'T', ' ', $start_l ) );
		}
		if ( null !== $end_l ) {
			/* translators: %s: local datetime (exclusive end) */
			$parts[] = sprintf( __( 'Until %s (exclusive)', 'universal-site-announcements' ), str_replace( 'T', ' ', $end_l ) );
		}
		return implode( ' · ', $parts );
	}
}
