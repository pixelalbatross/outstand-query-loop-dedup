<?php

namespace Outstand\WP\QueryLoop\Dedup;

class Assets extends BaseModule {
	use GetAssetInfo;

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		$this->setup_asset_vars(
			dist_path: OUTSTAND_QUERY_LOOP_DEDUP_DIST_PATH,
			fallback_version: OUTSTAND_QUERY_LOOP_DEDUP_VERSION
		);

		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_scripts' ] );
	}

	/**
	 * Enqueue block editor scripts.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_scripts(): void {
		wp_enqueue_script(
			'outstand-query-loop-dedup-block-editor',
			OUTSTAND_QUERY_LOOP_DEDUP_DIST_URL . 'js/block-editor.js',
			$this->get_asset_info( 'block-editor', 'dependencies' ),
			$this->get_asset_info( 'block-editor', 'version' ),
			true
		);

		wp_set_script_translations(
			'outstand-query-loop-dedup-block-editor',
			'outstand-query-loop-dedup',
			OUTSTAND_QUERY_LOOP_DEDUP_PATH . 'languages'
		);
	}
}
