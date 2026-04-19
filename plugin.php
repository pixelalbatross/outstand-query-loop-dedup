<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Outstand Query Loop Dedup
 * Description:       Prevents duplicate posts across multiple Query Loop blocks on the same page.
 * Plugin URI:        https://outstand.site/?utm_source=wp-plugins&utm_medium=outstand-query-loop-dedup&utm_campaign=plugin-uri
 * Requires at least: 6.7
 * Requires PHP:      8.2
 * Version:           1.0.0
 * Author:            Outstand
 * Author URI:        https://outstand.site/?utm_source=wp-plugins&utm_medium=outstand-query-loop-dedup&utm_campaign=author-uri
 * License:           GPL-3.0-or-later
 * License URI:       https://spdx.org/licenses/GPL-3.0-or-later.html
 * Update URI:        https://outstand.site/
 * GitHub Plugin URI: https://github.com/pixelalbatross/outstand-query-loop-dedup
 * Text Domain:       outstand-query-loop-dedup
 * Domain Path:       /languages
 */

namespace Outstand\WP\QueryLoop\Dedup;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'OUTSTAND_QUERY_LOOP_DEDUP_VERSION', '1.0.0' );
define( 'OUTSTAND_QUERY_LOOP_DEDUP_BASENAME', plugin_basename( __FILE__ ) );
define( 'OUTSTAND_QUERY_LOOP_DEDUP_URL', plugin_dir_url( __FILE__ ) );
define( 'OUTSTAND_QUERY_LOOP_DEDUP_PATH', plugin_dir_path( __FILE__ ) );
define( 'OUTSTAND_QUERY_LOOP_DEDUP_DIST_URL', OUTSTAND_QUERY_LOOP_DEDUP_URL . 'build/' );
define( 'OUTSTAND_QUERY_LOOP_DEDUP_DIST_PATH', OUTSTAND_QUERY_LOOP_DEDUP_PATH . 'build/' );

if ( file_exists( OUTSTAND_QUERY_LOOP_DEDUP_PATH . 'vendor/autoload.php' ) ) {
	require_once OUTSTAND_QUERY_LOOP_DEDUP_PATH . 'vendor/autoload.php';
}

PucFactory::buildUpdateChecker(
	'https://github.com/pixelalbatross/outstand-query-loop-dedup/',
	__FILE__,
	'outstand-query-loop-dedup'
)->setBranch( 'main' );

/**
 * Load the plugin.
 */
add_action(
	'plugins_loaded',
	function () {
		$plugin = Plugin::get_instance();
		$plugin->enable();
	}
);
