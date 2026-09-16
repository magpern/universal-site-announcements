<?php
/**
 * Page link merge tag.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Template;

/**
 * Resolves {{page:ID}} to a safe public page link.
 *
 * Resolving via get_permalink() at render time (rather than baking a raw URL
 * into the announcement body) is what lets a multilingual routing layer such
 * as Universal Multilingual localize the link automatically when it has a
 * published translation for the current language.
 */
final class PageToken implements TokenProvider {

	/**
	 * Optional page resolver for tests: function(int): ?array{title:string,url:string}.
	 *
	 * @var callable(int):(?array{title:string,url:string})|null
	 */
	private $resolver;

	/**
	 * Constructor.
	 *
	 * @param callable|null $resolver Optional test seam.
	 */
	public function __construct( ?callable $resolver = null ) {
		$this->resolver = $resolver;
	}

	/**
	 * Whether this provider handles the token.
	 *
	 * @param string      $name Token name.
	 * @param string|null $arg  Optional numeric argument.
	 */
	public function supports( string $name, ?string $arg ): bool {
		return SourceTokenRules::TOKEN_PAGE === $name
			&& null !== $arg
			&& 1 === preg_match( '/^[1-9][0-9]*$/', $arg );
	}

	/**
	 * Resolve to a safe HTML fragment, or null on failure.
	 *
	 * @param string               $name    Token name.
	 * @param string|null          $arg     Optional argument.
	 * @param array<string, mixed> $context Render context.
	 */
	public function resolve( string $name, ?string $arg, array $context ): ?string {
		unset( $name, $context );

		if ( null === $arg || ! ctype_digit( $arg ) || (int) $arg < 1 ) {
			return null;
		}

		$page_id = (int) $arg;
		$data    = null !== $this->resolver
			? ( $this->resolver )( $page_id )
			: $this->load_public_page( $page_id );

		if ( ! is_array( $data )
			|| empty( $data['title'] )
			|| empty( $data['url'] )
			|| ! is_string( $data['title'] )
			|| ! is_string( $data['url'] )
		) {
			return null;
		}

		$title = $data['title'];
		$url   = $data['url'];
		if ( '' === $title || '' === $url ) {
			return null;
		}

		return '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>';
	}

	/**
	 * Load a published, publicly viewable page.
	 *
	 * @param int $page_id Page ID.
	 * @return array{title:string,url:string}|null
	 */
	private function load_public_page( int $page_id ): ?array {
		$post = get_post( $page_id );
		if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}

		if ( ! is_post_publicly_viewable( $post ) ) {
			return null;
		}

		$title = (string) get_the_title( $post );
		$url   = (string) get_permalink( $post );

		if ( '' === $title || '' === $url || '#' === $url ) {
			return null;
		}

		return array(
			'title' => $title,
			'url'   => $url,
		);
	}
}
