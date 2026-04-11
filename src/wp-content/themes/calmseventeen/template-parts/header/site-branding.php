<?php
/**
 * Displays header site branding
 *
 * @package calmPress
 * @subpackage calm_Seventeen
 * @since 1.0
 * @version 1.0
 */

?>
<div class="site-branding">
	<div class="wrap">

		<?php the_custom_logo(); ?>

		<div class="site-branding-text">
			<?php
			$is_front  = ! is_paged() && ( is_front_page() || ( is_home() && ( (int) get_option( 'page_for_posts' ) !== get_queried_object_id() ) ) );
			$site_name = get_bloginfo( 'name', 'display' );

			if ( $site_name && is_front_page() ) :
				?>
				<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" <?php echo $is_front ? 'aria-current="page"' : ''; ?>><?php echo $site_name; ?></a></h1>
			<?php elseif ( $site_name ) : ?>
				<p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" <?php echo $is_front ? 'aria-current="page"' : ''; ?>><?php echo $site_name; ?></a></p>
				<?php
			endif;

			$description = get_bloginfo( 'description', 'display' );

			if ( $description || is_customize_preview() ) :
			?>
				<p class="site-description"><?php echo $description; ?></p>
			<?php endif; ?>
		</div><!-- .site-branding-text -->

		<?php if ( ( calmseventeen_is_frontpage() || ( is_home() && is_front_page() ) ) && ! has_nav_menu( 'top' ) ) : ?>
		<a href="#content" class="menu-scroll-down"><?php echo calmseventeen_get_svg( array( 'icon' => 'arrow-right' ) ); ?><span class="screen-reader-text">
			<?php
			/* translators: Hidden accessibility text. */
			_e( 'Scroll down to content', 'calmseventeen' );
			?>
		</span></a>
	<?php endif; ?>

	</div><!-- .wrap -->
</div><!-- .site-branding -->
