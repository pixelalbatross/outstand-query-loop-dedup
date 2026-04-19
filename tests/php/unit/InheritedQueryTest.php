<?php

namespace Outstand\WP\QueryLoop\Dedup\Tests\Unit;

use Outstand\WP\QueryLoop\Dedup\Deduplicator;
use WP_Query;
use function Outstand\WP\QueryLoop\Dedup\Tests\make_block;

/**
 * Inherited (main-query) flow: backup/filter/restore of $wp_query.
 *
 * @covers \Outstand\WP\QueryLoop\Dedup\QueryDeduplication::maybe_filter_inherited_query
 * @covers \Outstand\WP\QueryLoop\Dedup\QueryDeduplication::maybe_restore_inherited_query
 */
class InheritedQueryTest extends QueryDeduplicationTestCase {

	public function test_filter_inherited_query_removes_tracked_posts(): void {
		Deduplicator::add( 2 );

		$GLOBALS['wp_query']                = new WP_Query();
		$GLOBALS['wp_query']->posts         = [
			(object) [ 'ID' => 1 ],
			(object) [ 'ID' => 2 ],
			(object) [ 'ID' => 3 ],
		];
		$GLOBALS['wp_query']->post_count    = 3;
		$GLOBALS['wp_query']->found_posts   = 3;
		$GLOBALS['wp_query']->max_num_pages = 1;
		$GLOBALS['wp_query']->set( 'posts_per_page', 3 );

		$parent = make_block(
			'core/query',
			[
				'queryId'           => 7,
				'query'             => [ 'inherit' => true ],
				'excludeDuplicates' => true,
			]
		);

		$parsed = [
			'blockName' => 'core/post-template',
			'attrs'     => [],
		];
		$this->dedup->maybe_filter_inherited_query( null, $parsed, $parent );

		$ids = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
		$this->assertSame( [ 1, 3 ], $ids );
		$this->assertSame( 2, $GLOBALS['wp_query']->post_count );
		$this->assertSame( 2, $GLOBALS['wp_query']->found_posts );
		$this->assertSame( 1, $GLOBALS['wp_query']->max_num_pages );
	}

	public function test_filter_noop_when_not_inherited(): void {
		Deduplicator::add( 2 );

		$GLOBALS['wp_query']             = new WP_Query();
		$GLOBALS['wp_query']->posts      = [ (object) [ 'ID' => 1 ], (object) [ 'ID' => 2 ] ];
		$GLOBALS['wp_query']->post_count = 2;

		$parent = make_block(
			'core/query',
			[
				'queryId'           => 7,
				'query'             => [ 'inherit' => false ],
				'excludeDuplicates' => true,
			]
		);

		$parsed = [
			'blockName' => 'core/post-template',
			'attrs'     => [],
		];
		$this->dedup->maybe_filter_inherited_query( null, $parsed, $parent );

		$this->assertCount( 2, $GLOBALS['wp_query']->posts );
	}

	public function test_filter_noop_when_flag_off(): void {
		Deduplicator::add( 2 );

		$GLOBALS['wp_query']             = new WP_Query();
		$GLOBALS['wp_query']->posts      = [ (object) [ 'ID' => 1 ], (object) [ 'ID' => 2 ] ];
		$GLOBALS['wp_query']->post_count = 2;

		$parent = make_block(
			'core/query',
			[
				'queryId'           => 7,
				'query'             => [ 'inherit' => true ],
				'excludeDuplicates' => false,
			]
		);

		$parsed = [
			'blockName' => 'core/post-template',
			'attrs'     => [],
		];
		$this->dedup->maybe_filter_inherited_query( null, $parsed, $parent );

		$this->assertCount( 2, $GLOBALS['wp_query']->posts );
	}

	public function test_filter_noop_when_registry_empty(): void {
		$GLOBALS['wp_query']             = new WP_Query();
		$GLOBALS['wp_query']->posts      = [ (object) [ 'ID' => 1 ], (object) [ 'ID' => 2 ] ];
		$GLOBALS['wp_query']->post_count = 2;

		$parent = make_block(
			'core/query',
			[
				'queryId'           => 7,
				'query'             => [ 'inherit' => true ],
				'excludeDuplicates' => true,
			]
		);

		$parsed = [
			'blockName' => 'core/post-template',
			'attrs'     => [],
		];
		$this->dedup->maybe_filter_inherited_query( null, $parsed, $parent );

		$this->assertCount( 2, $GLOBALS['wp_query']->posts );
	}

	public function test_restore_after_filter_returns_original_state(): void {
		Deduplicator::add( 2 );

		$original_posts = [
			(object) [ 'ID' => 1 ],
			(object) [ 'ID' => 2 ],
			(object) [ 'ID' => 3 ],
		];

		$GLOBALS['wp_query']                = new WP_Query();
		$GLOBALS['wp_query']->posts         = $original_posts;
		$GLOBALS['wp_query']->post_count    = 3;
		$GLOBALS['wp_query']->found_posts   = 3;
		$GLOBALS['wp_query']->max_num_pages = 1;
		$GLOBALS['wp_query']->current_post  = -1;
		$GLOBALS['wp_query']->set( 'posts_per_page', 3 );

		$parent = make_block(
			'core/query',
			[
				'queryId'           => 7,
				'query'             => [ 'inherit' => true ],
				'excludeDuplicates' => true,
			]
		);

		$parsed = [
			'blockName' => 'core/post-template',
			'attrs'     => [],
		];
		$this->dedup->maybe_filter_inherited_query( null, $parsed, $parent );

		$this->assertCount( 2, $GLOBALS['wp_query']->posts );

		$post_template = make_block(
			'core/post-template',
			[],
			[ 'queryId' => 7 ]
		);
		$this->dedup->maybe_restore_inherited_query( '', $parsed, $post_template );

		$this->assertSame( $original_posts, $GLOBALS['wp_query']->posts );
		$this->assertSame( 3, $GLOBALS['wp_query']->post_count );
		$this->assertSame( 3, $GLOBALS['wp_query']->found_posts );
		$this->assertSame( 1, $GLOBALS['wp_query']->max_num_pages );
		$this->assertSame( -1, $GLOBALS['wp_query']->current_post );
	}

	public function test_restore_mismatched_query_id_is_noop(): void {
		Deduplicator::add( 2 );

		$GLOBALS['wp_query']             = new WP_Query();
		$GLOBALS['wp_query']->posts      = [
			(object) [ 'ID' => 1 ],
			(object) [ 'ID' => 2 ],
		];
		$GLOBALS['wp_query']->post_count = 2;
		$GLOBALS['wp_query']->set( 'posts_per_page', 2 );

		$parent = make_block(
			'core/query',
			[
				'queryId'           => 7,
				'query'             => [ 'inherit' => true ],
				'excludeDuplicates' => true,
			]
		);

		$parsed = [
			'blockName' => 'core/post-template',
			'attrs'     => [],
		];
		$this->dedup->maybe_filter_inherited_query( null, $parsed, $parent );

		$post_count_after_filter = $GLOBALS['wp_query']->post_count;

		$other = make_block(
			'core/post-template',
			[],
			[ 'queryId' => 99 ]
		);
		$this->dedup->maybe_restore_inherited_query( '', $parsed, $other );

		// No restore because queryId mismatched.
		$this->assertSame( $post_count_after_filter, $GLOBALS['wp_query']->post_count );
	}
}
