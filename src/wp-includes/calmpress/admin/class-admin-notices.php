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
	 * @since 1.0.0
	 */
	public static function htaccess_update_nag() {
		global $wp_rewrite;

		$nag = false;

		if ( ! is_multisite() && is_super_admin() && is_apache() ) {
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

		$nag = false;

		if ( ! is_multisite() && is_super_admin() ) {
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
	}

	/**
	 * Output an "Maintenance mode is active" admin notice when maintenance mode is active.
	 *
	 * The notice is displayed only to users which can deactivate maintenance mode.
	 *
	 * @since 1.0.0
	 */
	public static function maintenance_mode_active_nag() {

		$nag = false;

		if ( \calmpress\calmpress\Maintenance_Mode::is_active() ) {
			$nag = true;
		}

		if ( $nag ) {
			$screen = get_current_screen();
			if ( $screen && ( 'maintenance-mode' !== $screen->id ) ) {
				$msg = sprintf(
					/* translators: 1: Openning link to Maintenance mode page, 2: Closing </a> */
					esc_html__( 'The site is in maintenance mode. You can deactivate it in the %1$sMaintenance mode page%2$s.' ),
					'<a href="' . esc_url( admin_url( 'maintenance-mode.php' ) ) . '">',
					'</a>'
				);
				echo "<div class='notice notice-error'><p>$msg</p></div>";
			}
		}
	}

	/**
	 * Suggest admins to switch to editor or author role or dedicated user if they are
	 * creating new content.
	 *
	 * @since 1.0.0
	 */
	public static function suggest_reduce_admin_role() {

		$screen = get_current_screen();

		// Check if on new post page as admin.
		if ( 'post' === $screen->base && 'add' === $screen->action && current_user_can( 'administrator' ) ) {
			// Give notice if role is not already switched.
			$user = wp_get_current_user();
			if ( '' === $user->mocked_role() ) {
				$msg = sprintf(
					/* translators: 1: Openning link to profile page, 2: Closing </a> */
					esc_html__( 'It seems like you are using the administrator user to create content. You should probably create a dediated user for that, alternatively to reduce the admin clutter you can make your account to behave like editor or author in your %1$sprofile settings page%2$s.' ),
					'<a href="' . esc_url( admin_url( 'profile.php' ) ) . '#mock-role-wrap">',
					'</a>'
				);
				echo "<div class='notice notice-warning is-dismissible'><p>$msg</p></div>";
			}
		}

	}

	/**
	 * Notify admin when opcache miss rate is too high (above 5%) after a statistically meaningful
	 * amount of hits (10k).
	 *
	 * @since 1.0.0
	 */
	public static function opcache_miss_rate() {

		$screen = get_current_screen();
		if ( $screen && ( 'opcache' !== $screen->id ) ) {
			if ( current_user_can( 'manage_server' ) && \calmpress\opcache\Opcache::api_is_available() ) {
				$opcache = new \calmpress\opcache\Opcache();
				$stats   = $opcache->stats();
				if ( 5.0 < $stats->miss_rate() && 10000 < $stats->hits() ) {
					$msg = sprintf(
						/* translators: 1: Openning link to opcache page, 2: Closing </a> */
						esc_html__( 'The Opcode Cache has a poor performance, you should look for the reason in the %1$sOpcode Cache page%2$s and notify you server`s administrator.' ),
						'<a href="' . esc_url( admin_url( 'opcache.php' ) ) . '">',
						'</a>'
					);
					echo "<div class='notice notice-error'><p>$msg</p></div>";
				}
			}
		}
	}

	/**
	 * Notify admin when APCu has 5 or more apcu_store errors.
	 *
	 * @since 1.0.0
	 */
	public static function apcu_store_failures() {

		$screen = get_current_screen();
		if ( $screen && ( 'apcu' !== $screen->id ) ) {
			if ( current_user_can( 'manage_server' ) && \calmpress\apcu\APCu::APCu_is_avaialable() ) {
				$apcu = new \calmpress\apcu\APCu();
				if ( 5 <= $apcu->recent_store_failures() ) {
					$msg = sprintf(
						/* translators: 1: Openning link to apcu page, 2: Closing </a> */
						esc_html__( 'More than 5 writes to the APCu Cache had failed in the last hour, you might need to reset it at %1$APCu page%2$s and notify you server`s administrator.' ),
						'<a href="' . esc_url( admin_url( 'apcu.php' ) ) . '">',
						'</a>'
					);
					echo "<div class='notice notice-error'><p>$msg</p></div>";
				}
			}
		}
	}
}
