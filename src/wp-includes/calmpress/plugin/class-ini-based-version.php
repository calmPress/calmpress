<?php
/**
 * Implementation of a plugin version information based on ini file.
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
class INI_Based_Version implements Version {

	/**
	 * Hold the settings parsed from the ini string as an associative array of
	 * option => value.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Construct a plugin version information based on an ini file style string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $ini_data The ini file style string to be parsed to get the
	 *               version's settings.
	 */
	public function __construct( string $ini_data ) {
		$settings = parse_ini_string( $ini_data, false, INI_SCANNER_TYPED );
		if ( false === $settings ) {
			$settings = [];
		}

		$settings = wp_parse_args( $args, [
			'version'                      => '',
			'min_php_version'              => '',
			'max_php_version'              => '',
			'min_mysql_version'            => '',
			'max_mysql_version'            => '',
			'min_calmpress_version'        => '',
			'max_calmpress_version'        => '',
			'source_url'                   => '',
			'image_file'                   => '',
			'semantic_versioning'          => false,
			'required_apache_mods'         => '',
			'optional_apache_mods'         => '',
			'respects_semantic_versioning' => false,
		] );

		$settings['required_apache_mods'] = array_map( trim, explode( ',', $settings['required_apache_mods'] ) );
		$settings['optional_apache_mods'] = array_map( trim, explode( ',', $settings['optional_apache_mods'] ) );

		$this->settings = $settings;
	}

	/**
	 * The version string.
	 *
	 * @since 1.0.0
	 *
	 * @return string The version string.
	 */
	public function version() : string {
		return $this->settings['version'];
	}

	/**
	 * The source from which this specific version can be installed. This can be
	 * a URL or path to a zip file.
	 *
	 * @since 1.0.0
	 *
	 * @return string The URL or file path from which the plugin can be fetched,
	 *                otherwise empty string.
	 */
	public function source() : string {
		return $this->settings['source_url'];
	}

	/**
	 * The minimal version of calmPress required to use the version. An empty string
	 * indicates no declared minimum.
	 *
	 * @since 1.0.0
	 *
	 * @return string The version string.
	 */
	public function min_calmpress_version() : string {
		return $this->settings['min_calmpress_version'];
	}

	/**
	 * The maximal version of calmPress the version can be used with. An empty string
	 * indicates no declared maximum.
	 *
	 * @since 1.0.0
	 *
	 * @return string The version string.
	 */
	public function max_calmpress_version() : string {
		return $this->settings['max_calmpress_version'];
	}

	/**
	 * The minimal version of PHP required to use the plugin. An empty string
	 * indicates no declared minimum.
	 *
	 * @since 1.0.0
	 *
	 * @return string The version string.
	 */
	public function min_php_version() : string {
		return $this->settings['min_php_version'];
	}

	/**
	 * The maximal version of PHP the version can be used with. An empty string
	 * indicates no declared maximum.
	 *
	 * @since 1.0.0
	 *
	 * @return string The version string.
	 */
	public function max_php_version() : string {
		return $this->settings['max_php_version'];
	}

	/**
	 * The minimal version of MySQL required to use the version. An empty string
	 * indicates no declared minimum.
	 *
	 * @since 1.0.0
	 *
	 * @return string The version string.
	 */
	public function min_mysql_version() : string {
		return $this->settings['min_mysql_version'];
	}

	/**
	 * The maximal version of MySQL the version can be used with. An empty string
	 * indicates no declared maximum.
	 *
	 * @since 1.0.0
	 *
	 * @return string The version string.
	 */
	public function max_mysql_version() : string {
		return $this->settings['max_mysql_version'];
	}

	/**
	 * The apache modules required for the version to be able to operate. Can be empty
	 * if no module is required.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Array of module names.
	 */
	public function required_apache_modules() : array {
		return $this->settings['required_apache_mods'];
	}

	/**
	 * The apache modules which are optional but recommended for the version to operate
	 * best. Can be empty if there is no such module.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Array of module names.
	 */
	public function optional_apache_modules() : array {
		return $this->settings['optional_apache_mods'];
	}

	/**
	 * The file relative to the plugin's root directory which contains the
	 * image that can be used as the plugin's decoration image.
	 *
	 * @since 1.0.0
	 *
	 * @return string The path to the image if there is one, otherwise empty string.
	 */
	public function image_file() : string {
		return $this->settings['image_file'];
	}

	/**
	 * Indicates whether the plugin versioning follows the semantic versioning rules.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if semantic versioning is used, false otherwise.
	 */
	public function respects_semantic_versioning() : bool {
		return (bool) $this->settings['respects_semantic_versioning'];
	}

	/**
	 * Install the version from its source URL.
	 *
	 * @since 1.0.0
	 */
	public function install() {}
}
