<?php
/**
 * Implementation of an utility class that provides access to email related settings as
 * configured for the site and when needed network.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * A representation of email sender settings information.
 * @since 1.0.0
 */
class Email_Settings {

	/**
	 * The type of the gateway being used either
	 * - 'local' indicating usage of mail()
	 * - 'smtp'  indicating usage of SMTP server.
	 * 
	 * @since 1.0.0
	 */
	private readonly string $type;

	/**
	 * The URL of the SMTP host to be used.
	 * 
	 * Undefined if SMTP is not used.
	 * 
	 * @since 1.0.0
	 */
	private readonly string $smtp_host;

	/**
	 * The user to use to authenticate with of the SMTP server.
	 * 
	 * Undefined if SMTP is not used.
	 * 
	 * @since 1.0.0
	 */
	private readonly string $smtp_user;

	/**
	 * The password to use to authenticate with of the SMTP server.
	 * 
	 * Undefined if SMTP is not used.
	 * 
	 * @since 1.0.0
	 */
	private readonly string $smtp_password;

	/**
	 * The email address to use as the sender.
	 * 
	 * @since 1.0.0
	 */
	private readonly Email_Address $sender;

	/**
	 * The type of the gateway being used either
	 * - 'no'          indicating no loging of successful emails.
	 * - 'recipients'  indicating logging of the recieoients of successful emails.
	 * - 'full'        indicating logging of the full content of successful emails.
	 * 
	 * @since 1.0.0
	 */
	private readonly string $log_type;

	/**
	 * Construct the object based on the value of the relevant options.
	 * 
	 * For network sites values related to getway and sender email are based on network
	 * configuration unless it is indicated it is overriden by the specific site.
	 * 
	 * The name of the sender in a network can be set for a specific site. When the site do not
	 * have one set the value set for the network is used.
	 * 
	 * If no name is given for the sender "calmPress" is used.
	 * 
	 * @since 1.0.0
	 */
	public function __construct() {
		$options   = self::validate_option_value( get_option( 'calm_email_delivery' ) );
		$from_name = $options['from_name'];

		// Logging verbosity always set at site option.
		$verbosity = $options['verbosity'];

		if ( is_multisite() ) {
			// If a site in the network, use network settings if site is not allowed to override
			// them.
			if ( ! array_key_exists( 'network_override', $options ) ) {
				$options = self::validate_option_value( get_site_option( 'calm_email_delivery' ) );
			}

			// If sender name is not specified at site options, use the network one
			if ( empty( $from_name ) ) {
				$from_name = $options['from_name'];
			}
		}

		// If no from name is specified use calmPress.
		if ( empty( $from_name ) ) {
			$from_name = 'calmPress';
		}

		$this->type          = $options['type'];
		$this->smtp_host     = $options['host'];
		$this->smtp_user     = $options['user'];
		$this->smtp_password = $options['password'];
		$this->sender        = new Email_Address( $options['from_email'], $from_name );
		$this->log_type      = $verbosity;
	}

	/**
	 * Throw if settings do not use SMTP as transport.
	 * 
	 * @since 1.0.0
	 * 
	 * @throws \LogicException If setting do not use SMTP as transport.
	 */
	private function throw_if_not_smtp(): void {
		if ( ! $this->is_smtp() ) {
			throw new \LogicException(
				'SMTP settings requested for non-SMTP transport.'
			);
		}
	}

	/**
	 * Indicate wheather the gateway being used is local software (php mail function).
	 * 
	 * @since 1.0.0
	 * 
	 * @return true if gateway is local, false otherwise.
	 */
	public function is_local(): bool {
		return $this->type === 'local';
	}

	/**
	 * Indicate wheather the gateway being used is an SMTP server.
	 * 
	 * @since 1.0.0
	 * 
	 * @return true if gateway is SMTP server, false otherwise.
	 */
	public function is_smtp(): bool {
		return $this->type === 'smtp';		
	}

	/**
	 * The SMTP gateway's host URL.
	 * 
	 * @since 1.0.0
	 * 
	 * @throws \LogicException If setting do not use SMTP as transport.
	 */
	public function smtp_host(): string {
		$this->throw_if_not_smtp();
		return $this->smtp_host;
	}

	/**
	 * The SMTP gateway's user.
	 * 
	 * @since 1.0.0
	 * 
	 * @throws \LogicException If setting do not use SMTP as transport.
	 */
	public function smtp_user(): string {
		$this->throw_if_not_smtp();
		return $this->smtp_user;
	}

	/**
	 * The SMTP gateway's password.
	 * 
	 * @since 1.0.0
	 * 
	 * @throws \LogicException If setting do not use SMTP as transport.
	 */
	public function smtp_password(): string {
		$this->throw_if_not_smtp();
		return $this->smtp_password;
	}

	/**
	 * The sender's email address.
	 * 
	 * @since 1.0.0
	 * 
	 * @return Email_Address The email address to use as sender.
	 */
	public function sender(): Email_Address {
		return $this->sender;
	}

	/**
	 * Indicate if reciepient of successfuly sent email should be logged.
	 * 
	 * @since 1.0.0
	 * 
	 * @return bool true if recipients should be logged, false otherwise.
	 */
	public function log_succesful_email(): bool {
		return $this->log_type !== 'no';
	}

	/**
	 * Indicate if the content of successfuly sent email should be logged.
	 * 
	 * @since 1.0.0
	 * 
	 * @return bool true if content should be logged, false otherwise.
	 */
	public function log_content(): bool {
		return $this->log_type === 'full';
	}

	/**
	 * Validate that the option value matches expected format and values in keys and normalize if needed.
	 * 
	 * @since 1.0.0
	 * 
	 * @param mixed $value The value to validate.
	 * 
	 * @return mixed the normalized value if validation passed.
	 * 
	 * @throws \LogicException if validation fails.
	 */
	public static function validate_option_value( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			$value = array_map( 'trim', $value );
			// Check all expected keys have value.
			$expected_keys = ['type', 'host', 'user', 'password', 'from_email', 'verbosity', 'from_name'];
			foreach ( $expected_keys as $key ) {
				if ( ! array_key_exists( $key, $value ) ) {
					throw new \LogicException( 'missing key ' . $key );
				}
			}

			// Check gateway type is valid.
			if ( ! in_array( $value['type'], ['local', 'smtp'], true ) ) {
				throw new \LogicException( 'bad gateway type ' . $value['type'] );
			}

			// Check for valid verbosity.
			if ( ! in_array( $value['verbosity'], ['no', 'full', 'recipients'], true ) ) {
				throw new \LogicException( 'bad verbosity type ' . $value['verbosity'] );
			}

			// Check validity of sender's email
			if ( ! is_email( $value['from_email'] ) ) {
				throw new \LogicException( 'the sender\'s email is not a valid email address' );
			}

			// Check validity of host for smtp
			if ( $value['type'] === 'smtp' ) {
				if ( empty( $value['host'] ) ) { 
					throw new \LogicException( 'SMTP host is not given' );
				}
			}

			// unset network_override if its not a truthful value
			if ( array_key_exists( 'network_override', $value ) ) {
				if ( in_array( $value['network_override'], [ 1, '1' ], true ) ) {
					$value['network_override'] = true;
				} elseif ( in_array( $value['network_override'], [ 0, '0' ], true ) ) {
					unset( $value['network_override'] );
				} else {
					throw new \LogicException( 'Invalid value for "network_override".' );
				}
			}
		} else {
			// Value being set is not even an array.
			throw new \LogicException( 'not an array' );
		}

		return $value;
	}

	/**
	 * Indicate if current user can change gateway related settings.
	 * 
	 * For standalone install admin (whoever can manage options) always can.
	 * In a network site admin (including super admin) can only if overriding network defaults can be done
	 * on the site.
	 * 
	 * @since 1.0.0
	 * 
	 * @return bool true, if current user can change getway related setting, false otherwise.
	 */
	public static function current_user_can_change_gateway(): bool {
		if ( is_multisite() ) {
			// Check if its a network site admin or super user and setting can be overidden.
			$opt = get_option( 'calm_email_delivery' );
			if ( array_key_exists( 'network_override', $opt ) ) {
				return current_user_can( 'manage_options' );
			}
			return false;
		} else {
			return current_user_can( 'manage_options' );
		}
	}
}