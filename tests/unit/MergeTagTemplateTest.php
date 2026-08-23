<?php
/**
 * Merge-tag parser and HTML placement tests.
 *
 * @package UniversalSiteAnnouncements
 */

declare(strict_types=1);

namespace USA\Tests\Unit;

use PHPUnit\Framework\TestCase;
use USA\Template\HtmlPlacementValidator;
use USA\Template\MergeTagParser;
use USA\Template\SourceTokenRules;

/**
 * Parser grammar and placement validation.
 */
final class MergeTagTemplateTest extends TestCase {

	/**
	 * Valid tokens parse.
	 */
	public function test_parser_valid_tokens(): void {
		$parser = new MergeTagParser();
		$result = $parser->parse( 'Hello {{product:12}} and {{free_shipping_threshold}}' );
		$this->assertTrue( $result['ok'] );
		$this->assertCount( 2, $result['tokens'] );
		$this->assertSame( 'product', $result['tokens'][0]->name );
		$this->assertSame( '12', $result['tokens'][0]->arg );
		$this->assertSame( 'free_shipping_threshold', $result['tokens'][1]->name );
		$this->assertNull( $result['tokens'][1]->arg );
	}

	/**
	 * Unmatched opener suppresses.
	 */
	public function test_parser_unmatched_opener(): void {
		$parser = new MergeTagParser();
		$result = $parser->parse( 'Bad {{product:1' );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'malformed_merge_tags', $result['reason'] );
	}

	/**
	 * Unmatched closer suppresses.
	 */
	public function test_parser_unmatched_closer(): void {
		$parser = new MergeTagParser();
		$result = $parser->parse( 'Bad }} leftover' );
		$this->assertFalse( $result['ok'] );
	}

	/**
	 * Invalid complete expression suppresses.
	 */
	public function test_parser_invalid_complete_expression(): void {
		$parser = new MergeTagParser();
		$result = $parser->parse( 'Hello {{Product:1}}' );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'malformed_merge_tags', $result['reason'] );
	}

	/**
	 * Leading-zero product arg is invalid grammar.
	 */
	public function test_parser_rejects_leading_zero_arg(): void {
		$parser = new MergeTagParser();
		$result = $parser->parse( '{{product:01}}' );
		$this->assertFalse( $result['ok'] );
	}

	/**
	 * Tokens in attributes are rejected.
	 */
	public function test_attribute_token_rejection(): void {
		$parser    = new MergeTagParser();
		$placement = new HtmlPlacementValidator();
		$html      = '<a href="{{product:1}}">x</a>';
		$parsed    = $parser->parse( $html );
		$this->assertTrue( $parsed['ok'] );
		$this->assertSame( 'token_in_attribute', $placement->validate( $html, $parsed['tokens'] ) );
	}

	/**
	 * Product token inside existing &lt;a&gt; is rejected.
	 */
	public function test_product_inside_anchor_rejection(): void {
		$parser    = new MergeTagParser();
		$placement = new HtmlPlacementValidator();
		$html      = '<a href="https://example.test/p">Buy {{product:5}}</a>';
		$parsed    = $parser->parse( $html );
		$this->assertTrue( $parsed['ok'] );
		$this->assertSame( 'product_token_inside_anchor', $placement->validate( $html, $parsed['tokens'] ) );
	}

	/**
	 * Tokens inside &lt;strong&gt; are allowed.
	 */
	public function test_tokens_in_strong_ok(): void {
		$parser    = new MergeTagParser();
		$placement = new HtmlPlacementValidator();
		$html      = 'Orders of <strong>{{free_shipping_threshold}}</strong> or more';
		$parsed    = $parser->parse( $html );
		$this->assertTrue( $parsed['ok'] );
		$this->assertNull( $placement->validate( $html, $parsed['tokens'] ) );
	}

	/**
	 * Product inside strong (not anchor) is OK.
	 */
	public function test_product_in_strong_ok(): void {
		$parser    = new MergeTagParser();
		$placement = new HtmlPlacementValidator();
		$html      = 'Buy <em>{{product:9}}</em> today';
		$parsed    = $parser->parse( $html );
		$this->assertTrue( $parsed['ok'] );
		$this->assertNull( $placement->validate( $html, $parsed['tokens'] ) );
	}

	/**
	 * Source allowlists.
	 */
	public function test_source_token_allowlists(): void {
		$rules  = new SourceTokenRules();
		$parser = new MergeTagParser();

		$manual = $parser->parse( 'Hi {{product:1}}' );
		$this->assertNull( $rules->validate( 'manual', $manual['tokens'] ) );

		$forbidden = $parser->parse( 'Ship {{free_shipping_threshold}}' );
		$this->assertSame( 'free_shipping_token_forbidden', $rules->validate( 'manual', $forbidden['tokens'] ) );

		$fs_ok = $parser->parse( 'Ship {{free_shipping_threshold}} and {{product:2}}' );
		$this->assertNull( $rules->validate( 'woocommerce_free_shipping', $fs_ok['tokens'] ) );

		$fs_dup = $parser->parse( '{{free_shipping_threshold}} {{free_shipping_threshold}}' );
		$this->assertSame( 'free_shipping_token_count', $rules->validate( 'woocommerce_free_shipping', $fs_dup['tokens'] ) );

		$fs_none = $parser->parse( 'No token here' );
		$this->assertSame( 'free_shipping_token_count', $rules->validate( 'woocommerce_free_shipping', $fs_none['tokens'] ) );

		$dup_product = $parser->parse( '{{free_shipping_threshold}} {{product:1}} {{product:1}}' );
		$this->assertNull( $rules->validate( 'woocommerce_free_shipping', $dup_product['tokens'] ) );
	}
}
