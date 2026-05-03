<?php
/**
 * @group author
 * @group user
 * @covers ::wp_list_authors
 */
class Tests_User_ListAuthors extends WP_UnitTestCase {
	public static $author_ids = array();
	public static $fred_id;
	public static $fred_url;
	public static $posts     = array();
	public static $user_urls = array();
		/* Defaults
		'orderby'       => 'name',
		'order'         => 'ASC',
		'number'        => null,
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
			self::$user_urls[] = $post_author->posts_url( 'post' );
		}

		$author_term = get_term( self::$fred_id, \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME );
		$post_author = new calmpress\post_authors\Taxonomy_Based_Post_Author( $author_term );
		self::$fred_url = $post_author->posts_url();
	}

	public function test_wp_list_authors_default() {
		$expected['default'] =
			'<li><a href="' . self::$user_urls[1] . '" title="Posts by bob">bob</a></li>' .
			'<li><a href="' . self::$user_urls[2] . '" title="Posts by paul">paul</a></li>' .
			'<li><a href="' . self::$user_urls[0] . '" title="Posts by zack">zack</a></li>';

		$this->assertSame( $expected['default'], wp_list_authors( array( 'echo' => false ) ) );
	}

	public function test_wp_list_authors_orderby() {
		$expected['post_count'] =
			'<li><a href="' . self::$user_urls[0] . '" title="Posts by zack">zack</a></li>' .
			'<li><a href="' . self::$user_urls[1] . '" title="Posts by bob">bob</a></li>' .
			'<li><a href="' . self::$user_urls[2] . '" title="Posts by paul">paul</a></li>';

		$this->assertSame(
			$expected['post_count'],
			wp_list_authors(
				array(
					'echo'    => false,
					'orderby' => 'post_count',
				)
			)
		);
	}

	public function test_wp_list_authors_echo() {
		$expected['echo'] =
			'<li><a href="' . self::$user_urls[1] . '" title="Posts by bob">bob</a></li>' .
			'<li><a href="' . self::$user_urls[2] . '" title="Posts by paul">paul</a></li>' .
			'<li><a href="' . self::$user_urls[0] . '" title="Posts by zack">zack</a></li>';

		$this->expectOutputString( $expected['echo'] );
		wp_list_authors( array( 'echo' => true ) );
	}

	public function test_wp_list_authors_style() {
		$expected['style'] =
			'<a href="' . self::$user_urls[1] . '" title="Posts by bob">bob</a>, ' .
			'<a href="' . self::$user_urls[2] . '" title="Posts by paul">paul</a>, ' .
			'<a href="' . self::$user_urls[0] . '" title="Posts by zack">zack</a>';

		$this->assertSame(
			$expected['style'],
			wp_list_authors(
				array(
					'echo'  => false,
					'style' => 'none',
				)
			)
		);
	}

	public function test_wp_list_authors_html() {
		$expected['html'] = 'bob, paul, zack';

		$this->assertSame(
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
