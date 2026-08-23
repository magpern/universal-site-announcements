<?php
/**
 * WooCommerce Store Notice filter integration.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Rendering;

use USA\Announcement\Sanitizer;
use USA\Announcement\Selector;
use USA\Admin\DiagnosticsNotice;
use USA\Settings;

/**
 * Filters woocommerce_demo_store at priority 20.
 */
final class StoreNoticeRenderer {

	/**
	 * Announcement selector.
	 *
	 * @var Selector
	 */
	private Selector $selector;

	/**
	 * Content sanitiser.
	 *
	 * @var Sanitizer
	 */
	private Sanitizer $sanitizer;

	/**
	 * Markup replacer.
	 *
	 * @var ContentReplacer
	 */
	private ContentReplacer $replacer;

	/**
	 * Constructor.
	 *
	 * @param Selector        $selector  Announcement selector.
	 * @param Sanitizer       $sanitizer Content sanitiser.
	 * @param ContentReplacer $replacer  Markup replacer.
	 */
	public function __construct( Selector $selector, Sanitizer $sanitizer, ContentReplacer $replacer ) {
		$this->selector  = $selector;
		$this->sanitizer = $sanitizer;
		$this->replacer  = $replacer;
	}

	/**
	 * Register filter when WooCommerce is available.
	 */
	public function register(): void {
		if ( ! function_exists( 'is_store_notice_showing' ) ) {
			return;
		}
		add_filter( 'woocommerce_demo_store', array( $this, 'filter_notice' ), 20, 2 );
	}

	/**
	 * Filter callback.
	 *
	 * @param string $html   Upstream notice HTML.
	 * @param string $notice Raw notice text (unused for content).
	 */
	public function filter_notice( $html, $notice = '' ): string {
		unset( $notice );

		if ( ! is_string( $html ) ) {
			$html = '';
		}

		if ( ! Settings::is_enabled() ) {
			return $html;
		}

		$content = $this->selector->first_content();
		if ( null === $content ) {
			return '';
		}

		$content = $this->sanitizer->sanitize( $content );
		if ( '' === $content ) {
			return '';
		}

		$result = $this->replacer->replace( $html, $content );
		if ( ! $result['ok'] ) {
			DiagnosticsNotice::record_failure( (string) $result['error'] );
			return $html;
		}

		return $result['html'];
	}
}
