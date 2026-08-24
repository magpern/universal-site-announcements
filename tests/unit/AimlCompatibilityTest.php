<?php
/**
 * AimlCompatibility probe tests.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Integration\AimlCompatibility;

/**
 * @covers \USA\Integration\AimlCompatibility
 */
final class AimlCompatibilityTest extends TestCase {

	public function test_incompatible_when_aiml_absent(): void {
		$compat = new AimlCompatibility();
		$this->assertFalse( $compat->is_compatible() );
	}
}
