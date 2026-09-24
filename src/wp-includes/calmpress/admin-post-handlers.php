<?php
/**
 * Registration of admin_post handlers used by calmPress code.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\admin_post;

add_action( 'admin_init', __NAMESPACE__ . '\add_handlers' );

/**
 * Add various handlers to the admin_post handling.
 *
 * @since 1.0.0
 */
function add_handlers(): void {

	// Maintenance page form submittion.
	add_action( 'admin_post_maintenance_mode_content', '\calmpress\calmpress\Maintenance_Mode::handle_content_change_post' );
	add_action( 'admin_post_maintenance_mode_status', '\calmpress\calmpress\Maintenance_Mode::handle_status_change_post' );

	// Switch User form submittion.
	add_action( 'admin_post_switch_user', '\calmpress\user\Switch_User::handle_user_switch' );

	// Opcache restart form submittion.
	add_action( 'admin_post_opcache_reset', '\calmpress\opcache\Opcache::handle_opcache_reset' );

	// Object cache restart form submittion.
	add_action( 'admin_post_object_cache_reset', '\calmpress\object_cache\Utils::handle_object_cache_reset' );

	// Backup delete "GET" (link) action.
	add_action( 'admin_post_delete_backup', '\calmpress\backup\Utils::handle_delete_backup' );

	// Backup delete "GET" (link) action.
	add_action( 'admin_post_bulk_backup', '\calmpress\backup\Utils::handle_bulk_backup' );

	// Site invitation response form submissions.
	add_action( 'admin_post_accept_site_invitation', __NAMESPACE__ . '\handle_site_invitation_response' );
	add_action( 'admin_post_decline_site_invitation', __NAMESPACE__ . '\handle_site_invitation_response' );

	/**
	 * Get the user from an email approval style URL requests which include the user
	 * id and an expiry as nonce.
	 *
	 * Issue 403 if URL is malformed or user can not be found.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_User The user if found.
	 */
	function get_user_from_url(): \WP_User {
		if ( ! isset( $_GET['id'] ) ) {
			die( 403 );
		}

		$id   = $_GET['id'];
		$user = \WP_User::user_from_encrypted_string( $id );
		if ( $user === null ) {
			die( 403 );
		}

		return $user;
	}

	/**
	 * Handle the request for approving a changed email.
	 * 
	 * @since 1.0.0
	 */
	function handle_approve_change_email() {
		$user = get_user_from_url();

		try {
			$user->approve_new_email();
			wp_redirect( admin_url( 'user-edit.php' ) );
			die();
		} catch ( \RuntimeException $e ) {
			wp_die( __( 'Approval failed, link might have expired' ) );
		}
	}

	// Approve changed email "GET" (link) action.
	add_action( 'admin_post_nopriv_newuseremail', __NAMESPACE__ . '\handle_approve_change_email' );
	add_action( 'admin_post_newuseremail',  __NAMESPACE__ . '\handle_approve_change_email' );

	/**
	 * Handle the request for undoing a changed email.
	 * 
	 * @since 1.0.0
	 */
	function handle_undo_change_email() {
		$user = get_user_from_url();

		try {
			$user->undo_change_email();
			wp_redirect( admin_url( 'user-edit.php' ) );
			die();
		} catch ( \RuntimeException $e ) {
			wp_die( __( 'Undo failed, link might have expired' ) );
		}
	}

	// Undo changed email "GET" (link) action.
	add_action( 'admin_post_nopriv_undouseremail', __NAMESPACE__ . '\handle_undo_change_email' );
	add_action( 'admin_post_undouseremail', __NAMESPACE__ . '\handle_undo_change_email' );

	/**
	 * Handle the request for verifying an installer's email.
	 * 
	 * @since 1.0.0
	 */
	function handle_verify_installer_email() {
		$user = get_user_from_url();

		try {
			$user->approve_installer_email();
			wp_redirect( admin_url( 'user-edit.php' ) );
			die();
		} catch ( \RuntimeException $e ) {
			wp_die( __( 'Verification failed, link might have expired' ) );
		}
	}

	// Verify installer email "GET" (link) action.
	add_action( 'admin_post_nopriv_installeremail', __NAMESPACE__ . '\handle_verify_installer_email' );
	add_action( 'admin_post_installeremail', __NAMESPACE__ . '\handle_verify_installer_email' );
}

/**
 * Handles an authenticated user's response to a pending site invitation.
 *
 * @since 1.0.0
 */
function handle_site_invitation_response(): void {
	if ( ! is_multisite() ) {
		wp_die( 'Site invitations are available only on a network installation.', '', [ 'response' => 403 ] );
	}

	if ( ! isset( $_POST['action'], $_POST['site_id'] ) || ! is_string( $_POST['action'] ) || ! is_string( $_POST['site_id'] ) ) {
		wp_die( 'The site invitation form did not submit the required values.', '', [ 'response' => 400 ] );
	}

	$action  = wp_unslash( $_POST['action'] );
	$site_id = filter_var( wp_unslash( $_POST['site_id'] ), FILTER_VALIDATE_INT );
	if ( false === $site_id || 1 > $site_id || ! in_array( $action, [ 'accept_site_invitation', 'decline_site_invitation' ], true ) ) {
		wp_die( 'The site invitation form submitted invalid values.', '', [ 'response' => 400 ] );
	}

	$invitation_action = 'accept_site_invitation' === $action ? 'accept' : 'decline';
	check_admin_referer( "user-site-invitation-$invitation_action-$site_id" );

	$site = get_site( $site_id );
	if ( null === $site ) {
		wp_die( 'The invited site does not exist.', '', [ 'response' => 404 ] );
	}
	if ( (int) $site->network_id !== (int) get_network()->id ) {
		wp_die( 'The invited site does not belong to the current network.', '', [ 'response' => 403 ] );
	}

	$user = wp_get_current_user();
	if ( 'accept' === $invitation_action ) {
		$user->accept_site_invitation( $site );

		// The invited site may use a mapped domain, but its stored administration URL is trusted.
		wp_redirect( $site->admin_url() );
		exit;
	}

	$user->decline_site_invitation( $site );
	$sites_url = isset( $_POST['return_to_network_admin'] ) && '1' === $_POST['return_to_network_admin'] && current_user_can( 'manage_network' )
		? network_admin_url( 'profile-sites.php' )
		: user_admin_url( 'sites.php' );

	wp_safe_redirect(
		add_query_arg(
			'invitation',
			'declined',
			$sites_url
		)
	);
	exit;
}
