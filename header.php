<?php
/**
 * ktube header — Phase 0 shell.
 * Phase 5 expands menu + customizer layout; Phase 7 wires schema into wp_head.
 * Phase 7b wires the <button class="ktube-theme-toggle"> that dark-mode.js auto-binds.
 *
 * @package ktube
 */

$ktube_initial_theme = ktube_resolve_initial_theme();

$ktube_skip_label  = __( 'Skip to content', 'ktube' );
$ktube_toggle_id   = 'ktube-theme-toggle';
$ktube_toggle_next = ( 'dark' === $ktube_initial_theme ) ? 'light' : 'dark';
/* translators: %s: the theme the visitor's next click will flip to */
$ktube_toggle_aria = sprintf( __( 'Switch to %s mode', 'ktube' ), $ktube_toggle_next );
$ktube_toggle_text = ( 'dark' === $ktube_initial_theme )
	? __( 'Light', 'ktube' )
	: __( 'Dark',  'ktube' );
?><!DOCTYPE html>
<html <?php language_attributes(); ?> data-theme="<?php echo esc_attr( $ktube_initial_theme ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#primary"><?php echo esc_html( $ktube_skip_label ); ?></a>
<header id="masthead" class="site-header" role="banner">
	<div class="site-branding">
		<?php if ( function_exists( 'has_custom_logo' ) && has_custom_logo() ) : ?>
			<?php the_custom_logo(); ?>
		<?php endif; ?>
		<?php if ( is_front_page() && is_home() ) : ?>
			<h1 class="site-title">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
			</h1>
		<?php else : ?>
			<p class="site-title">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
			</p>
		<?php endif; ?>
		<?php
		$ktube_description = get_bloginfo( 'description', 'display' );
		if ( $ktube_description && is_front_page() && is_home() ) :
			?>
			<p class="site-description"><?php echo esc_html( $ktube_description ); ?></p>
			<?php
		endif;
		?>
	</div>
	<nav id="site-navigation" class="main-navigation" role="navigation" aria-label="<?php esc_attr_e( 'Primary Menu', 'ktube' ); ?>">
		<?php
		wp_nav_menu( array(
			'theme_location' => 'primary',
			'menu_id'        => 'primary-menu',
			'fallback_cb'    => false,
		) );
		?>
		<button
			id="<?php echo esc_attr( $ktube_toggle_id ); ?>"
			class="ktube-theme-toggle"
			type="button"
			aria-pressed="<?php echo esc_attr( 'dark' === $ktube_initial_theme ? 'true' : 'false' ); ?>"
			aria-label="<?php echo esc_attr( $ktube_toggle_aria ); ?>"
			title="<?php echo esc_attr( $ktube_toggle_aria ); ?>"
		>
			<span class="ktube-theme-toggle__icon" aria-hidden="true"></span>
			<span class="ktube-theme-toggle__label" aria-hidden="true"><?php echo esc_html( $ktube_toggle_text ); ?></span>
		</button>
	</nav>
</header>
