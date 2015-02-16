<?php

/*
Plugin Name: 	Analytic Bridge
Description: 	Pull analytic data into your wordpress install.
Author: 		Will Haynes for INN
Author URI: 	http://twitter.com/innnerds
Version:		0.1
License: 		Copyright © 2013 INN

*/

// Prevent direct file access
if ( ! defined ( 'ABSPATH' ) ) {
	exit;
}

// Toggle this to generate fake data.
define( 'FAKE_API' , FALSE );

/** Table defines */
global $wpdb;
define('METRICS_TABLE', $wpdb->prefix . "analyticbridge_metrics");
define('PAGES_TABLE', $wpdb->prefix . "analyticbridge_pages");

/** Include Google PHP client library. */

require_once( plugin_dir_path( __FILE__ ) . 'api/src/Google/Client.php');
require_once( plugin_dir_path( __FILE__ ) . 'api/src/Google/Service/Analytics.php');
require_once( plugin_dir_path( __FILE__ ) . 'AnalyticBridgeGoogleClient.php');
require_once( plugin_dir_path( __FILE__ ) . 'Analytic_Bridge_Service.php');
/**
 * Registers admin option page and populates with
 * plugin settings.
 */ 
require_once( plugin_dir_path( __FILE__ ) . 'AnalyticBridgeOptions.php');

include_once(plugin_dir_path( __FILE__ ) .'classes/AnalyticsDashWidget.php');
include_once(plugin_dir_path( __FILE__ ) .'classes/AnalyticsPopularWidget.php');


/**
 * =================================================================================================
 * Region :: INSTALLING & UNINSTALLING
 * =================================================================================================
 */

/**
 * Initializes the databases for the analytic bridge.
 *
 * @since 0.1
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
 * This is called for each blog instance on the network.
 * 
 * @since 0.1
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

	update_option('analyticbridge_setting_popular_posts_halflife',14);

}

/**
 * Drop database tables when uninstalling the plugin.
 * 
 * Calls _analytic_bridge_plugin_deinit on each blog in the network.
 * 
 * Since we don't have a plugin upgrade format currently we must drop the tables
 * in order to support users who don't have database access. Data will be rebuilt in
 * first cron job after reinitializing.
 * 
 * We keep all options intact (including Google API token).
 * 
 * @since v0.1
 */
function analytic_bridge_plugin_deinit($networkwide) {
            
	global $wpdb;

	if (function_exists('is_multisite') && is_multisite()) {
		
		// check if it is a network activation.
		if ($networkwide) {
		
			$old_blog = $wpdb->blogid;
			// Get all blog ids
			$blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
			foreach ($blogids as $blog_id) {
				switch_to_blog($blog_id);
				_analytic_bridge_plugin_deinit();
			}
			switch_to_blog($old_blog);
			return;
		}   
	} 
	_analytic_bridge_plugin_deinit();

}
register_deactivation_hook( __FILE__, 'analytic_bridge_plugin_deinit' );

/**
 * Called on deinit for each blog in the network.
 * 
 * @since 0.1
 */
function _analytic_bridge_plugin_deinit() {

	global $wpdb;
	
	/* our globals aren't going to work because we switched blogs */
	$metrics_table 	= $wpdb->prefix . "analyticbridge_metrics";
	$pages_table 	= $wpdb->prefix . "analyticbridge_pages";

	/* Run sql to drop created tables */
	$result = $wpdb->query("

		--							---
		--  Drop metrics table 	---
		--							---

		DROP TABLE  `" . $metrics_table . "` 

	");

	/* Run sql to drop created tables */
	$result = $wpdb->query("

		--							---
		--  Drop pages table 	---
		--							---

		DROP TABLE `" . $pages_table . "` ");

	// Clear hook
	wp_clear_scheduled_hook('analyticbridge_hourly_cron');

}

/**
 * Add new intervals for cron jobs.
 * 
 * @since 0.1
 */
function new_interval($interval) {

	$interval['10m'] = array('interval' => 15*60, 'display' => 'Once every 10 minutes');
	$interval['15m'] = array('interval' => 15*60, 'display' => 'Once every 15 minutes');
    $interval['20m'] = array('interval' => 15*60, 'display' => 'Once every 20 minutes');
    $interval['30m'] = array('interval' => 15*60, 'display' => 'Once every 30 minutes');
    $interval['45m'] = array('interval' => 15*60, 'display' => 'Once every 45 minutes');
    return $interval;

}
add_filter('cron_schedules', 'new_interval');


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
 * A cron job that loads data from google analytics into out analytic tables.
 * 
 * @since v0.2
 * 
 * @uses get_site_url
 * 
 * @param boolean $verbose set to true to print 
 */
function largo_anaylticbridge_cron($verbose = false) {

	global $wpdb;

	$rustart = getrusage(); // track usage.

	if($verbose) echo("\nBeginning analyticbridge_cron...\n\n");

	if(!(analyticbridge_client_id() && analyticbridge_client_secret() && get_option('analyticbridge_setting_account_profile_id'))) {
		exit;
	}

	// 1: Create an API client.

	$client = analytic_bridge_google_client(true,$e);
			
	if($client == false) {
		if($verbose) echo("[Error] creating api client, message: " . $e["message"] . "\n");
		if($verbose) echo("\nEnd analyticbridge_cron\n");
		return;
	}

	$analytics = new Analytic_Bridge_Service($client);

	query_and_save_analytics($analytics,"today",$verbose);
	query_and_save_analytics($analytics,"yesterday",$verbose);

	if($verbose) echo("Analytic Bridge cron executed successfully\n");
	if($verbose) echo("\nEnd analyticbridge_cron\n");

	// Script end
	function rutime($ru, $rus, $index) {
    	return ($ru["ru_$index.tv_sec"]*1000 + intval($ru["ru_$index.tv_usec"]/1000)) - ($rus["ru_$index.tv_sec"]*1000 + intval($rus["ru_$index.tv_usec"]/1000));
	}
		
	$ru = getrusage();
	if($verbose) echo "\nThis process used " . rutime($ru, $rustart, "utime") . " ms for its computations\n";
	if($verbose) echo "It spent " . rutime($ru, $rustart, "stime") . " ms in system calls\n";


	return;

}
add_action( 'analyticbridge_hourly_cron', 'largo_anaylticbridge_cron' );

/**
 * Queries analytics and saves them to the table for the given start date.
 * 
 * If the start and end dates already exist in the table, it first clears
 * them out and refreshes the values.
 * 
 * @param boolean $verbose whether we should print while we do it.
 */
function query_and_save_analytics($analytics,$startdate,$verbose = false) {

	global $wpdb;

	$start = $startdate;

	if($verbose) echo "Making a call to ga:get...\n";

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

	if($verbose) {
		echo "returned:\n\n";
		echo " - type: " . gettype($report) . "\n";
		echo " - itemsPerPage: " . $report->itemsPerPage . "\n";
		echo " - row count: " . sizeof($report->rows) . "\n\n";
	} 

	// Get google's timezone. Save it in case we need it later.

	$gaTimezone = $analytics->timezone($report);
	update_option('analyticbridge_analytics_timezone',$gaTimezone);

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

			// Adjust things to save based on the google analytics timezone.
			$tstart = new DateTime($startdate,new DateTimeZone($gaTimezone));
			$tend = new DateTime($startdate,new DateTimeZone($gaTimezone));

			// $r[1] - ga:sessions
			// $r[2] - ga:pageviews
			// $r[3] - ga:exits
			// $r[4] - ga:bounceRate
			// $r[5] - ga:avgSessionDuration
			// $r[6] - ga:avgTimeOnPage

			// Insert ga:sessions
			$wpdb->query( $wpdb->prepare(

					"INSERT INTO `" . METRICS_TABLE . "` (page_id,startdate,enddate,metric,value)
						VALUES (%d,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE `id`=LAST_INSERT_ID(id),`value`=%s
					", $pageid, date_format($tstart, 'Y-m-d'), date_format($tend, 'Y-m-d'), 'ga:sessions', $r[1], $r[1]

				));

			// Insert ga:pageviews
			$wpdb->query( $wpdb->prepare(

					"INSERT INTO `" . METRICS_TABLE . "` (page_id,startdate,enddate,metric,value)
						VALUES (%d,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE `id`=LAST_INSERT_ID(id),`value`=%s
					", $pageid, date_format($tstart, 'Y-m-d'), date_format($tend, 'Y-m-d'), 'ga:pageviews', $r[2], $r[2]

				));

			// Insert ga:exits
			$wpdb->query( $wpdb->prepare(

					"INSERT INTO `" . METRICS_TABLE . "` (page_id,startdate,enddate,metric,value)
						VALUES (%d,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE `id`=LAST_INSERT_ID(id),`value`=%s
					", $pageid, date_format($tstart, 'Y-m-d'), date_format($tend, 'Y-m-d'), 'ga:exits', $r[3], $r[3]

				));

			// Insert ga:bounceRate
			$wpdb->query( $wpdb->prepare(

					"INSERT INTO `" . METRICS_TABLE . "` (page_id,startdate,enddate,metric,value)
						VALUES (%d,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE `id`=LAST_INSERT_ID(id),`value`=%s
					", $pageid, date_format($tstart, 'Y-m-d'), date_format($tend, 'Y-m-d'), 'ga:bounceRate', $r[4], $r[4]

				));

			// Insert ga:avgSessionDuration
			$wpdb->query( $wpdb->prepare(

					"INSERT INTO `" . METRICS_TABLE . "` (page_id,startdate,enddate,metric,value)
						VALUES (%d,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE `id`=LAST_INSERT_ID(id),`value`=%s
					", $pageid, date_format($tstart, 'Y-m-d'), date_format($tend, 'Y-m-d'), 'ga:avgSessionDuration', $r[5], $r[5]

				));

			// Insert ga:avgTimeOnPage
			$wpdb->query( $wpdb->prepare(

					"INSERT INTO `" . METRICS_TABLE . "` (page_id,startdate,enddate,metric,value)
						VALUES (%d,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE `id`=LAST_INSERT_ID(id),`value`=%s
					", $pageid, date_format($tstart, 'Y-m-d'), date_format($tend, 'Y-m-d'), 'ga:avgTimeOnPage', $r[6], $r[6]

				));

		endforeach;

		$wpdb->query('COMMIT');

	// Catch mysql exception. TODO: catch only mysql exceptions.
	} catch(Exception $e) {

		$wpdb->query('ROLLBACK');
		if($verbose) echo("[Error] commiting sql to database.\n");
		if($verbose) echo("\nEnd analyticbridge_cron\n");
		return;

	}

}

/**
 * Static logging class for cron jobs.
 * 
 */
Class AnalyticBridgeLog {

	static $date = null;
	static $errorLog = false;
	static $printLog = false;

	static function log($log) {

		if( !WP_DEBUG )
			return;
		
		if( $date === null ) {
			$date = new DateTime('now', new DateTimeZone('America/Chicago'));
		}
		
		$time = $date->format('D M d h:i:s');
		$log = "[$time] $log\n";

	}

}


/**
 * Class to fake Google Analytics requests.
 * 
 * This class extends Google_Service_Analytics and fakes requests to the
 * Google API for debugging by generating data based on the global Wordpress
 * object.
 * 
 * Needs work.
 * 
 * @since v0.1
 */
class Google_Service_Analytics_Generator extends Google_Service_Analytics {

	public $data_ga;

	/**
	 * Construct a new Analytics Generator.
	 * 
	 * data_ga is set to ourselves. We handle the get function
	 * internally.
	 * 
	 * @since v0.1
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

include_once( plugin_dir_path( __FILE__ ) . 'classes/AnalyticBridgePopularPosts.php');