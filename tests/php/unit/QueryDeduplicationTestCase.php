<?php

namespace Outstand\WP\QueryLoop\Dedup\Tests\Unit;

use Outstand\WP\QueryLoop\Dedup\Deduplicator;
use Outstand\WP\QueryLoop\Dedup\QueryDeduplication;

/**
 * Shared setup/teardown for QueryDeduplication tests.
 *
 * Each test gets a fresh Deduplicator registry and a fresh QueryDeduplication
 * instance whose internal state (tracking_enabled, backup stack) starts clean.
 * Register hooks are not re-bound — the bootstrap-registered plugin instance
 * is used for hook-driven tests.
 */
abstract class QueryDeduplicationTestCase extends \WP_UnitTestCase {

	protected QueryDeduplication $dedup;

	protected function setUp(): void {
		parent::setUp();
		Deduplicator::clear();
		$this->dedup = new QueryDeduplication();
	}

	protected function tearDown(): void {
		Deduplicator::clear();
		parent::tearDown();
	}
}
