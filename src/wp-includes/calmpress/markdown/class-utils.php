<?php
/**
 * Class with utility functions to process markdown.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\markdown;

require_once __DIR__ . '/Parsedown.php';

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
	 *   - Strikethrough (~~text~~)
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

			// Treat headings written with leading # markers or underlines as literal text.
			protected function blockHeader( $Line ) { return null; }
			protected function blockSetextHeader( $Line, ?array $Block = null ) { return null; }

			// No code blocks
			protected function blockFencedCode( $Line ) { return null; }
			protected function blockCode( $Line, $Block = null ) { return null; }
			protected function inlineCode( $Excerpt ) { return null; }

			// No images
			protected function inlineImage( $Excerpt ) { return null; }

			// No links
			protected function inlineLink( $Excerpt ) { return null; }
			protected function inlineEmailTag( $Excerpt ) { return null; }
			protected function inlineUrlTag( $Excerpt ) { return null; }
			protected function blockReference( $Line ) { return null; }

			// Keep horizontal rules and tables as literal text.
			protected function blockRule( $Line ) { return null; }
			protected function blockTable( $Line, ?array $Block = null ) { return null; }

			/**
			 * Parse unordered list items that use a hyphen marker.
			 *
			 * @since 1.0.0
			 *
			 * @param array      $Line         Line being parsed.
			 * @param array|null $CurrentBlock Block preceding the line.
			 *
			 * @return array|null List block, or null when the line does not start a supported list.
			 */
			protected function blockList( $Line, ?array $CurrentBlock = null ) {
				if ( '-' !== ( $Line['text'][0] ?? '' ) ) {
					return null;
				}

				return parent::blockList( $Line, $CurrentBlock );
			}

			/**
			 * Allow asterisks for italic and bold text.
			 * 
			 * @since 1.0.0
			 * 
			 * @param string $Excerpt Text up to the end of line
			 * 
			 * @return mixed null if italics is triggered by having _, otherwise whatever
			 *               parsedown returns in such a case.
			 */
			protected function inlineEmphasis( $Excerpt ) {
				
				if ( isset( $Excerpt['text'][0] ) && $Excerpt['text'][0] === '*' ) {
					return parent::inlineEmphasis( $Excerpt );
				}

				return null;
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