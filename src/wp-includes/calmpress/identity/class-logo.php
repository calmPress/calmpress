<?php
/**
 * Logo representation.
 *
 * @package calmPress
 */

declare(strict_types=1);

namespace calmpress\identity;

/**
 * Represents a site's Logo.
 *
 * @since 1.0.0
 */
class Logo {
	/**
	 * The ID of the site whose Logo is represented.
	 *
	 * Zero is the special value for the current site. On a standalone installation,
	 * the value is always normalized to 0.
	 *
	 * @since 1.0.0
	 */
	private readonly int $blog_id;

	/**
	 * Creates a Logo representation for the specified site.
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
	 * Whether an image is configured for the Logo.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True when the Logo has an image, false otherwise.
	 */
	public function has_image(): bool {
		return null !== $this->attachment();
	}

	/**
	 * The IMG element markup for the Logo.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attributes Attributes to add to the generated IMG element, keyed by attribute name.
	 *
	 * @return string Logo IMG element markup, or an empty string when no Logo is configured
	 *                or its markup cannot be generated.
	 */
	public function img( array $attributes ): string {
		$attachment = $this->attachment();

		if ( null === $attachment ) {
			return '';
		}

		$switched = ( 0 !== $attachment['blog_id'] ) && ( get_current_blog_id() !== $attachment['blog_id'] );

		if ( $switched ) {
			switch_to_blog( $attachment['blog_id'] );
		}

		$image = wp_get_attachment_image( $attachment['id'], 'full', false, $attributes );

		if ( $switched ) {
			restore_current_blog();
		}

		return $image;
	}

	/**
	 * Resolves the configured Logo attachment and the site that owns it.
	 *
	 * On multisite, a Logo configured for the represented site takes precedence
	 * over the Network Logo. The Network Logo is used when the site has no Logo.
	 *
	 * A nonzero stored option value is assumed to identify a valid image attachment
	 * because Logo attachment validation belongs to the settings write paths.
	 *
	 * @return array{id: int, blog_id: int}|null Attachment data, or null when no Logo can be used.
	 */
	private function attachment(): ?array {
		if ( ! is_multisite() ) {
			$attachment_id = (int) get_option( 'custom_logo' );

			if ( 0 === $attachment_id ) {
				return null;
			}

			return [
				'id'      => $attachment_id,
				'blog_id' => 0,
			];
		}

		// Prefer the Logo configured for the represented site.
		$attachment_id = (int) get_blog_option( $this->blog_id, 'custom_logo' );

		if ( 0 !== $attachment_id ) {
			return [
				'id'      => $attachment_id,
				'blog_id' => ( 0 === $this->blog_id ) ? get_current_blog_id() : $this->blog_id,
			];
		}

		// Use the Network Logo when the represented site has no Logo.
		$attachment_id = (int) get_network_option( 0, 'custom_logo', 0 );

		if ( 0 === $attachment_id ) {
			return null;
		}

		return [
			'id'      => $attachment_id,
			'blog_id' => get_main_site_id(),
		];
	}
}
