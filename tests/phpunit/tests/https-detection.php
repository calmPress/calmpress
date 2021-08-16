<?php

/**
 * @group https-detection
 */
class Tests_HTTPS_Detection extends WP_UnitTestCase {

	private $last_request_url;

	public function setUp() {
		parent::setUp();

		remove_all_filters( 'option_home' );
		remove_all_filters( 'home_url' );
		remove_all_filters( 'site_url' );
	}

	/**
	 * @ticket 47577
	 */
	public function test_wp_is_using_https() {
		update_option( 'home', 'http://example.com/' );
		$this->assertFalse( wp_is_using_https() );

		update_option( 'home', 'https://example.com/' );
		$this->assertTrue( wp_is_using_https() );
	}

	/**
	 * @ticket 47577
	 */
	public function test_wp_is_https_supported() {
		// The function works with cached errors, so only test that here.
		$wp_error = new WP_Error();

		// No errors, so HTTPS is supported.
		update_option( 'https_detection_errors', $wp_error->errors );
		$this->assertTrue( wp_is_https_supported() );

		// Errors, so HTTPS is not supported.
		$wp_error->add( 'ssl_verification_failed', 'SSL verification failed.' );
		update_option( 'https_detection_errors', $wp_error->errors );
		$this->assertFalse( wp_is_https_supported() );
	}

	/**
	 * @ticket 47577
	 * @ticket 52484
	 */
	public function test_pre_wp_update_https_detection_errors() {
		// Override to enforce no errors being detected.
		add_filter(
			'pre_wp_update_https_detection_errors',
			function() {
				return new WP_Error();
			}
		);
		wp_update_https_detection_errors();
		$this->assertSame( array(), get_option( 'https_detection_errors' ) );

		// Override to enforce an error being detected.
		add_filter(
			'pre_wp_update_https_detection_errors',
			function() {
				return new WP_Error(
					'ssl_verification_failed',
					'Bad SSL certificate.'
				);
			}
		);
		wp_update_https_detection_errors();
		$this->assertSame(
			array( 'ssl_verification_failed' => array( 'Bad SSL certificate.' ) ),
			get_option( 'https_detection_errors' )
		);
	}

	/**
	 * @ticket 47577
	 */
	public function test_wp_schedule_https_detection() {
		wp_schedule_https_detection();
		$this->assertSame( 'twicedaily', wp_get_schedule( 'wp_https_detection' ) );
	}

	/**
	 * @ticket 47577
	 */
	public function test_wp_cron_conditionally_prevent_sslverify() {
		// If URL is not using HTTPS, don't set 'sslverify' to false.
		$request = array(
			'url'  => 'http://example.com/',
			'args' => array( 'sslverify' => true ),
		);
		$this->assertSame( $request, wp_cron_conditionally_prevent_sslverify( $request ) );

		// If URL is using HTTPS, set 'sslverify' to false.
		$request                       = array(
			'url'  => 'https://example.com/',
			'args' => array( 'sslverify' => true ),
		);
		$expected                      = $request;
		$expected['args']['sslverify'] = false;
		$this->assertSame( $expected, wp_cron_conditionally_prevent_sslverify( $request ) );
	}

	/**
	 * @ticket 47577
	 */
	public function test_wp_is_local_html_output_via_rest_link() {

		// HTML includes REST API link.
		$head_tag = get_echo( 'rest_output_link_wp_head' );
		$html     = $this->get_sample_html_string( $head_tag );
		$this->assertTrue( wp_is_local_html_output( $html ) );

		// HTML includes modified REST API link but same URL.
		$head_tag = str_replace( ' />', '>', get_echo( 'rest_output_link_wp_head' ) );
		$html     = $this->get_sample_html_string( $head_tag );
		$this->assertTrue( wp_is_local_html_output( $html ) );

		// HTML includes REST API link with alternative URL scheme.
		$head_tag = get_echo( 'rest_output_link_wp_head' );
		$head_tag = false !== strpos( $head_tag, 'https://' ) ? str_replace( 'https://', 'http://', $head_tag ) : str_replace( 'http://', 'https://', $head_tag );
		$html     = $this->get_sample_html_string( $head_tag );
		$this->assertTrue( wp_is_local_html_output( $html ) );

		// HTML does not include REST API link.
		$html = $this->get_sample_html_string();
		$this->assertFalse( wp_is_local_html_output( $html ) );
	}

	/**
	 * @ticket 47577
	 */
	public function test_wp_is_local_html_output_cannot_determine() {
		remove_action( 'wp_head', 'rest_output_link_wp_head' );

		// The HTML here doesn't matter because all hooks are removed.
		$html = $this->get_sample_html_string();
		$this->assertNull( wp_is_local_html_output( $html ) );
	}

	public function record_request_url( $preempt, $parsed_args, $url ) {
		$this->last_request_url = $url;
		return $preempt;
	}

	public function mock_success_with_sslverify( $preempt, $parsed_args ) {
		if ( ! empty( $parsed_args['sslverify'] ) ) {
			return $this->mock_success();
		}
		return $preempt;
	}

	public function mock_error_with_sslverify( $preempt, $parsed_args ) {
		if ( ! empty( $parsed_args['sslverify'] ) ) {
			return $this->mock_error();
		}
		return $preempt;
	}

	public function mock_success_without_sslverify( $preempt, $parsed_args ) {
		if ( empty( $parsed_args['sslverify'] ) ) {
			return $this->mock_success();
		}
		return $preempt;
	}

	public function mock_error_without_sslverify( $preempt, $parsed_args ) {
		if ( empty( $parsed_args['sslverify'] ) ) {
			return $this->mock_error();
		}
		return $preempt;
	}

	public function mock_not_found() {
		return array(
			'body'     => '<!DOCTYPE html><html><head><title>404</title></head><body>Not Found</body></html>',
			'response' => array(
				'code'    => 404,
				'message' => 'Not Found',
			),
		);
	}

	public function mock_bad_source() {
		// Looks like a success response, but is not generated by WordPress (e.g. missing RSD link).
		return array(
			'body'     => $this->get_sample_html_string(),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
		);
	}

	private function mock_success() {
		// Success response containing RSD link.
		return array(
			'body'     => $this->get_sample_html_string(),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
		);
	}

	private function mock_error() {
		return new WP_Error( 'bad_ssl_certificate', 'Bad SSL certificate.' );
	}

	private function get_sample_html_string( $head_tag = '' ) {
		return '<!DOCTYPE html><html><head><title>Page Title</title>' . $head_tag . '</head><body>Page Content.</body></html>';
	}

	/**
	 * Returns a filter callback that expects a URL and will set the URL scheme
	 * to the provided $scheme.
	 *
	 * @param string $scheme URL scheme to set.
	 * @return callable Filter callback.
	 */
	private function filter_set_url_scheme( $scheme ) {
		return function( $url ) use ( $scheme ) {
			return set_url_scheme( $url, $scheme );
		};
	}
}
