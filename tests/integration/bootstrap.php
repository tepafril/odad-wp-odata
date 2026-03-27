<?php
/**
 * Bootstrap for PHPUnit integration tests.
 *
 * Loads the WordPress test suite, then loads the plugin.
 * The WP test suite fires plugins_loaded automatically, which
 * triggers ODAD_Bootstrapper::build() and ODAD_Hook_Bridge::register().
 */

// Load WP test suite then the plugin.
$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';
require_once $_tests_dir . '/includes/bootstrap.php';

define( 'ODAD_VERSION',    '0.1.0' );
define( 'ODAD_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/wp-odata-suite/' );
define( 'ODAD_PLUGIN_URL', 'http://localhost/' );

require_once dirname( __DIR__, 2 ) . '/wp-odata-suite/wp-odata-suite.php';
