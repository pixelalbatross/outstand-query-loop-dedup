<?php

namespace Outstand\WP\QueryLoop\Dedup\Tests\Unit;

use Outstand\WP\QueryLoop\Dedup\Deduplicator;

/**
 * @covers \Outstand\WP\QueryLoop\Dedup\Deduplicator
 */
class DeduplicatorTest extends \WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Deduplicator::clear();
	}

	protected function tearDown(): void {
		Deduplicator::clear();
		parent::tearDown();
	}

	public function test_starts_empty(): void {
		$this->assertSame( 0, Deduplicator::count() );
		$this->assertSame( [], Deduplicator::get() );
	}

	public function test_add_single_id(): void {
		Deduplicator::add( 1 );
		$this->assertTrue( Deduplicator::has( 1 ) );
		$this->assertFalse( Deduplicator::has( 2 ) );
		$this->assertSame( 1, Deduplicator::count() );
	}

	public function test_add_multiple_ids(): void {
		Deduplicator::add( 1, 2, 3 );
		$this->assertSame( [ 1, 2, 3 ], Deduplicator::get() );
		$this->assertSame( 3, Deduplicator::count() );
	}

	public function test_add_is_idempotent(): void {
		Deduplicator::add( 1 );
		Deduplicator::add( 1 );
		Deduplicator::add( 1 );
		$this->assertSame( 1, Deduplicator::count() );
	}

	public function test_clear_resets_registry(): void {
		Deduplicator::add( 1, 2, 3 );
		Deduplicator::clear();
		$this->assertSame( 0, Deduplicator::count() );
		$this->assertFalse( Deduplicator::has( 1 ) );
	}
}
