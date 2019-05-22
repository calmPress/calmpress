<?php
/**
 * Implementation of a trivial plugin version.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\plugin;

/**
 * A version for which there is minimal information.
 *
 * @since 1.0.0
 */
class Trivial_Version implements Version {

	/**
	 * The version's version string.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Construct a version based on version number information.
	 *
	 * @since 1.0.0
	 *
	 * @param string $version the version's version number.
	 */
	public function __construct( string $version ) {
		$this->version = $version;
	}

	/**
	 * The version string.
	 *
	 * @since 1.0.0
	 *
	 * @return string The version string.
	 */
	public function version() : string {
		return $this->version;
	}

	/**
	 * The source from which this specific version can be installed. This can be
	 * a URL or path to a zip file.
	 *
	 * @since 1.0.0
	 *
	 * @return string Empty string.
	 */
	public function source() : string {
		return '';
	}

	/**
	 * The minimal version of calmPress required to use the version. An empty string
	 * indicates no declared minimum.
	 *
	 * @since 1.0.0
	 *
	 * @return string Empty string.
	 */
	public function min_calmpress_version() : string {
		return '';
	}

	/**
	 * The maximal version of calmPress the version can be used with. An empty string
	 * indicates no declared maximum.
	 *
	 * @since 1.0.0
	 *
	 * @return string Empty string.
	 */
	public function max_calmpress_version() : string {
		return '';
	}

	/**
	 * The minimal version of PHP required to use the plugin. An empty string
	 * indicates no declared minimum.
	 *
	 * @since 1.0.0
	 *
	 * @return string Empty string.
	 */
	public function min_php_version() : string {
		return '';
	}

	/**
	 * The maximal version of PHP the version can be used with. An empty string
	 * indicates no declared maximum.
	 *
	 * @since 1.0.0
	 *
	 * @return string Empty string.
	 */
	public function max_php_version() : string {
		return '';
	}

	/**
	 * The minimal version of MySQL required to use the version. An empty string
	 * indicates no declared minimum.
	 *
	 * @since 1.0.0
	 *
	 * @return string Empty string.
	 */
	public function min_mysql_version() : string {
		return '';
	}


	/**
	 * The maximal version of MySQL the version can be used with. An empty string
	 * indicates no declared maximum.
	 *
	 * @since 1.0.0
	 *
	 * @return string Empty string.
	 */
	public function max_mysql_version() : string {
		return '';
	}


	/**
	 * The apache modules required for the version to be able to operate. Can be empty
	 * if no module is required.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Empty array.
	 */
	public function required_apache_modules() : array {
		return [];
	}


	/**
	 * The apache modules which are optional but recommended for the version to operate
	 * best. Can be empty if there is no such module.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Empty array.
	 */
	public function optional_apache_modules() : array {
		return [];
	}


	/**
	 * The file relative to the plugin's root directory which contains the
	 * image that can be used as the plugin's decoration image.
	 *
	 * @since 1.0.0
	 *
	 * @return string Empty string.
	 */
	public function image_file() : string {
		return '';
	}

	/**
	 * Indicates whether the plugin versioning follows the semantic versioning rules.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Always false.
	 */
	public function respects_semantic_versioning() : bool {
		return false;
	}

	/**
	 * Install the version from its source URL.
	 *
	 * @since 1.0.0
	 */
	public function install() {}
}
