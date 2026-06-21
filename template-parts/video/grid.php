<?php
/**
 * template-parts/video/grid.php — reusable grid container.
 *
 * Expects the active loop to be a video query (caller calls wp_reset_postdata).
 *
 * @package ktube
 */
?>
<ul class="ktube-video-grid" role="list">
	<?php while ( have_posts() ) : the_post(); ?>
		<?php get_template_part( 'template-parts/video/card' ); ?>
	<?php endwhile; ?>
</ul>
