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
			$storage = new Local_Backup_Storage();
			$storage->create_backup( $description );
			return $ret;
		} catch ( \calmpress\calmpress\Timeout_Exception $e ) {
			$ret['status'] = 'incomplete';
			return $ret;
		} catch ( \Throwable $e ) {
			$ret['status']  = 'failed';
			$ret['message'] = $e->getMessage();
			return $ret;
		}
	}
}
