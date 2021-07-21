<?php
/**
 * Implementation of a local backup
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * A local backup class for backups consisting of two files as expect by the Local_Backup_Storage
 * class.
 *
 * @since 1.0.0
 */
class Local_Backup implements Backup {

	/**
	 * The meta information about the backup.
	 *
	 * @since 1.0.0
	 */
	private $backup_file;

	/**
	 * Constructor of a local backup object.
	 *
	 * Tries to create an object based on a meta file after validating the sanity of the
	 * content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $meta_file The path to the meta file of the backup.
	 *
	 * @throws Exception If meta file is not readable or the content do not contain all the info
	 *                   in the expected format.
	 */
	public function __construct( string $meta_file ) {
		$json = get_file_contents( $meta_file );
		if ( false === $json ) {
			throw new Exception( 'Meta file not readable:' . $meta_file );
		}

		$data = json_decode( $json, true );
		if ( null === $data ) {
			throw new Exception( 'Not a valid json format:' . $meta_file );
		}

		foreach ( [ 'backup_file', 'description', 'backup_engines', 'time_created' ] as $field ) {
			if ( ! isset( $data[ $field ] ) ) {
				throw new Exception( sprintf( 'The field %s is missing in the meta file %s', $field, $meta_file ) );
			}
		}

		foreach ( [ 'backup_file', 'description' ] as $field ) {
			if ( ! is_string( $data[ $field ] ) ) {
				throw new Exception( sprintf( 'The field %s is not a string in the meta file %s', $field, $meta_file ) );
			}
		}

		if ( ! is_array( $data['backup_engines'] ) ) {
			throw new Exception( sprintf( 'The field backup_engines is not an array in the meta file %s', $meta_file ) );
		}

		if ( (int) $data['time_created'] != $data[ 'time_created' ] ) {
			throw new Exception( sprintf( 'The field time_created is not an integer in the meta file %s', $meta_file ) );
		}

		$this->meta = $data;
	}

	/**
	 * The server unix time in which the backup was created.
	 *
	 * @since 1.0.0
	 *
	 * @return int The unix time.
	 */
	public function time_created() : int {
		return (int) $this->meta['time_created'];
	}

	/**
	 * The human readable description of the backup.
	 *
	 * @since 1.0.0
	 *
	 * @return int The description.
	 */
	public function description() : string {
		return $this->meta['description'];
	}

	/**
	 * Restore the backup.
	 *
	 * @since 1.0.0
	 */
	public function restore( restore_engines $engines ) {

	}
}