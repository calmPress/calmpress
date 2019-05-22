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
 * An abstract representation of a plugin.
 *
 * @since 1.0.0
 */
interface Plugin {
	/*
	 * Possible plugin states.
	 */
	const ACTIVE        = 1;
	const INACTIVE      = 2;
	const NOT_INSTALLED = 3;

	/*
	 * Possible network compatibility states.
	 */
	const COMPATIBLE_SINGLE_ONLY  = 1;
	const COMPATIBLE_NETWORK_ONLY = 2;
	const COMPATIBLE_BOTH         = 3;

	/**
	 * The current plugin's version object.
	 *
	 * @since 1.0.0
	 *
	 * @return Version The version object representing the currently installed
	 *                        version.
	 */
	public function version() : Version;

	/**
	 * The plugin's slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string The name.
	 */
	public function slug() : string;

	/**
	 * The plugin's name.
	 *
	 * @since 1.0.0
	 *
	 * @return string The name.
	 */
	public function name() : string;

	/**
	 * The plugin's description.
	 *
	 * @since 1.0.0
	 *
	 * @return string The description.
	 */
	public function description() : string;

	/**
	 * The plugin's state. Can be active, inactive or uninstalled.
	 *
	 * @since 1.0.0
	 *
	 * @return int The state.
	 */
	public function state() : int;

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
	public function image_html() : string;

	/**
	 * Indicates whether the plugin can work in a network environment.
	 *
	 * @since 1.0.0
	 *
	 * @return int A value indicating if the plugin can be used in a network, single site, or both.
	 */
	public function network_compatibility() : int;

	/**
	 * Indicates whether the plugin versioning follows the semantic versioning rules.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if semantic versioning is used, false otherwise.
	 */
	public function respects_semantic_versioning() : bool;

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
	public function available_upgrades() : array;

	/**
	 * Activate the plugin for the current site. Install it first if needed. Nothing is done if already active.
	 *
	 * @since 1.0.0
	 */
	public function activate();

	/**
	 * Activate the plugin network wide. Install it first if needed. Nothing is done if already active.
	 *
	 * @since 1.0.0
	 */
	public function network_activate();

	/**
	 * Deactivate the plugin. Nothing happens if it is not installed or already inactive.
	 *
	 * @since 1.0.0
	 */
	public function deactivate();

	/**
	 * Deactivate the plugin network wide. Nothing happens if it is uninstalled or already inactive.
	 *
	 * @since 1.0.0
	 */
	public function network_deactivate();

	/**
	 * Uninstall the plugin.
	 *
	 * @since 1.0.0
	 */
	public function uninstall();
}
