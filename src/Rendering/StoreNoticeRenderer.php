<?php
/**
 * WooCommerce Store Notice filter integration.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Rendering;

use USA\Announcement\DisplayMode;
use USA\Announcement\FixedRowStyle;
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
	 * Whether the multi-message JS rotation path should run.
	 *
	 * Requires two or more active messages and rotation enabled in settings.
	 *
	 * @param int $active_count Active rotating announcement count.
	 */
	public static function should_rotate( int $active_count ): bool {
		return $active_count >= 2 && Settings::is_rotation_enabled();
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

		$set      = $this->selector->render_set();
		$rotating = $set['rotating'];
		$count    = count( $rotating );

		$above = $this->fixed_fragment( $set['fixed'][ DisplayMode::PLACEMENT_ABOVE ], DisplayMode::PLACEMENT_ABOVE );
		$below = $this->fixed_fragment( $set['fixed'][ DisplayMode::PLACEMENT_BELOW ], DisplayMode::PLACEMENT_BELOW );

		$rotate = self::should_rotate( $count );

		if ( ! $rotate ) {
			$inner = 0 === $count ? '' : $this->sanitizer->sanitize_output( (string) $rotating[0]['content'] );
			if ( '' === $inner && null === $above && null === $below ) {
				return '';
			}

			$composed = $this->replacer->compose( $above, $inner, $below );
			$result   = $this->replacer->replace( $html, $composed );
			if ( ! $result['ok'] ) {
				DiagnosticsNotice::record_failure( (string) $result['error'] );
				return $html;
			}
			return $result['html'];
		}

		$spans = array();
		foreach ( $rotating as $index => $row ) {
			$safe = $this->sanitizer->sanitize_output( (string) $row['content'] );
			if ( '' === $safe ) {
				continue;
			}
			$class   = 'usa-announcement-bar__message' . ( 0 === $index ? ' is-active' : '' );
			$spans[] = '<span class="' . esc_attr( $class ) . '">' . $safe . '</span>';
		}

		if ( array() === $spans && null === $above && null === $below ) {
			return '';
		}

		// No-JS / reduced-motion: first message remains is-active; others hidden via CSS.
		$inner    = implode( '', $spans );
		$composed = $this->replacer->compose( $above, $inner, $below );
		$result   = $this->replacer->replace( $html, $composed );
		if ( ! $result['ok'] ) {
			DiagnosticsNotice::record_failure( (string) $result['error'] );
			return $html;
		}

		if ( array() === $spans ) {
			return $result['html'];
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
	 * Sanitised markup for one fixed-slot row, or null when there is nothing to show.
	 *
	 * @param array<string,mixed>|null $row       Winning fixed row.
	 * @param string                   $placement Placement (above|below).
	 */
	private function fixed_fragment( ?array $row, string $placement ): ?string {
		if ( null === $row ) {
			return null;
		}

		$safe = $this->sanitizer->sanitize_output( (string) $row['content'] );
		if ( '' === $safe ) {
			return null;
		}

		// Style meta is resolved from validated post meta, never from the
		// sanitized body above — it never re-enters sanitize_output()/wp_kses
		// and is fully independent of the AIML/token pipeline (M6.1).
		$style      = FixedRowStyle::resolve( (int) $row['id'] );
		$style_attr = FixedRowStyle::has_any( $style )
			? ' style="' . esc_attr( FixedRowStyle::to_css_vars( $style ) ) . '"'
			: '';

		return '<span class="' . esc_attr( 'usa-announcement-fixed usa-announcement-fixed--' . $placement ) . '"'
			. ' data-usa-fixed="' . esc_attr( $placement ) . '"' . $style_attr . '>' . $safe . '</span>';
	}

	/**
	 * Enqueue rotation CSS/JS when the multi-message rotation path is active.
	 *
	 * Runs on wp_enqueue_scripts (before the notice filter) so assets can print.
	 */
	public function maybe_enqueue_assets(): void {
		if ( ! Settings::is_enabled() ) {
			return;
		}

		$set       = $this->selector->render_set();
		$rotate    = self::should_rotate( count( $set['rotating'] ) );
		$has_fixed = null !== $set['fixed'][ DisplayMode::PLACEMENT_ABOVE ]
			|| null !== $set['fixed'][ DisplayMode::PLACEMENT_BELOW ];

		if ( ! $rotate && ! $has_fixed ) {
			return;
		}

		$version     = defined( 'USA_VERSION' ) ? USA_VERSION : '0.2.1';
		$base        = defined( 'USA_PLUGIN_FILE' ) ? plugin_dir_url( USA_PLUGIN_FILE ) : '';
		$fade_ms     = Settings::rotation_fade_ms();
		$interval_ms = Settings::rotation_interval_ms();

		wp_enqueue_style(
			'usa-announcement-bar',
			$base . 'assets/css/announcement-bar.css',
			array(),
			$version
		);

		if ( ! $rotate ) {
			return;
		}

		wp_add_inline_style(
			'usa-announcement-bar',
			sprintf( '.usa-announcement-bar__message{transition-duration:%dms;}', $fade_ms )
		);

		wp_enqueue_script(
			'usa-announcement-bar',
			$base . 'assets/js/announcement-bar.js',
			array(),
			$version,
			true
		);

		wp_localize_script(
			'usa-announcement-bar',
			'usaAnnouncementBar',
			array(
				'intervalMs' => $interval_ms,
				'fadeMs'     => $fade_ms,
			)
		);
	}
}
