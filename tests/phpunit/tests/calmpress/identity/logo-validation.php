<?php
/**
 * Tests Logo setting validation.
 *
 * @package calmPress
 */

declare(strict_types=1);

namespace calmpress\identity\tests;

/**
 * Tests the server-side Logo setting write paths.
 */
class Logo_Validation_Test extends \WP_UnitTestCase {

	/**
	 * Loads the General Settings validators.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/admin-settings-validators.php';
	}

	/**
	 * Removes files added to the uploads directory by each test.
	 */
	public function tear_down(): void {
		$this->remove_added_uploads();

		parent::tear_down();
	}

	/**
	 * Tests that General Settings accepts image attachments and rejects other media.
	 */
	public function test_general_settings_logo_validation(): void {
		$image_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.jpg'
		);
		$document_id = self::factory()->attachment->create(
			[ 'post_mime_type' => 'application/pdf' ]
		);

		$image_validity = apply_filters(
			'check_input_errors_general',
			new \WP_Error(),
			[
				'custom_logo' => $image_id,
				'site_icon'   => $image_id,
			]
		);
		$document_validity = apply_filters(
			'check_input_errors_general',
			new \WP_Error(),
			[
				'custom_logo' => $document_id,
				'site_icon'   => $document_id,
			]
		);

		$this->assertFalse( $image_validity->has_errors() );
		$this->assertSame( [ 'invalid_image_attachment' ], $document_validity->get_error_codes() );
		$this->assertCount( 2, $document_validity->get_error_messages() );
	}

	/**
	 * Tests that the Customizer accepts image attachments and rejects other media.
	 */
	public function test_customizer_logo_validation(): void {
		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';

		$image_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.jpg'
		);
		$document_id = self::factory()->attachment->create(
			[ 'post_mime_type' => 'application/pdf' ]
		);
		$manager = new \WP_Customize_Manager();

		$image_validity = $manager->_validate_image_attachment( new \WP_Error(), $image_id );
		$document_validity = $manager->_validate_image_attachment( new \WP_Error(), $document_id );

		$this->assertFalse( $image_validity->has_errors() );
		$this->assertSame( 'invalid_image_attachment', $document_validity->get_error_code() );
		$this->assertSame( 'Only images can be used for this feature.', $document_validity->get_error_message() );
	}
}
