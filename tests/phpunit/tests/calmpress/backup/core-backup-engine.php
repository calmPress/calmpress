<?php
/**
 * Unit tests covering Core_Backup_Engine functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

require_once ABSPATH . 'wp-admin/includes/file.php';

/**
 * Mock of the Paths class with directory structure rooted in the uploads directory.
 */
class mock_paths extends \calmpress\calmpress\Paths {

    var string $root_dir;

    public function __construct() {
        $upload_dir = wp_upload_dir();
        $root_dir = $upload_dir['basedir'];
        $this->root_dir = $root_dir . '/root/';
    }

    public function root_directory() : string {
        return $this->root_dir;
    }

    public function wp_admin_directory() : string {
        return $this->root_dir . 'wp-admin/';
    }

    public function wp_includes_directory() :string {
        return $this->root_dir . 'wp-includes/';
    }

    public function wp_content_directory() : string {
        return $this->root_dir . 'wp-content/';
    }

    public function plugins_directory() : string {
        return $this->root_dir . 'wp-content/plugins/';
    }	

    public function themes_directory() : string {
        return $this->root_dir . 'wp-content/themes/';
    }
}

/**
 * Mock the Core_Backup_Engine's Backup_Site_Options method to be able to test the
 * Backup_Options method.
 * 
 * @since 1.0.0
 */
class mock_backup_options extends \calmpress\backup\Core_Backup_Engine {
    public static $paths;
	public static $network_paths;

    /**
     * Overide the Backup_Site_Options method to collect information on the site ids
     * it is called with.
	 *
	 * @since 1.0.0
     */
	protected static function Backup_Site_Options( \calmpress\backup\Backup_Storage $storage, int $site_id ): \calmpress\backup\Backup_Section_Identity {
		self::$paths[ $site_id ] = $storage;

		return new \calmpress\backup\Backup_Section_Identity( 'options', (string) $site_id, 'test' );
    }

	/**
	 * Record the network IDs passed to the network options backup operation.
	 *
	 * @since 1.0.0
	 *
	 * @param \calmpress\backup\Backup_Storage $storage    Backup storage.
	 * @param int                                $network_id Network ID.
	 *
	 * @return \calmpress\backup\Backup_Section_Identity Test section identity.
	 */
	protected static function Backup_Network_Options( \calmpress\backup\Backup_Storage $storage, int $network_id ): \calmpress\backup\Backup_Section_Identity {
		self::$network_paths[ $network_id ] = $storage;

		return new \calmpress\backup\Backup_Section_Identity( 'network-options', (string) $network_id, 'test' );
	}
}

/**
 * A section used to test the core backup information display.
 *
 * @since 1.0.0
 */
class mock_display_backup_section extends \calmpress\backup\Backup_Section {

	/**
	 * Fetching is not needed by display tests.
	 *
	 * @since 1.0.0
	 *
	 * @param string $destination Destination directory.
	 */
	protected function fetch_files( string $destination ): void {
	}

	/**
	 * Removal is not needed by display tests.
	 *
	 * @since 1.0.0
	 */
	public function remove(): void {
	}
}

/**
 * Class that mocks WP_Theme for the properties required by Backup_Theme.
 *
 * @since 1.0.0
 */
class mock_theme extends WP_Theme {

    /*
     * The directory of the stylesheet of the theme.
     */
    private $stylesheet_directory;

    /*
     * The verion of the theme.
     */
    private $version;

    public function __construct( string $stylesheet_directory, string $version ) {
        $this->stylesheet_directory = $stylesheet_directory;
        $this->version              = $version;
    }

    /**
     * override the get method to return the version with which the object was
     * instantiated. The mock also provides a display name for the section.
     *
     * @since 1.0.0
     * 
     * @param string $type The type of information requested, only 'Version' is a valid one.
     * 
     * @return mixed The relevant value based on the type parameter.
     */
    public function get( $type ) {
        if ( 'Version' === $type ) {
            return $this->version;
        }
        if ( 'Name' === $type ) {
            return 'Mock theme';
        }
        trigger_error( 'Unknown type passed: ' . $type, E_USER_ERROR );
    }

    /**
     * override the get_stylesheet_directory method to return the directory of the theme 
     * with which the object was instantiated.
     *
     * @since 1.0.0
     * 
     * @return string The theme directory with which the object was intanstiated.
     */
    public function get_stylesheet_directory() : string {
        return $this->stylesheet_directory;
    }
}

/**
 * Mock the Core_Backup_Engine's Backup_Directory method to be able to test the
 * Backup_Theme method.
 * 
 * @since 1.0.0
 */
class mock_backup_theme extends \calmpress\backup\Core_Backup_Engine {
    
    public static bool $called = false;
    public static string $called_source = '';
    public static string $called_dest = '';

    /**
     * Overide the Backup_Directory method to skip having files being copied.
     *
     * The mocked version verifies the expected parameters and indicates the function was properly called if they match.
     *
     * @since 1.0.0
     */
    protected static function Backup_Directory( string $source, calmpress\backup\Temporary_Backup_Section $staging, string $destination ) {

        static::$called = true;
        static::$called_source = $source;
        static::$called_dest === $destination;
    }
}

/**
 * Mock the Core_Backup_Engine's Backup_Directory method to be able to test the
 * Backup_Themes method faster and change paths to testable ones.
 * 
 * @since 1.0.0
 */
class mock_backup_themes extends \calmpress\backup\Core_Backup_Engine {
    
    /**
     * Overide the paths object used to indicate where core file are to adjust
     * to test enviroment.
     */
    protected static function installation_paths() : \calmpress\calmpress\Paths {
        static $cache;

        if ( ! isset ( $cache ) ) {
            $cache = new mock_paths();
        }
        return $cache;
    }

    /**
     * Overide the Backup_Directory method to skip having files being copied.
     *
     * @since 1.0.0
     */
    protected static function Backup_Directory( string $source, calmpress\backup\Temporary_Backup_Section $staging, string $destination ) {
    }
}

/**
 * Test cases to test the Core_Backup_Engine class.
 *
 * @since 1.0.0
 */
class Core_Backup_Engine_Test extends WP_UnitTestCase {

    /**
     * (Local) Storage to use for testing
     *
     * @since 1.0.0
     *
     * @var \calmpress\backup\Backup_Storage
     */
    private \calmpress\backup\Backup_Storage $storage;

    /**
     * the root directory of the test storage.
     *
     * @since 1.0.0
     *
     * @var string
     */
    private string $storage_root;

    /**
     * Cleanup storage after tests.
     *
     * @since 1.0.0
     */
    public function tear_down() {
        $this->cleanup();
        parent::tear_down();
    }

    /**
     * Utility function to cleanup the storage.
     *
     * @since 1.0.0
     */
    private function cleanup() {
        $this->rm_dir( $this->storage_root );
    }

    /**
     * Cleanup storage before test runs.
     *
     * @since 1.0.0
     */
    public function set_up() {
        parent::set_up();
        $this->storage_root = get_temp_dir() . uniqid();
        $this->storage = new \calmpress\backup\Local_Backup_Storage( $this->storage_root, 'test_storage' );
    }

	/**
	 * Fetch a section into an isolated directory owned by the current test.
	 *
	 * @since 1.0.0
	 *
	 * @param \calmpress\backup\Backup_Section $section Section to fetch.
	 *
	 * @return string Absolute path to the fetched contents.
	 */
	private function fetch_section( \calmpress\backup\Backup_Section $section ): string {
		$destination = $this->storage_root . '/fetched-' . wp_generate_uuid4();
		mkdir( $destination, 0777, true );
		$section->fetch( $destination );

		return $destination;
	}

    /**
     * Remove directory and its file "recuresively".
     * 
     * @since 1.0.0
     * 
     * @param string $dir The directory to remove.
     */
    private static function rm_dir( $dir ) {
        if ( ! file_exists( $dir ) ) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $fileinfo) {
            if ( $fileinfo->isDir() ) {
                rmdir( $fileinfo->getRealPath() );
            } elseif ( $fileinfo->isLink() && ( PHP_OS_FAMILY === 'Windows' ) ) {
                unlink( $fileinfo->getPath() . '/' . $fileinfo->getFileName() );
            } else {
                unlink( $fileinfo->getRealPath() );
            }
        }
        
        rmdir( $dir );
    }

    /**
     * Test the Backup_Directory.
     * 
     * @since 1.0.0
     */
    function test_backup_directory() {

        $method = new ReflectionMethod( '\calmpress\backup\Core_Backup_Engine', 'Backup_Directory' );

        // copy a file (this test file).
        $test_dir = get_temp_dir() . uniqid();
        mkdir( $test_dir . '/source', 0755, true );
        copy( __FILE__, $test_dir . '/source/file1' );
        copy( __FILE__, $test_dir . '/source/file2' );
        mkdir( $test_dir . '/source/subdir' );
        copy( __FILE__, $test_dir . '/source/subdir/file1' );
        copy( __FILE__, $test_dir . '/source/subdir/file2' );
        if ( ! @symlink( $test_dir . '/source/file1', $test_dir . '/source/subdir/sym' ) ) {
            $this->markTestIncomplete(' failed creating the symlink. On windows you will need to run the tests as administrator');
        }

		$identity = new \calmpress\backup\Backup_Section_Identity( 'test', 'directory', 'dest' );
		$staging  = $this->storage->create_section( $identity, 'Options' );
        $method->invoke( null, $test_dir . '/source', $staging, '' );
		$staging->commit();
		$section_root = $this->fetch_section( $this->storage->section( $identity ) );

		$this->AssertTrue( is_file( $section_root . '/file1' ) );
		$this->AssertEquals( filesize( __FILE__ ), filesize( $section_root . '/file1' ) );
		$this->AssertTrue( is_file( $section_root . '/file2' ) );
		$this->AssertTrue( is_dir( $section_root . '/subdir' ) );
		$this->AssertTrue( is_file( $section_root . '/subdir/file1' ) );
		$this->AssertTrue( is_file( $section_root . '/subdir/file2' ) );
		$this->AssertFalse( file_exists( $section_root . '/subdir/sym' ) );

        $this->rm_dir( $test_dir );
    }

    /**
     * Test the Backup_Root method.
     * 
     * Test the logic with sample files.
     * 
     * @since 1.0.0
     */
    function test_backup_root() {

        $method = new ReflectionMethod( '\calmpress\backup\Core_Backup_Engine', 'Backup_Root' );

        $test_dir = get_temp_dir() . uniqid() . '/';

        // copy a file (this test file).
        mkdir( $test_dir, 0777, true );
        copy( __FILE__, $test_dir . 'wp-cron.php' );
        copy( __FILE__, $test_dir . 'wp-login.php' );
        copy( __FILE__, $test_dir . '.htaccess' );
        copy( __FILE__, $test_dir . 'none.php' );

		$files = $this->invoke_engine( 'root_files', $test_dir );
		$identity = $method->invoke( null, $this->storage, $files );
		$section = $this->storage->section( $identity );
		$this->assertStringStartsWith( 'filenames-timestamps-', $identity->version );
		$this->assertSame( '.htaccess, none.php', $section->display_name() );
		$section_root = $this->fetch_section( $section );
		$this->assertSame( array( '.htaccess', 'none.php' ), array_keys( $files ) );

        // Check that files that are non core files were copied
		$this->AssertTrue( is_file( $section_root . '/.htaccess' ) );
		$this->AssertEquals( filesize( __FILE__ ), filesize( $section_root . '/.htaccess' ) );
		$this->AssertTrue( is_file( $section_root . '/none.php' ) );

        // ... but no other file.
		$files = new FilesystemIterator( $section_root, FilesystemIterator::SKIP_DOTS );
		$this->AssertEquals( 2, iterator_count( $files ) );
		$this->AssertFalse( is_file( $section_root . '/.section.json' ) );

        self::rm_dir( $test_dir );
    }

    /**
     * Test the Backup_Site_Options method.
     * 
     * Test the logic with sample options.
     * 
     * @since 1.0.0
     */
    function test_backup_site_options() {

        $method = new ReflectionMethod( '\calmpress\backup\Core_Backup_Engine', 'Backup_Site_Options' );

        // for multisite testing we want to test the blog switch functionality of the function
        if ( is_multisite() ) {
            $blog_id = self::factory()->blog->create(
                array(
                    'public'  => 1,
                )
            );
            switch_to_blog( $blog_id );
        } else {
            $blog_id = get_current_blog_id();
        }

        // add some options and transients.
        add_option( 'test1', 'value1', '', 'no' );
        add_option( 'test2', 'value2', '', 'yes' );
        set_transient( 'trantest', 'tran1', 3000 );
        if ( is_multisite() ) {
            restore_current_blog();
        }
        
		$identity = $method->invoke( null, $this->storage, $blog_id );
		$section_root = $this->fetch_section( $this->storage->section( $identity ) );

        // Check file was created.
		$file = $section_root . '/options.json';
        $this->AssertTrue( file_exists( $file ) );

        // Test content
        $json = file_get_contents( $file );
        $data = json_decode( $json, true );

        $ar = [];
        foreach ( $data as $value ) {
            if ( 'test1' === $value['n'] || 'test2' === $value['n'] || '_transient_trantest' === $value['n'] ) {
                $ar[ $value['n'] ] = $value;
            }
        }

        $this->AssertTrue( array_key_exists( 'test1', $ar ) );
        $this->AssertSame( 'value1', $ar['test1']['v'] );
        $this->AssertSame( 'off', $ar['test1']['a'] );

        $this->AssertTrue( array_key_exists( 'test2', $ar ) );
        $this->AssertSame( 'value2', $ar['test2']['v'] );
        $this->AssertSame( 'on', $ar['test2']['a'] );

        $this->AssertFalse( array_key_exists( '_transient_trantest', $ar ) );
    }

    /**
     * Test the Backup_Site_Options method.
     * 
     * Test the logic for multiple site on multisite.
     * 
     * @since 1.0.0
     */
    function test_backup_options() {

        $method = new ReflectionMethod( 'mock_backup_options', 'Backup_Options' );

		$expected_blogs[] = get_current_blog_id();
		$expected_networks = array();
        // for multisite testing we want to test that all sites are used in the call
        // to backup_site_options.
		if ( is_multisite() ) {
			$expected_networks = array_map( 'intval', wp_list_pluck( get_networks(), 'id' ) );
            $expected_blogs[] = self::factory()->blog->create(
                array(
                    'public'  => 1,
                )
            );
            $expected_blogs[] = self::factory()->blog->create(
                array(
                    'public'  => 1,
                )
            );
        }

		$sections = $method->invoke( null, $this->storage );
		$this->AssertCount( count( $expected_blogs ) + count( $expected_networks ), $sections );

        // test correct calls to backup_site_options for all sites.
		foreach ( $expected_blogs as $blog_id ) {
            $this->AssertTrue( array_key_exists( $blog_id, mock_backup_options::$paths ) );
			$this->AssertSame( $this->storage, mock_backup_options::$paths[ $blog_id ] );
        }
		foreach ( $expected_networks as $network_id ) {
			$this->AssertTrue( array_key_exists( $network_id, mock_backup_options::$network_paths ) );
			$this->AssertSame( $this->storage, mock_backup_options::$network_paths[ $network_id ] );
		}
    }

	/**
	 * Verify each site's options section contains that site's values.
	 *
	 * @since 1.0.0
	 */
	public function test_backup_site_options_keeps_sites_separate() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		$site_ids = array( self::factory()->blog->create(), self::factory()->blog->create() );
		foreach ( $site_ids as $index => $site_id ) {
			switch_to_blog( $site_id );
			try {
				add_option( 'backup_site_marker', 'site-' . $index );
			} finally {
				restore_current_blog();
			}
		}

		foreach ( $site_ids as $index => $site_id ) {
			$identity = $this->invoke_engine( 'Backup_Site_Options', $this->storage, $site_id );
			$directory = $this->fetch_section( $this->storage->section( $identity ) );
			$rows = json_decode( file_get_contents( $directory . '/options.json' ), true );
			$options = array_column( $rows, 'v', 'n' );

			$this->assertSame( 'options', $identity->type );
			$this->assertSame( (string) $site_id, $identity->location );
			$this->assertSame( 'site-' . $index, $options['backup_site_marker'] );
		}
	}

	/**
	 * Verify network options are backed up per network without network transients.
	 *
	 * @since 1.0.0
	 */
	public function test_backup_network_options_keeps_networks_separate() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		$network_ids = array( get_current_network_id(), self::factory()->network->create() );
		foreach ( $network_ids as $index => $network_id ) {
			add_network_option( $network_id, 'backup_network_marker', 'network-' . $index );
			add_network_option( $network_id, '_site_transient_backup_network_marker', 'excluded' );
		}

		foreach ( $network_ids as $index => $network_id ) {
			$identity = $this->invoke_engine( 'Backup_Network_Options', $this->storage, $network_id );
			$directory = $this->fetch_section( $this->storage->section( $identity ) );
			$rows = json_decode( file_get_contents( $directory . '/options.json' ), true );
			$options = array_column( $rows, 'v', 'n' );

			$this->assertSame( 'network-options', $identity->type );
			$this->assertSame( (string) $network_id, $identity->location );
			$this->assertSame( 'network-' . $index, $options['backup_network_marker'] );
			$this->assertArrayNotHasKey( '_site_transient_backup_network_marker', $options );
		}
	}

    /**
     * Test the Backup_Theme method.
     * 
     * @since 1.0.0
     */
    function test_backup_theme() {

        $method = new ReflectionMethod( 'mock_backup_theme', 'Backup_Theme' );

        $theme_dir = $this->storage_root . '/theme';

        $theme = new mock_theme( $theme_dir, '1.0' );
		$identity = new \calmpress\backup\Backup_Section_Identity( 'theme', 'theme', '1.0' );

        $ret = $method->invoke( null, $this->storage, $theme, $identity );
        $this->AssertTrue( mock_backup_theme::$called );

        // The expected call has the root directory of the theme, mapped to the staging root.
        $this->AssertSame( $theme_dir, mock_backup_theme::$called_source );
        $this->AssertSame( '', mock_backup_theme::$called_dest );
		$this->AssertNull( $ret );

        // Test directory backup is not done if directory already exists.
        mock_backup_theme::$called = false;
        $ret = $method->invoke( null, $this->storage, $theme, $identity );
        $this->AssertFalse( mock_backup_theme::$called );
    }

    /**
     * Test the Backup_Themes method.
     * 
     * @since 1.0.0
     */
    function test_backup_themes() {

        $method = new ReflectionMethod( 'mock_backup_themes', 'Backup_Themes' );

        $paths_method = new ReflectionMethod( 'mock_backup_themes', 'installation_paths' );

        $upload_dir = wp_upload_dir();
        $test_dir   = $upload_dir['basedir'];

        // Create a themes directory in which there are themes to backup.
        // Create two valid themes (parent and child) with versions, an empty directory, a theme with no version,
        // and an invalid one (there are files but no style.css).
        $paths = $paths_method->invoke( null );
        $source_dir = $paths->themes_directory();
        self::rm_dir( $source_dir );
        mkdir( $source_dir, 0755, true );

        // Valid parent theme.
        mkdir( $source_dir . '/parent' );
        touch( $source_dir . '/parent/index.php' );
        file_put_contents( $source_dir . '/parent/style.css',
            '/* 
            Theme Name: Parent
            Version: 1.1
            '
        );
        mkdir( $source_dir . '/child' );
        touch( $source_dir . '/child/index.php' );
        file_put_contents( $source_dir . '/child/style.css',
            '/* 
            Theme Name: Child
            Template: parent
            Version: 1.0
            '
        );
        mkdir( $source_dir . '/empty' );
        mkdir( $source_dir . '/nostyle' );
        touch( $source_dir . '/nostyle/single.php' );
        mkdir( $source_dir . '/noversion' );
        touch( $source_dir . '/noversion/index.php' );
        file_put_contents( $source_dir . '/noversion/style.css',
            '/* 
            Theme Name: NoVersion 
            '
        );
        mkdir( $source_dir . '/childnoparent' );
        touch( $source_dir . '/childnoparent/index.php' );
        file_put_contents( $source_dir . '/childnoparent/style.css',
            '/* 
            Theme Name: Childnoparent 
            Template: noparent 
            Version: 1.0 
            '
        );

        $sections = $method->invoke( null, $this->storage, time() + 10 );

        // Valid themes are backed up even without a version header.
        $this->AssertCount( 3, $sections );
        $identities = [];
        foreach ( $sections as $section ) {
            $this->AssertInstanceOf( \calmpress\backup\Backup_Section_Identity::class, $section );
            $identities[ $section->location ] = $section->version;
        }
        $this->AssertSame( '1.0', $identities['child'] );
        $this->AssertSame( '1.1', $identities['parent'] );
        $this->AssertStringStartsWith( 'unversioned-', $identities['noversion'] );

        self::rm_dir( $paths->root_directory() );
        self::rm_dir( $test_dir . '/themes/' );
    }

    /**
     * Test the Backup_Plugin_Directory method.
     * 
     * @since 1.0.0
     */
    function test_backup_plugin_directory() {

        $method = new ReflectionMethod( 'mock_backup_theme', 'Backup_Plugin_Directory' );

        $test_dir = get_temp_dir() . uniqid();

        $plugin_dir = $test_dir . '/test_plugin';
        mkdir( $plugin_dir, 0777, true );
        
		$plugins = array(
			array( 'Name' => 'First Plugin', 'Version' => '52', 'filename' => 'test_plugin/first.php' ),
			array( 'Name' => 'Second Plugin', 'Version' => '3.45', 'filename' => 'test_plugin/second.php' ),
		);
		$identity = $method->invoke( null, $this->storage, $plugin_dir, $plugins );

        $this->AssertTrue( mock_backup_theme::$called );
        $this->AssertSame( $plugin_dir, mock_backup_theme::$called_source );
        $this->AssertSame( '', mock_backup_theme::$called_dest );
		$this->AssertSame( 'test_plugin', $identity->location );
		$this->AssertSame( '52_3.45', $identity->version );
		$this->AssertSame( 'First Plugin, Second Plugin', $this->storage->section( $identity )->display_name() );

        // Test directory backup is not done if directory already exists.
        mock_backup_theme::$called = false;
		$repeat_identity = $method->invoke( null, $this->storage, $plugin_dir, $plugins );
		$this->AssertFalse( mock_backup_theme::$called );
		$this->AssertEquals( $identity, $repeat_identity );
    }

    /**
     * Test the Backup_Single_File_Plugin method.
     * 
     * Use the hello,php plugin for the test
     * 
     * @since 1.0.0
     */
    function test_backup_single_file_plugin() {

        $method = new ReflectionMethod( '\calmpress\backup\Core_Backup_Engine', 'Backup_Single_File_Plugin' );

		$identity = $method->invoke( null, $this->storage, WP_PLUGIN_DIR . '/hello.php', '2.3', 'Hello Dolly' );
		$section_root = $this->fetch_section( $this->storage->section( new \calmpress\backup\Backup_Section_Identity( 'plugin-file', 'hello.php', '2.3' ) ) );

		$this->AssertTrue( is_dir( $section_root ) );
		$this->AssertTrue( is_file( $section_root . '/hello.php' ) );
		$this->AssertSame( filesize( WP_PLUGIN_DIR . '/hello.php' ), filesize( $section_root . '/hello.php' ) );
		$this->AssertSame( 'hello.php', $identity->location );
		$this->AssertSame( '2.3', $identity->version );
		$this->AssertSame( 'plugin-file', $identity->type );
    }

    /**
     * Test the Backup_Plugins method.
     * 
     * @since 1.0.0
     */
    function test_backup_plugins() {

        $method = new ReflectionMethod( 'mock_backup_theme', 'Backup_Plugins' );

        $upload_dir = wp_upload_dir();
        $test_dir   = $upload_dir['basedir'];

        $plugin_dir = $test_dir . '/plugin';

        $dest_dir = $this->storage_root . '/dest/';
		$sections = $method->invoke( null, $this->storage, time() + 10 );
		$versions = array();
		foreach ( $sections as $section ) {
			$this->AssertInstanceOf( \calmpress\backup\Backup_Section_Identity::class, $section );
			$versions[ $section->location ] = $section->version;
		}
		$this->AssertSame( '1.7.2', $versions['hello.php'] );
		$this->AssertSame( '1.0a', $versions['single_plugin_directory'] );
		$this->AssertSame( '1.1b_1.2c', $versions['double_plugin_directory'] );
    }

    /**
     * Test the Backup_MU_Plugins method.
     * 
     * @since 1.0.0
     */
    function test_backup_mu_plugins() {

        $method = new ReflectionMethod( 'mock_backup_theme', 'Backup_MU_Plugins' );

        $test_dir = get_temp_dir() . uniqid();
        mkdir( $test_dir . '/source/', 0777, true );
		$latest_modified = time() + 10;
		file_put_contents( $test_dir . '/source/plugin.php', '<?php' );
		touch( $test_dir . '/source/plugin.php', $latest_modified );
		$version_method = new ReflectionMethod( '\calmpress\backup\Core_Backup_Engine', 'version_from_directory_timestamp' );
		$this->AssertSame( 'unversioned-' . $latest_modified, $version_method->invoke( null, $test_dir . '/source/' ) );

        // Test Backup_Directory is invoked when directory exists.
        mock_backup_theme::$called = false;
		$identity = new \calmpress\backup\Backup_Section_Identity( 'mu-plugins', 'mu-plugins', 'unversioned-' . $latest_modified );
		$method->invoke( null, $this->storage, $test_dir . '/source/', $identity );
		$this->AssertTrue( mock_backup_theme::$called );

        self::rm_dir( $test_dir );
    }

    /**
     * Test the Backup_Languages method.
     * 
     * @since 1.0.0
     */
    function test_backup_languages() {

        $method = new ReflectionMethod( 'mock_backup_theme', 'Backup_Languages' );

        $test_dir = get_temp_dir() . uniqid();
        mkdir( $test_dir . '/source/', 0777, true );

        mock_backup_theme::$called = false;
		$identity = $method->invoke( null, $this->storage, $test_dir . '/source/' );
		$this->AssertTrue( mock_backup_theme::$called );
		$this->AssertSame( 'languages', $identity->location );
    }

    /**
     * Verify each present drop-in has its own reusable, timestamp-versioned section.
     * 
     * @since 1.0.0
     */
    function test_backup_dropins() {

        $method = new ReflectionMethod( '\calmpress\backup\Core_Backup_Engine', 'Backup_Dropins' );

        $test_dir = get_temp_dir() . uniqid();
        mkdir( $test_dir . '/source', 0777, true );

        $paths = new \calmpress\calmpress\Paths();

        // Only recognized drop-in files should be backed up.
        foreach ( $paths->dropin_files_name() as $filename ) {
            copy( __FILE__, $test_dir . '/source/' . $filename );
        }
        touch( $test_dir . '/source/test.test' );

		$sections = $method->invoke( null, $this->storage, $test_dir . '/source/' );
		$this->assertCount( count( $paths->dropin_files_name() ), $sections );
		foreach ( $sections as $identity ) {
			$this->assertSame( 'dropin', $identity->type );
			$this->assertSame( 'unversioned-' . filemtime( $test_dir . '/source/' . $identity->location ), $identity->version );
			$section_root = $this->fetch_section( $this->storage->section( $identity ) );
			$this->assertSame( filesize( __FILE__ ), filesize( $section_root . '/' . $identity->location ) );
			$this->assertCount( 1, glob( $section_root . '/*' ) );
		}
		$this->assertEquals( $sections, $method->invoke( null, $this->storage, $test_dir . '/source/' ) );
		$this->assertCount( count( $sections ), $this->storage->sections() );

        // A symlink with a recognized name must not become a section.
        $this->rm_dir( $test_dir . '/source' );
        mkdir( $test_dir . '/source', 0777, true );
        touch( $test_dir . '/source/test.test' );
        if ( ! @symlink( $test_dir . '/source/test.test', $test_dir . '/source/db.php' ) ) {
            $this->markTestIncomplete(' failed creating the symlink. On windows you will need to run the tests as administrator');
        }
		$this->assertSame( array(), $method->invoke( null, $this->storage, $test_dir . '/source/' ) );

        $this->rm_dir( $test_dir );
    }

	/**
	 * Verify changing a drop-in timestamp creates a new section version while retaining the old copy.
	 *
	 * @since 1.0.0
	 */
	public function test_dropin_timestamp_changes_section_version() {
		$method = new ReflectionMethod( '\calmpress\backup\Core_Backup_Engine', 'Backup_Dropins' );
		$source_dir = $this->storage_root . '/dropins-source/';
		\calmpress\utils\ensure_dir_exists( $source_dir );
		$file = $source_dir . 'db.php';
		file_put_contents( $file, 'first' );
		touch( $file, 1700000000 );

		$first = $method->invoke( null, $this->storage, $source_dir );
		$this->assertCount( 1, $first );
		$this->assertSame( 'db.php', $first[0]->location );
		$this->assertSame( 'unversioned-1700000000', $first[0]->version );

		file_put_contents( $file, 'second' );
		touch( $file, 1700000010 );
		$second = $method->invoke( null, $this->storage, $source_dir );
		$this->assertSame( 'unversioned-1700000010', $second[0]->version );
		$this->assertCount( 2, $this->storage->sections() );
		$this->assertSame( 'first', file_get_contents( $this->fetch_section( $this->storage->section( $first[0] ) ) . '/db.php' ) );
		$this->assertSame( 'second', file_get_contents( $this->fetch_section( $this->storage->section( $second[0] ) ) . '/db.php' ) );
	}

    /**
     * test throw_if_out_of_time method.
     */
    public function test_throw_if_out_of_time() {

        $method = new ReflectionMethod( '\calmpress\backup\Core_Backup_Engine', 'throw_if_out_of_time' );

        // Test exception when time passed is in the past.
        $exception = false;
        try {
            $method->invoke( null, time() - 10 );
        } catch ( \calmpress\calmpress\Timeout_Exception $e ) {
            $exception = true;
        }
        $this->AssertTrue( $exception );

        // Test no exception when time passed is in the future.
        $exception = false;
        try {
            $method->invoke( null, time() + 10 );
        } catch ( \calmpress\calmpress\Timeout_Exception $e ) {
            $exception = true;
        }
        $this->AssertFalse( $exception );

    }

	/**
	 * Invoke a protected backup engine method for testing.
	 *
	 * @since 1.0.0
	 *
	 * @param string $method Engine method to invoke.
	 * @param mixed  ...$args Arguments passed to the engine method.
	 *
	 * @return mixed The engine method result.
	 */
	private function invoke_engine( string $method, ...$args ) {
		$reflection = new ReflectionMethod( \calmpress\backup\Core_Backup_Engine::class, $method );
		return $reflection->invoke( null, ...$args );
	}

	/**
	 * Verify transient options are excluded while similarly named ordinary options are preserved.
	 *
	 * @since 1.0.0
	 */
	public function test_transient_filter_preserves_similarly_named_options() {
		\calmpress\utils\ensure_dir_exists( $this->storage_root );
		add_option( 'plugin_transient_settings', 'keep' );
		add_option( '_transient_real', 'exclude' );
		add_option( '_site_transient_real', 'exclude' );
		$site_id = get_current_blog_id();
		$identity = $this->invoke_engine( 'Backup_Site_Options', $this->storage, $site_id );
		$section_root = $this->fetch_section( $this->storage->section( $identity ) );
		$data = json_decode( file_get_contents( $section_root . '/options.json' ), true );
		$names = array_column( $data, 'n' );
		$this->assertContains( 'plugin_transient_settings', $names );
		$this->assertNotContains( '_transient_real', $names );
		$this->assertNotContains( '_site_transient_real', $names );
	}

	/**
	 * Verify configuration is stored in its own section with the original relative location.
	 *
	 * @since 1.0.0
	 */
	public function test_parent_configuration_is_backed_up_and_local_configuration_takes_precedence() {
		\calmpress\utils\ensure_dir_exists( $this->storage_root );
		mkdir( $this->storage_root . '/site' );
		file_put_contents( $this->storage_root . '/wp-config.php', 'parent configuration' );
		touch( $this->storage_root . '/wp-config.php', 1700000000 );
		$parent_identity = $this->invoke_engine( 'Backup_Config_File', $this->storage, $this->storage_root . '/site/' );
		$this->assertSame( '../wp-config.php', $parent_identity->location );
		$parent = $this->fetch_section( $this->storage->section( $parent_identity ) );
		$this->assertSame( 'parent configuration', file_get_contents( $parent . '/wp-config.php' ) );
		$this->assertSame( array(), $this->invoke_engine( 'root_files', $this->storage_root . '/site/' ) );
		file_put_contents( $this->storage_root . '/site/wp-config.php', 'local configuration' );
		touch( $this->storage_root . '/site/wp-config.php', 1700000010 );
		$local_identity = $this->invoke_engine( 'Backup_Config_File', $this->storage, $this->storage_root . '/site/' );
		$this->assertSame( 'wp-config.php', $local_identity->location );
		$local = $this->fetch_section( $this->storage->section( $local_identity ) );
		$this->assertSame( 'local configuration', file_get_contents( $local . '/wp-config.php' ) );
		$this->assertNotEquals( $parent_identity, $local_identity );
	}

	/**
	 * Verify root-file sections change when files are added, changed, or removed.
	 *
	 * @since 1.0.0
	 */
	public function test_root_file_versions_follow_file_names_and_timestamps() {
		$source_dir = $this->storage_root . '/site/';
		\calmpress\utils\ensure_dir_exists( $source_dir );
		$this->assertSame( array(), $this->invoke_engine( 'root_files', $source_dir ) );
		$this->assertCount( 0, $this->storage->sections() );

		$file = $source_dir . 'custom.php';
		file_put_contents( $file, 'first' );
		touch( $file, 1700000000 );
		$files = $this->invoke_engine( 'root_files', $source_dir );
		$first = $this->invoke_engine( 'Backup_Root', $this->storage, $files );
		$this->assertSame( array( 'custom.php' ), array_keys( $files ) );
		$this->assertNotNull( $first );
		$this->assertEquals( $first, $this->invoke_engine( 'Backup_Root', $this->storage, $files ) );

		file_put_contents( $file, 'second' );
		touch( $file, 1700000010 );
		$second = $this->invoke_engine( 'Backup_Root', $this->storage, $files );
		$this->assertNotEquals( $first, $second );
		$this->assertSame( 'first', file_get_contents( $this->fetch_section( $this->storage->section( $first ) ) . '/custom.php' ) );
		$this->assertSame( 'second', file_get_contents( $this->fetch_section( $this->storage->section( $second ) ) . '/custom.php' ) );

		unlink( $file );
		$this->assertSame( array(), $this->invoke_engine( 'root_files', $source_dir ) );
		$this->assertCount( 2, $this->storage->sections() );
	}

	/**
	 * Verify unversioned plugin backups are reused until the file timestamp changes, preserving both copies.
	 *
	 * @since 1.0.0
	 */
	public function test_unversioned_plugin_is_copied_again_after_editing() {
		\calmpress\utils\ensure_dir_exists( $this->storage_root );
		$source = $this->storage_root . '/plugin.php';
		file_put_contents( $source, 'first' );
		touch( $source, 1700000000 );
		$first = $this->invoke_engine( 'Backup_Single_File_Plugin', $this->storage, $source, '', 'Test Plugin' );
		$repeat = $this->invoke_engine( 'Backup_Single_File_Plugin', $this->storage, $source, '', 'Test Plugin' );
		$this->assertSame( 'unversioned-1700000000', $first->version );
		$this->assertEquals( $first, $repeat );
		file_put_contents( $source, 'second' );
		touch( $source, 1700000010 );
		$second = $this->invoke_engine( 'Backup_Single_File_Plugin', $this->storage, $source, '', 'Test Plugin' );
		$this->assertNotEquals( $first, $second );
		$first_root = $this->fetch_section( $this->storage->section( $first ) );
		$second_root = $this->fetch_section( $this->storage->section( $second ) );
		$this->assertSame( 'first', file_get_contents( $first_root . '/plugin.php' ) );
		$this->assertSame( 'second', file_get_contents( $second_root . '/plugin.php' ) );
	}

	/**
	 * Verify unversioned themes reuse a backup until the stylesheet timestamp changes.
	 *
	 * @since 1.0.0
	 */
	public function test_unversioned_theme_reuses_stylesheet_timestamp() {
		\calmpress\utils\ensure_dir_exists( $this->storage_root );
		$source = $this->storage_root . '/theme';
		mkdir( $source );
		file_put_contents( $source . '/style.css', '/* Theme Name: Unversioned */' );
		file_put_contents( $source . '/index.php', '<?php' );
		touch( $source . '/style.css', 1700000000 );
		$theme = new WP_Theme( 'theme', $this->storage_root );
		$first = new \calmpress\backup\Backup_Section_Identity( 'theme', 'theme', 'unversioned-1700000000' );
		$this->invoke_engine( 'Backup_Theme', $this->storage, $theme, $first );
		$this->invoke_engine( 'Backup_Theme', $this->storage, $theme, $first );
		$this->assertSame( 'Unversioned', $this->storage->section( $first )->display_name() );

		file_put_contents( $source . '/style.css', '/* Theme Name: Unversioned */ body {}' );
		touch( $source . '/style.css', 1700000010 );
		$third = new \calmpress\backup\Backup_Section_Identity( 'theme', 'theme', 'unversioned-1700000010' );
		$this->invoke_engine( 'Backup_Theme', $this->storage, $theme, $third );
		$section = $this->storage->section( $third );
		$this->assertSame( '/* Theme Name: Unversioned */ body {}', file_get_contents( trailingslashit( $this->fetch_section( $section ) ) . 'style.css' ) );
	}

	/**
	 * Verify an options query failure raises an exception without publishing an options backup.
	 *
	 * @since 1.0.0
	 */
	public function test_options_query_failure_does_not_create_backup() {
		\calmpress\utils\ensure_dir_exists( $this->storage_root );
		global $wpdb;
		$filter = static function ( $query ) {
			if ( false !== strpos( $query, 'SELECT option_name, option_value, autoload FROM' ) ) {
				return 'SELECT * FROM calmpress_backup_missing_table';
			}
			return $query;
		};
		$previous = $wpdb->suppress_errors();
		add_filter( 'query', $filter );
		$sections_before = $this->storage->sections();
		try {
			$this->invoke_engine( 'Backup_Site_Options', $this->storage, get_current_blog_id() );
			$this->fail( 'A failed query must not produce a successful backup.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'Failed reading options for backup.', $exception->getMessage() );
			$this->assertCount( count( $sections_before ), $this->storage->sections() );
		} finally {
			remove_filter( 'query', $filter );
			$wpdb->suppress_errors( $previous );
		}
	}

	/**
	 * Verify backup information shows useful section names and omits internal sections.
	 *
	 * @since 1.0.0
	 */
	public function test_data_description_uses_sections() {
		$plugin = new mock_display_backup_section(
			new \calmpress\backup\Backup_Section_Identity( 'plugin', 'sample', '2.0' ),
			time(),
			'Sample Plugin'
		);
		$config = new mock_display_backup_section(
			new \calmpress\backup\Backup_Section_Identity( 'config-file', '../wp-config.php', 'timestamp-1' ),
			time(),
			''
		);
		$root_files = new mock_display_backup_section(
			new \calmpress\backup\Backup_Section_Identity( 'root-files', 'root', 'timestamps-1' ),
			time(),
			'.htaccess, robots.txt'
		);
		$html = \calmpress\backup\Core_Backup_Engine::data_description( array( $plugin, $config, $root_files ) );

		$this->assertStringContainsString( 'Sample Plugin - version 2.0', $html );
		$this->assertStringNotContainsString( '../wp-config.php', $html );
		$this->assertStringContainsString( '.htaccess, robots.txt', $html );
		$this->assertStringNotContainsString( 'Themes', $html );
		$this->assertStringNotContainsString( 'MU plugins', $html );
		$this->assertStringNotContainsString( 'None', $html );
	}

	/**
	 * Verify an empty plugin list is explicit while other absent categories stay hidden.
	 *
	 * @since 1.0.0
	 */
	public function test_data_description_shows_none_only_for_plugins() {
		$html = \calmpress\backup\Core_Backup_Engine::data_description( array() );

		$this->assertStringContainsString( 'Plugins', $html );
		$this->assertStringContainsString( 'None', $html );
		$this->assertStringNotContainsString( 'MU plugins', $html );
		$this->assertStringNotContainsString( 'Themes', $html );
	}

	/**
	 * Verify plugin directories with two entry files show each saved name with its version.
	 *
	 * @since 1.0.0
	 */
	public function test_data_description_splits_plugin_directory_names_and_versions() {
		$directory = new mock_display_backup_section(
			new \calmpress\backup\Backup_Section_Identity( 'plugin', 'shared-directory', '1.2-beta_3.4' ),
			time(),
			'First Plugin, Second Plugin'
		);
		$single_file = new mock_display_backup_section(
			new \calmpress\backup\Backup_Section_Identity( 'plugin-file', 'standalone.php', '5.0' ),
			time(),
			'Standalone Plugin'
		);

		$html = \calmpress\backup\Core_Backup_Engine::data_description( array( $directory, $single_file ) );

		$this->assertStringContainsString( 'First Plugin - version 1.2-beta', $html );
		$this->assertStringContainsString( 'Second Plugin - version 3.4', $html );
		$this->assertStringContainsString( 'Standalone Plugin - version 5.0', $html );
		$this->assertSame( 1, substr_count( $html, '<h4>Plugins</h4>' ) );
	}
}
