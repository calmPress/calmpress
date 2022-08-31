<?php
/**
 * Implementation of utilities functions for backup management.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * An implementation of utility functions related to backup management.
 *
 * @since 1.0.0
 */
class Utils {

	/**
	 * Handle user initiated backup.
	 *
	 * Used as action callback for the calmpress/create_backup rest route.
	 *
     * Create backup request should include the fields 'nonce' which contain the nonce for the
     * request and a field 'description' which contains the textual description of the reason
     * for the backup (might be empty but required).
     *
     * The reply contains a field named 'status' which can have one the values of
     * - 'incomplete' which indicates that the backup was not finished and the request should
     * be sent as is again.
     *
     * - 'failed' which indicates that there was a problem with performing the backup. In that case
     * the field 'message' will include an unescaped textual description of the problem.
     * 
     * - 'complete' which indicates that the backup was fully completed.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request. It is assumed it was sanitized and validated
	 *                                 That it includes a description field.
	 *
	 * @return string[] An array which includes a status field and a message where status field values
	 *                  are described above, and message field is untranslated and unescaped
	 *                  addition information to enhance the meaning of the status field.
	 */
	public static function handle_backup_request( \WP_REST_Request $request ): array {

		$ret = [
			'status'  => 'complete',
			'message' => '',
		];

		try {
			$description = $request['description'];
			$storage     = $request['storage'];
			$engines     = $request['engines'];
			$manager     = new \calmpress\backup\Backup_Manager();
			$manager->create_backup( $description, $storage, 15, ...explode( ',', $engines ) );
			return $ret;
		} catch ( \calmpress\calmpress\Timeout_Exception $e ) {
			$ret['status'] = 'incomplete';
			return $ret;
		} catch ( \Error $e ) {
			// PHP execution exceptions. Add more debugging info.
			$ret['status']  = 'failed';
			$ret['message'] = $e->getMessage() . ' type: ' . get_class( $e ) . ' file: ' . $e->getFile() . ' line: ' . $e->getLine();
			return $ret;
		} catch ( \Throwable $e ) {
			$ret['status']  = 'failed';
			$ret['message'] = $e->getMessage();
			return $ret;
		}
	}

	/**
	 * Handle user initiated backup restore.
	 *
	 * Used as action callback for the calmpress/restore_backup rest route.
	 *
     * Restore backup request should include the fields 'nonce' which contain the nonce for the
     * request and a field 'id' which contains backup identifier.
     *
     * The reply contains a field named 'status' which can have one the values of
     * - 'incomplete' which indicates that the restore was not finished and the request should
     * be sent as is again.
     *
     * - 'data_mismatch' which indicates that the data that the backup contains cannot be
	 * processed by the current code. This can happen due to actual data curroption, missing engine
	 * that was used in the backup creation, or mismatching versions between the engine used to create
	 * the backup and the "current" engine.
	 * The response will also include an "engine_ids' field which will contain a comma separated list
	 * of the engine identifiers for which the failure happened.
     * 
     * - 'failed' which indicates that there was a problem with performing the restore. In that case
     * the field 'message' will include an unescaped textual description of the problem.
     * 
     * - 'complete' which indicates that the restore was fully completed.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request. It is assumed it was sanitized and validated
	 *                                 That it includes a description field.
	 *
	 * @return string[] An array which includes a status field and a message where status field values
	 *                  are described above, and message field is untranslated and unescaped
	 *                  addition information to enhance the meaning of the status field.
	 */
	public static function handle_restore_backup_request( \WP_REST_Request $request ): array {

		$ret = [
			'status'  => 'complete',
			'message' => '',
		];

		try {
			$backup_id = $request['backup_id'];
			$manager   = new \calmpress\backup\Backup_Manager();
			$backup    = $manager->backup_by_id( $backup_id );
			return $ret;
		} catch ( \calmpress\calmpress\Timeout_Exception $e ) {
			$ret['status'] = 'incomplete';
			return $ret;
		} catch ( \Error $e ) {
			// PHP execution exceptions. Add more debugging info.
			$ret['status']  = 'failed';
			$ret['message'] = $e->getMessage() . ' type: ' . get_class( $e ) . ' file: ' . $e->getFile() . ' line: ' . $e->getLine();
			return $ret;
		} catch ( \Throwable $e ) {
			$ret['status']  = 'failed';
			$ret['message'] = $e->getMessage();
			return $ret;
		}
	}

	/**
	 * Handle delete backup action.
	 *
	 * Expect the following values in the request (most likely A GET),
	 * _wp_nonce - The nonce verifying the request
	 * backup    - The ID of the backup to delete.
	 *
	 * @since 1.0.0
	 */
	public static function handle_delete_backup() {

		if  ( ! array_key_exists( 'backup', $_REQUEST ) ) {
			wp_die(
				esc_html__( 'Something went wrong, backup to delete is not given.' ),
				__( 'Error' ),
				[ 'response' => 404 ]
			);
		}

		$id = $_REQUEST[ 'backup' ];

		// Check nonce and capability.
		if  ( ! array_key_exists( '_wpnonce', $_REQUEST ) || 
		      ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'delete_backup' ) ||
			  ! current_user_can( 'backup' ) ) {
			wp_die(
				esc_html__( 'Missing credentials to perform the delete, refresh and try again.' ),
				__( 'Error' ),
				[ 'response' => 403 ]
			);
		}

		$notices = new \calmpress\admin\Admin_Notices_Handler();

		$backup_manager = new \calmpress\backup\Backup_Manager();
		try {
			$backup_manager->delete_backup( $id );
			$notices->add_success_message( esc_html__( 'Delete completed' ) );
		} catch ( \Exception $e ) {
			$notices->add_error_message(
				sprintf( esc_html__( 'Delete had failed. The reported reason is: %s' ), $e->message )
			);
		}
		\calmpress\utils\redirect_admin_with_action_results( admin_url( 'backups.php' ), $notices );
	}

	/**
	 * Handle bulk backup actions.
	 *
	 * Expect the following values in the request (most likely A GET),
	 * _wp_nonce - The nonce verifying the request
	 * backup    - The ID of the backup to delete.
	 */
	public static function handle_bulk_backup() {

		// Check nonce and capability.
		if  ( ! array_key_exists( '_wpnonce', $_POST ) || 
				! wp_verify_nonce( $_POST['_wpnonce'], 'bulk-backups' ) ||
				! current_user_can( 'backup' ) ) {
			wp_die(
				esc_html__( 'Missing credentials to perform the operation, refresh and try again.' ),
				__( 'Error' ),
				[ 'response' => 403 ]
			);
		}

		if ( 'delete' !== $_POST['subaction'] || 0 === count( $_POST['backups'] ) ) {
			wp_redirect( admin_url( 'backups.php' ) );
			die();
		}

		$notices        = new \calmpress\admin\Admin_Notices_Handler();
		$backup_manager = new \calmpress\backup\Backup_Manager();
		$success        = 0;
		foreach ( $_POST['backups'] as $id ) {
			try {
				$backup_manager->delete_backup( $id );
				$success++;
			} catch ( \Exception $e ) {
				$backup = $backup_manager->backup_by_id( $id );
				$notices->add_error_message(
					/* translators: 1: backup description, 2: internal error message. */
					sprintf( esc_html__( 'Delete of %1$s had failed. The reported reason is: %2$s' ), $backup->description(), $e->message )
				);
			}
		}
		if ( 0 !== $success ) {
			$notices->add_success_message(
				esc_html(
					sprintf(
						/* translators: %s: Number deleted backups. */
						_n( '%s backup deleted.', '%s backups deleted.', $success ),
						number_format_i18n( $success )
					)
				)
			);
		}
		\calmpress\utils\redirect_admin_with_action_results( admin_url( 'backups.php' ), $notices );
	}
}
