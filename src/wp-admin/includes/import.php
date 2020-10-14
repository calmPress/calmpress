<?php
/**
 * WordPress Administration Importer API.
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * Retrieve list of importers.
 *
 * @since 2.0.0
 *
 * @global array $wp_importers
 * @return array
 */
function get_importers() {
	global $wp_importers;
	if ( is_array( $wp_importers ) ) {
		uasort( $wp_importers, '_usort_by_first_member' );
	}
	return $wp_importers;
}

/**
 * Sorts a multidimensional array by first member of each top level member
 *
 * Used by uasort() as a callback, should not be used directly.
 *
 * @since 2.9.0
 * @access private
 *
 * @param array $a
 * @param array $b
 * @return int
 */
function _usort_by_first_member( $a, $b ) {
	return strnatcasecmp( $a[0], $b[0] );
}

/**
 * Register importer for WordPress.
 *
 * @since 2.0.0
 *
 * @global array $wp_importers
 *
 * @param string   $id          Importer tag. Used to uniquely identify importer.
 * @param string   $name        Importer name and title.
 * @param string   $description Importer description.
 * @param callable $callback    Callback to run.
 * @return WP_Error Returns WP_Error when $callback is WP_Error.
 */
function register_importer( $id, $name, $description, $callback ) {
	global $wp_importers;
	if ( is_wp_error( $callback ) ) {
		return $callback;
	}
	$wp_importers[ $id ] = array( $name, $description, $callback );
}

/**
 * Cleanup importer.
 *
 * Removes attachment based on ID.
 *
 * @since 2.0.0
 *
 * @param string $id Importer ID.
 */
function wp_import_cleanup( $id ) {
	wp_delete_attachment( $id );
}

/**
 * Handle importer uploading and add attachment.
 *
 * @since 2.0.0
 *
 * @return array Uploaded file's details on success, error message on failure
 */
function wp_import_handle_upload() {
	if ( ! isset( $_FILES['import'] ) ) {
		return array(
			'error' => sprintf(
				/* translators: 1: php.ini, 2: post_max_size, 3: upload_max_filesize */
				__( 'File is empty. Please upload something more substantial. This error could also be caused by uploads being disabled in your %1$s file or by %2$s being defined as smaller than %3$s in %1$s.' ),
				'php.ini',
				'post_max_size',
				'upload_max_filesize'
			),
		);
	}

	$overrides                 = array(
		'test_form' => false,
		'test_type' => false,
	);
	$_FILES['import']['name'] .= '.txt';
	$upload                    = wp_handle_upload( $_FILES['import'], $overrides );

	if ( isset( $upload['error'] ) ) {
		return $upload;
	}

	// Construct the object array.
	$object = array(
		'post_title'     => wp_basename( $upload['file'] ),
		'post_content'   => $upload['url'],
		'post_mime_type' => $upload['type'],
		'guid'           => $upload['url'],
		'context'        => 'import',
		'post_status'    => 'private',
	);

	// Save the data.
	$id = wp_insert_attachment( $object, $upload['file'] );

	/*
	 * Schedule a cleanup for one day from now in case of failed
	 * import or missing wp_import_cleanup() call.
	 */
	wp_schedule_single_event( time() + DAY_IN_SECONDS, 'importer_scheduled_cleanup', array( $id ) );

	return array(
		'file' => $upload['file'],
		'id'   => $id,
	);
}
