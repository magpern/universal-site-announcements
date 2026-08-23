<?php
/**
 * EligibilityGate unit tests.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Provider\EligibilityGate;

/**
 * Allowlist / foreign callback / escape hatch.
 */
final class EligibilityGateTest extends TestCase {

	/**
	 * Reset filter stubs between tests.
	 */
	protected function tearDown(): void {
		$GLOBALS['usa_test_filters'] = array();
		unset( $GLOBALS['wp_filter'][ EligibilityGate::HOOK ] );
		parent::tearDown();
	}

	/**
	 * Allowlisted UMC callback identity.
	 */
	public function test_allowlisted_identity(): void {
		$gate = new EligibilityGate();
		$this->assertTrue(
			$gate->is_allowlisted( EligibilityGate::ALLOWLISTED_CALLBACK )
		);
		$this->assertFalse(
			$gate->is_allowlisted( 'Some\\Other\\Plugin::filter_free_shipping' )
		);
	}

	/**
	 * callback_identity for object method arrays.
	 */
	public function test_callback_identity_object_method(): void {
		$obj = new class() {
			/**
			 * Dummy.
			 */
			public function filter_free_shipping_availability(): bool {
				return true;
			}
		};
		// Simulate UMC class name via a named stub is hard anonymously; test shape.
		$identity = EligibilityGate::callback_identity( array( $obj, 'filter_free_shipping_availability' ) );
		$this->assertStringEndsWith( '::filter_free_shipping_availability', $identity );
	}

	/**
	 * Foreign callbacks are reported.
	 */
	public function test_foreign_callbacks_detection(): void {
		$gate       = new EligibilityGate();
		$identities = array(
			EligibilityGate::ALLOWLISTED_CALLBACK,
			'Evil\\Plugin::hijack',
		);
		$foreign = $gate->foreign_callbacks( $identities );
		$this->assertSame( array( 'Evil\\Plugin::hijack' ), $foreign );
	}

	/**
	 * Empty inventory passes (no foreign callbacks).
	 */
	public function test_empty_inventory_passes(): void {
		$gate = new EligibilityGate();
		$this->assertTrue( $gate->passes() );
	}

	/**
	 * Escape hatch filter forces pass even with foreign callbacks inventoried via stub.
	 */
	public function test_escape_hatch_overrides(): void {
		$GLOBALS['usa_test_filters']['usa_free_shipping_provider_eligibility_verified'] = static function () {
			return true;
		};

		// Plant a foreign callback in wp_filter.
		$GLOBALS['wp_filter'][ EligibilityGate::HOOK ] = array(
			10 => array(
				array(
					'function' => array( 'Evil\\Plugin', 'hijack' ),
				),
			),
		);

		$gate = new EligibilityGate();
		$this->assertTrue( $gate->passes() );
	}

	/**
	 * Foreign callback without escape hatch fails.
	 */
	public function test_foreign_callback_fails_gate(): void {
		$GLOBALS['wp_filter'][ EligibilityGate::HOOK ] = array(
			10 => array(
				array(
					'function' => array( 'Evil\\Plugin', 'hijack' ),
				),
			),
		);

		$gate = new EligibilityGate();
		$this->assertFalse( $gate->passes() );
		$this->assertStringContainsString( 'non-allowlisted callback', $gate->failure_reason() );
		$this->assertStringContainsString( EligibilityGate::HOOK, $gate->failure_reason() );
	}

	/**
	 * Allowlisted-only inventory passes.
	 */
	public function test_allowlisted_only_passes(): void {
		$GLOBALS['wp_filter'][ EligibilityGate::HOOK ] = array(
			10 => array(
				array(
					'function' => array(
						'UMC\\Integration\\ShippingConversion',
						'filter_free_shipping_availability',
					),
				),
			),
		);

		$gate = new EligibilityGate();
		$this->assertTrue( $gate->passes() );
		$this->assertSame( '', $gate->failure_reason() );
	}
}
