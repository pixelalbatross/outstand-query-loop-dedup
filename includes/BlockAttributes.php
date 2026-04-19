<?php

namespace Outstand\WP\QueryLoop\Dedup;

/**
 * Registers the excludeDuplicates attribute and context on core/query server-side.
 */
class BlockAttributes extends BaseModule {

	/**
	 * Context key used to propagate excludeDuplicates to inner blocks.
	 *
	 * @var string
	 */
	public const CONTEXT_KEY = 'outstand/excludeDuplicates';

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter( 'register_block_type_args', [ $this, 'register_attributes' ], 10, 2 );
	}

	/**
	 * Register the excludeDuplicates attribute, context provider, and context consumer.
	 *
	 * @param array  $args       Block type arguments.
	 * @param string $block_type Block type name.
	 *
	 * @return array Modified block type arguments.
	 */
	public function register_attributes( array $args, string $block_type ): array {

		if ( 'core/query' === $block_type ) {
			$args['attributes']['excludeDuplicates'] = [
				'type'    => 'boolean',
				'default' => false,
			];

			$args['provides_context'][ self::CONTEXT_KEY ] = 'excludeDuplicates';
		}

		if ( 'core/post-template' === $block_type ) {
			$args['uses_context'] = $args['uses_context'] ?? [];

			if ( ! in_array( self::CONTEXT_KEY, $args['uses_context'], true ) ) {
				$args['uses_context'][] = self::CONTEXT_KEY;
			}
		}

		return $args;
	}
}
