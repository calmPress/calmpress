<?php
/**
 * Remote filesystem access interface with a connection settings form.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\filesystem;

/**
 * Remote filesystem access configured through a connection settings form.
 *
 * @since 1.0.0
 */
interface Remote_Filesystem_Access extends Filesystem_Access {

	/**
	 * Returns a stream context for accessing files through the connection.
	 *
	 * @since 1.0.0
	 *
	 * @return resource Stream context allowing file overwrite.
	 *
	 * @throws Filesystem_Access_Exception If preparing remote filesystem access fails.
	 */
	public function stream_context();

	/**
	 * Generates the HTML for the complete credentials form.
	 *
	 * @since 1.0.0
	 *
	 * @param array $initial_state Values keyed by form input name that override the form defaults. Pass [] for defaults and browser-stored prefilling.
	 * @param string|null $nonce_name Nonce action and prefix for its field name, or null to omit the nonce field.
	 * @param string $submit_label Text displayed on the submit button.
	 * @param string $action_url Form submission URL.
	 *
	 * @return string Form HTML.
	 */
	public static function form( array $initial_state, ?string $nonce_name, string $submit_label, string $action_url ): string;

	/**
	 * Parse and validate credential fields in the format submitted by form().
	 *
	 * @since 1.0.0
	 *
	 * @param array $vars  Unslashed values from the form's submitted fields.
	 *
	 * @return Connection_Credentials_Parameters|\WP_Error Parsed connection values or validation problems.
	 *
	 * @throws \LogicException If required fields are missing, have unexpected types, or specify an unsupported selection.
	 */
	public static function parse_parameters( array $vars ): Connection_Credentials_Parameters|\WP_Error;

	/**
	 * Parse submitted credentials, verify remote installation access, and test write
	 * access to the supplied directory.
	 *
	 * @since 1.0.0
	 *
	 * @param array $vars Unslashed form values.
	 * @param string $directory Absolute directory requiring write access.
	 *
	 * @return Remote_Filesystem_Access|\WP_Error Filesystem access on success, or validation and connection errors on failure.
	 *
	 * @throws \LogicException If required parameters are missing or malformed.
	 */
	public static function writable_directory_access( array $vars, string $directory ): Remote_Filesystem_Access|\WP_Error;

}
