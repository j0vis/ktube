<?php
/**
 * Template helpers — duration / views formatters, meta strip, related-videos query.
 *
 * @package ktube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Format a duration in seconds as MM:SS or H:MM:SS.
 */
function ktube_format_duration( int $seconds ): string {
	if ( $seconds <= 0 ) {
		return '';
	}
	$h = (int) floor( $seconds / 3600 );
	$m = (int) floor( ( $seconds % 3600 ) / 60 );
	$s = $seconds % 60;
	if ( $h > 0 ) {
		return sprintf( '%d:%02d:%02d', $h, $m, $s );
	}
	return sprintf( '%d:%02d', $m, $s );
}

/**
 * Format view counts as 1.2K / 3.4M.
 */
function ktube_format_views( int $views ): string {
	if ( $views < 1000 ) {
		return (string) number_format_i18n( $views );
	}
	if ( $views < 1000000 ) {
		return sprintf( '%sK', number_format_i18n( $views / 1000, 1 ) );
	}
	return sprintf( '%sM', number_format_i18n( $views / 1000000, 1 ) );
}

/**
 * Echo a single-video meta strip (channel, actor, duration, views).
 */
function ktube_the_video_meta( int $post_id ): void {
	$ktube_channels = get_the_term_list( $post_id, 'channel', '<span class="ktube-meta__channel">', ' ', '</span>' );
	$ktube_actors   = get_the_term_list( $post_id, 'actor', '<span class="ktube-meta__actor">', ' ', '</span>' );
	$ktube_duration = (int) get_post_meta( $post_id, '_ktube_duration', true );
	$ktube_views    = (int) get_post_meta( $post_id, '_ktube_views', true );
	?>
	<section class="ktube-meta" aria-label="<?php esc_attr_e( 'Video metadata', 'ktube' ); ?>">
		<?php if ( $ktube_channels && ! is_wp_error( $ktube_channels ) ) { echo wp_kses_post( $ktube_channels ); } ?>
		<?php if ( $ktube_actors && ! is_wp_error( $ktube_actors ) ) { echo wp_kses_post( $ktube_actors ); } ?>
		<?php if ( $ktube_duration ) : ?>
			<span class="ktube-meta__duration"><?php echo esc_html( ktube_format_duration( $ktube_duration ) ); ?></span>
		<?php endif; ?>
		<?php if ( $ktube_views ) : ?>
			<span class="ktube-meta__views"><?php
				/* translators: %s: formatted view count */
				printf( esc_html__( '%s views', 'ktube' ), esc_html( ktube_format_views( $ktube_views ) ) );
			?></span>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * Echo up to 8 related videos via shared taxonomy terms.
 */
function ktube_the_related_videos( int $post_id ): void {
	$ktube_terms = wp_get_post_terms( $post_id, array( 'channel', 'actor', 'category' ), array( 'fields' => 'ids' ) );
	if ( empty( $ktube_terms ) || is_wp_error( $ktube_terms ) ) {
		return;
	}
	$ktube_query = new WP_Query( array(
		'post_type'      => 'video',
		'posts_per_page' => 8,
		'post__not_in'   => array( $post_id ),
		'tax_query'      => array(
			array(
				'taxonomy' => 'channel',
				'terms'    => $ktube_terms,
			),
		),
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	) );
	if ( ! $ktube_query->have_posts() ) {
		return;
	}
	?>
	<section class="ktube-related" aria-label="<?php esc_attr_e( 'Related videos', 'ktube' ); ?>">
		<h2 class="ktube-related__title"><?php esc_html_e( 'Related', 'ktube' ); ?></h2>
		<ul class="ktube-video-grid" role="list">
			<?php
			while ( $ktube_query->have_posts() ) {
				$ktube_query->the_post();
				get_template_part( 'template-parts/video/card' );
			}
			wp_reset_postdata();
			?>
		</ul>
	</section>
	<?php
}

/**
 * Echo up to 4 related blog posts sharing category terms with the current post.
 *
 * Uses the same ktube-blog-card template-part so styling remains consistent.
 *
 * @param int $post_id Current single-blog post ID.
 */
function ktube_the_related_blog_posts( int $post_id ): void {
	$ktube_terms = wp_get_post_terms( $post_id, array( 'category', 'post_tag' ), array( 'fields' => 'ids' ) );
	if ( empty( $ktube_terms ) || is_wp_error( $ktube_terms ) ) {
		return;
	}
	$ktube_query = new WP_Query( array(
		'post_type'      => 'blog',
		'posts_per_page' => 4,
		'post__not_in'   => array( $post_id ),
		'tax_query'      => array(
			array(
				'taxonomy' => 'category',
				'terms'    => $ktube_terms,
				'field'   => 'term_id',
			),
		),
		'no_found_rows'  => true,
	) );
	if ( ! $ktube_query->have_posts() ) {
		return;
	}
	?>
	<section class="ktube-related ktube-related--blog" aria-label="<?php esc_attr_e( 'Related posts', 'ktube' ); ?>">
		<h2 class="ktube-related__title"><?php esc_html_e( 'Related posts', 'ktube' ); ?></h2>
		<div class="ktube-blog-list">
			<?php
			while ( $ktube_query->have_posts() ) {
				$ktube_query->the_post();
				get_template_part( 'template-parts/blog/card' );
			}
			wp_reset_postdata();
			?>
		</div>
	</section>
	<?php
}
