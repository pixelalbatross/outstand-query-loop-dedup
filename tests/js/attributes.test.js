/**
 * Guards against WP core API drift on `blocks.registerBlockType`.
 *
 * If WordPress changes the filter signature or semantics, this test catches it
 * before it hits the editor.
 */
import { applyFilters } from '@wordpress/hooks';

// Import registers the filter.
import '../../src/js/block-editor/attributes';

describe('blocks.registerBlockType filter — excludeDuplicates attribute', () => {
	it('adds excludeDuplicates attribute to core/query', () => {
		const result = applyFilters(
			'blocks.registerBlockType',
			{ attributes: { queryId: { type: 'number' } } },
			'core/query',
		);

		expect(result.attributes).toMatchObject({
			queryId: { type: 'number' },
			excludeDuplicates: { type: 'boolean', default: false },
		});
	});

	it('preserves other attributes when extending core/query', () => {
		const settings = {
			attributes: {
				queryId: { type: 'number' },
				query: { type: 'object' },
			},
			supports: { html: false },
		};

		const result = applyFilters('blocks.registerBlockType', settings, 'core/query');

		expect(result.attributes.queryId).toEqual({ type: 'number' });
		expect(result.attributes.query).toEqual({ type: 'object' });
		expect(result.supports).toEqual({ html: false });
	});

	it('returns settings unchanged for non-core/query blocks', () => {
		const settings = { attributes: { foo: { type: 'string' } } };
		const result = applyFilters('blocks.registerBlockType', settings, 'core/paragraph');
		expect(result).toBe(settings);
	});

	it('default of excludeDuplicates is false', () => {
		const result = applyFilters(
			'blocks.registerBlockType',
			{ attributes: {} },
			'core/query',
		);
		expect(result.attributes.excludeDuplicates.default).toBe(false);
	});
});
