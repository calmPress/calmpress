<?php
/**
 * Server-side rendering of the `core/block` block.
 *
 * @package WordPress
 */

/**
 * Renders the `core/block` block on server.
 *
 * @param array $attributes The block attributes.
 *
 * @return string Rendered HTML of the referenced block.
 */
function render_block_core_block( $attributes ) {
	if ( empty( $attributes['ref'] ) ) {
		return '';
	}

	return do_blocks( $reusable_block->post_content );
}

register_block_type(
	'core/block',
	array(
		'attributes'      => array(
			'ref' => array(
				'type' => 'number',
			),
		),

		'render_callback' => 'render_block_core_block',
	)
);
