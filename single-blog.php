<?php
/**
 * single-blog.php — long-form blog post template.
 * Brief §3.2: distinct content type (NOT video tile). Article schema emitted by
 * includes/seo/schema.php::ktube_render_blog_article_schema via wp_head.
 * OG + Twitter Card via ktube_render_open_graph_meta / ktube_render_twitter_card_meta.
 *
 * @package ktube
 */

get_header();
?>
<main id="primary" class="site-main ktube-single ktube-single--blog">
	<?php while ( have_posts() ) : the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'ktube-article' ); ?>>
			<header class="ktube-article__header">
				<h1 class="ktube-article__title"><?php the_title(); ?></h1>
				<p class="ktube-article__meta">
					<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>" class="ktube-article__date">
						<?php echo esc_html( get_the_date() ); ?>
					</time>
					<?php
					$ktube_cats = get_the_term_list( get_the_ID(), 'category', '<span class="ktube-article__cat">', ' · ', '</span>' );
					if ( $ktube_cats && ! is_wp_error( $ktube_cats ) ) {
						echo wp_kses_post( $ktube_cats );
					}
					?>
				</p>
				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="ktube-article__hero">
						<?php the_post_thumbnail( 'ktube-hero' ); ?>
					</figure>
				<?php endif; ?>
			</header>
			<div class="ktube-article__body entry-content">
				<?php the_content(); ?>
			</div>
			<?php
			$ktube_tags = get_the_term_list( get_the_ID(), 'post_tag', '<span class="ktube-article__tag">', ' ', '</span>' );
			if ( $ktube_tags && ! is_wp_error( $ktube_tags ) ) :
				?>
				<footer class="ktube-article__footer">
					<span class="ktube-article__tags-label"><?php esc_html_e( 'Tags:', 'ktube' ); ?></span>
					<?php echo wp_kses_post( $ktube_tags ); ?>
				</footer>
				<?php
			endif;
			?>
		</article>
		<?php ktube_the_related_blog_posts( get_the_ID() ); ?>
	<?php endwhile; ?>
</main>
<?php
get_footer();
