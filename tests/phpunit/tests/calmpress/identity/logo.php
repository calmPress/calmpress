<?php
/**
 * Tests for the Logo class.
 *
 * @package calmPress
 */

declare(strict_types=1);

namespace calmpress\identity\tests;

use calmpress\identity\Logo;

/**
 * Tests the effective Logo representation.
 */
class Logo_Test extends \WP_UnitTestCase {

	/**
	 * Removes files added to the uploads directory by each test.
	 */
	public function tear_down(): void {
		$this->remove_added_uploads();

		parent::tear_down();
	}

	/**
	 * Tests that has_image() returns true for a configured standalone Logo.
	 *
	 * @since 1.0.0
	 */
	public function test_has_image_returns_true_for_configured_standalone_logo(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'This behavior applies only to standalone installations.' );
		}

		$attachment_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.jpg'
		);
		update_option( 'custom_logo', $attachment_id );

		$this->assertTrue( ( new Logo() )->has_image() );
	}

	/**
	 * Tests that has_image() returns false for an unconfigured standalone Logo.
	 *
	 * @since 1.0.0
	 */
	public function test_has_image_returns_false_for_unconfigured_standalone_logo(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'This behavior applies only to standalone installations.' );
		}

		delete_option( 'custom_logo' );

		$this->assertFalse( ( new Logo() )->has_image() );
	}

	/**
	 * Tests that has_image() trusts a configured option value.
	 *
	 * @since 1.0.0
	 */
	public function test_has_image_returns_true_when_option_contains_non_image_attachment(): void {
		$attachment_id = self::factory()->attachment->create(
			[ 'post_mime_type' => 'application/pdf' ]
		);
		update_option( 'custom_logo', $attachment_id );

		$this->assertTrue( ( new Logo() )->has_image() );
	}

	/**
	 * Tests that has_image() returns true for a Network Logo fallback.
	 *
	 * @since 1.0.0
	 *
	 * @group ms-required
	 */
	public function test_has_image_returns_true_for_network_logo_fallback(): void {
		$blog_id = self::factory()->blog->create();
		$attachment_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.jpg'
		);

		delete_blog_option( $blog_id, 'custom_logo' );
		update_network_option( null, 'custom_logo', $attachment_id );

		$this->assertTrue( ( new Logo( $blog_id ) )->has_image() );
	}

	/**
	 * Tests that img() returns the configured standalone Logo.
	 *
	 * @since 1.0.0
	 */
	public function test_img_returns_configured_standalone_logo(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'This behavior applies only to standalone installations.' );
		}

		$attachment_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.jpg'
		);
		$attachment_url = wp_get_attachment_image_url( $attachment_id, 'full' );
		update_option( 'custom_logo', $attachment_id );

		$this->assertStringContainsString( $attachment_url, ( new Logo() )->img( [] ) );
	}

	/**
	 * Tests that img() adds the supplied attributes to its IMG element.
	 *
	 * @since 1.0.0
	 */
	public function test_img_adds_attributes(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'This behavior applies only to standalone installations.' );
		}

		$attachment_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.jpg'
		);
		update_option( 'custom_logo', $attachment_id );

		$image = ( new Logo() )->img(
			[
				'alt'       => 'Logo alternative text',
				'class'     => 'custom-logo',
				'data-test' => 'logo-attribute',
			]
		);

		$this->assertStringContainsString( 'alt="Logo alternative text"', $image );
		$this->assertStringContainsString( 'class="custom-logo"', $image );
		$this->assertStringContainsString( 'data-test="logo-attribute"', $image );
	}

	/**
	 * Tests that img() returns an empty string for an unconfigured standalone Logo.
	 *
	 * @since 1.0.0
	 */
	public function test_img_returns_empty_string_for_unconfigured_standalone_logo(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'This behavior applies only to standalone installations.' );
		}

		delete_option( 'custom_logo' );

		$this->assertSame( '', ( new Logo() )->img( [] ) );
	}

	/**
	 * Tests that img() returns the Network Logo as a fallback.
	 *
	 * @since 1.0.0
	 *
	 * @group ms-required
	 */
	public function test_img_returns_network_logo_fallback(): void {
		$blog_id = self::factory()->blog->create();
		$attachment_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.jpg'
		);
		$attachment_url = wp_get_attachment_image_url( $attachment_id, 'full' );

		delete_blog_option( $blog_id, 'custom_logo' );
		update_network_option( null, 'custom_logo', $attachment_id );

		$this->assertStringContainsString( $attachment_url, ( new Logo( $blog_id ) )->img( [] ) );
	}

	/**
	 * Tests that img() prefers a site's Logo over its Network Logo.
	 *
	 * @since 1.0.0
	 *
	 * @group ms-required
	 */
	public function test_img_prefers_site_logo_over_network_logo(): void {
		$blog_id         = self::factory()->blog->create();
		$network_logo_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.jpg'
		);
		$network_logo_url = wp_get_attachment_image_url( $network_logo_id, 'full' );
		update_network_option( null, 'custom_logo', $network_logo_id );

		switch_to_blog( $blog_id );
		$site_logo_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/canola.jpg'
		);
		$site_logo_url = wp_get_attachment_image_url( $site_logo_id, 'full' );
		update_option( 'custom_logo', $site_logo_id );
		restore_current_blog();

		$image = ( new Logo( $blog_id ) )->img( [] );

		$this->assertStringContainsString( $site_logo_url, $image );
		$this->assertStringNotContainsString( $network_logo_url, $image );
	}
}
