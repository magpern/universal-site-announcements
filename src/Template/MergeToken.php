<?php
/**
 * Merge-tag token value object.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Template;

/**
 * A single successfully parsed merge tag occurrence.
 */
final class MergeToken {

	/**
	 * Token name (snake_case).
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * Optional numeric argument as string, or null.
	 *
	 * @var string|null
	 */
	public ?string $arg;

	/**
	 * Full matched literal including braces.
	 *
	 * @var string
	 */
	public string $raw;

	/**
	 * Byte offset of the match in the template.
	 *
	 * @var int
	 */
	public int $offset;

	/**
	 * Constructor.
	 *
	 * @param string      $name   Token name.
	 * @param string|null $arg    Optional argument.
	 * @param string      $raw    Full match.
	 * @param int         $offset Offset.
	 */
	public function __construct( string $name, ?string $arg, string $raw, int $offset ) {
		$this->name   = $name;
		$this->arg    = $arg;
		$this->raw    = $raw;
		$this->offset = $offset;
	}
}
