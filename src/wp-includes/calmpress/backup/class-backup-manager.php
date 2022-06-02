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
 * A local backup class representing the backups stored at the default core backup folder.
 *
 * @since 1.0.0
 */
class Backup_Manager {

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
	 */
	public function __construct() {

		$this->register_storage( new Local_Backup_Storage() );

		$this->register_engine( '\calmpress\backup\Core_Backup_Engine' );

		// Trigger for additional storages to register.
		do_action( 'calm_backup_manager_init' );
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
	 * @return string[] The registered engines.
	 */
	public function available_engines(): array {
		return $this->engines;
	}

	public function existing_backups() {
		$backups = [];

		foreach ( $this->storages as $storage ) {
			$bks = $storage->backups()->as_array();
			foreach ( $bks as $backup ) {
				$backups[] = $backup;
			}
		}

		return $backups;
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
 	 * @throws Timeout_Exception If backup ran out of allocated time interval
	 *                           and requires more "time slices" to complete.
	 */
	public function create_backup(
		string $description,
		string $storage_id,
		int $timeout, 
		string ...$engine_ids ) {

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

		$engines_data = []; 
		foreach ( $engines as $id => $engine ) {
			$engines_data[ $id ] = $engine::backup( $storage, $timeout );
		}

		$storage->store_backup_meta( $description, current_time( 'U', true ), $engines_data );
	}

	public function backups() {

	}
}