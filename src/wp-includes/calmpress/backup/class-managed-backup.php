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
	 * Verify capability, nonce, and validitty of referer data a POST request. Die if the
	 * user is not allowed to manage backups, or nonce/referer include
	 * bad data. 
	 *
	 * @since 1.0.0
	 *
	 * @param string $action The name of the action expected to be used for generating the nonce
	 *                       and admin referer fields in the request.
	 */
	private static function verify_post_request( string $action ) {
		if ( ! current_user_can( 'backup' ) ) {
			wp_die(
				'<h1>' . __( 'You need additional permission.' ) . '</h1>' .
				'<p>' . __( 'Sorry, you are not allowed to manage backups at this site.' ) . '</p>',
				403
			);
		}
		check_admin_referer( $action );
	}

	/**
	 * Handle user initiated backup.
	 *
	 * Used as action callback for 'admin-post'.
	 *
	 * @since 1.0.0
	 */
	public static function handle_backup_request() {
		static::verify_post_request( 'new_backup' );

		$description = wp_unslash( $_POST['description'] );
		try {
			$storage = new Local_Backup_Storage();
			$storage->create_backup( $description );
			add_settings_error(
				'new_backup',
				'settings_updated',
				__( 'Backup was created.' ),
				'success'
			);	
		} catch ( \Throwable $e ) {
			add_settings_error(
				'new_backup',
				'new_backup',
				esc_html__( 'Something went wrong, reported cause is: ' )  . esc_html( $e->getMessage() ),
				'error'
			);
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );	
	
		// Redirect back to the page from which the form was submitted.
		$goback = add_query_arg( 'settings-updated', 'true', wp_get_referer() );
		wp_redirect( $goback );
		exit;			

	}
}