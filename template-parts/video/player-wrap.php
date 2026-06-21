<?php
/**
 * template-parts/video/player-wrap.php — single video player wrapper.
 *
 * Hosts the WPS Player parity hook contract:
 *   - do_action( 'ktube_before_player', $post )
 *   - do_action( 'ktube_after_player', $post )
 *   - apply_filters( 'ktube_player_markup', $html, $post )
 *
 * Falls back to plain Video.js markup when WPS Player plugin absent.
 *
 * @package ktube
 */

$ktube_post_id  = (int) get_the_ID();
$ktube_post      = get_post();
$ktube_embed     = (string) get_post_meta( $ktube_post_id, '_ktube_embed_url', true );
$ktube_video     = (string) get_post_meta( $ktube_post_id, '_ktube_video_url', true );
$ktube_duration  = (int) get_post_meta( $ktube_post_id, '_ktube_duration', true );

if ( ! $ktube_embed && ! $ktube_video ) {
	return;
}

/**
 * Hook contract docblock (must mirror includes/wps-compat/wps-player.php):
 *
 *   do_action( 'ktube_before_player', WP_Post $post )
 *       Fires INSIDE the ob_start() capture region so listeners that emit
 *       player chrome (e.g. analytics pixel, lazy-loaded sibling module)
 *       get buffered into $ktube_native_markup and can be replaced wholesale
 *       via `ktube_player_markup`. NOTE: thrown exceptions from listeners
 *       abort the buffer mid-render; listeners SHOULD NOT throw.
 *
 *   apply_filters( 'ktube_player_markup', string $ktube_native_markup, WP_Post $post )
 *       Fires AFTER ob_get_clean() to allow a third-party plugin to swap the
 *       entire player markup. Spec: return '' to opt out and fall back to
 *       the native $ktube_native_markup.
 *
 *   do_action( 'ktube_after_player', WP_Post $post )
 *       Fires AFTER echo so listeners can append sibling markup without
 *       being captured by the buffer.
 */

// WARNING: any `<?php echo ?>` between this ob_start() and the matching ob_get_clean() is
// captured into $ktube_native_markup and may be REPLACED by a third-party plugin via the
// `ktube_player_markup` filter. Prefer string concatenation if a future contributor needs
// to emit outside the player's own markup.
ob_start();

do_action( 'ktube_before_player', $ktube_post );
?>
<div class="ktube-player-wrap<?php echo esc_attr( ktube_has_wps_player() ? ' ktube-has-wps-player' : '' ); ?>">
	<?php if ( $ktube_embed ) : ?>
		<div class="ktube-player-embed">
			<?php echo wp_kses_post( wp_oembed_get( esc_url_raw( $ktube_embed ) ) ); ?>
		</div>
	<?php elseif ( $ktube_video ) : ?>
		<video class="ktube-player video-js vjs-big-play-centered"
		       controls playsinline preload="metadata"
		       <?php if ( $ktube_duration ) : ?>data-duration="<?php echo esc_attr( (string) $ktube_duration ); ?>"<?php endif; ?>
		       poster="<?php echo esc_url( (string) wp_get_attachment_image_url( (int) get_post_thumbnail_id(), 'ktube-hero' ) ); ?>">
			<source src="<?php echo esc_url( $ktube_video ); ?>" type="video/mp4">
			<?php
			$ktube_variants = (string) get_post_meta( $ktube_post_id, '_ktube_quality_variants', true );
			if ( $ktube_variants ) {
				$ktube_variants_arr = maybe_unserialize( $ktube_variants );
				if ( is_array( $ktube_variants_arr ) ) {
					foreach ( $ktube_variants_arr as $ktube_quality => $ktube_variant_url ) {
						printf(
							'<source src="%s" type="video/mp4" label="%s">',
							esc_url( (string) $ktube_variant_url ),
							esc_attr( (string) $ktube_quality )
						);
					}
				}
			}
			?>
		</video>
	<?php endif; ?>
</div>
<?php
$ktube_native_markup = (string) ob_get_clean();

/**
 * Filter ktube_player_markup — lets third-party plugins (e.g. WPS Player) replace
 * the native Video.js / oEmbed markup entirely. Return an empty string to fall
 * back to the native markup.
 *
 * NOTE: clean-tube-player does NOT currently consume this filter by name
 * (verified in plugin source). Filter is forward-looking integration surface.
 *
 * Signature (must mirror includes/wps-compat/wps-player.php docblock):
 * @param string        $ktube_native_markup Native ktube-rendered player HTML.
 * @param WP_Post|null  $post                Current single-video post object.
 * @return string                             Replacement markup, or '' to keep
 *                                            the native markup unchanged.
 */
$ktube_rendered_markup   = (string) apply_filters(
	'ktube_player_markup',
	$ktube_native_markup,
	$ktube_post
);

echo ( '' !== $ktube_rendered_markup ) ? $ktube_rendered_markup : $ktube_native_markup;

do_action( 'ktube_after_player', $ktube_post );
