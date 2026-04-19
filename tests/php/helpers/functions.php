<?php
/**
 * Test helper functions.
 *
 * @package Outstand\WP\QueryLoop\Dedup\Tests
 */

namespace Outstand\WP\QueryLoop\Dedup\Tests;

use Outstand\WP\QueryLoop\Dedup\Deduplicator;
use WP_Block;

/**
 * Build a WP_Block instance from raw serialized-style inputs.
 *
 * @param string               $block_name Block name (e.g. 'core/post-template').
 * @param array                $attributes Block attributes.
 * @param array<string, mixed> $context    Block context (queryId, excludeDuplicates, etc.).
 * @param array<int, array>    $inner      Inner parsed blocks.
 *
 * @return WP_Block
 */
function make_block( string $block_name, array $attributes = [], array $context = [], array $inner = [] ): WP_Block {
	$parsed = [
		'blockName'    => $block_name,
		'attrs'        => $attributes,
		'innerBlocks'  => $inner,
		'innerHTML'    => '',
		'innerContent' => [],
	];

	return new WP_Block( $parsed, $context );
}

/**
 * Create N posts and return their IDs in creation order.
 *
 * @param int   $n    Number of posts.
 * @param array $args Additional wp_insert_post args.
 *
 * @return int[]
 */
function create_posts( int $n, array $args = [] ): array {
	$ids = [];

	for ( $i = 0; $i < $n; $i++ ) {
		$ids[] = wp_insert_post(
			array_merge(
				[
					'post_title'  => 'Post ' . ( $i + 1 ),
					'post_status' => 'publish',
					'post_type'   => 'post',
				],
				$args
			)
		);
	}

	return $ids;
}

/**
 * Reset the Deduplicator registry between tests.
 *
 * @return void
 */
function reset_dedup(): void {
	Deduplicator::clear();
}
