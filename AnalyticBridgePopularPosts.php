<?php

Class AnayticBridgePopularPosts implements Iterator {

	// array of results.
	private $result;
	
	// used by iterator.
	private $position;
	
	// was a valid query executed?
	private $queried;

	// half life to use while querying.
	private $halflife;

	private $size;

	// interval to query over.
	private $interval;

	public function __construct() {

		$this->position = 0;
		$this->queried = false;
		$this->halflife = get_option('analyticbridge_setting_popular_posts_halflife');
		$this->size = 20;

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
					(coalesce(t.sessions, 0) * $ratio * .5) + 
					(coalesce(y.sessions, 0) * (1 - $ratio))
				) AS `avg_pageviews`,
				
				( -- Calulate days_old
					TIMESTAMPDIFF( hour, pst.post_date, NOW() ) - 1
				) AS `days_old`,
				
				( -- Calculate weighted_pageviews
					(
						(coalesce(t.sessions, 0) * $ratio * .5) + 
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