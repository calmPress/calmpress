<?php
/**
 * Interface specification of a post author class
 *
 * @package calmPress
 * @since 1.0.0
 */

namespace calmpress\post_authors;

/**
 * A human friendly representation of a post author.
 *
 * @since 1.0.0
 */
interface Post_Author {

	/**
	 * Provides the human friendly name of the author.
	 *
	 * @since 1.0.0
	 *
	 * @return string The unescaped name of the author.
	 */
	public function name() : string;

	/**
	 * Provide the ID of an attachment image associated with the author.
	 *
	 * @since 1.0.0
	 *
	 * @return int The ID of the attachment or 0 if no image is associated with the author.
	 */
	public function image_attachment_id() : int;

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
	public function description() : string;

	/**
	 * Provides the unescaped url for a page associate with the author.
	 *
	 * @since 1.0.0
	 *
	 * @return string The URL of the page, or empty string if none exists.
	 */
	public function posts_url() : string;
}
