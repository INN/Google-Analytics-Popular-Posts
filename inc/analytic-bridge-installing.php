<?php
/**
 * Functions for activating and deactivating the plugin.
 *
 * @package Analytic Bridge
 */

/**
 * Initializes the databases for the analytic bridge.
 *
 * @since 0.1
 */
function analyticbridge_plugin_init($networkwide) {
            
	global $wpdb;

	if (function_exists('is_multisite') && is_multisite()) {
		
		// check if it is a network activation.
		if ($networkwide) {
		
			$old_blog = $wpdb->blogid;
			// Get all blog ids
			$blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
			foreach ($blogids as $blog_id) {
				switch_to_blog($blog_id);
				_analyticbridge_plugin_init();
			}
			switch_to_blog($old_blog);
			return;
		}   

	} 
	_analyticbridge_plugin_init();

}
register_activation_hook( __FILE__, 'analyticbridge_plugin_init' );

/**
 * Delegate the actual initalization code to a seperate function.
 * This is called for each blog instance on the network.
 * 
 * @since 0.1
 */
function _analyticbridge_plugin_init() {
	
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
			`querytime` datetime NOT NULL,
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
 * Calls _analyticbridge_plugin_deinit on each blog in the network.
 * We keep all options intact (including Google API token).
 * 
 * @since v0.1
 */
function analyticbridge_plugin_deinit($networkwide) {
            
	global $wpdb;

	if (function_exists('is_multisite') && is_multisite()) {
		
		// check if it is a network activation.
		if ($networkwide) {
		
			$old_blog = $wpdb->blogid;
			// Get all blog ids
			$blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
			foreach ($blogids as $blog_id) {
				switch_to_blog($blog_id);
				_analyticbridge_plugin_deinit();
			}
			switch_to_blog($old_blog);
			return;
		}   
	} 
	_analyticbridge_plugin_deinit();

}
register_deactivation_hook( __FILE__, 'analyticbridge_plugin_deinit' );

/**
 * Called on deinit for each blog in the network.
 * 
 * @since 0.1
 */
function _analyticbridge_plugin_deinit() {

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