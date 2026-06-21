<?php
/**
 * Template Name: Terms of Service
 *
 * Phase 8-B — page template for the site's Terms of Service. Theme
 * renders a default heading + slot for operator-supplied legal text.
 * NEITHER KTUBE NOR THIS TEMPLATE AUTHOR LEGAL TEXT — operators
 * supply jurisdiction-specific clauses (acceptable-use, account terms,
 * billing, dispute-resolution, governing law, etc.).
 *
 * Auto-applied to any Page whose slug is `terms` via
 * includes/compliance-pages.php::ktube_compliance_auto_apply_template_to_slug().
 *
 * @package ktube
 */

get_header(); ?>

<main id="primary" class="site-main ktube-single ktube-single--page ktube-compliance-page ktube-compliance-page--terms">
	<?php
	while ( have_posts() ) :
		the_post();
		$ktube_page_title      = get_the_title();
		$ktube_default_heading = function_exists( 'ktube_compliance_default_heading' )
			? ktube_compliance_default_heading( 'terms' )
			: __( 'Terms of Service', 'ktube' );
		$ktube_display_title   = '' !== $ktube_page_title ? $ktube_page_title : $ktube_default_heading;
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'ktube-article ktube-article--compliance' ); ?>>
			<header class="ktube-article__header">
				<h1 class="ktube-article__title"><?php echo esc_html( $ktube_display_title ); ?></h1>
				<p class="ktube-article__meta ktube-compliance-page__notice">
					<?php esc_html_e( 'This page is populated by the site operator. ktube does not provide legal advice.', 'ktube' ); ?>
				</p>
			</header>
			<div class="ktube-article__body entry-content">
				<?php the_content(); ?>
			</div>
		</article>
		<?php
	endwhile;
	?>
</main>

<?php get_footer(); ?>
