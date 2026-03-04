<?php
/**
 * Functions related to registering and parsing blocks.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.0.0
 */

/**
 * Finds a script handle for the selected block metadata field. It detects
 * when a path to file was provided and optionally finds a corresponding asset
 * file with details necessary to register the script under automatically
 * generated handle name. It returns unprocessed script handle otherwise.
 *
 * @since 5.5.0
 * @since calmPress 1.0.0 Does nothing.
 *
 * @param array  $metadata   Block metadata.
 * @param string $field_name Field name to pick from metadata.
 * @param int    $index      Optional. Index of the script to register when multiple items passed.
 *                           Default 0.
 * @return string|false Script handle provided directly or created through
 *                      script's registration, or false on failure.
 */
function register_block_script_handle( $metadata, $field_name, $index = 0 ) {
}

/**
 * Finds a style handle for the block metadata field. It detects when a path
 * to file was provided and registers the style under automatically
 * generated handle name. It returns unprocessed style handle otherwise.
 *
 * @since 5.5.0
 * @since calmPress 1.0.0 Does nothing, just return false.
 *
 * @param array  $metadata   Block metadata.
 * @param string $field_name Field name to pick from metadata.
 * @param int    $index      Optional. Index of the style to register when multiple items passed.
 *                           Default 0.
 * @return string|false Style handle provided directly or created through
 *                      style's registration, or false on failure.
 */
function register_block_style_handle( $metadata, $field_name, $index = 0 ) {
	return false;
}

/**
 * Gets i18n schema for block's metadata read from `block.json` file.
 *
 * @since 5.9.0
 *
 * @return object The schema for block's metadata.
 */
function get_block_metadata_i18n_schema() {
	static $i18n_block_schema;

	if ( ! isset( $i18n_block_schema ) ) {
		$i18n_block_schema = wp_json_file_decode( __DIR__ . '/block-i18n.json' );
	}

	return $i18n_block_schema;
}

/**
 * Registers all block types from a block metadata collection.
 *
 * This can either reference a previously registered metadata collection or, if the `$manifest` parameter is provided,
 * register the metadata collection directly within the same function call.
 *
 * @since 6.8.0
 * @see wp_register_block_metadata_collection()
 * @see register_block_type_from_metadata()
 *
 * @param string $path     The absolute base path for the collection ( e.g., WP_PLUGIN_DIR . '/my-plugin/blocks/' ).
 * @param string $manifest Optional. The absolute path to the manifest file containing the metadata collection, in
 *                         order to register the collection. If this parameter is not provided, the `$path` parameter
 *                         must reference a previously registered block metadata collection.
 */
function wp_register_block_types_from_metadata_collection( $path, $manifest = '' ) {
	if ( $manifest ) {
		wp_register_block_metadata_collection( $path, $manifest );
	}

	$block_metadata_files = WP_Block_Metadata_Registry::get_collection_block_metadata_files( $path );
	foreach ( $block_metadata_files as $block_metadata_file ) {
		register_block_type_from_metadata( $block_metadata_file );
	}
}

/**
 * Registers a block metadata collection.
 *
 * This function allows core and third-party plugins to register their block metadata
 * collections in a centralized location. Registering collections can improve performance
 * by avoiding multiple reads from the filesystem and parsing JSON.
 *
 * @since 6.7.0
 *
 * @param string $path     The base path in which block files for the collection reside.
 * @param string $manifest The path to the manifest file for the collection.
 */
function wp_register_block_metadata_collection( $path, $manifest ) {
	WP_Block_Metadata_Registry::register_collection( $path, $manifest );
}

/**
 * Registers a block type from the metadata stored in the `block.json` file.
 *
 * @since 5.5.0
 * @since calmPress 1.0.0 Does nothing, returns false.
 *
 * @return false
 */
function register_block_type_from_metadata() {
	return false;
}

/**
 * Registers a block type. The recommended way is to register a block type using
 * the metadata stored in the `block.json` file.
 *
 * @since 5.0.0
 * @since 5.8.0 First param accepts a path to the `block.json` file.
 * 
 * @sinc3 calmPress 1.0.0 Does nothing.
 *
 * @param string|WP_Block_Type $block_type Block type name including namespace, or alternatively
 *                                         a path to the JSON file with metadata definition for the block,
 *                                         or a path to the folder where the `block.json` file is located,
 *                                         or a complete WP_Block_Type instance.
 *                                         In case a WP_Block_Type is provided, the $args parameter will be ignored.
 * @param array                $args       Optional. Array of block type arguments. Accepts any public property
 *                                         of `WP_Block_Type`. See WP_Block_Type::__construct() for information
 *                                         on accepted arguments. Default empty array.
 *
 * @return WP_Block_Type|false The registered block type on success, or false on failure.
 */
function register_block_type( $block_type, $args = array() ) {
}

/**
 * Unregisters a block type.
 *
 * @since 5.0.0
 * @since calmPress 1.0.0 Does nothing.
 *
 * @param string|WP_Block_Type $name Block type name including namespace, or alternatively
 *                                   a complete WP_Block_Type instance.
 * @return WP_Block_Type|false The unregistered block type on success, or false on failure.
 */
function unregister_block_type( $name ) {
}

/**
 * Determines whether a post or content string has blocks.
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
 * @param int|string|WP_Post|null $post Optional. Post content, post ID, or post object.
 *                                      Defaults to global $post.
 * @return bool Whether the post has blocks.
 */
function has_blocks( $post = null ) {
	return false;
}

/**
 * Determines whether a $post or a string contains a specific block type.
 *
 * This test optimizes for performance rather than strict accuracy, detecting
 * whether the block type exists but not validating its structure and not checking
 * synced patterns (formerly called reusable blocks). For strict accuracy,
 * you should use the block parser on post content.
 *
 * Always returns false for calmPress.
 *
 * @since 5.0.0
 *
 * @see parse_blocks()
 *
 * @param string                  $block_name Full block type to look for.
 * @param int|string|WP_Post|null $post       Optional. Post content, post ID, or post object.
 *                                            Defaults to global $post.
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
	return [];
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
 * @param array $parsed_block {
 *     An associative array of the block being rendered. See WP_Block_Parser_Block.
 *
 *     @type string|null $blockName    Name of block.
 *     @type array       $attrs        Attributes from block comment delimiters.
 *     @type array[]     $innerBlocks  List of inner blocks. An array of arrays that
 *                                     have the same structure as this one.
 *     @type string      $innerHTML    HTML from inside block comment delimiters.
 *     @type array       $innerContent List of string fragments and null markers where
 *                                     inner blocks were found.
 * }
 * @return string String of rendered HTML.
 */
function render_block( $block ) {
	return '';
}

/**
 * Parses blocks out of a content string.
 *
 * Given an HTML document, this function fully-parses block content, producing
 * a tree of blocks and their contents, as well as top-level non-block content,
 * which will appear as a block with no `blockName`.
 *
 * This function can be memory heavy for certain documents, particularly those
 * with deeply-nested blocks or blocks with extensive attribute values. Further,
 * this function must parse an entire document in one atomic operation.
 *
 * If the entire parsed document is not necessary, consider using {@see WP_Block_Processor}
 * instead, as it provides a streaming and low-overhead interface for finding blocks.
 *
 * @since 5.0.0
 * @since calmPress 1.0.0 returns empty array.
 * 
 * @param string $content Post content.
 * @return array[] {
 *     Array of block structures.
 *
 *     @type array ...$0 {
 *         An associative array of a single parsed block object. See WP_Block_Parser_Block.
 *
 *         @type string|null $blockName    Name of block.
 *         @type array       $attrs        Attributes from block comment delimiters.
 *         @type array[]     $innerBlocks  List of inner blocks. An array of arrays that
 *                                         have the same structure as this one.
 *         @type string      $innerHTML    HTML from inside block comment delimiters.
 *         @type array       $innerContent List of string fragments and null markers where
 *                                         inner blocks were found.
 *     }
 * }
 */
function parse_blocks( $content ) {
	return [];
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
 * @since 6.6.0 Added support for registering styles for multiple block types.
 *
 * @link https://developer.wordpress.org/block-editor/reference-guides/block-api/block-styles/
 *
 * @param string|string[] $block_name       Block type name including namespace or array of namespaced block type names.
 * @param array           $style_properties Array containing the properties of the style name, label,
 *                                          style_handle (name of the stylesheet to be enqueued),
 *                                          inline_style (string containing the CSS to be added),
 *                                          style_data (theme.json-like array to generate CSS from).
 *                                          See WP_Block_Styles_Registry::register().
 * @return bool True if the block style was registered with success and false otherwise.
 */
function register_block_style( $block_name, $style_properties ) {
	return false;
}

/**
 * Unregisters a block style.
 * 
 * for calmPress always returns true.
 *
 * @since 5.3.0
 *
 * @param string $block_name       Block type name including namespace.
 * @param string $block_style_name Block style name.
 * @return bool True if the block style was unregistered with success and false otherwise.
 */
function unregister_block_style( $block_name, $block_style_name ) {
	return true;
}
