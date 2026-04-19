/**
 * WordPress dependencies.
 */
import { createReduxStore, register } from '@wordpress/data';

export const STORE_NAME = 'outstand/query-loop-dedup';

const DEFAULT_STATE = {
	resolvedIds: {},
};

const actions = {
	setResolvedIds(clientId, ids) {
		return { type: 'SET_RESOLVED_IDS', clientId, ids };
	},
	clearResolvedIds(clientId) {
		return { type: 'CLEAR_RESOLVED_IDS', clientId };
	},
};

const sameIds = (a, b) => a.length === b.length && a.every((v, i) => v === b[i]);

const reducer = (state = DEFAULT_STATE, action) => {
	if (action.type === 'CLEAR_RESOLVED_IDS') {
		if (!(action.clientId in state.resolvedIds)) {
			return state;
		}
		const next = { ...state.resolvedIds };
		delete next[action.clientId];
		return { ...state, resolvedIds: next };
	}

	if (action.type !== 'SET_RESOLVED_IDS') {
		return state;
	}

	const current = state.resolvedIds[action.clientId];
	if (current && sameIds(current, action.ids)) {
		return state;
	}

	return {
		...state,
		resolvedIds: {
			...state.resolvedIds,
			[action.clientId]: action.ids,
		},
	};
};

const selectors = {
	getUnionForClientIds(state, clientIds) {
		const seen = new Set();
		const out = [];
		for (const id of clientIds) {
			const ids = state.resolvedIds[id] ?? [];
			for (const i of ids) {
				if (!seen.has(i)) {
					seen.add(i);
					out.push(i);
				}
			}
		}
		return out;
	},
};

register(createReduxStore(STORE_NAME, { reducer, actions, selectors }));
