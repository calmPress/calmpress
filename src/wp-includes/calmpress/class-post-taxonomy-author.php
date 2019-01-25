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
class Post_Taxonomy_Author implements Post_Author {

	private $term;

	/**
	 * Construct the author object based on a taxonomy term.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Term $term The term.
	 */
	public function __construct( \WP_Term $term ) {
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
	 * Provide the ID of an attachment image associated with the author.
	 *
	 * @since 1.0.0
	 *
	 * @return int The ID of the attachment or 0 if no image is associated with the author.
	 */
	public function image_attachment_id() : int {
		return 0;
	}

	/**
	 * Provides the human friendly description of the author in HTML.
	 *
	 * The HTML is constructed in a way in which an output of it will create
	 * content which is valid to be included inside of another HTML block element.
	 * It should not include any JS or CSS, neither as remote resources or inlined.
	 *
	 * All plain text has to be organized in paragraphs (enclosed in a P tag).
	 * This means that even if the description is just the word "text", this
	 * function should return "<p>text</p>".
	 *
	 * @since 1.0.0
	 *
	 * @return string The HTML
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
