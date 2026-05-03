<?php
/**
 * @group user
 *
 * @covers ::wp_list_users
 */
class Tests_User_wpListUsers extends WP_UnitTestCase {
	private static $user_ids = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_ids[] = $factory->user->create(
			array(
				'user_login'   => 'zack',
				'display_name' => 'zack',
				'role'         => 'subscriber',
				'user_email'   => 'm.zack@example.com',
			)
		);

		self::$user_ids[] = $factory->user->create(
			array(
				'user_login'   => 'jane',
				'display_name' => 'jane',
				'role'         => 'contributor',
				'user_email'   => 'r.jane@example.com',
			)
		);

		self::$user_ids[] = $factory->user->create(
			array(
				'user_login'   => 'michelle',
				'display_name' => 'michelle',
				'role'         => 'subscriber',
				'user_email'   => 'j.michelle@example.com',
			)
		);

		self::$user_ids[] = $factory->user->create(
			array(
				'user_login'   => 'paul',
				'display_name' => 'paul',
				'role'         => 'subscriber',
				'user_email'   => 'n.paul@example.com',
			)
		);

		foreach ( self::$user_ids as $user ) {
			$factory->post->create(
				array(
					'post_type'   => 'post',
					'post_author' => $user,
				)
			);
		}
	}

	/**
	 * Test that wp_list_users() creates the expected list of users.
	 *
	 * @dataProvider data_should_create_a_user_list
	 *
	 * @ticket 15145
	 *
	 * @param array|string $args     The arguments to create a list of users.
	 * @param string       $expected The expected result.
	 */
	public function test_should_create_a_user_list( $args, $expected ) {
		$actual = wp_list_users( $args );

		$expected = str_replace(
			array( 'AUTHOR_ID_zack', 'AUTHOR_ID_jane', 'AUTHOR_ID_michelle', 'AUTHOR_ID_paul' ),
			array( self::$user_ids[0], self::$user_ids[1], self::$user_ids[2], self::$user_ids[3] ),
			$expected
		);

		if ( null === $actual ) {
			$this->expectOutputString( $expected );
		} else {
			$this->assertSame( $expected, $actual );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_should_create_a_user_list() {
		return array(
			'defaults when no args are supplied' => array(
				'args'     => '',
				'expected' => '<li>Admin</li><li>jane</li><li>michelle</li><li>paul</li><li>zack</li>',
			),
			'the admin account included'         => array(
				'args'     => array(
					'exclude_admin' => false,
				),
				'expected' => '<li>Admin</li><li>jane</li><li>michelle</li><li>paul</li><li>zack</li>',
			),
			'no output via echo'                 => array(
				'args'     => array(
					'echo' => false,
				),
				'expected' => '<li>Admin</li><li>jane</li><li>michelle</li><li>paul</li><li>zack</li>',
			),
			'commas separating each user'        => array(
				'args'     => array(
					'style' => '',
				),
				'expected' => 'Admin, jane, michelle, paul, zack',
			),
			'plain text format'                  => array(
				'args'     => array(
					'html' => false,
				),
				'expected' => 'Admin, jane, michelle, paul, zack',
			),
		);
	}

	/**
	 * Tests that wp_list_users() does not create a user list.
	 *
	 * @dataProvider data_should_not_create_a_user_list
	 *
	 * @ticket 15145
	 *
	 * @param array|string $args The arguments to create a list of users.
	 */
	public function test_should_not_create_a_user_list( $args ) {
		$actual = wp_list_users( $args );

		if ( null === $actual ) {
			$this->expectOutputString( '', 'wp_list_users() did not output an empty string.' );
		} else {
			$this->assertSame( $actual, 'wp_list_users() did not return an empty string.' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_should_not_create_a_user_list() {
		return array(
			'an empty user query result' => array(
				'args'     => array(
					'include' => array( 9999 ),
				),
				'expected' => '',
			),
		);
	}
}
