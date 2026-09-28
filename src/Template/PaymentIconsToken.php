<?php
/**
 * {{payment_icons}} merge tag.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Template;

/**
 * Resolves {{payment_icons}} to a fixed, plugin-bundled set of payment-method
 * logos (Apple Pay, Google Pay, Visa, Mastercard).
 *
 * The markup is generated here, never taken from editor input, so announcement
 * content itself still cannot contain images: Sanitizer::sanitize() (used on
 * save) keeps its narrow allowlist, and Sanitizer::sanitize_output() only lets
 * through <img> tags that point at this plugin's own payment-icon assets.
 */
final class PaymentIconsToken implements TokenProvider {

	/**
	 * Icon key (asset file name without extension) => accessible name, and the
	 * intrinsic width/height hint used to avoid layout shift.
	 *
	 * @var array<string, array{label:string,width:int,height:int}>
	 */
	public const ICONS = array(
		'applepay'   => array(
			'label'  => 'Apple Pay',
			'width'  => 42,
			'height' => 18,
		),
		'googlepay'  => array(
			'label'  => 'Google Pay',
			'width'  => 46,
			'height' => 18,
		),
		'visa'       => array(
			'label'  => 'Visa',
			'width'  => 38,
			'height' => 18,
		),
		'mastercard' => array(
			'label'  => 'Mastercard',
			'width'  => 23,
			'height' => 18,
		),
	);

	/**
	 * Optional base-URL resolver (tests); defaults to the plugin's assets URL.
	 *
	 * @var callable|null
	 */
	private $base_url_resolver;

	/**
	 * Constructor.
	 *
	 * @param callable|null $base_url_resolver Returns the icon directory URL with a trailing slash.
	 */
	public function __construct( ?callable $base_url_resolver = null ) {
		$this->base_url_resolver = $base_url_resolver;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string      $name Token name.
	 * @param string|null $arg  Optional argument.
	 */
	public function supports( string $name, ?string $arg ): bool {
		return SourceTokenRules::TOKEN_PAYMENT_ICONS === $name && null === $arg;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string               $name    Token name.
	 * @param string|null          $arg     Optional argument.
	 * @param array<string, mixed> $context Render context.
	 */
	public function resolve( string $name, ?string $arg, array $context ): ?string {
		unset( $name, $arg, $context );

		$base = null !== $this->base_url_resolver
			? (string) ( $this->base_url_resolver )()
			: plugins_url( 'assets/img/payment-icons/', USA_PLUGIN_FILE );

		if ( '' === $base ) {
			return null;
		}

		$html = '<span class="usa-payment-icons">';
		foreach ( self::ICONS as $key => $icon ) {
			$html .= sprintf(
				'<img class="usa-payment-icon usa-payment-icon--%1$s" src="%2$s" alt="%3$s" width="%4$d" height="%5$d" loading="lazy" decoding="async" />',
				esc_attr( $key ),
				esc_url( $base . $key . '.svg' ),
				esc_attr( $icon['label'] ),
				$icon['width'],
				$icon['height']
			);
		}

		return $html . '</span>';
	}
}
