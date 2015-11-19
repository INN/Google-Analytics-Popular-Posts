<?php

require_once( plugin_dir_path( __FILE__ ) . 'AnalyticBridgePopularPosts.php');

$popPosts = new AnayticBridgePopularPosts();


class AnalyticBridgePopularPostWidget extends WP_Widget {


	/**
	 * Sets up the widgets name etc
	 */
	public function __construct() {

		parent::__construct(
			'analytic-bridge-popular-posts', // Base ID
			__( 'Analytic Bridge Popular Posts', 'analytic-bridge' ), // Name
			array( 'description' => __( 'List popular posts', 'analytic-bridge' ), ) // Args
		);
		// widget actual processes
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) { ?>
		
		<div class="popular-post-widget">

			<h3>Popular Posts</h3>
			<ul>

			<?php

			$popPosts = new AnayticBridgePopularPosts();
			$popPosts->size = 7;

			foreach($popPosts as $r) : ?>

				<li><a href="#" title="<?php echo get_the_title($r->post_id); ?>" class="">
					<?php echo get_the_title($r->post_id); ?>
				</a></li>

			<?php endforeach; ?>
	
			</ul>

		</div>


	<? }

	/**
	 * Outputs the options form on admin
	 *
	 * @param array $instance The widget options
	 */
	public function form( $instance ) {
		// outputs the options form on admin
	}

	/**
	 * Processing widget options on save
	 *
	 * @param array $new_instance The new options
	 * @param array $old_instance The previous options
	 */
	public function update( $new_instance, $old_instance ) {
		// processes widget options to be saved
	}
}

add_action( 'widgets_init', function(){
	 register_widget( 'AnalyticBridgePopularPostWidget' );
});