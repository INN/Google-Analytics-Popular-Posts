<?php
/**
 * Network option page.
 *
 * @package Analytic Bridge
 */


/**
 * Register *network* option page for the Analytic Bridge.
 *
 * @since v0.1
 */
function analyticbridge_network_plugin_menu() {

	add_submenu_page(
		'settings.php',
		'Analytic Bridge Network Options',
		'Analytic Bridge',
		'manage_network_options',
		'analytic-bridge',
		'analyticbridge_network_option_page_html'
	);

}
add_action( 'network_admin_menu', 'analyticbridge_network_plugin_menu' );


/**
 * Output the html for the *network* option page.
 *
 * @since v0.1
 */
function analyticbridge_network_option_page_html() {

	if(!current_user_can('manage_network_options'))
		wp_die("Sorry, you don't have permission to do this");
	echo '<div class="wrap">';
	echo '<h2>Network Analytic Bridge Options</h2>';
	echo '<form action="'. admin_url('admin-post.php?action=network-analytic-bridge-options') .'" method="post">';

	wp_nonce_field('network_option_page_update');

	echo "<h3>Google API tokens</h3>";
	echo "<p>Set API tokens for the network.</p>";
	echo "<table class='form-table'>";
	echo "<tbody><tr><th scope='row'>Client ID</th><td>";
	echo '<input name="analyticbridge_network_setting_api_client_id" id="analyticbridge_network_setting_api_client_id" type="text" value="' . get_site_option('analyticbridge_network_setting_api_client_id') . '" class="regular-text" />';
	echo "</td></tr><tr><th scope='row'>Client Secret</th><td>";
	echo '<input name="analyticbridge_network_setting_api_client_secret" id="analyticbridge_network_setting_api_client_secret" type="text" value="' . get_site_option('analyticbridge_network_setting_api_client_secret') . '" class="regular-text" />';
	echo "</tbody></table>";

	submit_button();

	echo '</form>';
	echo '</div>'; // div.wrap

}


function analyticbridge_update_network_options(){

	wp_nonce_field('network_option_page_update');
	if(!current_user_can('manage_network_options'))
		wp_die("Sorry, you don't have permission to do this");

	update_site_option('analyticbridge_network_setting_api_client_id',$_POST['analyticbridge_network_setting_api_client_id']);
	update_site_option('analyticbridge_network_setting_api_client_secret',$_POST['analyticbridge_network_setting_api_client_secret']);

	wp_redirect(admin_url('network/settings.php?page=analytic-bridge'));

	exit;
}
add_action('admin_post_network-analytic-bridge-options',  'analyticbridge_update_network_options');

