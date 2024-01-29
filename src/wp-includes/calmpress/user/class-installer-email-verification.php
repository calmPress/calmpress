<?php
/**
 * Implementation of installer email verification util.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\user;

/**
 * Installer email verification utils packaged as a class.
 * 
 * @since 1.0.0
 */
class Installer_Email_Verification {

	/**
	 * Verify capability, nonce, and validitty of referer data a POST request. Die if the
	 * user is not allowed to change users, or nonce/referer include
	 * bad data. 
	 *
	 * @since 1.0.0
	 *
	 * @param string $action The name of the action expected to be used for generating the nonce
	 *                       and admin referer fields in the request.
	 */
	private static function verify_post_request( string $action ) {
		if ( ! current_user_can( 'delete_users' ) ) {
			wp_die(
				'<h1>' . __( 'You need additional permission.' ) . '</h1>' .
				'<p>' . __( 'Sorry, you are not allowed to swith users at this site.' ) . '</p>',
				403
			);
		}
		check_admin_referer( $action );
	}

	/**
	 * If the cuurent user is the installer and he had not verified his email address
	 * yet, display a notice for him to do that.
	 *
	 * @since 1.0.0
	 */
	public static function admin_notice_if_email_need_to_verify(): void {
		static::verify_post_request( 'switch_user' );

		if ( ! isset( $_POST['user'] ) ) {
			add_settings_error(
				'switch_user',
				'switch_user',
				esc_html__( 'Something went wrong, please try again' ),
				'error'
			);
		} else {
			$user_id = (int) wp_unslash( $_POST['user'] );
			$user    = get_user_by( 'id', $user_id );
			if ( $user ) {
				wp_clear_auth_cookie();
				wp_set_auth_cookie( $user_id );
				wp_set_current_user( $user_id );
				wp_redirect( admin_url() );
				exit;
			} else {
				add_settings_error(
					'switch_user',
					'switch_user',
					esc_html__( 'Sorry, can not find the user you asked to switch to' ),
					'error'
				);
			}
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );	
	
		// Redirect back to the settings page that was submitted.
		$goback = add_query_arg( 'settings-updated', 'true', wp_get_referer() );
		wp_redirect( $goback );
		exit;			
	}
}
