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
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ), 20 );
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

		$contents = $this->selector->active_contents();
		$count    = count( $contents );

		if ( 0 === $count ) {
			return '';
		}

		if ( 1 === $count ) {
			$inner = $this->sanitizer->sanitize_output( $contents[0] );
			if ( '' === $inner ) {
				return '';
			}
			$result = $this->replacer->replace( $html, $inner );
			if ( ! $result['ok'] ) {
				DiagnosticsNotice::record_failure( (string) $result['error'] );
				return $html;
			}
			return $result['html'];
		}

		$spans = array();
		foreach ( $contents as $index => $content ) {
			$safe = $this->sanitizer->sanitize_output( $content );
			if ( '' === $safe ) {
				continue;
			}
			$class   = 'usa-announcement-bar__message' . ( 0 === $index ? ' is-active' : '' );
			$spans[] = '<span class="' . esc_attr( $class ) . '">' . $safe . '</span>';
		}

		if ( array() === $spans ) {
			return '';
		}

		// No-JS / reduced-motion: first message remains is-active; others hidden via CSS.
		$inner  = implode( '', $spans );
		$result = $this->replacer->replace( $html, $inner );
		if ( ! $result['ok'] ) {
			DiagnosticsNotice::record_failure( (string) $result['error'] );
			return $html;
		}

		$pause_label  = __( 'Pause announcements', 'universal-site-announcements' );
		$resume_label = __( 'Resume announcements', 'universal-site-announcements' );
		$button       = sprintf(
			'<button type="button" class="usa-announcement-bar__toggle" aria-pressed="false" data-label-pause="%1$s" data-label-resume="%2$s">%3$s</button>',
			esc_attr( $pause_label ),
			esc_attr( $resume_label ),
			esc_html( $pause_label )
		);

		return $this->replacer->wrap_shell( $result['html'], $button );
	}

	/**
	 * Enqueue rotation CSS/JS when two or more announcements are active.
	 *
	 * Runs on wp_enqueue_scripts (before the notice filter) so assets can print.
	 */
	public function maybe_enqueue_assets(): void {
		if ( ! Settings::is_enabled() ) {
			return;
		}

		if ( count( $this->selector->active_contents() ) < 2 ) {
			return;
		}

		$version = defined( 'USA_VERSION' ) ? USA_VERSION : '0.2.0';
		$base    = defined( 'USA_PLUGIN_FILE' ) ? plugin_dir_url( USA_PLUGIN_FILE ) : '';

		wp_enqueue_style(
			'usa-announcement-bar',
			$base . 'assets/css/announcement-bar.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'usa-announcement-bar',
			$base . 'assets/js/announcement-bar.js',
			array(),
			$version,
			true
		);
	}
}
