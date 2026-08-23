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
use USA\Admin\PluginActionLinks;
use USA\Admin\SettingsPage;
use USA\Announcement\ListTable;
use USA\Announcement\PostType;
use USA\Announcement\Repository;
use USA\Announcement\Sanitizer;
use USA\Announcement\ScheduleEvaluator;
use USA\Announcement\Selector;
use USA\Lifecycle\Schema;
use USA\Provider\EligibilityGate;
use USA\Provider\UmcActivity;
use USA\Provider\UmcThresholdDisplay;
use USA\Provider\WooCommerceFreeShippingProvider;
use USA\Rendering\ContentReplacer;
use USA\Rendering\StoreNoticeRenderer;
use USA\Template\FreeShippingThresholdToken;
use USA\Template\HtmlPlacementValidator;
use USA\Template\MergeTagParser;
use USA\Template\ProductToken;
use USA\Template\SourceTokenRules;
use USA\Template\TemplateEngine;
use USA\Template\TemplateRequirements;

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
		( new Schema() )->register();

		$sanitizer = new Sanitizer();
		$schedule  = new ScheduleEvaluator();
		$gate      = new EligibilityGate();
		$umc       = new UmcThresholdDisplay();
		$activity  = new UmcActivity();
		$provider  = new WooCommerceFreeShippingProvider( $gate, $umc, $sanitizer, $activity );

		$requirements = new TemplateRequirements(
			new MergeTagParser(),
			new HtmlPlacementValidator(),
			new SourceTokenRules()
		);
		$engine       = new TemplateEngine(
			$requirements,
			$sanitizer,
			array(
				new FreeShippingThresholdToken( $activity, $umc, $sanitizer ),
				new ProductToken(),
			)
		);

		$repository = new Repository( $sanitizer, $schedule, $engine, $provider );
		$selector   = new Selector( $repository );
		$replacer   = new ContentReplacer();

		( new PostType() )->register();
		( new SettingsPage() )->register();
		( new PluginActionLinks() )->register();
		( new AnnouncementMetaBoxes( $sanitizer, $schedule, $provider, $engine ) )->register();
		( new ListTable( $schedule, $requirements ) )->register();
		( new DiagnosticsNotice() )->register();
		( new StoreNoticeRenderer( $selector, $sanitizer, $replacer ) )->register();
	}
}
