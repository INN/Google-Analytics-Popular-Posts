<div class="col col--lg-<?php echo $grid_l_class; ?> col--sm-<?php echo $grid_s_class;?> col--xs-12">
	<div class="post <?php esc_attr_e( $classes ); ?>">
		<a href="<?php echo get_permalink(); ?>"><?php echo get_the_post_thumbnail(); ?></a>
		<a href="<?php echo get_permalink(); ?>"><strong class="heading"><?php echo get_the_title(); ?></strong></a>
		<ul class="post-details">
			<li><?php echo get_the_category()[0]->name; ?></li>
			<li>
				<a href="<?php echo get_author_posts_url( get_the_author_meta( 'ID' ) ); ?>"><?php echo get_the_author_meta( 'user_nicename' ); ?></a>
			</li>
			<li><?php echo get_the_date(); ?></li>
		</ul>
	</div>
</div>
