<?php
/**
 * Interface for connection credentials with form generation and parameter parsing.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\credentials;

/**
 * Connection credentials that can be entered through a form.
 *
 * @since 1.0.0
 */
interface Connection_Credentials extends Credentials {

	/**
	 * Returns a stream context for accessing files through the connection.
	 *
	 * @since 1.0.0
	 *
	 * @return resource Stream context allowing file overwrite.
	 *
	 * @throws Connection_Exception If there is a connectivity failure with the target server.
	 */
	public function stream_context();

	/**
	 * Generates the HTML for the complete credentials form.
	 *
	 * @since 1.0.0
	 *
	 * @param array $initial_state Values keyed by form input name that override the form defaults. Pass [] for the defaults.
	 * @param string $nonce_name   Nonce action and prefix for its field name.
	 * @param string $submit_label Text displayed on the submit button.
	 *
	 * @return string Form HTML.
	 */
	public static function form( array $initial_state, string $nonce_name, string $submit_label ): string;

	/**
	 * Parse and validate credential fields in the format submitted by form().
	 *
	 * @since 1.0.0
	 *
	 * @param array $vars  Slashed values from the form's submitted fields.
	 * @param array $files Uploaded files keyed by form input name, in the format of $_FILES.
	 *
	 * @return Connection_Credentials_Parameters|\WP_Error Parsed connection values or validation problems.
	 */
	public static function parse_parameters( array $vars, array $files ): Connection_Credentials_Parameters|\WP_Error;
}
