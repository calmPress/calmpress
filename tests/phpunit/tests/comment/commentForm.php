<?php

/**
 * @group  comment
 * @covers ::comment_form
 */
class Tests_Comment_CommentForm extends WP_UnitTestCase {
	public static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create( ['comment_status' => 'open'] );
	}

	public function test_default_markup_for_submit_button_and_wrapper() {

		$args = array(
			'name_submit'  => 'foo-name',
			'id_submit'    => 'foo-id',
			'class_submit' => 'foo-class',
			'label_submit' => 'foo-label',
		);

		$form = get_echo( 'comment_form', array( $args, self::$post_id ) );

		$button = '<input name="foo-name" type="submit" id="foo-id" class="foo-class" value="foo-label" />';
		$hidden = get_comment_id_fields( self::$post_id );
		$this->assertMatchesRegularExpression( '|<p class="form\-submit">\s*' . $button . '\s*' . $hidden . '\s*|', $form );
	}

	public function test_custom_submit_button() {

		$args = array(
			'name_submit'   => 'foo-name',
			'id_submit'     => 'foo-id',
			'class_submit'  => 'foo-class',
			'label_submit'  => 'foo-label',
			'submit_button' => '<input name="custom-%1$s" type="submit" id="custom-%2$s" class="custom-%3$s" value="custom-%4$s" />',
		);

		$form = get_echo( 'comment_form', array( $args, self::$post_id ) );

		$button = '<input name="custom-foo-name" type="submit" id="custom-foo-id" class="custom-foo-class" value="custom-foo-label" />';
		$this->assertStringContainsString( $button, $form );
	}

	public function test_custom_submit_field() {

		$args = array(
			'name_submit'  => 'foo-name',
			'id_submit'    => 'foo-id',
			'class_submit' => 'foo-class',
			'label_submit' => 'foo-label',
			'submit_field' => '<p class="my-custom-submit-field">%1$s %2$s</p>',
		);

		$form = get_echo( 'comment_form', array( $args, self::$post_id ) );

		$button = '<input name="foo-name" type="submit" id="foo-id" class="foo-class" value="foo-label" />';
		$hidden = get_comment_id_fields( self::$post_id );
		$this->assertMatchesRegularExpression( '|<p class="my\-custom\-submit\-field">\s*' . $button . '\s*' . $hidden . '\s*|', $form );
	}

	/**
	 * @ticket 32312
	 */
	public function test_submit_button_and_submit_field_should_fall_back_on_defaults_when_filtered_defaults_do_not_contain_the_keys() {

		$args = array(
			'name_submit'  => 'foo-name',
			'id_submit'    => 'foo-id',
			'class_submit' => 'foo-class',
			'label_submit' => 'foo-label',
		);

		add_filter( 'comment_form_defaults', array( $this, 'filter_comment_form_defaults' ) );
		$form = get_echo( 'comment_form', array( $args, self::$post_id ) );
		remove_filter( 'comment_form_defaults', array( $this, 'filter_comment_form_defaults' ) );

		$button = '<input name="foo-name" type="submit" id="foo-id" class="foo-class" value="foo-label" />';
		$hidden = get_comment_id_fields( self::$post_id );
		$this->assertMatchesRegularExpression( '|<p class="form\-submit">\s*' . $button . '\s*' . $hidden . '\s*|', $form );
	}

	public function filter_comment_form_defaults( $defaults ) {
		unset( $defaults['submit_field'] );
		unset( $defaults['submit_button'] );
		return $defaults;
	}

	/**
	 * @ticket 32767
	 */
	public function test_when_thread_comments_enabled() {
		update_option( 'thread_comments', true );

		$form     = get_echo( 'comment_form', array( array(), self::$post_id ) );
		$expected = '<a rel="nofollow" id="cancel-comment-reply-link" href="#respond">Cancel reply</a>';
		$this->assertStringContainsString( $expected, $form );
	}

	/**
	 * @ticket 32767
	 */
	public function test_when_thread_comments_disabled() {
		delete_option( 'thread_comments' );

		$form     = get_echo( 'comment_form', array( array(), self::$post_id ) );
		$expected = '<a rel="nofollow" id="cancel-comment-reply-link" href="#respond" style="display:none;">Cancel reply</a>';
		$this->assertStringNotContainsString( $expected, $form );
	}
}
