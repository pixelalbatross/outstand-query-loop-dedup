/**
 * WordPress dependencies.
 */
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEntityRecords } from '@wordpress/core-data';
import { useEffect, useMemo } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { STORE_NAME as DEDUP_STORE } from './store';
import { collectQueryClientIds, computeEffectiveQuery, toRestArgs } from './utils';

const withPostTemplateDedup = createHigherOrderComponent(
	(BlockEdit) => (props) => {
		if (props.name !== 'core/post-template') {
			return <BlockEdit {...props} />;
		}

		const { clientId } = props;

		const { parentClientId, parentQuery, excludeDuplicates, precedingClientIds } = useSelect(
			(select) => {
				const be = select(blockEditorStore);
				const parents = be.getBlockParentsByBlockName(clientId, 'core/query');
				const pid = parents[parents.length - 1];

				if (!pid) {
					return {};
				}

				const parent = be.getBlock(pid);
				const all = collectQueryClientIds(be.getBlocks());
				const idx = all.indexOf(pid);

				return {
					parentClientId: pid,
					parentQuery: parent?.attributes?.query ?? {},
					excludeDuplicates: !!parent?.attributes?.excludeDuplicates,
					precedingClientIds: idx > 0 ? all.slice(0, idx) : [],
				};
			},
			[clientId],
		);

		const precedingIds = useSelect(
			(select) => select(DEDUP_STORE).getUnionForClientIds(precedingClientIds ?? []),
			[precedingClientIds],
		);

		const shouldDedupe = excludeDuplicates && precedingIds.length > 0;

		const effectiveQuery = useMemo(
			() => computeEffectiveQuery(parentQuery, precedingIds, shouldDedupe),
			[parentQuery, precedingIds, shouldDedupe],
		);

		const { records } = useEntityRecords(
			'postType',
			parentQuery?.postType || 'post',
			toRestArgs(effectiveQuery),
		);

		const { setResolvedIds, clearResolvedIds } = useDispatch(DEDUP_STORE);
		const ownIds = useMemo(() => (records ? records.map((r) => r.id) : null), [records]);

		useEffect(() => {
			if (parentClientId && ownIds) {
				setResolvedIds(parentClientId, ownIds);
			}
		}, [parentClientId, ownIds, setResolvedIds]);

		useEffect(() => {
			if (!parentClientId) {
				return undefined;
			}
			return () => clearResolvedIds(parentClientId);
		}, [parentClientId, clearResolvedIds]);

		if (!shouldDedupe) {
			return <BlockEdit {...props} />;
		}

		return <BlockEdit {...props} context={{ ...props.context, query: effectiveQuery }} />;
	},
	'withPostTemplateDedup',
);

addFilter('editor.BlockEdit', 'outstand-query-loop-dedup/preview', withPostTemplateDedup);
