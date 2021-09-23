<?php
/**
 * Implementation of safe mode related utils.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\calmpress;

/**
 * Safe mode utils packaged as a class.
 * 
 * @since 1.0.0
 */
class Safe_Mode {

	/**
	 * The name of the cookie and URL parameter that might include the bypass code.
	 *
	 * @since 1.0.0
	 */
	const ACTIVATION_NAME = 'safe_mode';

	/**
	 * deactivate the maintenance mode..
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		update_option( self::OPTION_NAME, '' );
	}

	/**
	 * Helper function to set the safe mode cookie, exists mainly to be able to avoid headers
	 * already sent type of errors during testing.
	 *
	 * @since 1.0.0
	 */
	protected static function set_safe_mode_cookie() {
		setcookie( self::ACTIVATION_NAME, 1, 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl() );
	}

	/**
	 * Helper function to clear the safe mode cookie, exists mainly to be able to avoid headers
	 * already sent type of errors during testing.
	 *
	 * @since 1.0.0
	 */
	protected static function clear_safe_mode_cookie() {
		setcookie( self::ACTIVATION_NAME, 1, time() - 1000, COOKIEPATH, COOKIE_DOMAIN, is_ssl() );
	}

	/**
	 * Indicate if safe mode is active. for this request. To be active the user needs a safe_mode
	 * capability and a safe_mode parameter specified as a URL parameter or in a cookie.
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if safe mode is active for the user, false otherwise.
	 */
	public static function current_user_in_safe_mode():bool {

		// safe mode via URL paremeter.
		if ( isset( $_GET[ self::ACTIVATION_NAME ] ) &&
			current_user_can( 'safe_mode' ) ) {
			// Set the cookie only for the session.
			static::set_safe_mode_cookie();
			return true;
		}

		// safe mode via cookie.
		if ( isset( $_COOKIE[ self::ACTIVATION_NAME ] ) &&
			current_user_can( 'safe_mode' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Exit the current user from safe mode.
	 *
	 * Bsically removes the safe mode cookie. Usage of URL parameter can still put the user
	 * into safe mode.
	 *
	 * @since 1.0.0
	 */
	public static function exit_safe_mode():bool {

		static::clear_safe_mode_cookie();
	}

	/**
	 * Verify capability, nonce, and validitty of referer data a POST request. Die if the
	 * user is not allowed to changed safe mode related data, or nonce/referer include
	 * bad data. 
	 *
	 * @since 1.0.0
	 *
	 * @param string $action The name of the action expected to be used for generating the nonce
	 *                       and admin referer fields in the request.
	 */
	private static function verify_post_request( string $action ) {
		if ( ! current_user_can( 'safe_mode' ) ) {
			wp_die(
				'<h1>' . __( 'You need additional permission.' ) . '</h1>' .
				'<p>' . __( 'Sorry, you are not allowed to manage safe mode for this site.' ) . '</p>',
				403
			);
		}
		check_admin_referer( $action );
	}

	/**
	 * Handles the form post regarding content related maintenance page changes. Updates the
	 * post holding the content data.
	 *
	 * Used as a hook on admin-post.
	 *
	 * @since 1.0.0
	 */
	public static function handle_content_change_post() {
		$errors = [];
		static::verify_post_request( 'maintenance_mode_content' );

		if ( ! isset( $_POST['page_title'] ) || ! isset( $_POST['text_title'] ) || ! isset( $_POST['message_text'] ) ) {
			$errors[] = esc_html__( 'Something went wrong, please try again' );
		} else {
			static::set_page_title( wp_unslash( $_POST['page_title'] ) );
			static::set_text_title( wp_unslash( $_POST['text_title'] ) );
			static::set_content( wp_unslash( $_POST['message_text'] ) );
			static::set_use_theme_frame( isset( $_POST['theme_page'] ) );
		}

		set_transient( 'maintenance_mode_errors', $errors, 30 );	
	
		// Redirect back to the settings page that was submitted.
		$goback = add_query_arg( 'settings-updated', 'true', wp_get_referer() );
		wp_redirect( $goback );
		exit;			
	}

	/**
	 * Handle the form post regarding maintenance mode (de)activation. Updates the activation state
	 * and/or the interval till expected end of it.
	 *
	 * Used as a hook on admin-post.
	 *
	 * @since 1.0.0
	 */
	public static function handle_status_change_post() {
		$errors = [];
		static::verify_post_request( 'maintenance_mode_status' );

		// Check basic validity.
		if ( ! isset( $_POST['hours'] ) || ! isset( $_POST['minutes'] ) ) {
			$errors[] = esc_html__( 'Something went wrong, please try again' );
		} else {
			// Not putting much effort in validating the values as out of expected range
			// values can not do any harm.
			$hours    = (int) wp_unslash( $_POST['hours'] );
			$minutes  = (int) wp_unslash( $_POST['minutes'] );
			$end_time = time() + ( 60 * $hours + $minutes ) * 60;
			static::set_projected_end_time( $end_time );

			if ( isset( $_POST['enter'] ) ) {
				static::activate();
			}

			if ( isset( $_POST['exit'] ) ) {
				static::deactivate();
			}
		}

		set_transient( 'maintenance_mode_errors', $errors, 30 );	
	
		// Redirect back to the settings page that was submitted.
		$goback = add_query_arg( 'settings-updated', 'true', wp_get_referer() );
		wp_redirect( $goback );
		exit;			
	}
}
