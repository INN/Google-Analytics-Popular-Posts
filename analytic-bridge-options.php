<?php

require_once( plugin_dir_path( __FILE__ ) . '../AnalyticBridgeGoogleClient.php');
require_once( plugin_dir_path( __FILE__ ) . '../analytic-bridge.php');


/**
 * ================================================================================================
 *	 #region: network option page.
 * ================================================================================================
 */

/**
 * Register *network* option page for the Analytic Bridge.
 *
 * @since v0.1
 */
function analyticbridge_network_plugin_menu() {

	add_submenu_page(
		'settings.php',
		'GA Popular Posts Network Options',
		'GA Popular Posts',
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
	echo '<h2>Network Google Analytics Popular Posts Options</h2>';
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


/**
 * ================================================================================================
 *	 #region: blog option page(s).
 * ================================================================================================
 */


/**
 * Register option page for the Analytic Bridge.
 *
 * @since v0.1
 */
function analyticbridge_plugin_menu() {
	add_options_page(
		'GA Popular Posts Options', 					// $page_title title of the page.
		'GA Popular Posts', 							// $menu_title the text to be used for the menu.
		'manage_options', 							// $capability required capability for display.
		'analytic-bridge', 							// $menu_slug unique slug for menu.
		'analyticbridge_option_page_html' 			// $function callback.
		);
}
add_action( 'admin_menu', 'analyticbridge_plugin_menu' );

/**
 * Output the HTML for the Analytic Bridge option page.
 *
 * If a $_GET variable is posted back to the page (by Google), it's stored as an option.
 *
 * @since v0.1
 */
function analyticbridge_option_page_html() {

	// Nice try.
	if ( !current_user_can( 'manage_options' ) )
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );


	echo '<div class="wrap">';
	echo '<h2>Google Analytics Popular Posts</h2>';
	echo '<form method="post" action="options.php">';
	settings_fields( 'analytic-bridge' );
	do_settings_sections( 'analytic-bridge' );
	submit_button();
	echo '</form>';


	// check if there is a client id/secret defined.

	if(analyticbridge_client_id() && analyticbridge_client_secret()) :

		/* Google has posted an authenticate code back to us. */
		if ( isset($_GET['code']) ) :
			$client = analytic_bridge_authenticate_google_client($_GET['code']);
			$redirect = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
			header('Location: ' . filter_var($redirect, FILTER_SANITIZE_URL));

		// No auth ticket loaded (yet).
		elseif( !get_option('analyticbridge_access_token') ) :
			$client = analytic_bridge_google_client(false);
			echo "<a href='" . $client->createAuthUrl() . "'>Connect</a>";

		else :
			$client = analytic_bridge_google_client();
			$service = new Google_Service_Oauth2($client);
			$user = $service->userinfo->get();
			echo "Connected as " . $user->getEmail();

		endif;

		/* The user has asked us to run the cron. */
		if( isset($_GET['update']) ) :

			echo "<h3>Running Update...</h3>";
			echo "<pre>";
				echo "Running cron...";
				largo_anaylticbridge_cron(true);
			echo "</pre>";

		else :

			echo "<h3>Update Analytics</h3>";
			echo "<pre>";
				echo '<a href="' . admin_url('options-general.php?page=analytic-bridge&update'). '">Update analytics</a>';
			echo "</pre>";

		endif;

	else :

		echo "Enter your API details";

	endif;

	echo '</div>'; // div.wrap

}

/**
 * Registers options for the plugin.
 *
 * @since v0.1
 */
function analyticbridge_register_options() {

	/* ------------------------------------------------------------------------------------------
	 * Section 1: API settings.
	 * ---------------------------------------------------------------------------------------- */

	// Only if network API settings aren't defined.

	if( !analyticbridge_using_network_api_tokens() ) {

		// Add a section for network option
		add_settings_section(
			'largo_anaytic_bridge_api_settings_section',
			'Google API tokens',
			'largo_anaytic_bridge_api_settings_section_intro',
			'analytic-bridge'
		); // ($id, $title, $callback, $page)

		// Add Client ID field.
		add_settings_field(
			'analyticbridge_setting_api_client_id',
			'Google Client ID',
			'analyticbridge_setting_api_client_id_input',
			'analytic-bridge',
			'largo_anaytic_bridge_api_settings_section'
		); // ($id, $title, $callback, $page, $section, $args)

		// Add Client Secret field
		add_settings_field(
			'analyticbridge_setting_api_client_secret',
			'Google Client Secret',
			'analyticbridge_setting_api_client_secret_input',
			'analytic-bridge',
			'largo_anaytic_bridge_api_settings_section'
		); // ($id, $title, $callback, $page, $section, $args)

		// Register our settings.
		register_setting( 'analytic-bridge', 'analyticbridge_setting_api_client_id' );
		register_setting( 'analytic-bridge', 'analyticbridge_setting_api_client_secret' );

	}

	/* ------------------------------------------------------------------------------------------
	 * Section 2: Site settings.
	 * ---------------------------------------------------------------------------------------- */

	// Add a section for site option page.
	add_settings_section(
		'largo_anaytic_bridge_api_settings_section',
		'Google API tokens',
		'largo_anaytic_bridge_api_settings_section_intro',
		'analytic-bridge'
	); // ($id, $title, $callback, $page)

	// Add a section for our analytic-bridge page.
	add_settings_section(
		'largo_anaytic_bridge_account_settings_section',
		'Google Analytics Property',
		'largo_anaytic_bridge_account_settings_section_intro',
		'analytic-bridge'
	); // ($id, $title, $callback, $page)

	// Add property field
	add_settings_field(
		'analyticbridge_setting_account_profile_id',
		'Property View ID',
		'analyticbridge_setting_account_profile_id_input',
		'analytic-bridge',
		'largo_anaytic_bridge_account_settings_section'
	); // ($id, $title, $callback, $page, $section, $args)

	// Register our settings.
	register_setting( 'analytic-bridge', 'analyticbridge_setting_account_profile_id' );

	/* ------------------------------------------------------------------------------------------
	 * Section 3: Popular Post settings
	 * ---------------------------------------------------------------------------------------- */

	// Add a section for our analytic-bridge page.
	add_settings_section(
		'largo_anaytic_bridge_popular_posts_settings_section',
		'Popular Post Settings',
		'largo_anaytic_bridge_popular_posts_settings_section_intro',
		'analytic-bridge'
	); // ($id, $title, $callback, $page)

	// Add property field
	add_settings_field(
		'analyticbridge_setting_popular_posts_halflife',
		'Post halflife',
		'analyticbridge_setting_popular_posts_halflife_input',
		'analytic-bridge',
		'largo_anaytic_bridge_popular_posts_settings_section'
	); // ($id, $title, $callback, $page, $section, $args)

	// Register our settings.
	register_setting( 'analytic-bridge', 'analyticbridge_setting_popular_posts_halflife' );

}
add_action('admin_init', 'analyticbridge_register_options');

/**
 * Intro text for our google api settings section.
 *
 * @since v0.1
 */
function largo_anaytic_bridge_api_settings_section_intro() {
	if( !analyticbridge_using_network_api_tokens() ) {
		echo '<p>Enter the client id and client secret from your google developer console.</p>';
		echo '<p>Notes: ensure the <em>consent screen</em> has an email and product name defined, the <em>credentials screen</em> has a proper redirect uri defined and the analytic API is enabled on the <em>API</em> screen.';
	} else {
		echo '<em>API tokens already set by network.</em>';
	}
}

/**
 * Intro text for our google property settings section.
 *
 * @since v0.1
 */
function largo_anaytic_bridge_account_settings_section_intro() {
	echo '<p>Enter the property and profile that corresponds to this site.</p>';
}

/**
 * Intro text for popular post settings
 *
 * @since v0.1
 */
function largo_anaytic_bridge_popular_posts_settings_section_intro() {
	echo '<p>Enter the half life that popular post pageview weight should degrade by.</p>';
}


/**
 * Prints input field for Google Client ID setting.
 *
 * @since v0.1
 */
function analyticbridge_setting_api_client_id_input() {
	echo '<input name="analyticbridge_setting_api_client_id" id="analyticbridge_setting_api_client_id" type="text" value="' . analyticbridge_client_id() . '" class="regular-text" />';
}

/**
 * Prints input field for Google Client Secret setting.
 *
 * @since v0.1
 */
function analyticbridge_setting_api_client_secret_input() {
	echo '<input name="analyticbridge_setting_api_client_secret" id="analyticbridge_setting_api_client_secret" type="text" value="' . analyticbridge_client_secret() . '" class="regular-text" />';
}

/**
 * Prints input field for Google Profile ID to pull data from.
 *
 * @since v0.1
 */
function analyticbridge_setting_account_profile_id_input() {
	echo '<input name="analyticbridge_setting_account_profile_id" id="analyticbridge_setting_account_profile_id" type="text" value="' . get_option('analyticbridge_setting_account_profile_id') . '" class="regular-text" />';
}

/**
 * Prints input field for Popular Post halflife.
 *
 * @since v0.1
 */
function analyticbridge_setting_popular_posts_halflife_input() {
	echo '<input name="analyticbridge_setting_popular_posts_halflife" id="analyticbridge_setting_popular_posts_halflife" type="text" value="' . get_option('analyticbridge_setting_popular_posts_halflife') . '" class="regular-text" />';
}
