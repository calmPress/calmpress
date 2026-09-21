<?php
/**
 * Tests for CalmPress WebAuthn REST endpoints.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\webauthn\Devices_Of_User;
use calmpress\webauthn\User_Of_Device;
use function calmpress\utils\base64URL_encode;

class Webauthn_Rest_Endpoints_Test extends WP_UnitTestCase {
	/**
	 * A correctly signed assertion authenticates the credential owner.
	 *
	 * @since 1.0.0
	 */
	public function test_login_accepts_verified_assertion(): void {
		$original_home = get_option( 'home' );
		update_option( 'home', 'https://example.org' );

		try {
			$user = new WP_User( self::factory()->user->create() );
			$rp_id = Devices_Of_User::rp_info()->id;
			// A fixed test key avoids relying on the host's OpenSSL key-generation configuration.
			$private_key = openssl_pkey_get_private( <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDeLp97eKay9UZ+
6KUgwaqKbiDUfpTlgwFl/I0PktVcdb9iB36ECUbaC2WYuL7pxQKbyGt9LAxqqIYX
BdL03y+Velqxt82Xhd/TVEzxlhQ/ux5wZJALugdWze6VWCGhSZLWCuyWgmnEXGDI
uaZGFi8FhhBwxEd5wzpt0QjU2h9DWQcxpMwxzemLnB25evSGIKH2ZuffjEAwMbAd
92aDVwkg+mKjgwz5YlO6oh2kfzEv+ycEZGkaM7bo9r81b5DJnsLTjcb+2ownTShY
qzQBmMtCgYWk5ewV/b1T+UUYY9PeAAZAzQ524sluvP0C6z+I5pUsiL86tk1MaliL
bg4SYjl1AgMBAAECggEAB2SkRGrHm0FbounxYrVnDButhnyezkNNdtwQQpInfN/j
KisnK9QWMje6XfpZyfQXCeGBqCTg1eS9L4NvWVwa3H25ilvcILxg7gqKU+ijTFqY
4PMLswQx7dLE613HIrOMyggLhteYluLfCpbY4FCpKGUlY2c4nKXhKhnQUire6vH5
0eYTL2f/YZZTPQyaYBaEvE1u0a503AY7NrG5w2wWqbKMtGUJ7uqiveTJH0EJQuzh
gYhQWmn+02bQkMqOPFIiqLL8vT6nyPBeJgaOh4KQj6eHK2VXXMU3dTcM0rKLicTy
lVdUkAfwOd84Xb41cGUZCmHpk/AellzUJxA1nYXDYQKBgQDzqs8Zi9MoI7Xrx8aF
NFDLa6HQ9ZMLpbsQNPjkXshPVHjqaINKVGK9Sk7rom7RHp0Ja8LyC9u5WrgzGf4S
6T4EAKYnf8AQxbFntyqmsEIHVN6BXVe8dw7OmAMUh/FafcQfrfXlL21VzLNtoeH8
YKU3kMrI6m3bzgWkWAXhpxAmVQKBgQDpbW6W6r/DIJ5m1+1CxtVnzgtc7JUYbZMX
5GxNl9LF6kxNmEROsm55Wgv+976NU5a0vz59GrABYge6yXYCErHsa5VtZu7tLjtk
yPrlaWSb7lSmUS4hSTqQazIkgKxwAaXkfEC8eWEN4JzqkRHPfFyMmKqCYN7gbh2B
LYY0X+OmoQKBgQDrWKgtMWsiktNMRymMUMpUn8GsNPTwxAMYlUFsOcvZK2qaZZWh
fj3cPGBboQjNvHbKuaWR6TgxH9lXqhxHobY/YW0aK36T9I3z8eslEorD0AoVAtYR
9yB7FEGtW9wWnfCG9JvS3+sHeu42zquZ+rK5J4VlZ1/ydFvorwgHOjgT/QKBgFNd
3/dKU75usePtDjGhLaprLie73uvghn4r+Hol1QMWULYNwaeRll8Ex/ABry5uQg6/
lqO7mkyEJFqThO/smVrkeXOfJYnTzyaJmQHCCEqgbd8Qczc0HhRiFIBw7CT8kbDu
p3goqX75T1F/CiteMPeNtqflzPO+oA74oUunS3jBAoGBAJ8zJtuF1lVvpOgYYsus
NH8wulg5Z4NY1KOYvNXAnRwkbd7X1xXgBgZsE+wOPSH+L3KMdVHFdX9wW4DJ77d/
qDmELp5xMtfgT1qKP+PLaNACb0NCahsFBzqOBVJ/uKkJOhk8pXejYhSan/P85UU1
lOy8SB1CrfCFzT3yyw5TyWWf
-----END PRIVATE KEY-----
PEM );
			$this->assertNotFalse( $private_key );
			$key_details = openssl_pkey_get_details( $private_key );
			$cose_key = CBOR\MapObject::create()
				->add( CBOR\UnsignedIntegerObject::create( 1 ), CBOR\UnsignedIntegerObject::create( 3 ) )
				->add( CBOR\UnsignedIntegerObject::create( 3 ), CBOR\NegativeIntegerObject::create( -257 ) )
				->add( CBOR\NegativeIntegerObject::create( -1 ), CBOR\ByteStringObject::create( $key_details['rsa']['n'] ) )
				->add( CBOR\NegativeIntegerObject::create( -2 ), CBOR\ByteStringObject::create( $key_details['rsa']['e'] ) );
			$collection = new Devices_Of_User( $user );
			$collection->store( new User_Of_Device( 'signed-credential', (string) $cose_key, 'Test', new DateTime(), $collection, Devices_Of_User::rp_info()->id ) );

			$challenge = random_bytes( 32 );
			set_transient( 'webauthn_challenge_login_' . base64URL_encode( $challenge ), $rp_id, HOUR_IN_SECONDS );
			$client_data = wp_json_encode(
				[
					'type' => 'webauthn.get',
					'challenge' => base64URL_encode( $challenge ),
					'origin' => 'https://example.org',
				]
			);
			$authenticator_data = hash( 'sha256', $rp_id, true ) . chr( 0x05 ) . pack( 'N', 1 );
			openssl_sign( $authenticator_data . hash( 'sha256', $client_data, true ), $signature, $private_key, OPENSSL_ALGO_SHA256 );
			$user_handle = pack( 'NN', $user->ID >> 32, $user->ID & 0xFFFFFFFF );

			$request = new WP_REST_Request( 'POST', '/calmpress/webauthn/login' );
			$request->set_param( 'credential_id', base64URL_encode( 'signed-credential' ) );
			$request->set_param( 'clientDataJSON', base64URL_encode( $client_data ) );
			$request->set_param( 'authenticator_data', base64URL_encode( $authenticator_data ) );
			$request->set_param( 'signature', base64URL_encode( $signature ) );
			$request->set_param( 'user_handle', base64URL_encode( $user_handle ) );
			$request->set_param( 'redirect_to', '' );

			wp_set_current_user( 0 );

			// Reject a tampered signature even when every other assertion field is valid.
			$request->set_param( 'signature', base64URL_encode( 'invalid' ) );
			$this->assertSame( 400, calmpress\webauthn\rest_endpoints\login( $request )->get_status() );
			set_transient( 'webauthn_challenge_login_' . base64URL_encode( $challenge ), $rp_id, HOUR_IN_SECONDS );
			$request->set_param( 'signature', base64URL_encode( $signature ) );

			// The matching signature authenticates the user.
			$response = calmpress\webauthn\rest_endpoints\login( $request );

			$this->assertSame( 200, $response->get_status() );
		} finally {
			update_option( 'home', $original_home );
		}
	}

	/**
	 * A known credential ID and valid challenge cannot authenticate without a signed assertion.
	 *
	 * @since 1.0.0
	 */
	public function test_login_rejects_unsigned_assertion(): void {
		$user = new WP_User( self::factory()->user->create() );
		$collection = new Devices_Of_User( $user );
		$collection->store( new User_Of_Device( 'known-credential', 'invalid-key', 'Test', new DateTime(), $collection, Devices_Of_User::rp_info()->id ) );

		$challenge = random_bytes( 32 );
		set_transient(
			'webauthn_challenge_login_' . base64URL_encode( $challenge ),
			Devices_Of_User::rp_info()->id,
			HOUR_IN_SECONDS
		);

		$request = new WP_REST_Request( 'POST', '/calmpress/webauthn/login' );
		$request->set_param( 'credential_id', base64URL_encode( 'known-credential' ) );
		$request->set_param(
			'clientDataJSON',
			base64URL_encode(
				wp_json_encode(
					[
						'type' => 'webauthn.get',
						'challenge' => base64URL_encode( $challenge ),
						'origin' => home_url(),
					]
				)
			)
		);
		$request->set_param( 'authenticator_data', base64URL_encode( 'invalid' ) );
		$request->set_param( 'signature', base64URL_encode( 'invalid' ) );
		$request->set_param( 'redirect_to', '' );

		wp_set_current_user( 0 );
		$response = calmpress\webauthn\rest_endpoints\login( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 0, get_current_user_id() );
	}
}
