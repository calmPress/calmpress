<?php
/**
 * Administration API: Validation hooks for screens using the settings API
 *
 * @package calmpress
 * @since 1.0.0
 */

declare(strict_types=1);

// Validate values submitted at the email delivery options page.
add_filter(
	'check_input_errors_email_delivery',
	/**
	 * Add WP_Error for the cases of empty host name when setting SMTP gateway
	 * and sender email address which is not a valid address.
	 * 
	 * For a network site, there is nothing to validate unless user is allowed to set gateway related settings.
	 * 
	 * @since 1.0.0
	 * 
	 * @param \WP_Error $errors  The currently collected errors.
	 * @param array     $options The options subbmitted to the page where the key is the submitted
	 *                           option name (calm_email_delivery) and the value is the various values
	 *                           submitted for it in the input fields.
	 * 
	 * @return \WP_Error $errors to which new errors might have been added. 
	 */
	function ( \WP_Error $errors, $options ): \WP_Error {

		// Nothing to do if site admin can not set gateway related setting.
		if ( ! array_key_exists( 'network_override', $options ) )  {
			return $errors;
		}

		// Check host if getway type is SMTP.
		if ( $options['calm_email_delivery']['type'] === 'smtp' ) {
			if ( empty( $options['calm_email_delivery']['host'] ) ) {
				$errors->add( 'empty_host', __( 'Host has to be specified' ) );
			}
		}

		// Check that sender email address is valid email.
		if ( ! is_email( $options['calm_email_delivery']['from_email'] ) ) {
			$errors->add( 'invalid_sender_email', __( 'Default sender email address is not a valid email address' ) );
		}

		return $errors;
	},
	10,
	2
);

// Validate values submitted at the email delivery options page.
add_filter(
	'check_network_input_errors_email_delivery',
	/**
	 * Add WP_Error for the cases of empty host name when setting SMTP gateway
	 * and sender email address which is not a valid address.
	 * 
	 * @since 1.0.0
	 * 
	 * @param \WP_Error $errors  The currently collected errors.
	 * @param array     $options The options subbmitted to the page where the key is the submitted
	 *                           option name (calm_email_delivery) and the value is the various values
	 *                           submitted for it in the input fields.
	 * 
	 * @return \WP_Error $errors to which new errors might have been added. 
	 */
	function ( \WP_Error $errors, $options ): \WP_Error {

		// Check host if getway type is SMTP.
		if ( $options['calm_email_delivery']['type'] === 'smtp' ) {
			if ( empty( $options['calm_email_delivery']['host'] ) ) {
				$errors->add( 'empty_host', __( 'Host has to be specified' ) );
			}
		}

		// Check that sender email address is valid email.
		if ( ! is_email( $options['calm_email_delivery']['from_email'] ) ) {
			$errors->add( 'invalid_sender_email', __( 'Default sender email address is not a valid email address' ) );
		}

		return $errors;
	},
	10,
	2
);
