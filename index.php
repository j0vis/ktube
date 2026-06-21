<?php
/**
 * Fallback template — also covers the site homepage/front-page request
 * when no more specific template is configured.
 *
 * Phase 8-A (2026-06-21): when the request is the site homepage
 * (`is_front_page()`) or the blog posts index (`is_home()`), emit the
 * editorial H1 + lead paragraph sourced from `ktube_get_home_h1()` /
 * `ktube_get_home_description()`. Both return '' when unset, so the
 * section is rendered only when the operator has populated at least
 * one of the controls. The standard WP loop still runs below — the
 * header does not replace the post list.
 *
 * @package ktube
 */

get_header();
?>
<main id="primary" class="site-main">
	<?php
	// Phase 8-A — homepage editorial header. Gated on the homepage
	// request so paginated blog archives don't re-print the lead on
	// page 2, 3, ... At least one of H1/description must be non-empty
	// for the header to render; both empty ⇒ standard post-only layout.
	if ( is_front_page() || is_home() ) :
		$ktube_home_h1          = ktube_get_home_h1();
		$ktube_home_description = ktube_get_home_description();
		if ( '' !== $ktube_home_h1 || '' !== $ktube_home_description ) :
			?>
			<header class="ktube-home-header">
				<?php if ( '' !== $ktube_home_h1 ) : ?>
					<h1 class="ktube-home-header__h1"><?php echo esc_html( $ktube_home_h1 ); ?></h1>
				<?php endif; ?>
				<?php if ( '' !== $ktube_home_description ) : ?>
					<p class="ktube-home-header__description"><?php echo esc_html( $ktube_home_description ); ?></p>
				<?php endif; ?>
			</header>
			<?php
		endif;
	endif;
	?>
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<header class="entry-header">
					<?php
					the_title(
						is_singular() ? '<h1 class="entry-title">' : '<h2 class="entry-title">',
						is_singular() ? '</h1>' : '</h2>'
					);
					?>
				</header>
				<div class="entry-content">
					<?php the_content(); ?>
				</div>
			</article>
		<?php endwhile; ?>
		<?php the_posts_pagination( array(
			'mid_size'  => 2,
			'prev_text' => __( 'Previous', 'ktube' ),
			'next_text' => __( 'Next', 'ktube' ),
		) ); ?>
	<?php else : ?>
		<p class="ktube-no-content"><?php esc_html_e( 'Nothing here yet.', 'ktube' ); ?></p>
	<?php endif; ?>
</main>
<?php
get_footer();
