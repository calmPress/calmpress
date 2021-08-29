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
	 * Output an .htaccess needs to be resaved admin notice when the content of
	 * the WordPress section of the .htaccess file is not the same as the one
	 * calculated in code.
	 *
	 * The notice is not displayed on multisite setup, and it is displayed only
	 * for admins when the site uses apache.
	 *
	 * @since 1.0.0
	 */
	public static function htaccess_update_nag() {
		global $wp_rewrite, $is_apache;

		if ( ! is_multisite() && is_super_admin() && $is_apache ) {
			$home_path      = ABSPATH;
			$existing_rules = array_filter( extract_from_markers( $home_path . '.htaccess', 'WordPress' ) );
			$new_rules      = array_filter( explode( "\n", $wp_rewrite->mod_rewrite_rules() ) );
			if ( $new_rules !== $existing_rules ) {
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
		}
	}

	/**
	 * Output an wp-config.php needs to be resaved admin notice when the content of
	 * the User section of the wp-config.php file is not the same as the one
	 * calculated in code.
	 *
	 * The notice is not displayed on multisite setup.
	 *
	 * @since 1.0.0
	 */
	public static function wp_config_update_nag() {

		if ( ! is_multisite() && is_super_admin() ) {
			$wp_config      = \calmpress\wp_config\wp_config::current_wp_config();
			$existing_rules = $wp_config->user_section_in_file();
			$new_rules      = get_option( 'wp_config_user_section' );
			if ( $new_rules !== $existing_rules ) {
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
		}
	}
}
