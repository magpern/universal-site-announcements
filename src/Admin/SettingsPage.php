<?php
/**
 * Plugin settings admin page.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Admin;

use USA\Settings;

/**
 * Top-level settings menu and enable toggle.
 */
final class SettingsPage {

	/**
	 * Register menus and settings.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add top-level menu (parent for CPT submenu).
	 */
	public function add_menu(): void {
		$cap = Settings::manage_cap();
		add_menu_page(
			__( 'Site Announcements', 'universal-site-announcements' ),
			__( 'Announcements', 'universal-site-announcements' ),
			$cap,
			'usa-settings',
			array( $this, 'render_page' ),
			'dashicons-megaphone',
			58
		);

		add_submenu_page(
			'usa-settings',
			__( 'Settings', 'universal-site-announcements' ),
			__( 'Settings', 'universal-site-announcements' ),
			$cap,
			'usa-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register Settings API fields.
	 */
	public function register_settings(): void {
		register_setting(
			'usa_settings_group',
			Settings::OPTION_ENABLED,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => static function ( $value ): int {
					return empty( $value ) ? 0 : 1;
				},
				'default'           => 1,
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
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Universal Site Announcements', 'universal-site-announcements' ); ?></h1>
			<p>
				<?php
				echo esc_html__(
					'When enabled, this plugin owns Store Notice content. With no active announcements, the bar is hidden. Disable the plugin setting or deactivate the plugin to restore the WooCommerce store notice text. This plugin never changes the WooCommerce store-notice options.',
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
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
