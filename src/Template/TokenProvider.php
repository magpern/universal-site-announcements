<?php
/**
 * Token provider contract.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Template;

/**
 * Resolves a single merge-tag name (+ optional arg) to an HTML fragment.
 */
interface TokenProvider {

	/**
	 * Whether this provider handles the token.
	 *
	 * @param string      $name Token name.
	 * @param string|null $arg  Optional numeric argument.
	 */
	public function supports( string $name, ?string $arg ): bool;

	/**
	 * Resolve to a safe HTML fragment, or null on failure.
	 *
	 * @param string               $name    Token name.
	 * @param string|null          $arg     Optional argument.
	 * @param array<string, mixed> $context Render context (e.g. base_threshold).
	 */
	public function resolve( string $name, ?string $arg, array $context ): ?string;
}
