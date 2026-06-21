<?php
/**
 * archive-blog.php — paginated blog list w/ category/tag filtering affordance.
 *
 * Brief §3.2: archive w/ excerpt + featured image + category/tag filtering (UI only
 * in Phase 4; live filtering wires in Phase 5 Customizer + custom tax_query param).
 *
 * @package ktube
 */

get_header();

$ktube_paged = max( 1, (int) get_query_var( 'paged' ) );

$ktube_query = new WP_Query( array(
	'post_type'      => 'blog',
	'posts_per_page' => 12,
	'paged'          => $ktube_paged,
) );
?>
<main id="primary" class="site-main ktube-archive ktube-archive--blog">
	<header class="page-header">
		<h1 class="page-title"><?php esc_html_e( 'Blog', 'ktube' ); ?></h1>
	</header>
	<section class="ktube-blog-list" aria-label="<?php esc_attr_e( 'Blog posts', 'ktube' ); ?>">
		<?php if ( $ktube_query->have_posts() ) : ?>
			<?php while ( $ktube_query->have_posts() ) : $ktube_query->the_post(); ?>
				<?php get_template_part( 'template-parts/blog/card' ); ?>
			<?php endwhile; ?>
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
	</section>
</main>
<?php
get_footer();
