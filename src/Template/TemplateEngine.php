<?php
/**
 * Template engine: validate → parse → resolve → sanitise.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Template;

use USA\Announcement\Sanitizer;

/**
 * Renders announcement templates with controlled merge tags.
 */
final class TemplateEngine {

	/**
	 * Requirements analyser (same grammar as render).
	 *
	 * @var TemplateRequirements
	 */
	private TemplateRequirements $requirements;

	/**
	 * Content sanitiser.
	 *
	 * @var Sanitizer
	 */
	private Sanitizer $sanitizer;

	/**
	 * Token providers.
	 *
	 * @var TokenProvider[]
	 */
	private array $providers;

	/**
	 * Last failure reason.
	 *
	 * @var string
	 */
	private string $last_reason = '';

	/**
	 * Last diagnostic snapshot.
	 *
	 * @var array<string, mixed>
	 */
	private array $last_diagnostic = array();

	/**
	 * Constructor.
	 *
	 * @param TemplateRequirements $requirements Requirements analyser.
	 * @param Sanitizer            $sanitizer    Sanitiser.
	 * @param TokenProvider[]      $providers    Token providers.
	 */
	public function __construct(
		TemplateRequirements $requirements,
		Sanitizer $sanitizer,
		array $providers
	) {
		$this->requirements = $requirements;
		$this->sanitizer    = $sanitizer;
		$this->providers    = $providers;
	}

	/**
	 * Requirements analyser accessor.
	 */
	public function requirements(): TemplateRequirements {
		return $this->requirements;
	}

	/**
	 * Render a template, deriving requirements from content (source arg is ignored if passed for BC).
	 *
	 * @param string               $template Raw template.
	 * @param string               $source   Ignored; derived from template. Kept for call-site BC.
	 * @param array<string, mixed> $context  Token context.
	 */
	public function render( string $template, string $source = '', array $context = array() ): ?string {
		unset( $source );

		$this->last_reason     = '';
		$this->last_diagnostic = array(
			'tokens'             => array(),
			'suppression_reason' => '',
			'source'             => '',
		);

		$analysis                        = $this->requirements->analyse( $template );
		$this->last_diagnostic['source'] = $analysis['derived_source'];

		if ( ! $analysis['ok'] ) {
			return $this->fail( (string) $analysis['reason'] );
		}

		$tokens       = $analysis['tokens'];
		$token_diag   = array();
		$replacements = array();

		foreach ( $tokens as $index => $token ) {
			$provider = $this->find_provider( $token->name, $token->arg );
			if ( null === $provider ) {
				$token_diag[]                    = array(
					'raw'    => $token->raw,
					'status' => 'unknown',
				);
				$this->last_diagnostic['tokens'] = $token_diag;
				return $this->fail( 'unknown_token' );
			}

			$resolved = $provider->resolve( $token->name, $token->arg, $context );
			if ( null === $resolved ) {
				$token_diag[]                    = array(
					'raw'    => $token->raw,
					'status' => 'unresolved',
				);
				$this->last_diagnostic['tokens'] = $token_diag;
				return $this->fail( 'token_unresolved:' . $token->name );
			}

			$token_diag[]         = array(
				'raw'    => $token->raw,
				'status' => 'ok',
			);
			$key                  = "\x00USA_TOK_{$index}\x00";
			$replacements[ $key ] = $resolved;
			$template             = substr_replace( $template, $key, $token->offset, strlen( $token->raw ) );
			$delta                = strlen( $key ) - strlen( $token->raw );
			for ( $j = $index + 1, $n = count( $tokens ); $j < $n; $j++ ) {
				if ( $tokens[ $j ]->offset > $token->offset ) {
					$tokens[ $j ] = new MergeToken(
						$tokens[ $j ]->name,
						$tokens[ $j ]->arg,
						$tokens[ $j ]->raw,
						$tokens[ $j ]->offset + $delta
					);
				}
			}
		}

		foreach ( $replacements as $key => $html ) {
			$template = str_replace( $key, $html, $template );
		}

		$this->last_diagnostic['tokens'] = $token_diag;

		$clean = $this->sanitizer->sanitize_output( $template );
		if ( '' === $clean ) {
			return $this->fail( 'empty_after_sanitize' );
		}

		return $clean;
	}

	/**
	 * Inspect a template without resolving (admin preview helpers).
	 *
	 * @param string $template Template.
	 * @param string $source   Ignored; kept for call-site BC.
	 * @return array{
	 *   ok:bool,
	 *   reason:string,
	 *   tokens:list<array{raw:string,name:string,arg:?string}>,
	 *   requires_free_shipping:bool,
	 *   derived_source:string
	 * }
	 */
	public function inspect( string $template, string $source = '' ): array {
		unset( $source );
		$analysis = $this->requirements->analyse( $template );
		return array(
			'ok'                     => $analysis['ok'],
			'reason'                 => $analysis['reason'],
			'tokens'                 => $analysis['token_list'],
			'requires_free_shipping' => $analysis['requires_free_shipping'],
			'derived_source'         => $analysis['derived_source'],
		);
	}

	/**
	 * Last failure reason (empty on success).
	 */
	public function last_reason(): string {
		return $this->last_reason;
	}

	/**
	 * Last diagnostic snapshot.
	 *
	 * @return array<string, mixed>
	 */
	public function last_diagnostic(): array {
		return $this->last_diagnostic;
	}

	/**
	 * Find a provider that supports the token.
	 *
	 * @param string      $name Token name.
	 * @param string|null $arg  Optional argument.
	 */
	private function find_provider( string $name, ?string $arg ): ?TokenProvider {
		foreach ( $this->providers as $provider ) {
			if ( $provider->supports( $name, $arg ) ) {
				return $provider;
			}
		}
		return null;
	}

	/**
	 * Record failure and return null.
	 *
	 * @param string $reason Reason code.
	 */
	private function fail( string $reason ): ?string {
		$this->last_reason                           = $reason;
		$this->last_diagnostic['suppression_reason'] = $reason;
		return null;
	}
}
