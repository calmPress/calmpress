<?php
/**
 * Implementation of class for backups being managed in the admin.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * A representation of a specific backup.
 *
 * @since 1.0.0
 */
class Managed_Backup {

	private $backup;
	private $uniqe_id;

    public function __construct( Backup $backup, string $uniqe_id) {

    }

    public function time_created() : DateTimeImmutable {
		return $this->backup->time_created();
	}

    public function description() : string {
		return $this->backup->description();
	}

    public function uniqe_id(){
		return $this->unique_id;
    }

	public function type() : string {

	}

	public function storage() : Backup_Storage {
		return $this->backup->storage();
	}

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
	 */
	public static function handle_backup_request( \WP_REST_Request $request ): array {

		$ret = [
			'status'  => 'complete',
			'message' => '',
		];

		$description = $request['description'];
		try {
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