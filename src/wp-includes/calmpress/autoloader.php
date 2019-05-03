<?php
/**
 * Implementation of autoloader
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\autoloader;

/*
 * A map of classes to files to be used when determining which file to load
 * when a class needs to be loaded.
 *
 * The index is the fully qualified class name which includes the namespace,
 * the value is the path to the relevant file.
 */
const MAP = [
	'calmpress\post_authors\Post_Author'                => __DIR__ . '/class-post-author.php',
	'calmpress\post_authors\Post_Authors_As_Taxonomy'   => __DIR__ . '/class-post-authors-as-taxonomy.php',
	'calmpress\post_authors\Post_Authors_As_Taxonomy_Db_Upgrade' => __DIR__ . '/class-post-authors-as-taxonomy-db-upgrade.php',
	'calmpress\post_authors\Taxonomy_Based_Post_Author' => __DIR__ . '/class-taxonomy-based-post-author.php',
	'calmpress\admin\Admin_Notices'                     => __DIR__ . '/class-admin-notices.php',
	'calmpress\filesystem\Locked_File_Access'           => __DIR__ . '/filesystem/class-locked-file-access.php',
	'calmpress\filesystem\Locked_File_Exception'        => __DIR__ . '/filesystem/class-locked-file-exception.php',
	'calmpress\filesystem\Locked_File_Direct_Access'    => __DIR__ . '/filesystem/class-locked-file-direct-access.php',
	'calmpress\filesystem\Locked_File_FTP_Write_Access' => __DIR__ . '/filesystem/class-locked-file-ftp-write-access.php',
	'calmpress\credentials\FTP_Credentials'             => __DIR__ . '/class-ftp-credentials.php',
	'calmpress\avatar\Avatar'                           => __DIR__ . '/avatar/class-avatar.php',
	'calmpress\avatar\Blank_Avatar'                     => __DIR__ . '/avatar/class-blank-avatar.php',
	'calmpress\avatar\Has_Avatar'                       => __DIR__ . '/avatar/class-has-avatar.php',
	'calmpress\avatar\Text_Based_Avatar'                => __DIR__ . '/avatar/class-text-based-avatar.php',
];

spl_autoload_register(
	function ( string $classname ) {
		if ( isset( MAP[ $classname ] ) ) {
			require MAP[ $classname ];
		}
	}
);
