<?php
/**
 * HTML-aware merge-tag placement validation.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Template;

/**
 * Rejects tokens in attribute values and product tokens nested in existing &lt;a&gt;.
 *
 * Approach (fail closed):
 * 1. Prefer PHP DOMDocument when available: wrap the fragment, scan every Attr
 *    value for "{{", and walk text-node ancestors for &lt;a&gt; when the text
 *    contains a product token. Tokens must appear in text nodes (including
 *    inside &lt;strong&gt;/&lt;em&gt;).
 * 2. If DOMDocument is unavailable or load fails, use a conservative regex
 *    strategy that rejects "{{" inside quoted attributes and "{{product:…}}"
 *    inside &lt;a&gt;…&lt;/a&gt; regions.
 * 3. Ambiguous / unparseable HTML → reject.
 */
final class HtmlPlacementValidator {

	/**
	 * Token names whose resolved output is a link and so must not nest inside
	 * an existing &lt;a&gt; (nested anchors are invalid HTML).
	 */
	private const LINK_TOKEN_NAMES = array( 'product', 'page' );

	/**
	 * Validate placement of already-parsed tokens within the template HTML.
	 *
	 * @param string       $template Template HTML.
	 * @param MergeToken[] $tokens   Parsed tokens.
	 * @return string|null Null when valid; reason code when invalid.
	 */
	public function validate( string $template, array $tokens ): ?string {
		if ( array() === $tokens ) {
			return null;
		}

		if ( class_exists( \DOMDocument::class ) ) {
			$dom_result = $this->validate_with_dom( $template, $tokens );
			if ( is_string( $dom_result ) || null === $dom_result ) {
				return $dom_result;
			}
			// false → DOM load failed; fall through to regex.
		}

		return $this->validate_with_regex( $template, $tokens );
	}

	/**
	 * DOM-based validation.
	 *
	 * @param string       $template Template.
	 * @param MergeToken[] $tokens   Tokens.
	 * @return string|null|false Null = OK; string = reason; false = DOM load failed.
	 */
	private function validate_with_dom( string $template, array $tokens ) {
		$previous = libxml_use_internal_errors( true );
		$dom      = new \DOMDocument();
		$wrapped  = '<div id="usa-tpl-root">' . $template . '</div>';
		$loaded   = $dom->loadHTML(
			'<?xml encoding="utf-8">' . $wrapped,
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return false;
		}

		$xpath = new \DOMXPath( $dom );

		$attr_nodes = $xpath->query( '//@*' );
		if ( false === $attr_nodes ) {
			return false;
		}
		foreach ( $attr_nodes as $attr ) {
			if ( ! $attr instanceof \DOMAttr ) {
				continue;
			}
			if ( false !== strpos( $attr->value, '{{' ) ) {
				return 'token_in_attribute';
			}
		}

		$text_nodes = $xpath->query( '//text()' );
		if ( false === $text_nodes ) {
			return false;
		}

		foreach ( $tokens as $token ) {
			if ( ! in_array( $token->name, self::LINK_TOKEN_NAMES, true ) ) {
				continue;
			}
			foreach ( $text_nodes as $text_node ) {
				if ( ! $text_node instanceof \DOMText ) {
					continue;
				}
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
				$node_text = $text_node->wholeText;
				if ( false === strpos( $node_text, $token->raw ) ) {
					continue;
				}
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
				$parent = $text_node->parentNode;
				while ( $parent instanceof \DOMNode ) {
					if ( $parent instanceof \DOMElement ) {
						// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
						$tag = strtolower( $parent->tagName );
						if ( 'a' === $tag ) {
							return $token->name . '_token_inside_anchor';
						}
					}
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
					$parent = $parent->parentNode;
				}
			}
		}

		$all_text = '';
		foreach ( $text_nodes as $text_node ) {
			if ( $text_node instanceof \DOMText ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
				$all_text .= $text_node->wholeText;
			}
		}
		foreach ( $tokens as $token ) {
			if ( false === strpos( $all_text, $token->raw ) ) {
				return 'token_not_in_text_node';
			}
		}

		return null;
	}

	/**
	 * Regex fallback (fail closed).
	 *
	 * @param string       $template Template.
	 * @param MergeToken[] $tokens   Tokens.
	 * @return string|null Reason or null when OK.
	 */
	private function validate_with_regex( string $template, array $tokens ): ?string {
		if ( preg_match( '/\s[\w:-]+\s*=\s*"[^"]*\{\{/', $template )
			|| preg_match( "/\s[\w:-]+\s*=\s*'[^']*\{\{/", $template )
		) {
			return 'token_in_attribute';
		}

		$without_tags = preg_replace( '/<[^>]*>/', '', $template );
		if ( ! is_string( $without_tags ) ) {
			return 'token_in_attribute';
		}
		foreach ( $tokens as $token ) {
			if ( false === strpos( $without_tags, $token->raw ) ) {
				return 'token_in_attribute';
			}
		}

		foreach ( $tokens as $token ) {
			if ( ! in_array( $token->name, self::LINK_TOKEN_NAMES, true ) || null === $token->arg ) {
				continue;
			}
			$pattern = '/<a\b[^>]*>.*?\{\{' . preg_quote( $token->name, '/' ) . ':' . preg_quote( $token->arg, '/' ) . '\}\}.*?<\/a>/is';
			if ( preg_match( $pattern, $template ) ) {
				return $token->name . '_token_inside_anchor';
			}
		}

		return null;
	}
}
