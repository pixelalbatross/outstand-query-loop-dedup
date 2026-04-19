/**
 * Walk the block tree and collect all core/query block clientIds in document order.
 *
 * @param {Array} blocks Block list.
 * @return {Array<string>} Flat list of clientIds.
 */
export const collectQueryClientIds = (blocks) => {
	const out = [];
	const walk = (list) => {
		for (const b of list) {
			if (b.name === 'core/query') {
				out.push(b.clientId);
			}

			if (b.innerBlocks?.length) {
				walk(b.innerBlocks);
			}
		}
	};

	walk(blocks);

	return out;
};

/**
 * Translate a core/query attributes.query object into REST args.
 *
 * Mirrors core/post-template's inlined translation:
 * https://github.com/WordPress/gutenberg/blob/trunk/packages/block-library/src/post-template/edit.js#L143-L237
 *
 * @param {Object} query Block query attribute.
 * @return {Object} REST query args.
 */
const REST_KEY_MAP = {
	perPage: 'per_page',
	orderBy: 'orderby',
	parents: 'parent',
};

// Editor-only or inherit-driven keys that don't map to a REST arg directly.
// `taxQuery` is dropped here — taxonomy-filtered dedup in the editor preview is not yet supported.
const REST_KEY_DROP = new Set(['pages', 'inherit', 'taxQuery']);

/**
 * Compute the query used to fetch this post-template's records.
 *
 * When dedup is active (flag on AND preceding IDs exist), merge preceding IDs
 * into `exclude`. Otherwise return the parent query unchanged — crucially, an
 * unflagged query must NOT inherit preceding exclusions, or its resolved IDs
 * diverge from the frontend and corrupt the union used by later queries.
 *
 * @param {Object}   parentQuery    The parent core/query `query` attribute.
 * @param {number[]} precedingIds   Post IDs resolved from earlier queries.
 * @param {boolean}  shouldDedupe   Whether dedup applies to this query.
 * @return {Object} Effective query.
 */
export const computeEffectiveQuery = (parentQuery, precedingIds, shouldDedupe) => {
	if (!shouldDedupe) {
		return parentQuery ?? {};
	}

	return {
		...(parentQuery ?? {}),
		exclude: [...(parentQuery?.exclude ?? []), ...precedingIds],
	};
};

export const toRestArgs = (query = {}) => {
	const args = {};
	for (const [key, value] of Object.entries(query)) {
		if (REST_KEY_DROP.has(key)) {
			continue;
		}

		if (value === '' || value === null || value === undefined) {
			continue;
		}

		if (Array.isArray(value) && value.length === 0) {
			continue;
		}

		if (key === 'sticky') {
			if (value === 'only' || value === 'exclude') {
				args.sticky = value === 'only';
			} else if (value === 'ignore') {
				args.ignore_sticky = true;
			}
			continue;
		}

		const restKey = REST_KEY_MAP[key] ?? key;
		args[restKey] = value;
	}
	return args;
};
