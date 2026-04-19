/**
 * WordPress dependencies.
 */
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const withDedupControl = createHigherOrderComponent(
	(BlockEdit) => (props) => {
		if (props.name !== 'core/query') {
			return <BlockEdit {...props} />;
		}

		const { attributes, setAttributes } = props;

		return (
			<>
				<BlockEdit {...props} />
				<InspectorControls>
					<PanelBody title={__('Deduplication', 'outstand-query-loop-dedup')}>
						<ToggleControl
							__nextHasNoMarginBottom
							label={__('Exclude duplicate posts', 'outstand-query-loop-dedup')}
							help={__(
								'Skip posts already shown by previous query loops on this page.',
								'outstand-query-loop-dedup',
							)}
							checked={!!attributes.excludeDuplicates}
							onChange={(value) => setAttributes({ excludeDuplicates: value })}
						/>
					</PanelBody>
				</InspectorControls>
			</>
		);
	},
	'withDedupControl',
);

addFilter('editor.BlockEdit', 'outstand-query-loop-dedup/control', withDedupControl);
