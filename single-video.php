<?php
/**
 * single-video.php — single video template.
 *
 * @package ktube
 */

get_header();
?>
<main id="primary" class="site-main ktube-single ktube-single--video">
	<?php while ( have_posts() ) : the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title"><?php the_title(); ?></h1>
			</header>

			<?php get_template_part( 'template-parts/video/player-wrap' ); ?>

			<div class="entry-content">
				<?php the_content(); ?>
			</div>

			<?php ktube_the_video_meta( get_the_ID() ); ?>
		</article>

		<?php ktube_the_related_videos( get_the_ID() ); ?>
	<?php endwhile; ?>
</main>
<?php
get_footer();
