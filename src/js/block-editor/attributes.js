/**
 * WordPress dependencies.
 */
import { addFilter } from '@wordpress/hooks';

addFilter('blocks.registerBlockType', 'outstand-query-loop-dedup/attribute', (settings, name) => {
	if (name !== 'core/query') {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			excludeDuplicates: {
				type: 'boolean',
				default: false,
			},
		},
	};
});
