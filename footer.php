<?php
/**
 * ktube footer — Phase 0 shell + Phase 7b privacy badge + Phase 8-B
 * 4-slot compliance nav.
 *
 * The badge (Phase 7b) renders only when the age gate is active AND
 * a privacy page resolves — its copy claims the site does age
 * verification, so it lies if the gate is off.
 *
 * The 4-slot compliance nav (Phase 8-B) is a separate surface with a
 * separate gate: the slot for each kind renders whenever its page
 * resolves, regardless of age-gate state. This matches the brief's
 * "replicate equivalent footer menu slots" intent and gives operators
 * a uniform compliance-disclosure footer regardless of jurisdiction.
 *
 * @package ktube
 */
?>
<footer id="colophon" class="site-footer" role="contentinfo">
	<?php if ( function_exists( 'ktube_should_show_privacy_badge' ) && ktube_should_show_privacy_badge() ) : ?>
		<?php $ktube_badge_href      = ktube_get_privacy_page_url(); ?>
		<?php $ktube_badge_label     = ktube_privacy_badge_copy(); ?>
		<?php $ktube_badge_arialabel = sprintf(
			/* translators: %s: badge copy, prepended to "— read our privacy notice" */
			__( '%s — read our privacy notice', 'ktube' ),
			$ktube_badge_label
		); ?>
		<p class="ktube-privacy-badge">
			<a class="ktube-privacy-badge__link" href="<?php echo esc_url( $ktube_badge_href ); ?>" aria-label="<?php echo esc_attr( $ktube_badge_arialabel ); ?>">
				<span class="ktube-privacy-badge__icon" aria-hidden="true">&#x1F512;</span>
				<span class="ktube-privacy-badge__text"><?php echo esc_html( $ktube_badge_label ); ?></span>
			</a>
		</p>
	<?php endif; ?>

	<?php
	// Phase 8-B: 4-slot compliance nav (Privacy + 2257 + DMCA + Terms).
	// Each slot renders only when its Page resolves; missing slots are
	// silently dropped from the list rather than rendered as dead links.
	if ( function_exists( 'ktube_get_compliance_footer_slots' ) ) :
		$ktube_compliance_slots = ktube_get_compliance_footer_slots();
		if ( ! empty( $ktube_compliance_slots ) ) :
			?>
			<nav class="ktube-compliance-links" role="navigation" aria-label="<?php esc_attr_e( 'Compliance', 'ktube' ); ?>">
				<ul class="ktube-compliance-links__list">
					<?php foreach ( $ktube_compliance_slots as $ktube_slot ) : ?>
						<li class="ktube-compliance-links__item ktube-compliance-links__item--<?php echo esc_attr( $ktube_slot['slug'] ); ?>">
							<a class="ktube-compliance-links__link" href="<?php echo esc_url( $ktube_slot['url'] ); ?>">
								<?php echo esc_html( $ktube_slot['label'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>
			<?php
		endif;
	endif;
	?>

	<nav class="footer-navigation" role="navigation" aria-label="<?php esc_attr_e( 'Footer Menu', 'ktube' ); ?>">
		<?php
		wp_nav_menu( array(
			'theme_location' => 'footer',
			'menu_id'        => 'footer-menu',
			'fallback_cb'    => false,
		) );
		?>
	</nav>
	<p class="site-info">
		&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
	</p>
</footer>
<?php wp_footer(); ?>
</body>
</html>
