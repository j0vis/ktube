<?php
/**
 * template-parts/privacy/summary.php — auto-doc data sheet for the Privacy page.
 *
 * Iterates ktube_privacy_summary() rows. Each row is documented even when
 * inactive (collapsed under <details>) so a curious visitor can inspect
 * what the theme is CAPABLE of, not only what is currently active.
 *
 * Output is escaped per row; HTML inside value fields (e.g. the <meta>
 * tag for RTA) is allowlisted via wp_kses so a misconfigured setting
 * cannot smuggle arbitrary markup.
 *
 * @package ktube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ktube_rows = ktube_privacy_summary();
$ktube_active_count   = 0;
$ktube_inactive_count = 0;
foreach ( $ktube_rows as $ktube_row ) {
	if ( $ktube_row['active'] ) {
		++$ktube_active_count;
	} else {
		++$ktube_inactive_count;
	}
}
?>
<section class="ktube-privacy-summary" aria-labelledby="ktube-privacy-summary-heading">
	<h2 id="ktube-privacy-summary-heading" class="ktube-privacy-summary__heading">
		<?php esc_html_e( 'What this site stores about your visit', 'ktube' ); ?>
	</h2>
	<p class="ktube-privacy-summary__lede">
		<?php
		printf(
			/* translators: 1: number of active protections, 2: total protections documented */
			esc_html__( 'The theme documents %1$d of %2$d protections as currently active. Inactive items are listed below for transparency.', 'ktube' ),
			(int) $ktube_active_count,
			(int) count( $ktube_rows )
		);
		?>
	</p>
	<dl class="ktube-privacy-summary__list">
		<?php foreach ( $ktube_rows as $ktube_row ) : ?>
			<div class="ktube-privacy-summary__row <?php echo $ktube_row['active'] ? 'is-active' : 'is-inactive'; ?>">
				<dt class="ktube-privacy-summary__term">
					<span class="ktube-privacy-summary__label"><?php echo esc_html( $ktube_row['label'] ); ?></span>
					<span class="ktube-privacy-summary__state" aria-label="<?php echo $ktube_row['active'] ? esc_attr__( 'Active', 'ktube' ) : esc_attr__( 'Inactive', 'ktube' ); ?>">
						<?php echo $ktube_row['active'] ? esc_html__( 'Active', 'ktube' ) : esc_html__( 'Inactive', 'ktube' ); ?>
					</span>
				</dt>
				<dd class="ktube-privacy-summary__def">
					<code class="ktube-privacy-summary__value"><?php
						$ktube_allowed = array(
							'meta' => array(
								'http-equiv' => array(),
								'content'    => array(),
							),
						);
						echo wp_kses( $ktube_row['value'], $ktube_allowed );
					?></code>
					<?php if ( '' !== $ktube_row['unit'] ) : ?>
						<span class="ktube-privacy-summary__unit"><?php echo esc_html( $ktube_row['unit'] ); ?></span>
					<?php endif; ?>
					<p class="ktube-privacy-summary__description"><?php echo esc_html( $ktube_row['description'] ); ?></p>
				</dd>
			</div>
		<?php endforeach; ?>
	</dl>
</section>
