<?php
/**
 * Tests for get_edit_user_link().
 *
 * @group link
 * @group ms-required
 */
class Tests_Link_GetEditUserLink extends WP_UnitTestCase {

	/**
	 * Tests that site administrators edit another user's site profile.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_site_administrator_link_targets_site_profile() {
		$administrator_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$user_id          = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $administrator_id );

		$this->assertSame(
			add_query_arg( 'user_id', $user_id, admin_url( 'site-profile.php' ) ),
			get_edit_user_link( $user_id )
		);
	}
}
