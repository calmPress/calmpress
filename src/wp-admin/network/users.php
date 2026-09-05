<?php
/**
 * Multisite users administration panel.
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 3.0.0
 */

/** Load WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'manage_network_users' ) ) {
	wp_die( __( 'Sorry, you are not allowed to access this page.' ), 403 );
}

$wp_list_table = _get_list_table( 'WP_MS_Users_List_Table' );

/**
 * Retrieves the user referenced by a network invitation action.
 *
 * @since calmPress 1.0.0
 *
 * @param WP_Network $network Network whose invitation is being managed.
 *
 * @return WP_User The invited user.
 */
function network_invitation_user_from_request( WP_Network $network ): WP_User {
	if ( ! isset( $_GET['user_id'] ) ) {
		wp_die( 'Invalid user ID.' );
	}

	$user = get_userdata( (int) $_GET['user_id'] );

	if ( ! $user ) {
		wp_die( 'User does not exist.' );
	}

	if ( ! $user->has_network_invite( $network ) ) {
		wp_safe_redirect(
			add_query_arg(
				[
					'role'    => 'pending_activation',
					'updated' => 'true',
					'action'  => 'invitation_not_pending',
				],
				network_admin_url( 'users.php' )
			)
		);
		exit;
	}

	return $user;
}

if ( isset( $_GET['action'] ) ) {
	/** This action is documented in wp-admin/network/edit.php */
	do_action( 'wpmuadminedit' );

	switch ( $_GET['action'] ) {
		case 'resend-invitation':
			$network = get_network();
			$user    = network_invitation_user_from_request( $network );

			check_admin_referer( 'resend-network-invitation-' . $user->ID );
			$email = new calmpress\email\User_Invitation_Email(
				$user,
				$network->site_name,
				wp_login_url()
			);
			$email->send();

			wp_safe_redirect(
				add_query_arg(
					[
						'role'    => 'pending_activation',
						'updated' => 'true',
						'action'  => 'invitation_resent',
					],
					network_admin_url( 'users.php' )
				)
			);
			exit;

		case 'cancel-invitation':
			$network = get_network();
			$user    = network_invitation_user_from_request( $network );

			check_admin_referer( 'cancel-network-invitation-' . $user->ID );
			$user->cancel_network_invite( $network );

			// An account created only for invitations is no longer needed after its final invitation is cancelled.
			if ( $user->was_created_for_network_invitation() && ! $user->has_any_network_invites() && [] === $user->sites() ) {
				wpmu_delete_user( $user->ID );
			}

			wp_safe_redirect(
				add_query_arg(
					[
						'role'    => 'pending_activation',
						'updated' => 'true',
						'action'  => 'invitation_cancelled',
					],
					network_admin_url( 'users.php' )
				)
			);
			exit;

		case 'deleteuser':
			if ( ! current_user_can( 'manage_network_users' ) ) {
				wp_die( __( 'Sorry, you are not allowed to access this page.' ), 403 );
			}

			check_admin_referer( 'deleteuser' );

			$id = (int) $_GET['id'];
			if ( $id > 1 ) {
				$_POST['allusers'] = array( $id ); // confirm_delete_users() can only handle arrays.

				// Used in the HTML title tag.
				$title       = __( 'Users' );
				$parent_file = 'users.php';

				require_once ABSPATH . 'wp-admin/admin-header.php';

				echo '<div class="wrap">';
				confirm_delete_users( $_POST['allusers'] );
				echo '</div>';

				require_once ABSPATH . 'wp-admin/admin-footer.php';
			} else {
				wp_redirect( network_admin_url( 'users.php' ) );
			}
			exit;

		case 'allusers':
			if ( ! current_user_can( 'manage_network_users' ) ) {
				wp_die( __( 'Sorry, you are not allowed to access this page.' ), 403 );
			}

			if ( isset( $_POST['action'] ) && isset( $_POST['allusers'] ) ) {
				check_admin_referer( 'bulk-users-network' );

				$doaction     = $_POST['action'];
				$userfunction = '';

				foreach ( (array) $_POST['allusers'] as $user_id ) {
					if ( ! empty( $user_id ) ) {
						switch ( $doaction ) {
							case 'delete':
								if ( ! current_user_can( 'delete_users' ) ) {
									wp_die( __( 'Sorry, you are not allowed to access this page.' ), 403 );
								}

								// Used in the HTML title tag.
								$title       = __( 'Users' );
								$parent_file = 'users.php';

								require_once ABSPATH . 'wp-admin/admin-header.php';

								echo '<div class="wrap">';
								confirm_delete_users( $_POST['allusers'] );
								echo '</div>';

								require_once ABSPATH . 'wp-admin/admin-footer.php';
								exit;
						}
					}
				}

				if ( ! in_array( $doaction, array( 'delete' ), true ) ) {
					$sendback = wp_get_referer();
					$user_ids = (array) $_POST['allusers'];

					/** This action is documented in wp-admin/network/site-themes.php */
					$sendback = apply_filters( 'handle_network_bulk_actions-' . get_current_screen()->id, $sendback, $doaction, $user_ids ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

					wp_safe_redirect( $sendback );
					exit;
				}

				wp_safe_redirect(
					add_query_arg(
						array(
							'updated' => 'true',
							'action'  => $userfunction,
						),
						wp_get_referer()
					)
				);
			} else {
				$location = network_admin_url( 'users.php' );

				if ( ! empty( $_REQUEST['paged'] ) ) {
					$location = add_query_arg( 'paged', (int) $_REQUEST['paged'], $location );
				}
				wp_redirect( $location );
			}
			exit;

		case 'dodelete':
			check_admin_referer( 'ms-users-delete' );
			if ( ! ( current_user_can( 'manage_network_users' ) && current_user_can( 'delete_users' ) ) ) {
				wp_die( __( 'Sorry, you are not allowed to access this page.' ), 403 );
			}

			if ( ! empty( $_POST['blog'] ) && is_array( $_POST['blog'] ) ) {
				foreach ( $_POST['blog'] as $id => $users ) {
					foreach ( $users as $blogid => $user_id ) {
						if ( ! current_user_can( 'delete_user', $id ) ) {
							continue;
						}

						if ( ! empty( $_POST['delete'] ) && 'reassign' === $_POST['delete'][ $blogid ][ $id ] ) {
							remove_user_from_blog( $id, $blogid, (int) $user_id );
						} else {
							remove_user_from_blog( $id, $blogid );
						}
					}
				}
			}

			$i = 0;

			if ( is_array( $_POST['user'] ) && ! empty( $_POST['user'] ) ) {
				foreach ( $_POST['user'] as $id ) {
					if ( ! current_user_can( 'delete_user', $id ) ) {
						continue;
					}
					wpmu_delete_user( $id );
					++$i;
				}
			}

			if ( 1 === $i ) {
				$deletefunction = 'delete';
			} else {
				$deletefunction = 'all_delete';
			}

			wp_redirect(
				add_query_arg(
					array(
						'updated' => 'true',
						'action'  => $deletefunction,
					),
					network_admin_url( 'users.php' )
				)
			);
			exit;
	}
}

$pagenum       = $wp_list_table->get_pagenum();
$wp_list_table->prepare_items();
$total_pages = $wp_list_table->get_pagination_arg( 'total_pages' );

if ( $pagenum > $total_pages && $total_pages > 0 ) {
	wp_redirect( add_query_arg( 'paged', $total_pages ) );
	exit;
}

// Used in the HTML title tag.
$title       = __( 'Users' );
$parent_file = 'users.php';

add_screen_option( 'per_page' );

get_current_screen()->set_screen_reader_content(
	array(
		'heading_views'      => __( 'Filter users list' ),
		'heading_pagination' => __( 'Users list navigation' ),
		'heading_list'       => __( 'Users list' ),
	)
);

require_once ABSPATH . 'wp-admin/admin-header.php';

if ( isset( $_REQUEST['updated'] ) && 'true' === $_REQUEST['updated'] && ! empty( $_REQUEST['action'] ) ) {
	$message     = '';
	$notice_type = 'success';
	switch ( $_REQUEST['action'] ) {
		case 'delete':
			$message = __( 'User deleted.' );
			break;
		case 'all_delete':
			$message = __( 'Users deleted.' );
			break;
		case 'add':
			$message = __( 'User added.' );
			break;
		case 'invitation_resent':
			$message = esc_html__( 'Invitation resent.' );
			break;
		case 'invitation_cancelled':
			$message = esc_html__( 'Invitation cancelled.' );
			break;
		case 'invitation_not_pending':
			$message     = esc_html__( 'The user is not pending activation in this network.' );
			$notice_type = 'warning';
			break;
	}

	wp_admin_notice(
		$message,
		array(
			'type'        => $notice_type,
			'dismissible' => true,
			'id'          => 'message',
		)
	);
}
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Users' ); ?></h1>

	<?php
	if ( current_user_can( 'create_users' ) ) :
		?>
		<a href="<?php echo esc_url( network_admin_url( 'user-new.php' ) ); ?>" class="page-title-action"><?php echo esc_html__( 'Add User' ); ?></a>
		<?php
	endif;

	if ( strlen( $usersearch ) ) {
		echo '<span class="subtitle">';
		printf(
			/* translators: %s: Search query. */
			__( 'Search results for: %s' ),
			'<strong>' . esc_html( $usersearch ) . '</strong>'
		);
		echo '</span>';
	}
	?>

	<hr class="wp-header-end">

	<?php $wp_list_table->views(); ?>

	<?php if ( ! isset( $_GET['role'] ) || 'pending_activation' !== $_GET['role'] ) : ?>
		<form method="get" class="search-form">
			<?php $wp_list_table->search_box( __( 'Search Users' ), 'all-user' ); ?>
		</form>
	<?php endif; ?>

	<form id="form-user-list" action="users.php?action=allusers" method="post">
		<?php $wp_list_table->display(); ?>
	</form>
</div>

<?php require_once ABSPATH . 'wp-admin/admin-footer.php'; ?>
