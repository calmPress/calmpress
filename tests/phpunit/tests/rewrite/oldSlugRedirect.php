<?php

/**
 * @group rewrite
 * @ticket 33920
 */
class Tests_Rewrite_OldSlugRedirect extends WP_UnitTestCase {
	protected $old_slug_redirect_url;

	protected $post_id;

	public function set_up() {
		parent::set_up();

		$this->post_id = self::factory()->post->create(
			array(
				'post_title' => 'Foo Bar',
				'post_name'  => 'foo-bar',
			)
		);

		add_filter( 'old_slug_redirect_url', array( $this, 'filter_old_slug_redirect_url' ), 10, 1 );

		$this->set_permalink_structure( '/%postname%/' );

		add_rewrite_endpoint( 'custom-endpoint', EP_PERMALINK );
		add_rewrite_endpoint( 'second-endpoint', EP_PERMALINK, 'custom' );

		flush_rewrite_rules();
	}

	public function tear_down() {
		$this->old_slug_redirect_url = null;

		parent::tear_down();
	}

	public function test_old_slug_redirect() {
		$old_permalink = user_trailingslashit( get_permalink( $this->post_id ) );

		wp_update_post(
			array(
				'ID'        => $this->post_id,
				'post_name' => 'bar-baz',
			)
		);

		$permalink = user_trailingslashit( get_permalink( $this->post_id ) );

		$this->go_to( $old_permalink );
		wp_old_slug_redirect();
		$this->assertSame( $permalink, $this->old_slug_redirect_url );
	}

	public function test_old_slug_redirect_paged() {
		wp_update_post(
			array(
				'ID'           => $this->post_id,
				'post_content' => 'Test<!--nextpage-->Test',
			)
		);

		$old_permalink = user_trailingslashit( trailingslashit( get_permalink( $this->post_id ) ) . 'page/2' );

		wp_update_post(
			array(
				'ID'        => $this->post_id,
				'post_name' => 'bar-baz',
			)
		);

		$permalink = user_trailingslashit( trailingslashit( get_permalink( $this->post_id ) ) . 'page/2' );

		$this->go_to( $old_permalink );
		wp_old_slug_redirect();
		$this->assertSame( $permalink, $this->old_slug_redirect_url );
	}

	/**
	 * @ticket 35031
	 */
	public function test_old_slug_doesnt_redirect_when_reused() {
		$old_permalink = user_trailingslashit( get_permalink( $this->post_id ) );

		wp_update_post(
			array(
				'ID'        => $this->post_id,
				'post_name' => 'bar-baz',
			)
		);

		$new_post_id = self::factory()->post->create(
			array(
				'post_title' => 'Foo Bar',
				'post_name'  => 'foo-bar',
			)
		);

		$permalink = user_trailingslashit( get_permalink( $new_post_id ) );

		$this->assertSame( $old_permalink, $permalink );

		$this->go_to( $old_permalink );
		wp_old_slug_redirect();
		$this->assertNull( $this->old_slug_redirect_url );
	}

	public function filter_old_slug_redirect_url( $url ) {
		$this->old_slug_redirect_url = $url;
		return false;
	}
}
