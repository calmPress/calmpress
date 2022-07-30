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
	protected Backup_Storage $storage;

	/**
	 * The unix time in which the backup was created.
	 *
	 * @var int
	 *
	 * @since 1.0.0
	 */
	protected int $time;

	/**
	 * A unique identifier for the backup.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected string $unique_id;

	/**
	 * The backup's description.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected string $description;

	/**
	 * The engines which were used when creating the backup the data associate with them.
	 * 
	 * @var string[]
	 *
	 * @since 1.0.0
	 */
	protected array $engines;

	/**
	 * Constructor of a backup object.
	 *
	 * Create an object based on a meta information about the backup.
	 *
	 * @since 1.0.0
	 *
	 * @param string         $json_data The "meta" data about the backup in a json format.
	 * @param Backup_Storage $storage   The storage on which the backup resides.
	 *
	 * @throws \Exception If the $json_data is malformed json or do not contain all the info
	 *                    in the expected format.
	 */
	public function __construct( string $json_data, Backup_Storage $storage ) {
		$this->storage = $storage;

		$data = json_decode( $json_data, true );
		if ( null === $data ) {
			throw new \Exception( 'Not a valid json format: ' . $meta_file );
		}

		if ( ! isset( $data[ 'description' ] ) ) {
			throw new \Exception( sprintf( 'The "description" field is missing from the meta file %s', $meta_file ) );
		}

		$description = $data[ 'description' ];
		if ( ! is_scalar( $description ) ) {
			throw new \Exception( sprintf( 'The field "description" is not parseable as string in the meta file %s', $meta_file ) );
		}
		$this->description = (string) $description;

		if ( ! isset( $data[ 'unique_id' ] ) ) {
			throw new \Exception( sprintf( 'The "description" field is missing from the meta file %s', $meta_file ) );
		}

		$unique_id = $data[ 'unique_id' ];
		if ( ! is_scalar( $unique_id ) ) {
			throw new \Exception( sprintf( 'The field "unique_id" is not parseable as string in the meta file %s', $meta_file ) );
		}
		$this->unique_id = (string) $unique_id;

		if ( ! isset( $data[ 'time' ] ) ) {
			throw new \Exception( sprintf( 'The "time" field is missing from the meta file %s', $meta_file ) );
		}

		$time = filter_var( $data[ 'time' ], FILTER_VALIDATE_INT );
		if ( false === $time ) {
			throw new \Exception( sprintf( 'The field "time" is not an integer in the meta file %s', $meta_file ) );
		}
		$this->time = $time;

		if ( ! isset( $data[ 'engines' ] ) ) {
			throw new \Exception( sprintf( 'The "engines" field is missing from the meta file %s', $meta_file ) );
		}
		if ( ! is_array( $data[ 'engines' ] ) ) {
			throw new \Exception( sprintf( 'The "engines" field is not an array in the meta file %s', $meta_file ) );
		}

		$this->engines = $data[ 'engines' ];
	}

	/**
	 * A unique identifier for the backup.
	 *
	 * @since 1.0.0
	 *
	 * @return string The id.
	 */
	public function identifier() : string {
		return $this->unique_id;
	}

	/**
	 * The server unix time in which the backup was created.
	 *
	 * @since 1.0.0
	 *
	 * @return int The unix time.
	 */
	public function time_created() : int {
		return $this->time;
	}

	/**
	 * The human readable description of the backup.
	 *
	 * @since 1.0.0
	 *
	 * @return string The description.
	 */
	public function description() : string {
		return $this->description;
	}

	/**
	 * Create a new backup meta json string based on the description and engines data.
	 *
	 * The backup creation time and unique id are automattically generated.
	 *
	 * @param string $description  The backup's description.
	 * @param array  $engines_data An array containing data of the engines used in the creation
	 *                             of the backup and relevant associated data. The array keys are
	 *                             the identifiers of the engines.
	 *
	 * @return string json string that can be used to reconstruct the backup's information.
	 *
	 * @since 1.0.0
	 */
	public static function new_backup_meta( string $description, array $engines_data ): string {
		$o = new \stdClass();
		$o->description = $description;
		$o->time        = current_time( 'U', true );
		$o->unique_id   = uniqid();
		$o->engines     = $engines_data;

		return json_encode( $o );
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

		$missing_engines = $this->missing_engines( array_keys( $engines ) );
		if ( count( $missing_engines ) !== 0 ) {
			// It is probably better to call missing_engines() instead of relying on this exception.
			throw new \Exception( 'Some engine are missings: ', join( ', ', $missing_engines ) );
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
	 * The data about the engines and their data which were used to create the backup.
	 * 
	 * The array index is the engine identifier, and the value is the actual data.
	 *
	 * @return array
	 */
	public function engines_data(): array {
		return $this->engines;
	}

	/**
	 * The engines which were used in creating the backup.
	 *
	 * @return string[]
	 */
	public function engines(): array {
		return array_keys( $this->engines );
	}
}