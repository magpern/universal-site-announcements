<?php
/**
 * Optional AIML Integration adapter for announcement body overlays.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Integration;

use AIMultilingual\Integration\ChromeOwnedSurfaceDeclaration;
use AIMultilingual\Integration\CompatibilityStatus;
use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\DeclaresChromeOwnedSurfaces;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\PluginIntegrationInterface;
use AIMultilingual\Integration\TranslationUnitDescriptor;
use USA\Announcement\PostType;
use WP_Post;

/**
 * Registers USA chrome surface and extracts announcement body templates.
 */
final class AimlIntegration implements PluginIntegrationInterface, DeclaresChromeOwnedSurfaces {

	public const INTEGRATION_ID = 'universal_site_announcements';

	public const OWNER_TYPE = 'announcement';

	public const FIELD_BODY = 'body';

	/**
	 * Identity serializer.
	 *
	 * @var PluginIdentity
	 */
	private PluginIdentity $identity;

	/**
	 * Compatibility probe.
	 *
	 * @var AimlCompatibility
	 */
	private AimlCompatibility $compatibility;

	/**
	 * Constructor.
	 *
	 * @param PluginIdentity    $identity       Identity serializer.
	 * @param AimlCompatibility $compatibility Compatibility probe.
	 */
	public function __construct( PluginIdentity $identity, AimlCompatibility $compatibility ) {
		$this->identity       = $identity;
		$this->compatibility = $compatibility;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return self::INTEGRATION_ID;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_api_version(): string {
		return Contract::API_VERSION;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_compatibility(): CompatibilityStatus {
		if ( ! $this->compatibility->is_compatible() ) {
			return new CompatibilityStatus( Contract::STATE_UNSUPPORTED_VERSION, 'aiml_probe_failed' );
		}

		return new CompatibilityStatus( Contract::STATE_COMPATIBLE, 'ok' );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return list<ChromeOwnedSurfaceDeclaration>
	 */
	public function get_chrome_owned_surfaces(): array {
		return array(
			new ChromeOwnedSurfaceDeclaration(
				PostType::POST_TYPE,
				array( self::OWNER_TYPE ),
				array( self::FIELD_BODY ),
				ChromeOwnedSurfaceDeclaration::EXTRACTION_INTEGRATION_UNITS_ONLY
			),
		);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return list<TranslationUnitDescriptor>
	 */
	public function extract_for_post( WP_Post $post ): array {
		if ( PostType::POST_TYPE !== $post->post_type ) {
			return array();
		}

		if ( ! $this->get_compatibility()->allows_operation() ) {
			return array();
		}

		$body = (string) $post->post_content;
		if ( '' === trim( $body ) ) {
			return array();
		}

		$owner_id = (string) (int) $post->ID;
		$key      = $this->identity->build(
			self::INTEGRATION_ID,
			self::OWNER_TYPE,
			$owner_id,
			self::FIELD_BODY
		);

		return array(
			TranslationUnitDescriptor::from_source(
				$key,
				$body,
				Contract::FORMAT_HTML,
				Contract::OWNERSHIP_RECORD,
				self::OWNER_TYPE,
				$owner_id,
				self::FIELD_BODY,
				__( 'Announcement body', 'universal-site-announcements' ),
				self::INTEGRATION_ID,
				''
			),
		);
	}

	/**
	 * Chrome uses Extension resolver — not host-bound output hooks.
	 *
	 * {@inheritdoc}
	 */
	public function register_output_hooks( callable $resolve ): void {
		unset( $resolve );
	}
}
