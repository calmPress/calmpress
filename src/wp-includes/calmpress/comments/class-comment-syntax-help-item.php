<?php
/**
 * Implementation of a comment syntax help item "type".
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\comments;

/**
 * A representation of a line of comment syntax help.
 * 
 * @since 1.0.0
 */
class Comment_Syntax_Help_Item {

	/**
	 * An identifier for the item.
	 *
	 * @since calmPress 1.0.0
	 */
	public readonly string $id;

	/**
	 * An example for the syntax, HTML escaped.
	 *
	 * HTML tags are not allowed.
	 * 
	 * @since calmPress 1.0.0
	 */
	public readonly string $example;

	/**
	 * An explanation of the syntax, HTML escaped except for allowed tags.
	 *
	 * An explenation can contain only the following HTML tags
	 * B, I, EM, STRONG, S, CODE, SUP, SUB, BR, SPAN.
	 * attributes are not allowed on the tags, except for SPAN at which
	 * a class is allowed.
	 * 
	 * @since calmPress 1.0.0
	 */
	public readonly string $explanation;

	/**
	 * Create a Comment Syntax Help Item object.
	 * 
	 * $example and $explanation are sanitized and non allowed html tags are removed.
	 * 
	 * @since calmPress 1.0.0
	 * 
	 * @param string id           A stable  identifier of the item, making it easier to
	 *                            find it when its part of a collection.
	 *                            Can not be an empty string.
	 * 
	 * @param string $example     The example of the syntax, HTML escaped.
	 *                            Example can not have HTML tags.
	 * 
	 * @param string $explanation The explanation of the syntax, HTML escaped
	 *                            except for allowed tags.
	 *                            An explenation can contain only the following HTML tags
	 *                            B, I, EM, STRONG, S, CODE, SUP, SUB, BR, SPAN.
	 *                            attributes are not allowed on the tags, except for SPAN at which
	 *                            a class is allowed.
	 * 
	 * @throws LogicException if $id or $example are empty strings. 
	 */
	public function __construct( string $id, string $example, string $explanation ) {
		if ( $id === '' ) {
			throw new \LogicException( 'id can not be an empty string' );
		}

		if ( trim( $example ) === '' ) {
			throw new \LogicException( 'example should contain printable text' );
		}

		$this->id          = $id;
		$this->example     = wp_kses( trim( $example ), [] );
		$this->explanation = self::sanitize_example( trim( $explanation ) );
	}

	/**
	 * Helper to sanitize the explanation string to remove html tags and attributes
	 * which are not allowed in it.
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $str The string to sanitize.
	 * 
	 * @return string $str after sanitization.
	 */
	private static function sanitize_example( string $str ): string {
		$allowed = [
			'b'      => [],
			'i'      => [],
			'em'     => [],
			'strong' => [],
			's'      => [],
			'code'   => [],
			'sup'    => [],
			'sub'    => [],
			'br'     => [],
			'span'   => [ 'class' => true ],
		];

		return wp_kses( $str, $allowed );
	}
}
