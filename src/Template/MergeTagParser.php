<?php
/**
 * Merge-tag parser.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Template;

/**
 * Parses {{name}} / {{name:123}} tokens; rejects malformed brace syntax.
 *
 * Grammar (locked M3):
 * - Delimiters {{ … }}
 * - Name: [a-z][a-z0-9_]*
 * - Optional arg: :[1-9][0-9]*
 * - Any unmatched {{ or }}, or complete {{…}} that fails grammar → error
 */
final class MergeTagParser {

	/**
	 * Valid complete token pattern.
	 */
	private const TOKEN_PATTERN = '/\{\{([a-z][a-z0-9_]*)(?::([1-9][0-9]*))?\}\}/';

	/**
	 * Parse a template into tokens, or return an error reason.
	 *
	 * @param string $template Raw template HTML/text.
	 * @return array{ok:true,tokens:list<MergeToken>}|array{ok:false,reason:string}
	 */
	public function parse( string $template ): array {
		if ( ! $this->brace_balance_ok( $template ) ) {
			return array(
				'ok'     => false,
				'reason' => 'malformed_merge_tags',
			);
		}

		$tokens = array();
		if ( preg_match_all( self::TOKEN_PATTERN, $template, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches as $match ) {
				$raw      = $match[0][0];
				$offset   = (int) $match[0][1];
				$name     = $match[1][0];
				$arg      = isset( $match[2] ) && '' !== $match[2][0] ? $match[2][0] : null;
				$tokens[] = new MergeToken( $name, $arg, $raw, $offset );
			}
		}

		// Any remaining {{…}} that did not match the grammar is invalid.
		$stripped = preg_replace( self::TOKEN_PATTERN, '', $template );
		if ( ! is_string( $stripped ) ) {
			return array(
				'ok'     => false,
				'reason' => 'malformed_merge_tags',
			);
		}
		if ( false !== strpos( $stripped, '{{' ) || false !== strpos( $stripped, '}}' ) ) {
			return array(
				'ok'     => false,
				'reason' => 'malformed_merge_tags',
			);
		}

		return array(
			'ok'     => true,
			'tokens' => $tokens,
		);
	}

	/**
	 * Ensure every {{ is closed by }} and there are no stray closers.
	 *
	 * Scans left-to-right: each opener must be followed by a closer before
	 * the next opener; leftover }} is invalid.
	 *
	 * @param string $template Template.
	 */
	private function brace_balance_ok( string $template ): bool {
		$length = strlen( $template );
		$i      = 0;
		while ( $i < $length ) {
			$open  = strpos( $template, '{{', $i );
			$close = strpos( $template, '}}', $i );

			if ( false === $open && false === $close ) {
				return true;
			}

			// Stray closer before any opener (or with no opener left).
			if ( false === $open || ( false !== $close && $close < $open ) ) {
				return false;
			}

			// Opener without a following closer.
			if ( false === $close ) {
				return false;
			}

			// Nested {{ before the matching }} is invalid (no nesting).
			$next_open = strpos( $template, '{{', $open + 2 );
			if ( false !== $next_open && $next_open < $close ) {
				return false;
			}

			$i = $close + 2;
		}

		return true;
	}
}
