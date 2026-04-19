import {
	collectQueryClientIds,
	computeEffectiveQuery,
	toRestArgs,
} from '../../src/js/block-editor/utils';

describe('collectQueryClientIds', () => {
	it('returns empty list when no queries present', () => {
		const blocks = [{ name: 'core/paragraph', clientId: 'a', innerBlocks: [] }];
		expect(collectQueryClientIds(blocks)).toEqual([]);
	});

	it('collects top-level queries in document order', () => {
		const blocks = [
			{ name: 'core/query', clientId: 'q1', innerBlocks: [] },
			{ name: 'core/paragraph', clientId: 'p1', innerBlocks: [] },
			{ name: 'core/query', clientId: 'q2', innerBlocks: [] },
		];
		expect(collectQueryClientIds(blocks)).toEqual(['q1', 'q2']);
	});

	it('collects nested queries in depth-first order', () => {
		const blocks = [
			{
				name: 'core/group',
				clientId: 'g1',
				innerBlocks: [{ name: 'core/query', clientId: 'q1', innerBlocks: [] }],
			},
			{ name: 'core/query', clientId: 'q2', innerBlocks: [] },
		];
		expect(collectQueryClientIds(blocks)).toEqual(['q1', 'q2']);
	});
});

describe('computeEffectiveQuery', () => {
	it('returns parentQuery unchanged when shouldDedupe is false', () => {
		const parentQuery = { perPage: 5, postType: 'post' };
		const result = computeEffectiveQuery(parentQuery, [10, 20], false);
		expect(result).toEqual({ perPage: 5, postType: 'post' });
		expect(result).not.toHaveProperty('exclude');
	});

	it('does NOT inject preceding IDs when flag is off — regression guard', () => {
		// If unflagged queries inherit preceding IDs, their REST fetch diverges
		// from the frontend WP_Query (which only excludes when flag is true),
		// causing resolvedIds union corruption in later queries.
		const parentQuery = { perPage: 5 };
		const result = computeEffectiveQuery(parentQuery, [1, 2, 3], false);
		expect(result.exclude).toBeUndefined();
	});

	it('merges preceding IDs into exclude when shouldDedupe is true', () => {
		const parentQuery = { perPage: 5, exclude: [7] };
		const result = computeEffectiveQuery(parentQuery, [1, 2], true);
		expect(result).toEqual({ perPage: 5, exclude: [7, 1, 2] });
	});

	it('handles missing parentQuery', () => {
		expect(computeEffectiveQuery(undefined, [], false)).toEqual({});
		expect(computeEffectiveQuery(null, [1], true)).toEqual({ exclude: [1] });
	});

	it('preserves parent exclude array when no preceding IDs', () => {
		// shouldDedupe is only true when precedingIds > 0 in the HOC, but the
		// function itself still handles the empty case safely.
		const parentQuery = { exclude: [99] };
		const result = computeEffectiveQuery(parentQuery, [], true);
		expect(result.exclude).toEqual([99]);
	});
});

describe('toRestArgs', () => {
	it('maps core key aliases', () => {
		expect(toRestArgs({ perPage: 5, orderBy: 'title', parents: [3] })).toEqual({
			per_page: 5,
			orderby: 'title',
			parent: [3],
		});
	});

	it('drops editor-only and inherit-driven keys', () => {
		expect(toRestArgs({ pages: 2, inherit: true, taxQuery: { category: [1] } })).toEqual({});
	});

	it('drops empty values', () => {
		expect(toRestArgs({ perPage: 5, search: '', author: null, exclude: [] })).toEqual({
			per_page: 5,
		});
	});

	it('translates sticky values', () => {
		expect(toRestArgs({ sticky: 'only' })).toEqual({ sticky: true });
		expect(toRestArgs({ sticky: 'exclude' })).toEqual({ sticky: false });
		expect(toRestArgs({ sticky: 'ignore' })).toEqual({ ignore_sticky: true });
	});
});
