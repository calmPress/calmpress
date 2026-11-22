<?php
/**
 * Tests for shared local path validation.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\filesystem\Local_Path_Validation;

/**
 * Installation path validation tests.
 *
 * @since 1.0.0
 */
class WP_Test_Local_Path_Validation extends WP_UnitTestCase {

	use Local_Path_Validation;

	/**
	 * Installation paths and configured content paths are accepted before files exist.
	 *
	 * @since 1.0.0
	 */
	public function test_throw_if_not_absolute_local_path_accepts_local_paths(): void {
		$this->throw_if_not_absolute_local_path( rtrim( ABSPATH, '/' ) );
		$this->throw_if_not_absolute_local_path( ABSPATH . 'wp-settings.php' );
		$this->throw_if_not_absolute_local_path( ABSPATH . 'missing-directory/missing-file.php' );
		$this->throw_if_not_absolute_local_path( WP_CONTENT_DIR . '/missing-file.php' );
		$this->addToAssertionCount( 4 );
	}

	/**
	 * The parent directory's configuration file is an allowed installation path.
	 *
	 * @since 1.0.0
	 */
	public function test_throw_if_not_absolute_local_path_accepts_parent_configuration(): void {
		$this->throw_if_not_absolute_local_path( dirname( rtrim( ABSPATH, '/' ) ) . '/wp-config.php' );
		$this->addToAssertionCount( 1 );
	}

	/**
	 * Paths outside the installation, parent traversal, relative paths, and stream URLs are rejected with a domain exception.
	 *
	 * @since 1.0.0
	 *
	 * @dataProvider invalid_absolute_local_paths
	 *
	 * @param string $path Invalid local path.
	 */
	public function test_throw_if_not_absolute_local_path_rejects_invalid_paths( string $path ): void {
		$this->expectException( \DomainException::class );
		$this->throw_if_not_absolute_local_path( $path );
	}

	/**
	 * Supply paths outside the installation scope and invalid local paths.
	 *
	 * @since 1.0.0
	 *
	 * @return array[] Invalid local paths.
	 */
	public static function invalid_absolute_local_paths(): array {
		return [
			'empty' => [ '' ],
			'relative' => [ 'wp-config.php' ],
			'parent relative' => [ '../wp-config.php' ],
			'local stream URL' => [ 'file:///tmp/wp-config.php' ],
			'remote stream URL' => [ 'ftp://example.test/wp-config.php' ],
			'existing PHP stream' => [ 'php://memory' ],
			'parent directory' => [ dirname( rtrim( ABSPATH, '/' ) ) ],
			'other parent file' => [ dirname( rtrim( ABSPATH, '/' ) ) . '/other.php' ],
			'sibling directory' => [ rtrim( ABSPATH, '/' ) . '-other/file.php' ],
			'parent traversal' => [ ABSPATH . '../other.php' ],
			'nested traversal' => [ ABSPATH . 'wp-admin/../../other.php' ],
			'configuration child' => [ dirname( rtrim( ABSPATH, '/' ) ) . '/wp-config.php/other.php' ],
		];
	}
}
