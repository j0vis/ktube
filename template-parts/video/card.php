<?php
/**
 * template-parts/video/card.php — single video card.
 *
 * Pure markup. Phase 3 wires hover-trailer + storyboard via JS delegated listener
 * triggered on data-video-id / data-storyboard attrs.
 *
 * @package ktube
 */

$ktube_post_id    = (int) get_the_ID();
$ktube_duration   = (int) get_post_meta( $ktube_post_id, '_ktube_duration', true );
$ktube_views      = (int) get_post_meta( $ktube_post_id, '_ktube_views', true );
$ktube_thumb_id     = (int) get_post_thumbnail_id( $ktube_post_id );
$ktube_storyboard   = (string) get_post_meta( $ktube_post_id, '_ktube_storyboard_url', true );
$ktube_trailer      = (string) get_post_meta( $ktube_post_id, '_ktube_trailer_url', true );
$ktube_trailer_ext  = strtolower( (string) pathinfo( (string) parse_url( $ktube_trailer, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
$ktube_trailer_type = in_array( $ktube_trailer_ext, array( 'mp4', 'webm' ), true )
	? 'video'
	: ( in_array( $ktube_trailer_ext, array( 'gif', 'webp' ), true ) ? 'image' : '' );
?>
<li class="ktube-video-grid__item" data-video-id="<?php echo esc_attr( (string) $ktube_post_id ); ?>">
	<a class="ktube-card" href="<?php echo esc_url( get_permalink() ); ?>"
	   data-trailer-url="<?php echo esc_attr( $ktube_trailer ); ?>"
	   data-trailer-type="<?php echo esc_attr( $ktube_trailer_type ); ?>"
	   data-storyboard="<?php echo esc_attr( $ktube_storyboard ); ?>">
		<span class="ktube-card__thumb-wrap">
			<?php if ( $ktube_thumb_id ) : ?>
				<img class="ktube-card__thumb"
				     loading="lazy" decoding="async"
				     width="640" height="360"
				     src="<?php echo esc_url( wp_get_attachment_image_url( $ktube_thumb_id, 'ktube-card' ) ); ?>"
				     srcset="<?php echo esc_attr( (string) wp_get_attachment_image_srcset( $ktube_thumb_id, 'ktube-card' ) ); ?>"
				     sizes="(max-width:640px) 100vw, (max-width:1024px) 50vw, 25vw"
				     alt="<?php echo esc_attr( get_the_title() ); ?>">
			<?php else : ?>
				<span class="ktube-card__thumb ktube-card__thumb--placeholder" aria-hidden="true"></span>
			<?php endif; ?>
			<?php if ( $ktube_duration ) : ?>
				<span class="ktube-card__duration"><?php echo esc_html( ktube_format_duration( $ktube_duration ) ); ?></span>
			<?php endif; ?>
		</span>
		<span class="ktube-card__meta">
			<span class="ktube-card__title"><?php the_title(); ?></span>
			<?php
			$ktube_channels = get_the_term_list( $ktube_post_id, 'channel', '<span class="ktube-card__channel">', ', ', '</span>' );
			if ( $ktube_channels && ! is_wp_error( $ktube_channels ) ) {
				echo wp_kses_post( $ktube_channels );
			}
			?>
			<?php if ( $ktube_views ) : ?>
				<span class="ktube-card__views"><?php
					/* translators: %s: formatted view count */
					printf( esc_html__( '%s views', 'ktube' ), esc_html( ktube_format_views( $ktube_views ) ) );
				?></span>
			<?php endif; ?>
		</span>
	</a>
</li>
