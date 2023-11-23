<?php
/**
 * Implementation of an email address "type".
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * A representation of email address related information.
 * 
 * An email address can include two parts, a human readable name and
 * the actuall email address which will be combined to something like
 * "joe <joe@example.com>" in the headers of the email.
 *
 * @since 1.0.0
 */
class Email_Address {

	/**
	 * The humane readable name of the owner of the associated email address.
	 *
	 * @since calmPress 1.0.0
	 */
	private string $name;

	/**
	 * The email address.
	 *
	 * @since calmPress 1.0.0
	 */
	private string $address;

	/**
	 * Create an email address object
	 * 
	 * @since calmPress 1.0.0
	 * 
	 * @param string $address The email address part indicating to where email should
	 *                        be sent.
	 * @param string $name    The human readable name of the person owning the email
	 *                        address. An empty name indicates that the email address
	 *                        do not have any human readable associate name an will
	 *                        result in using something like "joe@example.com" in the
	 *                        emil headers.
	 *                        The name is sanitized. It is trimmed from spaces and
	 *                        line endings (\r\n) are replaced with space if there is
	 *                        none whitespace text around them.
	 *                        The address is sanitized. Space are removed.
	 * 
	 * @throws RunTimeException if email address is invalid
	 */
	public function __construct( string $address, string $name="" ) {
		// white space should be removed
		$this->address = trim( str_replace( ["\r", "\n", "\t", ' '], '', $address ) );

		if ( ! is_email( $this->address ) ) {
			throw new \RuntimeException( 'The email address ' . $this->address . ' is invalid' );
		}

		$this->name = trim( str_replace( "\r\n", ' ', $name ) );

	}

	/**
	 * The human readable name associated with the email address.
	 * 
	 * @since calmPress 1.0.0
	 *
	 * @return string The human readable name associated with the address. 
	 */
	public function name() : string {
		return $this->name;
	}

	/**
	 * The email address.
	 * 
	 * @since calmPress 1.0.0
	 *
	 * @return string The email address. 
	 */
	public function address(): string {
		return $this->address;
	}

	/**
	 * The full address representation as can be used in email headers,
	 * with the notation of "{name}" <{address}>. Quotes are being escaped.
	 * 
	 * @since calmPress 1.0.0
	 *
	 * @return string The full address representation as can be used in email headers. 
	 */
	public function full_address() : string {
		if ( $this->name === '' ) {
			return $this->address;
		}

		return $this->name . ' <' . $this->address .'>'; 
	}
}
