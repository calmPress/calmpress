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
	protected string $meta_file;

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
	 * The backup's description.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected string $description;

	/**
	 * The directory in which the mu_plugin backup resides.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected string $mu_plugins_directory;

	/**
	 * The directory in which the languages backup resides.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected string $languages_directory;

	/**
	 * The directory in which the dropins backup resides.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected string $dropins_directory;

	/**
	 * The directory in which the root directory backup resides.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected string $root_directory;

	/**
	 * The directory in which the options table backup resides.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected string $options_db_directory;

	/**
	 * Data related to the backedup themes. An array where key is theme's directory name.
	 * 
	 * @var Struct_Versioned_Data[]
	 *
	 * @since 1.0.0
	 */
	protected array $themes;

	/**
	 * Data related to the backedup plugin. An array where key is plugin's directory or file name.
	 * 
	 * @var Struct_Plugin_Data[]
	 *
	 * @since 1.0.0
	 */
	protected array $plugins;

	/**
	 * Get the path of a directory for a backup directory based
	 * on the expected structure of the backup meta json file.
	 *
	 * Main functionality is to validate the data structure and existance of the directory.
	 *
	 * @param mixed  $data The array which should contain a "directory" element.
	 * @param string $root_dir The full path to the directory where the meta file is.
	 *
	 * @return string The full path to the directory.
	 *
	 * @throws \Exception If $data is not an array, if it does not have a "directory" elsement, if it is
	 *                    not a string or if the indicated directory do not actually exist. 
	 */
	private function directory_info_for_field( $data, string $root_dir ): string {
		if ( ! is_array( $data ) ) {
			throw new \Exception( 'not an array' );
		}
		
		if ( ! isset( $data['directory'] ) ) {
			throw new \Exception( 'do not have directory item' );
		}

		if ( ! is_scalar( $data['directory'] ) ) {
			throw new \Exception( 'directory is not a string' );
		}

		$path = $root_dir . '/' . $data['directory'];
		if ( ! @is_dir( $path ) ) {
			throw new \Exception( 'directory do not exists' );
		}

		return $path;
	}

	/**
	 * Get the path of a directory for a backup directory based
	 * on the expected structure of the backup meta json file.
	 *
	 * Main functionality is to validate the data structure and existance of the directory.
	 *
	 * @param mixed $data The array which should contain a "directory" element.
	 * @param string $root_dir The full path to the directory where the meta file is.
	 *
	 * @return string The full path to the directory.
	 *
	 * @throws \Exception If $data is not an array, if it does not have a "directory" elsement, if it is
	 *                    not a string or if the indicated directory do not actually exist. 
	 */
	private function versioned_data_for_field( $data, string $root_dir ): Struct_Versioned_Data {
		$directory = $this->directory_info_for_field( $data, $root_dir );
		
		if ( ! isset( $data['version'] ) ) {
			throw new \Exception( 'version not found' );
		}

		if ( ! is_scalar( $data['version'] ) ) {
			throw new \Exception( 'version not a string' );
		}

		return new Struct_Versioned_Data( $data['version'], $directory );
	}

	/**
	 * Get the path of a directory for a backup directory based
	 * on the expected structure of the backup meta json file.
	 *
	 * Main functionality is to validate the data structure and existance of the directory.
	 *
	 * @param mixed $data The array which should contain a "directory" element.
	 * @param string $root_dir The full path to the directory where the meta file is.
	 *
	 * @return string The full path to the directory.
	 *
	 * @throws \Exception If $data is not an array, if it does not have a "directory" elsement, if it is
	 *                    not a string or if the indicated directory do not actually exist. 
	 */
	private function plugin_data_for_field( $data, string $root_dir ): Struct_Plugin_Data {
		$directory = $this->directory_info_for_field( $data, $root_dir );
		
		if ( ! isset( $data['version'] ) ) {
			throw new \Exception( 'version not found' );
		}

		if ( ! is_scalar( $data['version'] ) ) {
			throw new \Exception( 'version not a string' );
		}

		if ( ! isset( $data['type'] ) ) {
			throw new \Exception( 'type not found' );
		}

		if ( 'root_file' !== $data['type'] && 'directory' !== $data['type'] ) {
			throw new \Exception( 'type not an expected value' );
		}

		return new Struct_Plugin_Data( $data['version'], $directory, 'root_file' === $data['type'] );
	}

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
	 * @throws \Exception If meta file is not readable or the content do not contain all the info
	 *                   in the expected format.
	 */
	public function __construct( string $meta_file, Backup_Storage $storage ) {
		$this->storage   = $storage;
		$this->meta_file = $meta_file;

		$json = file_get_contents( $meta_file );
		if ( false === $json ) {
			throw new \Exception( 'Meta file not readable: ' . $meta_file );
		}

		$data = json_decode( $json, true );
		if ( null === $data ) {
			throw new \Exception( 'Not a valid json format: ' . $meta_file );
		}

		$description = $data[ 'description' ];
		if ( ! is_scalar( $description ) ) {
			throw new Exception( sprintf( 'The field "description" is not parseable as string in the meta file %s', $meta_file ) );
		}
		$this->description = (string) $description;

		$time = filter_var( $data[ 'time' ], FILTER_VALIDATE_INT );
		if ( false === $time ) {
			throw new Exception( sprintf( 'The field "time" is not an integer in the meta file %s', $meta_file ) );
		}
		$this->time = $time;

		$this->mu_plugins_directory = $this->directory_info_for_field( $data['mu_plugins'], dirname( $meta_file) );
		$this->languages_directory  = $this->directory_info_for_field( $data['languages'], dirname( $meta_file) );
		$this->dropins_directory    = $this->directory_info_for_field( $data['dropins'], dirname( $meta_file) );
		$this->root_directory       = $this->directory_info_for_field( $data['root_directory'], dirname( $meta_file) );
		$this->options_db_directory = $this->directory_info_for_field( $data['options'], dirname( $meta_file) );
		$this->core_data            = $this->versioned_data_for_field( $data['core'], dirname( $meta_file) );

		$this->themes=[];
		if ( ! is_array( $data['themes'] ) ) {
			throw new \Exception( sprintf( '"themes" is not an array in the meta file %s', $meta_file ) );
		}

		foreach ( $data['themes'] as $name => $theme_data ) {
			$this->themes['directory'] = $this->versioned_data_for_field( $theme_data, dirname( $meta_file) );
		}

		$this->plugins=[];
		if ( ! is_array( $data['plugins'] ) ) {
			throw new \Exception( sprintf( '"plugins" is not an array in the meta file %s', $meta_file ) );
		}

		foreach ( $data['plugins'] as $name => $plugin_data ) {
			$this->plugins['plugins'] = $this->plugin_data_for_field( $plugin_data, dirname( $meta_file) );
		}
	}

	/**
	 * Get the path to the file containing the meta information in a json format.
	 */
	public function backup_meta_file_path() : string {
		return $this->meta_file;
	}

	/**
	 * Throw a timeout exception if the current time is later than the parameter.
	 *
	 * @param int $time_to_check The unix time to compare against.
	 *
	 * @since 1.0.0
	 *
	 * @throws \calmpress\calmpress\Timeout_Exception if current time is later than $time_to_check.
	 */
	static function throw_if_out_of_time( int $time_to_check ) {
		if ( time() > $time_to_check ) {
			throw new \calmpress\calmpress\Timeout_Exception();
		}
	}

	/**
	 * A utility function to create a directory and raise exceptions if it fails.
	 * 
	 * Directory creation is recursive if needed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $directory Path to the directory to create.
	 *
	 * @throws \Exception When creation fails.
	 */
	protected static function mkdir( string $directory ) {
		if ( ! @mkdir( $directory, 0755, true ) ) {
			throw new \Exception( 'Failed creating a backup directory ' . $directory . '. Cause: ' . \calmpress\utils\last_error_message() );
		}
	}

	/**
	 * A utility function to copy files and raise exceptions if it fails.
	 *
	 * @since 1.0.0
	 *
	 * @param string $source      Path to the file to copy.
	 * @param string $destination Path to the copy destination.
	 *
	 * @throws \Exception When copy fails.
	 */
	protected static function copy( string $source, string $destination ) {
		if ( ! @copy( $source, $destination ) ) {
			throw new \Exception( sprintf( 'Copy from %s to %s had failed. Cause: %s', $source, $destination, \calmpress\utils\last_error_message() ) );
		}
	}

	/**
	 * Backup recursively a directory from source directory into destination directory.
	 * 
	 * The destination directory is created if do not exist.
	 * symlinks are not copied.
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $source      Full path of the source directory, the directory should exist.
	 * @param string $destination Full path of the destination directory.
	 * 
	 * @throws Exception When directory could not be created or file could not be copied.
	 */
	protected static function Backup_Directory( string $source, string $destination ) {

		if ( ! file_exists( $destination ) ) {
			static::mkdir( $destination );
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				$dir = $destination . '/' . $iterator->getSubPathName();
				static::mkdir( $dir );
			} elseif ( ! $item->isLink() ) { 
				// Symlinks might point anywhere, no clear backup strategy for them so handle only files.
				$file = $destination . '/' . $iterator->getSubPathName();
				static::copy( $item->getPathname(), $file );
			}
		}
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
	 * @return int The description.
	 */
	public function description() : string {
		return $this->description;
	}

	/**
	 * The storage in which the backup is located.
	 *
	 * @since 1.0.0
	 *
	 * @return Backup_Storage The storage.
	 */
	public function storage() : Backup_Storage {
		return $this->storage;
	}

	/**
	 * The data about the engines and their data which were used to create the backup.
	 * 
	 * The array index is the engine identifier, and the value is the actual data.
	 *
	 * @return array
	 */
	public function engines_data(): array {
		return [];
	}

	/**
	 * Restore the backup.
	 *
	 * @since 1.0.0
	 */
	public function restore( restore_engines $engines ) {

	}
}