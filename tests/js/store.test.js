/**
 * @jest-environment jsdom
 */

import { dispatch, select } from '@wordpress/data';
import { STORE_NAME } from '../../src/js/block-editor/store';

describe('dedup store', () => {
	beforeEach(() => {
		// Clear state by setting known clientIds to empty arrays.
		// Store is registered on import and persists across tests.
	});

	describe('setResolvedIds', () => {
		it('records IDs for a clientId', () => {
			dispatch(STORE_NAME).setResolvedIds('q1', [1, 2, 3]);
			expect(select(STORE_NAME).getUnionForClientIds(['q1'])).toEqual([1, 2, 3]);
		});

		it('overwrites previous IDs for same clientId', () => {
			dispatch(STORE_NAME).setResolvedIds('q-over', [1, 2]);
			dispatch(STORE_NAME).setResolvedIds('q-over', [9, 10]);
			expect(select(STORE_NAME).getUnionForClientIds(['q-over'])).toEqual([9, 10]);
		});
	});

	describe('clearResolvedIds', () => {
		it('removes a clientId entry', () => {
			dispatch(STORE_NAME).setResolvedIds('q-clear', [5, 6]);
			dispatch(STORE_NAME).clearResolvedIds('q-clear');
			expect(select(STORE_NAME).getUnionForClientIds(['q-clear'])).toEqual([]);
		});

		it('no-op when clientId unknown', () => {
			expect(() => dispatch(STORE_NAME).clearResolvedIds('unknown-id')).not.toThrow();
		});
	});

	describe('getUnionForClientIds', () => {
		beforeAll(() => {
			dispatch(STORE_NAME).setResolvedIds('u1', [1, 2, 3]);
			dispatch(STORE_NAME).setResolvedIds('u2', [3, 4, 5]);
			dispatch(STORE_NAME).setResolvedIds('u3', []);
		});

		it('returns empty for empty input', () => {
			expect(select(STORE_NAME).getUnionForClientIds([])).toEqual([]);
		});

		it('deduplicates overlapping IDs across clientIds', () => {
			expect(select(STORE_NAME).getUnionForClientIds(['u1', 'u2'])).toEqual([1, 2, 3, 4, 5]);
		});

		it('preserves insertion order based on clientId list', () => {
			expect(select(STORE_NAME).getUnionForClientIds(['u2', 'u1'])).toEqual([3, 4, 5, 1, 2]);
		});

		it('skips unknown clientIds', () => {
			expect(select(STORE_NAME).getUnionForClientIds(['u1', 'nope', 'u2'])).toEqual([
				1, 2, 3, 4, 5,
			]);
		});

		it('handles clientIds with empty ID lists', () => {
			expect(select(STORE_NAME).getUnionForClientIds(['u3', 'u1'])).toEqual([1, 2, 3]);
		});
	});
});
