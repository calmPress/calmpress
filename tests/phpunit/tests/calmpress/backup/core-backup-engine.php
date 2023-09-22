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

    var $root_dir;

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

    /**
     * Overide the Backup_Site_Options method to collect information on the site ids
     * it is called with.
     */
    protected static function Backup_Site_Options( \calmpress\backup\Temporary_Backup_Storage $storage, $site_id ) {
        $property = new ReflectionProperty( $storage, 'dest_root_path' );
        $property->setAccessible(true);

        self::$paths[ $site_id ] = $property->getValue( $storage );
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
     * instantiated. If anything but 'Version' is being passed raise an error.
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
        } else {
            trigger_error( 'Unknown type passed: ' . $type, E_USER_ERROR );
        }
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
    protected static function Backup_Directory( string $source, calmpress\backup\Temporary_Backup_Storage $staging, string $destination ) {

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
    protected static function Backup_Directory( string $source, calmpress\backup\Temporary_Backup_Storage $staging, string $destination ) {
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
        $method->setAccessible(true);

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

        $staging = $this->storage->section_working_area_storage( 'dest' );
        $method->invoke( null, $test_dir . '/source', $staging, '' );
        $staging->store();

        $this->AssertTrue( is_file( $this->storage_root . '/dest/file1' ) );
        $this->AssertEquals( filesize( __FILE__ ), filesize( $this->storage_root . '/dest/file1' ) );
        $this->AssertTrue( is_file( $this->storage_root . '/dest/file2' ) );
        $this->AssertTrue( is_dir( $this->storage_root . '/dest/subdir' ) );
        $this->AssertTrue( is_file( $this->storage_root . '/dest/subdir/file1' ) );
        $this->AssertTrue( is_file( $this->storage_root . '/dest/subdir/file2' ) );
        $this->AssertFalse( file_exists( $this->storage_root . '/dest/subdir/sym' ) );

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
        $method->setAccessible(true);

        $test_dir = get_temp_dir() . uniqid() . '/';

        // copy a file (this test file).
        mkdir( $test_dir, 0777, true );
        copy( __FILE__, $test_dir . 'wp-cron.php' );
        copy( __FILE__, $test_dir . 'wp-login.php' );
        copy( __FILE__, $test_dir . '.htaccess' );
        copy( __FILE__, $test_dir . 'none.php' );

        $method->invoke( null, $this->storage, $test_dir, '/dest' );

        // Check that files that are non core files were copied
        $this->AssertTrue( is_file( $this->storage_root . '/dest/.htaccess' ) );
        $this->AssertEquals( filesize( __FILE__ ), filesize( $this->storage_root . '/dest/.htaccess' ) );
        $this->AssertTrue( is_file( $this->storage_root . '/dest/none.php' ) );

        // ... but no other file.
        $files = new FilesystemIterator( $this->storage_root . '/dest', FilesystemIterator::SKIP_DOTS );
        $this->AssertEquals( 2, iterator_count( $files ) );

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
        $method->setAccessible(true);

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
        
        $staging = $this->storage->section_working_area_storage( 'dest' );

        $method->invoke( null, $staging, $blog_id );
        $staging->store();

        // Check file was created.
        $file = $this->storage_root . '/dest/' . $blog_id . '-options.json';
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
        $this->AssertSame( 'no', $ar['test1']['a'] );

        $this->AssertTrue( array_key_exists( 'test2', $ar ) );
        $this->AssertSame( 'value2', $ar['test2']['v'] );
        $this->AssertSame( 'yes', $ar['test2']['a'] );

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
        $method->setAccessible(true);

        $expected_blogs[] = get_current_blog_id();
        // for multisite testing we want to test that all sites are used in the call
        // to backup_site_options.
        if ( is_multisite() ) {
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

        $method->invoke( null, $this->storage, 'options' );

        // test that the directory was created.
        $this->AssertTrue( is_dir( $this->storage_root . '/options' ) );

        // test correct calls to backup_site_options for all sites.
        foreach ( $expected_blogs as $blog_id ) {
            $this->AssertTrue( array_key_exists( $blog_id, mock_backup_options::$paths ) );
            $this->AssertSame( $this->storage_root . '/options/', mock_backup_options::$paths[ $blog_id ] );
        }
        self::rm_dir( $this->storage_root . '/options' );
    }

    /**
     * Test the Backup_Theme method.
     * 
     * @since 1.0.0
     */
    function test_backup_theme() {

        $method = new ReflectionMethod( 'mock_backup_theme', 'Backup_Theme' );
        $method->setAccessible(true);

        $theme_dir = $this->storage_root . '/theme';

        $dest_dir = $this->storage_root . '/themes/';
        $theme = new mock_theme( $theme_dir, '1.0' );

        $ret = $method->invoke( null, $this->storage, 'themes', $theme );
        $this->AssertTrue( mock_backup_theme::$called );

        // The expected call has the root directory of the theme, mapped to the staging root.
        $this->AssertSame( $theme_dir, mock_backup_theme::$called_source );
        $this->AssertSame( '', mock_backup_theme::$called_dest );
        $this->AssertSame( 'theme/1.0', $ret );

        // Test directory backup is not done if directory already exists.
        mock_backup_theme::$called = false;
        $ret = $method->invoke( null, $this->storage, 'themes', $theme );
        $this->AssertFalse( mock_backup_theme::$called );
    }

    /**
     * Test the Backup_Themes method.
     * 
     * @since 1.0.0
     */
    function test_backup_themes() {

        $method = new ReflectionMethod( 'mock_backup_themes', 'Backup_Themes' );
        $method->setAccessible(true);

        $paths_method = new ReflectionMethod( 'mock_backup_themes', 'installation_paths' );
        $paths_method->setAccessible(true);

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

        $meta = $method->invoke( null, $this->storage, time() + 10 );

        // there should be only two themes.
        $this->AssertSame( 2, count( $meta ) );
        foreach ( [ 'parent', 'child' ] as $theme_dir ) {
            $this->AssertTrue( array_key_exists( $theme_dir, $meta ) );
            $this->AssertSame( 4, count( $meta[ $theme_dir ] ) );
            $this->AssertTrue( array_key_exists( 'version', $meta[ $theme_dir ] ) );
            $this->AssertTrue( array_key_exists( 'directory', $meta[ $theme_dir ] ) );
            $this->AssertTrue( array_key_exists( 'name', $meta[ $theme_dir ] ) );
            $this->AssertTrue( array_key_exists( 'directory_name', $meta[ $theme_dir ] ) );
        }
        $this->AssertSame( '1.1', $meta['parent']['version'] );
        $this->AssertSame( 'themes/parent/1.1/', $meta['parent']['directory'] );
        $this->AssertSame( '1.0', $meta['child']['version'] );
        $this->AssertSame( 'themes/child/1.0/', $meta['child']['directory'] );

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
        $method->setAccessible(true);

        $test_dir = get_temp_dir() . uniqid();

        $plugin_dir = $test_dir . '/test_plugin';
        mkdir( $plugin_dir, 0777, true );
        
        $dest_dir = $this->storage_root . '/dest/';
        $ret = $method->invoke( null, $this->storage, 'dest', $plugin_dir, '52-3.45' );

        $this->AssertTrue( mock_backup_theme::$called );
        $this->AssertSame( $plugin_dir, mock_backup_theme::$called_source );
        $this->AssertSame( '', mock_backup_theme::$called_dest );
        $this->AssertSame( 'test_plugin/52-3.45', $ret );

        // Test directory backup is not done if directory already exists.
        mock_backup_theme::$called = false;
        $ret = $method->invoke( null, $this->storage, 'dest', $plugin_dir, '52-3.45' );
        $this->AssertFalse( mock_backup_theme::$called );
        $this->AssertSame( 'test_plugin/52-3.45', $ret );
    }

    /**
     * Test the Backup_Root_Single_File_Plugin method.
     * 
     * Use the hello,php plugin for the test
     * 
     * @since 1.0.0
     */
    function test_backup_root_single_file_plugin() {

        $method = new ReflectionMethod( '\calmpress\backup\Core_Backup_Engine', 'Backup_Root_Single_File_Plugin' );
        $method->setAccessible( true );

        $dest_dir = $this->storage_root . 'dest/';
        $ret = $method->invoke( null, $this->storage, 'dest', WP_PLUGIN_DIR . '/hello.php', '2.3' );

        $this->AssertTrue( is_dir( $this->storage_root . '/dest/hello.php/2.3' ) );
        $this->AssertTrue( is_file( $this->storage_root . '/dest/hello.php/2.3/hello.php' ) );
        $this->AssertSame( filesize( WP_PLUGIN_DIR . '/hello.php' ), filesize( $this->storage_root . '/dest/hello.php/2.3/hello.php' ) );
        $this->AssertSame( 'hello.php/2.3', $ret );
    }

    /**
     * Test the Backup_Plugins method.
     * 
     * @since 1.0.0
     */
    function test_backup_plugins() {

        $method = new ReflectionMethod( 'mock_backup_theme', 'Backup_Plugins' );
        $method->setAccessible(true);

        $upload_dir = wp_upload_dir();
        $test_dir   = $upload_dir['basedir'];

        $plugin_dir = $test_dir . '/plugin';

        $dest_dir = $this->storage_root . '/dest/';
        $meta = $method->invoke( null, $this->storage, time() + 10 );

        foreach ( [ 'hello.php', 'single_plugin_directory', 'double_plugin_directory' ] as $plugin ) {
            $this->AssertTrue( array_key_exists( $plugin, $meta ) );
            $this->AssertSame( 4, count( $meta[ $plugin ] ) );
        }

        $this->AssertSame( '1.5.1', $meta['hello.php']['version'] );
        $this->AssertSame( 'plugins/hello.php/1.5.1', $meta['hello.php']['directory'] );
        $this->AssertSame( 'root_file', $meta['hello.php']['type'] );
        $this->AssertIsArray( $meta['hello.php']['data'] );
    
        $this->AssertSame( '1.0a', $meta['single_plugin_directory']['version'] );
        $this->AssertSame( 'plugins/single_plugin_directory/1.0a/', $meta['single_plugin_directory']['directory'] );
        $this->AssertSame( 'directory', $meta['single_plugin_directory']['type'] );
        $this->AssertIsArray( $meta['single_plugin_directory']['data'] );

        $this->AssertSame( '1.1b-1.2c', $meta['double_plugin_directory']['version'] );
        $this->AssertSame( 'plugins/double_plugin_directory/1.1b-1.2c/', $meta['double_plugin_directory']['directory'] );
        $this->AssertSame( 'directory', $meta['double_plugin_directory']['type'] );
        $this->AssertIsArray( $meta['double_plugin_directory']['data'] );
    }

    /**
     * Test the Backup_MU_Plugins method.
     * 
     * @since 1.0.0
     */
    function test_backup_mu_plugins() {

        $method = new ReflectionMethod( 'mock_backup_theme', 'Backup_MU_Plugins' );
        $method->setAccessible(true);

        $test_dir = get_temp_dir() . uniqid();
        mkdir( $test_dir . '/source/', 0777, true );

        // Test Backup_Directory is invoked when directory exists.
        mock_backup_theme::$called = false;
        $method->invoke( null, $this->storage, $test_dir . '/source/', 'dest' );
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
        $method->setAccessible(true);

        $test_dir = get_temp_dir() . uniqid();
        mkdir( $test_dir . '/source/', 0777, true );

        mock_backup_theme::$called = false;
        $method->invoke( null, $this->storage, $test_dir . '/source/', 'dest' );
        $this->AssertTrue( mock_backup_theme::$called );
    }

    /**
     * Test the Backup_Dropins method.
     * 
     * @since 1.0.0
     */
    function test_backup_dropins() {

        $method = new ReflectionMethod( '\calmpress\backup\Core_Backup_Engine', 'Backup_Dropins' );
        $method->setAccessible(true);

        $test_dir = get_temp_dir() . uniqid();
        mkdir( $test_dir . '/source', 0777, true );

        $paths = new \calmpress\calmpress\Paths();

        // test all dropins are backuped, but not other files.
        foreach ( $paths->dropin_files_name() as $filename ) {
            copy( __FILE__, $test_dir . '/source/' . $filename );
        }
        touch( $test_dir . '/source/test.test' );

        $method->invoke( null, $this->storage, $test_dir . '/source/', 'dest' );
        foreach ( $paths->dropin_files_name() as $filename ) {
            $this->AssertTrue( is_file( $this->storage_root . '/dest/' . $filename ) );
            $this->AssertSame( filesize( __FILE__ ), filesize( $this->storage_root . '/dest/' . $filename ) );
        }
        $this->AssertFalse( file_exists( $this->storage_root . '/dest/test.test' ) );

        // Test symlink not copied even when their name is valid dropin name.
        $this->rm_dir( $test_dir . '/source' );
        $this->rm_dir( $this->storage_root . '/dest' );
        mkdir( $test_dir . '/source', 0777, true );
        touch( $test_dir . '/source/test.test' );
        if ( ! @symlink( $test_dir . '/source/test.test', $test_dir . '/source/db.php' ) ) {
            $this->markTestIncomplete(' failed creating the symlink. On windows you will need to run the tests as administrator');
        }
        $method->invoke( null, $this->storage, $test_dir . '/source/', 'dest' );
        $files = new FilesystemIterator( $this->storage_root . '/dest', FilesystemIterator::SKIP_DOTS );
        $this->AssertEquals( 0, iterator_count( $files ) );

        $this->rm_dir( $test_dir );
    }

    /**
     * test throw_if_out_of_time method.
     */
    public function test_throw_if_out_of_time() {

        $method = new ReflectionMethod( '\calmpress\backup\Core_Backup_Engine', 'throw_if_out_of_time' );
        $method->setAccessible(true);

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
}
