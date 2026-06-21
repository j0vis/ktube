<?php
/**
 * archive-photo.php — paginated photo-set grid.
 * Brief §3.3: cover image, title, photo count, actor/channel badges.
 *
 * @package ktube
 */

get_header();

$ktube_paged = max( 1, (int) get_query_var( 'paged' ) );
$ktube_query = new WP_Query( array(
	'post_type'      => 'photo',
	'posts_per_page' => 24,
	'paged'          => $ktube_paged,
) );
?>
<main id="primary" class="site-main ktube-archive ktube-archive--photo">
	<header class="page-header">
		<h1 class="page-title"><?php esc_html_e( 'Photos', 'ktube' ); ?></h1>
	</header>
	<?php if ( $ktube_query->have_posts() ) : ?>
		<ul class="ktube-photo-grid" role="list">
			<?php while ( $ktube_query->have_posts() ) : $ktube_query->the_post(); ?>
				<?php get_template_part( 'template-parts/photo/card' ); ?>
			<?php endwhile; ?>
		</ul>
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
