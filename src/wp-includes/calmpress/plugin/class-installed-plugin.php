<?php
/**
 * Interface specification of the plugin class
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\plugin;

/**
 * A representation of an installed plugin.
 *
 * @since 1.0.0
 */
class Installed_Plugin implements Plugin {

	/**
	 * The plugin's data extracted from its header.
	 *
	 * @since 1.0.0
	 *
	 * @var string[]
	 */
	private $data;

	/**
	 * The plugin's root path relative to the plugins directory.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private $path;

	/**
	 * The cache for the installed version information of the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var Version
	 */
	private $version;

	/**
	 * Construct an installed plugin object based on its path relative to the plugin
	 * directory and the plugin's header information.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path The plugin's root path relative to the plugins directory.
	 * @param array  $data The plugin's header information.
	 */
	public function __construct( string $path, array $data ) {
		$this->data = $data;
		$this->path = $path;
	}

	/**
	 * The current plugin's version object.
	 *
	 * @since 1.0.0
	 *
	 * @return Version The version object representing the currently installed
	 *                 version.
	 */
	public function version() : Version {
		// Return the cached version if there is.
		if ( $this->version ) {
			return $this->version;
		}

		/*
		 * Read the settings in the .calmpress.ini file for extended information
		 * about the plugin and the current installed version.
		 */

		$filename = WP_PLUGIN_DIR . '/' . $path . '.calmpress.ini';
		if ( file_exists( $filename ) ) {
			$this->version = new INI_Based_Version( file_get_contents( $filename ) );
		} else {
			$this->version = new Trivial_Version( $this->data['Version'] );
		}

		return $this->version;
	}

	/**
	 * The plugin's slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string The slug.
	 */
	public function slug() : string {
		return dirname( $this->path );
	}

	/**
	 * The plugin's name.
	 *
	 * @since 1.0.0
	 *
	 * @return string The name.
	 */
	public function name() : string {
		return $this->data['Name'];
	}

	/**
	 * The plugin's description.
	 *
	 * @since 1.0.0
	 *
	 * @return string The description.
	 */
	public function description() : string {
		return $this->data['Description'];
	}

	/**
	 * The plugin's state. Can be active or inactive.
	 *
	 * @since 1.0.0
	 *
	 * @return int The state.
	 */
	public function state() : int {
		if ( is_plugin_active( $this->path ) ) {
			return self::ACTIVE;
		}

		return self::INACTIVE;
	}

	/**
	 * The HTML of the decoration image (AKA thumbnail) to be displayed where the information
	 * about the plugin is displayed. An empty string indicates that there is no
	 * such image.
	 *
	 * It is expected that the size will be controlled via CSS.
	 *
	 * @since 1.0.0
	 *
	 * @return string The HTML of the image if there is one, otherwise empty string.
	 */
	public function image_html() : string {
		$version    = $this->version();
		$image_file = $version->image_file();
		if ( $image_file ) {
			return '<img src="' . plugin_url( $image_file, WP_PLUGIN_DIR . '/' . $this->slug() . '/' . $image_file ) . '" alt="">';
		}

		return '';
	}

	/**
	 * Indicates whether the plugin can work in a network environment.
	 *
	 * @since 1.0.0
	 *
	 * @return int A value indicating if the plugin can be used in a network, single site, or both.
	 */
	public function network_compatibility() : int {
		if ( $this->data['Network'] ) {
			return self::COMPATIBLE_BOTH;
		}

		return self::COMPATIBLE_SINGLE_ONLY;
	}

	/**
	 * Indicates whether the plugin versioning follows the semantic versioning rules.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if semantic versioning is used, false otherwise.
	 */
	public function respects_semantic_versioning() : bool {
		return $this->version()->respects_semantic_versioning();
	}

	/**
	 * All the version available for the plugin which are of a higher version than
	 * the current one.
	 *
	 * The array is sorted with the latest version first, oldest last.
	 *
	 * @since 1.0.0
	 *
	 * @return Version[] The versions the plugin can be upgraded to.
	 */
	public function available_upgrades() : array {
		if ( isset( $this->data['PluginURI'] ) ) {
			if ( false !== strpos( 'wordpress.org', $this->data['PluginURI'] ) ) {
				// The plugin is hosted on wordpress.org therefore we can use
				// the plugin api to get related version information.
				$call_api = plugins_api( 'plugin_information', [
					'slug'   => $this->slug(),
					'fields' => [
						'downloaded' => false,
						'rating'     => false,
						'versions'   => true,
					],
				] );
				if ( is_wp_error( $call_api ) ) {
					// Failed getting information, assume it is temporary.
					return [];
				}
			}
		}

		return [];
	}

	/**
	 * Activate the plugin for the current site.
	 *
	 * @since 1.0.0
	 *
	 * @throws Activation_Exception If activation fails.
	 */
	public function activate() {
		$error = activate_plugin( $this->path, '', false, false );

		if ( null === $error ) {
			return;
		}

		throw Activation_Exception( $error->get_error_message() );
	}

	/**
	 * Activate the plugin network wide. Install it first if needed. Nothing is done if already active.
	 *
	 * @since 1.0.0
	 *
	 * @throws Activation_Exception If activation fails.
	 */
	public function network_activate() {
		$error = activate_plugin( $this->path, '', true, false );

		if ( null === $error ) {
			return;
		}

		throw Activation_Exception( $error->get_error_message() );
	}

	/**
	 * Deactivate the plugin for the current site. Nothing happens if it is already inactive.
	 *
	 * @since 1.0.0
	 */
	public function deactivate() {
		deactivate_plugin( $this->path, false, false );
	}

	/**
	 * Deactivate the plugin network wide. Nothing happens if it is already inactive.
	 *
	 * @since 1.0.0
	 */
	public function network_deactivate() {
		deactivate_plugin( $this->path, false, true );
	}

	/**
	 * Uninstall the plugin.
	 *
	 * @since 1.0.0
	 */
	public function uninstall() : array {
	}
}
