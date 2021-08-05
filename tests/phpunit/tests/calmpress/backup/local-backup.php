<?php
/**
 * Unit tests covering Local_Backup functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

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
 * Mock the paths object for local backup tests to be able to override the paths.
 * 
 * @since 1.0.0
 */
class mock_local_backup extends \calmpress\backup\Local_Backup {

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

}

/**
 * Mock the Local_Backup's Backup_Site_Options method to be able to test the
 * Backup_Options method.
 * 
 * @since 1.0.0
 */
class mock_backup_options extends \calmpress\backup\Local_Backup {
	public static $paths;

	/**
	 * Overide the Backup_Site_Options method to collect information on the site ids
	 * it is called with.
	 */
	protected static function Backup_Site_Options( string $directory, $site_id ) {
		self::$paths[ $site_id ] = $directory;
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
 * Mock the Local_Backup's Backup_Directory method to be able to test the
 * Backup_Theme method.
 * 
 * @since 1.0.0
 */
class mock_backup_theme extends \calmpress\backup\Local_Backup {
	
	public static $called;

	/**
	 * Overide the Backup_Directory method to skip having files being copied.
	 *
	 * The mocked version just creates the directory.
	 *
	 * @since 1.0.0
	 */
	protected static function Backup_Directory( string $source, string $destination ) {
		static::$called = true;

		// To complete the mocking, create the directory.
		mkdir( $destination, 0755, true );
	}
}

/**
 * Mock the Local_Backup's Backup_Directory method to be able to test the
 * Backup_Themes method faster and change paths to testable ones.
 * 
 * @since 1.0.0
 */
class mock_backup_themes extends \calmpress\backup\Local_Backup {
	
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
	 * The mocked version just creates the directory.
	 *
	 * @since 1.0.0
	 */
	protected static function Backup_Directory( string $source, string $destination ) {
		mkdir( $destination, 0755, true );
	}
}

/**
 * Test cases to test the Local_Backup class.
 *
 * @since 1.0.0
 */
class Local_Backup_Test extends WP_UnitTestCase {

	/**
	 * Remove directory and its file "recuresively".
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $dir The directory to remove.
	 */
	function rmdir( $dir ) {
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
				@unlink( $fileinfo->getRealPath() );
			} else {
				unlink( $fileinfo->getRealPath() );
			}
		}
		
		rmdir($dir);
	}

    /**
     * Test the mkdir method.
     * 
     * @since 1.0.0
     */
    function test_mkdir() {

		$upload_dir = wp_upload_dir();
		$test_dir = $upload_dir['basedir'];

        $method = new ReflectionMethod( '\calmpress\backup\Local_Backup', 'mkdir' );
        $method->setAccessible(true);

        // create a directory under an existing one.
        $this->rmdir( $test_dir . '/test1' );
        $method->invoke( null, $test_dir . '/test1' );
        $this->AssertTrue( is_dir( $test_dir . '/test1' ) );

        // create a directory heirarchy.
        $this->rmdir( $test_dir . '/test2' );
        $method->invoke( null, $test_dir . '/test2/test1' );
        $this->AssertTrue( is_dir( $test_dir . '/test2/test1' ) );
        $this->rmdir( $test_dir . '/test2' );

        // Test exception when directory already exists.
        $exception = false;
		try {
        	$method->invoke( null, $test_dir . '/test1' );
		} catch ( \Exception $e ) {
			$exception = true;
		}
        $this->AssertTrue( $exception );

		$this->rmdir( $test_dir . '/test1' );
    }

    /**
     * Test the copy method.
     * 
     * @since 1.0.0
     */
    function test_copy() {

        $upload_dir = wp_upload_dir();
        $test_dir = $upload_dir['basedir'];

        $method = new ReflectionMethod( '\calmpress\backup\Local_Backup', 'copy' );
        $method->setAccessible(true);

        // copy a file (this test file).
        @unlink( $test_dir . '/test.file' );
        $method->invoke( null, __FILE__, $test_dir . '/test.file' );
        $this->AssertTrue( is_file( $test_dir . '/test.file' ) );
		$this->AssertSame( filesize( __FILE__ ), filesize( $test_dir . '/test.file' ) );
        @unlink( $test_dir . '/test.file' );

        // Test exception when directory do not exist.
        $this->expectException( '\Exception' );
        $method->invoke( null, __FILE__, $test_dir . '/no/test.file' );
    }

    /**
     * Test the Backup_Directory.
     * 
     * @since 1.0.0
     */
    function test_backup_directory() {

        $upload_dir = wp_upload_dir();
        $test_dir = $upload_dir['basedir'];

        $method = new ReflectionMethod( '\calmpress\backup\Local_Backup', 'Backup_Directory' );
        $method->setAccessible(true);

		// copy a file (this test file).
		$this->rmdir( $test_dir . '/source' );
		mkdir( $test_dir . '/source' );
		copy( __FILE__, $test_dir . '/source/file1' );
		copy( __FILE__, $test_dir . '/source/file2' );
		mkdir( $test_dir . '/source/subdir' );
		copy( __FILE__, $test_dir . '/source/subdir/file1' );
		copy( __FILE__, $test_dir . '/source/subdir/file2' );
		if ( ! @symlink( $test_dir . '/source/file1', $test_dir . '/source/subdir/sym' ) ) {
			$this->markTestIncomplete(' failed creating the symlink. On windows you will need to run the tests as administrator');
		}
        $this->rmdir( $test_dir . '/dest' );

        $method->invoke( null, $test_dir . '/source', $test_dir . '/dest' );
        $this->AssertTrue( is_file( $test_dir . '/dest/file1' ) );
		$this->AssertEquals( filesize( __FILE__ ), filesize( $test_dir . '/dest/file1' ) );
        $this->AssertTrue( is_file( $test_dir . '/dest/file2' ) );
		$this->AssertTrue( is_dir( $test_dir . '/dest/subdir' ) );
        $this->AssertTrue( is_file( $test_dir . '/dest/subdir/file1' ) );
        $this->AssertTrue( is_file( $test_dir . '/dest/subdir/file2' ) );
		$this->AssertFalse( file_exists( $test_dir . '/dest/subdir/sym' ) );

		// windows sucks in deleting symlinks.
		unlink($test_dir . '/source/file1');
		unlink($test_dir . '/source/subdir/sym');

		$this->rmdir( $test_dir . '/source' );
		$this->rmdir( $test_dir . '/dest' );
	}

	/**
     * Test the Backup_Root method.
	 * 
	 * Test the logic with sample files.
     * 
     * @since 1.0.0
     */
    function test_backup_root() {

        $paths       = new mock_paths();
		$content_dir = $paths->root_directory();
		
        $method = new ReflectionMethod( 'mock_local_backup', 'Backup_Root' );
        $method->setAccessible(true);

        $upload_dir = wp_upload_dir();
        $test_dir = $upload_dir['basedir'];

		// copy a file (this test file).
		$this->rmdir( $content_dir );
		mkdir( $content_dir, 0777, true );
		copy( __FILE__, $content_dir . 'wp-cron.php' );
		copy( __FILE__, $content_dir . 'wp-login.php' );
		copy( __FILE__, $content_dir . '.htaccess' );
		copy( __FILE__, $content_dir . 'none.php' );

		$this->rmdir( $test_dir . '/dest' );
        $method->invoke( null, $test_dir . '/dest/' );

		// Check that files that are non core files were copied
        $this->AssertTrue( is_file( $test_dir . '/dest/.htaccess' ) );
		$this->AssertEquals( filesize( __FILE__ ), filesize( $test_dir . '/dest/.htaccess' ) );
        $this->AssertTrue( is_file( $test_dir . '/dest/none.php' ) );

		// ... but no other file.
		$files = new FilesystemIterator( $test_dir . '/dest', FilesystemIterator::SKIP_DOTS );
		$this->AssertEquals( 2, iterator_count( $files ) );

		$this->rmdir( $content_dir );
		$this->rmdir( $test_dir . '/dest' );
	}

	/**
     * Test the Backup_Site_Options method.
	 * 
	 * Test the logic with sample options.
     * 
     * @since 1.0.0
     */
    function test_backup_site_options() {

        $method = new ReflectionMethod( 'mock_local_backup', 'Backup_Site_Options' );
        $method->setAccessible(true);

		$upload_dir = wp_upload_dir();
        $test_dir = $upload_dir['basedir'];

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
		
		$this->rmdir( $test_dir . '/dest' );
		mkdir( $test_dir . '/dest/' );
        $method->invoke( null, $test_dir . '/dest/', $blog_id );

		// Check file was created.
		$file = $test_dir . '/dest/' . $blog_id . '-options.json';
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

		$this->rmdir( $test_dir . '/dest' );
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

		$upload_dir = wp_upload_dir();
        $test_dir = $upload_dir['basedir'];

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

		$this->rmdir( $test_dir . '/options' );
		$method->invoke( null, $test_dir . '/options' );

		// test that the directory was created.
		$this->AssertTrue( is_dir( $test_dir . '/options' ) );

		// test correct calls to backup_site_options for all sites.
		foreach ( $expected_blogs as $blog_id ) {
			$this->AssertTrue( array_key_exists( $blog_id, mock_backup_options::$paths ) );
			$this->AssertSame( $test_dir . '/options', mock_backup_options::$paths[ $blog_id ] );
		}
		$this->rmdir( $test_dir . '/options' );
	}

	/**
     * Test the Backup_Theme method.
     * 
     * @since 1.0.0
     */
    function test_backup_theme() {

        $method = new ReflectionMethod( 'mock_backup_theme', 'Backup_Theme' );
        $method->setAccessible(true);

		$upload_dir = wp_upload_dir();
        $test_dir   = $upload_dir['basedir'];

		$theme_dir = $test_dir . '/theme';

		$dest_dir = $test_dir . '/dest/';
		$this->rmdir( $dest_dir );
		mkdir( $dest_dir );
		$theme = new mock_theme( $theme_dir, '1.0' );
		$ret = $method->invoke( null, $dest_dir, $theme );

		$this->AssertTrue( is_dir( $dest_dir . 'theme/1.0' ) );
		$this->AssertSame( 'theme/1.0', $ret );

		// Test directory backup is not done if directory already exists.
		mock_backup_theme::$called = false;
		$ret = $method->invoke( null, $dest_dir, $theme );
		$this->AssertFalse( mock_backup_theme::$called );
		$this->rmdir( $dest_dir );
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
		$this->rmdir( $source_dir );
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

		$meta = $method->invoke( null, $test_dir . '/themes/' );

		// there should be only two themes.
		$this->AssertSame( 2, count( $meta ) );
		foreach ( [ 'parent', 'child' ] as $theme_dir ) {
			$this->AssertTrue( array_key_exists( $theme_dir, $meta ) );
			$this->AssertSame( 2, count( $meta[ $theme_dir ] ) );
			$this->AssertTrue( array_key_exists( 'version', $meta[ $theme_dir ] ) );
			$this->AssertTrue( array_key_exists( 'directory', $meta[ $theme_dir ] ) );
		}
		$this->AssertSame( '1.1', $meta['parent']['version'] );
		$this->AssertSame( 'themes/parent/1.1', $meta['parent']['directory'] );
		$this->AssertSame( '1.0', $meta['child']['version'] );
		$this->AssertSame( 'themes/child/1.0', $meta['child']['directory'] );

		$this->rmdir( $paths->root_directory() );
		$this->rmdir( $test_dir . '/themes/' );
	}

	/**
     * Test the Backup_Plugin_Directory method.
     * 
     * @since 1.0.0
     */
    function test_backup_plugin_directory() {

        $method = new ReflectionMethod( 'mock_backup_theme', 'Backup_Plugin_Directory' );
        $method->setAccessible(true);

		$upload_dir = wp_upload_dir();
        $test_dir   = $upload_dir['basedir'];

		$plugin_dir = $test_dir . '/plugin';

		$dest_dir = $test_dir . '/dest/';
		$this->rmdir( $dest_dir );
		mkdir( $dest_dir );
		$ret = $method->invoke( null, $dest_dir, 'test_plugin', '52-3.45' );

		$this->AssertTrue( is_dir( $dest_dir . 'test_plugin/52-3.45' ) );
		$this->AssertSame( 'test_plugin/52-3.45', $ret );

		// Test directory backup is not done if directory already exists.
		mock_backup_theme::$called = false;
		$ret = $method->invoke( null, $dest_dir, 'test_plugin', '52-3.45' );
		$this->AssertFalse( mock_backup_theme::$called );
		$this->rmdir( $dest_dir );
	}

	/**
     * Test the Backup_Root_Single_File_Plugin method.
	 * 
	 * Use the hello,php plugin for the test
     * 
     * @since 1.0.0
     */
    function test_backup_root_single_file_plugin() {

        $method = new ReflectionMethod( '\calmpress\backup\Local_Backup', 'Backup_Root_Single_File_Plugin' );
        $method->setAccessible(true);

		$upload_dir = wp_upload_dir();
        $test_dir   = $upload_dir['basedir'];

		$dest_dir = $test_dir . '/dest/';
		$this->rmdir( $dest_dir );
		mkdir( $dest_dir );
		$ret = $method->invoke( null, $dest_dir, WP_PLUGIN_DIR . '/hello.php', '2.3' );

		$this->AssertTrue( is_dir( $dest_dir . 'hello.php/2.3' ) );
		$this->AssertTrue( is_file( $dest_dir . 'hello.php/2.3/hello.php' ) );
		$this->AssertSame( filesize( WP_PLUGIN_DIR . '/hello.php' ), filesize( $dest_dir . 'hello.php/2.3/hello.php' ) );
		$this->AssertSame( 'hello.php/2.3', $ret );

		$this->rmdir( $dest_dir );
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

		$dest_dir = $test_dir . '/dest/';
		$this->rmdir( $dest_dir );
		mkdir( $dest_dir );
		$meta = $method->invoke( null, $dest_dir );

		foreach ( [ 'hello.php', 'single_plugin_directory', 'double_plugin_directory' ] as $plugin ) {
			$this->AssertTrue( array_key_exists( $plugin, $meta ) );
			$this->AssertSame( 3, count( $meta[ $plugin ] ) );
		}
		$this->AssertSame( '1.5.1', $meta['hello.php']['version'] );
		$this->AssertSame( 'plugins/hello.php/1.5.1', $meta['hello.php']['directory'] );
		$this->AssertSame( 'root_file', $meta['hello.php']['type'] );

		$this->AssertSame( '1.0a', $meta['single_plugin_directory']['version'] );
		$this->AssertSame( 'plugins/single_plugin_directory/1.0a', $meta['single_plugin_directory']['directory'] );
		$this->AssertSame( 'directory', $meta['single_plugin_directory']['type'] );

		$this->AssertSame( '1.1b-1.2c', $meta['double_plugin_directory']['version'] );
		$this->AssertSame( 'plugins/double_plugin_directory/1.1b-1.2c', $meta['double_plugin_directory']['directory'] );
		$this->AssertSame( 'directory', $meta['double_plugin_directory']['type'] );

		$this->rmdir( $dest_dir );
	}

	/**
     * Test the Backup_MU_Plugins method.
     * 
     * @since 1.0.0
     */
    function test_backup_mu_plugins() {

        $method = new ReflectionMethod( 'mock_backup_theme', 'Backup_MU_Plugins' );
        $method->setAccessible(true);

        $upload_dir = wp_upload_dir();
        $test_dir = $upload_dir['basedir'];

		// Test non existing mu-plugins directory creates a backup directory.
		$this->rmdir( $test_dir . '/source/' );
		$this->rmdir( $test_dir . '/dest' );
        $method->invoke( null, $test_dir . '/source/', $test_dir . '/dest/' );

        $this->AssertTrue( file_exists( $test_dir . '/dest/' ) );
        $this->AssertTrue( is_dir( $test_dir . '/dest/' ) );

		// Test Backup_Directory is invoked when directory exists.
		$this->rmdir( $test_dir . '/dest' );
		mkdir( $test_dir . '/source/', 0777, true );
		mock_backup_theme::$called = false;
		$method->invoke( null, $test_dir . '/source/', $test_dir . '/dest/' );
		$this->AssertTrue( mock_backup_theme::$called );
        $this->AssertTrue( is_dir( $test_dir . '/dest/' ) );

		$this->rmdir( $test_dir . '/source/' );
		$this->rmdir( $test_dir . '/dest' );
	}

	/**
     * Test the Backup_Languages method.
     * 
     * @since 1.0.0
     */
    function test_backup_languages() {

        $method = new ReflectionMethod( 'mock_backup_theme', 'Backup_Languages' );
        $method->setAccessible(true);

        $upload_dir = wp_upload_dir();
        $test_dir = $upload_dir['basedir'];

		// Test non existing languages directory creates a backup directory.
		$this->rmdir( $test_dir . '/source/' );
		$this->rmdir( $test_dir . '/dest' );
        $method->invoke( null, $test_dir . '/source/', $test_dir . '/dest/' );

        $this->AssertTrue( file_exists( $test_dir . '/dest/' ) );
        $this->AssertTrue( is_dir( $test_dir . '/dest/' ) );

		// Test Backup_Directory is invoked when directory exists.
		$this->rmdir( $test_dir . '/dest' );
		mkdir( $test_dir . '/source/', 0777, true );
		mock_backup_theme::$called = false;
		$method->invoke( null, $test_dir . '/source/', $test_dir . '/dest/' );
		$this->AssertTrue( mock_backup_theme::$called );
        $this->AssertTrue( is_dir( $test_dir . '/dest/' ) );

		$this->rmdir( $test_dir . '/source/' );
		$this->rmdir( $test_dir . '/dest' );
	}

	/**
     * Test the Backup_Dropins method.
     * 
     * @since 1.0.0
     */
    function test_backup_dropins() {

        $method = new ReflectionMethod( '\calmpress\backup\Local_Backup', 'Backup_Dropins' );
        $method->setAccessible(true);

        $upload_dir = wp_upload_dir();
        $test_dir = $upload_dir['basedir'];

		$paths = new \calmpress\calmpress\Paths();

		// copy a file (this test file).
		$this->rmdir( $test_dir . '/source' );
		$this->rmdir( $test_dir . '/dest' );
		mkdir( $test_dir . '/source' );

		// test all dropins are backuped, but not other files.
		foreach ( $paths->dropin_files_name() as $filename ) {
			copy( __FILE__, $test_dir . '/source/' . $filename );
		}
		touch( $test_dir . '/source/test.test' );
		$method->invoke( null, $test_dir . '/source/', $test_dir . '/dest/' );
		foreach ( $paths->dropin_files_name() as $filename ) {
			$this->AssertTrue( is_file( $test_dir . '/dest/' . $filename ) );
			$this->AssertSame( filesize( __FILE__ ), filesize( $test_dir . '/dest/' . $filename ) );
		}
		$this->AssertFalse( file_exists( $test_dir . '/dest/test.test' ) );

		// Test symlink not copied even when their name is valid dropin name.
		$this->rmdir( $test_dir . '/source' );
		$this->rmdir( $test_dir . '/dest' );
		mkdir( $test_dir . '/source' );
		touch( $test_dir . '/source/test.test' );
		if ( ! @symlink( $test_dir . '/source/test.test', $test_dir . '/source/db.php' ) ) {
			$this->markTestIncomplete(' failed creating the symlink. On windows you will need to run the tests as administrator');
		}
		$method->invoke( null, $test_dir . '/source/', $test_dir . '/dest/' );
		$files = new FilesystemIterator( $test_dir . '/dest', FilesystemIterator::SKIP_DOTS );
		$this->AssertEquals( 0, iterator_count( $files ) );

		// windows sucks in deleting symlinks.
		unlink( $test_dir . '/source/test.test' );
		unlink( $test_dir . '/source/db.php' );

		$this->rmdir( $test_dir . '/source' );
		$this->rmdir( $test_dir . '/dest' );
	}

}
