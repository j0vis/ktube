<?php
/**
 * single-photo.php — single photo-set template + lightbox gallery.
 * Brief §3.3: keyboard-navigable lightbox, lazy images beyond first 2-3.
 * ImageGallery JSON-LD emitted by seo/schema.php::ktube_render_photo_image_object_schema.
 *
 * @package ktube
 */

get_header();
?>
<main id="primary" class="site-main ktube-single ktube-single--photo">
	<?php while ( have_posts() ) : the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'ktube-photo-set' ); ?>>
			<header class="ktube-photo-set__header">
				<h1 class="ktube-photo-set__title"><?php the_title(); ?></h1>
			</header>

			<?php get_template_part( 'template-parts/photo/gallery' ); ?>

			<div class="ktube-photo-set__body entry-content">
				<?php the_content(); ?>
			</div>
		</article>
	<?php endwhile; ?>
</main>
<?php
get_footer();
