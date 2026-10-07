<?php
/**
 * Tests for the backup manager API.
 *
 * @package calmPress
 * @since 1.0.0
 */

/**
 * Minimal backup engine used to verify manager metadata.
 *
 * @since 1.0.0
 */
class Backup_Manager_Test_Engine extends \calmpress\backup\Core_Backup_Engine {
	/**
	 * Return no sections for the metadata test.
	 *
	 * @since 1.0.0
	 *
	 * @param \calmpress\backup\Backup_Storage $storage Storage used by the backup.
	 * @param int                                $max_time Maximum backup time.
	 *
	 * @return array No sections.
	 */
	public static function backup( \calmpress\backup\Backup_Storage $storage, int $max_time ): array {
		return array();
	}

	/**
	 * Provide the description to save with a backup.
	 *
	 * @since 1.0.0
	 *
	 * @return string Engine description.
	 */
	public static function description(): string {
		return 'Example engine';
	}

	/**
	 * Identify the test engine.
	 *
	 * @since 1.0.0
	 *
	 * @return string Engine identifier.
	 */
	public static function identifier(): string {
		return 'example_engine';
	}
}

/**
 * Test backup creation through the manager.
 *
 * @since 1.0.0
 */
class Backup_Manager_Test extends WP_UnitTestCase {
	/**
	 * Verify a registered initialization observer receives the new manager.
	 *
	 * @since 1.0.0
	 */
	public function test_initialization_observer_receives_manager() {
		$root = get_temp_dir() . 'backup-manager-' . wp_generate_uuid4();
		$observer = new class implements \calmpress\backup\Backup_Manager_Initialization_Observer {
			/**
			 * Manager received by the observer.
			 *
			 * @since 1.0.0
			 */
			public ?\calmpress\backup\Backup_Manager $called_with = null;

			/**
			 * Record the manager passed to the observer.
			 *
			 * @since 1.0.0
			 *
			 * @param \calmpress\backup\Backup_Manager $manager New manager.
			 *
			 * @return void
			 */
			public function register_with( \calmpress\backup\Backup_Manager $manager ): void {
				$this->called_with = $manager;
			}

			/**
			 * Give this observer no ordering dependency.
			 *
			 * @since 1.0.0
			 *
			 * @param \calmpress\observer\Observer $observer Another observer.
			 *
			 * @return \calmpress\observer\Observer_Priority No dependency.
			 */
			public function notification_dependency_with( \calmpress\observer\Observer $observer ): \calmpress\observer\Observer_Priority {
				return \calmpress\observer\Observer_Priority::NONE;
			}
		};
		\calmpress\backup\Backup_Manager::add_initialization_observer( $observer );
		try {
			$manager = new \calmpress\backup\Backup_Manager( $root . '/backups-meta' );

			$this->assertSame( $manager, $observer->called_with );
		} finally {
			\calmpress\backup\Backup_Manager::remove_initialization_observer( $observer );
			\calmpress\utils\delete_directory( $root );
		}
	}

	/**
	 * Verify backup creation writes the identity, time, description, and engine map.
	 *
	 * @since 1.0.0
	 */
	public function test_create_backup_writes_metadata() {
		$root = get_temp_dir() . 'backup-manager-' . wp_generate_uuid4();
		$storage = new \calmpress\backup\Local_Backup_Storage( $root, 'test_storage' );
		try {
			$manager = new \calmpress\backup\Backup_Manager( $root . '/backups-meta' );
			$manager->register_storage( $storage );
			$manager->register_engine( Backup_Manager_Test_Engine::class );
			$before = time();
			$backup_id = $manager->create_backup( 'Test backup', 'test_storage', 10, 'example_engine' );
			$after = time();
			$files = glob( $root . '/backups-meta/*.json' );

			$this->assertCount( 1, $files );
			$data = json_decode( file_get_contents( $files[0] ), true );
			$this->assertSame( array( 'description', 'time', 'unique_id', 'storage_id', 'engines' ), array_keys( $data ) );
			$this->assertSame( 'Test backup', $data['description'] );
			$this->assertGreaterThanOrEqual( $before, $data['time'] );
			$this->assertLessThanOrEqual( $after, $data['time'] );
			$this->assertSame( $backup_id, $data['unique_id'] );
			$this->assertSame( 'test_storage', $data['storage_id'] );
			$this->assertSame( array( 'example_engine' => array( 'description' => 'Example engine', 'sections' => array() ) ), $data['engines'] );
			$this->assertCount( 1, $manager->existing_backups() );
			$this->assertSame( $storage, $manager->existing_backups()[0]->storage );
			$manager->delete_backup( $data['unique_id'] );
			$this->assertSame( array(), glob( $root . '/backups-meta/*.json' ) );
		} finally {
			\calmpress\utils\delete_directory( $root );
		}
	}

	/**
	 * Verify backups in different storages share one catalog directory.
	 *
	 * @since 1.0.0
	 */
	public function test_manager_uses_one_catalog_for_all_storages() {
		$root = get_temp_dir() . 'backup-manager-' . wp_generate_uuid4();
		try {
			$manager = new \calmpress\backup\Backup_Manager( $root . '/backups-meta' );
			$first = new \calmpress\backup\Local_Backup_Storage( $root . '/first', 'first' );
			$second = new \calmpress\backup\Local_Backup_Storage( $root . '/second', 'second' );
			$manager->register_storage( $first );
			$manager->register_storage( $second );
			$manager->register_engine( Backup_Manager_Test_Engine::class );
			$manager->create_backup( 'First', 'first', 10, 'example_engine' );
			$manager->create_backup( 'Second', 'second', 10, 'example_engine' );

			$this->assertCount( 2, $manager->existing_backups() );
			$this->assertCount( 2, glob( $root . '/backups-meta/*.json' ) );
			$this->assertSame( array(), glob( $root . '/first/backups-meta/*.json' ) ?: array() );
			$this->assertSame( array(), glob( $root . '/second/backups-meta/*.json' ) ?: array() );
		} finally {
			\calmpress\utils\delete_directory( $root );
		}
	}

	/**
	 * Verify a backup cannot be created without selecting an engine.
	 *
	 * @since 1.0.0
	 */
	public function test_create_backup_requires_an_engine() {
		$root = get_temp_dir() . 'backup-manager-' . wp_generate_uuid4();
		try {
			$manager = new \calmpress\backup\Backup_Manager( $root . '/backups-meta' );

			$this->expectException( \InvalidArgumentException::class );
			$manager->create_backup( 'No engine', 'default_local_storage', 10 );
		} finally {
			\calmpress\utils\delete_directory( $root );
		}
	}

	/**
	 * Verify manager cleanup removes malformed records and records with unregistered storages.
	 *
	 * @since 1.0.0
	 */
	public function test_cleanup_removes_malformed_catalog_records() {
		$root = get_temp_dir() . 'backup-manager-' . wp_generate_uuid4();
		try {
			$manager = new \calmpress\backup\Backup_Manager( $root . '/backups-meta' );
			$file = $root . '/backups-meta/corrupt.json';
			$missing_storage = $root . '/backups-meta/missing-storage.json';
			$unregistered_storage = $root . '/backups-meta/unregistered-storage.json';
			file_put_contents( $file, '{invalid json' );
			file_put_contents( $missing_storage, wp_json_encode( array( 'description' => 'Old record', 'time' => time(), 'unique_id' => wp_generate_uuid4(), 'engines' => array() ) ) );
			file_put_contents( $unregistered_storage, wp_json_encode( array( 'description' => 'Unavailable storage', 'time' => time(), 'unique_id' => wp_generate_uuid4(), 'storage_id' => 'unregistered', 'engines' => array() ) ) );

			$manager->cleanup();

			$this->assertFileDoesNotExist( $file );
			$this->assertFileDoesNotExist( $missing_storage );
			$this->assertFileDoesNotExist( $unregistered_storage );
		} finally {
			\calmpress\utils\delete_directory( $root );
		}
	}
}
