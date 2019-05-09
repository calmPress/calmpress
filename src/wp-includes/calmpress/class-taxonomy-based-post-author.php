<?php
/**
 * Implementation of the Post_Author interface for authors stored
 * in a taxonomy
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\post_authors;

/**
 * Implementation of the Post_Author interface for authors stored
 * in a taxonomy
 *
 * @since 1.0.0
 */
class Taxonomy_Based_Post_Author implements Post_Author {

	/**
	 * The term meta key holding the attachment id of the "featured image".
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	const IMAGE_META_KEY = 'calm_featured_image';

	/**
	 * The term holding the author information.
	 *
	 * @var \WP_Term
	 *
	 * @since 1.0.0
	 */
	private $term;

	/**
	 * Construct the author object based on a taxonomy term.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Term $term The term.
	 */
	public function __construct( \WP_Term $term ) {

		if ( Post_Authors_As_Taxonomy::TAXONOMY_NAME !== $term->taxonomy ) {
			trigger_error( 'The term do not belong to the authors taxonomy, but to ' . $term->taxonomy );
		}

		$this->term = $term;
	}

	/**
	 * Provides the human friendly name of the author based on the term title.
	 *
	 * @since 1.0.0
	 *
	 * @return string The unescaped name of the author.
	 */
	public function name() : string {
		return $this->term->name;
	}

	/**
	 * The ID of the term identifying it in WordPress APIs.
	 *
	 * @since 1.0.0
	 *
	 * @return int The ID of the term.
	 */
	public function term_id() : int {
		return $this->term->term_id;
	}

	/**
	 * Remove the association of the featured image (if one is associated) with the author.
	 *
	 * @since 1.0.0
	 */
	public function remove_image( \WP_Post $image ) {
		delete_term_meta( $this->term->term_id, self::IMAGE_META_KEY );
	}

	/**
	 * Set an image attachment to be the featured image of the author.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $image The WP_Post object for the image attachment or null if
	 *                        no image is associated with the author.
	 */
	public function set_image( \WP_Post $image ) {
		update_term_meta( $this->term->term_id, self::IMAGE_META_KEY, $image->ID );
	}

	/**
	 * Provide the attachment image associated with the author.
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_Post|null The WP_Post object for the image attachment or null if
	 *                       no image is associated with the author.
	 */
	public function image() {
		$id = get_term_meta( $this->term->term_id, self::IMAGE_META_KEY, true );

		if ( ! $id ) {
			return null;
		}

		// Rely on get_post to verify that the id is an actual post.
		// Right now not checking the post and mime types.
		return get_post( (int) $id );
	}

	/**
	* Provides the human friendly description of the author.
	*
	* Due to the legacy of how WordPress used to store such information,
	* the only valid expectations about the format of the returned value is that
	* it is a mix of text and HTML.
	* It should not include any JS or CSS, neither as remote resources or inlined.
	 *
	 * @since 1.0.0
	 *
	 * @return string The description text.
	 */
	public function description() : string {

		// This is not optimal from security in depth POV and sanitization
		// should have been applied here, but since it might be expensive in
		// terms of performance, sanitization and other format changes is
		// done on information save.
		return $this->term->description;
	}

	/**
	 * The URL for the authors posts archive.
	 *
	 * @since 1.0.0
	 *
	 * @return string The URL of the page, or empty string if none exists.
	 */
	public function posts_url() : string {
		return get_term_link( $this->term, Post_Authors_As_Taxonomy::TAXONOMY_NAME );
	}

	/**
	 * The number of posts the author published.
	 *
	 * @since 1.0.0
	 *
	 * @return int The number of posts.
	 */
	public function posts_count() : int {
		return (int) $this->term->count;
	}

	/**
	 * The slug of the author.
	 *
	 * @since 1.0.0
	 *
	 * @return string The slug.
	 */
	public function slug() : string {
		return $this->term->slug;
	}
}
