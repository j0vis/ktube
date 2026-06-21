<?php
/**
 * template-parts/blog/card.php — single blog post card. Distinct from video card.
 * Brief §3.2: must NOT inherit video-grid card layout — separate .ktube-blog-card CSS.
 *
 * @package ktube
 */

$ktube_post_id = (int) get_the_ID();
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'ktube-blog-card' ); ?>>
	<a class="ktube-blog-card__link" href="<?php echo esc_url( get_permalink() ); ?>">
		<?php if ( has_post_thumbnail() ) : ?>
			<div class="ktube-blog-card__thumb-wrap">
				<?php the_post_thumbnail( 'ktube-card' ); ?>
			</div>
		<?php endif; ?>
		<div class="ktube-blog-card__body">
			<h2 class="ktube-blog-card__title"><?php the_title(); ?></h2>
			<?php if ( has_excerpt() ) : ?>
				<p class="ktube-blog-card__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>
			<p class="ktube-blog-card__meta">
				<?php
				$ktube_cats = get_the_term_list( $ktube_post_id, 'category', '<span class="ktube-blog-card__cat">', ' · ', '</span>' );
				if ( $ktube_cats && ! is_wp_error( $ktube_cats ) ) {
					echo wp_kses_post( $ktube_cats );
				}
				?>
				<time class="ktube-blog-card__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
					<?php echo esc_html( get_the_date() ); ?>
				</time>
			</p>
		</div>
	</a>
</article>
