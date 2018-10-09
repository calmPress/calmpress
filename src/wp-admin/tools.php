<?php
/**
 * Tools Administration Screen.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once( dirname( __FILE__ ) . '/admin.php' );

$is_privacy_guide = ( isset( $_GET['wp-privacy-policy-guide'] ) && current_user_can( 'manage_privacy_options' ) );

if ( $is_privacy_guide ) {
	$title = __( 'Privacy Policy Guide' );

	// "Borrow" xfn.js for now so we don't have to create new files.
	wp_enqueue_script( 'xfn' );

} else {

	$title = __('Tools');

}

require_once( ABSPATH . 'wp-admin/admin-header.php' );

?>
<div class="wrap">
<h1><?php echo esc_html( $title ); ?></h1>
<?php

if ( $is_privacy_guide ) {
	?>
	<div class="wp-privacy-policy-guide">
		<?php WP_Privacy_Policy_Content::privacy_policy_guide(); ?>
	</div>
	<?php

} else {

	/**
	 * Fires at the end of the Tools Administration screen.
	 *
	 * @since 2.8.0
	 */
	do_action( 'tool_box' );
}
?>
</div>
<?php
include( ABSPATH . 'wp-admin/admin-footer.php' );
