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
	<a href="<?php echo esc_url( __( 'https://calmpress.org/', 'calmseventeen' ) ); ?>" class="imprint">
		<?php
			/* translators: %s: WordPress */
		printf( __( 'Proudly powered by %s', 'calmseventeen' ), 'calmPress' );
		?>
	</a>
</div><!-- .site-info -->
