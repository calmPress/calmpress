<?php
/**
 * Unit tests covering schema initialization.
 *
 * Also generates the fixture data used by the wp-api.js QUnit tests.
 *
 * @package WordPress
 * @subpackage REST API
 */

require_once ABSPATH . 'wp-admin/includes/admin.php';

/**
 * @group restapi
 * @group restapi-jsclient
 */
class WP_Test_REST_Schema_Initialization extends WP_Test_REST_TestCase {
	const YOUTUBE_VIDEO_ID = 'i_cVJgIz_Cs';

	public function set_up() {
		parent::set_up();

		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		$wp_rest_server = new Spy_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		add_filter( 'pre_http_request', array( $this, 'mock_embed_request' ), 10, 3 );
	}

	public function tear_down() {
		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	public function mock_embed_request( $response, $parsed_args, $url ) {
		unset( $response, $parsed_args );

		// Mock request to YouTube Embed.
		if ( false !== strpos( $url, self::YOUTUBE_VIDEO_ID ) ) {
			return array(
				'response' => array(
					'code' => 200,
				),
				'body'     => wp_json_encode(
					array(
						'version'          => '1.0',
						'type'             => 'video',
						'provider_name'    => 'YouTube',
						'provider_url'     => 'https://www.youtube.com',
						'thumbnail_width'  => 480,
						'width'            => 500,
						'thumbnail_height' => 360,
						'html'             => '<iframe width="500" height="375" src="https://www.youtube.com/embed/' . self::YOUTUBE_VIDEO_ID . '?feature=oembed" frameborder="0" allowfullscreen></iframe>',
						'author_name'      => 'Jorge Rubira Santos',
						'thumbnail_url'    => 'https://i.ytimg.com/vi/' . self::YOUTUBE_VIDEO_ID . '/hqdefault.jpg',
						'title'            => 'No te olvides de poner el Where en el Delete From. (Una cancion para programadores)',
						'height'           => 375,
					)
				),
			);
		} else {
			return array(
				'response' => array(
					'code' => 404,
				),
			);
		}
	}

	/**
	 * @ticket 54596
	 */
	public function test_expected_routes_in_schema() {
		update_option( 'calm_embedding_on', 0 );

		global $wp_rest_server;
		$wp_rest_server = new Spy_REST_Server;
		do_action( 'rest_api_init', $wp_rest_server );

		$routes = rest_get_server()->get_routes();

		$this->assertIsArray( $routes, '`get_routes` should return an array.' );
		$this->assertNotEmpty( $routes, 'Routes should not be empty.' );

		$routes = array_filter( array_keys( $routes ), array( $this, 'is_builtin_route' ) );

		$expected_routes = array(
			'/',
			'/wp/v2',
			'/wp/v2/posts',
			'/wp/v2/posts/(?P<id>[\\d]+)',
			'/wp/v2/posts/(?P<parent>[\\d]+)/revisions',
			'/wp/v2/posts/(?P<parent>[\\d]+)/revisions/(?P<id>[\\d]+)',
			'/wp/v2/posts/(?P<id>[\\d]+)/autosaves',
			'/wp/v2/posts/(?P<parent>[\\d]+)/autosaves/(?P<id>[\\d]+)',
			'/wp/v2/menu-items',
			'/wp/v2/menu-items/(?P<id>[\d]+)',
			'/wp/v2/menu-items/(?P<id>[\d]+)/autosaves',
			'/wp/v2/menu-items/(?P<parent>[\d]+)/autosaves/(?P<id>[\d]+)',
			'/wp/v2/menu-locations',
			'/wp/v2/menu-locations/(?P<location>[\w-]+)',
			'/wp/v2/menus',
			'/wp/v2/menus/(?P<id>[\d]+)',
			'/wp/v2/pages',
			'/wp/v2/pages/(?P<id>[\\d]+)',
			'/wp/v2/pages/(?P<parent>[\\d]+)/revisions',
			'/wp/v2/pages/(?P<parent>[\\d]+)/revisions/(?P<id>[\\d]+)',
			'/wp/v2/pages/(?P<id>[\\d]+)/autosaves',
			'/wp/v2/pages/(?P<parent>[\\d]+)/autosaves/(?P<id>[\\d]+)',
			'/wp/v2/media',
			'/wp/v2/media/(?P<id>[\\d]+)',
			'/wp/v2/media/(?P<id>[\\d]+)/post-process',
			'/wp/v2/media/(?P<id>[\\d]+)/edit',
			'/wp/v2/types',
			'/wp/v2/types/(?P<type>[\\w-]+)',
			'/wp/v2/statuses',
			'/wp/v2/statuses/(?P<status>[\\w-]+)',
			'/wp/v2/taxonomies',
			'/wp/v2/taxonomies/(?P<taxonomy>[\\w-]+)',
			'/wp/v2/categories',
			'/wp/v2/categories/(?P<id>[\\d]+)',
			'/wp/v2/tags',
			'/wp/v2/tags/(?P<id>[\\d]+)',
			'/wp/v2/calm_authors',
			'/wp/v2/calm_authors/(?P<id>[\\d]+)',
			'/wp/v2/users',
			'/wp/v2/users/(?P<id>[\\d]+)',
			'/wp/v2/users/me',
			'/wp/v2/users/(?P<user_id>(?:[\\d]+|me))/application-passwords',
			'/wp/v2/users/(?P<user_id>(?:[\\d]+|me))/application-passwords/introspect',
			'/wp/v2/users/(?P<user_id>(?:[\\d]+|me))/application-passwords/(?P<uuid>[\\w\\-]+)',
			'/wp/v2/comments',
			'/wp/v2/comments/(?P<id>[\\d]+)',
			'/wp/v2/search',
			'/wp/v2/settings',
			'/wp/v2/themes',
			'/wp/v2/themes/(?P<stylesheet>[^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)',
			'/wp/v2/plugins',
			'/wp/v2/plugins/(?P<plugin>[^.\/]+(?:\/[^.\/]+)?)',
			'/wp/v2/sidebars',
			'/wp/v2/sidebars/(?P<id>[\w-]+)',
			'/wp/v2/widget-types',
			'/wp/v2/widget-types/(?P<id>[a-zA-Z0-9_-]+)',
			'/wp/v2/widget-types/(?P<id>[a-zA-Z0-9_-]+)/encode',
			'/wp/v2/widget-types/(?P<id>[a-zA-Z0-9_-]+)/render',
			'/wp/v2/widgets',
			'/wp/v2/widgets/(?P<id>[\w\-]+)',
			'/wp/v2/navigation',
			'/wp/v2/navigation/(?P<id>[\d]+)',
			'/wp/v2/navigation/(?P<id>[\d]+)/autosaves',
			'/wp/v2/navigation/(?P<parent>[\d]+)/autosaves/(?P<id>[\d]+)',
			'/wp/v2/navigation/(?P<parent>[\d]+)/revisions',
			'/wp/v2/navigation/(?P<parent>[\d]+)/revisions/(?P<id>[\d]+)',
			'/wp-site-health/v1',
			'/wp-site-health/v1/tests/loopback-requests',
			'/wp-site-health/v1/tests/dotorg-communication',
			'/wp-site-health/v1/tests/authorization-header',
			'/wp-abilities/v1',
			'/wp-abilities/v1/categories',
			'/wp-abilities/v1/categories/(?P<slug>[a-z0-9]+(?:-[a-z0-9]+)*)',
			'/wp-abilities/v1/abilities/(?P<name>[a-zA-Z0-9\-\/]+?)/run',
			'/wp-abilities/v1/abilities/(?P<name>[a-zA-Z0-9\-\/]+)',
			'/wp-abilities/v1/abilities',
			'/wp/v2/wp_pattern_category',
			'/wp/v2/wp_pattern_category/(?P<id>[\d]+)',
		);

		$this->assertSameSets( $expected_routes, $routes );
	}

	private function is_builtin_route( $route ) {
		return (
			'/' === $route ||
			preg_match( '#^/oembed/1\.0(/.+)?$#', $route ) ||
			preg_match( '#^/wp/v2(/.+)?$#', $route ) ||
			preg_match( '#^/wp-site-health/v1(/.+)?$#', $route ) ||
			preg_match( '#^/wp-abilities/v1(/.+)?$#', $route )
		);
	}
}
