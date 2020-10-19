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
 * @param string|WP_Block_Type $name Block type name including namespace, or alternatively
 *                                   a complete WP_Block_Type instance. In case a WP_Block_Type
 *                                   is provided, the $args parameter will be ignored.
 * @param array                $args {
 *     Optional. Array of block type arguments. Accepts any public property of `WP_Block_Type`.
 *     Any arguments may be defined, however the ones described below are supported by default.
 *     Default empty array.
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
 * @param string|WP_Block_Type $name Block type name including namespace, or alternatively
 *                                   a complete WP_Block_Type instance.
 * @return WP_Block_Type|false The unregistered block type on success, or false on failure.
 */
function unregister_block_type( $name ) {
	false;
}

/**
 * Removes the block asset's path prefix if provided.
 *
 * @since 5.5.0
 *
 * @param string $asset_handle_or_path Asset handle or prefixed path.
 * @return string Path without the prefix or the original value.
 */
function remove_block_asset_path_prefix( $asset_handle_or_path ) {
	$path_prefix = 'file:';
	if ( 0 !== strpos( $asset_handle_or_path, $path_prefix ) ) {
		return $asset_handle_or_path;
	}
	return substr(
		$asset_handle_or_path,
		strlen( $path_prefix )
	);
}

/**
 * Generates the name for an asset based on the name of the block
 * and the field name provided.
 *
 * @since 5.5.0
 *
 * @param string $block_name Name of the block.
 * @param string $field_name Name of the metadata field.
 * @return string Generated asset name for the block's field.
 */
function generate_block_asset_handle( $block_name, $field_name ) {
	$field_mappings = array(
		'editorScript' => 'editor-script',
		'script'       => 'script',
		'editorStyle'  => 'editor-style',
		'style'        => 'style',
	);
	return str_replace( '/', '-', $block_name ) .
		'-' . $field_mappings[ $field_name ];
}

/**
 * Finds a script handle for the selected block metadata field. It detects
 * when a path to file was provided and finds a corresponding asset file
 * with details necessary to register the script under automatically
 * generated handle name. It returns unprocessed script handle otherwise.
 *
 * @since 5.5.0
 *
 * @param array  $metadata   Block metadata.
 * @param string $field_name Field name to pick from metadata.
 * @return string|bool Script handle provided directly or created through
 *                     script's registration, or false on failure.
 */
function register_block_script_handle( $metadata, $field_name ) {
	if ( empty( $metadata[ $field_name ] ) ) {
		return false;
	}
	$script_handle = $metadata[ $field_name ];
	$script_path   = remove_block_asset_path_prefix( $metadata[ $field_name ] );
	if ( $script_handle === $script_path ) {
		return $script_handle;
	}

	$script_handle     = generate_block_asset_handle( $metadata['name'], $field_name );
	$script_asset_path = realpath(
		dirname( $metadata['file'] ) . '/' .
		substr_replace( $script_path, '.asset.php', - strlen( '.js' ) )
	);
	if ( ! file_exists( $script_asset_path ) ) {
		$message = sprintf(
			/* translators: %1: field name. %2: block name */
			__( 'The asset file for the "%1$s" defined in "%2$s" block definition is missing.', 'default' ),
			$field_name,
			$metadata['name']
		);
		_doing_it_wrong( __FUNCTION__, $message, '5.5.0' );
		return false;
	}
	$script_asset = require $script_asset_path;
	$result       = wp_register_script(
		$script_handle,
		plugins_url( $script_path, $metadata['file'] ),
		$script_asset['dependencies'],
		$script_asset['version']
	);
	return $result ? $script_handle : false;
}

/**
 * Finds a style handle for the block metadata field. It detects when a path
 * to file was provided and registers the style under automatically
 * generated handle name. It returns unprocessed style handle otherwise.
 *
 * @since 5.5.0
 *
 * @param array  $metadata Block metadata.
 * @param string $field_name Field name to pick from metadata.
 * @return string|boolean Style handle provided directly or created through
 *                        style's registration, or false on failure.
 */
function register_block_style_handle( $metadata, $field_name ) {
	if ( empty( $metadata[ $field_name ] ) ) {
		return false;
	}
	$style_handle = $metadata[ $field_name ];
	$style_path   = remove_block_asset_path_prefix( $metadata[ $field_name ] );
	if ( $style_handle === $style_path ) {
		return $style_handle;
	}

	$style_handle = generate_block_asset_handle( $metadata['name'], $field_name );
	$block_dir    = dirname( $metadata['file'] );
	$result       = wp_register_style(
		$style_handle,
		plugins_url( $style_path, $metadata['file'] ),
		array(),
		filemtime( realpath( "$block_dir/$style_path" ) )
	);
	return $result ? $style_handle : false;
}

/**
 * Registers a block type from metadata stored in the `block.json` file.
 *
 * @since 5.5.0
 *
 * @param string $file_or_folder Path to the JSON file with metadata definition for
 *                               the block or path to the folder where the `block.json` file is located.
 * @param array  $args {
 *     Optional. Array of block type arguments. Accepts any public property of `WP_Block_Type`.
 *     Any arguments may be defined, however the ones described below are supported by default.
 *     Default empty array.
 *
 *     @type callable $render_callback Callback used to render blocks of this block type.
 * }
 * @return WP_Block_Type|false The registered block type on success, or false on failure.
 */
function register_block_type_from_metadata( $file_or_folder, $args = array() ) {
	$filename      = 'block.json';
	$metadata_file = ( substr( $file_or_folder, -strlen( $filename ) ) !== $filename ) ?
		trailingslashit( $file_or_folder ) . $filename :
		$file_or_folder;
	if ( ! file_exists( $metadata_file ) ) {
		return false;
	}

	$metadata = json_decode( file_get_contents( $metadata_file ), true );
	if ( ! is_array( $metadata ) || empty( $metadata['name'] ) ) {
		return false;
	}
	$metadata['file'] = $metadata_file;

	$settings          = array();
	$property_mappings = array(
		'title'           => 'title',
		'category'        => 'category',
		'parent'          => 'parent',
		'icon'            => 'icon',
		'description'     => 'description',
		'keywords'        => 'keywords',
		'attributes'      => 'attributes',
		'providesContext' => 'provides_context',
		'usesContext'     => 'uses_context',
		'supports'        => 'supports',
		'styles'          => 'styles',
		'example'         => 'example',
	);

	foreach ( $property_mappings as $key => $mapped_key ) {
		if ( isset( $metadata[ $key ] ) ) {
			$settings[ $mapped_key ] = $metadata[ $key ];
		}
	}

	if ( ! empty( $metadata['editorScript'] ) ) {
		$settings['editor_script'] = register_block_script_handle(
			$metadata,
			'editorScript'
		);
	}

	if ( ! empty( $metadata['script'] ) ) {
		$settings['script'] = register_block_script_handle(
			$metadata,
			'script'
		);
	}

	if ( ! empty( $metadata['editorStyle'] ) ) {
		$settings['editor_style'] = register_block_style_handle(
			$metadata,
			'editorStyle'
		);
	}

	if ( ! empty( $metadata['style'] ) ) {
		$settings['style'] = register_block_style_handle(
			$metadata,
			'style'
		);
	}

	return register_block_type(
		$metadata['name'],
		array_merge(
			$settings,
			$args
		)
	);
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
 *
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
 *
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
 * @return string[] Array of dynamic block names.
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
 * @param array $block_attributes Attributes object.
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
 * @param string $block_name       Block name.
 * @param array  $block_attributes Block attributes.
 * @param string $block_content    Block save content.
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
 * @since calmPress 1.0.0 returns empty string
 *
 * @param array $parsed_block A single parsed block object.
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
 * @return array[] Array of parsed block objects.
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
 * @param array  $style_properties Array containing the properties of the style name,
 *                                 label, style (name of the stylesheet to be enqueued),
 *                                 inline_style (string containing the CSS to be added).
 * @return boolean True if the block style was registered with success and false otherwise.
 */
function register_block_style( $block_name, $style_properties ) {
	return false;
}

/**
 * Unregisters a block style.
 *
 * @since 5.3.0
 *
 * @param string $block_name       Block type name including namespace.
 * @param array  $block_style_name Block style name.
 * @return boolean True if the block style was unregistered with success and false otherwise.
 */
function unregister_block_style( $block_name, $block_style_name ) {
	return false;
}
