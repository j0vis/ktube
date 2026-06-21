<?php
/**
 * 404.php — graceful not-found fallback.
 *
 * @package ktube
 */

get_header();
?>
<main id="primary" class="site-main ktube-404">
	<header class="page-header">
		<h1 class="page-title"><?php esc_html_e( 'Not Found', 'ktube' ); ?></h1>
	</header>
	<div class="page-content">
		<p><?php esc_html_e( 'That page can’t be found. Try a search or browse from the menu.', 'ktube' ); ?></p>
		<?php get_search_form(); ?>
	</div>
</main>
<?php
get_footer();
