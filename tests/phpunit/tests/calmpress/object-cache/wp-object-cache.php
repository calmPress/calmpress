<?php
/**
 * Tests installation isolation in WP_Object_Cache.
 *
 * @package calmPress
 * @since 1.0.0
 */

use calmpress\object_cache\File;

/**
 * Covers persistent cache access during installation.
 *
 * @since 1.0.0
 * @group cache
 */
class Tests_WP_Object_Cache_Installation extends PHPUnit\Framework\TestCase {

	/**
	 * Cache factories ignore earlier installations and keep new entries in memory.
	 *
	 * @since 1.0.0
	 * @dataProvider data_cache_factories
	 *
	 * @param string $factory Cache factory method to exercise.
	 */
	public function test_cache_factories_isolate_installation_entries( string $factory ): void {
		$namespace = uniqid( 'installation-test-', true );
		$persisted = new File( $namespace );
		$persisted->set( 'key', 'previous-site', 3600 );
		$was_installing = wp_installing( true );

		try {
			$method = new ReflectionMethod( WP_Object_Cache::class, $factory );
			$cache = $method->invoke( null, $namespace );

			$this->assertNull( $cache->get( 'key' ) );
			$this->assertTrue( $cache->set( 'key', 'new-site' ) );
			$this->assertSame( 'new-site', $cache->get( 'key' ) );
			$this->assertSame( 'previous-site', $persisted->get( 'key' ) );
		} finally {
			wp_installing( $was_installing );
			$persisted->clear();
			rmdir( File::CACHE_ROOT_DIR . $namespace );
		}
	}

	/**
	 * Supplies the cache factories used for persistent groups.
	 *
	 * @since 1.0.0
	 *
	 * @return array Cache factory methods.
	 */
	public static function data_cache_factories(): array {
		return [
			'options and users' => [ 'create_cache_for_static_groups' ],
			'transients'        => [ 'create_cache_for_transient_groups' ],
			'default groups'    => [ 'create_default_cache' ],
		];
	}

	/**
	 * Transient caches continue reading and writing persistent entries outside installation.
	 *
	 * @since 1.0.0
	 */
	public function test_create_cache_for_transient_groups_persists_outside_installation(): void {
		$namespace = uniqid( 'installation-test-', true );
		$persisted = new File( $namespace );
		$persisted->set( 'key', 'existing-site', 3600 );
		$was_installing = wp_installing( false );

		try {
			$method = new ReflectionMethod( WP_Object_Cache::class, 'create_cache_for_transient_groups' );
			$cache = $method->invoke( null, $namespace );

			$this->assertSame( 'existing-site', $cache->get( 'key' ) );
			$this->assertTrue( $cache->set( 'key', 'updated-site', 3600 ) );
			$this->assertSame( 'updated-site', $persisted->get( 'key' ) );
		} finally {
			wp_installing( $was_installing );
			$persisted->clear();
			rmdir( File::CACHE_ROOT_DIR . $namespace );
		}
	}
	/**
	 * Installation flushes remove persisted groups even when they have not been read.
	 *
	 * @since 1.0.0
	 */
	public function test_flush_clears_persisted_groups_not_read_during_installation(): void {
		$namespace = uniqid( 'installation-test-', true );
		$persisted = new File( $namespace );
		$persisted->set( 'key', 'previous-site', 3600 );
		$was_installing = wp_installing( true );

		try {
			$cache = new WP_Object_Cache();
			$this->assertTrue( $cache->flush() );
			$this->assertNull( $persisted->get( 'key' ) );
		} finally {
			wp_installing( $was_installing );
			$persisted->clear();
			rmdir( File::CACHE_ROOT_DIR . $namespace );
		}
	}

}
