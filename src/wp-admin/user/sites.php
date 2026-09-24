<?php
/**
 * User sites administration screen.
 *
 * @package CalmPress
 * @subpackage Administration
 * @since calmPress 1.0.0
 */

if ( ! defined( 'WP_NETWORK_ADMIN' ) ) {
	/** Load the User Administration bootstrap. */
	require_once __DIR__ . '/admin.php';
}

if ( ! is_multisite() ) {
	wp_die(
		'The Sites screen is available only on a network installation.',
		'',
		[ 'response' => 403 ]
	);
}

$user    = wp_get_current_user();
$network = get_network();

$pending_sites = $user->sites_pending_activation( $network );
$sites         = $user->sites();

$title        = __( 'My Sites' );
$parent_file  = is_network_admin() ? 'profile.php' : 'user-edit.php';
$submenu_file = is_network_admin() ? 'profile-sites.php' : 'sites.php';

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap">
	<h1><?php echo esc_html( $title ); ?></h1>

	<?php
	if ( isset( $_GET['invitation'] ) && is_string( $_GET['invitation'] ) ) {
		$invitation_status = sanitize_key( wp_unslash( $_GET['invitation'] ) );
		if ( 'declined' === $invitation_status ) {
			wp_admin_notice( esc_html__( 'Site invitation declined.' ), [ 'type' => 'success', 'dismissible' => true ] );
		}
	}
	?>

	<?php if ( [] !== $pending_sites ) { ?>
		<h2><?php esc_html_e( 'Pending invitations' ); ?></h2>
		<table class="widefat striped user-sites" id="user-site-invitations">
			<thead>
				<tr>
					<th scope="col" class="column-site-name"><?php esc_html_e( 'Site name' ); ?></th>
					<th scope="col" class="column-site-url"><?php esc_html_e( 'Site URL' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $pending_sites as $site ) { ?>
				<?php
				$site_id  = (int) $site->blog_id;
				$switched = get_current_blog_id() !== $site_id;

				// Resolve invitation information in the context of the represented site.
				if ( $switched ) {
					switch_to_blog( $site_id );
				}

				try {
					$site_url       = $site->home_url();
					$site_name      = $site->name();
					$site_name      = '' !== $site_name ? $site_name : untrailingslashit( $site_url );
					$site_icon_url  = $site->icon()->url( 32 );
					$site_icon_url  = $site_icon_url ?: includes_url( 'images/calmpresslogo.png' );
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
								<strong><a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a></strong>
								<div class="row-actions">
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="site_id" value="<?php echo esc_attr( $site_id ); ?>">
										<input type="hidden" name="action" value="accept_site_invitation">
										<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( "user-site-invitation-accept-$site_id" ) ); ?>">
										<button type="submit" class="button-link"><?php esc_html_e( 'Accept' ); ?></button>
									</form>
									<span aria-hidden="true"> | </span>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="site_id" value="<?php echo esc_attr( $site_id ); ?>">
						<input type="hidden" name="action" value="decline_site_invitation">
						<?php if ( is_network_admin() ) { ?>
							<input type="hidden" name="return_to_network_admin" value="1">
						<?php } ?>
						<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( "user-site-invitation-decline-$site_id" ) ); ?>">
										<button type="submit" class="button-link delete"><?php esc_html_e( 'Decline' ); ?></button>
									</form>
								</div>
							</div>
						</div>
					</td>
					<td class="column-site-url"><a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( untrailingslashit( $site_url ) ); ?></a></td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	<?php } ?>

	<h2><?php esc_html_e( 'My sites' ); ?></h2>
	<?php if ( [] === $sites ) { ?>
		<p><?php esc_html_e( 'You are not currently a member of any site in this network.' ); ?></p>
	<?php } else { ?>
		<table class="widefat striped user-sites" id="user-sites">
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
