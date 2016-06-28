<?php

require_once( plugin_dir_path( __FILE__ ) . 'AnalyticBridgePopularPosts.php');

/**
 * The base widget for the Analytics bridge Plugin
 *
 * @since 0.2
 * @link https://github.com/INN/analytic-bridge/issues/26
 */
class AnalyticBridgePopularPostWidget extends WP_Widget {

	private $popPosts;

	/**
	 * Sets up the widget
	 *
	 * @since 0.1
	 */
	public function __construct() {

		parent::__construct(
			'analytic-bridge-popular-posts', // Base ID
			__( 'Google Analytics Popular Posts', 'largo' ), // Name
			array( 'description' => __( 'List popular posts', 'largo' ), ) // Args
		);
		// widget actual processes
		if( is_active_widget( false, false, $this->id_base ) ) {
			wp_enqueue_style( 'abp-popular-posts-widget', plugins_url( 'css/abp-popular-posts-widget.css', __DIR__ ), 'largo-stylesheet' );
		}
	}

	/**
	 * Output the widget
	 *
	 * This widget function was copied from the Nonprofit Quarterly theme: https://bitbucket.org/projectlargo/theme-npq/src/5b7661348039e13cce66356eb85ddc975118aec6/inc/widgets/npq-popular-posts.php?at=master&fileviewer=file-view-default
	 * It contains additional bugfixes: https://github.com/INN/analytic-bridge/issues/26
	 *
	 * @since 0.2
	 * @uses $this->compare_popular_posts
	 * @param array $args
	 * @param array $instance
	 */
	function widget( $args, $instance ) {

		global $shown_ids, $post; // an array of post IDs already on a page so we can avoid duplicating posts
		$preserve = $post;

		/**
		 * Allow changing of the name of whatever we're calling posts.
		 *
		 * Also used in the form() method in this class
		 *
		 * @filter
		 * @link https://github.com/INN/analytic-bridge/issues/43
		 * @param string 'Posts'
		 */
		$posts_term = apply_filters( 'abp-widget-posts-term', __( 'Posts', 'largo' ) );

		/**
		 * Filter the Analytics Bridge Plugin widget $args and $instance variables on page generation time.
		 *
		 * If you would like to ensure than an Analytics Popular Posts widget placed in a particular zone always displays certain options, this is how.
		 * When adding your filter, be sure to specify that the filter accepts 2 arguments:
		 *
		 *     add_action('abp-widget-force-unsaved-options', 'largo_filter_abp_article_bottom', 10, 2);
		 *                                                                                           ^
		 *
		 * @filter
		 * @link https://github.com/INN/analytic-bridge/issues/13
		 * @param array $instance this particular widget's settings
		 * @param array $args the widget arguments
		 */
		$instance = apply_filters( 'abp-widget-force-unsaved-options', $instance, $args );

		extract( $args );

		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Recent ' . $posts_term, 'largo') : $instance['title'], $instance, $this->id_base );

		/*
		 * Start drawing the widget
		 */
		echo $before_widget;

		if ( $title ) {
			echo $before_title . $title . $after_title;
		}

		$olul =  isset( $instance['olul'] ) ? $instance['olul'] : 'ul';

		// Start the list
		echo sprintf( '<%s class="count-%d">',
			$olul,
			$instance['num_posts']
		);

		$this->popPosts = new AnayticBridgePopularPosts();
		$this->popPosts->size = $instance['num_posts'];
		$this->popPosts->query();

		$query_args = array(
			'post__in' => $this->popPosts->ids,
			'ignore_sticky_posts' => true,
			'showposts' => $instance['num_posts'],
		);

		// Get posts, sort them using the compare_popular_posts function defined elsewhere in this plugin.
		$my_query = new WP_Query( $query_args );
		usort( $my_query->posts,array( $this, 'compare_popular_posts' ) );

		if ( $my_query->have_posts() ) {

			$output = '';

			while ( $my_query->have_posts() ) {
				$my_query->the_post();
				$shown_ids[] = get_the_ID();

				// wrap the items in li's.
				$classes = join( ' ', get_post_class() );
				$output .= '<li class="' . $classes . '">';

				// the headline
				$output .= '<h5><a href="' . get_permalink() . '">' . get_the_title() . '</a></h5>';

				// close the item
				$output .= '</li>';
			}

			// print all of the items
			echo $output;

		} else {
			printf(
				__( '<p class="error"><strong>No %1$s found.</strong></p>', 'largo' ),
				strtolower( $posts_term )
			);
		} // end more featured posts

		// close the ul if we're just showing a list of headlines
		if ( $olul == 'ul' ) {
			echo '</ul>';
		} else {
			echo '</ol>';
		}

		echo $after_widget;

		// Restore global $post
		wp_reset_postdata();
		$post = $preserve;
	}

	/**
	 * Outputs the options form on admin
	 *
	 * @param array $instance The widget options
	 */
	public function form( $instance ) {

		/**
		 * Allow changing of the name of whatever we're calling posts.
		 *
		 * Also used in the widget() method in this class
		 *
		 * @filter
		 * @link https://github.com/INN/analytic-bridge/issues/43
		 * @param string 'Posts'
		 */
		$posts_term = apply_filters( 'abp-widget-posts-term', __('Posts') );

		$defaults = array(
			'title' => __('Recent', 'largo') . ' ' . $posts_term,
			'num_posts' => 3,
			'olul' => 'ol',
		);
		$instance = wp_parse_args( (array) $instance, $defaults );
		$olul =  isset( $instance['olul'] ) ? $instance['olul'] : 'ul';
		?>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'largo' ); ?></label>
			<input id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>" style="width:90%;" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'num_posts' ); ?>"><?php _e( 'Number of posts to show:', 'largo' ); ?></label>
			<input id="<?php echo $this->get_field_id( 'num_posts' ); ?>" name="<?php echo $this->get_field_name( 'num_posts' ); ?>" value="<?php echo $instance['num_posts']; ?>" style="width:90%;" type="number"/>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'olul' ); ?>"><?php _e( 'Display as an ordered list (with numbers) or as an unordered list?', 'largo' ); ?></label>
			<select id="<?php echo $this->get_field_id( 'olul' ); ?>" name="<?php echo $this->get_field_name( 'olul' ); ?>" class="widefat">
				<option <?php selected( $instance['olul'], 'ul' ); ?> value="ul"><?php _e( 'Unordered list', 'largo' ); ?></option>
				<option <?php selected( $instance['olul'], 'ol' ); ?> value="ol"><?php _e( 'Ordered list', 'largo' ); ?></option>
			</select>
		</p>

	<?php
	}

	/**
	 * Processing widget options on save
	 *
	 * @param array $new_instance The new options
	 * @param array $old_instance The previous options
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = sanitize_text_field( $new_instance['title'] );
		$instance['num_posts'] = intval( $new_instance['num_posts'] );
		$instance['olul'] = sanitize_text_field( $new_instance['olul'] );
		return $instance;
	}

	/**
	 * Sort comparison.
	 *
	 * @since 0.2
	 */
	private function compare_popular_posts( $a, $b ) {

		$ascore = $this->popPosts->score( $a->ID );
		$bscore = $this->popPosts->score( $b->ID );

		return ( $ascore > $bscore ) ? -1 : 1;

	}
}

add_action( 'widgets_init', function(){
	register_widget( 'AnalyticBridgePopularPostWidget' );
});
