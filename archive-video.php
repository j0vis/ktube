<?php
/**
 * archive-video.php — paginated video grid archive.
 *
 * @package ktube
 */

get_header();

$ktube_paged = max( 1, (int) get_query_var( 'paged' ) );
$ktube_query = new WP_Query( array(
	'post_type'      => 'video',
	'posts_per_page' => 24,
	'paged'          => $ktube_paged,
) );
?>
<main id="primary" class="site-main ktube-archive ktube-archive--video">
	<header class="page-header">
		<h1 class="page-title"><?php esc_html_e( 'Videos', 'ktube' ); ?></h1>
	</header>
	<?php if ( $ktube_query->have_posts() ) : ?>
		<?php get_template_part( 'template-parts/video/grid' ); ?>
		<?php
		the_posts_pagination( array(
			'mid_size'  => 2,
			'prev_text' => __( 'Previous', 'ktube' ),
			'next_text' => __( 'Next', 'ktube' ),
		) );
		?>
	<?php else : ?>
		<?php get_template_part( 'template-parts/content', 'none' ); ?>
	<?php endif; ?>
	<?php wp_reset_postdata(); ?>
</main>
<?php
get_footer();
