<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Outstand\WP\QueryLoop\Dedup
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore
	exit( 1 );
}

// Load Composer autoloader.
$plugin_dir = dirname( __DIR__, 2 );
if ( file_exists( $plugin_dir . '/vendor/autoload.php' ) ) {
	require_once $plugin_dir . '/vendor/autoload.php';
}

// Define plugin constants normally set in plugin.php.
if ( ! defined( 'OUTSTAND_QUERY_LOOP_DEDUP_VERSION' ) ) {
	define( 'OUTSTAND_QUERY_LOOP_DEDUP_VERSION', '1.0.0-test' );
}

if ( ! defined( 'OUTSTAND_QUERY_LOOP_DEDUP_BASENAME' ) ) {
	define( 'OUTSTAND_QUERY_LOOP_DEDUP_BASENAME', 'outstand-query-loop-dedup/plugin.php' );
}

if ( ! defined( 'OUTSTAND_QUERY_LOOP_DEDUP_PATH' ) ) {
	define( 'OUTSTAND_QUERY_LOOP_DEDUP_PATH', $plugin_dir . '/' );
}

if ( ! defined( 'OUTSTAND_QUERY_LOOP_DEDUP_URL' ) ) {
	define( 'OUTSTAND_QUERY_LOOP_DEDUP_URL', 'http://example.org/wp-content/plugins/outstand-query-loop-dedup/' );
}

if ( ! defined( 'OUTSTAND_QUERY_LOOP_DEDUP_DIST_PATH' ) ) {
	define( 'OUTSTAND_QUERY_LOOP_DEDUP_DIST_PATH', OUTSTAND_QUERY_LOOP_DEDUP_PATH . 'build/' );
}

if ( ! defined( 'OUTSTAND_QUERY_LOOP_DEDUP_DIST_URL' ) ) {
	define( 'OUTSTAND_QUERY_LOOP_DEDUP_DIST_URL', OUTSTAND_QUERY_LOOP_DEDUP_URL . 'build/' );
}

// Load WordPress test suite functions.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin's modules on muplugins_loaded.
 *
 * We don't load the full plugin.php because it wires the PUC updater which
 * hits the network — tests boot the modules directly.
 */
tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		$plugin = \Outstand\WP\QueryLoop\Dedup\Plugin::get_instance();
		$plugin->enable();
	}
);

// Bootstrap WordPress test suite.
require $_tests_dir . '/includes/bootstrap.php';

// Load test helper functions (after WP is booted).
require_once __DIR__ . '/helpers/functions.php';
