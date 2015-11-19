<?php

$wp_tests_dir = getenv('WP_TESTS_DIR');
require_once $wp_tests_dir . '/includes/functions.php';

function _manually_load_environment() {
	// Add your theme
	switch_theme("your-theme-name");

	// Update array with plugins to include ...
	$plugins_to_active = array("your-plugin/your-plugin.php");

	update_option('active_plugins', $plugins_to_active);
}
tests_add_filter('muplugins_loaded', '_manually_load_environment');

require $wp_tests_dir . '/includes/bootstrap.php';
