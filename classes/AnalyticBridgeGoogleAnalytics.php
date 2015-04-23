<?php

$AnalyticBridge = new AnalyticBridgeGoogleAnalytics();

Class AnalyticBridgeGoogleAnalytics {

	public function __construct() {
		// silence is golden.
	}

	/**
	 * Get a metric for a post.
	 * 
	 * Queries the analytic bridge database to find metrics for a post.
	 * 
	 * @param Mixed $post (optional) the post to query for. Defaults to global. 
	 * @param String $metric (optional) a metric to query for. Default is 'ga:session'
	 * @param Mixed $date (optional) the date to query for. Default is today. Value is passed into a new DateTime() object.
	 * 
	 * @return mixed. Returns 'false' if data not found. Otherwise, returns an integer.
	 */
	public function metric($post = null,$metric = null,$date = null) {

		global $wpdb;

		$post = get_post($post);

		if($metric == null) {
			$metric = "ga:sessions";
		}

		if($date == null) {
			$date = new DateTime('now');
		} else {
			$date = new DateTime($date);
		}

		$result = $wpdb->get_results(" 

			SELECT
				* 
			FROM " .
				PAGES_TABLE . " as `p` 
			JOIN " .
				METRICS_TABLE . " as `m` 
				ON m.metric = '$metric' AND p.id = m.page_id
			WHERE 
				m.startdate = '" . date_format($date,'Y-m-d') . "'
				AND
				m.enddate = '" . date_format($date,'Y-m-d') . "'
				AND 
				p.post_id = {$post->ID}

			");

		if(count($result) > 0)
			return $result[0]->value;

	}

}
