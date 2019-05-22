<?php
/**
 * Interface specification of the plugin version class
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\plugin;

/**
 * An abstract representation of a specific version of a plugin.
 *
 * @since 1.0.0
 */
interface Version {

	/**
	 * The version string.
	 *
	 * @since 1.0.0
	 *
	 * @return string The version string.
	 */
	public function version() : string;

	/**
	 * The source from which this specific version can be installed. This can be
	 * a URL or path to a zip file.
	 *
	 * @since 1.0.0
	 *
	 * @return string The URL or file path from which the plugin can be fetched,
	 *                otherwise empty string.
	 */
	public function source() : string;

	/**
	 * The minimal version of calmPress required to use the version. An empty string
	 * indicates no declared minimum.
	 *
	 * @since 1.0.0
	 *
	 * @return string The version string.
	 */
	public function min_calmpress_version() : string;

	/**
	 * The maximal version of calmPress the version can be used with. An empty string
	 * indicates no declared maximum.
	 *
	 * @since 1.0.0
	 *
	 * @return string The version string.
	 */
	public function max_calmpress_version() : string;

	/**
	 * The minimal version of PHP required to use the plugin. An empty string
	 * indicates no declared minimum.
	 *
	 * @since 1.0.0
	 *
	 * @return string The version string.
	 */
	public function min_php_version() : string;

	/**
	 * The maximal version of PHP the version can be used with. An empty string
	 * indicates no declared maximum.
	 *
	 * @since 1.0.0
	 *
	 * @return string The version string.
	 */
	public function max_php_version() : string;

	/**
	 * The minimal version of MySQL required to use the version. An empty string
	 * indicates no declared minimum.
	 *
	 * @since 1.0.0
	 *
	 * @return string The version string.
	 */
	public function min_mysql_version() : string;

	/**
	 * The maximal version of MySQL the version can be used with. An empty string
	 * indicates no declared maximum.
	 *
	 * @since 1.0.0
	 *
	 * @return string The version string.
	 */
	public function max_mysql_version() : string;

	/**
	 * The apache modules required for the version to be able to operate. Can be empty
	 * if no module is required.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Array of module names.
	 */
	public function required_apache_modules() : array;

	/**
	 * The apache modules which are optional but recommended for the version to operate
	 * best. Can be empty if there is no such module.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Array of module names.
	 */
	public function optional_apache_modules() : array;

	/**
	 * The file relative to the plugin's root directory which contains the
	 * image that can be used as the plugin's decoration image.
	 *
	 * @since 1.0.0
	 *
	 * @return string The path to the image if there is one, otherwise empty string.
	 */
	public function image_file() : string;

	/**
	 * Indicates whether the plugin versioning follows the semantic versioning rules.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if semantic versioning is used, false otherwise.
	 */
	public function respects_semantic_versioning() : bool;

	/**
	 * Install the version from its source URL.
	 *
	 * @since 1.0.0
	 */
	public function install() : array;
}
