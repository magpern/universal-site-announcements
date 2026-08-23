<?php
/**
 * Product link merge tag.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Template;

/**
 * Resolves {{product:ID}} to a safe public product link.
 */
final class ProductToken implements TokenProvider {

	/**
	 * Optional product resolver for tests: function(int): ?array{title:string,url:string}.
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
		return SourceTokenRules::TOKEN_PRODUCT === $name
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

		$product_id = (int) $arg;
		$data       = null !== $this->resolver
			? ( $this->resolver )( $product_id )
			: $this->load_public_product( $product_id );

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
	 * Load a published, publicly viewable WooCommerce product.
	 *
	 * @param int $product_id Product ID.
	 * @return array{title:string,url:string}|null
	 */
	private function load_public_product( int $product_id ): ?array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product = wc_get_product( $product_id );
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_status' ) ) {
			return null;
		}

		if ( 'publish' !== (string) $product->get_status() ) {
			return null;
		}

		if ( method_exists( $product, 'get_catalog_visibility' ) ) {
			$visibility = (string) $product->get_catalog_visibility();
			if ( 'hidden' === $visibility ) {
				return null;
			}
		}

		$post = get_post( $product_id );
		if ( ! $post || 'product' !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}

		if ( ! is_post_publicly_viewable( $post ) ) {
			return null;
		}

		$title = method_exists( $product, 'get_name' )
			? (string) $product->get_name()
			: (string) get_the_title( $product_id );
		$url   = method_exists( $product, 'get_permalink' )
			? (string) $product->get_permalink()
			: (string) get_permalink( $product_id );

		if ( '' === $title || '' === $url || '#' === $url ) {
			return null;
		}

		return array(
			'title' => $title,
			'url'   => $url,
		);
	}
}
