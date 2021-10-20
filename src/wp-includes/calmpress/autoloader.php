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
	'calmpress\post_authors\Post_Author'                => __DIR__ . '/post_authors/class-post-author.php',
	'calmpress\post_authors\Post_Authors_As_Taxonomy'   => __DIR__ . '/post_authors/class-post-authors-as-taxonomy.php',
	'calmpress\post_authors\Post_Authors_As_Taxonomy_Db_Upgrade' => __DIR__ . '/post_authors/class-post-authors-as-taxonomy-db-upgrade.php',
	'calmpress\post_authors\Taxonomy_Based_Post_Author' => __DIR__ . '/post_authors/class-taxonomy-based-post-author.php',
	'calmpress\admin\Admin_Notices'                     => __DIR__ . '/admin/class-admin-notices.php',
	'calmpress\filesystem\Path_Lock'                    => __DIR__ . '/filesystem/class-path-lock.php',
	'calmpress\credentials\FTP_Credentials'             => __DIR__ . '/credentials/class-ftp-credentials.php',
	'calmpress\credentials\Credentials'                 => __DIR__ . '/credentials/class-credentials.php',
	'calmpress\credentials\File_Credentials'            => __DIR__ . '/credentials/class-file-credentials.php',
	'calmpress\avatar\Avatar'                           => __DIR__ . '/avatar/class-avatar.php',
	'calmpress\avatar\Blank_Avatar'                     => __DIR__ . '/avatar/class-blank-avatar.php',
	'calmpress\avatar\Has_Avatar'                       => __DIR__ . '/avatar/class-has-avatar.php',
	'calmpress\avatar\Text_Based_Avatar'                => __DIR__ . '/avatar/class-text-based-avatar.php',
	'calmpress\avatar\Image_Based_Avatar'               => __DIR__ . '/avatar/class-image-based-avatar.php',
	'calmpress\avatar\Html_Parameter_Validation'        => __DIR__ . '/avatar/trait-html-parameter-validation.php',
	'calmpress\plugin\Activation_Exception'             => __DIR__ . '/plugin/class-activation-exception.php',
	'calmpress\plugin\INI_Based_Version'                => __DIR__ . '/plugin/class-ini-based-version.php',
	'calmpress\plugin\Installed_Plugin'                 => __DIR__ . '/plugin/class-installed-plugin.php',
	'calmpress\plugin\Installed_Plugins'                => __DIR__ . '/plugin/class-installed-plugins.php',
	'calmpress\plugin\Core_Plugins'                     => __DIR__ . '/plugin/class-core-plugins.php',
	'calmpress\plugin\Plugin'                           => __DIR__ . '/plugin/class-plugin.php',
	'calmpress\plugin\Repository'                       => __DIR__ . '/plugin/class-repository.php',
	'calmpress\plugin\Trivial_Version'                  => __DIR__ . '/plugin/class-trivial-version.php',
	'calmpress\plugin\Version'                          => __DIR__ . '/plugin/class-version.php',
	'calmpress\backup\Backup_Manager'                   => __DIR__ . '/backup/class-backup-manager.php',
	'calmpress\backup\Managed_Backup'                   => __DIR__ . '/backup/class-managed-backup.php',
	'calmpress\backup\Backup'                           => __DIR__ . '/backup/class-backup.php',
	'calmpress\backup\Backup_Storage'                   => __DIR__ . '/backup/class-backup-storage.php',
	'calmpress\backup\Local_Backup_Storage'             => __DIR__ . '/backup/class-local-backup-storage.php',
	'calmpress\backup\Local_Backup'                     => __DIR__ . '/backup/class-local-backup.php',
	'calmpress\calmpress\Paths'                         => __DIR__ . '/calmpress/class-paths.php',
	'calmpress\calmpress\Timteout_Exception'            => __DIR__ . '/calmpress/class-timeout-exception.php',
	'calmpress\wp_config\wp_config'                     => __DIR__ . '/wp_config/class-wp_config.php',
	'calmpress\object_cache\Session_Memory'             => __DIR__ . '/object-cache/class-session-memory.php',
	'calmpress\object_cache\Invalid_Argument_Exception' => __DIR__ . '/object-cache/class-invalid-argument-exception.php',
	'calmpress\object_cache\Psr16_Parameter_Utils'      => __DIR__ . '/object-cache/trait-psr16-parameter-utils.php',
	'calmpress\object_cache\Chained_Caches'             => __DIR__ . '/object-cache/class-chained-caches.php',
	'calmpress\object_cache\Utils'                      => __DIR__ . '/object-cache/class-utils.php',
	'calmpress\apcu\APCu'                               => __DIR__ . '/apcu/class-apcu.php',
	'calmpress\object_cache\APCu'                       => __DIR__ . '/object-cache/class-apcu.php',
	'calmpress\opcache\Opcache'                         => __DIR__ . '/opcache/class-opcache.php',
	'calmpress\opcache\Stats'                           => __DIR__ . '/opcache/class-stats.php',
	'calmpress\object_cache\PHP_File'                   => __DIR__ . '/object-cache/class-php-file.php',
	'calmpress\object_cache\File'                       => __DIR__ . '/object-cache/class-file.php',
	'calmpress\object_cache\Null_Cache'                 => __DIR__ . '/object-cache/class-null-cache.php',
	'calmpress\calmpress\Maintenance_Mode'              => __DIR__ . '/calmpress/class-maintenance-mode.php',
	'calmpress\calmpress\Safe_Mode'                     => __DIR__ . '/calmpress/class-safe-mode.php',
	'calmpress\user\Switch_User'                        => __DIR__ . '/user/class-switch-user.php',
	'Psr\SimpleCache\CacheInterface'                    => ABSPATH . 'wp-includes/Psr/SimpleCache/CacheInterface.php',
	'Psr\SimpleCache\CacheException'                    => ABSPATH . 'wp-includes/Psr/SimpleCache/CacheException.php',
	'Psr\SimpleCache\InvalidArgumentException'          => ABSPATH . 'wp-includes/Psr/SimpleCache/InvalidArgumentException.php',
];

spl_autoload_register(
	function ( string $classname ) {
		if ( isset( MAP[ $classname ] ) ) {
			require MAP[ $classname ];
		}
	}
);
