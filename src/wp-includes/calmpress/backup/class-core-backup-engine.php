<?php
/**
 * Implementation of a backup/restore engine for core data and files.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

// This is need for accessing get_plugins API.
require_once ABSPATH . 'wp-admin/includes/plugin.php';

/**
 * A backup/restore engine for the core parts.
 *
 * @since 1.0.0
 */
class Core_Backup_Engine implements Engine_Specific_Backup {

	/**
	 * The directory in which core backup files are located relative to the
	 * backup root directory.
	 */
	const RELATIVE_CORE_BACKUP_PATH = 'core/';

	/**
	 * The directory in which themes' backup directories are located relative to the
	 * backup root directory.
	 */
	const RELATIVE_THEMES_BACKUP_PATH = 'themes/';

	/**
	 * The directory in which plugins' backup directories are located relative to the
	 * backup root directory.
	 */
	const RELATIVE_PLUGINS_BACKUP_PATH = 'plugins/';

	/**
	 * The directory in which mu-plugin backup directory is located relative to the
	 * backup root directory.
	 */
	const RELATIVE_MU_PLUGINS_BACKUP_PATH = 'mu-plugins/';

	/**
	 * The directory in which languages backup directory is located relative to the
	 * backup root directory.
	 */
	const RELATIVE_LANGUAGES_BACKUP_PATH = 'languages/';

	/**
	 * The directory in which dropins backup files are located relative to backup root.
	 */
	const RELATIVE_DROPINS_BACKUP_PATH = 'dropins/';

	/**
	 * The directory in which root directory backup files are located relative to backup root.
	 */
	const RELATIVE_ROOTDIR_BACKUP_PATH = 'root_directory/';

	/**
	 * The directory in which the backup file with option table data is located.
	 */
	const RELATIVE_OPTIONS_BACKUP_PATH = 'db/options/';

	/**
	 * The storage engine used to hold (save to) the backup.
	 *
	 * @var Backup_Storage
	 *
	 * @since 1.0.0
	 */
	protected Backup_Storage $storage;

	/**
	 * The versions of calmpress core.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected string $version = '';

	/**
	 * The directory in which the mu_plugin backup resides.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected string $mu_plugins_directory = '';

	/**
	 * The directory in which the languages backup resides.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected string $languages_directory = '';

	/**
	 * The directory in which the dropins backup resides.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected string $dropins_directory = '';

	/**
	 * The directory in which the root directory backup resides.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected string $root_directory = '';

	/**
	 * The directory in which the options table backup resides.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected string $options_db_directory = '';

	/**
	 * Data related to the backedup themes. An array where key is theme's directory name.
	 * 
	 * @var Struct_Versioned_Data[]
	 *
	 * @since 1.0.0
	 */
	protected array $themes = [];

	/**
	 * Data related to the backedup plugin. An array where key is plugin's directory or file name.
	 * 
	 * @var Struct_Plugin_Data[]
	 *
	 * @since 1.0.0
	 */
	protected array $plugins = [];

	/**
	 * Get the relative path of a directory for a backup directory.
	 *
	 * Main functionality is to validate the data structure and existance of the directory.
	 *
	 * @param mixed  $data The array which should contain a "directory" element.
	 * @param string $root_dir The full path to the root directory of the backup.
	 *
	 * @return string The full path to the directory.
	 *
	 * @throws \Exception If $data is not an array, if it does not have a "directory" element, if it is
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

		return $data['directory'];
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
	 * Constructor of the core specific backup.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage The storage of which the backup (should) resides.
	 * @param array          $data    The backup data. An empty array indicates that there is no actual backup.
	 */
	public function __construct( Backup_Storage $storage, array $data=[] ) {

		$this->storage = $storage;

		if ( ! empty( $data ) ) {
			$this->mu_plugins_directory = $this->directory_info_for_field( $data['mu_plugins'], $root_dir );
			$this->languages_directory  = $this->directory_info_for_field( $data['languages'], $root_dir );
			$this->dropins_directory    = $this->directory_info_for_field( $data['dropins'], $root_dir );
			$this->root_directory       = $this->directory_info_for_field( $data['root_directory'], $root_dir );
			$this->options_db_directory = $this->directory_info_for_field( $data['options'], $root_dir );
			$this->core_data            = $this->versioned_data_for_field( $data['core'], $root_dir );

			$this->themes=[];
			if ( ! is_array( $data['themes'] ) ) {
				throw new \Exception( '"themes" is not an array' );
			}

			foreach ( $data['themes'] as $name => $theme_data ) {
				$this->themes[ $name ] = $this->versioned_data_for_field( $theme_data, $root_dir );
			}

			$this->plugins=[];
			if ( ! is_array( $data['plugins'] ) ) {
				throw new \Exception( '"plugins" is not an array' );
			}

			foreach ( $data['plugins'] as $name => $plugin_data ) {
				$this->plugins['plugins'] = $this->plugin_data_for_field( $plugin_data, $root_dir );
			}
		}
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
	 * Backup recursively a directory from source directory into destination staging storage.
	 * 
	 * Symlinks are not copied as they might point anywhere and there is no clear backup and restore
	 * strategy to handle all cases.
	 * 
	 * @since 1.0.0
	 * 
	 * @param string                   $source      Full path of the source directory, the directory should exist.
	 * @param Temporary_Backup_Storage $staging     The staging storage for the files.
	 * @param string                   $destination A path relative to the staging root in which files will be stored.
	 * 
	 * @throws Exception When directory could not be created or file could not be copied.
	 */
	protected static function Backup_Directory( string $source, Temporary_Backup_Storage $staging, string $destination ) {

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				// skip as storage is responsible to create directories as needed.
				;
			} elseif ( $item->isLink() ) {
				// Skip, not handling symlinks.
				;
			} else {
				$file = $destination . '/' . $iterator->getSubPathName();
				$staging->copy_file( $item->getPathname(), $file );
			}
		}
	}

	/**
	 * Backup the core files (wp-admin, wp-include and some core file or root directory).
	 *
	 * If a backup for the current version already exists, just return the directory in which it located,
	 * otherwise create a new directory under the core backups root, and copy into it all files from
	 * wp-include, wp-admin and core files on root, while preserving the relative directory structure.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage The storage to backup to.
	 *
	 * @return string The directory at which the core backup resides in the storage.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Core( Backup_Storage $storage ) : string {
		$core_dir   = static::RELATIVE_CORE_BACKUP_PATH;
		$version    = calmpress_version();
		$backup_dir = $core_dir . $version;

		/*
		 * If the backup directory exists it means we already have a backup of the version,
		 * If not we need to create a directory and copy into it the core files.
		 */
		if ( ! $storage->section_exists( $backup_dir ) ) {

			$staging = $storage->section_working_area_storage( $backup_dir );

			// Copy wp-includes
			static::Backup_Directory( static::installation_paths()->wp_includes_directory(), $staging, 'wp-includes' );


			// Copy wp-admin.
			static::Backup_Directory( static::installation_paths()->wp_admin_directory(), $staging, 'wp-admin' );

			// Copy core code files located at root directory.
			foreach ( static::installation_paths()->core_root_file_names() as $file ) {
				$staging->copy_file( static::installation_paths()->root_directory() . $file, $file );
			}

			$staging->store();
		}

		return $backup_dir;
	}

	/**
	 * Backup the mu-plugins directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $source     The full path to mu-plugins directory which might not exist.
	 * @param string $backup_dir The directory to backup to.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_MU_Plugins( string $source, string $backup_dir ) {

		// If the mu-plugins directory does not exist there is nothing to backup and
		// Backup_Directory requires an existing directory.
		if ( is_dir( $source ) ) {
			static::Backup_Directory( $source, $backup_dir );
		}
	}

	/**
	 * Backup the languages directory.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage The storage to use for the root directory files.
	 * @param string $source          The full path to languages directory which might not exist.
	 * @param string $backup_dir      The directory to backup to.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Languages(  Backup_Storage $storage, string $source, string $backup_dir ) {

		// If the languages directory does not exist there is nothing to backup and
		// Backup_Directory requires an existing directory.
		if ( is_dir( $source ) ) {
			$staging = $storage->section_working_area_storage( $backup_dir );
			static::Backup_Directory( $source, $staging, '' );
			$staging->store();
		}
	}

	/**
	 * Backup the dropins plugins files.
	 *
	 * Dropins are located at the root of the wp_content directory. as they don't contain
	 * version information just backup all of them together.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage The storage to use for the root directory files.
	 * @param string $source_dir      The directory of the dropins files.
	 * @param string $backup_dir      The root directory for the dropins backup files.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Dropins( Backup_Storage $storage, string $source_dir, string $backup_dir ) {

		$staging    = $storage->section_working_area_storage( $backup_dir );
		foreach ( static::installation_paths()->dropin_files_name() as $filename ) {
			$file = $source_dir . $filename;
			if ( file_exists( $file ) ) {
				if ( ! is_link( $file ) ) {
					$staging->copy_file( $file, $filename );
				}
			}
		}

		$staging->store();
	}

	/**
	 * Backup the files on the root dir.
	 *
	 * The root directory can contain all kind of files that might not be code but are needed
	 * for the proper functioning of the site.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage    The storage to use for the root directory files.
	 * @param string         $backup_dir The root directory for the root backup files.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Root( $storage, string $backup_dir ) {

		$staging    = $storage->section_working_area_storage( $backup_dir );
		$core_files = static::installation_paths()->core_root_file_names();

		foreach ( new \DirectoryIterator( static::installation_paths()->root_directory() ) as $file ) {
			if ( $file->isDot() || $file->isLink() || ! $file->isFile() ) {
				continue;
			}

			// No need to backup core files.
			if ( in_array( $file->getFilename(), $core_files, true ) ) {
				continue;
			}
			$staging->copy_file( $file->getPathname(), $file->getFilename() );
		}

		$staging->store();
	}

	/**
	 * Backup the theme files.
	 *
	 * If a backup for the current version already exists, just return the directory in which it located,
	 * otherwise create a new directory under the theme backups root / theme directory, and copy into it all
	 * files from the theme's directory, while preserving the relative directory structure.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage           The storage to use for the themes backup directories.
	 * @param string         $themes_backup_dir The relative directory for the themes backup directories.
	 * @param \WP_Theme      $theme The object representing the theme properties.
	 *
	 * @return string The path to the backup directory relative to the backup
	 *                root directory.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Theme( Backup_Storage $storage, string $themes_backup_dir, \WP_Theme $theme ) : string {
		$version      = $theme->get( 'Version' );
		$source       = $theme->get_stylesheet_directory();
		$theme_dir    = $themes_backup_dir . '/' . basename( $source ) . '/' . $version;

		/*
		 * If the backup directory exists it means we already have a backup of the version,
		 * If not we need to create a directory and copy into it the theme files.
		 */
		if ( ! $storage->section_exists( $theme_dir ) ) {

			$staging = $storage->section_working_area_storage( $theme_dir );

			// Copy the theme files to the root of the staging area.
			static::Backup_Directory( $source, $staging, '' );
			$staging->store();
		}

		return basename( $source ) . '/' . $version;
	}

	/**
	 * Backup the theme files.
	 *
	 * If a backup for the current version already exists, just return the directory in which it located,
	 * otherwise create a new directory under the theme backups root / theme directory, and copy into it all
	 * files from the theme's directory, while preserving the relative directory structure.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $themes_backup_dir The storage to use.
	 * @param int            $max_end_time      The last second in which a theme backup can start.
	 *
	 * @return array Array of arrays containing meta information about the themes' backups
	 *               Each sub array is indexed by the relevant theme's directory and has the
	 *               following values:
	 *               'version'   The version of the backuped theme.
	 *               'directory' The directory in which the backup is located relative to the root
	 *                           of the backup directory.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Themes( Backup_Storage $storage, int $max_end_time ) : array {
		global $wp_theme_directories;
		$directory_change = false;

		// Switch the current content of the registers theme root directories with
		// the directory we expect from the paths object.
		// This is done to be able to test the function while not needing to reinvent
		// wp_get_themes.
		$old_theme_directories = $wp_theme_directories;
		$wp_theme_directories  = rtrim( static::installation_paths()->themes_directory(), '/' );

		// Need to clear the theme cache if directories actually changed.
		if ( $old_theme_directories !== $wp_theme_directories ) {
			wp_clean_themes_cache();
			$directory_change = true;
		}

		$themes = wp_get_themes();
		$meta   = []; 
		foreach ( $themes as $theme ) {

			static::throw_if_out_of_time( $max_end_time );
				
			// skip themes without a version.
			if ( $theme->get( 'Version' ) ) {
				$theme_dir = static::Backup_Theme( $storage, static::RELATIVE_THEMES_BACKUP_PATH, $theme );
				$meta[ $theme->get_stylesheet() ] = [
					'version'   => $theme->get( 'Version' ),
					'directory' => static::RELATIVE_THEMES_BACKUP_PATH . $theme_dir . '/',
				];
			}
		}

		// Return the theme directories global to its original state.
		$wp_theme_directories = $old_theme_directories;
		// Need to clear the theme cache if directories actually changed.
		if ( $directory_change ) {
			wp_clean_themes_cache();
		}
		

		return $meta;
	}

	 /**
	 * Backup the plugin files.
	 *
	 * If a backup for the current version already exists, just return the directory in which it located,
	 * otherwise create a new directory under the plugin backups root / plugin directory, and copy into it all
	 * files from the plugin's directory, while preserving the relative directory structure.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage            The backup storage.
	 * @param string         $plugins_backup_dir The root directory for the plugins backup files.
	 * @param string         $source             The directory in which the plugin is located.
	 * @param string         $version            The version to associate with the directory.
	 *
	 * @return string The path to the backup directory relative to the backup
	 *                root directory.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Plugin_Directory( Backup_Storage $storage, string $plugins_backup_dir, string $source, string $version ) : string {
		$relative_dir = basename( $source ) . '/' . $version;
		$backup_dir   = $plugins_backup_dir . $relative_dir;

		/*
		 * If the backup directory exists it means we already have a backup of the version,
		 * If not we need to create a directory and copy into it the plugin files.
		 */
		if ( ! $storage->section_exists( $backup_dir ) ) {

			$staging = $storage->section_working_area_storage( $backup_dir );

			// Copy the directory files.
			static::Backup_Directory( $source, $staging, '' );
			$staging->store();
		}

		return $relative_dir;
	}

	 /**
	 * Backup a plugin which is a single file plugin on plugins root directory.
	 *
	 * If a backup for the current version already exists, just return the directory in which it located,
	 * otherwise create a new directory under the plugin backups root / plugin directory, and copy into it all
	 * files from the plugin's directory, while preserving the relative directory structure.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage            The backup storage.
	 * @param string         $plugins_backup_dir The root directory for the plugins backup files.
	 * @param string         $source             The file to backup.
	 * @param string         $version            The version of the plugin.
	 *
	 * @return string The path to the backup directory relative to the backup
	 *                root directory.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Root_Single_File_Plugin( Backup_Storage $storage, string $plugins_backup_dir, string $source, string $version ) : string {
		$relative_dir = $plugins_backup_dir . basename( $source ) . '/' . $version;

		/*
		 * If the backup section exists it means we already have a backup of the version,
		 * If not we need to create it and copy into it the plugin files.
		 */
		if ( ! $storage->section_exists( $relative_dir ) ) {

			$staging = $storage->section_working_area_storage( $relative_dir );

			// Copy the file to the staging area.
			$staging->copy_file( $source, basename( $source ) );

			$staging.store();
		}

		return basename( $source ) . '/' . $version;
	}

	 /**
	 * Backup the plugin files and directories.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage      The storage of the backup.
	 * @param int            $max_end_time The last second in which a plugin backup can start.
	 *
	 * @return array Array of arrays containing meta information about the plugins' backups
	 *               Each sub array is indexed by the relevant plugin's directory and has the
	 *               following values:
	 *               'version'   The version of the backuped theme.
	 *               'directory' The directory in which the backup is located relative to the root
	 *                           of the backup directory.
	 *               'type'      The type of the plugin backedup, 'root_file' for plugins
	 *                           located as single files on the plugin root directory or 'directory'
	 *                           for plugins located in a directory.
	 * 
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Plugins( Backup_Storage $storage, int $max_end_time ) : array {

		// Plugins are in principal just a file with a plugin header and you can have
		// a one file plugin in the plugins directory, or two plugin files in one directory
		// in addition to the standard format of one file in its own directory,
		// therefore copying directorie like it is done in themes is just not enough if version
		// information is needed.

		$plugindirs = [];

		foreach ( \get_plugins() as $filename => $plugin_data ) {
			$plugin_data['filename']              = $filename; // we need this later.

			// Skip plugins without versions.
			if ( ! empty( $plugin_data['Version'] ) ) {
				$plugindirs[ dirname( $filename ) ][] = $plugin_data;
			}
		}

		$meta = [];

		// Special case are plugins locate at the plugins root directory.
		if ( isset( $plugindirs['.'] ) ) {
			foreach ( $plugindirs['.'] as $plugin_data ) {
				$plugin_dir = static::Backup_Root_Single_File_Plugin(
					$storage,
					static::RELATIVE_PLUGINS_BACKUP_PATH,
					WP_PLUGIN_DIR . '/' . $plugin_data['filename'],
					$plugin_data['Version']
				);

				$meta[ $plugin_data['filename'] ] = [
					'version'   => $plugin_data['Version'],
					'directory' => static::RELATIVE_PLUGINS_BACKUP_PATH . $plugin_dir,
					'type'      => 'root_file',
				];
			}
			unset( $plugindirs['.'] );
		}

		foreach ( $plugindirs as $dirname => $dir_data ) {
			static::throw_if_out_of_time( $max_end_time );

			// For directories with two plugins make the version a combination
			// of the versions of both plugins.
			// The version generation code relies on get_plugins and the code processing the data
			// to generate the plugin data in consistant order otherwise there might be more than
			// one backup for the same identical versions for multiple plugins in a directory.
			$versions = [];
			foreach ( $dir_data as $plugin_data ) {
				$versions[] = $plugin_data['Version'];
			}
			$version = join( '-', $versions );
			
			$plugin_dir = static::Backup_Plugin_Directory(
				$storage,
				static::RELATIVE_PLUGINS_BACKUP_PATH,
				WP_PLUGIN_DIR . '/' . $dirname,
				$version
			);

			$meta[ $dirname ] = [
				'version'   => $version,
				'directory' => static::RELATIVE_PLUGINS_BACKUP_PATH . $plugin_dir .'/',
				'type'      => 'directory',
			];
		}

		return $meta;
	}

	/**
	 * Backup the options to a json file for a the current site.
	 *
	 * The backup ignores transients, widgets and user roles options.
	 *
	 * @since 1.0.0
	 *
	 * @param Temporary_Backup_Storage $staging The staging storage for the files.
	 * @param int                      $site_id The id of the specific site being backed up.
	 *
	 * @throws \Exception When file creation error occurs.
	 */
	protected static function Backup_Site_Options( Temporary_Backup_Storage $staging, int $site_id ) {
		global $wpdb;

		if ( is_multisite() ) {
			\switch_to_blog( $site_id );
		}
		$options = $wpdb->get_results( "SELECT option_name, option_value, autoload FROM $wpdb->options WHERE option_name NOT LIKE '_%transient_%'" );
		if ( is_multisite() ) {
			\restore_current_blog();
		}

		// Remove widgets, sidebar and role capabilities.
		$options = array_filter(
			$options,
			function ( $option ) : bool {
				$option_name = $option->option_name;
				if ( 'sidebars_widgets' === $option_name ) {
					return false;
				}
				if ( 0 === strncmp( $option_name, 'widget_', 7 ) ) {
					return false;
				}
				if ( 'user_roles' === substr( $option_name, -10 ) ) {
					return false;
				}
				return true;
			}
		);

		// reduce verbosness of the generated json
		$options = array_map(
			function ( $option ) {
				return [
					'n' => $option->option_name,
					'v' => $option->option_value,
					'a' => $option->autoload,
				];
			},
			$options
		);

		$json = json_encode( $options );
		$file = $backup_dir . $site_id . '-options.json';
		if ( false === $staging->file_put_contents( $file, $json ) ) {
			throw new \Exception( 'Failed writing to ' . $file );
		}
	}

	/**
	 * Backup the options to a json file. In case it is a multisite there would be
	 * a file per site.
	 *
	 * The backup ignores transients, widgets and user roles options.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage    The storage of the backup.
	 * @param string         $backup_dir The directory for the options backup files.
	 *
	 * @throws \Exception When directory creation or file creation error occurs.
	 */
	protected static function Backup_Options( Backup_Storage $storage, string $backup_dir ) {
		global $wpdb;

		$staging = $storage->section_working_area_storage( $backup_dir );

		// loop over all sites, store options for each site in different file.
		if ( is_multisite() ) {
			foreach ( \get_sites() as $site ) {
				static::Backup_Site_Options( $staging, $site->blog_id );
			}
		} else {
			static::Backup_Site_Options( $staging, \get_current_blog_id() );
		}

		$staging->store();
	}

	/**
	 * Utility function to provide access to the information about the paths in which
	 * the calmPress being backuped is installed.
	 * 
	 * Helps to avoid dependency on global state to make testing easier while reducing
	 * the amount of parameters that would have needed to be passed around.
	 * 
	 * @return \calmpress\calpress\Paths An object with various path related information.
	 */
	protected static function installation_paths() : \calmpress\calmpress\Paths {
		static $cache;

		if ( ! isset ( $cache ) ) {
			$cache = new \calmpress\calmpress\Paths();
		}
		return $cache;
	}

	/**
	 * Create a backup.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $backup_root The storage to which to write files.
	 * @param int            $max_time    The maximum amount of time in second th backup
	 *                                    should last before terminating.
	 *                                    In practice the amount of time after which no new atomic
	 *                                    type of backup should start.
	 *
	 * @return array An unstructured data that the engine need for restoring the backup.
	 *
	 * @throws \Exception if the backup creation fails.
	 * @throws Timeout_Exception If the backup timeed out and need more "time slices" to complete.
	 */
	public static function backup( Backup_Storage $storage, int $max_time ): array {
		$max_end_time = time() + $max_time; // After this time backup process should end, completed or not.

		$meta['version'] = calmpress_version();
		static::Backup_Core( $storage, static::RELATIVE_CORE_BACKUP_PATH );
		static::throw_if_out_of_time( $max_end_time );

		// Backup all themes that are in standard theme location, which can be activated (no errors).
		// Ignore everything else in the themes directories.
		$meta['themes'] = static::Backup_Themes( $storage, $max_end_time );
		static::throw_if_out_of_time( $max_end_time );
		
		$meta['plugins'] = static::Backup_Plugins( $storage, $max_end_time );
		static::throw_if_out_of_time( $max_end_time );
		
		$mu_rel_dir = static::RELATIVE_MU_PLUGINS_BACKUP_PATH . time() . '/';
		$mu_dir     = $backup_root . $mu_rel_dir;
		$meta['mu_plugins'] = static::Backup_MU_Plugins( static::installation_paths()->mu_plugins_directory(), $mu_dir );
		$meta['mu_plugins']['directory'] = $mu_rel_dir;

		static::throw_if_out_of_time( $max_end_time );
		
		$lang_rel_dir = static::RELATIVE_LANGUAGES_BACKUP_PATH . time() . '/';
		$meta['languages'] = static::Backup_Languages( $storage, static::installation_paths()->languages_directory(), $lang_rel_dir );
		$meta['languages']['directory'] = $lang_rel_dir;

		$dropins_rel_dir = static::RELATIVE_DROPINS_BACKUP_PATH . time();
		static::Backup_Dropins( $storage, static::installation_paths()->wp_content_directory(), $dropins_rel_dir );
		$meta['dropins']['directory'] = $dropins_rel_dir;

		$root_dir_rel_dir = static::RELATIVE_ROOTDIR_BACKUP_PATH . time();
		static::Backup_Root( $storage, $root_dir_rel_dir );
		$meta['root_directory']['directory'] = $root_dir_rel_dir;

		$options_rel_dir = static::RELATIVE_OPTIONS_BACKUP_PATH . time();
		static::Backup_Options( $storage, $options_rel_dir );
		$meta['options']['directory'] = $options_rel_dir ;

		return $meta;
	}

	/**
	 * A freeform array containing data that can be used to reconstruct the object.
	 * This data is likely to be stored as part of the whole backup meta file and used for
	 * when a restore or display of backup details are needed.
	 *
	 * @since 1.0.0
	 *
	 * @return array Data to reconstruct the object.
	 */
	public function data(): array {
		$ret = [];

		$ret['version'] = $this->version;

		$themes = [];
		foreach ( $this->themes as $directory => $theme_data ) {
			$themes[ $directory ][ 'version']       = $theme_data->version();
			$themes[ $directory ][ 'rel_directory'] = $theme_data->directory();
		}
		$ret['themes'] = $themes;

		$plugins = [];
		foreach ( $this->plugins as $directory => $theme_data ) {
			$plugins[ $directory ][ 'version']       = $theme_data->version();
			$plugins[ $directory ][ 'rel_directory'] = $theme_data->directory();
		}
		$ret['plugins'] = $plugins;

		return $ret;
	}


	/**
	 * Restore a backup.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $backup_root The storage to which to write files.
	 * @param array          $data        An unstructured data that the engine need for restoring the backup.
	 */
	public static function restore( Backup_Storage $storage, array $data ) {

	}

	/**
	 * Human redable description of the engine. Should not contain HTML (it will be escaped),
	 * and be translated where appropriate.
	 *
	 * @since 1.0.0
	 *
	 * @return string The description text.
	 */
	public static function description() : string {
		return __( 'Essential code and settings' );
	}

	/**
	 * A unique identiier of the engine. It mey be used in the backup meta files, therefor
	 * best to have it semantically meaningful.
	 *
	 * @since 1.0.0
	 *
	 * @return string The identifier.
	 */
	public static function identifier(): string {
		return 'core';
	}
}