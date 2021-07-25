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
	 * The directory in which themes' backup directories are located relative to the
	 * backup root directory.
	 */
	const RELATIVE_PLUGINS_BACKUP_PATH = 'plugins/';

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
	private $backup_file;

	/**
	 * The meta information about the backup.
	 *
	 * @since 1.0.0
	 */
	private $storage;

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
	 * A utility function to copy files and raise exceptions if it fails.
	 *
	 * @since 1.0.0
	 *
	 * @param string $source      Path to the file to copy.
	 * @param string $destination Path to the copy destination.
	 *
	 * @throws \Exception When copy fails.
	 */
	private static function Copy( string $source, string $destination ) {
		if ( ! copy( $source, $destination ) ) {
			throw new \Exception( sprintf( 'Copy from %s to %s had failed', $source, $destination ) );
		}
	}

	/**
	 * Backup recursively a directory from source directory into destination directory.
	 * 
	 * The destination directory is created if do not exist.
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $source      Full path of the source directory.
	 * @param string $destination Full path of the destination directory.
	 * 
	 * @throws Exception When directory could not be created or file could not be copied.
	 */
	private static function Backup_Directory( string $source, string $destination ) {

		if ( ! file_exists( $destination ) ) {
				if ( ! mkdir( $destination, 0755, true ) ) {
				throw new Exception( 'Failed creating a backup directory ' . $backup_dir );
			}
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				$dir = $destination . '/' . $iterator->getSubPathName();
				if ( ! mkdir( $dir, 0755, true ) ) {
					throw new \Exception( 'Failed creating a directory ' . $dir );
				}
			} elseif ( ! $item->isLink() ) { 
				// Symlinks might point anywhere, no clear backup strategy for them so handle only files.
				$file = $destination . '/' . $iterator->getSubPathName();
				self::Copy( $item->getPathname(), $file );
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
	 * @return string The path to the backup directory relative to the backup
	 *                root directory.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	private static function Backup_Core( string $core_dir ) : string {
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
			self::Backup_Directory( ABSPATH . WPINC, $tmp_backup_dir . '/wp-includes' );


			// Copy wp-admin.
			self::Backup_Directory( ABSPATH . 'wp-admin', $tmp_backup_dir . '/wp-admin' );

			// Copy core code files located at root directory.
			$files = [
				'index.php',
				'wp-activate.php',
				'wp-blog-header.php',
				'wp-comments-post.php',
				'wp-cron.php',
				'wp-cron.php',
				'wp-load.php',
				'wp-login.php',
				'wp-settings.php',
				'wp-signup.php',
			];

			foreach ( $files as $file ) {
				self::Copy( ABSPATH . $file, $tmp_backup_dir . '/' . $file );
			}

			// All went well so far, rename the temp directory to the proper expected name.
			if ( ! rename( $tmp_backup_dir, $backup_dir ) ) {
				throw new \Exception( 'Failed renaming the temp core backup directory %s to %s ', $tmp_backup_dir, $backup_dir );
			}
		}

		return $backup_dir;
	}

	/**
	 * Backup the dropins plugins files.
	 *
	 * Dropins are located at the root of the wp_content directory. as they don't contain
	 * version information just backup all of them together.
	 *
	 * @since 1.0.0
	 *
	 * @param string $backup_dir The root directory for the dropins backup files.
	 *
	 * @return string The path to the backup directory relative to the backup
	 *                root directory.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	private static function Backup_Dropins( string $backup_dir ) : string {

		if ( ! mkdir( $backup_dir, 0755, true ) ) {
			throw new Exception( 'Failed creating a backup directory ' . $backup_dir );
		}

		foreach ( _get_dropins() as $filename => $data ) {
			$file = WP_CONTENT_DIR . '/' . $filename;
			if ( file_exists( $file ) ) {
				if ( ! is_link( $file ) ) {
					self::Copy( $file, $backup_dir . $filename );
				}
			}
		}

		return $backup_dir;
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
	 * @return string The path to the backup directory relative to the backup
	 *                root directory.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	private static function Backup_Root( string $backup_dir ) : string {

		$core_files = [
			'index.php',
			'wp-activate.php',
			'wp-blog-header.php',
			'wp-comments-post.php',
			'wp-cron.php',
			'wp-cron.php',
			'wp-load.php',
			'wp-login.php',
			'wp-settings.php',
			'wp-signup.php',
		];

		if ( ! mkdir( $backup_dir, 0755, true ) ) {
			throw new Exception( 'Failed creating a backup directory ' . $backup_dir );
		}

		foreach ( new \DirectoryIterator( ABSPATH ) as $file ) {
			if ( $file->isDot() || $file->isLink() || ! $file->isFile() ) {
				continue;
			}

			// No need to backup core files.
			if ( in_array( $file->getFilename(), $core_files, true ) ) {
				continue;
			}
			self::Copy( $file->getPathname(), $backup_dir . $file->getFilename() );
		}

		return $backup_dir;
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
	 * @param string    $themes_backup_dir The root directory for the plugins backup files.
	 *
	 * @param \WP_Theme $theme The object representing the theme properties.
	 *
	 * @return string The path to the backup directory relative to the backup
	 *                root directory.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	private static function Backup_Theme( string $themes_backup_dir, \WP_Theme $theme ) : string {
		$version = $theme->get( 'Version' );
		$source  = $theme->get_stylesheet_directory();

		$theme_dir  = $themes_backup_dir . basename( $source );
		$backup_dir = $theme_dir . '/' . $version;

		/*
		 * If the backup directory exists it means we already have a backup of the version,
		 * If not we need to create a directory and copy into it the theme files.
		 */
		if ( ! file_exists( $backup_dir ) ) {

			// Temp directory to backup to that will be easier to discard in case of failure.
			$tmp_backup_dir = $theme_dir . '/' . wp_unique_filename( $theme_dir, 'tmp-' . $version );

			// Copy the theme files.
			self::Backup_Directory( $source, $tmp_backup_dir );

			// All went well so far, rename the temp directory to the proper expected name.
			if ( ! rename( $tmp_backup_dir, $backup_dir ) ) {
				throw new \Exception( sprintf( 'Failed renaming the temp theme backup directory %s to %s ', $tmp_backup_dir, $backup_dir ) );
			}
		}

		return $backup_dir;
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
	 *
	 * @param string $directory The directory in which the plugin is located
	 *                          relative to plugins root directory.
	 * @param string $version   The version of the plugin.
	 *
	 * @return string The path to the backup directory relative to the backup
	 *                root directory.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	private static function Backup_Plugin( string $plugins_backup_dir, string $directory, string $version ) : string {
		$source = WP_PLUGIN_DIR . '/' . $directory;

		$plugin_dir = $plugins_backup_dir . $directory;
		$backup_dir = $plugin_dir . '/' . $version;

		/*
		 * If the backup directory exists it means we already have a backup of the version,
		 * If not we need to create a directory and copy into it the plugin files.
		 */
		if ( ! file_exists( $backup_dir ) ) {

			// Temp directory to backup to that will be easier to discard in case of failure.
			$tmp_backup_dir = $plugin_dir . '/' . wp_unique_filename( $plugin_dir, 'tmp-' . $version );

			// Copy the theme files.
			self::Backup_Directory( $source, $tmp_backup_dir );

			// All went well so far, rename the temp directory to the proper expected name.
			if ( ! rename( $tmp_backup_dir, $backup_dir ) ) {
				throw new \Exception( sprintf( 'Failed renaming the temp plugin backup directory %s to %s ', $tmp_backup_dir, $backup_dir ) );
			}
		}

		return $backup_dir;
	}

	/**
	 * Backup the options to a json file for a the current site.
	 *
	 * The backup ignores transients, widgets and user roles options.
	 *
	 * @since 1.0.0
	 *
	 * @param string $backup_dir The root directory for the options backup files.
	 *
	 * @throws \Exception When file creation error occurs.
	 */
	private static function Backup_Site_Options( string $backup_dir ) {
		global $wpdb;

		$options = $wpdb->get_results( "SELECT option_name, option_value, autoload FROM $wpdb->options WHERE option_name NOT LIKE '_%transient_%'" );

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
		$file = $backup_dir . \get_current_blog_id() . '-options.json';
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
	 * @return string The path to the backup directory relative to the backup
	 *                root directory.
	 *
	 * @throws \Exception When directory creation or file creation error occurs.
	 */
	private static function Backup_Options( string $backup_dir ) : string {
		global $wpdb;

		if ( ! mkdir( $backup_dir, 0755, true ) ) {
			throw new Exception( 'Failed creating a backup directory ' . $backup_dir );
		}

		// loop over all sites, store options for each site in different file.
		if ( is_multisite() ) {
			foreach ( \get_sites() as $site ) {
				\switch_to_blog( $site->blog_id );
				self::Backup_Site_Options( $backup_dir );
				\restore_current_blog();

			}
		} else {
			self::Backup_Site_Options( $backup_dir );
		}

		return $backup_dir;
	}

	/**
	 * Do a local backup.
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

		$core_directory = self::Backup_Core( $backup_root . self::RELATIVE_CORE_BACKUP_PATH );
		$meta['core'] = [
			'version'   => calmpress_version(),
			'directory' => $core_directory,
		];

		// Backup all themes that are in standard theme location, even if they are faulty.
		// Ignore everything else in the themes directories.
		$meta['themes'] = [];
		$themes         = wp_get_themes( [ 'errors' => null ] ); // Backup all themes even if broken.
		foreach ( $themes as $theme ) {
			$theme_dir = self::Backup_Theme( $backup_root . self::RELATIVE_THEMES_BACKUP_PATH, $theme );
			$meta['themes'][ $theme->get_stylesheet_directory() ] = [
				'version'   => $theme->get( 'Version' ),
				'directory' => $theme_dir,
			];
		}

		// Plugins are in principal just a file with a plugin header and you can have
		// a one file plugin in the plugins directory, or two plugin files in one directory
		// in addition to the standard format of one file in its own directory,
		// therefore copying directorie like it is done in themes is just not enough if version
		// information is needed.

		$plugindir = [];
		foreach ( get_plugins() as $filename => $plugin_data ) {
			$plugin_data['filename']              = $filename; // we need this later.
			$plugindirs[ dirname( $filename ) ][] = $plugin_data;
		}

		$meta['plugins'] = [];

		// Special case are plugins locate at the plugins root directory.
		if ( isset( $plugindirs['.'] ) ) {
			foreach ( $plugindirs['.'] as $plugin_data ) {
				$plugin_dir = self::Backup_Single_File_Plugin( $plugin_data );

				$meta['single_file_plugins'][ $plugin_data['filename'] ] = [
					'version'   => $plugin_data['Version'],
					'directory' => $plugin_dir,
				];
			}
			unset( $plugindirs['.'] );
		}

		foreach ( $plugindirs as $dirname => $dir_data ) {
			$version = '';
			foreach ( $dir_data as $plugin_data ) {
				$version .= $plugin_data['Version'];
			}
			
			$plugin_dir = self::Backup_Plugin( $backup_root . self::RELATIVE_PLUGINS_BACKUP_PATH, $dirname, $version );

			$meta['plugins'][ $dirname ] = [
				'version'   => $version,
				'directory' => $plugin_dir,
			];
		}

		$meta['dropins']['directory'] = self::Backup_Dropins( $backup_root . self::RELATIVE_DROPINS_BACKUP_PATH . time() . '/' );

		$meta['root_directory']['directory'] = self::Backup_Root( $backup_root . self::RELATIVE_ROOTDIR_BACKUP_PATH . time() . '/' );

		$meta['options']['directory'] = self::Backup_Options( $backup_root . self::RELATIVE_OPTIONS_BACKUP_PATH . time() . '/' );

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