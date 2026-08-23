<?php
/**
 * Announcement content sanitiser.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Announcement;

/**
 * Narrow HTML allowlist for announcement content.
 */
final class Sanitizer {

	/**
	 * Allowed tags and attributes for manual announcement content.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public function allowed_html(): array {
		return array(
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
			'strong' => array(),
			'em'     => array(),
			'br'     => array(),
		);
	}

	/**
	 * Narrow allowlist for wc_price / UMC formatted_html fragments.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public function price_allowed_html(): array {
		return array(
			'span' => array(
				'class'       => true,
				'title'       => true,
				'aria-hidden' => true,
				'style'       => true,
			),
			'bdi'  => array(
				'class' => true,
				'dir'   => true,
			),
			'abbr' => array(
				'class' => true,
				'title' => true,
			),
			'b'    => array(
				'class' => true,
			),
		);
	}

	/**
	 * Merged allowlist for final announcement output (manual + price markup).
	 *
	 * @return array<string, array<string, bool>>
	 */
	public function output_allowed_html(): array {
		return array_merge( $this->allowed_html(), $this->price_allowed_html() );
	}

	/**
	 * Sanitise announcement HTML and enforce blank-target rel.
	 *
	 * @param string $html Raw HTML.
	 */
	public function sanitize( string $html ): string {
		$clean = wp_kses( $html, $this->allowed_html() );
		return $this->enforce_blank_rel( $clean );
	}

	/**
	 * Sanitise UMC/wc_price amount HTML with the narrow price allowlist.
	 *
	 * @param string $html Price HTML fragment.
	 */
	public function sanitize_price_html( string $html ): string {
		return wp_kses( $html, $this->price_allowed_html() );
	}

	/**
	 * Sanitise final bar output (manual links + price markup).
	 *
	 * @param string $html Announcement HTML.
	 */
	public function sanitize_output( string $html ): string {
		$clean = wp_kses( $html, $this->output_allowed_html() );
		return $this->enforce_blank_rel( $clean );
	}

	/**
	 * Ensure target=_blank links include noopener noreferrer.
	 *
	 * @param string $html HTML fragment.
	 */
	private function enforce_blank_rel( string $html ): string {
		if ( '' === $html || false === stripos( $html, 'target' ) ) {
			return $html;
		}

		return (string) preg_replace_callback(
			'/<a\b([^>]*)>/i',
			static function ( array $matches ): string {
				$attrs = $matches[1];
				if ( ! preg_match( '/\btarget\s*=\s*(["\'])_blank\1/i', $attrs ) ) {
					return '<a' . $attrs . '>';
				}

				if ( preg_match( '/\brel\s*=\s*(["\'])(.*?)\1/i', $attrs, $rel_match ) ) {
					$split = preg_split( '/\s+/', trim( $rel_match[2] ) );
					$parts = false === $split ? array() : $split;
					$parts = array_map( 'strtolower', $parts );
					foreach ( array( 'noopener', 'noreferrer' ) as $token ) {
						if ( ! in_array( $token, $parts, true ) ) {
							$parts[] = $token;
						}
					}
					$rel   = implode( ' ', array_unique( $parts ) );
					$attrs = preg_replace(
						'/\brel\s*=\s*(["\']).*?\1/i',
						'rel="' . esc_attr( $rel ) . '"',
						$attrs,
						1
					);
				} else {
					$attrs .= ' rel="noopener noreferrer"';
				}

				return '<a' . $attrs . '>';
			},
			$html
		);
	}
}
