<?php
/**
 * Opcode cache tools Screen.
 *
 * @package calmPress
 */

declare(strict_types=1);

namespace calmpress\opcache;

/** WordPress Administration Bootstrap */
require_once dirname( __FILE__ ) . '/admin.php';

if ( ! current_user_can( 'manage_server' ) ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to manage the opcode cache for this site.' ) );
}

$title       = __( 'Opcode Cache (opcache)' );
$parent_file = 'tools.php';

get_current_screen()->add_help_tab(
	[
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' =>
			'<p>' . esc_html__( 'The Opcode Cache is used by PHP make script execute faster and may be used for object caching.' ) . '</p>',
	]
);

require ABSPATH . 'wp-admin/admin-header.php';

?>
<div class="wrap">
<?php settings_errors(); ?>
	<h1><?php echo esc_html( $title ); ?></h1>
	<?php
	if ( ! Opcache::api_is_available() ) {
		echo '<p>' . esc_html__( 'The Opcode Cache API is not available to this site.' ) . '</p>';
	} else {
		$opcache = new Opcache();
		$stats   = $opcache->stats();
		?>
	<h2><?php esc_html_e( 'Stats' ); ?></h2>
	<p><?php esc_html_e( 'The Opcode Cache stats are an aggregation for all the sites hosted on the webserver hosting this site. If there are other sites any issue being detected might be related to them.' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th><?php esc_html_e( 'Total hits' ); ?></th>
			<td><?php echo esc_html( $stats->hits() ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Miss rate' ); ?></th>
			<td>
				<?php echo esc_html( number_format( $stats->miss_rate(), 2 ) . '%' ); ?>
				<p class="description">
					<?php esc_html_e( 'The higher the miss rate is, the less useful is the cache. This number might be misleading if a restart had happened recently.' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Last restart time' ); ?></th>
			<td><?php echo esc_html( human_time_diff( time(), $stats->last_restart_time() ) ) . ' ' . esc_html__( 'ago' ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'System initiated restarts number' ); ?></th>
			<td>
				<?php echo esc_html( $stats->system_restarts() ); ?>
				<p class="description">
					<?php esc_html_e( 'This value should be zero. A much higher number indicates that you might need to allocated more memory to opcache in the PHP\'s .ini files.' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'External restarts number' ); ?></th>
			<td>
				<?php echo esc_html( $stats->external_restarts() ); ?>
				<p class="description">
					<?php esc_html_e( 'These restarts could have been triggered by an actual manual request or some "user land" PHP process.' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Cached keys usage' ); ?></th>
			<td>
				<?php echo esc_html( number_format( $stats->cached_keys_usage(), 2 ) . '%' ); ?>
				<p class="description">
					<?php esc_html_e( 'If close to full, the opcode cache might not be able to cache new scripts.' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Memory usage' ); ?></th>
			<td>
				<?php echo esc_html( number_format( $stats->memory_usage(), 2 ) . '%' ); ?>
				<p class="description">
					<?php esc_html_e( 'If close to full, the opcode cache might not be able to cache new scripts.' ); ?>
				</p>
			</td>
		</tr>
	</table>
	<h2><?php esc_html_e( 'Restart' ); ?></h2>
		<?php if ( PHP_OS_FAMILY === 'Windows' ) { ?>
		<p><strong><?php esc_html_e( 'The best way to restart the Opcode Cache on windows is to restart the web server.' ); ?></strong></p>
		<?php } else { ?>
		<form action="admin-post.php" method="post">
			<p><strong><?php esc_html_e( 'Before restarting you should keep in mind that the Opcode Cache might serve other sites as well which will be impacted by the retart.' ); ?></strong></p>
			<input name='action' type="hidden" value='opcache_reset'>
			<?php
			wp_nonce_field( 'opcache_reset' );
			submit_button( __( 'Restart the Opcode Cache' ), 'primary', 'restart' );
			?>
		</form>
		<?php } ?>
	<?php } ?>
</div>

<?php
require ABSPATH . 'wp-admin/admin-footer.php';
