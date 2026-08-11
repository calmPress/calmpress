<?php
/**
 * Tests for the Site_Icon class.
 *
 * @package calmPress
 */

declare(strict_types=1);

namespace calmpress\identity\tests;

use calmpress\identity\Site_Icon;

/**
 * Tests the Site Icon representation.
 */
class Site_Icon_Test extends \WP_UnitTestCase {

	/**
	 * Removes files added to the uploads directory by each test.
	 */
	public function tear_down(): void {
		$this->remove_added_uploads();

		parent::tear_down();
	}

	/**
	 * Tests that the Site Icon returns its attachment URL.
	 */
	public function test_url_returns_attachment_url(): void {
		$attachment_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/canola.jpg'
		);
		update_option( 'site_icon', $attachment_id );

		$site_icon = new Site_Icon();

		$this->assertSame( wp_get_attachment_image_url( $attachment_id, 'full' ), $site_icon->url() );
	}

	/**
	 * Tests that a site without a configured Site Icon has no URL.
	 */
	public function test_url_is_empty_without_configured_icon(): void {
		delete_option( 'site_icon' );

		$site_icon = new Site_Icon();

		$this->assertSame( '', $site_icon->url() );
	}

	/**
	 * Tests the result when a configured Site Icon has no attachment URL.
	 */
	public function test_url_is_false_when_attachment_url_cannot_be_resolved(): void {
		update_option( 'site_icon', PHP_INT_MAX );

		$site_icon = new Site_Icon();

		$this->assertFalse( $site_icon->url() );
	}

	/**
	 * Tests that a standalone installation rejects an invalid site ID.
	 */
	public function test_standalone_site_rejects_invalid_blog_id(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'This behavior applies only to standalone installations.' );
		}

		$this->expectException( \LogicException::class );

		new Site_Icon( 555 );
	}

	/**
	 * Tests that site IDs 0 and 1 identify the same standalone site.
	 */
	public function test_standalone_site_ids_are_equivalent(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'This behavior applies only to standalone installations.' );
		}

		$attachment_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/canola.jpg'
		);
		update_option( 'site_icon', $attachment_id );

		$this->assertSame( ( new Site_Icon( 0 ) )->url(), ( new Site_Icon( 1 ) )->url() );
	}

	/**
	 * Tests that a site's Site Icon is used when no network Site Icon is configured.
	 *
	 * @group ms-required
	 */
	public function test_site_icon_is_used_without_network_site_icon(): void {
		$original_blog_id = get_current_blog_id();
		$blog_id          = self::factory()->blog->create();

		delete_network_option( null, 'site_icon' );

		switch_to_blog( $blog_id );
		$attachment_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.jpg'
		);
		update_option( 'site_icon', $attachment_id );
		$expected_url = wp_get_attachment_image_url( $attachment_id, 'full' );
		restore_current_blog();

		$site_icon = new Site_Icon( $blog_id );

		$this->assertSame( $expected_url, $site_icon->url() );
		$this->assertSame( $original_blog_id, get_current_blog_id() );
	}

	/**
	 * Tests that no URL is returned when neither the site nor its network has a Site Icon.
	 *
	 * @group ms-required
	 */
	public function test_url_is_empty_without_site_or_network_site_icon(): void {
		$blog_id = self::factory()->blog->create();

		delete_blog_option( $blog_id, 'site_icon' );
		delete_network_option( null, 'site_icon' );

		$site_icon = new Site_Icon( $blog_id );

		$this->assertSame( '', $site_icon->url() );
	}

	/**
	 * Tests the result when a site's configured Site Icon has no attachment URL.
	 *
	 * @group ms-required
	 */
	public function test_url_is_false_when_site_icon_url_cannot_be_resolved(): void {
		$blog_id = self::factory()->blog->create();

		update_blog_option( $blog_id, 'site_icon', PHP_INT_MAX );
		delete_network_option( null, 'site_icon' );

		$site_icon = new Site_Icon( $blog_id );

		$this->assertFalse( $site_icon->url() );
	}

	/**
	 * Tests that an unresolvable site icon falls back to the network Site Icon.
	 *
	 * @group ms-required
	 */
	public function test_network_site_icon_is_used_when_site_icon_url_cannot_be_resolved(): void {
		$blog_id              = self::factory()->blog->create();
		$network_site_icon_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.jpg'
		);
		$network_site_icon_url = wp_get_attachment_image_url( $network_site_icon_id, 'full' );

		update_blog_option( $blog_id, 'site_icon', PHP_INT_MAX );
		update_network_option( null, 'site_icon', $network_site_icon_id );

		$site_icon = new Site_Icon( $blog_id );

		$this->assertSame( $network_site_icon_url, $site_icon->url() );
	}

	/**
	 * Tests the result when neither configured Site Icon URL can be resolved.
	 *
	 * @group ms-required
	 */
	public function test_url_is_false_when_site_and_network_site_icon_urls_cannot_be_resolved(): void {
		$blog_id = self::factory()->blog->create();

		update_blog_option( $blog_id, 'site_icon', PHP_INT_MAX );
		update_network_option( null, 'site_icon', PHP_INT_MAX );

		$site_icon = new Site_Icon( $blog_id );

		$this->assertFalse( $site_icon->url() );
	}

	/**
	 * Tests the result when an inherited network Site Icon has no attachment URL.
	 *
	 * @group ms-required
	 */
	public function test_url_is_false_when_network_site_icon_url_cannot_be_resolved(): void {
		$blog_id = self::factory()->blog->create();

		delete_blog_option( $blog_id, 'site_icon' );
		update_network_option( null, 'site_icon', PHP_INT_MAX );

		$site_icon = new Site_Icon( $blog_id );

		$this->assertFalse( $site_icon->url() );
	}

	/**
	 * Tests that a site without a Site Icon uses its network's Site Icon.
	 *
	 * @group ms-required
	 */
	public function test_network_site_icon_is_used_as_fallback(): void {
		$blog_id = self::factory()->blog->create();

		delete_blog_option( $blog_id, 'site_icon' );

		$network_site_icon_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.jpg'
		);
		$expected_url = wp_get_attachment_image_url( $network_site_icon_id, 'full' );

		update_network_option( null, 'site_icon', $network_site_icon_id );

		$site_icon = new Site_Icon( $blog_id );

		$this->assertSame( $expected_url, $site_icon->url() );
	}

	/**
	 * Tests that a site's Site Icon takes precedence over its network's Site Icon.
	 *
	 * @group ms-required
	 */
	public function test_site_icon_takes_precedence_over_network_site_icon(): void {
		$blog_id              = self::factory()->blog->create();
		$network_site_icon_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.jpg'
		);

		update_network_option( null, 'site_icon', $network_site_icon_id );

		switch_to_blog( $blog_id );
		$site_icon_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/canola.jpg'
		);
		update_option( 'site_icon', $site_icon_id );
		$expected_url = wp_get_attachment_image_url( $site_icon_id, 'full' );
		restore_current_blog();

		$site_icon = new Site_Icon( $blog_id );

		$this->assertSame( $expected_url, $site_icon->url() );
	}
}
