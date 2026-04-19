<?php

namespace Outstand\WP\QueryLoop\Dedup;

/**
 * Static registry of rendered post IDs.
 *
 * Tracks which posts have been rendered to enable deduplication
 * across multiple Query Loop blocks on the same page.
 */
class Deduplicator {

	/**
	 * Registry of rendered post IDs, keyed by ID for O(1) lookups.
	 *
	 * @var array<int, true>
	 */
	private static array $post_ids = [];

	/**
	 * Private constructor — not instantiable.
	 */
	private function __construct() {}

	/**
	 * Add one or more post IDs to the registry.
	 *
	 * @param int ...$ids Post IDs to register.
	 *
	 * @return void
	 */
	public static function add( int ...$ids ): void {
		foreach ( $ids as $id ) {
			self::$post_ids[ $id ] = true;
		}
	}

	/**
	 * Check if a post ID has been registered.
	 *
	 * @param int $id Post ID to check.
	 *
	 * @return bool
	 */
	public static function has( int $id ): bool {
		return isset( self::$post_ids[ $id ] );
	}

	/**
	 * Get all registered post IDs.
	 *
	 * @return array<int>
	 */
	public static function get(): array {
		return array_keys( self::$post_ids );
	}

	/**
	 * Get the number of registered post IDs.
	 *
	 * @return int
	 */
	public static function count(): int {
		return count( self::$post_ids );
	}

	/**
	 * Clear all registered post IDs.
	 *
	 * @return void
	 */
	public static function clear(): void {
		self::$post_ids = [];
	}
}
