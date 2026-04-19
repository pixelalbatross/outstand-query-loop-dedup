<?php

namespace Outstand\WP\QueryLoop\Dedup\Tests\Unit;

/**
 * Detection of page-wide tracking via the_content + pre_render_block.
 *
 * @covers \Outstand\WP\QueryLoop\Dedup\QueryDeduplication::detect_tracking
 * @covers \Outstand\WP\QueryLoop\Dedup\QueryDeduplication::detect_tracking_in_template
 */
class TrackingDetectionTest extends QueryDeduplicationTestCase {

	public function test_detect_tracking_no_flag_in_content(): void {
		$content = '<!-- wp:query {"queryId":1,"query":{}} --><!-- /wp:query -->';
		$this->dedup->detect_tracking( $content );
		$this->assertFalse( $this->get_tracking_enabled() );
	}

	public function test_detect_tracking_flag_in_content(): void {
		$content = '<!-- wp:query {"queryId":1,"query":{},"excludeDuplicates":true} --><!-- /wp:query -->';
		$this->dedup->detect_tracking( $content );
		$this->assertTrue( $this->get_tracking_enabled() );
	}

	public function test_detect_tracking_flag_nested_inner_block(): void {
		$content = '<!-- wp:group --><!-- wp:query {"excludeDuplicates":true} --><!-- /wp:query --><!-- /wp:group -->';
		$this->dedup->detect_tracking( $content );
		$this->assertTrue( $this->get_tracking_enabled() );
	}

	public function test_detect_tracking_string_optimization_skips_parse(): void {
		// No `"excludeDuplicates":true` substring → short-circuits without parsing.
		$content = '<!-- wp:paragraph --><p>nothing</p><!-- /wp:paragraph -->';
		$this->dedup->detect_tracking( $content );
		$this->assertFalse( $this->get_tracking_enabled() );
	}

	public function test_detect_tracking_in_template_non_query_block(): void {
		$parsed = [
			'blockName' => 'core/paragraph',
			'attrs'     => [],
		];
		$this->dedup->detect_tracking_in_template( null, $parsed );
		$this->assertFalse( $this->get_tracking_enabled() );
	}

	public function test_detect_tracking_in_template_flagged_query(): void {
		$parsed = [
			'blockName' => 'core/query',
			'attrs'     => [ 'excludeDuplicates' => true ],
		];
		$this->dedup->detect_tracking_in_template( null, $parsed );
		$this->assertTrue( $this->get_tracking_enabled() );
	}

	public function test_detect_tracking_in_template_unflagged_query(): void {
		$parsed = [
			'blockName' => 'core/query',
			'attrs'     => [],
		];
		$this->dedup->detect_tracking_in_template( null, $parsed );
		$this->assertFalse( $this->get_tracking_enabled() );
	}

	public function test_detect_tracking_is_sticky_once_enabled(): void {
		$this->dedup->detect_tracking( '<!-- wp:query {"excludeDuplicates":true} --><!-- /wp:query -->' );
		$this->assertTrue( $this->get_tracking_enabled() );

		// Subsequent call with no-flag content should not flip it off.
		$this->dedup->detect_tracking( '<p>nothing</p>' );
		$this->assertTrue( $this->get_tracking_enabled() );
	}

	private function get_tracking_enabled(): bool {
		$ref = new \ReflectionProperty( $this->dedup, 'tracking_enabled' );
		$ref->setAccessible( true );
		return (bool) $ref->getValue( $this->dedup );
	}
}
