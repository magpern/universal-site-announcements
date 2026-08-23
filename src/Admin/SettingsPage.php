<?php
/**
 * Plugin settings admin page and menu information architecture.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Admin;

use USA\Announcement\PostType;
use USA\Provider\UmcThresholdDisplay;
use USA\Settings;

/**
 * CPT-rooted Announcements menu and Settings submenu.
 */
final class SettingsPage {

	public const MENU_SLUG = 'usa-settings';

	/**
	 * Register menus and settings.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Attach Settings under the CPT top-level menu.
	 *
	 * CPT registration uses show_in_menu=true so WordPress provides:
	 * Announcements → All Announcements, Add New.
	 */
	public function add_menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . PostType::POST_TYPE,
			__( 'Announcement Settings', 'universal-site-announcements' ),
			__( 'Settings', 'universal-site-announcements' ),
			Settings::manage_cap(),
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Parent file / menu slug used for the Announcements CPT menu.
	 */
	public static function menu_parent(): string {
		return 'edit.php?post_type=' . PostType::POST_TYPE;
	}

	/**
	 * Register Settings API fields.
	 */
	public function register_settings(): void {
		register_setting(
			'usa_settings_group',
			Settings::OPTION_ENABLED,
			array(
				'type'              => 'integer',
				'sanitize_callback' => static function ( $value ): int {
					return empty( $value ) ? 0 : 1;
				},
				'default'           => 1,
			)
		);

		register_setting(
			'usa_settings_group',
			Settings::OPTION_ROTATION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize_rotation' ),
				'default'           => Settings::rotation_defaults(),
			)
		);
	}

	/**
	 * Render settings page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( Settings::manage_cap() ) ) {
			return;
		}

		$rotation         = Settings::get_rotation();
		$interval_seconds = (int) round( $rotation['interval_ms'] / 1000 );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Announcement Settings', 'universal-site-announcements' ); ?></h1>
			<p>
				<?php
				echo esc_html__(
					'When announcements are enabled, this plugin owns Store Notice content. With no active announcements, the bar is hidden. Disable the setting or deactivate the plugin to restore the WooCommerce store notice text. This plugin never changes WooCommerce store-notice options.',
					'universal-site-announcements'
				);
				?>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'usa_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="usa_plugin_enabled"><?php echo esc_html__( 'Enable announcements', 'universal-site-announcements' ); ?></label>
						</th>
						<td>
							<input
								type="checkbox"
								id="usa_plugin_enabled"
								name="<?php echo esc_attr( Settings::OPTION_ENABLED ); ?>"
								value="1"
								<?php checked( Settings::is_enabled() ); ?>
							/>
							<p class="description">
								<?php echo esc_html__( 'Global on/off for USA content ownership of the Store Notice bar.', 'universal-site-announcements' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="usa_rotation_enabled"><?php echo esc_html__( 'Enable rotation', 'universal-site-announcements' ); ?></label>
						</th>
						<td>
							<input
								type="checkbox"
								id="usa_rotation_enabled"
								name="<?php echo esc_attr( Settings::OPTION_ROTATION ); ?>[enabled]"
								value="1"
								<?php checked( $rotation['enabled'] ); ?>
							/>
							<p class="description">
								<?php echo esc_html__( 'When disabled, only the highest-priority active announcement is shown (no fade, pause control, or rotation script). Single-message, no-JS, and reduced-motion visitors never rotate.', 'universal-site-announcements' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="usa_rotation_interval_seconds"><?php echo esc_html__( 'Rotation interval (seconds)', 'universal-site-announcements' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="usa_rotation_interval_seconds"
								name="<?php echo esc_attr( Settings::OPTION_ROTATION ); ?>[interval_seconds]"
								value="<?php echo esc_attr( (string) $interval_seconds ); ?>"
								min="<?php echo esc_attr( (string) (int) ( Settings::MIN_INTERVAL_MS / 1000 ) ); ?>"
								max="<?php echo esc_attr( (string) (int) ( Settings::MAX_INTERVAL_MS / 1000 ) ); ?>"
								step="1"
								class="small-text"
								required
							/>
							<p class="description">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: min seconds, 2: max seconds, 3: default seconds */
										__( 'Whole seconds only (%1$d–%2$d). Default %3$d.', 'universal-site-announcements' ),
										(int) ( Settings::MIN_INTERVAL_MS / 1000 ),
										(int) ( Settings::MAX_INTERVAL_MS / 1000 ),
										(int) ( Settings::DEFAULT_INTERVAL_MS / 1000 )
									)
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="usa_rotation_fade_ms"><?php echo esc_html__( 'Fade duration (milliseconds)', 'universal-site-announcements' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="usa_rotation_fade_ms"
								name="<?php echo esc_attr( Settings::OPTION_ROTATION ); ?>[fade_ms]"
								value="<?php echo esc_attr( (string) $rotation['fade_ms'] ); ?>"
								min="<?php echo esc_attr( (string) Settings::MIN_FADE_MS ); ?>"
								max="<?php echo esc_attr( (string) Settings::MAX_FADE_MS ); ?>"
								step="1"
								class="small-text"
								required
							/>
							<p class="description">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: min ms, 2: max ms, 3: default ms */
										__( 'Whole milliseconds only (%1$d–%2$d). Must be shorter than the interval. Default %3$d.', 'universal-site-announcements' ),
										Settings::MIN_FADE_MS,
										Settings::MAX_FADE_MS,
										Settings::DEFAULT_FADE_MS
									)
								);
								?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php echo esc_html__( 'Operational status', 'universal-site-announcements' ); ?></h2>
			<?php $this->render_diagnostics(); ?>
		</div>
		<?php
	}

	/**
	 * Operator-facing diagnostics already owned by USA.
	 */
	private function render_diagnostics(): void {
		$umc        = new UmcThresholdDisplay();
		$diag       = get_transient( DiagnosticsNotice::TRANSIENT_KEY );
		$render_err = ( is_array( $diag ) && ! empty( $diag['code'] ) ) ? (string) $diag['code'] : '';

		$rows = array(
			__( 'Announcements enabled', 'universal-site-announcements' ) => Settings::is_enabled()
				? __( 'Yes', 'universal-site-announcements' )
				: __( 'No', 'universal-site-announcements' ),
			__( 'Rotation enabled', 'universal-site-announcements' )      => Settings::is_rotation_enabled()
				? __( 'Yes', 'universal-site-announcements' )
				: __( 'No', 'universal-site-announcements' ),
			__( 'Rotation interval', 'universal-site-announcements' )     => sprintf(
				/* translators: %d: milliseconds */
				__( '%d ms', 'universal-site-announcements' ),
				Settings::rotation_interval_ms()
			),
			__( 'Fade duration', 'universal-site-announcements' )         => sprintf(
				/* translators: %d: milliseconds */
				__( '%d ms', 'universal-site-announcements' ),
				Settings::rotation_fade_ms()
			),
			__( 'UMC threshold API', 'universal-site-announcements' )     => $umc->is_available()
				? __( 'Available (function_exists)', 'universal-site-announcements' )
				: __( 'Unavailable — free-shipping provider will suppress', 'universal-site-announcements' ),
			__( 'Last render diagnostic', 'universal-site-announcements' ) => '' !== $render_err
				? $render_err
				: __( 'None', 'universal-site-announcements' ),
		);

		echo '<table class="widefat striped" style="max-width:40rem"><tbody>';
		foreach ( $rows as $label => $value ) {
			printf(
				'<tr><th scope="row">%s</th><td>%s</td></tr>',
				esc_html( (string) $label ),
				esc_html( (string) $value )
			);
		}
		echo '</tbody></table>';
	}
}
