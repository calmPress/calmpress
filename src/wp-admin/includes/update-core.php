<?php
/**
 * WordPress core upgrade functionality.
 *
 * This file is the instalation driver when a new version is installed. At that
 * case the file from the new version is being run instead of the one from the
 * old version.
 *
 * @package WordPress
 * @subpackage Administration
 * @since 2.7.0
 */

/**
 * Stores files to be deleted.
 *
 * @since 2.7.0
 * @global array $_old_files
 * @var array
 * @name $_old_files
 */
global $_old_files;

$_old_files = array(
);

/**
 * Upgrades the core of calmPress.
 *
 * This will create a .maintenance file at the base of the calmPress directory
 * to ensure that people can not access the web site, when the files are being
 * copied to their locations.
 *
 * The files in the `$_old_files` list will be removed and the new files
 * copied from the zip file after the database is upgraded.
 *
 * The steps for the upgrader for after the new release is downloaded and
 * unzipped is:
 *   1. Test unzipped location for select files to ensure that unzipped worked.
 *   2. Create the .maintenance file in current calmPress base.
 *   3. Copy new calmPress directory over old calmPress files.
 *   4. Upgrade calmPress to new version.
 *     4.1. Copy all files/folders other than wp-content
 *   5. Delete new calmPress directory path.
 *   6. Delete .maintenance file.
 *   7. Remove old files.
 *   8. Delete 'update_core' option.
 *
 * There are several areas of failure. For instance if PHP times out before step
 * 6, then you will not be able to access any portion of your site. Also, since
 * the upgrade will not continue where it left off, you will not be able to
 * automatically remove old files and remove the 'update_core' option. This
 * isn't that bad.
 *
 * If the copy of the new calmPress over the old fails, then the worse is that
 * the new calmPress directory will remain.
 *
 * If it is assumed that every file will be copied over, including plugins and
 * themes, then if you edit the default theme, you should rename it, so that
 * your changes remain.
 *
 * @since 2.7.0
 *
 * @global WP_Filesystem_Base $wp_filesystem
 * @global array              $_old_files
 * @global wpdb               $wpdb
 *
 * @param string $from New release unzipped path.
 * @param string $to   Path to old calmPress installation.
 * @return WP_Error|null WP_Error on failure, null on success.
 */
function update_core($from, $to) {
	global $wp_filesystem, $_old_files, $wpdb;

	$calmpress_version = '0.9.9';
    $required_php_version = '5.2.4';
    $required_mysql_version = '5.0';

	@set_time_limit( 300 );

	/**
	 * Filters feedback messages displayed during the core update process.
	 *
	 * The filter is first evaluated after the zip file for the latest version
	 * has been downloaded and unzipped. It is evaluated five more times during
	 * the process:
	 *
	 * 1. Before calmPress begins the core upgrade process.
	 * 2. Before Maintenance Mode is enabled.
	 * 3. Before calmPress begins copying over the necessary files.
	 * 4. Before Maintenance Mode is disabled.
	 * 5. Before the database is upgraded.
	 *
	 * @since 2.5.0
	 *
	 * @param string $feedback The core update feedback messages.
	 */
	apply_filters( 'update_feedback', __( 'Verifying the unpacked files&#8230;' ) );

	$php_version    = phpversion();
	$mysql_version  = $wpdb->db_version();
	$php_compat     = version_compare( $php_version, $required_php_version, '>=' );
	if ( file_exists( WP_CONTENT_DIR . '/db.php' ) && empty( $wpdb->is_mysql ) )
		$mysql_compat = true;
	else
		$mysql_compat = version_compare( $mysql_version, $required_mysql_version, '>=' );

	if ( !$mysql_compat || !$php_compat )
		$wp_filesystem->delete($from, true);

	if ( !$mysql_compat && !$php_compat )
		return new WP_Error( 'php_mysql_not_compatible', sprintf( __('The update cannot be installed because calmPress %1$s requires PHP version %2$s or higher and MySQL version %3$s or higher. You are running PHP version %4$s and MySQL version %5$s.'), $calmpress_version, $required_php_version, $required_mysql_version, $php_version, $mysql_version ) );
	elseif ( !$php_compat )
		return new WP_Error( 'php_not_compatible', sprintf( __('The update cannot be installed because calmPress %1$s requires PHP version %2$s or higher. You are running version %3$s.'), $calmpress_version, $required_php_version, $php_version ) );
	elseif ( !$mysql_compat )
		return new WP_Error( 'mysql_not_compatible', sprintf( __('The update cannot be installed because calmPress %1$s requires MySQL version %2$s or higher. You are running version %3$s.'), $calmpress_version, $required_mysql_version, $mysql_version ) );

	/** This filter is documented in wp-admin/includes/update-core.php */
	apply_filters( 'update_feedback', __( 'Preparing to install the latest version&#8230;' ) );

	// We also copy version.php last so failed updates report their old version
	$skip = array( 'wp-includes/version.php' );
	$check_is_writable = array();

	// If we're using the direct method, we can predict write failures that are due to permissions.
	if ( $check_is_writable && 'direct' === $wp_filesystem->method ) {
		$files_writable = array_filter( $check_is_writable, array( $wp_filesystem, 'is_writable' ) );
		if ( $files_writable !== $check_is_writable ) {
			$files_not_writable = array_diff_key( $check_is_writable, $files_writable );
			foreach ( $files_not_writable as $relative_file_not_writable => $file_not_writable ) {
				// If the writable check failed, chmod file to 0644 and try again, same as copy_dir().
				$wp_filesystem->chmod( $file_not_writable, FS_CHMOD_FILE );
				if ( $wp_filesystem->is_writable( $file_not_writable ) )
					unset( $files_not_writable[ $relative_file_not_writable ] );
			}

			// Store package-relative paths (the key) of non-writable files in the WP_Error object.
			$error_data = array_keys( $files_not_writable );

			if ( $files_not_writable )
				return new WP_Error( 'files_not_writable', __( 'The update cannot be installed because we will be unable to copy some files. This is usually due to inconsistent file permissions.' ), implode( ', ', $error_data ) );
		}
	}

	/** This filter is documented in wp-admin/includes/update-core.php */
	apply_filters( 'update_feedback', __( 'Enabling Maintenance mode&#8230;' ) );
	// Create maintenance file to signal that we are upgrading
	$maintenance_string = '<?php $upgrading = ' . time() . '; ?>';
	$maintenance_file = $to . '.maintenance';
	$wp_filesystem->delete($maintenance_file);
	$wp_filesystem->put_contents($maintenance_file, $maintenance_string, FS_CHMOD_FILE);

	/** This filter is documented in wp-admin/includes/update-core.php */
	apply_filters( 'update_feedback', __( 'Copying the required files&#8230;' ) );
	// Copy new versions of WP files into place.
	$result = _copy_dir( $from, $to, $skip );
	if ( is_wp_error( $result ) )
		$result = new WP_Error( $result->get_error_code(), $result->get_error_message(), substr( $result->get_error_data(), strlen( $to ) ) );

	// Since we know the core files have copied over, we can now copy the version file
	if ( ! is_wp_error( $result ) ) {
		if ( ! $wp_filesystem->copy( $from . '/wp-includes/version.php', $to . 'wp-includes/version.php', true /* overwrite */ ) ) {
			$wp_filesystem->delete( $from, true );
			$result = new WP_Error( 'copy_failed_for_version_file', __( 'The update cannot be installed because we will be unable to copy some files. This is usually due to inconsistent file permissions.' ), 'wp-includes/version.php' );
		}
		$wp_filesystem->chmod( $to . 'wp-includes/version.php', FS_CHMOD_FILE );
	}

	// Check to make sure everything copied correctly.
	$skip = array( );
	$failed = array();

	// Some files didn't copy properly
	if ( ! empty( $failed ) ) {
		$total_size = 0;
		foreach ( $failed as $file ) {
			if ( file_exists( $working_dir_local . $file ) )
				$total_size += filesize( $working_dir_local . $file );
		}

		// If we don't have enough free space, it isn't worth trying again.
		// Unlikely to be hit due to the check in unzip_file().
		$available_space = @disk_free_space( ABSPATH );
		if ( $available_space && $total_size >= $available_space ) {
			$result = new WP_Error( 'disk_full', __( 'There is not enough free disk space to complete the update.' ) );
		} else {
			$result = _copy_dir( $from, $to, $skip );
			if ( is_wp_error( $result ) )
				$result = new WP_Error( $result->get_error_code() . '_retry', $result->get_error_message(), substr( $result->get_error_data(), strlen( $to ) ) );
		}
	}

	/** This filter is documented in wp-admin/includes/update-core.php */
	apply_filters( 'update_feedback', __( 'Disabling Maintenance mode&#8230;' ) );
	// Remove maintenance file, we're done with potential site-breaking changes
	$wp_filesystem->delete( $maintenance_file );

	// Handle $result error from the above blocks
	if ( is_wp_error($result) ) {
		$wp_filesystem->delete($from, true);
		return $result;
	}

	// Remove old files
	foreach ( $_old_files as $old_file ) {
		$old_file = $to . $old_file;
		if ( !$wp_filesystem->exists($old_file) )
			continue;

		// If the file isn't deleted, try writing an empty string to the file instead.
		if ( ! $wp_filesystem->delete( $old_file, true ) && $wp_filesystem->is_file( $old_file ) ) {
			$wp_filesystem->put_contents( $old_file, '' );
		}
	}

	// Upgrade DB with separate request
	/** This filter is documented in wp-admin/includes/update-core.php */
	apply_filters( 'update_feedback', __( 'Upgrading database&#8230;' ) );
	$db_upgrade_url = admin_url('upgrade.php?step=upgrade_db');
	wp_remote_post($db_upgrade_url, array('timeout' => 60));

	// Clear the cache to prevent an update_option() from saving a stale db_version to the cache
	wp_cache_flush();
	// (Not all cache back ends listen to 'flush')
	wp_cache_delete( 'alloptions', 'options' );

	// Remove working directory
	$wp_filesystem->delete($from, true);

	// Force refresh of update information
	if ( function_exists('delete_site_transient') )
		delete_site_transient('update_core');
	else
		delete_option('update_core');

	/**
	 * Fires after WordPress core has been successfully updated.
	 *
	 * @since 3.3.0
	 *
	 * @param string $calmpress_version The updated calmPress version.
	 */
	do_action( '_core_updated_successfully', $calmpress_version );

	// Clear the option that blocks auto updates after failures, now that we've been successful.
	if ( function_exists( 'delete_site_option' ) )
		delete_site_option( 'auto_core_update_failed' );

	return $calmpress_version;
}

/**
 * Copies a directory from one location to another via the WordPress Filesystem Abstraction.
 * Assumes that WP_Filesystem() has already been called and setup.
 *
 * This is a temporary function for the 3.1 -> 3.2 upgrade, as well as for those upgrading to
 * 3.7+
 *
 * @ignore
 * @since 3.2.0
 * @since 3.7.0 Updated not to use a regular expression for the skip list
 * @see copy_dir()
 *
 * @global WP_Filesystem_Base $wp_filesystem
 *
 * @param string $from     source directory
 * @param string $to       destination directory
 * @param array $skip_list a list of files/folders to skip copying
 * @return mixed WP_Error on failure, True on success.
 */
function _copy_dir($from, $to, $skip_list = array() ) {
	global $wp_filesystem;

	$dirlist = $wp_filesystem->dirlist($from);

	$from = trailingslashit($from);
	$to = trailingslashit($to);

	foreach ( (array) $dirlist as $filename => $fileinfo ) {
		if ( in_array( $filename, $skip_list ) )
			continue;

		if ( 'f' == $fileinfo['type'] ) {
			if ( ! $wp_filesystem->copy($from . $filename, $to . $filename, true, FS_CHMOD_FILE) ) {
				// If copy failed, chmod file to 0644 and try again.
				$wp_filesystem->chmod( $to . $filename, FS_CHMOD_FILE );
				if ( ! $wp_filesystem->copy($from . $filename, $to . $filename, true, FS_CHMOD_FILE) )
					return new WP_Error( 'copy_failed__copy_dir', __( 'Could not copy file.' ), $to . $filename );
			}
		} elseif ( 'd' == $fileinfo['type'] ) {
			if ( !$wp_filesystem->is_dir($to . $filename) ) {
				if ( !$wp_filesystem->mkdir($to . $filename, FS_CHMOD_DIR) )
					return new WP_Error( 'mkdir_failed__copy_dir', __( 'Could not create directory.' ), $to . $filename );
			}

			/*
			 * Generate the $sub_skip_list for the subdirectory as a sub-set
			 * of the existing $skip_list.
			 */
			$sub_skip_list = array();
			foreach ( $skip_list as $skip_item ) {
				if ( 0 === strpos( $skip_item, $filename . '/' ) )
					$sub_skip_list[] = preg_replace( '!^' . preg_quote( $filename, '!' ) . '/!i', '', $skip_item );
			}

			$result = _copy_dir($from . $filename, $to . $filename, $sub_skip_list);
			if ( is_wp_error($result) )
				return $result;
		}
	}
	return true;
}
