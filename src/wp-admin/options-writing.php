<?php
/**
 * Writing settings administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once( dirname( __FILE__ ) . '/admin.php' );

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( __( 'Sorry, you are not allowed to manage options for this site.' ) );
}

$title       = __( 'Writing Settings' );
$parent_file = 'options-general.php';

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' => '<p>' . __( 'You can submit content in several different ways; this screen holds the settings for all of them. The top section controls the editor within the dashboard, while the rest control external publishing methods. For more information on any of these methods, use the documentation links.' ) . '</p>' .
			'<p>' . __( 'You must click the Save Changes button at the bottom of the screen for new settings to take effect.' ) . '</p>',
	)
);

include( ABSPATH . 'wp-admin/admin-header.php' );
?>

<div class="wrap">
<h1><?php echo esc_html( $title ); ?></h1>

<form method="post" action="options.php">
<?php settings_fields( 'writing' ); ?>

<table class="form-table" role="presentation">
<?php
do_settings_fields( 'writing', 'default' );
do_settings_fields( 'writing', 'remote_publishing' ); // A deprecated section.
?>
</table>

<?php do_settings_sections( 'writing' ); ?>

<?php submit_button(); ?>
</form>
</div>

<?php include( ABSPATH . 'wp-admin/admin-footer.php' ); ?>
