<?php
/**
 * FixedRowStyle validation, resolution, and CSS custom-property output.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Announcement\FixedRowStyle;

/**
 * @covers \USA\Announcement\FixedRowStyle
 */
final class FixedRowStyleTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['usa_test_post_meta'] = array();
	}

	protected function tearDown(): void {
		$GLOBALS['usa_test_post_meta'] = array();
	}

	public function test_sanitize_hex_accepts_valid_hex(): void {
		$this->assertSame( '#a1b2c3', FixedRowStyle::sanitize_hex( '#a1b2c3' ) );
		$this->assertSame( '#FFF', FixedRowStyle::sanitize_hex( '#FFF' ) );
	}

	public function test_sanitize_hex_rejects_absent_or_blank(): void {
		$this->assertNull( FixedRowStyle::sanitize_hex( null ) );
		$this->assertNull( FixedRowStyle::sanitize_hex( '' ) );
		$this->assertNull( FixedRowStyle::sanitize_hex( '   ' ) );
	}

	/**
	 * @dataProvider provide_malformed_values
	 */
	public function test_sanitize_hex_rejects_malformed_values( string $value ): void {
		$this->assertNull( FixedRowStyle::sanitize_hex( $value ) );
	}

	/**
	 * @return list<list<string>>
	 */
	public function provide_malformed_values(): array {
		return array(
			array( 'red' ),
			array( 'rgb(1,2,3)' ),
			array( 'rgba(1,2,3,0.5)' ),
			array( 'hsl(0,0%,0%)' ),
			array( 'a1b2c3' ), // missing '#'.
			array( '#12' ), // too short.
			array( '#1234' ), // 4 digits, not 3 or 6.
			array( '#gggggg' ), // non-hex characters.
		);
	}

	/**
	 * @dataProvider provide_injection_payloads
	 */
	public function test_sanitize_hex_rejects_css_injection_payloads( string $value ): void {
		$this->assertNull( FixedRowStyle::sanitize_hex( $value ) );
	}

	/**
	 * @return list<list<string>>
	 */
	public function provide_injection_payloads(): array {
		return array(
			array( 'red;}body{display:none' ),
			array( '#fff</style><script>alert(1)</script>' ),
			array( 'javascript:alert(1)' ),
			array( '#fff;--x:expression(alert(1))' ),
			array( '#fff" onmouseover="alert(1)' ),
		);
	}

	public function test_normalize_accepts_valid_hex_all_fields(): void {
		$result = FixedRowStyle::normalize(
			array(
				'bg'     => '#111111',
				'fg'     => '#222222',
				'link'   => '#333333',
				'border' => '#444444',
			)
		);

		$this->assertSame(
			array(
				'bg'     => '#111111',
				'fg'     => '#222222',
				'link'   => '#333333',
				'border' => '#444444',
			),
			$result
		);
	}

	public function test_normalize_missing_fields_resolve_to_null(): void {
		$this->assertSame(
			array(
				'bg'     => null,
				'fg'     => null,
				'link'   => null,
				'border' => null,
			),
			FixedRowStyle::normalize( array() )
		);
	}

	public function test_normalize_invalid_field_resolves_to_null_others_kept(): void {
		$result = FixedRowStyle::normalize(
			array(
				'bg'   => '#111111',
				'fg'   => 'not-a-colour',
				'link' => '',
			)
		);

		$this->assertSame( '#111111', $result['bg'] );
		$this->assertNull( $result['fg'] );
		$this->assertNull( $result['link'] );
		$this->assertNull( $result['border'] );
	}

	public function test_resolve_defaults_to_all_null_when_meta_absent(): void {
		$this->assertSame(
			array(
				'bg'     => null,
				'fg'     => null,
				'link'   => null,
				'border' => null,
			),
			FixedRowStyle::resolve( 42 )
		);
	}

	public function test_resolve_reads_all_four_meta_keys(): void {
		update_post_meta( 7, FixedRowStyle::META_BG, '#ffffff' );
		update_post_meta( 7, FixedRowStyle::META_FG, '#000000' );
		update_post_meta( 7, FixedRowStyle::META_LINK, '#0000ff' );
		update_post_meta( 7, FixedRowStyle::META_BORDER, '#cccccc' );

		$this->assertSame(
			array(
				'bg'     => '#ffffff',
				'fg'     => '#000000',
				'link'   => '#0000ff',
				'border' => '#cccccc',
			),
			FixedRowStyle::resolve( 7 )
		);
	}

	public function test_resolve_ignores_tampered_meta_values(): void {
		// Simulate a value written directly to the DB, bypassing normalize().
		update_post_meta( 9, FixedRowStyle::META_BG, 'red;}body{display:none' );
		update_post_meta( 9, FixedRowStyle::META_FG, '#ok0000' ); // invalid: 7 hex digits.

		$result = FixedRowStyle::resolve( 9 );
		$this->assertNull( $result['bg'] );
		$this->assertNull( $result['fg'] );
	}

	public function test_has_any_true_when_one_field_set(): void {
		$this->assertTrue(
			FixedRowStyle::has_any(
				array(
					'bg'     => '#111111',
					'fg'     => null,
					'link'   => null,
					'border' => null,
				)
			)
		);
	}

	public function test_has_any_false_when_all_null(): void {
		$this->assertFalse(
			FixedRowStyle::has_any(
				array(
					'bg'     => null,
					'fg'     => null,
					'link'   => null,
					'border' => null,
				)
			)
		);
	}

	public function test_persist_writes_set_fields_and_deletes_absent_fields(): void {
		update_post_meta( 3, FixedRowStyle::META_LINK, '#stale00' ); // pre-existing meta to be cleared.

		FixedRowStyle::persist(
			3,
			array(
				'bg'     => '#101010',
				'fg'     => null,
				'link'   => null,
				'border' => '#202020',
			)
		);

		$this->assertSame( '#101010', get_post_meta( 3, FixedRowStyle::META_BG, true ) );
		$this->assertSame( '', get_post_meta( 3, FixedRowStyle::META_FG, true ) );
		$this->assertSame( '', get_post_meta( 3, FixedRowStyle::META_LINK, true ) );
		$this->assertSame( '#202020', get_post_meta( 3, FixedRowStyle::META_BORDER, true ) );
	}

	public function test_to_css_vars_emits_only_set_fields(): void {
		$css = FixedRowStyle::to_css_vars(
			array(
				'bg'     => '#111111',
				'fg'     => null,
				'link'   => '#333333',
				'border' => null,
			)
		);

		$this->assertSame( '--usa-fixed-bg:#111111;--usa-fixed-link:#333333;', $css );
	}

	public function test_to_css_vars_empty_when_nothing_set(): void {
		$this->assertSame(
			'',
			FixedRowStyle::to_css_vars(
				array(
					'bg'     => null,
					'fg'     => null,
					'link'   => null,
					'border' => null,
				)
			)
		);
	}
}
