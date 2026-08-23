<?php
/**
 * PHPUnit bootstrap (no full WordPress install).
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

require dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * @param string $text Text.
	 */
	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * @param string $text Text.
	 */
	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 */
	function esc_html__( $text, $domain = '' ): string {
		unset( $domain );
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_kses' ) ) {
	/**
	 * Minimal allowlist kses for unit tests.
	 *
	 * @param string               $content Content.
	 * @param array<string, mixed> $allowed Allowed tags.
	 */
	function wp_kses( $content, $allowed ): string {
		$tags = array_keys( $allowed );
		$tag_list = implode( '|', array_map( 'preg_quote', $tags ) );
		// Strip disallowed tags crudely while keeping allowed ones.
		$previous = null;
		$result   = (string) $content;
		while ( $previous !== $result ) {
			$previous = $result;
			$result   = (string) preg_replace(
				'#</?(?!(?:' . $tag_list . ')\b)[a-z0-9]+\b[^>]*>#i',
				'',
				$result
			);
		}
		// Drop on* attributes and javascript: hrefs.
		$result = (string) preg_replace( '/\son[a-z]+\s*=\s*(["\']).*?\1/i', '', $result );
		$result = (string) preg_replace( '/\shref\s*=\s*(["\'])\s*javascript:[^"\']*\1/i', ' href="#"', $result );
		return $result;
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 */
	function __( $text, $domain = '' ): string { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames
		unset( $domain );
		return $text;
	}
}

/**
 * @var array<string, mixed>
 */
$GLOBALS['usa_test_filters'] = array();

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Minimal apply_filters for unit tests.
	 *
	 * @param string $hook          Hook name.
	 * @param mixed  $value         Value.
	 * @param mixed  ...$args       Extra args (ignored).
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		unset( $args );
		if ( isset( $GLOBALS['usa_test_filters'][ $hook ] ) && is_callable( $GLOBALS['usa_test_filters'][ $hook ] ) ) {
			return ( $GLOBALS['usa_test_filters'][ $hook ] )( $value );
		}
		return $value;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	/**
	 * @return int
	 */
	function get_current_user_id(): int {
		return 0;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $key     Option key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) {
		if ( isset( $GLOBALS['usa_test_options'] ) && is_array( $GLOBALS['usa_test_options'] ) && array_key_exists( $key, $GLOBALS['usa_test_options'] ) ) {
			return $GLOBALS['usa_test_options'][ $key ];
		}
		return $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param string $key   Option key.
	 * @param mixed  $value Value.
	 * @param mixed  $autoload Autoload (ignored).
	 */
	function update_option( $key, $value, $autoload = null ): bool {
		unset( $autoload );
		if ( ! isset( $GLOBALS['usa_test_options'] ) || ! is_array( $GLOBALS['usa_test_options'] ) ) {
			$GLOBALS['usa_test_options'] = array();
		}
		$GLOBALS['usa_test_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * @param string $path Path.
	 */
	function admin_url( $path = '' ): string {
		return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * @param string $cap Capability.
	 */
	function current_user_can( $cap ): bool {
		unset( $cap );
		return ! empty( $GLOBALS['usa_test_current_user_can'] );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * @param string $url URL.
	 */
	function esc_url( $url ): string {
		return (string) $url;
	}
}

if ( ! defined( 'USA_PLUGIN_BASENAME' ) ) {
	define( 'USA_PLUGIN_BASENAME', 'universal-site-announcements/universal-site-announcements.php' );
}

