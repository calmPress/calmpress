<?php
/**
 * User sites administration screen.
 *
 * @package CalmPress
 * @subpackage Administration
 * @since calmPress 1.0.0
 */

/** Load the User Administration bootstrap. */
require_once __DIR__ . '/admin.php';

if ( ! is_multisite() ) {
	wp_die(
		'The Sites screen is available only on a network installation.',
		'',
		[ 'response' => 403 ]
	);
}

$user  = wp_get_current_user();
$sites = $user->sites();

$title       = __( 'My Sites' );
$parent_file = 'sites.php';

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap">
	<h1><?php echo esc_html( $title ); ?></h1>

	<?php if ( [] === $sites ) { ?>
		<p><?php esc_html_e( 'You do not currently belong to any sites.' ); ?></p>
	<?php } else { ?>
		<table class="widefat striped" id="user-sites">
			<thead>
				<tr>
					<th scope="col" class="column-site-name"><?php esc_html_e( 'Site name' ); ?></th>
					<th scope="col" class="column-site-role"><?php esc_html_e( 'Role' ); ?></th>
					<th scope="col" class="column-site-url"><?php esc_html_e( 'Site URL' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $sites as $site ) { ?>
				<?php
				$site_id  = (int) $site->blog_id;
				$switched = get_current_blog_id() !== $site_id;

				// Resolve site information and roles in the context of the represented site.
				if ( $switched ) {
					switch_to_blog( $site_id );
				}

				try {
					$dashboard_url        = $site->admin_url();
					$site_url             = $site->home_url();
					$site_name            = $site->name();
					$site_name            = '' !== $site_name ? $site_name : untrailingslashit( $site_url );
					$site_icon_url        = $site->icon()->url( 32 );
					$site_icon_url        = $site_icon_url ?: includes_url( 'images/calmpresslogo.png' );
					$site_user            = new WP_User( $user->ID, '', $site_id );
					$registered_role_names = wp_roles()->get_names();
					$role_names            = [];
					foreach ( $site_user->roles as $role ) {
						$role_names[] = translate_user_role( $registered_role_names[ $role ] ?? $role );
					}
				} finally {
					if ( $switched ) {
						restore_current_blog();
					}
				}
				?>
				<tr>
					<td class="column-site-name">
						<div class="site-name-content">
							<img src="<?php echo esc_url( $site_icon_url ); ?>" width="32" height="32" alt="">
							<div>
								<strong><a href="<?php echo esc_url( $dashboard_url ); ?>"><?php echo esc_html( $site_name ); ?></a></strong>
								<div class="row-actions">
									<span class="dashboard"><a href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Dashboard' ); ?></a> | </span>
									<span class="view"><a href="<?php echo esc_url( $site_url ); ?>"><?php esc_html_e( 'View' ); ?></a></span>
								</div>
							</div>
						</div>
					</td>
					<td class="column-site-role"><?php echo esc_html( implode( ', ', $role_names ) ); ?></td>
					<td class="column-site-url"><a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( untrailingslashit( $site_url ) ); ?></a></td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	<?php } ?>
</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
