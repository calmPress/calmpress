<?php
/**
 * Implementation of admin notices.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\admin;

/**
 * Utility class to group admin notices related code.
 *
 * @since 1.0.0
 */
class Admin_Notices {

	/**
	 * Output an ".htaccess needs to be resaved" admin notice when the content of
	 * the WordPress section of the .htaccess file is not the same as the one
	 * calculated in code.
	 *
	 * The notice is not displayed on multisite setup, and it is displayed only
	 * for admins when the site uses apache.
	 *
	 * To avoid getting the data from the DB on every admin page load, the status of the nagging
	 * will be stored in a cookie for 10 minutes and the cookie will be used to determine if a nag should
	 * be displayed on additional page loads.
	 *
	 * @since 1.0.0
	 */
	public static function htaccess_update_nag() {
		global $wp_rewrite;

		// should we ignore nagging based on the cookie
		if ( isset( $_COOKIE['ht_nag'] ) && ( 'no' === $_COOKIE['ht_nag'] ) ) {
			return;
		}

		$nag = false;
		if ( isset( $_COOKIE['ht_nag'] ) ) {
			$nag = true;
		}

		if ( ! is_multisite() && is_super_admin() && is_apache() && ! $nag ) {
			$home_path      = ABSPATH;
			$existing_rules = array_filter( extract_from_markers( $home_path . '.htaccess', 'WordPress' ) );
			$new_rules      = array_filter( explode( "\n", $wp_rewrite->mod_rewrite_rules() ) );
			if ( $new_rules !== $existing_rules ) {
				$nag = true;
			}
		}

		if ( $nag ) {
			$msg = sprintf(
				/* translators: 1: The file name, 2: the section name */
				esc_html__( 'The %1$s file contain different settings in its %2$s section than what it should have.' ),
				'<code>.htaccess</code>',
				'<code>WordPress</code>'
			);
			$screen = get_current_screen();
			if ( $screen && ( 'options-htaccess' !== $screen->id ) ) {
				$msg .= '<br>' . sprintf(
					/* translators: 1: The file name, 2: Openning link to .htaccess settings page, 3: Closing </a> */
					esc_html__( 'You can fix the %1$s file by going to the %2$shtaccess settings page%3$s which will automatically try to update the file, and follow additional instructions if it fails.' ),
					'<code>.htaccess</code>',
					'<a href="' . esc_url( admin_url( 'options-htaccess.php' ) ) . '">',
					'</a>'
				);
			}
			echo "<div class='notice notice-error'><p>$msg</p></div>";
		}

		// If not set yet, set the nag state for 10 minutes.
		if ( ! isset( $_COOKIE['ht_nag'] ) ) {
			setcookie( 'ht_nag', $nag ? 'yes' : 'no', time() + 30 * MINUTE_IN_SECONDS, ADMIN_COOKIE_PATH, COOKIE_DOMAIN, is_ssl() );
		}
	}

	/**
	 * Remove the cookie associated with a nag state for htaccess nag.
	 *
	 * @since 1.0.0
	 */
	public static function clear_htaccess_update_nag_state() {
		setcookie( 'ht_nag', ' ', time() - 10000, ADMIN_COOKIE_PATH, COOKIE_DOMAIN, is_ssl() );

		// Make sure the nag is recalculated if this method was called before its generation.
		unset( $_COOKIE['ht_nag'] );
	}

	/**
	 * Output an wp-config.php needs to be resaved admin notice when the content of
	 * the User section of the wp-config.php file is not the same as the one
	 * calculated in code.
	 *
	 * The notice is not displayed on multisite setup.
	 *
	 * To avoid getting the data from the DB on every admin page load, the status of the nagging
	 * will be stored in a cookie for 10 minutes and the cookie will be used to determine if a nag should
	 * be displayed on additional page loads.
	 *
	 * @since 1.0.0
	 */
	public static function wp_config_update_nag() {

		// should we ignore nagging based on the cookie
		if ( isset( $_COOKIE['wpc_nag'] ) && ( 'no' === $_COOKIE['wpc_nag'] ) ) {
			return;
		}

		$nag = false;
		if ( isset( $_COOKIE['wpc_nag'] ) ) {
			$nag = true;
		}

		if ( ! is_multisite() && is_super_admin() && ! $nag ) {
			$wp_config      = \calmpress\wp_config\wp_config::current_wp_config();
			$existing_rules = $wp_config->user_section_in_file();
			$new_rules      = get_option( 'wp_config_user_section' );
			if ( $new_rules !== $existing_rules ) {
				$nag = true;
			}
		}

		if ( $nag ) {
			$msg = sprintf(
				/* translators: 1: The file name, 2: the section name */
				esc_html__( 'The %1$s file contain different settings in its %2$s section than what it should have.' ),
				'<code>wp-config.php</code>',
				'<code>User</code>'
			);
			$screen = get_current_screen();
			if ( $screen && ( 'options-wp-config' !== $screen->id ) ) {
				$msg .= '<br>' . sprintf(
					/* translators: 1: The file name, 2: The URL of the permalink setting page */
					esc_html__( 'You can fix the %1$s file by going to the %2$swp-config.php settings page%3$s and update the file from there.' ),
					'<code>wp-config.php</code>',
					'<a href="' . esc_url( admin_url( 'options-wp-config.php' ) ) . '">',
					'</a>'
				);
			}
			echo "<div class='notice notice-error'><p>$msg</p></div>";
		}

		// If not set yet, set the nag state for 10 minutes.
		if ( ! isset( $_COOKIE['wpc_nag'] ) ) {
			setcookie( 'wpc_nag', $nag ? 'yes' : 'no', time() + 30 * MINUTE_IN_SECONDS, ADMIN_COOKIE_PATH, COOKIE_DOMAIN, is_ssl() );
		}
	}

	/**
	 * Remove the cookie associated with a nag state for wp-config nag.
	 *
	 * @since 1.0.0
	 */
	public static function clear_wp_config_update_nag_state() {
		setcookie( 'wpc_nag', ' ', time() - 10000, ADMIN_COOKIE_PATH, COOKIE_DOMAIN, is_ssl() );

		// Make sure the nag is recalculated if this method was called before its generation.
		unset( $_COOKIE['wpc_nag'] );
	}
}
