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
use USA\Integration\AimlCompatibility;
use USA\Integration\AimlIntegration;
use USA\Integration\TemplateOverlay;
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

		$aiml_compatibility = new AimlCompatibility();
		$aiml_identity      = null;
		if ( $aiml_compatibility->is_compatible() && class_exists( '\\AIMultilingual\\Integration\\Identity\\PluginIdentity' ) ) {
			$aiml_identity = new \AIMultilingual\Integration\Identity\PluginIdentity();
			add_action(
				'aiml_register_integrations',
				static function ( $registry ) use ( $aiml_identity, $aiml_compatibility ): void {
					if ( ! is_object( $registry ) || ! method_exists( $registry, 'register' ) ) {
						return;
					}
					$registry->register( new AimlIntegration( $aiml_identity, $aiml_compatibility ) );
				}
			);
		}

		$overlay    = new TemplateOverlay( $aiml_compatibility, $requirements, $aiml_identity );
		$repository = new Repository( $sanitizer, $schedule, $engine, $provider, $overlay );
		$selector   = new Selector( $repository );
		$replacer   = new ContentReplacer();

		( new PostType() )->register();
		( new SettingsPage() )->register();
		( new PluginActionLinks() )->register();
		( new AnnouncementMetaBoxes( $sanitizer, $schedule, $provider, $engine, $aiml_compatibility ) )->register();
		( new ListTable( $schedule, $requirements ) )->register();
		( new DiagnosticsNotice() )->register();
		( new StoreNoticeRenderer( $selector, $sanitizer, $replacer ) )->register();
	}
}
