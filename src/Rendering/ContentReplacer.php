<?php
/**
 * Store-notice content replacement.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Rendering;

/**
 * Replaces inner HTML of the upstream store-notice &lt;p&gt; while preserving
 * the original opening-tag fragment (aside from surgical style edits).
 */
final class ContentReplacer {

	/**
	 * Compose fixed-above, rotating, and fixed-below fragments into one inner HTML string.
	 *
	 * The result is passed to replace(), so the single-host-element contract is
	 * unchanged: one store-notice paragraph in, one out. Fragments that are null
	 * or empty contribute nothing — no empty wrapper is ever emitted.
	 *
	 * @param string|null $above_html          Already-sanitised fixed-above fragment, or null.
	 * @param string      $rotating_inner_html Already-sanitised rotating inner HTML ('' when none).
	 * @param string|null $below_html          Already-sanitised fixed-below fragment, or null.
	 */
	public function compose( ?string $above_html, string $rotating_inner_html, ?string $below_html ): string {
		$parts = array();

		if ( null !== $above_html && '' !== $above_html ) {
			$parts[] = $above_html;
		}
		if ( '' !== $rotating_inner_html ) {
			$parts[] = $rotating_inner_html;
		}
		if ( null !== $below_html && '' !== $below_html ) {
			$parts[] = $below_html;
		}

		return implode( '', $parts );
	}

	/**
	 * Replace inner content of the recognised store-notice paragraph.
	 *
	 * @param string $upstream_html Filtered markup from WooCommerce/host/theme.
	 * @param string $inner_html    Already-sanitised announcement HTML.
	 * @return array{ok:bool,html:string,error:?string}
	 */
	public function replace( string $upstream_html, string $inner_html ): array {
		$match = $this->locate_notice_paragraph( $upstream_html );
		if ( null === $match ) {
			return array(
				'ok'    => false,
				'html'  => $upstream_html,
				'error' => 'unrecognised_outer_markup',
			);
		}

		$opening = $this->strip_display_none_from_opening_tag( $match['opening'] );
		$html    = $match['before'] . $opening . $inner_html . '</p>' . $match['after'];

		return array(
			'ok'    => true,
			'html'  => $html,
			'error' => null,
		);
	}

	/**
	 * Locate the first store-notice &lt;p&gt; and surrounding segments.
	 *
	 * @param string $html Upstream HTML.
	 * @return array{before:string,opening:string,after:string}|null
	 */
	public function locate_notice_paragraph( string $html ): ?array {
		if ( ! preg_match_all( '/<p\b[^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}

		foreach ( $matches[0] as $hit ) {
			$opening = $hit[0];
			$offset  = (int) $hit[1];

			if ( ! $this->opening_has_required_classes( $opening ) ) {
				continue;
			}

			$open_end = $offset + strlen( $opening );
			$close    = stripos( $html, '</p>', $open_end );
			if ( false === $close ) {
				return null;
			}

			return array(
				'before'  => substr( $html, 0, $offset ),
				'opening' => $opening,
				'after'   => substr( $html, $close + 4 ),
			);
		}

		return null;
	}

	/**
	 * Whether the opening tag includes required class tokens.
	 *
	 * @param string $opening Opening tag fragment.
	 */
	public function opening_has_required_classes( string $opening ): bool {
		if ( ! preg_match( '/\bclass\s*=\s*(["\'])(.*?)\1/i', $opening, $m ) ) {
			return false;
		}
		$split   = preg_split( '/\s+/', trim( $m[2] ) );
		$classes = false === $split ? array() : $split;
		return in_array( 'woocommerce-store-notice', $classes, true )
			&& in_array( 'demo_store', $classes, true );
	}

	/**
	 * Remove only display:none from the style attribute; preserve opening tag otherwise.
	 *
	 * @param string $opening Opening tag fragment.
	 */
	public function strip_display_none_from_opening_tag( string $opening ): string {
		if ( ! preg_match( '/\bstyle\s*=\s*(["\'])(.*?)\1/i', $opening, $m, PREG_OFFSET_CAPTURE ) ) {
			return $opening;
		}

		$quote   = $m[1][0];
		$style   = $m[2][0];
		$cleaned = $this->remove_display_none_declarations( $style );
		$full    = $m[0][0];
		$start   = (int) $m[0][1];

		if ( '' === $cleaned ) {
			$replacement = '';
			// Drop a trailing/leading space left by attribute removal.
			$before = substr( $opening, 0, $start );
			$after  = substr( $opening, $start + strlen( $full ) );
			$joined = $before . $after;
			$joined = preg_replace( '/\s{2,}/', ' ', $joined ) ?? $joined;
			$joined = preg_replace( '/\s+>/', '>', $joined ) ?? $joined;
			$joined = preg_replace( '/\s+\/>/', ' />', $joined ) ?? $joined;
			return $joined;
		}

		$replacement = 'style=' . $quote . $cleaned . $quote;
		return substr( $opening, 0, $start ) . $replacement . substr( $opening, $start + strlen( $full ) );
	}

	/**
	 * Drop display:none declarations from a CSS style string.
	 *
	 * @param string $style Inline style attribute value.
	 */
	public function remove_display_none_declarations( string $style ): string {
		$parts = explode( ';', $style );
		$kept  = array();
		foreach ( $parts as $part ) {
			$trimmed = trim( $part );
			if ( '' === $trimmed ) {
				continue;
			}
			if ( preg_match( '/^display\s*:\s*none\s*$/i', $trimmed ) ) {
				continue;
			}
			$kept[] = $trimmed;
		}
		return implode( '; ', $kept );
	}

	/**
	 * Wrap a successfully replaced store-notice paragraph in the rotation shell
	 * with a pause button as a sibling outside the &lt;p&gt;.
	 *
	 * @param string $replaced_html Full HTML from a successful replace().
	 * @param string $button_html   Already-escaped pause button markup.
	 */
	public function wrap_shell( string $replaced_html, string $button_html ): string {
		$match = $this->locate_notice_paragraph( $replaced_html );
		if ( null === $match ) {
			return $replaced_html;
		}

		$p_start = strlen( $match['before'] );
		$close   = stripos( $replaced_html, '</p>', $p_start );
		if ( false === $close ) {
			return $replaced_html;
		}

		$paragraph = substr( $replaced_html, $p_start, ( $close + 4 ) - $p_start );

		return $match['before']
			. '<div class="usa-announcement-shell">'
			. $paragraph
			. $button_html
			. '</div>'
			. $match['after'];
	}
}
