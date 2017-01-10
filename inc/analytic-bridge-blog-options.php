<?php
/**
 * Blog option page.
 *
 * @package Analytic Bridge
 */

/**
 * Enqueue style for admin page.
 *
 */
function analyticbridge_blog_options_admin_style( $hook ) {
	if ( $hook == 'settings_page_analytic-bridge' ) {
		wp_enqueue_style(
			'analyticbridge_admin_style',
			plugins_url( 'css/admin.css', dirname( __FILE__ ) ),
			false,
			'0.1'
		);
	}
}
add_action( 'admin_enqueue_scripts', 'analyticbridge_blog_options_admin_style' );

/**
 * Google Analytics Embed API.
 *
 * @see https://developers.google.com/analytics/devguides/reporting/embed/v1/devguide
 */
function analyticbridge_blog_options_admin_head() {

	/* We only give the selector to the user that authenticated in the first place */

	$current_user = wp_get_current_user();
	if ( $current_user->ID != get_option('analyticbridge_authenticated_user') ) {
		return;
	}

	$client = analytic_bridge_google_client();
	$accessToken = json_decode( get_option('analyticbridge_access_token') );
?>
	<!-- Google Analytics Embed API -->
	<script>
	(function(w,d,s,g,js,fjs){
	  g=w.gapi||(w.gapi={});g.analytics={q:[],ready:function(cb){this.q.push(cb)}};
	  js=d.createElement(s);fjs=d.getElementsByTagName(s)[0];
	  js.src='https://apis.google.com/js/platform.js';
	  fjs.parentNode.insertBefore(js,fjs);js.onload=function(){g.load('analytics')};
	}(window,document,'script'));
	</script>

	<script>
	gapi.analytics.ready(function() {
	  // 1: Authorize the user.
	  var CLIENT_ID = '<?php echo analyticbridge_client_id() ?>';

	  gapi.analytics.auth.authorize({
		container: 'auth-button',
		clientid: CLIENT_ID,
		serverAuth: {
			access_token: '<?php echo $accessToken->access_token;?>',
		}
	  });

	// 2: Create the view selector.
	jQuery('input[name=analyticbridge_setting_account_profile_id]').before(jQuery('<div id="google-view-selector"></div>'));
	var currentView = jQuery('input[name=analyticbridge_setting_account_profile_id]').attr('value');
	var viewSelector = new gapi.analytics.ViewSelector({
	  container: 'google-view-selector',
	  ids: {currentView}
	});

	// 3: Hook it all up.
	var loaded = false;
	viewSelector.once('change',function(ids) {
		viewSelector.on('change', function(ids) {
			jQuery('input[name=analyticbridge_setting_account_profile_id]').attr('value',ids);
		});
	});

	viewSelector.execute();

	});
	</script><?php
}
add_action( 'admin_footer', 'analyticbridge_blog_options_admin_head' );

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


	/*
	 * from here down is the cron job kick-off button
	 */

	// check if there is a client id/secret defined.
	if ( analyticbridge_client_id() && analyticbridge_client_secret() ) {

		/* Google has posted an authenticate code back to us. */
		if ( isset( $_GET['code'] ) ) {
			// ignore this section as we don't have any way to properly display it
			// If we're at the _GET['code'] part of the workflow, it's just noise
			// @see analyticbridge_google_authenticate_code_post();

		// No auth ticket loaded (yet).
		} elseif ( ! get_option('analyticbridge_access_token') ) {
			$client = analytic_bridge_google_client(false);
			echo "<a href='" . $client->createAuthUrl() . "'>" . __( "Connect", 'gapp' ) . "</a>";
		} else {
			$client = analytic_bridge_google_client();
			$service = new Google_Service_Oauth2($client);
			$user = $service->userinfo->get();
			echo __( "Connected as ", 'gapp' ) . $user->getEmail();
		}

		/* The user has asked us to run the cron. */
		if ( isset( $_GET['update'] ) ) {
			echo "<h3>Running Update...</h3>";
			echo "<pre>";
				_e( "Running cron...", 'gapp' );
				largo_anaylticbridge_cron(true);
			echo "</pre>";
		} else {
			_e( "<h3>Update Analytics</h3>", 'gapp' );;
			echo "<pre>";
				printf(
					'<a href="%1$s">%2$s</a>',
					admin_url('options-general.php?page=analytic-bridge&update'),
					__( 'Update analytics', 'gapp' )
				);
			echo "</pre>";
		}
	} else {
		_e( "Enter your Google API client details in the settings fields at the top of this page.", 'gapp' );
	}

	echo '</div>'; // div.wrap

}

/**
 * do header modification in actual header
 * @since 0.1.2
 * @link https://github.com/INN/Google-Analytics-Popular-Posts/issues/59
 */
function analyticbridge_google_authenticate_code_post() {
	if ( isset( $_GET['code'] ) ) {
		$client = analytic_bridge_authenticate_google_client( $_GET['code'] );
		// get the admin url for the analytics bridge
		$redirect = admin_url( 'options-general.php?page=analytic-bridge' );
		wp_safe_redirect( filter_var( $redirect, FILTER_SANITIZE_URL ) );
		exit;
	}
}
add_action( 'admin_init', 'analyticbridge_google_authenticate_code_post' );

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

		// Add Client Secret field
		add_settings_field(
			'analyticbridge_setting_api_token',
			'Connect Google Analytics',
			'analyticbridge_setting_api_token_connect_button',
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
		'Post halflife (in days)',
		'analyticbridge_setting_popular_posts_halflife_input',
		'analytic-bridge',
		'largo_anaytic_bridge_popular_posts_settings_section'
	); // ($id, $title, $callback, $page, $section, $args)

	// Register our settings.
	register_setting( 'analytic-bridge', 'analyticbridge_setting_popular_posts_halflife' );

}
add_action( 'admin_init', 'analyticbridge_register_options' );

/**
 * Intro text for our google api settings section.
 *
 * @since v0.1
 */
function largo_anaytic_bridge_api_settings_section_intro() {
	if( !analyticbridge_using_network_api_tokens() ) {
		_e( '<p>Enter the client id and client secret from your google developer console.</p>', 'gapp' );
		_e( '<p>Notes: ensure the <em>consent screen</em> has an email and product name defined, the <em>credentials screen</em> has a proper redirect uri defined and the analytic API is enabled on the <em>API</em> screen.', 'gapp' );
	} else {
		_e( '<em>API tokens already set by network.</em>', 'gapp' );
	}
}

/**
 * Intro text for our google property settings section.
 *
 * @since v0.1
 */
function largo_anaytic_bridge_account_settings_section_intro() {
	_e( '<p>Enter the property and profile that corresponds to this site.</p>', 'gapp' );
}

/**
 * Intro text for popular post settings
 *
 * @since v0.1
 */
function largo_anaytic_bridge_popular_posts_settings_section_intro() {
	_e( '<p>The post halflife is a measure of how long more-popular posts should remain in the popular posts list.</p>', 'gapp' );
	_e( '<p>For example, with a half-life setting of 14 days, a post that is two weeks old and has 200 views in the last 24 hours will be as valuable as a post that is 1 day old and has 100 views in the last 24 hours.</p>', 'gapp' );
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
 * Prints input field for Google Client Secret setting.
 *
 * @since v0.1
 */
function analyticbridge_setting_api_token_connect_button() {

	if ( analyticbridge_client_id() && analyticbridge_client_secret() ) {

		// API Tokens are defined.

		if ( ! get_option( 'analyticbridge_access_token' ) ) {

			// Analytic Bridge is NOT authenticated.
			// We still need it to create an authentication URL.
			$client = analytic_bridge_google_client( false );

			?>
				<a href="<?php echo $client->createAuthUrl() ?>"  class='google-button'><?php _e( 'Connect to Google Analytics', 'gapp' ); ?></a>
				<p class="description"><?php _e( 'A user with read access to your organization\'s Google Analytics profile must connect their Google Account.', 'gapp' ); ?></p>
			<?php

		} else {

			// Analytic Bridge is Authenticated.
			$client = analytic_bridge_google_client();
			$service = new Google_Service_Oauth2($client);
			$user = $service->userinfo->get();
			?>

			<div class="google-chip">
				<?php if ( !empty( $user->picture ) ) : ?>
				<span class="google-user-image">
					<img src="<?php echo $user->picture ?>" />
				</span>
				<?php endif; ?>
				<span class="google-user-name">
					<?php echo "Authenticated as " . $user->getName(); ?>
				</span>
			</div>
			<!-- todo: <p class="description">Disconnect this user.</p> -->
			<?php

			if ( get_option( 'analyticbridge_authenticated_user' ) ) {
				$userdata = get_userdata( get_option( 'analyticbridge_authenticated_user' ) );
				$username = $userdata->user_login;

				$authenticated_date = get_option( 'analyticbridge_authenticated_date_gmt' );
				$authenticated_date = get_date_from_gmt( $authenticated_date );
				$authenticated_date = mysql2date( 'M d, Y', $authenticated_date );
			?>
			<p class="description">
				<?php
					printf(
						__( 'by WordPress user "%1$s" on %2$s', 'gapp' ),
						$username,
						$authenticated_date
					);
				?>
			</p>
			<?php
			}
		}

	} else {
		?>
			<span class='google-button disabled'><?php _e( 'Google Analytics not connected', 'gapp' ); ?></span>
			<p class="description"><?php _e( 'You must enter a Google Client ID and Client Secret above, and press the "Save Changes" button, before you can connect Google Analytics.' , 'gapp' ); ?></p>
		<?php
	}

}

/**
 * Prints input field for Google Profile ID to pull data from.
 *
 * @since v0.1
 */
function analyticbridge_setting_account_profile_id_input() {

	if( $client = analytic_bridge_google_client(true,$e) ) {
		echo '<input name="analyticbridge_setting_account_profile_id" id="analyticbridge_setting_account_profile_id" type="text" value="' . get_option('analyticbridge_setting_account_profile_id') . '" class="regular-text" />';
	} else {
		echo "<p class='description'>Not authenticated.</p>";
	}

}

/**
 * Prints input field for Popular Post halflife.
 *
 * @since v0.1
 */
function analyticbridge_setting_popular_posts_halflife_input() {
	echo '<input name="analyticbridge_setting_popular_posts_halflife" id="analyticbridge_setting_popular_posts_halflife" type="number" value="' . get_option('analyticbridge_setting_popular_posts_halflife') . '" class="regular-text" />';
}
