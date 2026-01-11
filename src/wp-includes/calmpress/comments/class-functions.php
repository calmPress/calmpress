<?php
/**
 * Helper functions used related to comments.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\comments;

class Functions {

	/**
	 * Collection of the formatting help.
	 * 
	 * @since 1.0.0
	 * 
	 * @return Comment_Syntax_Help_Item_Collection The ollection
	 */
	public static function formatting_help_collection(
		): Comment_Syntax_Help_Item_Collection {
		$collection = new Comment_Syntax_Help_Item_Collection(
			new Comment_Syntax_Help_Item(
				'italic',
				'*' . esc_html__( 'italic' ) . '*',
				'<i>' . esc_html__( 'italic' ) . '</i>'
			),
			new Comment_Syntax_Help_Item(
				'bold',
				'**' . esc_html__( 'bold' ) . '**',
				'<b>' . esc_html__( 'bold' ) . '</b>'
			),
			new Comment_Syntax_Help_Item(
				'strike',
				'~' . esc_html__( 'strike' ) . '~',
				'<s>' . esc_html__( 'strike' ) . '</s>'
			),
			new Comment_Syntax_Help_Item(
				'escape',
				'\*, \~, \\\\',
				esc_html__( 'Literal *, ~, \ characters respectively' )
			),
			new Comment_Syntax_Help_Item(
				'quote',
				'&gt; ' . esc_html__( 'quote' ) . ' ' . esc_html__( '(at start of line)' ),
				esc_html__( 'quote' ) . ' ' . esc_html__( 'as block styled as a quote, usually with different bakcground and indentation' )
			),
			new Comment_Syntax_Help_Item(
				'list_item',
				'- ' . esc_html__( 'list item' ) . ' ' . esc_html__( '(at start of line)' ),
				esc_html__( 'list item' ) . ' ' . esc_html__( 'as an item in a list, usually indicated with a bullet' )
			),
			new Comment_Syntax_Help_Item(
				'empty_line',
				esc_html__( 'Empty line' ),
				esc_html__( 'Creates a new paragraph. Lines which are not separated by an empty line remain in the same paragraph' )
			),
		);

		return $collection;
	}

	/**
	 * HTML for the comment formatting help containing examples and explanations as "lines".
	 * 
	 * This is the full HTML for the front end and admin comment submition/editting,
	 * relevant CSS is injected as well.
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $example_tag     The HTML tag in which the example is enclosed.
	 * @param string $explanation_tag The HTML tag in which the explanation is enclosed.
	 * @param string $enclosing_tag   The HTML tag in which the cobination of example
	 *                                and explanation is enclosed.
	 *
	 * @return string The HTML
	 */
	public static function formatting_help_html(
		string $example_tag,
		string $explanation_tag,
		string $enclosing_tag = ''
		): string {
		$collection = self::formatting_help_collection();

		$lines = '';
		foreach ( $collection->all_items() as $item ) {
			$line = sprintf(
				'<%1$s>%3$s</%1$s><%2$s>%4$s</%2$s>',
				$example_tag,
				$explanation_tag,
				$item->example,
				$item->explanation
			);

			if ( $enclosing_tag ) {
				$line = '<' . $enclosing_tag . '>' . $line . '</' . $enclosing_tag . '>';
			}

			$lines .= $line;
		}

		$html = '<input type="checkbox" id="show-comment-help" autocomplete="off" style="display:none;">
				<label id="comment-help-label" for="show-comment-help" style="cursor:pointer; font-weight:normal;margin-bottom:0;">
					<small>Formatting help</small>
				</label>
				<small id="comment-help"> ' .
				$lines .
				'</small>';

	$comment_form_css = <<<CSS
#comment-help {
	display:none;
    grid-template-columns: max-content auto;
    column-gap: 0.5em;
	line-height: 1.5em;
	margin-inline-start:2em;
}

#comment-help span {
    display: contents;
}

#show-comment-help:checked + #comment-help-label + #comment-help {
    display: inline-grid;
}

#comment-help-label small::after {
    content: '▼';
    display: inline-block;
    transition: transform 0.2s;
	margin-inline-start:2px;
}
#show-comment-help:checked + #comment-help-label small::after {
	content:'▲';
}
CSS;

	\calmpress\utils\enqueue_inline_style_once( 'comment_form', $comment_form_css );
		return $html;
	}
}