<?php
/**
 * template-parts/photo/card.php — single photo-set archive card.
 *
 * @package ktube
 */

$ktube_post_id  = (int) get_the_ID();
$ktube_cover_id = (int) get_post_meta( $ktube_post_id, '_ktube_cover_photo_id', true );
if ( ! $ktube_cover_id ) {
	$ktube_cover_id = (int) get_post_thumbnail_id( $ktube_post_id );
}
$ktube_set_raw = (string) get_post_meta( $ktube_post_id, '_ktube_photo_set', true );
$ktube_set_arr = is_string( $ktube_set_raw ) ? maybe_unserialize( $ktube_set_raw ) : array();
if ( ! is_array( $ktube_set_arr ) ) {
	$ktube_set_arr = array();
}
$ktube_count = count( $ktube_set_arr );
?>
<li class="ktube-photo-grid__item">
	<a class="ktube-photo-card" href="<?php echo esc_url( get_permalink() ); ?>">
		<span class="ktube-photo-card__thumb-wrap">
			<?php
			if ( $ktube_cover_id ) {
				echo wp_get_attachment_image(
					$ktube_cover_id,
					'ktube-card',
					false,
					array(
						'class'   => 'ktube-photo-card__thumb',
						'loading' => 'lazy',
						'alt'     => esc_attr( get_the_title() ),
					)
				);
			}
			?>
			<?php if ( $ktube_count > 0 ) : ?>
				<span class="ktube-photo-card__count"><?php
					/* translators: %d: number of photos in the set */
					printf( esc_html__( '%d photos', 'ktube' ), (int) $ktube_count );
				?></span>
			<?php endif; ?>
		</span>
		<span class="ktube-photo-card__meta">
			<span class="ktube-photo-card__title"><?php the_title(); ?></span>
			<?php
			$ktube_actors = get_the_term_list( $ktube_post_id, 'actor', '<span class="ktube-photo-card__actor">', ', ', '</span>' );
			if ( $ktube_actors && ! is_wp_error( $ktube_actors ) ) {
				echo wp_kses_post( $ktube_actors );
			}
			?>
		</span>
	</a>
</li>
