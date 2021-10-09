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
	wp_die( esc_html__( 'Sorry, you are not allowed to manage the object cache for this site.' ) );
}

$title       = __( 'Object Cache' );
$parent_file = 'tools.php';

get_current_screen()->add_help_tab(
	[
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' =>
			'<p>' . esc_html__( 'The object Cache is used to keep values in fast access storage between requests eliminating the need to request them from the database, resulting with faster site.' ) . '</p>',
	]
);

require ABSPATH . 'wp-admin/admin-header.php';

?>
<div class="wrap">
<?php settings_errors(); ?>
	<h1><?php echo esc_html( $title ); ?></h1>
	<h2><?php esc_html_e( 'Restart' ); ?></h2>
	<form action="admin-post.php" method="post">
		<p><strong><?php esc_html_e( 'Resetting the cache will slow down the site until the cache is rebuilt. It might be a good idea to activate maintenance mode before doing a restart and for few minutes after it.' ); ?></strong></p>
		<input name='action' type="hidden" value='object_cache_reset'>
		<?php
		wp_nonce_field( 'object_cache_reset' );
		submit_button( __( 'Reset the Object Cache' ), 'primary', 'restart' );
		?>
	</form>
</div>

<?php
require ABSPATH . 'wp-admin/admin-footer.php';
