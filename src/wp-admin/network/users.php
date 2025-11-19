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

if ( isset( $_GET['action'] ) ) {
	/** This action is documented in wp-admin/network/edit.php */
	do_action( 'wpmuadminedit' );

	switch ( $_GET['action'] ) {
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
					$i++;
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

$wp_list_table = _get_list_table( 'WP_MS_Users_List_Table' );
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

if ( isset( $_REQUEST['updated'] ) && 'true' == $_REQUEST['updated'] && ! empty( $_REQUEST['action'] ) ) {
	?>
	<div id="message" class="updated notice is-dismissible"><p>
		<?php
		switch ( $_REQUEST['action'] ) {
			case 'delete':
				_e( 'User deleted.' );
				break;
			case 'all_spam':
				_e( 'Users marked as spam.' );
				break;
			case 'all_notspam':
				_e( 'Users removed from spam.' );
				break;
			case 'all_delete':
				_e( 'Users deleted.' );
				break;
			case 'add':
				_e( 'User added.' );
				break;
		}
		?>
	</p></div>
	<?php
}
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Users' ); ?></h1>

	<?php
	if ( current_user_can( 'create_users' ) ) :
		?>
		<a href="<?php echo esc_url( network_admin_url( 'user-new.php' ) ); ?>" class="page-title-action"><?php echo esc_html_x( 'Add New', 'user' ); ?></a>
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

	<form method="get" class="search-form">
		<?php $wp_list_table->search_box( __( 'Search Users' ), 'all-user' ); ?>
	</form>

	<form id="form-user-list" action="users.php?action=allusers" method="post">
		<?php $wp_list_table->display(); ?>
	</form>
</div>

<?php require_once ABSPATH . 'wp-admin/admin-footer.php'; ?>
