<?php
/**
 * Tests shared installation utilities.
 *
 * @package calmPress
 * @since calmPress 1.0.0
 */

use calmpress\filesystem\Filesystem_Access;

require_once ABSPATH . 'wp-admin/includes/install-utilities.php';

/**
 * Covers installer cookies and configuration file writing.
 *
 * @since calmPress 1.0.0
 */
class Tests_Installation_Utilities extends PHPUnit\Framework\TestCase {

	/**
	 * Directory containing the isolated file fixtures.
	 *
	 * @since calmPress 1.0.0
	 * @var string
	 */
	private string $root;

	/**
	 * Configuration contents reflect the selected standalone or network layout.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @dataProvider installation_layouts
	 *
	 * @param string $type             Installation type.
	 * @param string $path             Installation path.
	 * @param string $expected_setting Expected PHP configuration statement.
	 * @param string $expected_rule    Expected rewrite rule.
	 */
	public function test_installation_configuration_matches_selected_layout( string $type, string $path, string $expected_setting, string $expected_rule ): void {
		$config   = installation_network_settings( $type, 'example.test', $path );
		$htaccess = installation_htaccess_contents( $type, $path );

		$this->assertStringContainsString( $expected_setting, $config );
		$this->assertStringContainsString( $expected_rule, $htaccess );
	}

	/**
	 * Supply the supported layouts and their configuration requirements.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return array Configuration cases for standalone and both network types.
	 */
	public static function installation_layouts(): array {
		return [
			'standalone'    => [ 'standalone', '/calm/', '// None.', 'RewriteRule . /calm/index.php [L]' ],
			'subdirectory'  => [ 'network_subdirectory', '/calm/', "define( 'SUBDOMAIN_INSTALL', false );", 'RewriteRule ^[_0-9a-zA-Z-]+/(.*\\.php)$ /calm/$1 [L]' ],
			'subdomain'     => [ 'network_subdomain', '/', "define( 'SUBDOMAIN_INSTALL', true );", 'RewriteRule . index.php [L]' ],
		];
	}

	/**
	 * Credential cookies are restricted to HTTPS requests under the installation's admin path.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_installation_cookie_options_restrict_scope_and_javascript_access(): void {
		$server = $_SERVER;
		$_SERVER['HTTPS'] = 'on';
		$_SERVER['HTTP_HOST'] = 'example.test';
		$_SERVER['REQUEST_URI'] = '/site/wp-admin/setup-config.php';
		$_SERVER['SCRIPT_FILENAME'] = ABSPATH . 'wp-admin/setup-config.php';

		try {
			$options = installation_cookie_options( 123 );

			$this->assertSame( '/site/wp-admin/', $options['path'] );
			$this->assertTrue( $options['secure'] );
			$this->assertTrue( $options['httponly'] );
			$this->assertSame( 'Strict', $options['samesite'] );
			$this->assertSame( 123, $options['expires'] );
		} finally {
			$_SERVER = $server;
		}
	}

	/**
	 * Create an isolated directory for file-writing fixtures.
	 *
	 * @since calmPress 1.0.0
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->root = sys_get_temp_dir() . '/calmpress-install-file-' . wp_generate_uuid4();
		mkdir( $this->root, 0700 );
	}

	/**
	 * Remove the current test's file fixtures.
	 *
	 * @since calmPress 1.0.0
	 */
	protected function tearDown(): void {
		calmpress\utils\delete_directory( $this->root );
		parent::tearDown();
	}

	/**
	 * New and existing installation files receive the supplied contents and explicit permissions.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_write_installation_file_creates_and_replaces_files(): void {
		foreach ( [ 'wp-config.php', '.htaccess' ] as $name ) {
			$file   = $this->root . '/' . $name;
			$access = $this->local_access();

			if ( 'wp-config.php' === $name ) {
				file_put_contents( $file, 'original' );
			}

			$result = write_installation_file( $access, $file, 'updated' );

			$this->assertFalse( $result->has_errors() );
			$this->assertSame( 'updated', file_get_contents( $file ) );
			$this->assertSame( 0644, fileperms( $file ) & 0777 );
		}

		$this->assertSame( [], glob( $this->root . '/.calmpress-install-*' ) );
	}

	/**
	 * A failed temporary write returns an installation file error.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_write_installation_file_reports_write_failure(): void {
		$file   = $this->root . '/wp-config.php';
		$access = $this->createMock( Filesystem_Access::class );
		file_put_contents( $file, 'original' );
		$access->method( 'stream_url_from_path' )->willReturnCallback(
			static function ( string $path ) use ( $file ): string {
				if ( $path === $file ) {
					return $path;
				}

				return dirname( $file ) . '/missing/' . basename( $path );
			}
		);

		$result = write_installation_file( $access, $file, 'updated' );

		$this->assertSame( 'installation_file_write_failed', $result->get_error_code() );
	}

	/**
	 * Failed permission changes return an installation file error.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_write_installation_file_reports_permissions_failure(): void {
		$file   = $this->root . '/wp-config.php';
		$access = $this->local_access( false );
		file_put_contents( $file, 'original' );

		$result = write_installation_file( $access, $file, 'updated' );

		$this->assertSame( 'installation_file_write_failed', $result->get_error_code() );
	}

	/**
	 * Failed replacement returns an installation file error.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_write_installation_file_reports_replacement_failure(): void {
		$file = $this->root . '/destination';
		mkdir( $file );

		$result = write_installation_file( $this->local_access(), $file, 'updated' );

		$this->assertSame( 'installation_file_write_failed', $result->get_error_code() );
	}

	/**
	 * Filesystem exception URLs omit the username and password from the reported error.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_write_installation_file_reports_sanitized_exception_details(): void {
		$access = $this->createMock( Filesystem_Access::class );
		$access->method( 'stream_url_from_path' )->willThrowException(
			new RuntimeException( 'Cannot access ftp://account:secret%40password@example.test/config.php' )
		);

		$result = write_installation_file( $access, $this->root . '/wp-config.php', 'updated' );

		$this->assertSame( 'installation_file_write_failed', $result->get_error_code() );
		// Credentials must be absent from the entire error, while the server and path remain in its URL.
		$this->assertStringNotContainsString( 'account', $result->get_error_message() );
		$this->assertStringNotContainsString( 'secret%40password', $result->get_error_message() );
		preg_match( '~ftp://\S+~', $result->get_error_message(), $matches );
		$this->assertSame( 'ftp://example.test/config.php', $matches[0] ?? '' );
	}

	/**
	 * Provide filesystem access using actual local stream operations in the fixture directory.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param bool $allow_permissions Whether permission changes succeed.
	 *
	 * @return Filesystem_Access Access to the local test files.
	 */
	private function local_access( bool $allow_permissions = true ): Filesystem_Access {
		$access = $this->createMock( Filesystem_Access::class );
		$access->method( 'stream_url_from_path' )->willReturnArgument( 0 );
		$access->method( 'change_permissions' )->willReturnCallback(
			static function ( string $path, int $permissions ) use ( $allow_permissions ): bool {
				return $allow_permissions && chmod( $path, $permissions );
			}
		);

		return $access;
	}
}
