<?php

/*
Plugin Name: 	Analytic Bridge
Description: 	Pull analytic data into your wordpress install.
Author: 		Will Haynes for INN
Author URI: 	http://twitter.com/innnerds
Version:		α.1
License: 		Copyright © 2013 INN

*/

// Prevent direct file access
if ( ! defined ( 'ABSPATH' ) ) {
	exit;
}

// Uncomment this to fake data.
define( 'FAKE_API' , FALSE );

/** Table defines */
global $wpdb;
define('METRICS_TABLE', $wpdb->prefix . "analyticbridge_metrics");
define('PAGES_TABLE', $wpdb->prefix . "analyticbridge_pages");

/** Include Google php client library. */

require_once( plugin_dir_path( __FILE__ ) . 'api/src/Google/Client.php');
require_once( plugin_dir_path( __FILE__ ) . 'api/src/Google/Service/Analytics.php');

/**
 * Initializes the databases for the analytic bridge.
 *
 * @since 1.0
 */
function analytic_bridge_plugin_init($networkwide) {
            
	global $wpdb;

	if (function_exists('is_multisite') && is_multisite()) {
		
		// check if it is a network activation.
		if ($networkwide) {
		
			$old_blog = $wpdb->blogid;
			// Get all blog ids
			$blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
			foreach ($blogids as $blog_id) {
				switch_to_blog($blog_id);
				_analytic_bridge_plugin_init();
			}
			switch_to_blog($old_blog);
			return;
		}   
	} 
	_analytic_bridge_plugin_init();

}
register_activation_hook( __FILE__, 'analytic_bridge_plugin_init' );

/**
 * Delegate the actual initalization code to a seperate function.
 * 
 */
function _analytic_bridge_plugin_init() {
	
	/* do not generate any output here. */

	global $wpdb;
	
	/* our globals aren't going to work because we switched blogs */
	$metrics_table 	= $wpdb->prefix . "analyticbridge_metrics";
	$pages_table 	= $wpdb->prefix . "analyticbridge_pages";

	/* Run sql to create the proper tables we need. */
	$result = $wpdb->query("

		--							---
		--  Create metrics table 	---
		--							---

		CREATE TABLE IF NOT EXISTS `" . $metrics_table . "` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`page_id` int(11) NOT NULL,
			`startdate` datetime NOT NULL,
			`enddate` datetime NOT NULL,
			`metric` varchar(64) NOT NULL,
			`value` varchar(64) NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `page_id` (`page_id`,`startdate`,`enddate`,`metric`)
		) ENGINE=InnoDB  DEFAULT CHARSET=latin1

	");

	/* Run sql to create the proper tables we need. */
	$result = $wpdb->query("

		--							---
		--  Create metrics table 	---
		--							---

		CREATE TABLE IF NOT EXISTS `" . $pages_table . "` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`pagepath` varchar(450) NOT NULL,
			`post_id` int(11) NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `pagepath` (`pagepath`)
		) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

	");

	// 2: Register a cron job.
	wp_schedule_event( time(), '10m', 'analyticbridge_hourly_cron');

}

/**
 * Run when plugin is deactivated
 * 
 * @since 1.0
 */
function analytic_bridge_plugin_deinit() {
	wp_clear_scheduled_hook('analyticbridge_hourly_cron');
}
register_deactivation_hook(__FILE__, 'analytic_bridge_plugin_deinit');

/**
 * Add a new 10 minute interval for cron jobs.
 * 
 */
function new_interval($interval) {

    $interval['10m'] = array('interval' => 1*60, 'display' => 'Once 10 minutes');
    return $interval;

}
add_filter('cron_schedules', 'new_interval');

/**
 * ----------------------------------------------------------------------------------------------
 * Step 1: Create a wordpress option page. 
 * ---------------------------------------------------------------------------------------------- 
 */

/**
 * Register a user option page for the Analytic Bridge
 *
 * @since 1.0
 */
function analyticbridge_plugin_menu() {
	add_options_page( 
		'Analytic Bridge Options', 					// $page_title title of the page.
		'Analytic Bridge', 							// $menu_title the text to be used for the menu.
		'manage_options', 							// $capability required capability for display.
		'analytic-bridge', 							// $menu_slug unique slug for menu.
		'analyticbridge_option_page_html' 	// $function callback.
		);
}
add_action( 'admin_menu', 'analyticbridge_plugin_menu' );


/**
 * Output the HTML for the Analytic Bridge option page.
 * 
 * If a $_GET variable is posted back to the page (by Google), it's stored as an option.
 *
 * @since 1.0
 */
function analyticbridge_option_page_html() {

	// Nice try.
	if ( !current_user_can( 'manage_options' ) )
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );

	echo '<div class="wrap">';
	echo '<h2>Largo Analytic Bridge</h2>';
	echo '<form method="post" action="options.php">';
	settings_fields( 'analytic-bridge' );
	do_settings_sections( 'analytic-bridge' );
	submit_button();
	echo '</form>';
	echo '</div>';

	if(get_option('analyticbridge_setting_api_client_id') && get_option('analyticbridge_setting_api_client_secret')) :

		// Create a Google Client.
		$client = new Google_Client();
		$client->setApplicationName("Analytic_Bridge_bhrld");
		$client->setClientId( get_option('analyticbridge_setting_api_client_id') );
		$client->setClientSecret( get_option('analyticbridge_setting_api_client_secret') );
		$client->setRedirectUri(site_url("/wp-admin/options-general.php?page=analytic-bridge"));
		$client->setAccessType("offline");
		$client->setScopes(
			array(
				'https://www.googleapis.com/auth/analytics.readonly', 
				'https://www.googleapis.com/auth/userinfo.email', 
				'https://www.googleapis.com/auth/userinfo.profile'));

		// Google has posted a code back to us.
		if ( isset($_GET['code']) ) {
			$client->authenticate($_GET['code']);
			update_option('analyticbridge_access_token',$client->getAccessToken());
			largo_pre_print( $client->getAccessToken() );
			update_option('analyticbridge_refresh_token',$client->getRefreshToken());
			largo_pre_print( get_option('analyticbridge_refresh_token'));
			// Todo: Add this back
			$redirect = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
			header('Location: ' . filter_var($redirect, FILTER_SANITIZE_URL));
		}

		// No auth ticket loaded (yet).
		
		if( !get_option('analyticbridge_access_token') ) :

			echo "<a href='" . $client->createAuthUrl() . "'>Connect</a>";

		// Someone has previouly authenticated!

		else :

			try {

				$client->setAccessToken( get_option('analyticbridge_access_token') );

				if( $client->isAccessTokenExpired() && get_option('analyticbridge_refresh_token') ) {
					$token = get_option('analyticbridge_refresh_token');
					largo_pre_print($token);
					$accesstoken = $client->refreshToken( $token );
					largo_pre_print( $accesstoken );
					update_option('analyticbridge_access_token',$client->getAccessToken());
				}
				

				$service = new Google_Service_Oauth2($client);
				$user = $service->userinfo->get();
				echo "Connected as " . $user->getEmail();
			
			} catch(Google_Auth_Exception $e) {
				
				largo_pre_print( get_option('analyticbridge_access_token') );
				largo_die($e);

			}

		endif;


	else :
	
		echo "Enter your API details";

	endif;

	// largo_anaylticbridge_cron();

}

/**
 * Registers options for the plugin.
 *
 * @since 1.0
 */
function analyticbridge_register_options() {

	/**
	 * Section 1: API settings section
	 */

	// Add a section for our analytic-bridge page.
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

	/**
	 * Section 2: Google Analytics Profile View ID
	 */

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

}
add_action('admin_init', 'analyticbridge_register_options');
  
/**
 * Intro text for our google api settings section.
 *
 * @since 1.0
 */
function largo_anaytic_bridge_api_settings_section_intro() {
	echo '<p>Enter the client id and client secret from your google developer console.</p>';
}

/**
 * Intro text for our google property settings section.
 *
 * @since 1.0
 */
function largo_anaytic_bridge_account_settings_section_intro() {
	echo '<p>Enter the property and profile that corresponds to this site.</p>';
}
 

/**
 * Prints input field for Google Client ID setting.
 *
 * @since 1.0
 */ 
function analyticbridge_setting_api_client_id_input() {
	echo '<input name="analyticbridge_setting_api_client_id" id="analyticbridge_setting_api_client_id" type="text" value="' . get_option('analyticbridge_setting_api_client_id') . '" class="regular-text" />';
}

/**
 * Prints input field for Google Client Secret setting.
 *
 * @since 1.0
 */ 
function analyticbridge_setting_api_client_secret_input() {
	echo '<input name="analyticbridge_setting_api_client_secret" id="analyticbridge_setting_api_client_secret" type="text" value="' . get_option('analyticbridge_setting_api_client_secret') . '" class="regular-text" />';
}

/**
 * Prints input field for Google Profile ID to pull data from.
 *
 * @since 1.0
 */ 
function analyticbridge_setting_account_profile_id_input() {
	echo '<input name="analyticbridge_setting_account_profile_id" id="analyticbridge_setting_account_profile_id" type="text" value="' . get_option('analyticbridge_setting_account_profile_id') . '" class="regular-text" />';
}



/** 
 * ----------------------------------------------------------------------------------------------
 * Step 2: Connect to Google & create an api token.
 * ----------------------------------------------------------------------------------------------
 */


function largo_die($e) {
	largo_pre_print($e);
}

function largo_pre_print($pre) {
	echo "<pre style='background:#fff;border:1px solid #eee;overflow-y:scroll'>";	
	print_r($pre);
	echo "</pre>";
}


/**
 * Attempts to authenticate with google's servers.
 * 
 * Pulls the access_token and refresh_token provided by google from the 
 * database and authenticates a google client.
 * 
 * On success, returns a google_client object that's pre authenticated and loaded
 * with the right scopes to access analytic data and email/name of the authenticated.
 * 
 * On failure, returns false.
 * 
 * @param array $e passed by reference. If provided, $e will contain error information if authentication fails.
 * @return Google_Client object on success, 'false' on failure.
 */
function analytic_bridge_authenticate(&$e = null) {

	// No auth ticket or refresh token.
	if( !(get_option('analyticbridge_access_token') && get_option('analyticbridge_refresh_token')) ) :
		
		if( $e ) {
			$e = array();
			$e['message'] = 'No access token. Get a system administrator to authenticate the Analytic Bridge.';
		}

		return false;

	// No client id or client secret.
	elseif( !(get_option('analyticbridge_setting_api_client_id') && get_option('analyticbridge_setting_api_client_secret')) ) :

		if( $e ) {
			$e = array();
			$e['message'] = 'No access token. Get a system administrator to authenticate the Analytic Bridge.';
		}

		return false;

	// We have everything we need.
	else : 

		// Create a Google Client.
		$client = new Google_Client();
		$client->setApplicationName("Analytic_Bridge_bhrld");
		$client->setClientId( get_option('analyticbridge_setting_api_client_id') );
		$client->setClientSecret( get_option('analyticbridge_setting_api_client_secret') );
		$client->setRedirectUri(site_url("/wp-admin/options-general.php?page=analytic-bridge"));
		$client->setAccessType("offline");
		$client->setScopes(
			array(
				'https://www.googleapis.com/auth/analytics.readonly', 
				'https://www.googleapis.com/auth/userinfo.email', 
				'https://www.googleapis.com/auth/userinfo.profile')
			);

		try {
			$client->setAccessToken( get_option('analyticbridge_access_token') );
			if( $client->isAccessTokenExpired() && get_option('analyticbridge_refresh_token') ) {
				$token = get_option('analyticbridge_refresh_token');
				$accesstoken = $client->refreshToken( $token );
				update_option('analyticbridge_access_token',$client->getAccessToken());
			}
			$service = new Google_Service_Oauth2($client);
		
		} catch(Google_Auth_Exception $error) {
			
			largo_pre_print( get_option('analyticbridge_access_token') );

			// return error information
			if ( $e ) $e = $error;
			return false;
		}

		// Return our client.
		return $client;

	endif;

}

/**
 * A cron job that loads data from google analytics into out analytic tables.
 * 
 * @uses get_site_url
 */
function largo_anaylticbridge_cron() {

	global $wpdb;

	AnalyticBridgeLog::log("\nBeginning analyticbridge_cron");

	// 1: Create an API client.
	$e = array();
	$client = analytic_bridge_authenticate($e);
	
	if($client == false) {
		AnalyticBridgeLog::log(" - Error creating api client:");
		AnalyticBridgeLog::log(" - Message:" . $e["message"]);
		AnalyticBridgeLog::log("End analyticbridge_cron\n");
		return;
	}

	$analytics = new Google_Service_Analytics($client);

	query_and_save_analytics($analytics,"today","now");
	query_and_save_analytics($analytics,"yesterday","yesterday");

	AnalyticBridgeLog::log(' - Analytic Bridge cron executed successfully');
	AnalyticBridgeLog::log("End analyticbridge_cron\n");

	return;

}
add_action( 'analyticbridge_hourly_cron', 'largo_anaylticbridge_cron' );

/**
 * Queries analytics and saves them to the table given the passed in start
 * and end dates.
 * 
 * If the start and end dates already exist in the table, it first clears
 * them out and refreshes the values.
 * 
 */
function query_and_save_analytics($analytics,$startdate,$enddate) {

	global $wpdb;

	$start = $startdate;

	// Make API call.
	// We use $start-$start because we're interested in one day.
	$report = $analytics->data_ga->get(
					get_option('analyticbridge_setting_account_profile_id'),
					$start,
					$start,
					"ga:sessions,ga:pageviews,ga:exits,ga:bounceRate,ga:avgSessionDuration,ga:avgTimeOnPage",
					array( 
					  "dimensions" => "ga:pagePath"
					)
	); // $ids, $startDate, $endDate, $metrics, $optParams

	error_log( print_r($report,TRUE) );

	// TODO: break here if API errors.
	// TODO: paginate.

	// Start a mysql transaction, in a try catch.
	try {
	
		$wpdb->query('START TRANSACTION');
		
		foreach($report->rows as $r) :
		
			// $r[0] - pagePath
	
			// remove index.php from the end of the string.
			$gapath = $r[0];
	
			// map google url path the wordpress path.
			$wpurl = get_site_url() . preg_replace('/index.php$/', '', $gapath);
	
			// try to determine the $postid.
			$postid = url_to_postid( $wpurl );
	
			// Insert into our 'post' table the pagepath and related postid
			// (if it doesn't exist).
			// Update `id` so that mysql_last_inserted is set.
			$wpdb->query( $wpdb->prepare(

					"INSERT INTO `" . PAGES_TABLE . "` (pagepath, post_id) 
						VALUES (%s, %s) 
    					ON DUPLICATE KEY 
    					UPDATE `id`=LAST_INSERT_ID(id)
        			", $gapath, $postid

				));

			$pageid = $wpdb->insert_id;
			$tstart = new DateTime($startdate);
			$tend = new DateTime($enddate);

			// $r[1] - ga:sessions
			// $r[2] - ga:pageviews
			// $r[3] - ga:exits
			// $r[4] - ga:bounceRate
			// $r[5] - ga:avgSessionDuration
			// $r[6] - ga:avgTimeOnPage

			// Insert ga:sessions
			$wpdb->query( $wpdb->prepare(

					"INSERT INTO `" . METRICS_TABLE . "` (page_id,startdate,enddate,metric,value)
						VALUES (%d,%s,%s,%s,%s)
					", $pageid, date_format($tstart, 'Y-m-d H:i:s'), date_format($tend, 'Y-m-d H:i:s'), 'ga:sessions', $r[1]

				));

			// Insert ga:pageviews
			$wpdb->query( $wpdb->prepare(

					"INSERT INTO `" . METRICS_TABLE . "` (page_id,startdate,enddate,metric,value)
						VALUES (%d,%s,%s,%s,%s)
					", $pageid, date_format($tstart, 'Y-m-d H:i:s'), date_format($tend, 'Y-m-d H:i:s'), 'ga:pageviews', $r[2]

				));

			// Insert ga:exits
			$wpdb->query( $wpdb->prepare(

					"INSERT INTO `" . METRICS_TABLE . "` (page_id,startdate,enddate,metric,value)
						VALUES (%d,%s,%s,%s,%s)
					", $pageid, date_format($tstart, 'Y-m-d H:i:s'), date_format($tend, 'Y-m-d H:i:s'), 'ga:exits', $r[3]

				));

			// Insert ga:bounceRate
			$wpdb->query( $wpdb->prepare(

					"INSERT INTO `" . METRICS_TABLE . "` (page_id,startdate,enddate,metric,value)
						VALUES (%d,%s,%s,%s,%s)
					", $pageid, date_format($tstart, 'Y-m-d H:i:s'), date_format($tend, 'Y-m-d H:i:s'), 'ga:bounceRate', $r[4]

				));

			// Insert ga:avgSessionDuration
			$wpdb->query( $wpdb->prepare(

					"INSERT INTO `" . METRICS_TABLE . "` (page_id,startdate,enddate,metric,value)
						VALUES (%d,%s,%s,%s,%s)
					", $pageid, date_format($tstart, 'Y-m-d H:i:s'), date_format($tend, 'Y-m-d H:i:s'), 'ga:avgSessionDuration', $r[5]

				));

			// Insert ga:avgTimeOnPage
			$wpdb->query( $wpdb->prepare(

					"INSERT INTO `" . METRICS_TABLE . "` (page_id,startdate,enddate,metric,value)
						VALUES (%d,%s,%s,%s,%s)
					", $pageid, date_format($tstart, 'Y-m-d H:i:s'), date_format($tend, 'Y-m-d H:i:s'), 'ga:avgTimeOnPage', $r[6]

				));

		endforeach;

		$wpdb->query('COMMIT');

	// Catch mysql exception. TODO: catch only mysql exceptions.
	} catch(Exception $e) {


		$wpdb->query('ROLLBACK');
		AnalyticBridgeLog::log(' - Error commiting sql to database.');
		AnalyticBridgeLog::log("End analyticbridge_cron\n");
		return;

	}

}

/**
 * =================================================================================================
 * DASHBOARD WIDGET
 * =================================================================================================
 */

/**
 * Add a widget to the dashboard.
 *
 * This function is hooked into the 'wp_dashboard_setup' action below.
 */
function example_add_dashboard_widgets() {

	wp_add_dashboard_widget(
                 'analyticbridge_popular_posts',         // Widget slug.
                 'Popular Posts',         // Title.
                 'analyticbridge_popular_posts_widget' // Display function.
        );	
}
add_action( 'wp_dashboard_setup', 'example_add_dashboard_widgets' );

/**
 * Outputs the HTML for the popular post widget.
 * 
 * An unordered list of 20 popular posts.
 * 
 * @since 1.0
 */
function analyticbridge_popular_posts_widget() {

	global $wpdb;

	// Display whatever it is you want to show.
	echo "<p>Most popular posts from Google Analytics, with a relative weighting average.</p>";


	// 1: Calculate a ratio coeffient 
	$tday = new DateTime('today',new DateTimeZone('America/Chicago'));
	$now = new DateTime('',new DateTimeZone('America/Chicago'));

	$interval = $tday->diff($now);

	$minutes = $interval->h * 60 + $interval->i;

	$ratio = $minutes / (24*60);

	/* sql statement that pulls todays sessions, yesterdays 		*/
	/* sessions and a weighted average of them from the database.	*/

	$halflife = 14;

	$result = $wpdb->get_results("

		--							---
		--  SELECT POPULAR POSTS 	---
		--							---

		SELECT

			pg.pagepath AS pagepath, 
			pg.id AS page_id,
			pst.id AS post_id, 
			coalesce(t.sessions, 0) AS today_pageviews, 
			coalesce(y.sessions, 0) AS yesterday_pageviews, 

			-- calculate the weighted session averages.

			( -- Calculate avg_pageviews.
				(coalesce(t.sessions, 0) * $ratio) + 
				(coalesce(y.sessions, 0) * (1 - $ratio))
			) AS `avg_pageviews`,
			
			( -- Calulate days_old
				TIMESTAMPDIFF( hour, pst.post_date, NOW() ) - 1
			) AS `days_old`,
			
			( -- Calculate weighted_pageviews
				(
					(coalesce(t.sessions, 0) * $ratio * .4) + 
					(coalesce(y.sessions, 0) * (1 - $ratio))
				) * POWER( 
					1/2, 
					( TIMESTAMPDIFF( hour, pst.post_date, NOW() ) - 1 ) / ($halflife * 24)
				)
			) AS `weighted_pageviews`

		FROM 

			`" . PAGES_TABLE . "` as `pg`

		LEFT JOIN (
		
			-- 
			-- Nested select returns today's sessions.
			--
		
			SELECT

				CAST(value as unsigned) as `sessions`, 
				page_id
			
			FROM

				`" . METRICS_TABLE . "` as m
			
			WHERE

				m.metric = 'ga:pageviews'
			
			AND

				m.startdate >= CURDATE() 
		
		) as `t` ON pg.id = t.page_id
		
		LEFT JOIN (
		
			-- 
			-- Nested select returns yesterday's sessions.
			--
		
			SELECT 
			
				CAST(value as unsigned) as `sessions`, 
				`page_id`
			
			FROM
			
				`" . METRICS_TABLE . "` as m
			
			WHERE
			
				m.metric = 'ga:pageviews'
			
			AND 
			
				m.startdate >= CURDATE() - 1
			
			AND
			
				m.enddate < CURDATE()
		
		
		) as `y` ON `pg`.`id` = `y`.`page_id`
		
		LEFT JOIN `" . $wpdb->prefix . "posts` as `pst`
		  
			ON `pst`.`id` = `pg`.`post_id`

		-- For now, they must be posts.

		WHERE `pst`.`post_type` = 'post'
			
			ORDER BY `weighted_pageviews` DESC
			LIMIT 50


	");


	// 3: Print list
	
	echo "<table>";
	$i = 1;
	
	foreach($result as $r) {
		echo "<tr>";
			echo "<td rowspan='2'>#" . $i . "</td>";
			echo "<td><b>" . get_the_title($r->post_id) . "</b><em> (" . $r->days_old . " hours ago)</em></td>";
		echo "</tr>";
		echo "<tr>";
			echo "<td style='color:#939393'><em>yesterday: " . $r->yesterday_pageviews . " views, ";
			echo "today: " . $r->today_pageviews . " views, ";
			echo "<b>weight:</b> " . number_format($r->weighted_pageviews,2) . "</em></td>";
		echo "</tr>";
		$i++;
	}

	echo "</table>";

}

/**
 * Static logging class for cron jobs.
 * 
 */
Class AnalyticBridgeLog {

	static $date = null;

	static function log($log) {

		if( !WP_DEBUG )
			return;
		
		if( $date === null ) {
			$date = new DateTime('now', new DateTimeZone('America/Chicago'));
		}
		
		$time = $date->format('D M d h:i:s');
		$log = "[$time] $log\n";

		file_put_contents('./log.txt', $log, FILE_APPEND);

	}

}


/**
 * Class to fake Google Analytics requests.
 * 
 * This class extends Google_Service_Analytics and fakes requests to the
 * Google API for debugging by generating data based on the global Wordpress
 * object.
 * 
 * @since 1.0
 */
class Google_Service_Analytics_Generator extends Google_Service_Analytics {

	public $data_ga;

	/**
	 * Construct a new Analytics Generator.
	 * 
	 * data_ga is set to ourselves. We handle the get function
	 * internally.
	 * 
	 * @since 1.0
	 */
	public function __construct(Google_Client $client) {
		$this->data_ga = $this;
	}

	/**
	 * Overrided Google_Service_Analytics_DataGa_Resource get function.
	 * 
	 * @return Google_Service_Analytics_GaData Object with proper row values.
	 */
	public function get($ids, $startDate, $endDate, $metrics, $optParams = array()) {

		$rows = array();
		$metrics = explode(",",$metrics);
		
		$the_query = new WP_Query(  array( 'post_type' => 'post') );

		if ( $the_query->have_posts() ) :

			while ( $the_query->have_posts() ) : $the_query->the_post();
	
				$r = array();
				$url = parse_url(get_permalink());
				$r[0] = $url['path'] . $url['query'];

				foreach($metrics as $m) {

					if( $m == 'ga:sessions' ) {
						$r[] = 100 + rand(-25,25);
					} else if( $m == 'ga:pageviews') {
						$r[] = 130 + rand(-25,25);
					} else if ( $m == 'ga:exits' ) {
						$r[] = 30 + rand(-10,10);
					} else if ( $m == 'ga:bounceRate' ) {
						$r[] = 60 + rand(-30,40);
					} else if ( $m == 'ga:avgSessionDuration' ) {
						$r[] = 231 + rand(-100,100);
					} else if ( $m = 'ga:avgTimeOnPage' ) {
						$r[] = 140 + rand(-40,130);
					} else {
						$r[] = 0;
					}

				}

				$rows[] = $r;
	
			endwhile;
		
			wp_reset_postdata();

		endif;

		$toRet = new Google_Service_Analytics_GaData();
		$toRet->rows = $rows;

		return $toRet;

	}

}


Class AnayticBridgeList implements Iterator {

	// array of results.
	private $result;
	
	// used by iterator.
	private $position;
	
	// was a valid query executed?
	private $queried;

	// half life to use while querying.
	private $halflife;

	// interval to query over.
	private $interval;

	public function __construct() {

		$this->position = 0;
		$this->queried = false;
		$this->halflife = 14;

    }

    private function query() {

    	global $wpdb;

    	// 1: Calculate a ratio coeffient 
		$tday = new DateTime('today',new DateTimeZone('America/Chicago'));
		$now = new DateTime('',new DateTimeZone('America/Chicago'));

		$interval = $tday->diff($now);

		$minutes = $interval->h * 60 + $interval->i;

		$ratio = $minutes / (24*60);

		/* sql statement that pulls todays sessions, yesterdays 		*/
		/* sessions and a weighted average of them from the database.	*/

		$halflife = 14;

    	$this->result = $wpdb->get_results("

			--							---
			--  SELECT POPULAR POSTS 	---
			--							---

			SELECT

				pg.pagepath AS pagepath, 
				pg.id AS page_id,
				pst.id AS post_id, 
				coalesce(t.sessions, 0) AS today_pageviews, 
				coalesce(y.sessions, 0) AS yesterday_pageviews, 

				-- calculate the weighted session averages.

				( -- Calculate avg_pageviews.
					(coalesce(t.sessions, 0) * $ratio) + 
					(coalesce(y.sessions, 0) * (1 - $ratio))
				) AS `avg_pageviews`,
				
				( -- Calulate days_old
					TIMESTAMPDIFF( hour, pst.post_date, NOW() ) - 1
				) AS `days_old`,
				
				( -- Calculate weighted_pageviews
					(
						(coalesce(t.sessions, 0) * $ratio) + 
						(coalesce(y.sessions, 0) * (1 - $ratio))
					) * POWER( 
						1/2, 
						( TIMESTAMPDIFF( hour, pst.post_date, NOW() ) - 1 ) / ($halflife * 24)
					)
				) AS `weighted_pageviews`

			FROM 

				`" . PAGES_TABLE . "` as `pg`

			LEFT JOIN (
			
				-- 
				-- Nested select returns today's sessions.
				--
			
				SELECT

					CAST(value as unsigned) as `sessions`, 
					page_id
				
				FROM

					`" . METRICS_TABLE . "` as m
				
				WHERE

					m.metric = 'ga:pageviews'
				
				AND

					m.startdate >= CURDATE() 
			
			) as `t` ON pg.id = t.page_id
			
			LEFT JOIN (
			
				-- 
				-- Nested select returns yesterday's sessions.
				--
			
				SELECT 
				
					CAST(value as unsigned) as `sessions`, 
					`page_id`
				
				FROM
				
					`" . METRICS_TABLE . "` as m
				
				WHERE
				
					m.metric = 'ga:pageviews'
				
				AND 
				
					m.startdate >= CURDATE() - 1
				
				AND
				
					m.enddate < CURDATE()
			
			
			) as `y` ON `pg`.`id` = `y`.`page_id`
			
			LEFT JOIN `" . $wpdb->prefix . "posts` as `pst`
			  
				ON `pst`.`id` = `pg`.`post_id`

			-- For now, they must be posts.

			WHERE `pst`.`post_type` = 'post'
				
				ORDER BY `weighted_pageviews` DESC
				LIMIT 50


		");

		$this->queried = true;

    }

    /** Region: Iterator functions */

	public function rewind() {

		if( !$this->queried ) {
			query();
		}
		$this->position = 0;

	}

	public function current() {

		if( !$this->queried ) {
			query();
		}

		return $this->result[$this->position];


	}

	public function key() {

		if( !$this->queried ) {
			query();
		}

		return $this->position;

	}

	public function next() {

		if( !$this->queried ) {
			query();
		}

		$this->position += 1;

		return $this->position;

	}

	public function valid() {

		if( !$this->queried ) {
			query();
		}

		return isset($this->result[$this->position]);
		
	}


}