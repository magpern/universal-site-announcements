<?php
/**
 * Plugin Name:       Universal Site Announcements
 * Plugin URI:        https://github.com/magpern/universal-site-announcements
 * Description:       Generic site-wide announcement bar with safe inline links. Integrates with the WooCommerce Store Notice seam when WooCommerce is active.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            magpern
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       universal-site-announcements
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'USA_VERSION', '0.1.0' );
define( 'USA_PLUGIN_FILE', __FILE__ );
define( 'USA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'USA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

$usa_autoload = __DIR__ . '/vendor/autoload.php';
if ( ! is_readable( $usa_autoload ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Universal Site Announcements requires Composer dependencies (run composer install).', 'universal-site-announcements' )
			);
		}
	);
	return;
}

require_once $usa_autoload;

register_activation_hook(
	__FILE__,
	static function (): void {
		( new \USA\Lifecycle\Activator() )->activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		( new \USA\Lifecycle\Deactivator() )->deactivate();
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		\USA\Plugin::instance()->init();
	}
);
