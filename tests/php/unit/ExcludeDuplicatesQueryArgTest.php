<?php

namespace Outstand\WP\QueryLoop\Dedup\Tests\Unit;

use Outstand\WP\QueryLoop\Dedup\Deduplicator;
use WP_Query;
use function Outstand\WP\QueryLoop\Dedup\Tests\create_posts;

/**
 * The `exclude_duplicates` WP_Query arg — public PHP API.
 *
 * @covers \Outstand\WP\QueryLoop\Dedup\QueryDeduplication::handle_exclude_duplicates_query_arg
 */
class ExcludeDuplicatesQueryArgTest extends \WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Deduplicator::clear();
	}

	protected function tearDown(): void {
		Deduplicator::clear();
		parent::tearDown();
	}

	public function test_query_arg_excludes_tracked_ids(): void {
		$ids = create_posts( 3 );
		Deduplicator::add( $ids[0], $ids[1] );

		$query = new WP_Query(
			[
				'post_type'          => 'post',
				'posts_per_page'     => -1,
				'exclude_duplicates' => true,
				'orderby'            => 'ID',
				'order'              => 'ASC',
			]
		);

		$returned = wp_list_pluck( $query->posts, 'ID' );
		$this->assertSame( [ $ids[2] ], $returned );
	}

	public function test_query_arg_noop_when_registry_empty(): void {
		$ids = create_posts( 2 );

		$query = new WP_Query(
			[
				'post_type'          => 'post',
				'posts_per_page'     => -1,
				'exclude_duplicates' => true,
				'orderby'            => 'ID',
				'order'              => 'ASC',
			]
		);

		$returned = wp_list_pluck( $query->posts, 'ID' );
		$this->assertEqualsCanonicalizing( $ids, $returned );
	}

	public function test_query_arg_merges_with_existing_post_not_in(): void {
		$ids = create_posts( 4 );
		Deduplicator::add( $ids[0] );

		$query = new WP_Query(
			[
				'post_type'          => 'post',
				'posts_per_page'     => -1,
				'exclude_duplicates' => true,
				'post__not_in'       => [ $ids[1] ],
				'orderby'            => 'ID',
				'order'              => 'ASC',
			]
		);

		$returned = wp_list_pluck( $query->posts, 'ID' );
		$this->assertEqualsCanonicalizing( [ $ids[2], $ids[3] ], $returned );
	}

	public function test_query_arg_noop_when_flag_absent(): void {
		$ids = create_posts( 2 );
		Deduplicator::add( $ids[0] );

		$query = new WP_Query(
			[
				'post_type'      => 'post',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
		);

		$returned = wp_list_pluck( $query->posts, 'ID' );
		$this->assertEqualsCanonicalizing( $ids, $returned );
	}
}
