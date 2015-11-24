<?php

class AnalyticBridgePopularPostWidget_Test extends WP_UnitTestCase {
	function setUp() {
		parent::setUp();
	}

	function test_registered() {
		var_log( $GLOBALS['wp_widget_factory']->widgets['WP_Widget_RSS'] );
		var_log( $GLOBALS['wp_widget_factory']->widgets['AnalyticBridgePopularPostWidget'] );
	}
}
