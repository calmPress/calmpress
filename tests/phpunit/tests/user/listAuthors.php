<?php
/**
 * @group author
 * @group user
 */
class Tests_User_ListAuthors extends WP_UnitTestCase {
	static $author_ids = array();
	static $fred_id;
	static $fred_url;
	static $posts     = array();
	static $user_urls = array();
		/* Defaults
		'orderby'       => 'name',
		'order'         => 'ASC',
		'number'        => null,
		'optioncount'   => false,
		'hide_empty'    => true,
		'echo'          => true,
		'style'         => 'list',
		'html'          => true );
		*/
	public static function wpSetUpBeforeClass( $factory ) {
		self::$author_ids[] = $factory->term->create( array( 'taxonomy' => \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, 'name' => 'zack' ) );
		self::$author_ids[] = $factory->term->create( array( 'taxonomy' => \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, 'name' => 'bob' ) );
		self::$author_ids[] = $factory->term->create( array( 'taxonomy' => \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, 'name' => 'paul' ) );
		self::$fred_id = $factory->term->create( array( 'taxonomy' => \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, 'name' => 'fred' ) );

		$count = 0;
		foreach ( self::$author_ids as $authorid ) {
			$count = $count + 1;
			for ( $i = 0; $i < $count; $i++ ) {
				$pid = $factory->post->create( array( 'post_type' => 'post' ) );
				wp_set_object_terms( $pid, $authorid, \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME );
				self::$posts[] = $pid;
			}

			$author_term = get_term( $authorid, \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME );
			$post_author = new calmpress\post_authors\Taxonomy_Based_Post_Author( $author_term );
			self::$user_urls[] = $post_author->posts_url();
		}

		$author_term = get_term( self::$fred_id, \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME );
		$post_author = new calmpress\post_authors\Taxonomy_Based_Post_Author( $author_term );
		self::$fred_url = $post_author->posts_url();
	}

	function test_wp_list_authors_default() {
		$expected['default'] =
			'<li><a href="' . self::$user_urls[1] . '" title="Posts by bob">bob</a></li>' .
			'<li><a href="' . self::$user_urls[2] . '" title="Posts by paul">paul</a></li>' .
			'<li><a href="' . self::$user_urls[0] . '" title="Posts by zack">zack</a></li>';

		$this->AssertEquals( $expected['default'], wp_list_authors( array( 'echo' => false ) ) );
	}

	function test_wp_list_authors_orderby() {
		$expected['post_count'] =
			'<li><a href="' . self::$user_urls[0] . '" title="Posts by zack">zack</a></li>' .
			'<li><a href="' . self::$user_urls[1] . '" title="Posts by bob">bob</a></li>' .
			'<li><a href="' . self::$user_urls[2] . '" title="Posts by paul">paul</a></li>';

		$this->AssertEquals(
			$expected['post_count'],
			wp_list_authors(
				array(
					'echo'    => false,
					'orderby' => 'post_count',
				)
			)
		);
	}

	function test_wp_list_authors_optioncount() {
		$expected['optioncount'] =
			'<li><a href="' . self::$user_urls[1] . '" title="Posts by bob">bob</a> (2)</li>' .
			'<li><a href="' . self::$user_urls[2] . '" title="Posts by paul">paul</a> (3)</li>' .
			'<li><a href="' . self::$user_urls[0] . '" title="Posts by zack">zack</a> (1)</li>';

		$this->AssertEquals(
			$expected['optioncount'],
			wp_list_authors(
				array(
					'echo'        => false,
					'optioncount' => 1,
				)
			)
		);
	}

	function test_wp_list_authors_hide_empty() {
		$fred_id = self::$fred_id;
		$fred_term = get_term( $fred_id, \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME );
		$fred_author = new calmpress\post_authors\Taxonomy_Based_Post_Author( $fred_term );
		$fred_url = $fred_author->posts_url();

		$expected['hide_empty'] =
			'<li><a href="' . self::$user_urls[1] . '" title="Posts by bob">bob</a></li>' .
			'<li><a href="' . $fred_url . '" title="Posts by fred">fred</a></li>' .
			'<li><a href="' . self::$user_urls[2] . '" title="Posts by paul">paul</a></li>' .
			'<li><a href="' . self::$user_urls[0] . '" title="Posts by zack">zack</a></li>';

		$this->AssertEquals(
			$expected['hide_empty'],
			wp_list_authors(
				array(
					'echo'       => false,
					'hide_empty' => 0,
				)
			)
		);
	}

	function test_wp_list_authors_echo() {
		$expected['echo'] =
			'<li><a href="' . self::$user_urls[1] . '" title="Posts by bob">bob</a></li>' .
			'<li><a href="' . self::$user_urls[2] . '" title="Posts by paul">paul</a></li>' .
			'<li><a href="' . self::$user_urls[0] . '" title="Posts by zack">zack</a></li>';

		$this->expectOutputString( $expected['echo'] );
		wp_list_authors( array( 'echo' => true ) );
	}

	function test_wp_list_authors_style() {
		$expected['style'] =
			'<a href="' . self::$user_urls[1] . '" title="Posts by bob">bob</a>, ' .
			'<a href="' . self::$user_urls[2] . '" title="Posts by paul">paul</a>, ' .
			'<a href="' . self::$user_urls[0] . '" title="Posts by zack">zack</a>';

		$this->AssertEquals(
			$expected['style'],
			wp_list_authors(
				array(
					'echo'  => false,
					'style' => 'none',
				)
			)
		);
	}

	function test_wp_list_authors_html() {
		$expected['html'] = 'bob, paul, zack';

		$this->AssertEquals(
			$expected['html'],
			wp_list_authors(
				array(
					'echo' => false,
					'html' => 0,
				)
			)
		);
	}
}
