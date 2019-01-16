<?php
/**
 * Implementation of autoloader
 *
 * @package calmPress
 * @since 1.0.0
 */

namespace calmpress\autoloader;

/*
 * A map of classes to files to be used when determining which file to load
 * when a class needs to be loaded.
 *
 * The index is the fully qualified class name which includes the namespace,
 * the value is the path to the relevant file.
 */
const MAP = [
	'calmpress\post_authors\Post_Author' => __DIR__ . '\class-post-author.php',
	'calmpress\post_authors\Post_Authors_As_Taxonomy' => __DIR__ . '\class-post-authors-as-taxonomy.php',
	'calmpress\post_authors\Post_Taxonomy_Author' => __DIR__ . '\class-post-taxonomy-author.php',
];

spl_autoload_register( function ( string $classname ) {
	if ( isset( MAP[ $classname ] ) ) {
		require MAP[ $classname ];
	}
} );
