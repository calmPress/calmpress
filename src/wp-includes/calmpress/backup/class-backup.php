<?php
/**
 * Implementation of a backup class
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * A representation of a backup.
 *
 * @since 1.0.0
 */
class Backup {

	/**
	 * The storage engine.
	 *
	 * @since 1.0.0
	 */
	public readonly Backup_Storage $storage;

	/**
	 * The unix time in which the backup was created.
	 *
	 * @var int
	 *
	 * @since 1.0.0
	 */
	public readonly int $time;

	/**
	 * A unique identifier for the backup.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	public readonly string $unique_id;

	/**
	 * The backup's description.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	public readonly string $description;

	/**
	 * The engines which were used when creating the backup and their section identities.
	 * 
	 * @var array<string, Backup_Section_Identity[]>
	 *
	 * @since 1.0.0
	 */
	public readonly array $engines;

	/**
	 * Descriptions captured when each backup engine ran.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, string>
	 */
	public readonly array $engine_descriptions;

	/**
	 * Constructor of a backup object.
	 *
	 * Create an object based on a meta information about the backup.
	 *
	 * @since 1.0.0
	 *
	 * @param string         $json_data The "meta" data about the backup in a json format.
	 * @param Backup_Storage $storage   The storage containing the backup's sections.
	 *
	 * @throws \Exception If the metadata is malformed or does not contain the expected information.
	 */
	public function __construct( string $json_data, Backup_Storage $storage ) {
		$this->storage = $storage;

		$data = json_decode( $json_data, true );
		if ( null === $data ) {
			throw new \Exception( 'Not a valid json format ' );
		}
		$storage_id = $data['storage_id'] ?? null;
		if ( ! is_string( $storage_id ) || $storage_id !== $storage->identifier() ) {
			throw new \Exception( 'The "storage_id" field does not identify the section storage.' );
		}

		if ( ! isset( $data[ 'description' ] ) ) {
			throw new \Exception( 'The "description" field is missing' );
		}

		$description = $data[ 'description' ];
		if ( ! is_scalar( $description ) ) {
			throw new \Exception( 'The field "description" is not parseable as string' );
		}
		$this->description = (string) $description;

		if ( ! isset( $data[ 'unique_id' ] ) ) {
			throw new \Exception( 'The "unique_id" field is missing' );
		}

		$unique_id = $data[ 'unique_id' ];
		if ( ! is_scalar( $unique_id ) ) {
			throw new \Exception( 'The field "unique_id" is not parseable as string' );
		}
		$this->unique_id = (string) $unique_id;

		if ( ! isset( $data[ 'time' ] ) ) {
			throw new \Exception( 'The "time" field is missing' );
		}

		$time = filter_var( $data[ 'time' ], FILTER_VALIDATE_INT );
		if ( false === $time ) {
			throw new \Exception( 'The field "time" is not an integer' );
		}
		$this->time = $time;

		if ( ! isset( $data[ 'engines' ] ) ) {
			throw new \Exception( 'The "engines" field is missing' );
		}
		if ( ! is_array( $data[ 'engines' ] ) ) {
			throw new \Exception( 'The "engines" field is not an array' );
		}

		$engines             = array();
		$engine_descriptions = array();
		foreach ( $data['engines'] as $engine_id => $engine_data ) {
			if ( ! is_string( $engine_id ) || ! is_array( $engine_data ) || ! is_string( $engine_data['description'] ?? null ) || ! is_array( $engine_data['sections'] ?? null ) ) {
				throw new \Exception( 'The "engines" field contains invalid engine data.' );
			}
			$engine_sections = $engine_data['sections'];
			$sections = array();
			foreach ( $engine_sections as $identity ) {
				if ( ! is_array( $identity ) || ! is_string( $identity['type'] ?? null ) || ! is_string( $identity['location'] ?? null ) || ! is_string( $identity['version'] ?? null ) ) {
					throw new \Exception( 'Engine backup data contains an invalid section identity' );
				}
				$sections[] = new Backup_Section_Identity( $identity['type'], $identity['location'], $identity['version'] );
			}
			$engines[ $engine_id ] = $sections;
			$engine_descriptions[ $engine_id ] = $engine_data['description'];
		}
		$this->engines             = $engines;
		$this->engine_descriptions = $engine_descriptions;

	}

	/**
	 * Find which engines specified to participate in the backup's creation are missing from the
	 * given engines ids list.
	 *
	 * @param string[] $engine_ids The engine ids to compare against.
	 *
	 * @return string[] An array which contains the engine ids which were used in creating the back up
	 *                and are missing from the list.
	 */
	public function missing_engines(string ...$engine_ids ): array {
		return array_diff( array_keys( $this->engines ), $engine_ids );
	}

	/**
	 * Restore the backup.
	 *
	 * If the backup includes engines which are not currently registered the retore will not be done
	 * and an exception will be thrown. The caller will be responsible on how to handle this.
	 *
	 * @param string[] $engines An array includine mapping of engine id to engine class name.
	 *                          The key of the array elements is the identifier and the value
	 *                          is the class name (fully qualified).
	 *
	 * @throws \Exception        If an engine used to build the backup can not be found in the $engines list.
	 * @throws Restore_Exception If the restore operation had failed.
	 *
	 * @since 1.0.0
	 */
	public function restore( array $engines ) {

		$missing_engines = $this->missing_engines( ...array_keys( $engines ) );
		if ( count( $missing_engines ) !== 0 ) {
			throw new \RuntimeException( 'Missing backup engines: ' . implode( ', ', $missing_engines ) );
		}

		// A hack to make sure all engine class are loaded into memory to avoid
		// a situation were an engine class might be replace by former iteration of the code
		// during the restore.
		foreach ( $this->engines as $engine_id => $engine_class ) {
			$engine_class::identifier();
		}

		foreach ( $this->engines as $engine_id => $engine_class ) {
			$engine_class::restore( $this->engines[ $engine_id ], $this->storage );
		}
	}

	/**
	 * Section identities referenced by the backup.
	 *
	 * @since 1.0.0
	 *
	 * @return Backup_Section_Identity[] Referenced section identities.
	 */
	public function section_identities(): array {
		$identities = array();
		foreach ( $this->engines as $engine_sections ) {
			foreach ( $engine_sections as $identity ) {
				$identities[] = $identity;
			}
		}

		return $identities;
	}

	/**
	 * Sections referenced by the backup.
	 *
	 * @since 1.0.0
	 *
	 * @return iterable<Backup_Section> Referenced sections.
	 *
	 * @throws \RuntimeException If a referenced section does not exist in the storage.
	 */
	public function sections(): iterable {
		foreach ( $this->engines as $engine_sections ) {
			foreach ( $engine_sections as $identity ) {
				$section = $this->storage->section( $identity );
				if ( null === $section ) {
					throw new \RuntimeException( 'A section referenced by the backup does not exist in the storage.' );
				}
				yield $section;
			}
		}
	}

	/**
	 * Sections referenced by one backup engine.
	 *
	 * @since 1.0.0
	 *
	 * @param string $engine_id The backup engine identifier.
	 *
	 * @return Backup_Section[] Referenced sections.
	 *
	 * @throws \RuntimeException If the engine or a referenced section does not exist.
	 */
	public function engine_sections( string $engine_id ): array {
		if ( ! isset( $this->engines[ $engine_id ] ) ) {
			throw new \RuntimeException( 'The backup engine does not exist in this backup.' );
		}
		$sections = array();
		foreach ( $this->engines[ $engine_id ] as $identity ) {
			$section = $this->storage->section( $identity );
			if ( null === $section ) {
				throw new \RuntimeException( 'A section referenced by the backup does not exist in the storage.' );
			}
			$sections[] = $section;
		}

		return $sections;
	}

}
