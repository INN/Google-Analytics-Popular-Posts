<?php


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
function analyticbridge_add_dashboard_widgets() {

	wp_add_dashboard_widget(
                 'analyticbridge_popular_posts',         	// Widget slug.
                 'Popular Posts',         					// Title.
                 'analyticbridge_popular_posts_widget' 		// Display function.
        );	
}
add_action( 'wp_dashboard_setup', 'analyticbridge_add_dashboard_widgets' );

/**
 * Outputs the HTML for the popular post widget.
 * 
 * An unordered list of 20 popular posts.
 * 
 * @since 0.1
 */
function analyticbridge_popular_posts_widget() {

	global $wpdb;

	// Display whatever it is you want to show.
	echo "<p>Most popular posts from Google Analytics, with a relative weighting average.</p>";

	$popPosts = new AnayticBridgePopularPosts();
	$popPosts->size = "5";

	// 3: Print list
	
	echo "<table>";
	$i = 1;
	
	foreach($popPosts as $r) {
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