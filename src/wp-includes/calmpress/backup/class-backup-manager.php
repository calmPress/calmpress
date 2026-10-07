<?php
/**
 * Implementation of a bacup manager class
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * Coordinates backup engines, section storages, and the shared backup catalog.
 *
 * @since 1.0.0
 */
class Backup_Manager {
	use \calmpress\observer\Static_Observer_Collection {
		remove_observer as remove_initialization_observer;
		remove_all_observers as remove_all_initialization_observers;
	}

	/**
	 * Register an observer called when a backup manager is constructed.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Manager_Initialization_Observer $observer Observer to register.
	 *
	 * @return void
	 */
	public static function add_initialization_observer( Backup_Manager_Initialization_Observer $observer ): void {
		self::add_observer( $observer );
	}

	/**
	 * Directory containing the shared backup metadata catalog.
	 *
	 * @since 1.0.0
	 */
	private string $metadata_directory;

	/**
	 * Catalog files indexed by backup identifier after reading the catalog.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, string>
	 */
	private array $backup_files = array();

	/**
	 * Holds the registered storages.
	 *
	 * @since 1.0.0
	 *
	 * @var \calmpress\backup\Backup_Storage[]
	 */
	private array $storages = [];

	/**
	 * Holds the class names of the registered engines.
	*
	 * @since 1.0.0
	 *
	 * @var string[]
	 */
	private array $engines = [];

	/**
	 * Initialize the manager, mainly give a backup storages and engine chance to register.
	 *
	 * @since 1.0.0
	 *
	 * @param ?string $metadata_directory Shared backup metadata directory; defaults to the calmPress private backup catalog.
	 *
	 * @throws \RuntimeException If the metadata directory cannot be created.
	 */
	public function __construct( ?string $metadata_directory = null ) {
		$this->metadata_directory = trailingslashit( $metadata_directory ?? WP_CONTENT_DIR . '/.private/backup/backups-meta' );
		\calmpress\utils\ensure_dir_exists( $this->metadata_directory );

		$this->register_storage( new Local_Backup_Storage() );

		$this->register_engine( '\calmpress\backup\Core_Backup_Engine' );

		if ( null !== self::$collection ) {
			foreach ( self::$collection->observers() as $observer ) {
				$observer->register_with( $this );
			}
		}
	}

	/**
	 * Register a storage on which backups are stored and from which they are restored.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage The storage to register.
	 */
	public function register_storage( Backup_Storage $storage ) {
		$id = $storage->identifier();

		// If already registered we ignore the registration, with loggin an error when it is an obviously
		// bad code.
		if ( isset( $this->storages[ $id ] ) ) {
			// Weak object comparison to avoid errors when there is an attempt to register what is essentially
			// The same object which is created twice for whatever reason.
			if ( $this->storages[ $id ] != $storage ) {
					trigger_error( 'An attempt to register a different storage with an already used identifier' );
			}

			return;
		}

		$this->storages[ $id ] = $storage;
	}

	/**
	 * Unregister a storage.
	 *
	 * @since 1.0.0
	 *
	 * @param string $storage_id The storage's identifier.
	 */
	public function unregister_storage( string $storage_id ) {
		unset( $this->storages[ $storage_id ] );
	}

	/**
	 * Provide the storage object for a specific id if one registered.
	 *
	 * @since 1.0.0
	 *
	 * @param string $storage_id The identifier of the storage to retrieve.
	 *
	 * @return ?\calmpress\backup\Backup_Storage The storage if it is registered, or null if it is not.
	 */
	public function registered_storage_by_id( string $storage_id ): ?\calmpress\backup\Backup_Storage {
		if ( ! isset( $this->storages[ $storage_id ] ) ) {
			return null;
		}

		return $this->storages[ $storage_id ];
	}

	/**
	 * Provide avaiable storages.
	 *
	 * @since 1.0.0
	 *
	 * @return Backup_Storage[] The registered storages.
	 */
	public function available_storages(): array {
		return $this->storages;
	}

	/**
	 * Register an engine that handles some specific type of backup and restore.
	 *
	 * @since 1.0.0
	 *
	 * @param string $engine_class The class implementing the engine.
	 */
	public function register_engine( string $engine_class ) {

		if ( ! is_a( $engine_class, '\calmpress\backup\Engine_Specific_Backup' , true ) ) {
			trigger_error( 'An attempt to register an engine which do not implement Engine_Specific_Backup interface' );
		}

		$id = $engine_class::identifier();

		// If already registered we ignore the registration, with logging an error when it is an obviously
		// bad code.
		if ( isset( $this->engines[ $id ] ) ) {
			if ( $this->engines[ $id ] !== $engine_class ) {
					trigger_error( 'An attempt to register a different engine with an already used identifier' );
			}

			return;
		}

		$this->engines[ $id ] = $engine_class;
	}

	/**
	 * Unregister an engine.
	 *
	 * @since 1.0.0
	 *
	 * @param string $engine_id The identifier of the engine.
	 */
	public function unregister_engine( string $engine_id ) {
		unset( $this->engines[ $engine_id ] );
	}

	/**
	 * Provide the engine class for a specific id if one registered.
	 *
	 * @since 1.0.0
	 *
	 * @param string $engine_id The identifier of the storage to retrieve.
	 *
	 * @return string The engine if it is registered, or empty striong if it is not.
	 */
	public function registered_engine_by_id( string $engine_id ): string {
		if ( ! isset( $this->engines[ $engine_id ] ) ) {
			return '';
		}

		return $this->engines[ $engine_id ];
	}

	/**
	 * Provide avaiable engines.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] The class names of the registered engines. The key is the identifier of
	 *                  the engine.
	 */
	public function available_engines(): array {
		return $this->engines;
	}

	/**
	 * List backups in the shared catalog, newest first.
	 *
	 * @since 1.0.0
	 *
	 * @return \calmpress\backup\Backup[] A sorted array containing all the backups.
	 */
	public function existing_backups() : array {
		return $this->read_backups( false );
	}

	/**
	 * Read the shared backup catalog, optionally removing malformed records.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $remove_malformed Whether to remove malformed metadata files.
	 *
	 * @return Backup[] Valid backups whose section storage is registered.
	 */
	private function read_backups( bool $remove_malformed ): array {
		$backups = [];
		$this->backup_files = array();
		foreach ( glob( $this->metadata_directory . '*.json' ) ?: array() as $file ) {
			$metadata = @file_get_contents( $file );
			if ( false === $metadata ) {
				continue;
			}
			$data = json_decode( $metadata, true );
			$storage_id = is_array( $data ) ? ( $data['storage_id'] ?? null ) : null;
			if ( ! is_string( $storage_id ) ) {
				$this->handle_malformed_metadata( $file, $remove_malformed );
				continue;
			}
			$storage = $this->registered_storage_by_id( $storage_id );
			if ( null === $storage ) {
				$this->handle_malformed_metadata( $file, $remove_malformed );
				continue;
			}
			try {
				$backup = new Backup( $metadata, $storage );
				$backups[] = $backup;
				$this->backup_files[ $backup->unique_id ] = $file;
			} catch ( \Exception $exception ) {
				$this->handle_malformed_metadata( $file, $remove_malformed );
			}
		}

		usort(
			$backups,
			static function ( $a, $b ) {
				return $b->time <=> $a->time;
			}
		);

		return $backups;
	}

	/**
	 * Report a malformed catalog file and remove it during cleanup.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file             Metadata file path.
	 * @param bool   $remove_malformed Whether cleanup is removing malformed files.
	 *
	 * @throws \RuntimeException If a malformed metadata file cannot be deleted.
	 */
	private function handle_malformed_metadata( string $file, bool $remove_malformed ): void {
		if ( $remove_malformed ) {
			if ( ! @unlink( $file ) ) {
				throw new \RuntimeException( 'Failed deleting malformed backup metadata: ' . $file );
			}
		} else {
			trigger_error( 'Failed parsing the backup metadata file ' . $file );
		}
	}

	/**
	 * Find a backup in the shared catalog by its identifier.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id The identifier.
	 *
	 * @return \calmpress\backup\Backup The backup, or null if non is found.
	 *
	 * @throws \Exception If a backup with such id could not be found.
	 */
	public function backup_by_id( string $id ) : \calmpress\backup\Backup {

		foreach ( $this->existing_backups() as $backup ) {
			if ( $id === $backup->unique_id ) {
				return $backup;
			}
		}

		throw new \Exception( 'Such a backup do not exists' );
	}

	/**
	 * Create a backup at a specific storage with specific backup engines.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $description The textual description of the bakup, reason for its creation.
	 * @param string   $storage_id  The identifier of the storage to back to.
	 * @param int      $timeout     The maximal time a backup partial operation should try to not exceed.
	 * @param string[] $engines_ids The list of identifiers of the engines to use in the backup.
	 *
	 * @throws \Exception When a storage or engine identified by the parameters do not exists, or some
	 *                    error happening during backup.
	 * @throws \InvalidArgumentException If no backup engine is selected.
	 * @throws \calmpress\calmpress\Timeout_Exception If backup ran out of allocated time interval
	 *                                                and requires more "time slices" to complete.
	 */
	public function create_backup(
		string $description,
		string $storage_id,
		int $timeout, 
		string ...$engine_ids ) {
		if ( empty( $engine_ids ) || in_array( '', $engine_ids, true ) ) {
			throw new \InvalidArgumentException( 'Select at least one backup engine.' );
		}

		$storage = $this->registered_storage_by_id( $storage_id );
		if ( null === $storage ) {
			throw new \Exception( 'Unknown storage ' . $storage_id );
		}

		$engines = [];
		foreach ( $engine_ids as $engine_id ) { 
			$engine = $this->registered_engine_by_id( $engine_id );
			if ( '' === $engine ) {
				throw new \Exception( 'Unknown engine ' . $engine_id );
			}
			$engines[ $engine_id ] = $engine;
		}

		$sections_by_engine = [];
		foreach ( $engines as $id => $engine ) {
			$sections = $engine::backup( $storage, $timeout );

			// Keep the engine's name in the backup so it remains identifiable if the engine is later removed.
			$sections_by_engine[ $id ] = array(
				'description' => $engine::description(),
				'sections'    => $sections,
			);
		}

		$this->store_backup_meta(
			wp_json_encode(
				array(
					'description' => $description,
					'time'        => time(),
					'unique_id'   => wp_generate_uuid4(),
					'storage_id'  => $storage_id,
					'engines'     => $sections_by_engine,
				)
			)
		);
	}

	/**
	 * Write backup metadata to the shared catalog.
	 *
	 * @since 1.0.0
	 *
	 * @param string $metadata Encoded backup metadata.
	 *
	 * @throws \RuntimeException If the metadata cannot be written or moved into place.
	 */
	private function store_backup_meta( string $metadata ): void {
		$path = $this->metadata_directory . wp_generate_uuid4() . '.json';
		$temp = $path . '.tmp';
		try {
			if ( strlen( $metadata ) !== @file_put_contents( $temp, $metadata ) ) {
				throw new \RuntimeException( 'Failed writing backup metadata.' );
			}
			if ( ! @rename( $temp, $path ) ) {
				throw new \RuntimeException( 'Failed moving backup metadata into place.' );
			}
		} finally {
			if ( file_exists( $temp ) ) {
				@unlink( $temp );
			}
		}
	}

	/**
	 * Clean registered backup storages which support automatic cleanup.
	 *
	 * @since 1.0.0
	 */
	public static function cleanup_registered_storages(): void {
		( new Backup_Manager() )->cleanup();
	}

	/**
	 * Remove malformed catalog records and expired unreferenced sections.
	 *
	 * @since 1.0.0
	 *
	 * @throws \RuntimeException If a catalog file or section selected for cleanup cannot be deleted.
	 */
	public function cleanup(): void {
		$references = array();
		foreach ( $this->read_backups( true ) as $backup ) {
			foreach ( $backup->section_identities() as $identity ) {
				$references[ $backup->storage->identifier() ][] = $identity;
			}
		}
		foreach ( $this->storages as $id => $storage ) {
			$storage->cleanup( time() - WEEK_IN_SECONDS, $references[ $id ] ?? array() );
		}
	}

	/**
	 * Delete a backup record from the shared catalog.
	 * 
	 * No failure if the backup did not exist.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id The identifier indentifying the specific backup.
	 *
	 * @throws \Exception If backup do not exist or deletion had failed.
	 */
	public function delete_backup( string $id ) {
		try {
			$backup = $this->backup_by_id( $id );
		} catch ( \Exception $e ) {
			// Backup do not exist is as good as it being deleted.
			return;
		}

		$file = $this->backup_files[ $backup->unique_id ];
		if ( ! @unlink( $file ) ) {
			throw new \RuntimeException( 'Failed deleting backup metadata: ' . $file );
		}
	}
}
