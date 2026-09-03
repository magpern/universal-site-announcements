<?php
/**
 * Shared stub-post fixture for announcement pipeline tests.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Support;

use USA\Announcement\Repository;
use USA\Announcement\Sanitizer;
use USA\Announcement\ScheduleEvaluator;
use USA\Announcement\Selector;
use USA\Rendering\ContentReplacer;
use USA\Rendering\StoreNoticeRenderer;
use USA\Template\HtmlPlacementValidator;
use USA\Template\MergeTagParser;
use USA\Template\SourceTokenRules;
use USA\Template\TemplateEngine;
use USA\Template\TemplateRequirements;

/**
 * Builds announcement posts against the bootstrap stubs.
 */
trait AnnouncementFixture {

	/**
	 * Reset all stub state.
	 */
	protected function reset_world(): void {
		$GLOBALS['usa_test_post_ids']     = array();
		$GLOBALS['usa_test_post_meta']    = array();
		$GLOBALS['usa_test_post_content'] = array();
		$GLOBALS['usa_test_transients']   = array();
		$GLOBALS['usa_test_options']      = array();
		$GLOBALS['usa_test_timezone']     = 'UTC';
	}

	/**
	 * Register one announcement post.
	 *
	 * @param int                  $id      Post ID.
	 * @param string               $content Post content.
	 * @param array<string,string> $meta    Post meta.
	 */
	protected function add_announcement( int $id, string $content, array $meta = array() ): void {
		$GLOBALS['usa_test_post_ids'][]        = $id;
		$GLOBALS['usa_test_post_content'][ $id ] = $content;

		$defaults = array(
			'_usa_enabled'  => '1',
			'_usa_priority' => '10',
		);

		foreach ( array_merge( $defaults, $meta ) as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
	}

	/**
	 * Repository over the stub posts.
	 *
	 * @param list<\USA\Template\TokenProvider> $providers Optional token providers.
	 * @param \USA\Integration\TemplateOverlay|null $overlay Optional AIML overlay.
	 */
	protected function make_repository( array $providers = array(), $overlay = null ): Repository {
		$sanitizer    = new Sanitizer();
		$requirements = new TemplateRequirements(
			new MergeTagParser(),
			new HtmlPlacementValidator(),
			new SourceTokenRules()
		);
		$engine       = new TemplateEngine( $requirements, $sanitizer, $providers );

		return new Repository( $sanitizer, new ScheduleEvaluator(), $engine, null, $overlay );
	}

	/**
	 * Selector over the stub posts.
	 *
	 * @param list<\USA\Template\TokenProvider> $providers Optional token providers.
	 * @param \USA\Integration\TemplateOverlay|null $overlay Optional AIML overlay.
	 */
	protected function make_selector( array $providers = array(), $overlay = null ): Selector {
		return new Selector( $this->make_repository( $providers, $overlay ) );
	}

	/**
	 * A real TemplateOverlay; without AIML loaded its compatibility probe fails,
	 * which is the M5-B "overlay unavailable" fallback path.
	 */
	protected function make_overlay(): \USA\Integration\TemplateOverlay {
		return new \USA\Integration\TemplateOverlay(
			new \USA\Integration\AimlCompatibility(),
			new TemplateRequirements(
				new MergeTagParser(),
				new HtmlPlacementValidator(),
				new SourceTokenRules()
			),
			null
		);
	}

	/**
	 * Store-notice renderer over a selector.
	 *
	 * @param Selector|null $selector Optional selector to reuse.
	 */
	protected function make_renderer( ?Selector $selector = null ): StoreNoticeRenderer {
		return new StoreNoticeRenderer(
			$selector ?? $this->make_selector(),
			new Sanitizer(),
			new ContentReplacer()
		);
	}

	/**
	 * Upstream WooCommerce store-notice markup.
	 */
	protected function upstream_notice(): string {
		return '<p role="complementary" aria-label="Store notice" class="woocommerce-store-notice demo_store" data-notice-id="abc123" style="display:none;">Use coupon</p>';
	}
}
