<?php

namespace Outstand\WP\QueryLoop\Dedup\Tests\Unit;

use Outstand\WP\QueryLoop\Dedup\Deduplicator;
use Outstand\WP\QueryLoop\Dedup\QueryDeduplication;
use function Outstand\WP\QueryLoop\Dedup\Tests\make_block;

/**
 * Custom-block resolvers registered via outstand_query_loop_deduplication_resolvers.
 *
 * @covers \Outstand\WP\QueryLoop\Dedup\QueryDeduplication::load_resolvers
 */
class ResolversTest extends QueryDeduplicationTestCase {

	public function test_resolver_adds_single_id_to_registry(): void {
		add_filter(
			'outstand_query_loop_deduplication_resolvers',
			static fn( array $r ): array => $r + [
				'acme/card' => static fn( array $parsed ): int => (int) ( $parsed['attrs']['postId'] ?? 0 ),
			]
		);

		// Re-run load_resolvers with fresh instance so it picks up the filter.
		$dedup = new QueryDeduplication();
		$dedup->load_resolvers();
		$this->set_tracking_enabled( $dedup, true );

		$block = make_block( 'acme/card', [ 'postId' => 55 ] );
		apply_filters( 'render_block_acme/card', '', [ 'attrs' => [ 'postId' => 55 ] ], $block );

		$this->assertTrue( Deduplicator::has( 55 ) );
	}

	public function test_resolver_adds_array_of_ids(): void {
		add_filter(
			'outstand_query_loop_deduplication_resolvers',
			static fn( array $r ): array => $r + [
				'acme/grid' => static fn( array $parsed ): array => array_map( 'intval', $parsed['attrs']['ids'] ?? [] ),
			]
		);

		$dedup = new QueryDeduplication();
		$dedup->load_resolvers();
		$this->set_tracking_enabled( $dedup, true );

		$block = make_block( 'acme/grid', [ 'ids' => [ 10, 20, 30 ] ] );
		apply_filters( 'render_block_acme/grid', '', [ 'attrs' => [ 'ids' => [ 10, 20, 30 ] ] ], $block );

		$this->assertEqualsCanonicalizing( [ 10, 20, 30 ], Deduplicator::get() );
	}

	public function test_resolver_skipped_when_tracking_disabled(): void {
		add_filter(
			'outstand_query_loop_deduplication_resolvers',
			static fn( array $r ): array => $r + [
				'acme/card' => static fn( array $parsed ): int => (int) ( $parsed['attrs']['postId'] ?? 0 ),
			]
		);

		$dedup = new QueryDeduplication();
		$dedup->load_resolvers();
		// tracking_enabled remains false.

		$block = make_block( 'acme/card', [ 'postId' => 55 ] );
		apply_filters( 'render_block_acme/card', '', [ 'attrs' => [ 'postId' => 55 ] ], $block );

		$this->assertFalse( Deduplicator::has( 55 ) );
	}

	public function test_resolver_zero_and_negative_ids_are_ignored(): void {
		add_filter(
			'outstand_query_loop_deduplication_resolvers',
			static fn( array $r ): array => $r + [
				'acme/card' => static fn( array $parsed ): array => [ 0, -1, 7 ],
			]
		);

		$dedup = new QueryDeduplication();
		$dedup->load_resolvers();
		$this->set_tracking_enabled( $dedup, true );

		$block = make_block( 'acme/card' );
		apply_filters( 'render_block_acme/card', '', [ 'attrs' => [] ], $block );

		$this->assertSame( [ 7 ], Deduplicator::get() );
	}

	public function test_invalid_resolver_entries_are_skipped(): void {
		add_filter(
			'outstand_query_loop_deduplication_resolvers',
			static fn(): array => [
				''          => static fn(): int => 1,
				'acme/ok'   => 'not_callable',
				'acme/good' => static fn( array $parsed ): int => 99,
			]
		);

		$dedup = new QueryDeduplication();
		$dedup->load_resolvers();
		$this->set_tracking_enabled( $dedup, true );

		$block = make_block( 'acme/good' );
		apply_filters( 'render_block_acme/good', '', [ 'attrs' => [] ], $block );

		$this->assertTrue( Deduplicator::has( 99 ) );
	}

	public function test_non_array_filter_return_is_ignored(): void {
		add_filter( 'outstand_query_loop_deduplication_resolvers', static fn() => 'garbage' );

		$dedup = new QueryDeduplication();
		// Should not throw / trigger errors.
		$dedup->load_resolvers();

		$this->assertSame( 0, Deduplicator::count() );
	}

	private function set_tracking_enabled( QueryDeduplication $dedup, bool $value ): void {
		$ref = new \ReflectionProperty( $dedup, 'tracking_enabled' );
		$ref->setAccessible( true );
		$ref->setValue( $dedup, $value );
	}
}
