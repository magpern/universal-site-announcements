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
	 * Allowed tags and attributes.
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
	 * Sanitise announcement HTML and enforce blank-target rel.
	 *
	 * @param string $html Raw HTML.
	 */
	public function sanitize( string $html ): string {
		$clean = wp_kses( $html, $this->allowed_html() );
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
