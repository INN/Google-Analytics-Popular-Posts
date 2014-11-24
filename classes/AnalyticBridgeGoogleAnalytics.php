<?php

$AnalyticBridge = new AnalyticBridgeGoogleAnalytics();

Class AnalyticBridgeGoogleAnalytics {

	public function analytics($postid,$startdate) {

		global $wpdb;

		$this->result = $wpdb->get_results("

			--							---
			--  SELECT POPULAR POSTS 	---
			--							---

			SELECT

				*

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

		");


	}

}
