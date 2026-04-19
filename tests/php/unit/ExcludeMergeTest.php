<?php

namespace Outstand\WP\QueryLoop\Dedup\Tests\Unit;

use Outstand\WP\QueryLoop\Dedup\Deduplicator;
use function Outstand\WP\QueryLoop\Dedup\Tests\make_block;

/**
 * Merging of tracked IDs into post__not_in via query_loop_block_query_vars.
 *
 * @covers \Outstand\WP\QueryLoop\Dedup\QueryDeduplication::maybe_exclude_tracked_posts
 * @covers \Outstand\WP\QueryLoop\Dedup\QueryDeduplication::should_exclude
 */
class ExcludeMergeTest extends QueryDeduplicationTestCase {

	public function test_no_exclusion_when_flag_off(): void {
		Deduplicator::add( 10, 20 );

		$block = make_block(
			'core/post-template',
			[],
			[ 'queryId' => 1 ]
		);

		$query = $this->dedup->maybe_exclude_tracked_posts( [], $block );
		$this->assertArrayNotHasKey( 'post__not_in', $query );
	}

	public function test_exclusion_when_flag_on_via_context(): void {
		Deduplicator::add( 10, 20 );

		$block = make_block(
			'core/post-template',
			[],
			[
				'queryId'                    => 1,
				'outstand/excludeDuplicates' => true,
			]
		);

		$query = $this->dedup->maybe_exclude_tracked_posts( [], $block );
		$this->assertSame( [ 10, 20 ], $query['post__not_in'] );
	}

	public function test_exclusion_merges_existing_post_not_in(): void {
		Deduplicator::add( 10, 20 );

		$block = make_block(
			'core/post-template',
			[],
			[
				'queryId'                    => 1,
				'outstand/excludeDuplicates' => true,
			]
		);

		$query = $this->dedup->maybe_exclude_tracked_posts(
			[ 'post__not_in' => [ 5, 10 ] ],
			$block
		);

		$this->assertEqualsCanonicalizing( [ 5, 10, 20 ], $query['post__not_in'] );
	}

	public function test_no_exclusion_when_registry_empty(): void {
		$block = make_block(
			'core/post-template',
			[],
			[
				'queryId'                    => 1,
				'outstand/excludeDuplicates' => true,
			]
		);

		$query = $this->dedup->maybe_exclude_tracked_posts( [], $block );
		$this->assertArrayNotHasKey( 'post__not_in', $query );
	}

	public function test_should_exclude_filter_override(): void {
		Deduplicator::add( 10 );

		add_filter( 'outstand_query_loop_deduplication_should_exclude', '__return_false' );

		$block = make_block(
			'core/post-template',
			[],
			[
				'queryId'                    => 1,
				'outstand/excludeDuplicates' => true,
			]
		);

		$query = $this->dedup->maybe_exclude_tracked_posts( [], $block );
		$this->assertArrayNotHasKey( 'post__not_in', $query );

		remove_filter( 'outstand_query_loop_deduplication_should_exclude', '__return_false' );
	}

	public function test_should_exclude_filter_can_opt_in(): void {
		Deduplicator::add( 42 );

		add_filter( 'outstand_query_loop_deduplication_should_exclude', '__return_true' );

		$block = make_block( 'core/post-template', [], [ 'queryId' => 1 ] );

		$query = $this->dedup->maybe_exclude_tracked_posts( [], $block );
		$this->assertSame( [ 42 ], $query['post__not_in'] );

		remove_filter( 'outstand_query_loop_deduplication_should_exclude', '__return_true' );
	}
}
