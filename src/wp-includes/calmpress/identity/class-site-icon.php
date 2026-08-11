<?php
/**
 * Site Icon representation.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\identity;

/**
 * Represents a site's Site Icon.
 *
 * @since 1.0.0
 */
class Site_Icon {
	/**
	 * The ID of the site whose Site Icon is represented.
	 *
	 * Zero is the special value for the current site. On a standalone installation,
	 * the value is always normalized to 0.
	 *
	 * @since 1.0.0
	 */
	private readonly int $blog_id;

	/**
	 * Creates a Site Icon representation for the specified site.
	 *
	 * @since 1.0.0
	 *
	 * @param int $blog_id Site ID. A value of 0 indicates the current site. On a
	 *                     standalone installation, only 0 and 1 are accepted;
	 *                     both identify the sole current site and are stored
	 *                     internally as 0. Default 0.
	 *
	 * @throws \LogicException When a standalone installation receives an invalid site ID.
	 * @throws \RuntimeException When the requested multisite site does not exist.
	 */
	public function __construct( int $blog_id = 0 ) {
		if ( ! is_multisite() ) {
			if ( ! in_array( $blog_id, [ 0, 1 ], true ) ) {
				throw new \LogicException( 'A standalone installation only supports site ID 0 or 1.' );
			}

			$this->blog_id = 0;
			return;
		}

		if ( ( 0 !== $blog_id ) && ( null === get_site( $blog_id ) ) ) {
			throw new \RuntimeException( 'The requested site does not exist.' );
		}

		$this->blog_id = $blog_id;
	}

	/**
	 * The URL of an image for displaying the Site Icon at a specific image size.
	 *
	 * The URL is an empty string when no Site Icon is configured.
	 * The value is false when a Site Icon is configured but its URL cannot be resolved.
	 * The full attachment image is used when the requested size is 512 pixels or greater.
	 *
	 * @since 1.0.0
	 *
	 * @param int $size Requested width and height in pixels.
	 *
	 * @return string|false Site Icon image URL, an empty string when no Site Icon is
	 *                      configured, or false when its URL cannot be resolved.
	 */
	public function url( int $size = 512 ): string|false {
		if ( ! is_multisite() ) {
			$attachment_id = (int) get_option( 'site_icon' );
			$url = ( 0 === $attachment_id ) ? '' : $this->attachment_url( $attachment_id, 0, $size );
		} else {
			// Try the Site Icon configured for the represented site first.
			$site_attachment_id = (int) get_blog_option( $this->blog_id, 'site_icon' );
			$url                = ( 0 === $site_attachment_id ) ? '' : $this->attachment_url( $site_attachment_id, $this->blog_id, $size );

			if ( empty( $url ) ) {
				// A missing or unresolvable site icon falls back to the network Site Icon.
				$network_attachment_id = (int) get_network_option( 0, 'site_icon', 0 );

				if ( 0 !== $network_attachment_id ) {
					$url = $this->attachment_url( $network_attachment_id, get_main_site_id(), $size );
				}
			}
		}

		return $url;
	}

	/**
	 * Resolves an attachment URL in the context of the site that owns it.
	 *
	 * @since 1.0.0
	 *
	 * @param int $attachment_id      Attachment ID.
	 * @param int $attachment_blog_id ID of the site that owns the attachment.
	 * @param int $size               Requested width and height in pixels.
	 *
	 * @return string|false Attachment image URL, or false when it cannot be resolved.
	 */
	private function attachment_url( int $attachment_id, int $attachment_blog_id, int $size ): string|false {
		$size_data = ( 512 <= $size ) ? 'full' : [ $size, $size ];

		if ( ( 0 === $attachment_blog_id ) || ( get_current_blog_id() === $attachment_blog_id ) ) {
			// The attachment belongs to the current site, so avoid switching blog context.
			return wp_get_attachment_image_url( $attachment_id, $size_data );
		}

		// Attachment APIs must run in the site that owns the attachment.
		switch_to_blog( $attachment_blog_id );
		$url = wp_get_attachment_image_url( $attachment_id, $size_data );
		restore_current_blog();

		return $url;
	}
}
