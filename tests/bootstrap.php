<?php
/**
 * PHPUnit bootstrap (no full WordPress install).
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

require dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/support/AnnouncementFixture.php';

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

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * @param string $key Option key.
	 */
	function delete_option( $key ): bool {
		if ( ! isset( $GLOBALS['usa_test_options'] ) || ! is_array( $GLOBALS['usa_test_options'] ) ) {
			return true;
		}
		unset( $GLOBALS['usa_test_options'][ (string) $key ] );
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

/**
 * @var array<int, array<string, mixed>>
 */
$GLOBALS['usa_test_post_meta'] = array();

/**
 * @var list<int>
 */
$GLOBALS['usa_test_post_ids'] = array();

/**
 * @var string
 */
$GLOBALS['usa_test_timezone'] = 'UTC';

/**
 * Post content keyed by post ID for the get_posts() stub.
 *
 * @var array<int, string>
 */
$GLOBALS['usa_test_post_content'] = array();

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Single.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$post_id = (int) $post_id;
		$key     = (string) $key;
		if ( ! isset( $GLOBALS['usa_test_post_meta'][ $post_id ] ) ) {
			return $single ? '' : array();
		}
		if ( '' === $key ) {
			return $GLOBALS['usa_test_post_meta'][ $post_id ];
		}
		if ( ! array_key_exists( $key, $GLOBALS['usa_test_post_meta'][ $post_id ] ) ) {
			return $single ? '' : array();
		}
		$value = $GLOBALS['usa_test_post_meta'][ $post_id ][ $key ];
		return $single ? $value : array( $value );
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Value.
	 */
	function update_post_meta( $post_id, $key, $value ): bool {
		$post_id = (int) $post_id;
		if ( ! isset( $GLOBALS['usa_test_post_meta'][ $post_id ] ) ) {
			$GLOBALS['usa_test_post_meta'][ $post_id ] = array();
		}
		$GLOBALS['usa_test_post_meta'][ $post_id ][ (string) $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 */
	function delete_post_meta( $post_id, $key ): bool {
		$post_id = (int) $post_id;
		$key     = (string) $key;
		if ( isset( $GLOBALS['usa_test_post_meta'][ $post_id ][ $key ] ) ) {
			unset( $GLOBALS['usa_test_post_meta'][ $post_id ][ $key ] );
		}
		return true;
	}
}

if ( ! function_exists( 'wp_timezone_string' ) ) {
	/**
	 * @return string
	 */
	function wp_timezone_string(): string {
		return isset( $GLOBALS['usa_test_timezone'] ) ? (string) $GLOBALS['usa_test_timezone'] : 'UTC';
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data Data.
	 * @return string|false
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}
}

/**
 * @var array<int, string>
 */
$GLOBALS['usa_test_post_types'] = array();

if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * @param array<string, mixed> $args Args.
	 * @return list<int>|list<object>
	 */
	function get_posts( $args = array() ) {
		$ids = isset( $GLOBALS['usa_test_post_ids'] ) && is_array( $GLOBALS['usa_test_post_ids'] )
			? $GLOBALS['usa_test_post_ids']
			: array();
		
		// If no explicit IDs, infer from content keys.
		if ( empty( $ids ) && isset( $GLOBALS['usa_test_post_content'] ) && is_array( $GLOBALS['usa_test_post_content'] ) ) {
			$ids = array_keys( $GLOBALS['usa_test_post_content'] );
		}
		
		// Filter by post_type if specified (default to match all if not set).
		if ( isset( $args['post_type'] ) ) {
			$post_type = (string) $args['post_type'];
			$types = isset( $GLOBALS['usa_test_post_types'] ) && is_array( $GLOBALS['usa_test_post_types'] )
				? $GLOBALS['usa_test_post_types']
				: array();
			$filtered_ids = array();
			foreach ( $ids as $id ) {
				// If post type is not set for this post, assume it matches.
				if ( ! isset( $types[ (int) $id ] ) || $types[ (int) $id ] === $post_type ) {
					$filtered_ids[] = $id;
				}
			}
			$ids = $filtered_ids;
		}
		
		sort( $ids, SORT_NUMERIC );
		$fields = isset( $args['fields'] ) ? (string) $args['fields'] : '';
		if ( 'ids' === $fields ) {
			return array_map( 'intval', $ids );
		}
		$content = isset( $GLOBALS['usa_test_post_content'] ) && is_array( $GLOBALS['usa_test_post_content'] )
			? $GLOBALS['usa_test_post_content']
			: array();
		$out     = array();
		foreach ( $ids as $id ) {
			$out[] = (object) array(
				'ID'           => (int) $id,
				'post_content' => isset( $content[ (int) $id ] ) ? (string) $content[ (int) $id ] : '',
			);
		}
		return $out;
	}
}

/**
 * @var array<string, array<string, mixed>>
 */
$GLOBALS['usa_test_registered_post_types'] = array();

if ( ! function_exists( 'register_post_type' ) ) {
	/**
	 * @param string               $post_type Post type key.
	 * @param array<string, mixed> $args      Arguments.
	 * @return object
	 */
	function register_post_type( $post_type, $args = array() ) {
		$GLOBALS['usa_test_registered_post_types'][ (string) $post_type ] = $args;
		return (object) array( 'name' => (string) $post_type );
	}
}

if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	/**
	 * @param bool $hard Hard flush.
	 */
	function flush_rewrite_rules( $hard = true ): bool {
		unset( $hard );
		$GLOBALS['usa_test_rewrite_flushed'] = true;
		return true;
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * @return bool
	 */
	function is_admin(): bool {
		return ! empty( $GLOBALS['usa_test_is_admin'] );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * @param string   $hook     Hook.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $accepted Accepted args.
	 */
	function add_action( $hook, $callback, $priority = 10, $accepted = 1 ): bool {
		unset( $hook, $callback, $priority, $accepted );
		return true;
	}
}

if ( ! function_exists( 'wp_update_post' ) ) {
	/**
	 * @param array<string, mixed> $postarr Post.
	 */
	function wp_update_post( $postarr ): int {
		return isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
	}
}

/**
 * @var array<string, mixed>
 */
$GLOBALS['usa_test_transients'] = array();

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * @param string $key Transient key.
	 * @return mixed
	 */
	function get_transient( $key ) {
		if ( ! isset( $GLOBALS['usa_test_transients'] ) || ! is_array( $GLOBALS['usa_test_transients'] ) ) {
			return false;
		}
		return array_key_exists( (string) $key, $GLOBALS['usa_test_transients'] )
			? $GLOBALS['usa_test_transients'][ (string) $key ]
			: false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * @param string $key        Transient key.
	 * @param mixed  $value      Value.
	 * @param int    $expiration Expiration.
	 */
	function set_transient( $key, $value, $expiration = 0 ): bool {
		unset( $expiration );
		if ( ! isset( $GLOBALS['usa_test_transients'] ) || ! is_array( $GLOBALS['usa_test_transients'] ) ) {
			$GLOBALS['usa_test_transients'] = array();
		}
		$GLOBALS['usa_test_transients'][ (string) $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * @param string $key Transient key.
	 */
	function delete_transient( $key ): bool {
		if ( ! isset( $GLOBALS['usa_test_transients'] ) || ! is_array( $GLOBALS['usa_test_transients'] ) ) {
			return true;
		}
		unset( $GLOBALS['usa_test_transients'][ (string) $key ] );
		return true;
	}
}

