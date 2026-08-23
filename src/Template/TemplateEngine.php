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
	 * Parser.
	 *
	 * @var MergeTagParser
	 */
	private MergeTagParser $parser;

	/**
	 * HTML placement validator.
	 *
	 * @var HtmlPlacementValidator
	 */
	private HtmlPlacementValidator $placement;

	/**
	 * Source token rules.
	 *
	 * @var SourceTokenRules
	 */
	private SourceTokenRules $rules;

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
	 * @param MergeTagParser         $parser    Parser.
	 * @param HtmlPlacementValidator $placement Placement validator.
	 * @param SourceTokenRules       $rules     Source rules.
	 * @param Sanitizer              $sanitizer Sanitiser.
	 * @param TokenProvider[]        $providers Token providers.
	 */
	public function __construct(
		MergeTagParser $parser,
		HtmlPlacementValidator $placement,
		SourceTokenRules $rules,
		Sanitizer $sanitizer,
		array $providers
	) {
		$this->parser    = $parser;
		$this->placement = $placement;
		$this->rules     = $rules;
		$this->sanitizer = $sanitizer;
		$this->providers = $providers;
	}

	/**
	 * Render a template for a source, or null on any failure.
	 *
	 * @param string               $template Raw template.
	 * @param string               $source   Announcement source.
	 * @param array<string, mixed> $context  Token context.
	 */
	public function render( string $template, string $source, array $context = array() ): ?string {
		$this->last_reason     = '';
		$this->last_diagnostic = array(
			'tokens'             => array(),
			'suppression_reason' => '',
			'source'             => $source,
		);

		$parsed = $this->parser->parse( $template );
		if ( ! $parsed['ok'] ) {
			return $this->fail( (string) $parsed['reason'] );
		}

		// Parsed merge tokens from a successful parse().
		$tokens = $parsed['tokens'];

		$rule_error = $this->rules->validate( $source, $tokens );
		if ( null !== $rule_error ) {
			return $this->fail( $rule_error );
		}

		$placement_error = $this->placement->validate( $template, $tokens );
		if ( null !== $placement_error ) {
			return $this->fail( $placement_error );
		}

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

			$token_diag[] = array(
				'raw'    => $token->raw,
				'status' => 'ok',
			);
			// Unique placeholder so duplicate product IDs each get a replacement pass.
			$key                  = "\x00USA_TOK_{$index}\x00";
			$replacements[ $key ] = $resolved;
			$template             = substr_replace( $template, $key, $token->offset, strlen( $token->raw ) );
			// Adjust subsequent offsets after this replacement length change.
			$delta = strlen( $key ) - strlen( $token->raw );
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
	 * @param string $source   Source.
	 * @return array{ok:bool,reason:string,tokens:list<array{raw:string,name:string,arg:?string}>}
	 */
	public function inspect( string $template, string $source ): array {
		$parsed = $this->parser->parse( $template );
		if ( ! $parsed['ok'] ) {
			return array(
				'ok'     => false,
				'reason' => (string) $parsed['reason'],
				'tokens' => array(),
			);
		}

		// Parsed merge tokens from a successful parse().
		$tokens = $parsed['tokens'];
		$list   = array();
		foreach ( $tokens as $token ) {
			$list[] = array(
				'raw'  => $token->raw,
				'name' => $token->name,
				'arg'  => $token->arg,
			);
		}

		$rule_error = $this->rules->validate( $source, $tokens );
		if ( null !== $rule_error ) {
			return array(
				'ok'     => false,
				'reason' => $rule_error,
				'tokens' => $list,
			);
		}

		$placement_error = $this->placement->validate( $template, $tokens );
		if ( null !== $placement_error ) {
			return array(
				'ok'     => false,
				'reason' => $placement_error,
				'tokens' => $list,
			);
		}

		return array(
			'ok'     => true,
			'reason' => '',
			'tokens' => $list,
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
