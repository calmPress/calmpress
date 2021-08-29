<?php
/**
 * Upgrade API: Core_Upgrader class
 *
 * @package WordPress
 * @subpackage Upgrader
 * @since 4.6.0
 */

/**
 * Core class used for updating core.
 *
 * It allows for WordPress to upgrade itself in combination with
 * the wp-admin/includes/update-core.php file.
 *
 * @since 2.8.0
 * @since 4.6.0 Moved to its own file from wp-admin/includes/class-wp-upgrader.php.
 *
 * @see WP_Upgrader
 */
class Core_Upgrader extends WP_Upgrader {

	/**
	 * Initialize the upgrade strings.
	 *
	 * @since 2.8.0
	 */
	public function upgrade_strings() {
		$this->strings['up_to_date'] = __( 'calmPress is at the latest version.' );
		$this->strings['locked']     = __( 'Another update is currently in progress.' );
		$this->strings['no_package'] = __( 'Update package not available.' );
		/* translators: %s: Package URL. */
		$this->strings['downloading_package']   = sprintf( __( 'Downloading update from %s&#8230;' ), '<span class="code">%s</span>' );
		$this->strings['unpack_package']        = __( 'Unpacking the update&#8230;' );
		$this->strings['copy_failed']           = __( 'Could not copy files.' );
		$this->strings['copy_failed_space']     = __( 'Could not copy files. You may have run out of disk space.' );
		$this->strings['start_rollback']        = __( 'Attempting to roll back to previous version.' );
		$this->strings['rollback_was_required'] = __( 'Due to an error during updating, WordPress has rolled back to your previous version.' );
	}

	/**
	 * Upgrade WordPress core.
	 *
	 * @since 2.8.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem                WordPress filesystem subclass.
	 * @global callable           $_wp_filesystem_direct_method
	 *
	 * @param object $current Response object for whether WordPress is current.
	 * @param array  $args {
	 *     Optional. Arguments for upgrading WordPress core. Default empty array.
	 *
	 *     @type bool $pre_check_md5    Whether to check the file checksums before
	 *                                  attempting the upgrade. Default true.
	 *     @type bool $attempt_rollback Whether to attempt to rollback the chances if
	 *                                  there is a problem. Default false.
	 *     @type bool $do_rollback      Whether to perform this "upgrade" as a rollback.
	 *                                  Default false.
	 * }
	 * @return string|false|WP_Error New WordPress version on success, false or WP_Error on failure.
	 */
	public function upgrade( $current, $args = array() ) {
		global $wp_filesystem;

		$start_time = time();

		$defaults    = array(
			'pre_check_md5'                => true,
			'attempt_rollback'             => false,
			'do_rollback'                  => false,
			'allow_relaxed_file_ownership' => false,
		);
		$parsed_args = wp_parse_args( $args, $defaults );

		$this->init();
		$this->upgrade_strings();

		$res = $this->fs_connect( array( ABSPATH, WP_CONTENT_DIR ), $parsed_args['allow_relaxed_file_ownership'] );
		if ( ! $res || is_wp_error( $res ) ) {
			return $res;
		}

		$wp_dir = trailingslashit( $wp_filesystem->abspath() );

		// Might need to revisit this in the future, especially to support CLI,
		// but at first we are going to support only core upgrades.
		$to_download = 'core';

		// Lock to prevent multiple Core Updates occurring.
		$lock = WP_Upgrader::create_lock( 'core_updater', 15 * MINUTE_IN_SECONDS );
		if ( ! $lock ) {
			return new WP_Error( 'locked', $this->strings['locked'] );
		}

		$download = $this->download_package( $current->packages->$to_download, true );
		if ( is_wp_error( $download ) ) {
			WP_Upgrader::release_lock( 'core_updater' );
			return $download;
		}

		$working_dir = $this->unpack_package( $download );
		if ( is_wp_error( $working_dir ) ) {
			WP_Upgrader::release_lock( 'core_updater' );
			return $working_dir;
		}

		// Copy update-core.php from the new version into place.
		if ( ! $wp_filesystem->copy( $working_dir . '/wp-admin/includes/update-core.php', $wp_dir . 'wp-admin/includes/update-core.php', true ) ) {
			$wp_filesystem->delete( $working_dir, true );
			WP_Upgrader::release_lock( 'core_updater' );
			return new WP_Error( 'copy_failed_for_update_core_file', __( 'The update cannot be installed because we will be unable to copy some files. This is usually due to inconsistent file permissions.' ), 'wp-admin/includes/update-core.php' );
		}
		$wp_filesystem->chmod( $wp_dir . 'wp-admin/includes/update-core.php', FS_CHMOD_FILE );

		wp_opcache_invalidate( ABSPATH . 'wp-admin/includes/update-core.php' );
		require_once ABSPATH . 'wp-admin/includes/update-core.php';

		if ( ! function_exists( 'update_core' ) ) {
			WP_Upgrader::release_lock( 'core_updater' );
			return new WP_Error( 'copy_failed_space', $this->strings['copy_failed_space'] );
		}

		$result = update_core( $working_dir, $wp_dir );

		// In the event of an issue, we may be able to roll back.
		if ( $parsed_args['attempt_rollback'] && $current->packages->rollback && ! $parsed_args['do_rollback'] ) {
			$try_rollback = false;
			if ( is_wp_error( $result ) ) {
				$error_code = $result->get_error_code();
				/*
				 * Not all errors are equal. These codes are critical: copy_failed__copy_dir,
				 * mkdir_failed__copy_dir, copy_failed__copy_dir_retry, and disk_full.
				 * do_rollback allows for update_core() to trigger a rollback if needed.
				 */
				if ( false !== strpos( $error_code, 'do_rollback' ) ) {
					$try_rollback = true;
				} elseif ( false !== strpos( $error_code, '__copy_dir' ) ) {
					$try_rollback = true;
				} elseif ( 'disk_full' === $error_code ) {
					$try_rollback = true;
				}
			}

			if ( $try_rollback ) {
				/** This filter is documented in wp-admin/includes/update-core.php */
				apply_filters( 'update_feedback', $result );

				/** This filter is documented in wp-admin/includes/update-core.php */
				apply_filters( 'update_feedback', $this->strings['start_rollback'] );

				$rollback_result = $this->upgrade( $current, array_merge( $parsed_args, array( 'do_rollback' => true ) ) );

				$original_result = $result;
				$result          = new WP_Error(
					'rollback_was_required',
					$this->strings['rollback_was_required'],
					(object) array(
						'update'   => $original_result,
						'rollback' => $rollback_result,
					)
				);
			}
		}

		/** This action is documented in wp-admin/includes/class-wp-upgrader.php */
		do_action(
			'upgrader_process_complete',
			$this,
			array(
				'action' => 'update',
				'type'   => 'core',
			)
		);

		// Clear the current updates.
		delete_site_transient( 'update_core' );

		WP_Upgrader::release_lock( 'core_updater' );

		return $result;
	}
}
