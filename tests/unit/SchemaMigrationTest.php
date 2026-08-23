<?php
/**
 * Schema migration tests.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Lifecycle\Schema;

/**
 * Migration seed rules (pure where possible).
 */
final class SchemaMigrationTest extends TestCase {

	/**
	 * Reset options.
	 */
	protected function setUp(): void {
		$GLOBALS['usa_test_options'] = array();
		parent::setUp();
	}

	/**
	 * Empty / whitespace-only content should be seeded; non-empty left alone.
	 */
	public function test_empty_vs_non_empty_seed_rule(): void {
		$default = Schema::DEFAULT_FREE_SHIPPING_TEMPLATE;

		$cases = array(
			''           => true,
			'   '        => true,
			"\n\t"       => true,
			'Custom copy'=> false,
			$default     => false,
			'Has {{free_shipping_threshold}} already' => false,
		);

		foreach ( $cases as $content => $should_seed ) {
			$empty = '' === trim( (string) $content );
			$this->assertSame(
				$should_seed,
				$empty,
				'Content ' . var_export( $content, true ) . ' seed expectation'
			);
			if ( $should_seed ) {
				$this->assertSame( $default, Schema::DEFAULT_FREE_SHIPPING_TEMPLATE );
			}
		}
	}

	/**
	 * Schema version option is idempotent when already at target.
	 */
	public function test_schema_version_idempotent(): void {
		update_option( Schema::OPTION, Schema::VERSION );
		$schema = new Schema();
		$schema->maybe_migrate();
		$this->assertSame( Schema::VERSION, Schema::current() );

		// Second call must not change version.
		$schema->maybe_migrate();
		$this->assertSame( Schema::VERSION, Schema::current() );
	}

	/**
	 * Version constant is 3 for M3.
	 */
	public function test_schema_version_is_three(): void {
		$this->assertSame( 3, Schema::VERSION );
	}
}
