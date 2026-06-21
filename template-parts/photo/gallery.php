<?php
/**
 * template-parts/photo/gallery.php — lightbox-capable thumbnail grid.
 * Brief §3.3: lazy-loaded beyond first 2-3, keyboard-navigable lightbox.
 *
 * @package ktube
 */

$ktube_post_id = (int) get_the_ID();

$ktube_set_raw = (string) get_post_meta( $ktube_post_id, '_ktube_photo_set', true );
$ktube_set_arr = is_string( $ktube_set_raw ) ? maybe_unserialize( $ktube_set_raw ) : array();
if ( ! is_array( $ktube_set_arr ) ) {
	$ktube_set_arr = array();
}

// Filter out IDs that no longer resolve to a usable image.
$ktube_filtered = array_values(
	array_filter(
		array_map( 'intval', $ktube_set_arr ),
		static function ( int $id ): bool {
			return $id > 0 && wp_get_attachment_image_url( $id, 'ktube-hero' );
		}
	)
);

if ( empty( $ktube_filtered ) ) {
	return;
}
?>
<div class="ktube-lightbox-gallery" data-photo-set="<?php echo esc_attr( (string) $ktube_post_id ); ?>" role="list">
	<?php foreach ( $ktube_filtered as $ktube_index => $ktube_attach_id ) : ?>
		<?php
		$ktube_full_url  = wp_get_attachment_image_url( $ktube_attach_id, 'full' );
		$ktube_thumb_url = wp_get_attachment_image_url( $ktube_attach_id, 'ktube-card' );
		if ( ! $ktube_full_url || ! $ktube_thumb_url ) {
			continue;
		}
		$ktube_alt = (string) get_post_meta( $ktube_attach_id, '_wp_attachment_image_alt', true );
		?>
		<button type="button"
				class="ktube-lightbox-trigger"
				data-index="<?php echo esc_attr( (string) $ktube_index ); ?>"
				data-full-src="<?php echo esc_url( $ktube_full_url ); ?>"
				aria-label="<?php
					/* translators: %d: image position in set */
					printf( esc_attr__( 'Open image %d', 'ktube' ), (int) ( $ktube_index + 1 ) );
				?>">
			<img class="ktube-lightbox-thumb"
			     src="<?php echo esc_url( $ktube_thumb_url ); ?>"
			     width="640" height="360"
			     <?php echo ( $ktube_index >= 3 ) ? 'loading="lazy"' : 'loading="eager"'; ?>
			     decoding="async"
			     alt="<?php echo esc_attr( $ktube_alt ); ?>">
		</button>
	<?php endforeach; ?>
</div>
