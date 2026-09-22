<?php
/**
 * User sites network administration screen.
 *
 * @package CalmPress
 * @subpackage Administration
 * @since calmPress 1.0.0
 */

/** Load the Network Administration bootstrap. */
require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'manage_network_users' ) ) {
	wp_die( 'You are not allowed to view this user.', '', [ 'response' => 403 ] );
}

if ( ! isset( $_GET['user_id'] ) || ! is_string( $_GET['user_id'] ) || $_GET['user_id'] != (int) $_GET['user_id'] || (int) $_GET['user_id'] <= 0 ) {
	wp_die( 'Invalid user ID.', '', [ 'response' => 400 ] );
}

$user    = get_userdata( (int) $_GET['user_id'] );
$network = get_network();

if ( ! $user || ! $network->has_user( $user ) ) {
	wp_die( 'Invalid user ID.', '', [ 'response' => 400 ] );
}

$pending_sites = $user->sites_pending_activation( $network );
$sites         = [];

// A user may belong to sites in several networks; show only the network being administered.
foreach ( $user->sites() as $site ) {
	$site_network = $site->network();
	if ( null !== $site_network && (int) $site_network->id === (int) $network->id ) {
		$sites[] = $site;
	}
}

/* translators: %s: User display name. */
$title        = sprintf( __( 'Sites for %s' ), $user->display_name );
$parent_file  = 'users.php';
$submenu_file = 'users.php';

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap">
	<h1><?php echo esc_html( $title ); ?></h1>

	<?php if ( [] !== $pending_sites ) { ?>
		<h2><?php esc_html_e( 'Pending invitations' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Site' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Intended role' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $pending_sites as $site ) { ?>
				<?php
				$site_id  = (int) $site->blog_id;
				$switched = get_current_blog_id() !== $site_id;

				// Resolve the site name and intended role in the context of the represented site.
				if ( $switched ) {
					switch_to_blog( $site_id );
				}

				try {
					$site_name             = $site->name();
					$site_name             = '' !== $site_name ? $site_name : untrailingslashit( $site->home_url() );
					$role                  = $user->site_invitation_role( $site );
					$registered_role_names = wp_roles()->get_names();
					$role_name              = translate_user_role( $registered_role_names[ $role ] ?? $role );
				} finally {
					if ( $switched ) {
						restore_current_blog();
					}
				}
				?>
				<tr>
					<td><?php echo esc_html( $site_name ); ?></td>
					<td><?php echo esc_html( $role_name ); ?></td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	<?php } ?>

	<h2><?php esc_html_e( 'Sites' ); ?></h2>
	<?php if ( [] === $sites ) { ?>
		<p><?php esc_html_e( 'This user is not a member of any sites in this network.' ); ?></p>
	<?php } else { ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Site' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Role' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $sites as $site ) { ?>
				<?php
				$site_id  = (int) $site->blog_id;
				$switched = get_current_blog_id() !== $site_id;

				// Resolve the site name and the user's roles in the context of the represented site.
				if ( $switched ) {
					switch_to_blog( $site_id );
				}

				try {
					$dashboard_url         = $site->admin_url();
					$site_name             = $site->name();
					$site_name             = '' !== $site_name ? $site_name : untrailingslashit( $site->home_url() );
					$site_user             = new WP_User( $user->ID, '', $site_id );
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
					<td><strong><a href="<?php echo esc_url( $dashboard_url ); ?>"><?php echo esc_html( $site_name ); ?></a></strong></td>
					<td><?php echo esc_html( implode( ', ', $role_names ) ); ?></td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	<?php } ?>
</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
