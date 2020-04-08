<?php
/**
 * Functions related to registering and parsing blocks.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.0.0
 */

/**
 * Registers a block type.
 *
 * Does nothing for calmPress.
 *
 * @since 5.0.0
 *
 * @param string|WP_Block_Type $name Block type name including namespace, or alternatively a
 *                                   complete WP_Block_Type instance. In case a WP_Block_Type
 *                                   is provided, the $args parameter will be ignored.
 * @param array                $args {
 *     Optional. Array of block type arguments. Any arguments may be defined, however the
 *     ones described below are supported by default. Default empty array.
 *
 *     @type callable $render_callback Callback used to render blocks of this block type.
 * }
 * @return WP_Block_Type|false The registered block type on success, or false on failure.
 */
function register_block_type( $name, $args = array() ) {
	return false;
}

/**
 * Unregisters a block type.
 *
 * Does nothing for calmPress.
 *
 * @since 5.0.0
 *
 * @param string|WP_Block_Type $name Block type name including namespace, or alternatively a
 *                                   complete WP_Block_Type instance.
 * @return WP_Block_Type|false The unregistered block type on success, or false on failure.
 */
function unregister_block_type( $name ) {
	false;
}

/**
 * Determine whether a post or content string has blocks.
 *
 * This test optimizes for performance rather than strict accuracy, detecting
 * the pattern of a block but not validating its structure. For strict accuracy,
 * you should use the block parser on post content.
 *
 * Always returns false for calmPress.
 *
 * @since 5.0.0
 * @see parse_blocks()
 *
 * @param int|string|WP_Post|null $post Optional. Post content, post ID, or post object. Defaults to global $post.
 * @return bool Whether the post has blocks.
 */
function has_blocks( $post = null ) {
	return false;
}

/**
 * Determine whether a $post or a string contains a specific block type.
 *
 * This test optimizes for performance rather than strict accuracy, detecting
 * the block type exists but not validating its structure. For strict accuracy,
 * you should use the block parser on post content.
 *
 * Always returns false for calmPress.
 *
 * @since 5.0.0
 * @see parse_blocks()
 *
 * @param string                  $block_name Full Block type to look for.
 * @param int|string|WP_Post|null $post Optional. Post content, post ID, or post object. Defaults to global $post.
 * @return bool Whether the post content contains the specified block.
 */
function has_block( $block_type, $post = null ) {
	return false;
}

/**
 * Returns an array of the names of all registered dynamic block types.
 *
 * Always returns empty array for calmPress.
 *
 * @since 5.0.0
 *
 * @return array Array of dynamic block names.
 */
function get_dynamic_block_names() {
	return array();
}

/**
 * Given an array of attributes, returns a string in the serialized attributes
 * format prepared for post content.
 *
 * The serialized result is a JSON-encoded string, with unicode escape sequence
 * substitution for characters which might otherwise interfere with embedding
 * the result in an HTML comment.
 *
 * @since 5.3.1
 *
 * @param array $attributes Attributes object.
 * @return string Serialized attributes.
 */
function serialize_block_attributes( $block_attributes ) {
	$encoded_attributes = json_encode( $block_attributes );
	$encoded_attributes = preg_replace( '/--/', '\\u002d\\u002d', $encoded_attributes );
	$encoded_attributes = preg_replace( '/</', '\\u003c', $encoded_attributes );
	$encoded_attributes = preg_replace( '/>/', '\\u003e', $encoded_attributes );
	$encoded_attributes = preg_replace( '/&/', '\\u0026', $encoded_attributes );
	// Regex: /\\"/
	$encoded_attributes = preg_replace( '/\\\\"/', '\\u0022', $encoded_attributes );

	return $encoded_attributes;
}

/**
 * Returns the block name to use for serialization. This will remove the default
 * "core/" namespace from a block name.
 *
 * @since 5.3.1
 *
 * @param string $block_name Original block name.
 * @return string Block name to use for serialization.
 */
function strip_core_block_namespace( $block_name = null ) {
	if ( is_string( $block_name ) && 0 === strpos( $block_name, 'core/' ) ) {
		return substr( $block_name, 5 );
	}

	return $block_name;
}

/**
 * Returns the content of a block, including comment delimiters.
 *
 * @since 5.3.1
 *
 * @param string $block_name Block name.
 * @param array  $attributes Block attributes.
 * @param string $content    Block save content.
 * @return string Comment-delimited block content.
 */
function get_comment_delimited_block_content( $block_name = null, $block_attributes, $block_content ) {
	if ( is_null( $block_name ) ) {
		return $block_content;
	}

	$serialized_block_name = strip_core_block_namespace( $block_name );
	$serialized_attributes = empty( $block_attributes ) ? '' : serialize_block_attributes( $block_attributes ) . ' ';

	if ( empty( $block_content ) ) {
		return sprintf( '<!-- wp:%s %s/-->', $serialized_block_name, $serialized_attributes );
	}

	return sprintf(
		'<!-- wp:%s %s-->%s<!-- /wp:%s -->',
		$serialized_block_name,
		$serialized_attributes,
		$block_content,
		$serialized_block_name
	);
}

/**
 * Returns the content of a block, including comment delimiters, serializing all
 * attributes from the given parsed block.
 *
 * This should be used when preparing a block to be saved to post content.
 * Prefer `render_block` when preparing a block for display. Unlike
 * `render_block`, this does not evaluate a block's `render_callback`, and will
 * instead preserve the markup as parsed.
 *
 * @since 5.3.1
 *
 * @param WP_Block_Parser_Block $block A single parsed block object.
 * @return string String of rendered HTML.
 */
function serialize_block( $block ) {
	$block_content = '';

	$index = 0;
	foreach ( $block['innerContent'] as $chunk ) {
		$block_content .= is_string( $chunk ) ? $chunk : serialize_block( $block['innerBlocks'][ $index++ ] );
	}

	if ( ! is_array( $block['attrs'] ) ) {
		$block['attrs'] = array();
	}

	return get_comment_delimited_block_content(
		$block['blockName'],
		$block['attrs'],
		$block_content
	);
}

/**
 * Returns a joined string of the aggregate serialization of the given parsed
 * blocks.
 *
 * @since 5.3.1
 *
 * @param WP_Block_Parser_Block[] $blocks Parsed block objects.
 * @return string String of rendered HTML.
 */
function serialize_blocks( $blocks ) {
	return implode( '', array_map( 'serialize_block', $blocks ) );
}

/**
 * Filters and sanitizes block content to remove non-allowable HTML from
 * parsed block attribute values.
 *
 * @since 5.3.1
 *
 * @param string         $text              Text that may contain block content.
 * @param array[]|string $allowed_html      An array of allowed HTML elements
 *                                          and attributes, or a context name
 *                                          such as 'post'.
 * @param string[]       $allowed_protocols Array of allowed URL protocols.
 * @return string The filtered and sanitized content result.
 */
function filter_block_content( $text, $allowed_html = 'post', $allowed_protocols = array() ) {
	$result = '';

	$blocks = parse_blocks( $text );
	foreach ( $blocks as $block ) {
		$block   = filter_block_kses( $block, $allowed_html, $allowed_protocols );
		$result .= serialize_block( $block );
	}

	return $result;
}

/**
 * Filters and sanitizes a parsed block to remove non-allowable HTML from block
 * attribute values.
 *
 * @since 5.3.1
 *
 * @param WP_Block_Parser_Block $block             The parsed block object.
 * @param array[]|string        $allowed_html      An array of allowed HTML
 *                                                 elements and attributes, or a
 *                                                 context name such as 'post'.
 * @param string[]              $allowed_protocols Allowed URL protocols.
 * @return array The filtered and sanitized block object result.
 */
function filter_block_kses( $block, $allowed_html, $allowed_protocols = array() ) {
	$block['attrs'] = filter_block_kses_value( $block['attrs'], $allowed_html, $allowed_protocols );

	if ( is_array( $block['innerBlocks'] ) ) {
		foreach ( $block['innerBlocks'] as $i => $inner_block ) {
			$block['innerBlocks'][ $i ] = filter_block_kses( $inner_block, $allowed_html, $allowed_protocols );
		}
	}

	return $block;
}

/**
 * Filters and sanitizes a parsed block attribute value to remove non-allowable
 * HTML.
 *
 * @since 5.3.1
 *
 * @param mixed          $value             The attribute value to filter.
 * @param array[]|string $allowed_html      An array of allowed HTML elements
 *                                          and attributes, or a context name
 *                                          such as 'post'.
 * @param string[]       $allowed_protocols Array of allowed URL protocols.
 * @return array The filtered and sanitized result.
 */
function filter_block_kses_value( $value, $allowed_html, $allowed_protocols = array() ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $key => $inner_value ) {
			$filtered_key   = filter_block_kses_value( $key, $allowed_html, $allowed_protocols );
			$filtered_value = filter_block_kses_value( $inner_value, $allowed_html, $allowed_protocols );

			if ( $filtered_key !== $key ) {
				unset( $value[ $key ] );
			}

			$value[ $filtered_key ] = $filtered_value;
		}
	} elseif ( is_string( $value ) ) {
		return wp_kses( $value, $allowed_html, $allowed_protocols );
	}

	return $value;
}

/**
 * Parses blocks out of a content string, and renders those appropriate for the excerpt.
 *
 * As the excerpt should be a small string of text relevant to the full post content,
 * this function renders the blocks that are most likely to contain such text.
 *
 * Does nothing for calmPress.
 *
 * @since 5.0.0
 *
 * @param string $content The content to parse.
 * @return string The parsed and filtered content.
 */
function excerpt_remove_blocks( $content ) {
	return $content;
}

/**
 * Renders a single block into a HTML string.
 *
 * Returns empty string for calmPress.
 *
 * @since 5.0.0
 *
 * @global WP_Post $post The post to edit.
 *
 * @param array $block A single parsed block object.
 * @return string String of rendered HTML.
 */
function render_block( $block ) {
	return '';
}

/**
 * Parses blocks out of a content string.
 *
 * Does nothing for calmPress.
 *
 * @since 5.0.0
 *
 * @param string $content Post content.
 * @return array Array of parsed block objects.
 */
function parse_blocks( $content ) {
	return $content;
}

/**
 * Parses dynamic blocks out of `post_content` and re-renders them.
 *
 * Does nothing for calmPress.
 *
 * @since 5.0.0
 *
 * @param string $content Post content.
 * @return string Updated post content.
 */
function do_blocks( $content ) {
	return $content;
}

/**
 * Returns the current version of the block format that the content string is using.
 *
 * If the string doesn't contain blocks, it returns 0.
 *
 * Returns 0 for calmPress.
 *
 * @since 5.0.0
 *
 * @param string $content Content to test.
 * @return int The block format version is 1 if the content contains one or more blocks, 0 otherwise.
 */
function block_version( $content ) {
	return 0;
}

/**
 * Registers a new block style.
 *
 * @since 5.3.0
 *
 * @param string $block_name       Block type name including namespace.
 * @param array  $style_properties Array containing the properties of the style name, label, style (name of the stylesheet to be enqueued), inline_style (string containing the CSS to be added).
 *
 * @return boolean True if the block style was registered with success and false otherwise.
 */
function register_block_style( $block_name, $style_properties ) {
	return WP_Block_Styles_Registry::get_instance()->register( $block_name, $style_properties );
}

/**
 * Unregisters a block style.
 *
 * @since 5.3.0
 *
 * @param string $block_name       Block type name including namespace.
 * @param array  $block_style_name Block style name.
 *
 * @return boolean True if the block style was unregistered with success and false otherwise.
 */
function unregister_block_style( $block_name, $block_style_name ) {
	return WP_Block_Styles_Registry::get_instance()->unregister( $block_name, $block_style_name );
}
