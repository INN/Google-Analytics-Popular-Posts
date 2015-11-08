<?php

Class AnayticBridgePopularPosts implements Iterator {

	// array of results.
	private $result;
	
	// used by iterator.
	private $position;
	
	// was a valid query executed?
	public $queried;

	// half life to use while querying.
	public $halflife;

	public $size;

	public $initalized = false;

	// returns an array of post ids that may be passed to a WP_Query object.
	public $ids;

	// interval to query over.
	private $interval;

	public function __construct() {

		$this->position = 0;
		$this->queried = false;
		$this->halflife = get_option('analyticbridge_setting_popular_posts_halflife');
		$this->size = 20;
		$this->initalized = true;

	}

	public function query() {

		global $wpdb;

		if($this->initalized) :
		
			// 1: Calculate a ratio coeffient 
			$tday = new DateTime('today',new DateTimeZone('America/Chicago'));
			$now = new DateTime('',new DateTimeZone('America/Chicago'));
	
			$interval = $tday->diff($now);

			// $minutes is hours*60 + $interval->i minutes
			$minutes = $interval->h * 60 + $interval->i;

			// $ratio is minutes passed today : minutes in today,
			// A measure of how long today has been
			$ratio = $minutes / (24*60);
	
			/* sql statement that pulls today's sessions, yesterday's
			 * sessions and a weighted average of them from the database.
			 *
			 * A note on the calculation of weighted pageviews, using a simplified equation:
			 *
			 * ( ( today's sessions * $ratio ) + ( yesterday's sessions * ( 1 - $ratio ) ) returns the post's sessions count, averaged between the last 24 hours.
			 * ( sessions count ) * 1/2 ^ ( ( post date - now ) / ( $halflife * 24 ) ) multiplies this post's sessions count by the half-life equation.
			 * The half-life equation raises 1/2 to the power n, where n is the number of half-lifes elapsed.
			 * $half-life is set in the plugin options in Settings > Analytic Bridge > Post halflife. It is the half-life of post popularity, in days.
			 * A post will count half as much every $halflife days.
			 * ( post date - now ) returns hours.
			 * ( $halflife * 24 ) returns hours.
			 * Dividing the post's age by the halflife-hours gives the number of half-lives that have elapsed, and thus the power that 1/2 should be raised to.
			 */
	
			$this->result = $wpdb->get_results("
	
				--							---
				--  SELECT POPULAR POSTS 	---
				--							---
	
				SELECT
	
					pg.pagepath AS pagepath,
					pg.id AS page_id,
					pst.id AS post_id,
					-- coalesce returns the first result that is not NULL:
					-- either the sessions count or zero
					coalesce(t.sessions, 0) AS today_pageviews,
					coalesce(y.sessions, 0) AS yesterday_pageviews,
	
					-- calculate the weighted session averages.
	
					( -- Calculate avg_pageviews in the last 24 hours
						(coalesce(t.sessions, 0) * $ratio) +
						(coalesce(y.sessions, 0) * (1 - $ratio))
					) AS `avg_pageviews`,
					
					( -- Calulate how many days_old the post is
						TIMESTAMPDIFF( hour, pst.post_date, NOW() ) - 1
					) AS `days_old`,
					
					( -- Calculate weighted_pageviews, using halflife to put less emphasis
					  -- on older posts
						(
							(coalesce(t.sessions, 0) * $ratio) + 
							(coalesce(y.sessions, 0) * (1 - $ratio))
						) * POWER( 
							1/2, 
							( TIMESTAMPDIFF( hour, pst.post_date, NOW() ) - 1 ) / ($this->halflife * 24)
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
					LIMIT $this->size
	
	
			");
	
			$this->queried = true;
			$this->setIds();

		endif;



	}

	/**
	 * Returns a score specified by the given $pid.
	 * 
	 * If the $pid is not in this list, returns false.
	 * 
	 * @since v0.1
	 */
	public function score($pid) {

		foreach($this as $popularPost) 
			if ($popularPost->post_id == $pid) 
				return $popularPost->weighted_pageviews;
			
		return false;

	}

	private function setIds() {
		$this->ids = array();
		foreach($this as $popPost) {
			$this->ids[] = $popPost->post_id;
		}
	}

	/** Region: Iterator functions */

	public function rewind() {

		if( !$this->queried ) {
			$this->query();
		}
		$this->position = 0;

	}

	public function current() {

		if( !$this->queried ) {
			$this->query();
		}

		return $this->result[$this->position];


	}

	public function key() {

		if( !$this->queried ) {
			$this->query();
		}

		return $this->position;

	}

	public function next() {

		if( !$this->queried ) {
			$this->query();
		}

		$this->position += 1;

		return $this->position;

	}

	public function valid() {

		if( !$this->queried ) {
			$this->query();
		}

		return isset($this->result[$this->position]);
		
	}

}
