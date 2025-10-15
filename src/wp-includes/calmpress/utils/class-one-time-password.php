<?php
/**
 * Implementation of sturcture which is used to a verification code used as
 * a one time passwords.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\utils;

/**
 * Sturcture which is used to represent decryption results.
 *
 * @since 1.0.0
 */
class One_Time_Password {

	/**
	 * The password.
	 *
	 * @since 1.0.0
	 */
	public readonly string $password;

	/**
	 * The expiry time of the password.
	 *
	 * @since 1.0.0
	 */
	private readonly int $expiry;

	/**
	 * Construct the object. The password is automatically generated to be
	 * a 6 digits representation of decimal number.
	 *
	 * @since 1.0.0
	 */
	private function __construct( string $password, int $expiry ) {
		$this->expiry   = $expiry;
		$this->password = $password;
	}

	/**
	 * Construct a new one time password which will expire in an hour.
	 *
	 * @since 1.0.0
	 * 
	 * @param int $expiry_interval The inteval for whih the object should validate
	 *                             the generated password.
	 */
	static public function new( int $expiry_interval ): One_Time_Password {
		
		return new One_Time_Password(
			str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT ),
			time() + $expiry_interval
		);
	}

	/**
	 * Check if a value is the same as the password and the password didn't expire yet.
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $value The value to compare against.
	 * 
	 * @return bool true If $value same as the password and the password had not expired
	 *              yet, false otherwise. 
	 */
	public function is_matching( string $value ): bool {
		return ( $this->password === $value ) && ( time() < $this->expiry );
	}

	/**
	 * Create a string represantation of the object which can be unserialized.
	 * 
	 * The generated json has the following fields
	 * - p which has the password string.
	 * - e which has the expiry time.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return string The represantation. 
	 */
	public function serialize() : string {
		$o = new \stdClass();
		$o->p = $this->password;
		$o->e = (string) $this->expiry;
		return json_encode( $o );
	}

	/**
	 * Unserialize priviously serialized OTP into a full objet.
	 * 
	 * @see serialize for the expected format of the json string.
	 * 
	 * @since calmpress 1.0.0
	 * 
	 * @param string          $data The string containing the serialized represantation.
	 * 
	 * @return One_Time_Password An object created based from parsing the $data.
	 * 
	 * @throws RuntimeException If the serialized data is invalid (which includes
	 *                          if the password had expired).
	 */
	static public function unserialize( string $data ): One_Time_Password {
		$o = json_decode( $data, true );

		// Check if valid json.
		if ( ! $o ) {
			throw new \RuntimeException( 
				sprintf(
					'data %s is not json encoded',
					$data
				)
			);
		}

		// Check for all expected fields.
		foreach ( ['p', 'e' ] as $key ) {
			if ( ! isset( $o[ $key ] ) ) {
				throw new \RuntimeException( 
					sprintf(
						'missing field %s in serialised data %s',
						$key,
						$data
					)
				);
			}

			if ( ! is_string( $o[ $key ] ) ) {
				throw new \RuntimeException( 
					sprintf(
						'field %s is not a string in serialised data %s',
						$key,
						$data
					)
				);
			}
		}

		if ( ! preg_match( '/^\d{6}$/', $o['p'] ) ) {
			throw new \RuntimeException( 
				sprintf(
					'password is not a 6 digits number',
					$o['p'],
					$data
				)
			);
		}

		try {
			$date = new \DateTime( '@' . $o['e'] );
		} catch ( \Exception $e ) {
			if ( $date === false ) {
				throw new \RuntimeException( 
					sprintf(
						'Date can not be parsed from data %s',
						$data
					)
				);
			}
		}

		if ( $date->getTimestamp() < time() ) {
			throw new \RuntimeException( 'password expired' );
		}

		return new One_Time_Password( $o['p'], (int) $o['e'] );
	}
}
