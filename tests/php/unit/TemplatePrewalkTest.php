<?php

namespace Outstand\WP\QueryLoop\Dedup\Tests\Unit;

/**
 * FSE template pre-walk including template-part resolution.
 *
 * @covers \Outstand\WP\QueryLoop\Dedup\QueryDeduplication::prewalk_template
 */
class TemplatePrewalkTest extends QueryDeduplicationTestCase {

	protected function tearDown(): void {
		$GLOBALS['_wp_current_template_content'] = '';
		parent::tearDown();
	}

	public function test_prewalk_no_template_content_is_noop(): void {
		$GLOBALS['_wp_current_template_content'] = '';
		$this->dedup->prewalk_template( '/tmp/template.php' );
		$this->assertFalse( $this->get_tracking_enabled() );
	}

	public function test_prewalk_detects_flagged_query_in_template(): void {
		$GLOBALS['_wp_current_template_content'] = '<!-- wp:query {"excludeDuplicates":true} --><!-- /wp:query -->';
		$this->dedup->prewalk_template( '/tmp/template.php' );
		$this->assertTrue( $this->get_tracking_enabled() );
	}

	public function test_prewalk_ignores_plain_template(): void {
		$GLOBALS['_wp_current_template_content'] = '<!-- wp:paragraph --><p>no queries</p><!-- /wp:paragraph -->';
		$this->dedup->prewalk_template( '/tmp/template.php' );
		$this->assertFalse( $this->get_tracking_enabled() );
	}

	public function test_prewalk_ignores_unflagged_query(): void {
		$GLOBALS['_wp_current_template_content'] = '<!-- wp:query {"queryId":1} --><!-- /wp:query -->';
		$this->dedup->prewalk_template( '/tmp/template.php' );
		$this->assertFalse( $this->get_tracking_enabled() );
	}

	public function test_prewalk_returns_template_path_unchanged(): void {
		$GLOBALS['_wp_current_template_content'] = '<!-- wp:query {"excludeDuplicates":true} --><!-- /wp:query -->';
		$result                                  = $this->dedup->prewalk_template( '/tmp/template.php' );
		$this->assertSame( '/tmp/template.php', $result );
	}

	private function get_tracking_enabled(): bool {
		$ref = new \ReflectionProperty( $this->dedup, 'tracking_enabled' );
		$ref->setAccessible( true );
		return (bool) $ref->getValue( $this->dedup );
	}
}
