<?php
/**
 * Class with utility functions to process markdown.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\markdown;

require_once __DIR__ . '\parsedown.php';

/**
 * Class with utility functions to process markdown.
 *
 * @since 1.0.0
 */
class Utils {

	/**
	 * Convert comment Markdown to safe HTML.
	 *
	 * Supported Markdown elements:
	 *   - Bold (**text**)
	 *   - Italic (*text*)
	 *   - Strikethrough (~text~)
	 *   - Blockquotes (> text)
	 *   - Lists (- item)
	 *
	 * All other Markdown elements (headers, code blocks, links, images, 
	 * horizontal rules) and any raw HTML tags are preserved as literal text
	 * with HTML entities escaped (<, >, &), so they appear safely in the output.
	 *
	 * Markdown paragraphs are converted to <p> elements.
	 * 
	 * @since 1.0
	 *
	 * @param string $comment_markdown The markdown text to convert.
	 * 
	 * @return string The HTML based on converting $comment_markdown, escaping HTML
	 *                where needed.
	 */
	public static function comment_markdown_to_html( string $comment_markdown ): string {

		/**
		 * A Mardown parser with restricted syntax as document at the function's
		 * description.
		 * 
		 * @since 1.0.0
		 */
		$markdown_parser = new class extends \Parsedown {

			// Override the list of characters that can be esaped with "\".
			protected $specialCharacters = array(
				'\\', '~', '*'
			);

			// no H support
			protected function blockHeader( $Line ) { return null; }

			// No code blocks
			protected function blockFencedCode( $Line ) { return null; }
			protected function blockCode( $Line, $Block = null ) { return null; }
			protected function inlineCode( $Excerpt ) { return null; }

			// No images
			protected function inlineImage( $Excerpt ) { return null; }

			// No links
			protected function inlineLink( $Excerpt ) { return null; }

			// No <hr>.
			protected function blockHorizontalRule( $Line ) { return null; }

			/**
			 * Prevent * and + fron starting list iten, and ordered lists.
			 * 
			 * @since 1.0.0
			 * 
			 * @param string $Line The line being parsed.
			 * 
			 * @return mixed null if Line starts with +,* or number followed by . (dot)
			 *              otherwise call parsedown to continue and return whatever
			 *              it will return.
			 */
			protected function blockList( $Line ) {
				// Reject * and +
				if ( isset( $Line['text'][0] ) && ( $Line['text'][0] === '*' || $Line['text'][0] === '+' ) ) {
					return null;
				}

				// Reject numeric list markers.
				if ( preg_match( '/^\d+\./', $Line['text'] ) ) {
					return null;
				}

				return parent::blockList( $Line );
			}

			/**
			 * Only allow * for italics, ignore _
			 * 
			 * @since 1.0.0
			 * 
			 * @param string $Excerpt Text up to the end of line
			 * 
			 * @return mixed null if italics is triggered by having _, otherwise whtever
			 *               parsedown returns in such a case.
			 */
			protected function inlineEmphasis( $Excerpt ) {
				
				if ( isset( $Excerpt['text'][0]) && $Excerpt['text'][0] === '*' ) {
					return parent::inlineEmphasis( $Excerpt );
				}

				return null;
			}

			/**
			 * Only allow ** for bold, ignore __
			 * 
			 * @since 1.0.0
			 * 
			 * @param string $Excerpt Text up to the end of line
			 * 
			 * @return mixed null if bold is triggered by having __, otherwise whtever
			 *               parsedown returns in such a case.
			 */
			protected function inlineStrong( $Excerpt ) {
				if ( isset($Excerpt['text'][0] ) && $Excerpt['text'][0] === '_' ) {
					return null;
				}

				return parent::inlineStrong( $Excerpt );
			}
		};

		// Disable automatic URL linking
		$markdown_parser->setUrlsLinked(false);

		// Always just escape HTML.
		$markdown_parser->setMarkupEscaped( true );

		// Parse allowed Markdown into HTML
		return $markdown_parser->text( $comment_markdown );
	}
}