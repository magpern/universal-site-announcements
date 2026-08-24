<?php
/**
 * AIML version + public feature probe for USA M5-B.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Integration;

/**
 * Gates AIML registration and runtime overlay on public symbols only.
 */
final class AimlCompatibility {

	public const MIN_AIML_VERSION = '1.7.0';

	/**
	 * Whether AIML is active, versioned, and exposes required public APIs.
	 */
	public function is_compatible(): bool {
		if ( ! defined( 'AIML_VERSION' ) ) {
			return false;
		}

		if ( version_compare( (string) AIML_VERSION, self::MIN_AIML_VERSION, '<' ) ) {
			return false;
		}

		$required_types = array(
			'AIMultilingual\\Integration\\DeclaresChromeOwnedSurfaces',
			'AIMultilingual\\Integration\\ChromeOwnedSurfaceDeclaration',
			'AIMultilingual\\Integration\\Contract',
			'AIMultilingual\\Integration\\TranslationUnitDescriptor',
			'AIMultilingual\\Integration\\PluginIntegrationInterface',
			'AIMultilingual\\Integration\\Identity\\PluginIdentity',
			'AIMultilingual\\Extension\\VisitorTranslationResolver',
			'AIMultilingual\\Extension\\VisitorLanguageContext',
			'AIMultilingual\\Extension\\SourceSegmentReference',
			'AIMultilingual\\Extension\\LanguageReference',
			'AIMultilingual\\Extension\\ResolvedTranslation',
			'AIMultilingual\\Extension\\ExtensionServices',
		);

		foreach ( $required_types as $type ) {
			if ( ! class_exists( $type ) && ! interface_exists( $type ) ) {
				return false;
			}
		}

		if ( ! function_exists( 'aiml_visitor_language' ) || ! function_exists( 'aiml_mark_source_dirty' ) ) {
			return false;
		}

		if ( ! method_exists( 'AIMultilingual\\Extension\\ExtensionServices', 'resolver' ) ) {
			return false;
		}

		if ( ! defined( 'AIMultilingual\\Integration\\Contract::FORMAT_HTML' )
			|| ! defined( 'AIMultilingual\\Integration\\Contract::FORMAT_PLAIN' )
		) {
			return false;
		}

		if ( ! method_exists( 'AIMultilingual\\Integration\\TranslationUnitDescriptor', 'from_source' ) ) {
			return false;
		}

		return true;
	}
}
