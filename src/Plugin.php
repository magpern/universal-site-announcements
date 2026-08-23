<?php
/**
 * Plugin bootstrap.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA;

use USA\Admin\AnnouncementMetaBoxes;
use USA\Admin\DiagnosticsNotice;
use USA\Admin\SettingsPage;
use USA\Announcement\PostType;
use USA\Announcement\Repository;
use USA\Announcement\Sanitizer;
use USA\Announcement\Selector;
use USA\Rendering\ContentReplacer;
use USA\Rendering\StoreNoticeRenderer;

/**
 * Main plugin controller.
 */
final class Plugin {

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers hooks.
	 */
	public function init(): void {
		$sanitizer  = new Sanitizer();
		$repository = new Repository( $sanitizer );
		$selector   = new Selector( $repository );
		$replacer   = new ContentReplacer();

		( new PostType() )->register();
		( new SettingsPage() )->register();
		( new AnnouncementMetaBoxes( $sanitizer ) )->register();
		( new DiagnosticsNotice() )->register();
		( new StoreNoticeRenderer( $selector, $sanitizer, $replacer ) )->register();
	}
}
