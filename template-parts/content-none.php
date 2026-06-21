<?php
/**
 * template-parts/content-none.php — empty-state placeholder.
 *
 * @package ktube
 */
?>
<section class="ktube-no-content no-results not-found">
	<header class="entry-header">
		<h1 class="entry-title"><?php esc_html_e( 'Nothing here yet', 'ktube' ); ?></h1>
	</header>
	<div class="page-content">
		<?php if ( is_home() && current_user_can( 'publish_posts' ) ) : ?>
			<p>
				<?php
				printf(
					/* translators: %s: post-new admin URL */
					wp_kses(
						__( 'Ready to publish your first post? <a href="%s">Get started here</a>.', 'ktube' ),
						array( 'a' => array( 'href' => array() ) )
					),
					esc_url( admin_url( 'post-new.php?post_type=post' ) )
				);
				?>
			</p>
		<?php elseif ( is_search() ) : ?>
			<p><?php esc_html_e( 'Nothing matched your search. Try different keywords.', 'ktube' ); ?></p>
			<?php get_search_form(); ?>
		<?php else : ?>
			<p><?php esc_html_e( 'It seems there’s nothing to display.', 'ktube' ); ?></p>
		<?php endif; ?>
	</div>
</section>
