<?php
/**
 * An implementation of the backup storage located at the 
 * a backup location accessable via "normal" file paths.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * An implementation of the backup storage located at the 
 * a backup location accessable via "normal" file paths.
 *
 * The name "local" refers to either backups on the same disk where the app is located,
 * or disks attached via network protocols (NFS and similar).
 * Can be used to RAM based disk volume and other non persistant storage,
 * but that is obviously not recommended.
 *
 * Stores committed sections and their metadata.
 *
 * @since 1.0.0
 */
class Local_Backup_Storage extends Backup_Storage {
	/**
	 * Directory containing committed section metadata files.
	 *
	 * @since 1.0.0
	 */
	const SECTION_META_DIRECTORY = 'sections-meta';

	/**
	 * The root directory at which backups are stored.
	 * Defaults to wp-content/.private/backup/ (in the constructor).
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected string $root;

	/**
	 * The identifier of the storage.
	 * Defaults to "default_local_storage".
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected string $id;

	/**
	 * Create a storage object based at specific root directory.
	 *
	 * When using this constructor from outside core code, use explicit and different
	 * parameter values. Using same $id will cause problems at some point. Same $root might work but
	 * unlikely to make sense.
	 * 
	 * @since 1.0.0
	 *
	 * @param string $root The absolute path of the backups root directory.
	 * @param string $id   The identifier to be used when internally identifying the storage.
	 *
	 * @throws \RuntimeException If the storage root directory cannot be created.
	 */
	public function __construct( string $root = WP_CONTENT_DIR . '/.private/backup/', $id = 'default_local_storage' ) {
		$this->root = trailingslashit( $root );
		$this->id   = $id;
		\calmpress\utils\ensure_dir_exists( $this->root );
	}

	/**
	 * Human redable description of the storage.
	 *
	 * @since 1.0.0
	 *
	 * @return string The description text.
	 */
	public function description() : string {
		return sprintf( __( 'Backups located at %s' ), $this->root );
	}

	/**
	 * A unique identifier of the storage.
	 *
	 * @since 1.0.0
	 *
	 * @return string The identifier.
	 */
	public function identifier() : string {
		return $this->id;
	}

	/**
	 * The committed sections stored in this storage.
	 *
	 * @since 1.0.0
	 *
	 * @return Backup_Section[] Committed sections with valid manifests.
	 */
	public function sections(): array {
		$sections       = array();
		$expected_fields = array(
			'type'          => 'string',
			'location'      => 'string',
			'version'       => 'string',
			'display_name'  => 'string',
			'creation_time' => 'integer',
			'directory'     => 'string',
		);
		$files = glob( $this->root . self::SECTION_META_DIRECTORY . '/*.json' ) ?: array();
		foreach ( $files as $file ) {
			// Fetch and verify the contents of the section metadata file.
			$data = json_decode( (string) @file_get_contents( $file ), true );
			if ( ! is_array( $data ) ) {
				trigger_error( 'Failed parsing the backup section metadata file ' . $file . ' because it does not contain a JSON object.' );
				continue;
			}
			$valid = true;
			foreach ( $expected_fields as $field => $type ) {
				if ( ! array_key_exists( $field, $data ) ) {
					trigger_error( 'Failed parsing the backup section metadata file ' . $file . ' because the "' . $field . '" field is missing.' );
					$valid = false;
					break;
				}
				if ( $type !== gettype( $data[ $field ] ) ) {
					trigger_error( 'Failed parsing the backup section metadata file ' . $file . ' because the "' . $field . '" field must have type ' . $type . '.' );
					$valid = false;
					break;
				}
			}
			if ( ! $valid ) {
				continue;
			}
			// Construction verifies the stored section files. Report and skip an invalid section.
			try {
				$identity   = new Backup_Section_Identity( $data['type'], $data['location'], $data['version'] );
				$sections[] = new Local_Backup_Section( $identity, $data['creation_time'], $data['display_name'], $this->root . $data['directory'], $file );
			} catch ( \RuntimeException $e ) {
				trigger_error( 'Failed loading the backup section described by ' . $file . ' because: ' . $e->getMessage() );
				continue;
			}
		}

		return $sections;
	}

	/**
	 * Create a temporary local working area for a section.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Section_Identity $identity     Section identity.
	 * @param string                  $display_name Human-readable section name.
	 *
	 * @return Temporary_Backup_Section Temporary section.
	 *
	 * @throws \RuntimeException If the temporary directory cannot be created.
	 */
	protected function create_temporary_section( Backup_Section_Identity $identity, string $display_name ): Temporary_Backup_Section {
		return new Local_Temporary_Backup_Section( $this->root, $identity, $display_name );
	}

}
