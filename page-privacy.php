<?php
/**
 * Template Name: Privacy
 *
 * Phase 7b privacy disclosure. Renders an auto-doc data sheet of what the
 * theme stores / emits (driven by includes/privacy.php::ktube_privacy_summary)
 * above any operator-supplied body copy. Auto-apply logic lives in
 * includes/privacy.php — this file assumes it has already been selected.
 *
 * @package ktube
 */

get_header();
?>
<main id="primary" class="site-main ktube-single ktube-single--page ktube-privacy-page">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'ktube-article ktube-article--privacy' ); ?>>
			<header class="ktube-article__header">
				<h1 class="ktube-article__title"><?php the_title(); ?></h1>
				<p class="ktube-article__meta ktube-privacy-page__last-updated">
					<?php
					printf(
						/* translators: %s: localized last-updated date */
						esc_html__( 'Last updated: %s', 'ktube' ),
						esc_html( get_the_modified_date( 'F j, Y' ) )
					);
					?>
				</p>
			</header>
			<div class="ktube-article__body entry-content">
				<?php
				// Auto-doc data sheet — what the theme currently stores / emits.
				// Renders regardless of body copy so visitors always see the
				// live state of the theme's privacy surface.
				get_template_part( 'template-parts/privacy/summary' );
				?>
				<div class="ktube-privacy-page__operator-content">
					<?php
					// Operator-supplied body copy. Renders BELOW the auto-doc
					// data sheet so operators can add site-specific notice
					// language (GDPR contact, DPO, jurisdiction, etc.) without
					// conflicting with the live data sheet above.
					the_content();
					?>
				</div>
			</div>
		</article>
		<?php
	endwhile;
	?>
</main>
<?php
get_footer();
