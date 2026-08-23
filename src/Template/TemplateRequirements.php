<?php
/**
 * Derive announcement template requirements from validated content.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Template;

use USA\Provider\WooCommerceFreeShippingProvider;

/**
 * Server-side dependency analysis using the same grammar as rendering.
 */
final class TemplateRequirements {

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
	 * Token structure rules.
	 *
	 * @var SourceTokenRules
	 */
	private SourceTokenRules $rules;

	/**
	 * Constructor.
	 *
	 * @param MergeTagParser         $parser    Parser.
	 * @param HtmlPlacementValidator $placement Placement validator.
	 * @param SourceTokenRules       $rules     Token rules.
	 */
	public function __construct(
		MergeTagParser $parser,
		HtmlPlacementValidator $placement,
		SourceTokenRules $rules
	) {
		$this->parser    = $parser;
		$this->placement = $placement;
		$this->rules     = $rules;
	}

	/**
	 * Analyse a template. Invalid templates never derive a permissive manual mode.
	 *
	 * @param string $template Raw template HTML.
	 * @return array{
	 *   ok:bool,
	 *   reason:string,
	 *   requires_free_shipping:bool,
	 *   derived_source:string,
	 *   tokens:list<MergeToken>,
	 *   token_list:list<array{raw:string,name:string,arg:?string}>
	 * }
	 */
	public function analyse( string $template ): array {
		$empty = array(
			'ok'                     => false,
			'reason'                 => '',
			'requires_free_shipping' => false,
			'derived_source'         => 'manual',
			'tokens'                 => array(),
			'token_list'             => array(),
		);

		$parsed = $this->parser->parse( $template );
		if ( ! $parsed['ok'] ) {
			$empty['reason'] = (string) $parsed['reason'];
			return $empty;
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

		$rule_error = $this->rules->validate_structure( $tokens );
		if ( null !== $rule_error ) {
			$empty['reason']     = $rule_error;
			$empty['token_list'] = $list;
			return $empty;
		}

		$placement_error = $this->placement->validate( $template, $tokens );
		if ( null !== $placement_error ) {
			$empty['reason']     = $placement_error;
			$empty['token_list'] = $list;
			return $empty;
		}

		$requires = $this->rules->requires_free_shipping( $tokens );
		$source   = $requires
			? WooCommerceFreeShippingProvider::SOURCE
			: 'manual';

		return array(
			'ok'                     => true,
			'reason'                 => '',
			'requires_free_shipping' => $requires,
			'derived_source'         => $source,
			'tokens'                 => $tokens,
			'token_list'             => $list,
		);
	}

	/**
	 * Administrator-facing status label for the Dynamic requirements panel.
	 *
	 * @param array{ok:bool,reason:string,requires_free_shipping:bool} $analysis Analysis.
	 * @param callable(string):string                                  $humanize Reason humanizer.
	 */
	public function status_label( array $analysis, callable $humanize ): string {
		if ( ! $analysis['ok'] ) {
			return sprintf(
				/* translators: %s: concise validation reason */
				__( 'Template is invalid — %s', 'universal-site-announcements' ),
				$humanize( (string) $analysis['reason'] )
			);
		}
		if ( ! empty( $analysis['requires_free_shipping'] ) ) {
			return __( 'Requires WooCommerce free shipping', 'universal-site-announcements' );
		}
		return __( 'No special requirements', 'universal-site-announcements' );
	}
}
