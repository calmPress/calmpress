<?php
/**
 * Opcode cache tools Screen.
 *
 * @package calmPress
 */

declare(strict_types=1);

namespace calmpress\apcu;

/** WordPress Administration Bootstrap */
require_once dirname( __FILE__ ) . '/admin.php';

if ( ! current_user_can( 'manage_server' ) ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to manage the APCu cache for this site.' ) );
}

$title       = __( 'APCu Cache' );
$parent_file = 'tools.php';

get_current_screen()->add_help_tab(
	[
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' =>
			'<p>' . esc_html__( 'The APCu Cache is used to keep value in the servers`s memory between request eliminating the need to request them from the database, resultins with faster site.' ) . '</p>',
	]
);

require ABSPATH . 'wp-admin/admin-header.php';

?>
<div class="wrap">
<?php settings_errors(); ?>
	<h1><?php echo esc_html( $title ); ?></h1>
	<?php
	if ( ! APCu::APCu_is_avaialable() ) {
		echo '<p>' . esc_html__( 'The APCu API is not available to this site.' ) . '</p>';
	} else {
		$apcu = new APCu();
		?>
	<h2><?php esc_html_e( 'Stats' ); ?></h2>
		<?php if ( 0 === $apcu->recent_store_failures() ) {	?>
		<p><?php esc_html_e( 'No write failures happened on this site at the last hour.' ); ?></p>
		<?php } else { ?>
		<p>
			<?php
			printf(
				/* translators: %1 number of write failures. */
				esc_html__( '%d write failures happened on this site at the last hour.' ),
				$apcu->recent_store_failures()
			);
			?>
		</p>
	<?php } ?>
	<h2><?php esc_html_e( 'Restart' ); ?></h2>
	<form action="admin-post.php" method="post">
		<p><strong><?php esc_html_e( 'Before restarting you should keep in mind that the APCu Cache might serve other sites as well which will be impacted by the retart.' ); ?></strong></p>
		<input name='action' type="hidden" value='apcu_reset'>
		<?php
		wp_nonce_field( 'apcu_reset' );
		submit_button( __( 'Restart the APCu Cache' ), 'primary', 'restart' );
		?>
	</form>
	<?php } ?>
</div>

<?php
require ABSPATH . 'wp-admin/admin-footer.php';
