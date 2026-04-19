<?php

namespace Outstand\WP\QueryLoop\Dedup\Tests\Unit;

use Outstand\WP\QueryLoop\Dedup\BlockAttributes;
use WP_Block_Type_Registry;

/**
 * @covers \Outstand\WP\QueryLoop\Dedup\BlockAttributes
 */
class BlockAttributesTest extends \WP_UnitTestCase {

	public function test_core_query_has_exclude_duplicates_attribute(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'core/query' );
		$this->assertNotNull( $type );
		$this->assertArrayHasKey( 'excludeDuplicates', $type->attributes );
		$this->assertSame( 'boolean', $type->attributes['excludeDuplicates']['type'] );
		$this->assertFalse( $type->attributes['excludeDuplicates']['default'] );
	}

	public function test_core_query_provides_dedup_context(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'core/query' );
		$this->assertNotNull( $type );
		$this->assertArrayHasKey( BlockAttributes::CONTEXT_KEY, $type->provides_context );
		$this->assertSame( 'excludeDuplicates', $type->provides_context[ BlockAttributes::CONTEXT_KEY ] );
	}

	public function test_post_template_uses_dedup_context(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'core/post-template' );
		$this->assertNotNull( $type );
		$this->assertContains( BlockAttributes::CONTEXT_KEY, (array) $type->uses_context );
	}

	public function test_context_key_format(): void {
		$this->assertSame( 'outstand/excludeDuplicates', BlockAttributes::CONTEXT_KEY );
	}
}
