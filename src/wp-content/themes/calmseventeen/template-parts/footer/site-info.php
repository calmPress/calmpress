<?php
/**
 * Displays footer site info
 *
 * @package calmPress
 * @subpackage calm_Seventeen
 * @since 1.0
 * @version 1.0
 */

?>
<div class="site-info">
	<?php
	if ( function_exists( 'the_privacy_policy_link' ) ) {
		the_privacy_policy_link( '', '<span role="separator" aria-hidden="true"></span>' );
	}
	?>
	<a href="<?php echo esc_url( __( 'https://calmpress.org/', 'calmseventeen' ) ); ?>" class="imprint">
		<?php
			/* translators: %s: WordPress */
		printf( __( 'Proudly powered by %s', 'calmseventeen' ), 'calmPress' );
		?>
	</a>
</div><!-- .site-info -->
