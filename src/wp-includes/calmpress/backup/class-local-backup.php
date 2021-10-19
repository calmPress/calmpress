<?php
/**
 * Implementation of a local backup
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

// This is need for accessing get_plugins API.
require_once ABSPATH . 'wp-admin/includes/plugin.php';

/**
 * A local backup class for backups consisting of two files as expect by the Local_Backup_Storage
 * class.
 *
 * @since 1.0.0
 */
class Local_Backup implements Backup {

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
	 * The meta information about the backup.
	 *
	 * @since 1.0.0
	 */
	protected $backup_file;

	/**
	 * The meta information about the backup.
	 *
	 * @since 1.0.0
	 */
	protected $storage;

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
	public function __construct( string $meta_file, Backup_Storage $storage ) {
		$this->storage = $storage;

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
			$error = error_get_last();
			throw new \Exception( 'Failed creating a backup directory ' . $directory . '. Cause: ' . $error['message'] );
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
			$error = error_get_last();
			throw new \Exception( sprintf( 'Copy from %s to %s had failed. Cause: %s', $source, $destination, $error['message'] ) );
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
	 * Backup the core files (wp-admin, wp-include and some core file or root directory).
	 *
	 * If a backup for the current version already exists, just return the directory in which it located,
	 * otherwise create a new directory under the core backups root, and copy into it all files from
	 * wp-include, wp-admin and core files on root, while preserving the relative directory structure.
	 *
	 * @since 1.0.0
	 *
	 * @param string $core_dir The root directory for the core backup files.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Core( string $core_dir ) : string {
		$version = calmpress_version();

		$backup_dir = $core_dir . $version;

		/*
		 * If the backup directory exists it means we already have a backup of the version,
		 * If not we need to create a directory and copy into it the core files.
		 */
		if ( ! file_exists( $backup_dir ) ) {

			// Temp directory to backup to that will be easier to discard in case of failure.
			$tmp_backup_dir = $core_dir . wp_unique_filename( $core_dir, 'tmp-' . $version );

			// Copy wp-includes
			static::Backup_Directory( static::installation_paths()->wp_includes_directory(), $tmp_backup_dir . '/wp-includes' );


			// Copy wp-admin.
			static::Backup_Directory( static::installation_paths()->wp_admin_directory(), $tmp_backup_dir . '/wp-admin' );

			// Copy core code files located at root directory.
			foreach ( static::installation_paths()->core_root_file_names() as $file ) {
				static::copy( static::installation_paths()->root_directory() . $file, $tmp_backup_dir . '/' . $file );
			}

			// All went well so far, rename the temp directory to the proper expected name.
			// While failure is handled, it is more for completness as the chances for it to happen
			// are less than slim.
			if ( ! rename( $tmp_backup_dir, $backup_dir ) ) {
				throw new \Exception( 'Failed renaming the temp core backup directory %s to %s ', $tmp_backup_dir, $backup_dir );
			}
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
		} else {
			static::mkdir( $backup_dir );
		}
	}

	/**
	 * Backup the languages directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $source     The full path to languages directory which might not exist.
	 * @param string $backup_dir The directory to backup to.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Languages( string $source, string $backup_dir ) {

		// If the languages directory does not exist there is nothing to backup and
		// Backup_Directory requires an existing directory.
		if ( is_dir( $source ) ) {
			static::Backup_Directory( $source, $backup_dir );
		} else {
			static::mkdir( $backup_dir );
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
	 * @param string $source_dir The directory of the dropins files.
	 * @param string $backup_dir The root directory for the dropins backup files.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Dropins( string $source_dir, string $backup_dir ) {

		static::mkdir( $backup_dir );

		foreach ( static::installation_paths()->dropin_files_name() as $filename ) {
			$file = $source_dir . $filename;
			if ( file_exists( $file ) ) {
				if ( ! is_link( $file ) ) {
					static::copy( $file, $backup_dir . $filename );
				}
			}
		}
	}

	/**
	 * Backup the files on the root dir.
	 *
	 * The root directory can contain all kind of files that might not be code but are needed
	 * for the proper functioning of the site.
	 *
	 * @since 1.0.0
	 *
	 * @param string $backup_dir The root directory for the root backup files.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Root( string $backup_dir ) {

		$core_files = static::installation_paths()->core_root_file_names();

		static::mkdir( $backup_dir );

		foreach ( new \DirectoryIterator( static::installation_paths()->root_directory() ) as $file ) {
			if ( $file->isDot() || $file->isLink() || ! $file->isFile() ) {
				continue;
			}

			// No need to backup core files.
			if ( in_array( $file->getFilename(), $core_files, true ) ) {
				continue;
			}
			static::copy( $file->getPathname(), $backup_dir . $file->getFilename() );
		}
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
	 * @param string    $themes_backup_dir The root directory for the themes backup directories.
	 *
	 * @param \WP_Theme $theme The object representing the theme properties.
	 *
	 * @return string The path to the backup directory relative to the backup
	 *                root directory.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Theme( string $themes_backup_dir, \WP_Theme $theme ) : string {
		$version      = $theme->get( 'Version' );
		$source       = $theme->get_stylesheet_directory();
		$relative_dir = basename( $source ) . '/' . $version;
		$theme_dir    = $themes_backup_dir . '/' . basename( $source );
		$backup_dir   = $themes_backup_dir . $relative_dir;

		/*
		 * If the backup directory exists it means we already have a backup of the version,
		 * If not we need to create a directory and copy into it the theme files.
		 */
		if ( ! file_exists( $backup_dir ) ) {

			// Temp directory to backup to that will be easier to discard in case of failure.
			$tmp_backup_dir = $theme_dir . '/' . wp_unique_filename( $theme_dir, 'tmp-' . $version );

			// Copy the theme files.
			static::Backup_Directory( $source, $tmp_backup_dir );

			// All went well so far, rename the temp directory to the proper expected name.
			// While failure is handled, it is more for completness as the chances for it to happen
			// are less than slim.
			if ( ! rename( $tmp_backup_dir, $backup_dir ) ) {
				throw new \Exception( sprintf( 'Failed renaming the temp theme backup directory %s to %s ', $tmp_backup_dir, $backup_dir ) );
			}
		}

		return $relative_dir;
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
	 * @param string $themes_backup_dir The root directory for the tehemes backup directories.
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
	protected static function Backup_Themes( string $themes_backup_dir ) : array {
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
			// skip themes without a version.
			if ( $theme->get( 'Version' ) ) {
				$theme_dir = static::Backup_Theme( $themes_backup_dir . static::RELATIVE_THEMES_BACKUP_PATH, $theme );
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
	 * @param string $plugins_backup_dir The root directory for the plugins backup files.
	 * @param string $source             The directory in which the plugin is located.
	 * @param string $version            The version to associate with the directory.
	 *
	 * @return string The path to the backup directory relative to the backup
	 *                root directory.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Plugin_Directory( string $plugins_backup_dir, string $source, string $version ) : string {
		$relative_dir = basename( $source ) . '/' . $version;
		$backup_dir   = $plugins_backup_dir . $relative_dir;
		$plugin_dir   = $plugins_backup_dir . basename( $source );

		/*
		 * If the backup directory exists it means we already have a backup of the version,
		 * If not we need to create a directory and copy into it the plugin files.
		 */
		if ( ! file_exists( $backup_dir ) ) {

			// Temp directory to backup to that will be easier to discard in case of failure.
			$tmp_backup_dir = $plugin_dir . '/' . wp_unique_filename( $plugin_dir, 'tmp-' . $version );

			// Copy the directory files.
			static::Backup_Directory( $source, $tmp_backup_dir );

			// All went well so far, rename the temp directory to the proper expected name.
			// While failure is handled, it is more for completness as the chances for it to happen
			// are less than slim.
			if ( ! rename( $tmp_backup_dir, $backup_dir ) ) {
				throw new \Exception( sprintf( 'Failed renaming the temp plugin backup directory %s to %s ', $tmp_backup_dir, $backup_dir ) );
			}
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
	 * @param string $plugins_backup_dir The root directory for the plugins backup files.
	 * @param string $source             The file to backup.
	 * @param string $version            The version of the plugin.
	 *
	 * @return string The path to the backup directory relative to the backup
	 *                root directory.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Root_Single_File_Plugin( string $plugins_backup_dir, string $source, string $version ) : string {
		$relative_dir = basename( $source ) . '/' . $version;
		$backup_dir   = $plugins_backup_dir . $relative_dir;

		/*
		 * If the backup directory exists it means we already have a backup of the version,
		 * If not we need to create a directory and copy into it the plugin files.
		 */
		if ( ! file_exists( $backup_dir ) ) {

			// Temp directory to backup to that will be easier to discard in case of failure.
			$tmp_backup_dir = $plugins_backup_dir . wp_unique_filename( $plugins_backup_dir, 'tmp-' . $version );
			static::mkdir( $tmp_backup_dir );

			// Copy the file to the temp directory.
			static::copy( $source, $tmp_backup_dir . '/' . basename( $source ) );

			// All went well so far, rename the temp directory to the proper expected name.
			// While failure is handled, it is more for completness as the chances for it to happen
			// are less than slim.

			// need to create the dir for the rename to not fail.
			static::mkdir( dirname( $backup_dir ) );

			if ( ! rename( $tmp_backup_dir, $backup_dir ) ) {
				throw new \Exception( sprintf( 'Failed renaming the temp plugin backup directory %s to %s ', $tmp_backup_dir, $backup_dir ) );
			}
		}

		return $relative_dir;
	}

	 /**
	 * Backup the plugin files and directories.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugins_backup_dir The root directory for the plugins backup files.
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
	protected static function Backup_Plugins( string $backup_root ) : array {

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
					$backup_root . static::RELATIVE_PLUGINS_BACKUP_PATH,
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
				$backup_root . static::RELATIVE_PLUGINS_BACKUP_PATH,
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
	 * @param string $backup_dir The root directory for the options backup files.
	 * @param int    $site_id    The id of the specific site being backed up.
	 *
	 * @throws \Exception When file creation error occurs.
	 */
	protected static function Backup_Site_Options( string $backup_dir, int $site_id ) {
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
		if ( false === file_put_contents( $file, $json ) ) {
			throw new \Exception( 'Failed writing to ', $file );
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
	 * @param string $backup_dir The root directory for the options backup files.
	 *
	 * @throws \Exception When directory creation or file creation error occurs.
	 */
	protected static function Backup_Options( string $backup_dir ) {
		global $wpdb;

		static::mkdir( $backup_dir );

		// loop over all sites, store options for each site in different file.
		if ( is_multisite() ) {
			foreach ( \get_sites() as $site ) {
				static::Backup_Site_Options( $backup_dir, $site->blog_id );
			}
		} else {
			static::Backup_Site_Options( $backup_dir, \get_current_blog_id() );
		}
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
	 * Do a local backup of core files, themes, plugins, root directory and option values.
	 *
	 * @since 1.0.0
	 *
	 * @param string $description The description to be used for the backup.
	 * @param string $backup_root The path to the directory on which the backup heirarchy
	 *                            should be and the meta files should be written to.
	 *
	 * @throws \Exception if the backup creation fails.
	 */
	public static function create_backup( string $description, string $backup_root ) {
		$meta = [];

		$meta['time']        = time();
		$meta['description'] = $description;

		static::Backup_Core( $backup_root . static::RELATIVE_CORE_BACKUP_PATH );
		$meta['core'] = [
			'version'   => calmpress_version(),
			'directory' => static::RELATIVE_CORE_BACKUP_PATH,
		];

		// Backup all themes that are in standard theme location, which can be activated (no errors).
		// Ignore everything else in the themes directories.
		$meta['themes'] = static::Backup_Themes( $backup_root );

		$meta['plugins'] = static::Backup_Plugins( $backup_root );

		$mu_rel_dir = static::RELATIVE_MU_PLUGINS_BACKUP_PATH . time() . '/';
		$mu_dir     = $backup_root . $mu_rel_dir;
		$meta['mu_plugins'] = static::Backup_MU_Plugins( static::installation_paths()->mu_plugins_directory(), $mu_dir );
		$meta['mu_plugins']['directory'] = $mu_rel_dir;

		$lang_rel_dir = static::RELATIVE_LANGUAGES_BACKUP_PATH . time() . '/';
		$lang_dir     = $backup_root . $lang_rel_dir;
		$meta['languages'] = static::Backup_Languages( static::installation_paths()->languages_directory(), $lang_dir );
		$meta['languages']['directory'] = $lang_rel_dir;

		$dropins_rel_dir = static::RELATIVE_DROPINS_BACKUP_PATH . time() . '/';
		$dropins_dir = $backup_root . $dropins_rel_dir;
		static::Backup_Dropins( static::installation_paths()->wp_content_directory(), $dropins_dir );
		$meta['dropins']['directory'] = $dropins_rel_dir;

		$root_dir_rel_dir = static::RELATIVE_ROOTDIR_BACKUP_PATH . time() . '/';
		$root_dir_dir = $backup_root . $root_dir_rel_dir;
		static::Backup_Root( $root_dir_dir );
		$meta['root_directory']['directory'] = $root_dir_rel_dir;

		$options_rel_dir = static::RELATIVE_OPTIONS_BACKUP_PATH . time() . '/';
		$options_dir = $backup_root . $options_rel_dir;
		static::Backup_Options( $options_dir );
		$meta['options']['directory'] = $options_rel_dir;
		
		$json = json_encode( $meta );
		$file = $backup_root . 'meta-' . time() . '.json';
		if ( false === file_put_contents( $file, $json ) ) {
			throw new \Exception( 'Failed writing to ', $file );
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
	 * The storage in which the backup is located.
	 *
	 * @since 1.0.0
	 *
	 * @return Backup_Storage The storage.
	 */
	public function storage() : Backup_Storage {
		return $this->storeage;
	}

    public function type() : string {
		return 'core';
	}

	/**
	 * Restore the backup.
	 *
	 * @since 1.0.0
	 */
	public function restore( restore_engines $engines ) {

	}
}