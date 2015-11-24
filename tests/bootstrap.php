<?php

$wp_tests_dir = getenv('WP_TESTS_DIR');
require_once $wp_tests_dir . '/includes/functions.php';

$basename = basename(dirname(__DIR__));

$GLOBALS['wp_tests_options'] = array(
	'stylesheet' => $basename,
	'template' => $basename
);

tests_add_filter('set_current_user', function($arg) {
	$user = wp_get_current_user();
	$user->set_role('administrator');
	return $arg;
}, 1, 10);

tests_add_filter('filesystem_method', function($arg) {
	return 'direct';
}, 1, 10);

function _manually_load_environment() {
	$plugins_to_active = array (basename(dirname(__DIR__))) . "/analytic-bridge.php";

	update_option('active_plugins', $plugins_to_active);

}
tests_add_filter('muplugins_loaded', '_manually_load_environment');

require $wp_tests_dir . '/includes/bootstrap.php';

if ( !function_exists('var_log') ) {
	function var_log($stuff) {
		error_log(var_export($stuff, true));
	}
}
