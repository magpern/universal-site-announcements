<?php
/**
 * AIML visitor overlay for announcement template bodies.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Integration;

use AIMultilingual\Extension\ExtensionServices;
use AIMultilingual\Extension\LanguageReference;
use AIMultilingual\Extension\SourceSegmentReference;
use AIMultilingual\Integration\Identity\PluginIdentity;
use USA\Admin\DiagnosticsNotice;
use USA\Template\TemplateRequirements;

/**
 * Resolves localized announcement bodies via public AIML Extension APIs.
 */
final class TemplateOverlay {

	/**
	 * Compatibility probe.
	 *
	 * @var AimlCompatibility
	 */
	private AimlCompatibility $compatibility;

	/**
	 * Template requirements analyser (signature + structure).
	 *
	 * @var TemplateRequirements
	 */
	private TemplateRequirements $requirements;

	/**
	 * Identity serializer (optional until AIML loads).
	 *
	 * @var PluginIdentity|null
	 */
	private ?PluginIdentity $identity;

	/**
	 * Constructor.
	 *
	 * @param AimlCompatibility    $compatibility Compatibility probe.
	 * @param TemplateRequirements $requirements  Requirements analyser.
	 * @param PluginIdentity|null  $identity      Identity serializer when AIML available.
	 */
	public function __construct(
		AimlCompatibility $compatibility,
		TemplateRequirements $requirements,
		?PluginIdentity $identity = null
	) {
		$this->compatibility = $compatibility;
		$this->requirements  = $requirements;
		$this->identity      = $identity;
	}

	/**
	 * Returns overlay template or original source on any ineligible path.
	 *
	 * @param int    $post_id         Announcement post ID.
	 * @param string $source_template Source post_content template.
	 */
	public function apply( int $post_id, string $source_template ): string {
		if ( ! $this->compatibility->is_compatible() || null === $this->identity ) {
			return $source_template;
		}

		if ( ! function_exists( 'aiml_visitor_language' ) ) {
			return $source_template;
		}

		$lang = aiml_visitor_language();
		if ( null === $lang || $lang->is_default || '' === $lang->code ) {
			return $source_template;
		}

		$resolver = ExtensionServices::resolver();
		if ( null === $resolver ) {
			return $source_template;
		}

		$segment_key = $this->identity->build(
			AimlIntegration::INTEGRATION_ID,
			AimlIntegration::OWNER_TYPE,
			(string) $post_id,
			AimlIntegration::FIELD_BODY
		);

		$resolved = $resolver->resolve(
			new SourceSegmentReference( 'post', $post_id, $segment_key ),
			new LanguageReference( $lang->code )
		);

		if ( null === $resolved || ! $resolved->available || '' === trim( $resolved->text ) ) {
			return $source_template;
		}

		$overlay          = $resolved->text;
		$source_analysis  = $this->requirements->analyse( $source_template );
		$overlay_analysis = $this->requirements->analyse( $overlay );

		if ( ! $overlay_analysis['ok'] ) {
			DiagnosticsNotice::record_failure( 'template_' . $overlay_analysis['reason'], $post_id );
			return $source_template;
		}

		if ( ! self::token_signature_matches( $source_analysis['token_list'], $overlay_analysis['token_list'] ) ) {
			DiagnosticsNotice::record_failure( DiagnosticsNotice::CODE_OVERLAY_TOKEN_SIGNATURE_MISMATCH, $post_id );
			return $source_template;
		}

		DiagnosticsNotice::clear_if_recovered( DiagnosticsNotice::CODE_OVERLAY_TOKEN_SIGNATURE_MISMATCH, $post_id );
		// Overlay body is valid again — clear a prior overlay-template diagnostic for this same announcement only.
		DiagnosticsNotice::clear_if_recovered_prefix( 'template_', $post_id );

		return $overlay;
	}

	/**
	 * Exact merge-tag multiset equality on {name, arg} bags.
	 *
	 * @param list<array{name:string,arg:?string}> $source  Source token_list.
	 * @param list<array{name:string,arg:?string}> $overlay Overlay token_list.
	 */
	public static function token_signature_matches( array $source, array $overlay ): bool {
		return self::signature_bag( $source ) === self::signature_bag( $overlay );
	}

	/**
	 * Canonical occurrence bag for token signatures.
	 *
	 * @param list<array{name:string,arg:?string}> $tokens Token list.
	 * @return array<string,int>
	 */
	private static function signature_bag( array $tokens ): array {
		$bag = array();
		foreach ( $tokens as $token ) {
			$name        = (string) ( $token['name'] ?? '' );
			$arg         = array_key_exists( 'arg', $token ) && null !== $token['arg']
				? (string) $token['arg']
				: '';
			$key         = $name . "\0" . $arg;
			$bag[ $key ] = ( $bag[ $key ] ?? 0 ) + 1;
		}
		ksort( $bag );
		return $bag;
	}
}
