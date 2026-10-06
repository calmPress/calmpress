<?php
/**
 * Tests for the backup metadata API.
 *
 * @package calmPress
 * @since 1.0.0
 */

/**
 * Test backup metadata creation.
 *
 * @since 1.0.0
 */
class Backup_Test extends WP_UnitTestCase {

	/**
	 * Verify an engine's stored identities resolve to its committed sections.
	 *
	 * @since 1.0.0
	 */
	public function test_engine_sections_resolves_stored_section_identities() {
		$root = get_temp_dir() . 'backup-api-' . wp_generate_uuid4();
		$storage = new \calmpress\backup\Local_Backup_Storage( $root );
		try {
			$identity = new \calmpress\backup\Backup_Section_Identity( 'plugin', 'example', '1.0' );
			$staging = $storage->create_section( $identity, 'Example Plugin' );
			$staging->file_put_contents( 'plugin.php', '<?php' );
			$staging->commit();
			$json = wp_json_encode(
				array(
					'description' => 'Test backup',
					'time'        => time(),
					'unique_id'   => wp_generate_uuid4(),
					'storage_id'  => $storage->identifier(),
					'engines'     => array( 'core' => array( 'description' => 'Core backup', 'sections' => array( $identity ) ) ),
				)
			);
			$backup = new \calmpress\backup\Backup( $json, $storage );
			$sections = $backup->engine_sections( 'core' );

			$this->assertCount( 1, $sections );
			$this->assertEquals( $identity, $sections[0]->identity );
			$this->assertSame( 'Example Plugin', $sections[0]->display_name() );
		} finally {
			\calmpress\utils\delete_directory( $root );
		}
	}

	/**
	 * Verify the saved engine description remains available from backup metadata.
	 *
	 * @since 1.0.0
	 */
	public function test_constructor_retains_engine_description() {
		$root = get_temp_dir() . 'backup-api-' . wp_generate_uuid4();
		$storage = new \calmpress\backup\Local_Backup_Storage( $root );
		try {
			$json = wp_json_encode(
				array(
					'description' => 'Test backup',
					'time'        => time(),
					'unique_id'   => wp_generate_uuid4(),
					'storage_id'  => $storage->identifier(),
					'engines'     => array(
						'core' => array(
							'description' => 'Essential code and settings',
							'sections' => array( array( 'type' => 'core', 'location' => 'calmPress', 'version' => '1.0' ) ),
						),
					),
				)
			);
			$backup = new \calmpress\backup\Backup( $json, $storage );

			$this->assertSame( array( 'core' ), array_keys( $backup->engines ) );
			$this->assertSame( 'Essential code and settings', $backup->engine_descriptions['core'] );
		} finally {
			\calmpress\utils\delete_directory( $root );
		}
	}

	/**
	 * Verify construction rejects metadata for a different section storage.
	 *
	 * @since 1.0.0
	 */
	public function test_constructor_rejects_mismatched_storage() {
		$root = get_temp_dir() . 'backup-api-' . wp_generate_uuid4();
		try {
			$storage = new \calmpress\backup\Local_Backup_Storage( $root, 'registered' );
			$json = wp_json_encode(
				array(
					'description' => 'Test backup',
					'time'        => time(),
					'unique_id'   => wp_generate_uuid4(),
					'storage_id'  => 'unregistered',
					'engines'     => array(),
				)
			);

			$this->expectException( Exception::class );
			new \calmpress\backup\Backup( $json, $storage );
		} finally {
			\calmpress\utils\delete_directory( $root );
		}
	}

}
