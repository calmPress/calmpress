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
		global $wp_rewrite;

		if ( ! is_multisite() && is_super_admin() && got_mod_rewrite() ) {
			$home_path      = get_home_path();
			$existing_rules = array_filter( extract_from_markers( $home_path . '.htaccess', 'WordPress' ) );
			$new_rules      = array_filter( explode( "\n", $wp_rewrite->mod_rewrite_rules() ) );
			if ( $new_rules !== $existing_rules ) {
				$msg = sprintf(
					/* translators: 1: The file name, 2: the section name */
					__( 'The %1$s file contain different settings in its %2$s section than what it should have.' ),
					'<code>.htaccess</code>',
					'<code>WordPress</code>'
				);
				$screen = get_current_screen();
				if ( $screen && ( 'options-htaccess' !== $screen->id ) ) {
					$msg .= '<br>' . sprintf(
						/* translators: 1: The file name, 2: The URL of the permalink setting page */
						__( 'You can fix the %1$s file by going to the <a href="%2$s">htaccess settings page</a> which will automatically try to update the file, and follow additional instructions if it fails.' ),
						'<code>.htaccess</code>',
						esc_url( admin_url( 'options-htaccess.php' ) )
					);
				}
				echo "<div class='notice notice-error'><p>$msg</p></div>";
			}
		}
	}
}
