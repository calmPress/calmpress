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

if ( ! current_user_can( 'opcache' ) ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to manage the opcode cache for this site.' ) );
}

$title       = __( 'Opcode Cache' );
$parent_file = 'tools.php';

get_current_screen()->add_help_tab(
	[
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' =>
			'<p>' . esc_html__( 'The Opcode cache (opcache) is used by PHP make script execute faster and may be used for object caching.' ) . '</p>',
	]
);

require ABSPATH . 'wp-admin/admin-header.php';

?>
<div class="wrap">
<?php settings_errors(); ?>
	<h1><?php echo esc_html( $title ); ?></h1>
	<?php
	if ( ! Opcache::api_is_avaialable() ) {
		echo '<p>' . esc_html__( 'The Opcode cache API is not available to this site.' ) . '</p>';
	} else {
		$opcache = new Opcache();
		$stats   = $opcache->stats();
		?>
	<h2><?php esc_html_e( 'Stats' ); ?></h2>
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
					<?php esc_html_e( 'This value should be zero. A much higher number indicates that you might need to allocated more memory to the opcache.' ); ?>
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
					<?php esc_html_e( 'If close to full, opcache might not be able to cache new scripts.' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Memory usage' ); ?></th>
			<td>
				<?php echo esc_html( number_format( $stats->memory_usage(), 2 ) . '%' ); ?>
				<p class="description">
					<?php esc_html_e( 'If close to full, opcache might not be able to cache new scripts.' ); ?>
				</p>
			</td>
		</tr>
	</table>
	<?php } ?>
</div>

<?php
require ABSPATH . 'wp-admin/admin-footer.php';
