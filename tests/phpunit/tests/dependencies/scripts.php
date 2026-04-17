<?php
/**
 * @group dependencies
 * @group scripts
 * @covers ::wp_enqueue_script
 * @covers ::wp_register_script
 * @covers ::wp_print_scripts
 * @covers ::wp_script_add_data
 * @covers ::wp_add_inline_script
 * @covers ::wp_set_script_translations
 */
class Tests_Dependencies_Scripts extends WP_UnitTestCase {

	/**
	 * @var WP_Scripts
	 */
	protected $old_wp_scripts;

	/**
	 * @var WP_Styles
	 */
	protected $old_wp_styles;

	protected $wp_scripts_print_translations_output;

	/**
	 * Stores a string reference to a default scripts directory name, utilised by certain tests.
	 *
	 * @var string
	 */
	protected $default_scripts_dir = '/directory/';

	public function set_up() {
		parent::set_up();
		$this->old_wp_scripts          = isset( $GLOBALS['wp_scripts'] ) ? $GLOBALS['wp_scripts'] : null;
		$this->old_wp_styles           = isset( $GLOBALS['wp_styles'] ) ? $GLOBALS['wp_styles'] : null;
		remove_action( 'wp_default_scripts', 'wp_default_scripts' );
		remove_action( 'wp_default_scripts', 'wp_default_packages' );
		$GLOBALS['wp_scripts']                  = new WP_Scripts();
		$GLOBALS['wp_scripts']->default_version = calm_version_hash( calmpress_version() );
		$GLOBALS['wp_styles']                   = new WP_Styles();

		$this->wp_scripts_print_translations_output  = <<<JS
<script type='text/javascript'>
/* <![CDATA[ */
( function( domain, translations ) {
	var localeData = translations.locale_data[ domain ] || translations.locale_data.messages;
	localeData[""].domain = domain;
	wp.i18n.setLocaleData( localeData, domain );
} )( "__DOMAIN__", __JSON_TRANSLATIONS__ );
//# sourceURL=__HANDLE__-js-translations
/* ]]> */
</script>
JS;
		$this->wp_scripts_print_translations_output .= "\n";
	}

	public function tear_down() {
		$GLOBALS['wp_scripts']          = $this->old_wp_scripts;
		$GLOBALS['wp_styles']           = $this->old_wp_styles;
		add_action( 'wp_default_scripts', 'wp_default_scripts' );
		parent::tear_down();
	}

	/**
	 * Test versioning
	 *
	 * @ticket 11315
	 */
	public function test_wp_enqueue_script() {
		global $wp_version;

		wp_enqueue_script( 'no-deps-no-version', 'example.com', array() );
		wp_enqueue_script( 'empty-deps-no-version', 'example.com' );
		wp_enqueue_script( 'empty-deps-version', 'example.com', array(), 1.2 );
		wp_enqueue_script( 'empty-deps-null-version', 'example.com', array(), null );

		$ver       = calm_version_hash( calmpress_version() );
		$expected  = "<script type='text/javascript' src='http://example.com?ver=$ver'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com?ver=$ver'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com?ver=1.2'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com'></script>\n";

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );

		// No scripts left to print.
		$this->assertSame( '', get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Gets delayed strategies as a data provider.
	 *
	 * @return array[] Delayed strategies.
	 */
	public function data_provider_delayed_strategies() {
		return array(
			'defer' => array( 'defer' ),
			'async' => array( 'async' ),
		);
	}

	/**
	 * Tests that inline scripts in the `after` position, attached to delayed main scripts, remain unaffected.
	 *
	 * If the main script with delayed loading strategy has an `after` inline script,
	 * the inline script should not be affected.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_inline_script_tag
	 * @covers ::wp_add_inline_script
	 * @covers ::wp_enqueue_script
	 *
	 * @dataProvider data_provider_delayed_strategies
	 *
	 * @param string $strategy Strategy.
	 */
	public function test_after_inline_script_with_delayed_main_script( $strategy ) {
		wp_enqueue_script( 'ms-isa-1', 'http://example.org/ms-isa-1.js', array(), null, compact( 'strategy' ) );
		wp_add_inline_script( 'ms-isa-1', 'console.log(\'after one\');', 'after' );
		$output    = get_echo( 'wp_print_scripts' );
		$expected  = "<script type='text/javascript' src='http://example.org/ms-isa-1.js' data-wp-strategy='{$strategy}'></script>\n";
		$expected .= wp_get_inline_script_tag(
			"console.log('after one');\n//# sourceURL=ms-isa-1-js-after",
			array(
			)
		);
		$this->assertEqualHTML( $expected, $output, '<body>', 'Inline scripts in the "after" position, that are attached to a deferred main script, are failing to print/execute.' );
	}

	/**
	 * Tests that inline scripts in the `after` position, attached to a blocking main script, are rendered as javascript.
	 *
	 * If a main script with a `blocking` strategy has an `after` inline script,
	 * the inline script should be rendered as type='text/javascript'.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_inline_script_tag
	 * @covers ::wp_add_inline_script
	 * @covers ::wp_enqueue_script
	 */
	public function test_after_inline_script_with_blocking_main_script() {
		wp_enqueue_script( 'ms-insa-3', 'http://example.org/ms-insa-3.js', array(), null );
		wp_add_inline_script( 'ms-insa-3', 'console.log(\'after one\');', 'after' );
		$output = get_echo( 'wp_print_scripts' );

		$expected  = "<script type='text/javascript' src='http://example.org/ms-insa-3.js'></script>\n";
		$expected .= wp_get_inline_script_tag(
			"console.log('after one');\n//# sourceURL=ms-insa-3-js-after",
			array(
			)
		);

		$this->assertEqualHTML( $expected, $output, '<body>', 'Inline scripts in the "after" position, that are attached to a blocking main script, are failing to print/execute.' );
	}

	/**
	 * Tests that inline scripts in the `before` position, attached to a delayed inline main script, results in all
	 * dependents being delayed.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_inline_script_tag
	 * @covers ::wp_add_inline_script
	 * @covers ::wp_enqueue_script
	 *
	 * @dataProvider data_provider_delayed_strategies
	 *
	 * @param string $strategy
	 */
	public function test_before_inline_scripts_with_delayed_main_script( $strategy ) {
		wp_enqueue_script( 'ds-i1-1', 'http://example.org/ds-i1-1.js', array(), null, compact( 'strategy' ) );
		wp_add_inline_script( 'ds-i1-1', 'console.log(\'before first\');', 'before' );
		wp_enqueue_script( 'ds-i1-2', 'http://example.org/ds-i1-2.js', array(), null, compact( 'strategy' ) );
		wp_enqueue_script( 'ds-i1-3', 'http://example.org/ds-i1-3.js', array(), null, compact( 'strategy' ) );
		wp_enqueue_script( 'ms-i1-1', 'http://example.org/ms-i1-1.js', array( 'ds-i1-1', 'ds-i1-2', 'ds-i1-3' ), null, compact( 'strategy' ) );
		wp_add_inline_script( 'ms-i1-1', 'console.log(\'before last\');', 'before' );
		$output = get_echo( 'wp_print_scripts' );

		$expected  = wp_get_inline_script_tag(
			"console.log('before first');\n//# sourceURL=ds-i1-1-js-before",
			array(
			)
		);
		$expected .= "<script type='text/javascript' src='http://example.org/ds-i1-1.js' {$strategy}='{$strategy}' data-wp-strategy='{$strategy}'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ds-i1-2.js' {$strategy}='{$strategy}' data-wp-strategy='{$strategy}'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.org/ds-i1-3.js' {$strategy}='{$strategy}' data-wp-strategy='{$strategy}'></script>\n";
		$expected .= wp_get_inline_script_tag(
			"console.log('before last');\n//# sourceURL=ms-i1-1-js-before",
			array(
				'type' => 'text/javascript',
			)
		);
		$expected .= "<script type='text/javascript' src='http://example.org/ms-i1-1.js' {$strategy}='{$strategy}' data-wp-strategy='{$strategy}'></script>\n";

		$this->assertEqualHTML( $expected, $output, '<body>', 'Inline scripts in the "before" position, that are attached to a deferred main script, are failing to print/execute.' );
	}

	/**
	 * Tests that scripts registered with an async strategy print with the async attribute.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers WP_Scripts::filter_eligible_strategies
	 * @covers ::wp_enqueue_script
	 */
	public function test_loading_strategy_with_valid_async_registration() {
		// No dependents, No dependencies then async.
		wp_enqueue_script( 'main-script-a1', '/main-script-a1.js', array(), null, array( 'strategy' => 'async' ) );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = "<script type='text/javascript' src='/main-script-a1.js' async='async' data-wp-strategy='async'></script>\n";
		$this->assertEqualHTML( $expected, $output, '<body>', 'Scripts enqueued with an async loading strategy are failing to have the async attribute applied to the script handle when being printed.' );
	}

	/**
	 * Tests that dependents of a blocking dependency script are free to contain any strategy.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers WP_Scripts::filter_eligible_strategies
	 * @covers ::wp_enqueue_script
	 *
	 * @dataProvider data_provider_delayed_strategies
	 *
	 * @param string $strategy Strategy.
	 */
	public function test_delayed_dependent_with_blocking_dependency( $strategy ) {
		wp_enqueue_script( 'dependency-script-a2', '/dependency-script-a2.js', array(), null );
		wp_enqueue_script( 'main-script-a2', '/main-script-a2.js', array( 'dependency-script-a2' ), null, compact( 'strategy' ) );
		$output    = get_echo( 'wp_print_scripts' );
		$expected  = "<script src='/dependency-script-a2.js' type='text/javascript'></script>\n";
		$expected .= "<script type='text/javascript' src='/main-script-a2.js' {$strategy}='{$strategy}' data-wp-strategy='{$strategy}'></script>\n";
		$this->assertEqualHTML( $expected, $output, '<body>', 'Dependents of a blocking dependency are free to have any strategy.' );
	}

	/**
	 * Tests that blocking dependents force delayed dependencies to become blocking.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers WP_Scripts::filter_eligible_strategies
	 * @covers ::wp_enqueue_script
	 *
	 * @dataProvider data_provider_delayed_strategies
	 * @param string $strategy Strategy.
	 */
	public function test_blocking_dependent_with_delayed_dependency( $strategy ) {
		wp_enqueue_script( 'main-script-a3', '/main-script-a3.js', array(), null, compact( 'strategy' ) );
		wp_enqueue_script( 'dependent-script-a3', '/dependent-script-a3.js', array( 'main-script-a3' ), null );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = <<<JS
<script type='text/javascript' src='/main-script-a3.js' data-wp-strategy='{$strategy}'></script>
<script src="/dependent-script-a3.js" type="text/javascript"></script>

JS;
		$this->assertEqualHTML( $expected, $output, '<body>', 'Blocking dependents must force delayed dependencies to become blocking.' );
	}

	/**
	 * Tests that only enqueued dependents effect the eligible loading strategy.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers WP_Scripts::filter_eligible_strategies
	 * @covers ::wp_enqueue_script
	 *
	 * @dataProvider data_provider_delayed_strategies
	 * @param string $strategy Strategy.
	 */
	public function test_delayed_dependent_with_blocking_dependency_not_enqueued( $strategy ) {
		$this->add_html5_script_theme_support();
		wp_enqueue_script( 'main-script-a4', '/main-script-a4.js', array(), null, compact( 'strategy' ) );
		// This dependent is registered but not enqueued, so it should not factor into the eligible loading strategy.
		wp_register_script( 'dependent-script-a4', '/dependent-script-a4.js', array( 'main-script-a4' ), null );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = str_replace( "'", '"', "<script src='/main-script-a4.js' {$strategy} data-wp-strategy='{$strategy}'></script>" );
		$this->assertStringContainsString( $expected, $output, 'Only enqueued dependents should affect the eligible strategy.' );
	}

	/**
	 * Data provider for test_filter_eligible_strategies.
	 *
	 * @return array
	 */
	public function get_data_to_filter_eligible_strategies() {
		return array(
			'no_dependents'                       => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'defer' ) );
					return 'foo';
				},
				'expected' => array( 'defer' ),
			),
			'one_delayed_dependent'               => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script( 'bar', 'https://example.com/bar.js', array( 'foo' ), null, array( 'strategy' => 'defer' ) );
					return 'foo';
				},
				'expected' => array( 'defer' ),
			),
			'one_blocking_dependent'              => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script( 'bar', 'https://example.com/bar.js', array( 'foo' ), null );
					return 'foo';
				},
				'expected' => array(),
			),
			'one_blocking_dependent_not_enqueued' => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_register_script( 'bar', 'https://example.com/bar.js', array( 'foo' ), null );
					return 'foo';
				},
				'expected' => array( 'defer' ), // Because bar was not enqueued, only foo was.
			),
			'two_delayed_dependents'              => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script( 'bar', 'https://example.com/bar.js', array( 'foo' ), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script( 'baz', 'https://example.com/baz.js', array( 'foo' ), null, array( 'strategy' => 'defer' ) );
					return 'foo';
				},
				'expected' => array( 'defer' ),
			),
			'recursion_not_delayed'               => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array( 'foo' ), null );
					return 'foo';
				},
				'expected' => array(),
			),
			'recursion_yes_delayed'               => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array( 'foo' ), null, array( 'strategy' => 'defer' ) );
					return 'foo';
				},
				'expected' => array( 'defer' ),
			),
			'recursion_triple_level'              => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array( 'baz' ), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script( 'bar', 'https://example.com/bar.js', array( 'foo' ), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script( 'baz', 'https://example.com/bar.js', array( 'bar' ), null, array( 'strategy' => 'defer' ) );
					return 'foo';
				},
				'expected' => array( 'defer' ),
			),
			'async_only_with_async_dependency'    => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'async' ) );
					wp_enqueue_script( 'bar', 'https://example.com/bar.js', array( 'foo' ), null, array( 'strategy' => 'async' ) );
					return 'foo';
				},
				'expected' => array( 'defer', 'async' ),
			),
			'async_only_with_defer_dependency'    => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'async' ) );
					wp_enqueue_script( 'bar', 'https://example.com/bar.js', array( 'foo' ), null, array( 'strategy' => 'defer' ) );
					return 'foo';
				},
				'expected' => array( 'defer' ),
			),
			'async_only_with_blocking_dependency' => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'async' ) );
					wp_enqueue_script( 'bar', 'https://example.com/bar.js', array( 'foo' ), null );
					return 'foo';
				},
				'expected' => array(),
			),
			'defer_with_inline_after_script'      => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_add_inline_script( 'foo', 'console.log("foo")', 'after' );
					return 'foo';
				},
				'expected' => array(),
			),
			'defer_with_inline_before_script'     => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_add_inline_script( 'foo', 'console.log("foo")', 'before' );
					return 'foo';
				},
				'expected' => array( 'defer' ),
			),
			'async_with_inline_after_script'      => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'async' ) );
					wp_add_inline_script( 'foo', 'console.log("foo")', 'after' );
					return 'foo';
				},
				'expected' => array(),
			),
			'async_with_inline_before_script'     => array(
				'set_up'   => static function () {
					wp_enqueue_script( 'foo', 'https://example.com/foo.js', array(), null, array( 'strategy' => 'async' ) );
					wp_add_inline_script( 'foo', 'console.log("foo")', 'before' );
					return 'foo';
				},
				'expected' => array( 'defer', 'async' ),
			),
		);
	}

	/**
	 * Tests that the filter_eligible_strategies method works as expected and returns the correct value.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::filter_eligible_strategies
	 *
	 * @dataProvider get_data_to_filter_eligible_strategies
	 *
	 * @param callable $set_up     Set up.
	 * @param bool     $async_only Async only.
	 * @param bool     $expected   Expected return value.
	 */
	public function test_filter_eligible_strategies( $set_up, $expected ) {
		$handle = $set_up();

		$wp_scripts_reflection      = new ReflectionClass( WP_Scripts::class );
		$filter_eligible_strategies = $wp_scripts_reflection->getMethod( 'filter_eligible_strategies' );
		if ( PHP_VERSION_ID < 80100 ) {
			$filter_eligible_strategies->setAccessible( true );
		}
		$this->assertSame( $expected, $filter_eligible_strategies->invokeArgs( wp_scripts(), array( $handle ) ), 'Expected return value of WP_Scripts::filter_eligible_strategies to match.' );
	}

	/**
	 * Register test script.
	 *
	 * @param string   $handle    Dependency handle to enqueue.
	 * @param string   $strategy  Strategy to use for dependency.
	 * @param string[] $deps      Dependencies for the script.
	 * @param bool     $in_footer Whether to print the script in the footer.
	 */
	protected function register_test_script( $handle, $strategy, $deps = array(), $in_footer = false ) {
		wp_register_script(
			$handle,
			add_query_arg(
				array(
					'script_event_log' => "$handle: script",
				),
				'https://example.com/external.js'
			),
			$deps,
			null
		);
		if ( 'blocking' !== $strategy ) {
			wp_script_add_data( $handle, 'strategy', $strategy );
		}
	}

	/**
	 * Enqueue test script.
	 *
	 * @param string   $handle    Dependency handle to enqueue.
	 * @param string   $strategy  Strategy to use for dependency.
	 * @param string[] $deps      Dependencies for the script.
	 * @param bool     $in_footer Whether to print the script in the footer.
	 */
	protected function enqueue_test_script( $handle, $strategy, $deps = array(), $in_footer = false ) {
		$this->register_test_script( $handle, $strategy, $deps, $in_footer );
		wp_enqueue_script( $handle );
	}

	/**
	 * Adds test inline script.
	 *
	 * @param string $handle   Dependency handle to enqueue.
	 * @param string $position Position.
	 */
	protected function add_test_inline_script( $handle, $position ) {
		wp_add_inline_script( $handle, sprintf( 'scriptEventLog.push( %s )', wp_json_encode( "{$handle}: {$position} inline" ) ), $position );
	}

	/**
	 * Data provider to test various strategy dependency chains.
	 *
	 * @return array[]
	 */
	public function data_provider_to_test_various_strategy_dependency_chains() {
		$wp_tests_domain = WP_TESTS_DOMAIN;

		return array(
			'async-dependent-with-one-blocking-dependency' => array(
				'set_up'          => function () {
					$handle1 = 'blocking-not-async-without-dependency';
					$handle2 = 'async-with-blocking-dependency';
					$this->enqueue_test_script( $handle1, 'blocking', array() );
					$this->enqueue_test_script( $handle2, 'async', array( $handle1 ) );
					foreach ( array( $handle1, $handle2 ) as $handle ) {
						$this->add_test_inline_script( $handle, 'before' );
						$this->add_test_inline_script( $handle, 'after' );
					}
				},
				'expected_markup' => <<<HTML
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "blocking-not-async-without-dependency: before inline" )
//# sourceURL=blocking-not-async-without-dependency-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=blocking-not-async-without-dependency:%20script'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "blocking-not-async-without-dependency: after inline" )
//# sourceURL=blocking-not-async-without-dependency-js-after
/* ]]> */
</script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "async-with-blocking-dependency: before inline" )
//# sourceURL=async-with-blocking-dependency-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=async-with-blocking-dependency:%20script' data-wp-strategy='async'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "async-with-blocking-dependency: after inline" )
//# sourceURL=async-with-blocking-dependency-js-after
/* ]]> */
</script>
HTML
				,
				/*
				 * Note: The above comma must be on its own line in PHP<7.3 and not after the `HTML` identifier
				 * terminating the heredoc. Otherwise, a syntax error is raised with the line number being wildly wrong:
				 *
				 * PHP Parse error:  syntax error, unexpected '' (T_ENCAPSED_AND_WHITESPACE), expecting '-' or identifier (T_STRING) or variable (T_VARIABLE) or number (T_NUM_STRING)
				 */
			),
			'async-with-async-dependencies'                => array(
				'set_up'          => function () {
					$handle1 = 'async-no-dependency';
					$handle2 = 'async-one-async-dependency';
					$handle3 = 'async-two-async-dependencies';
					$this->enqueue_test_script( $handle1, 'async', array() );
					$this->enqueue_test_script( $handle2, 'async', array( $handle1 ) );
					$this->enqueue_test_script( $handle3, 'async', array( $handle1, $handle2 ) );
					foreach ( array( $handle1, $handle2, $handle3 ) as $handle ) {
						$this->add_test_inline_script( $handle, 'before' );
						$this->add_test_inline_script( $handle, 'after' );
					}
				},
				'expected_markup' => <<<HTML
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "async-no-dependency: before inline" )
//# sourceURL=async-no-dependency-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=async-no-dependency:%20script' data-wp-strategy='async'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "async-no-dependency: after inline" )
//# sourceURL=async-no-dependency-js-after
/* ]]> */
</script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "async-one-async-dependency: before inline" )
//# sourceURL=async-one-async-dependency-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=async-one-async-dependency:%20script' data-wp-strategy='async'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "async-one-async-dependency: after inline" )
//# sourceURL=async-one-async-dependency-js-after
/* ]]> */
</script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "async-two-async-dependencies: before inline" )
//# sourceURL=async-two-async-dependencies-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=async-two-async-dependencies:%20script' data-wp-strategy='async'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "async-two-async-dependencies: after inline" )
//# sourceURL=async-two-async-dependencies-js-after
/* ]]> */
</script>
HTML
				,
			),
			'async-with-blocking-dependency'               => array(
				'set_up'          => function () {
					$handle1 = 'async-with-blocking-dependent';
					$handle2 = 'blocking-dependent-of-async';
					$this->enqueue_test_script( $handle1, 'async', array() );
					$this->enqueue_test_script( $handle2, 'blocking', array( $handle1 ) );
					foreach ( array( $handle1, $handle2 ) as $handle ) {
						$this->add_test_inline_script( $handle, 'before' );
						$this->add_test_inline_script( $handle, 'after' );
					}
				},
				'expected_markup' => <<<HTML
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "async-with-blocking-dependent: before inline" )
//# sourceURL=async-with-blocking-dependent-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=async-with-blocking-dependent:%20script' data-wp-strategy='async'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "async-with-blocking-dependent: after inline" )
//# sourceURL=async-with-blocking-dependent-js-after
/* ]]> */
</script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "blocking-dependent-of-async: before inline" )
//# sourceURL=blocking-dependent-of-async-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=blocking-dependent-of-async:%20script'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "blocking-dependent-of-async: after inline" )
//# sourceURL=blocking-dependent-of-async-js-after
/* ]]> */
</script>
HTML
				,
			),
			'defer-with-async-dependency'                  => array(
				'set_up'          => function () {
					$handle1 = 'async-with-defer-dependent';
					$handle2 = 'defer-dependent-of-async';
					$this->enqueue_test_script( $handle1, 'async', array() );
					$this->enqueue_test_script( $handle2, 'defer', array( $handle1 ) );
					foreach ( array( $handle1, $handle2 ) as $handle ) {
						$this->add_test_inline_script( $handle, 'before' );
						$this->add_test_inline_script( $handle, 'after' );
					}
				},
				'expected_markup' => <<<HTML
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "async-with-defer-dependent: before inline" )
//# sourceURL=async-with-defer-dependent-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=async-with-defer-dependent:%20script' data-wp-strategy='async'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "async-with-defer-dependent: after inline" )
//# sourceURL=async-with-defer-dependent-js-after
/* ]]> */
</script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-dependent-of-async: before inline" )
//# sourceURL=defer-dependent-of-async-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-dependent-of-async:%20script' data-wp-strategy='defer'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-dependent-of-async: after inline" )
//# sourceURL=defer-dependent-of-async-js-after
/* ]]> */
</script>
HTML
				,
			),
			'blocking-bundle-of-none-with-inline-scripts-and-defer-dependent' => array(
				'set_up'          => function () {
					$handle1 = 'blocking-bundle-of-none';
					$handle2 = 'defer-dependent-of-blocking-bundle-of-none';

					wp_register_script( $handle1, false, array(), null );
					$this->add_test_inline_script( $handle1, 'before' );
					$this->add_test_inline_script( $handle1, 'after' );

					// Note: the before script for this will be blocking because the dependency is blocking.
					$this->enqueue_test_script( $handle2, 'defer', array( $handle1 ) );
					$this->add_test_inline_script( $handle2, 'before' );
					$this->add_test_inline_script( $handle2, 'after' );
				},
				'expected_markup' => <<<HTML
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "blocking-bundle-of-none: before inline" )
//# sourceURL=blocking-bundle-of-none-js-before
/* ]]> */
</script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "blocking-bundle-of-none: after inline" )
//# sourceURL=blocking-bundle-of-none-js-after
/* ]]> */
</script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-dependent-of-blocking-bundle-of-none: before inline" )
//# sourceURL=defer-dependent-of-blocking-bundle-of-none-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-dependent-of-blocking-bundle-of-none:%20script' data-wp-strategy='defer'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-dependent-of-blocking-bundle-of-none: after inline" )
//# sourceURL=defer-dependent-of-blocking-bundle-of-none-js-after
/* ]]> */
</script>
HTML
				,
			),
			'blocking-bundle-of-two-with-defer-dependent'  => array(
				'set_up'          => function () {
					$handle1 = 'blocking-bundle-of-two';
					$handle2 = 'blocking-bundle-member-one';
					$handle3 = 'blocking-bundle-member-two';
					$handle4 = 'defer-dependent-of-blocking-bundle-of-two';

					wp_register_script( $handle1, false, array( $handle2, $handle3 ), null );
					$this->enqueue_test_script( $handle2, 'blocking' );
					$this->enqueue_test_script( $handle3, 'blocking' );
					$this->enqueue_test_script( $handle4, 'defer', array( $handle1 ) );

					foreach ( array( $handle2, $handle3, $handle4 ) as $handle ) {
						$this->add_test_inline_script( $handle, 'before' );
						$this->add_test_inline_script( $handle, 'after' );
					}
				},
				'expected_markup' => <<<HTML
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "blocking-bundle-member-one: before inline" )
//# sourceURL=blocking-bundle-member-one-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=blocking-bundle-member-one:%20script'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "blocking-bundle-member-one: after inline" )
//# sourceURL=blocking-bundle-member-one-js-after
/* ]]> */
</script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "blocking-bundle-member-two: before inline" )
//# sourceURL=blocking-bundle-member-two-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=blocking-bundle-member-two:%20script'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "blocking-bundle-member-two: after inline" )
//# sourceURL=blocking-bundle-member-two-js-after
/* ]]> */
</script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-dependent-of-blocking-bundle-of-two: before inline" )
//# sourceURL=defer-dependent-of-blocking-bundle-of-two-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-dependent-of-blocking-bundle-of-two:%20script' data-wp-strategy='defer'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-dependent-of-blocking-bundle-of-two: after inline" )
//# sourceURL=defer-dependent-of-blocking-bundle-of-two-js-after
/* ]]> */
</script>
HTML
				,
			),
			'defer-bundle-of-none-with-inline-scripts-and-defer-dependents' => array(
				'set_up'          => function () {
					$handle1 = 'defer-bundle-of-none';
					$handle2 = 'defer-dependent-of-defer-bundle-of-none';

					// The eligible loading strategy for this will be forced to be blocking when rendered since $src = false.
					wp_register_script( $handle1, false, array(), null );
					wp_scripts()->registered[ $handle1 ]->extra['strategy'] = 'defer'; // Bypass wp_script_add_data() which should no-op with _doing_it_wrong() because of $src=false.
					$this->add_test_inline_script( $handle1, 'before' );
					$this->add_test_inline_script( $handle1, 'after' );

					// Note: the before script for this will be blocking because the dependency is blocking.
					$this->enqueue_test_script( $handle2, 'defer', array( $handle1 ) );
					$this->add_test_inline_script( $handle2, 'before' );
					$this->add_test_inline_script( $handle2, 'after' );
				},
				'expected_markup' => <<<HTML
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-bundle-of-none: before inline" )
//# sourceURL=defer-bundle-of-none-js-before
/* ]]> */
</script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-bundle-of-none: after inline" )
//# sourceURL=defer-bundle-of-none-js-after
/* ]]> */
</script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-dependent-of-defer-bundle-of-none: before inline" )
//# sourceURL=defer-dependent-of-defer-bundle-of-none-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-dependent-of-defer-bundle-of-none:%20script' data-wp-strategy='defer'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-dependent-of-defer-bundle-of-none: after inline" )
//# sourceURL=defer-dependent-of-defer-bundle-of-none-js-after
/* ]]> */
</script>
HTML
				,
			),
			'defer-dependent-with-blocking-and-defer-dependencies' => array(
				'set_up'          => function () {
					$handle1 = 'blocking-dependency-with-defer-following-dependency';
					$handle2 = 'defer-dependency-with-blocking-preceding-dependency';
					$handle3 = 'defer-dependent-of-blocking-and-defer-dependencies';
					$this->enqueue_test_script( $handle1, 'blocking', array() );
					$this->enqueue_test_script( $handle2, 'defer', array() );
					$this->enqueue_test_script( $handle3, 'defer', array( $handle1, $handle2 ) );

					foreach ( array( $handle1, $handle2, $handle3 ) as $dep ) {
						$this->add_test_inline_script( $dep, 'before' );
						$this->add_test_inline_script( $dep, 'after' );
					}
				},
				'expected_markup' => <<<HTML
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "blocking-dependency-with-defer-following-dependency: before inline" )
//# sourceURL=blocking-dependency-with-defer-following-dependency-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=blocking-dependency-with-defer-following-dependency:%20script'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "blocking-dependency-with-defer-following-dependency: after inline" )
//# sourceURL=blocking-dependency-with-defer-following-dependency-js-after
/* ]]> */
</script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-dependency-with-blocking-preceding-dependency: before inline" )
//# sourceURL=defer-dependency-with-blocking-preceding-dependency-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-dependency-with-blocking-preceding-dependency:%20script' data-wp-strategy='defer'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-dependency-with-blocking-preceding-dependency: after inline" )
//# sourceURL=defer-dependency-with-blocking-preceding-dependency-js-after
/* ]]> */
</script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-dependent-of-blocking-and-defer-dependencies: before inline" )
//# sourceURL=defer-dependent-of-blocking-and-defer-dependencies-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-dependent-of-blocking-and-defer-dependencies:%20script' data-wp-strategy='defer'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-dependent-of-blocking-and-defer-dependencies: after inline" )
//# sourceURL=defer-dependent-of-blocking-and-defer-dependencies-js-after
/* ]]> */
</script>
HTML
				,
			),
			'defer-dependent-with-defer-and-blocking-dependencies' => array(
				'set_up'          => function () {
					$handle1 = 'defer-dependency-with-blocking-following-dependency';
					$handle2 = 'blocking-dependency-with-defer-preceding-dependency';
					$handle3 = 'defer-dependent-of-defer-and-blocking-dependencies';
					$this->enqueue_test_script( $handle1, 'defer', array() );
					$this->enqueue_test_script( $handle2, 'blocking', array() );
					$this->enqueue_test_script( $handle3, 'defer', array( $handle1, $handle2 ) );

					foreach ( array( $handle1, $handle2, $handle3 ) as $dep ) {
						$this->add_test_inline_script( $dep, 'before' );
						$this->add_test_inline_script( $dep, 'after' );
					}
				},
				'expected_markup' => <<<HTML
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-dependency-with-blocking-following-dependency: before inline" )
//# sourceURL=defer-dependency-with-blocking-following-dependency-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-dependency-with-blocking-following-dependency:%20script' data-wp-strategy='defer'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-dependency-with-blocking-following-dependency: after inline" )
//# sourceURL=defer-dependency-with-blocking-following-dependency-js-after
/* ]]> */
</script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "blocking-dependency-with-defer-preceding-dependency: before inline" )
//# sourceURL=blocking-dependency-with-defer-preceding-dependency-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=blocking-dependency-with-defer-preceding-dependency:%20script'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "blocking-dependency-with-defer-preceding-dependency: after inline" )
//# sourceURL=blocking-dependency-with-defer-preceding-dependency-js-after
/* ]]> */
</script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-dependent-of-defer-and-blocking-dependencies: before inline" )
//# sourceURL=defer-dependent-of-defer-and-blocking-dependencies-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-dependent-of-defer-and-blocking-dependencies:%20script' data-wp-strategy='defer'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-dependent-of-defer-and-blocking-dependencies: after inline" )
//# sourceURL=defer-dependent-of-defer-and-blocking-dependencies-js-after
/* ]]> */
</script>
HTML
				,
			),
			'async-with-defer-dependency'                  => array(
				'set_up'          => function () {
					$handle1 = 'defer-with-async-dependent';
					$handle2 = 'async-dependent-of-defer';
					$this->enqueue_test_script( $handle1, 'defer', array() );
					$this->enqueue_test_script( $handle2, 'async', array( $handle1 ) );
					foreach ( array( $handle1, $handle2 ) as $handle ) {
						$this->add_test_inline_script( $handle, 'before' );
						$this->add_test_inline_script( $handle, 'after' );
					}
				},
				'expected_markup' => <<<HTML
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-with-async-dependent: before inline" )
//# sourceURL=defer-with-async-dependent-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-with-async-dependent:%20script' data-wp-strategy='defer'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-with-async-dependent: after inline" )
//# sourceURL=defer-with-async-dependent-js-after
/* ]]> */
</script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "async-dependent-of-defer: before inline" )
//# sourceURL=async-dependent-of-defer-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=async-dependent-of-defer:%20script' data-wp-strategy='async'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "async-dependent-of-defer: after inline" )
//# sourceURL=async-dependent-of-defer-js-after
/* ]]> */
</script>
HTML
				,
			),
			'defer-with-before-inline-script'              => array(
				'set_up'          => function () {
					// Note this should NOT result in no delayed-inline-script-loader script being added.
					$handle = 'defer-with-before-inline';
					$this->enqueue_test_script( $handle, 'defer', array() );
					$this->add_test_inline_script( $handle, 'before' );
				},
				'expected_markup' => <<<HTML
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-with-before-inline: before inline" )
//# sourceURL=defer-with-before-inline-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-with-before-inline:%20script' defer='defer' data-wp-strategy='defer'></script>
HTML
				,
			),
			'defer-with-after-inline-script'               => array(
				'set_up'          => function () {
					// Note this SHOULD result in delayed-inline-script-loader script being added.
					$handle = 'defer-with-after-inline';
					$this->enqueue_test_script( $handle, 'defer', array() );
					$this->add_test_inline_script( $handle, 'after' );
				},
				'expected_markup' => <<<HTML
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-with-after-inline:%20script' data-wp-strategy='defer'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-with-after-inline: after inline" )
//# sourceURL=defer-with-after-inline-js-after
/* ]]> */
</script>
HTML
				,
			),
			'jquery-deferred'                              => array(
				'set_up'          => function () {
					$wp_scripts = wp_scripts();
					wp_default_scripts( $wp_scripts );
					foreach ( $wp_scripts->registered['jquery']->deps as $jquery_dep ) {
						$wp_scripts->registered[ $jquery_dep ]->add_data( 'strategy', 'defer' );
						$wp_scripts->registered[ $jquery_dep ]->ver = null; // Just to avoid markup changes in the test when jQuery is upgraded.
					}
					wp_enqueue_script( 'theme-functions', 'https://example.com/theme-functions.js', array( 'jquery' ), null, array( 'strategy' => 'defer' ) );
				},
				'expected_markup' => <<<HTML
<script type='text/javascript' src='http://$wp_tests_domain/wp-includes/js/jquery/jquery.min.js' defer='defer' data-wp-strategy='defer'></script>
<script type='text/javascript' src='http://$wp_tests_domain/wp-includes/js/jquery/jquery-migrate.min.js' defer='defer' data-wp-strategy='defer'></script>
<script type='text/javascript' src='https://example.com/theme-functions.js' defer='defer' data-wp-strategy='defer'></script>
HTML
				,
			),
			'nested-aliases'                               => array(
				'set_up'          => function () {
					$outer_alias_handle = 'outer-bundle-of-two';
					$inner_alias_handle = 'inner-bundle-of-two';

					// The outer alias contains a blocking member, as well as a nested alias that contains defer scripts.
					wp_register_script( $outer_alias_handle, false, array( $inner_alias_handle, 'outer-bundle-leaf-member' ), null );
					$this->register_test_script( 'outer-bundle-leaf-member', 'blocking', array() );

					// Inner alias only contains delay scripts.
					wp_register_script( $inner_alias_handle, false, array( 'inner-bundle-member-one', 'inner-bundle-member-two' ), null );
					$this->register_test_script( 'inner-bundle-member-one', 'defer', array() );
					$this->register_test_script( 'inner-bundle-member-two', 'defer', array() );

					$this->enqueue_test_script( 'defer-dependent-of-nested-aliases', 'defer', array( $outer_alias_handle ) );
					$this->add_test_inline_script( 'defer-dependent-of-nested-aliases', 'before' );
					$this->add_test_inline_script( 'defer-dependent-of-nested-aliases', 'after' );
				},
				'expected_markup' => <<<HTML
<script type='text/javascript' src='https://example.com/external.js?script_event_log=inner-bundle-member-one:%20script' data-wp-strategy='defer'></script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=inner-bundle-member-two:%20script' data-wp-strategy='defer'></script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=outer-bundle-leaf-member:%20script'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-dependent-of-nested-aliases: before inline" )
//# sourceURL=defer-dependent-of-nested-aliases-js-before
/* ]]> */
</script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-dependent-of-nested-aliases:%20script' data-wp-strategy='defer'></script>
<script type="text/javascript">
/* <![CDATA[ */
scriptEventLog.push( "defer-dependent-of-nested-aliases: after inline" )
//# sourceURL=defer-dependent-of-nested-aliases-js-after
/* ]]> */
</script>
HTML
				,
			),

			'async-alias-members-with-defer-dependency'    => array(
				'set_up'          => function () {
					$alias_handle = 'async-alias';
					$async_handle1 = 'async1';
					$async_handle2 = 'async2';

					wp_register_script( $alias_handle, false, array( $async_handle1, $async_handle2 ), null );
					$this->register_test_script( $async_handle1, 'async', array() );
					$this->register_test_script( $async_handle2, 'async', array() );

					$this->enqueue_test_script( 'defer-dependent-of-async-aliases', 'defer', array( $alias_handle ) );
				},
				'expected_markup' => <<<HTML
<script type='text/javascript' src='https://example.com/external.js?script_event_log=async1:%20script' defer='defer' data-wp-strategy='async'></script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=async2:%20script' defer='defer' data-wp-strategy='async'></script>
<script type='text/javascript' src='https://example.com/external.js?script_event_log=defer-dependent-of-async-aliases:%20script' defer='defer' data-wp-strategy='defer'></script>
HTML
				,
			),
		);
	}

	/**
	 * Tests that various loading strategy dependency chains function as expected.
	 *
	 * @covers ::wp_enqueue_script()
	 * @covers ::wp_add_inline_script()
	 * @covers ::wp_print_scripts()
	 * @covers WP_Scripts::get_inline_script_tag
	 *
	 * @dataProvider data_provider_to_test_various_strategy_dependency_chains
	 *
	 * @param callable $set_up          Set up.
	 * @param string   $expected_markup Expected markup.
	 */
	public function test_various_strategy_dependency_chains( $set_up, $expected_markup ) {
		$set_up();
		$actual_markup = get_echo( 'wp_print_scripts' );
		$this->assertEqualHTML( trim( $expected_markup ), trim( $actual_markup ), '<body>', "Actual markup:\n{$actual_markup}" );
	}

	/**
	 * Tests that defer is the final strategy when registering a script using defer, that has no dependents/dependencies.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers ::wp_enqueue_script
	 */
	public function test_loading_strategy_with_defer_having_no_dependents_nor_dependencies() {
		$this->add_html5_script_theme_support();
		wp_enqueue_script( 'main-script-d1', 'http://example.com/main-script-d1.js', array(), null, array( 'strategy' => 'defer' ) );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = str_replace( "'", '"', "<script src='http://example.com/main-script-d1.js' defer data-wp-strategy='defer'></script>\n" );
		$this->assertStringContainsString( $expected, $output, 'Expected defer, as there is no dependent or dependency' );
	}

	/**
	 * Tests that a script registered with defer remains deferred when all dependencies are either deferred or blocking.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers ::wp_enqueue_script
	 */
	public function test_loading_strategy_with_defer_dependent_and_varied_dependencies() {
		$this->add_html5_script_theme_support();
		wp_enqueue_script( 'dependency-script-d2-1', 'http://example.com/dependency-script-d2-1.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependency-script-d2-2', 'http://example.com/dependency-script-d2-2.js', array(), null );
		wp_enqueue_script( 'dependency-script-d2-3', 'http://example.com/dependency-script-d2-3.js', array( 'dependency-script-d2-2' ), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'main-script-d2', 'http://example.com/main-script-d2.js', array( 'dependency-script-d2-1', 'dependency-script-d2-3' ), null, array( 'strategy' => 'defer' ) );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = '<script src="http://example.com/main-script-d2.js" defer data-wp-strategy="defer"></script>';
		$this->assertStringContainsString( $expected, $output, 'Expected defer, as all dependencies are either deferred or blocking' );
	}

	/**
	 * Tests that scripts registered with defer remain deferred when all dependents are also deferred.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers ::wp_enqueue_script
	 */
	public function test_loading_strategy_with_all_defer_dependencies() {
		$this->add_html5_script_theme_support();
		wp_enqueue_script( 'main-script-d3', 'http://example.com/main-script-d3.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependent-script-d3-1', 'http://example.com/dependent-script-d3-1.js', array( 'main-script-d3' ), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependent-script-d3-2', 'http://example.com/dependent-script-d3-2.js', array( 'dependent-script-d3-1' ), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependent-script-d3-3', 'http://example.com/dependent-script-d3-3.js', array( 'dependent-script-d3-2' ), null, array( 'strategy' => 'defer' ) );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = '<script src="http://example.com/main-script-d3.js" defer data-wp-strategy="defer"></script>';
		$this->assertStringContainsString( $expected, $output, 'Expected defer, as all dependents have defer loading strategy' );
	}

	/**
	 * Tests that dependents that are async but attached to a deferred main script, print with defer as opposed to async.
	 *
	 * Also tests that fetchpriority attributes are added as expected.
	 *
	 * @ticket 12009
	 * @ticket 61734
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers ::wp_register_script
	 * @covers ::wp_enqueue_script
	 */
	public function test_defer_with_async_dependent() {
		// case with one async dependent.
		wp_register_script( 'main-script-d4', '/main-script-d4.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script(
			'dependent-script-d4-1',
			'/dependent-script-d4-1.js',
			array( 'main-script-d4' ),
			null,
			array(
				'strategy'      => 'defer',
				'fetchpriority' => 'auto',
			)
		);
		wp_enqueue_script(
			'dependent-script-d4-2',
			'/dependent-script-d4-2.js',
			array( 'dependent-script-d4-1' ),
			null,
			array(
				'strategy'      => 'async',
				'fetchpriority' => 'low',
			)
		);
		wp_enqueue_script(
			'dependent-script-d4-3',
			'/dependent-script-d4-3.js',
			array( 'dependent-script-d4-2' ),
			null,
			array(
				'strategy'      => 'defer',
				'fetchpriority' => 'high',
			)
		);
		// Note: All of these scripts have fetchpriority=high because the leaf dependent script has that fetch priority.
		$output    = get_echo( 'wp_print_scripts' );
		$expected  = "<script type='text/javascript' src='/main-script-d4.js'        defer='defer' data-wp-strategy='defer' fetchpriority='high' data-wp-fetchpriority='auto'></script>\n";
		$expected .= "<script type='text/javascript' src='/dependent-script-d4-1.js' defer='defer' data-wp-strategy='defer' fetchpriority='high' data-wp-fetchpriority='auto'></script>\n";
		$expected .= "<script type='text/javascript' src='/dependent-script-d4-2.js' defer='defer' data-wp-strategy='async' fetchpriority='high' data-wp-fetchpriority='low'></script>\n";
		$expected .= "<script type='text/javascript' src='/dependent-script-d4-3.js' defer='defer' data-wp-strategy='defer' fetchpriority='high'></script>\n";

		$this->assertEqualHTML( $expected, $output, '<body>', 'Scripts registered as defer but that have dependents that are async are expected to have said dependents deferred.' );
	}

	/**
	 * Data provider for test_fetchpriority_values.
	 *
	 * @return array<string, array{fetchpriority: string}>
	 */
	public function data_provider_fetchpriority_values(): array {
		return array(
			'auto' => array( 'fetchpriority' => 'auto' ),
			'low'  => array( 'fetchpriority' => 'low' ),
			'high' => array( 'fetchpriority' => 'high' ),
		);
	}

	/**
	 * Tests that valid fetchpriority values are correctly added to script data.
	 *
	 * @ticket 61734
	 *
	 * @covers ::wp_register_script
	 * @covers WP_Scripts::add_data
	 * @covers ::wp_script_add_data
	 *
	 * @dataProvider data_provider_fetchpriority_values
	 *
	 * @param string $fetchpriority The fetchpriority value to test.
	 */
	public function test_fetchpriority_values( string $fetchpriority ) {
		wp_register_script( 'test-script', '/test-script.js', array(), null, array( 'fetchpriority' => $fetchpriority ) );
		$this->assertArrayHasKey( 'fetchpriority', wp_scripts()->registered['test-script']->extra );
		$this->assertSame( $fetchpriority, wp_scripts()->registered['test-script']->extra['fetchpriority'] );

		wp_register_script( 'test-script-2', '/test-script-2.js' );
		$this->assertTrue( wp_script_add_data( 'test-script-2', 'fetchpriority', $fetchpriority ) );
		$this->assertArrayHasKey( 'fetchpriority', wp_scripts()->registered['test-script-2']->extra );
		$this->assertSame( $fetchpriority, wp_scripts()->registered['test-script-2']->extra['fetchpriority'] );
	}

	/**
	 * Tests that an empty fetchpriority is treated the same as auto.
	 *
	 * @ticket 61734
	 *
	 * @covers ::wp_register_script
	 * @covers WP_Scripts::add_data
	 */
	public function test_empty_fetchpriority_value() {
		wp_register_script( 'unset', '/joke.js', array(), null, array( 'fetchpriority' => 'low' ) );
		$this->assertSame( 'low', wp_scripts()->registered['unset']->extra['fetchpriority'] );
		$this->assertTrue( wp_script_add_data( 'unset', 'fetchpriority', null ) );
		$this->assertSame( 'auto', wp_scripts()->registered['unset']->extra['fetchpriority'] );
	}

	/**
	 * Tests that an invalid fetchpriority causes a _doing_it_wrong() warning.
	 *
	 * @ticket 61734
	 *
	 * @covers ::wp_register_script
	 * @covers WP_Scripts::add_data
	 *
	 * @expectedIncorrectUsage WP_Scripts::add_data
	 */
	public function test_invalid_fetchpriority_value() {
		wp_register_script( 'joke', '/joke.js', array(), null, array( 'fetchpriority' => 'silly' ) );
		$this->assertArrayNotHasKey( 'fetchpriority', wp_scripts()->registered['joke']->extra );
		$this->assertArrayHasKey( 'WP_Scripts::add_data', $this->caught_doing_it_wrong );
		$this->assertStringContainsString( 'Invalid fetchpriority `silly`', $this->caught_doing_it_wrong['WP_Scripts::add_data'] );
	}

	/**
	 * Tests that an invalid fetchpriority causes a _doing_it_wrong() warning.
	 *
	 * @ticket 61734
	 *
	 * @covers ::wp_register_script
	 * @covers WP_Scripts::add_data
	 *
	 * @expectedIncorrectUsage WP_Scripts::add_data
	 */
	public function test_invalid_fetchpriority_value_type() {
		wp_register_script( 'bad', '/bad.js' );
		$this->assertFalse( wp_script_add_data( 'bad', 'fetchpriority', array( 'THIS IS SO WRONG!!!' ) ) );
		$this->assertArrayNotHasKey( 'fetchpriority', wp_scripts()->registered['bad']->extra );
		$this->assertArrayHasKey( 'WP_Scripts::add_data', $this->caught_doing_it_wrong );
		$this->assertStringContainsString( 'Invalid fetchpriority `array`', $this->caught_doing_it_wrong['WP_Scripts::add_data'] );
	}

	/**
	 * Tests that adding fetchpriority causes a _doing_it_wrong() warning on a script alias.
	 *
	 * @ticket 61734
	 *
	 * @covers ::wp_register_script
	 * @covers WP_Scripts::add_data
	 *
	 * @expectedIncorrectUsage WP_Scripts::add_data
	 */
	public function test_invalid_fetchpriority_on_alias() {
		wp_register_script( 'alias', false, array(), null, array( 'fetchpriority' => 'low' ) );
		$this->assertArrayNotHasKey( 'fetchpriority', wp_scripts()->registered['alias']->extra );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{enqueues: string[], expected: string}>
	 */
	public function data_provider_to_test_fetchpriority_bumping(): array {
		return array(
			'enqueue_bajo' => array(
				'enqueues' => array( 'bajo' ),
				'expected' => "<script fetchpriority='low' src='/bajo.js' type='text/javascript'></script>\n",
			),
			'enqueue_auto' => array(
				'enqueues' => array( 'auto' ),
				'expected' =>
					"<script type='text/javascript' src='/bajo.js' data-wp-fetchpriority='low'></script>\n" .
					"<script type='text/javascript' src='/auto.js'></script>\n",
			),
			'enqueue_alto' => array(
				'enqueues' => array( 'alto' ),
				'expected' =>
					"<script type='text/javascript' src='/bajo.js' fetchpriority='high' data-wp-fetchpriority='low'></script>\n" .
					"<script type='text/javascript' src='/auto.js' fetchpriority='high' data-wp-fetchpriority='auto'></script>\n" .
					"<script type='text/javascript' src='/alto.js' fetchpriority='high'></script>\n",
			),
		);
	}

	/**
	 * Tests a higher fetchpriority on a dependent script module causes the fetchpriority of a dependency script module to be bumped.
	 *
	 * @ticket 61734
	 *
	 * @covers WP_Scripts::get_dependents
	 * @covers WP_Scripts::get_highest_fetchpriority_with_dependents
	 * @covers WP_Scripts::do_item
	 *
	 * @dataProvider data_provider_to_test_fetchpriority_bumping
	 */
	public function test_fetchpriority_bumping( array $enqueues, string $expected ) {
		wp_register_script( 'bajo', '/bajo.js', array(), null, array( 'fetchpriority' => 'low' ) );
		wp_register_script( 'auto', '/auto.js', array( 'bajo' ), null, array( 'fetchpriority' => 'auto' ) );
		wp_register_script( 'alto', '/alto.js', array( 'auto' ), null, array( 'fetchpriority' => 'high' ) );

		foreach ( $enqueues as $enqueue ) {
			wp_enqueue_script( $enqueue );
		}

		$actual = get_echo( 'wp_print_scripts' );
		$this->assertEqualHTML( $expected, $actual, '<body>', "Snapshot:\n$actual" );
	}

	/**
	 * Tests bumping fetchpriority with complex dependency graph.
	 *
	 * @ticket 61734
	 * @link https://github.com/WordPress/wordpress-develop/pull/9770#issuecomment-3280065818
	 *
	 * @covers WP_Scripts::get_dependents
	 * @covers WP_Scripts::get_highest_fetchpriority_with_dependents
	 * @covers WP_Scripts::do_item
	 */
	public function test_fetchpriority_bumping_a_to_z() {
		wp_register_script( 'a', '/a.js', array( 'b' ), null, array( 'fetchpriority' => 'low' ) );
		wp_register_script( 'b', '/b.js', array( 'c' ), null, array( 'fetchpriority' => 'auto' ) );
		wp_register_script( 'c', '/c.js', array( 'd', 'e' ), null, array( 'fetchpriority' => 'auto' ) );
		wp_register_script( 'd', '/d.js', array( 'z' ), null, array( 'fetchpriority' => 'high' ) );
		wp_register_script( 'e', '/e.js', array(), null, array( 'fetchpriority' => 'auto' ) );

		wp_register_script( 'x', '/x.js', array( 'd', 'y' ), null, array( 'fetchpriority' => 'high' ) );
		wp_register_script( 'y', '/y.js', array( 'z' ), null, array( 'fetchpriority' => 'auto' ) );
		wp_register_script( 'z', '/z.js', array(), null, array( 'fetchpriority' => 'auto' ) );

		wp_enqueue_script( 'a' );
		wp_enqueue_script( 'x' );

		$actual   = get_echo( 'wp_print_scripts' );
		$expected = <<<'HTML'
<script type="text/javascript" src="/z.js" fetchpriority="high" data-wp-fetchpriority="auto"></script>
<script type="text/javascript" src="/d.js" fetchpriority="high"></script>
<script type="text/javascript" src="/e.js"></script>
<script type="text/javascript" src="/c.js"></script>
<script type="text/javascript" src="/b.js"></script>
<script type="text/javascript" src="/a.js" fetchpriority="low"></script>
<script type="text/javascript" src="/y.js" fetchpriority="high" data-wp-fetchpriority="auto"></script>
<script type="text/javascript" src="/x.js" fetchpriority="high"></script>

HTML;
		$this->assertEqualHTML( $expected, $actual, '<body>', "Snapshot:\n$actual" );
	}

	/**
	 * Tests that `WP_Scripts::get_highest_fetchpriority_with_dependents()` correctly reuses cached results.
	 *
	 * @ticket 64194
	 *
	 * @covers WP_Scripts::get_highest_fetchpriority_with_dependents
	 */
	public function test_highest_fetchpriority_with_dependents_uses_cached_result() {
		$wp_scripts = new WP_Scripts();
		$wp_scripts->add( 'd', 'https://example.com/d.js' );
		$wp_scripts->add_data( 'd', 'fetchpriority', 'low' );

		/*
		 * Simulate a pre-existing `$stored_results` cache entry for `d`.
		 * If the caching logic works, the function should use this "high" value
		 * instead of recalculating based on the actual (lower) value.
		 */
		$stored_results = array( 'd' => 'high' );

		// Access the private method using reflection.
		$method = new ReflectionMethod( WP_Scripts::class, 'get_highest_fetchpriority_with_dependents' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		// Pass `$stored_results` BY REFERENCE.
		$result = $method->invokeArgs( $wp_scripts, array( 'd', array(), &$stored_results ) );

		$this->assertSame(
			'high',
			$result,
			'Expected "high" indicates that the cached `$stored_results` entry for D was used instead of recalculating.'
		);
	}

	/**
	 * Tests expected priority is used when a dependent is registered but not enqueued.
	 *
	 * @ticket 64429
	 *
	 * @covers WP_Scripts::print_scripts
	 * @covers WP_Scripts::get_highest_fetchpriority_with_dependents
	 */
	public function test_priority_of_dependency_for_non_enqueued_dependent() {
		$wp_scripts = wp_scripts();
		wp_default_scripts( $wp_scripts );

		$wp_scripts->add( 'not-enqueued', 'https://example.com/not-enqueued.js', array( 'comment-reply' ), null, array( 'priority' => 'high' ) );
		$wp_scripts->enqueue( 'comment-reply' );

		$actual = $this->normalize_markup_for_snapshot( get_echo( array( $wp_scripts, 'print_scripts' ) ) );
		$this->assertEqualHTML(
			"<script type='text/javascript' src='/wp-includes/js/comment-reply.min.js' async='async' data-wp-strategy='async' fetchpriority='low'></script>\n",
			$actual,
			'<body>',
			"Snapshot:\n$actual"
		);
	}

	/**
	 * Tests that printing a script without enqueueing has the same output as when it is enqueued.
	 *
	 * @ticket 61734
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::do_items
	 * @covers ::wp_default_scripts
	 *
	 * @dataProvider data_provider_enqueue_or_not_to_enqueue
	 */
	public function test_printing_default_script_comment_reply_enqueued_or_not_enqueued( bool $enqueue ) {
		$wp_scripts = wp_scripts();
		wp_default_scripts( $wp_scripts );

		$this->assertArrayHasKey( 'comment-reply', $wp_scripts->registered );
		$wp_scripts->registered['comment-reply']->ver = null;
		$this->assertArrayHasKey( 'fetchpriority', $wp_scripts->registered['comment-reply']->extra );
		$this->assertSame( 'low', $wp_scripts->registered['comment-reply']->extra['fetchpriority'] );
		$this->assertArrayHasKey( 'strategy', $wp_scripts->registered['comment-reply']->extra );
		$this->assertSame( 'async', $wp_scripts->registered['comment-reply']->extra['strategy'] );
		if ( $enqueue ) {
			wp_enqueue_script( 'comment-reply' );
			$markup = get_echo( array( $wp_scripts, 'do_items' ), array( false ) );
		} else {
			$markup = get_echo( array( $wp_scripts, 'do_items' ), array( array( 'comment-reply' ) ) );
		}

		$this->assertEqualHTML(
			sprintf(
				"<script type='text/javascript' src='%s' async='async' data-wp-strategy='async' fetchpriority='low'></script>\n",
				includes_url( 'js/comment-reply.min.js' )
			),
			$markup
		);
	}

	/**
	 * Data provider for test_default_scripts_comment_reply_not_enqueued.
	 *
	 * @return array[]
	 */
	public static function data_provider_enqueue_or_not_to_enqueue(): array {
		return array(
			'not_enqueued' => array(
				false,
			),
			'enqueued'     => array(
				true,
			),
		);
	}

	/**
	 * Tests that scripts registered as defer become blocking when their dependents chain are all blocking.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers WP_Scripts::filter_eligible_strategies
	 * @covers ::wp_enqueue_script
	 */
	public function test_loading_strategy_with_invalid_defer_registration() {
		// Main script is defer and all dependent are not defer. Then main script will have blocking(or no) strategy.
		wp_enqueue_script( 'main-script-d4', '/main-script-d4.js', array(), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependent-script-d4-1', '/dependent-script-d4-1.js', array( 'main-script-d4' ), null, array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'dependent-script-d4-2', '/dependent-script-d4-2.js', array( 'dependent-script-d4-1' ), null );
		wp_enqueue_script( 'dependent-script-d4-3', '/dependent-script-d4-3.js', array( 'dependent-script-d4-2' ), null, array( 'strategy' => 'defer' ) );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = str_replace( "'", '"', "<script type='text/javascript' src='/main-script-d4.js' data-wp-strategy='defer'></script>\n" );
		$this->assertStringContainsString( $expected, $output, 'Scripts registered as defer but that have all dependents with no strategy, should become blocking (no strategy).' );
	}

	/**
	 * Tests that scripts registered as default/blocking remain as such when they have no dependencies.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers WP_Scripts::get_eligible_loading_strategy
	 * @covers WP_Scripts::filter_eligible_strategies
	 * @covers ::wp_enqueue_script
	 */
	public function test_loading_strategy_with_valid_blocking_registration() {
		wp_enqueue_script( 'main-script-b1', '/main-script-b1.js', array(), null );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = "<script type='text/javascript' src='/main-script-b1.js'></script>\n";
		$expected = str_replace( "'", '"', $expected );
		$this->assertSame( $expected, $output, 'Scripts registered with a "blocking" strategy, and who have no dependencies, should have no loading strategy attributes printed.' );

		// strategy args not set.
		wp_enqueue_script( 'main-script-b2', '/main-script-b2.js', array(), null, array() );
		$output   = get_echo( 'wp_print_scripts' );
		$expected = "<script type='text/javascript' src='/main-script-b2.js'></script>\n";
		$expected = str_replace( "'", '"', $expected );
		$this->assertSame( $expected, $output, 'Scripts registered with no strategy assigned, and who have no dependencies, should have no loading strategy attributes printed.' );
	}

	/**
	 * Tests that `WP_Scripts::filter_eligible_strategies()` correctly reuses cached results.
	 *
	 * @ticket 64194
	 *
	 * @covers WP_Scripts::filter_eligible_strategies
	 */
	public function test_filter_eligible_strategies_uses_cached_result() {
		$wp_scripts = new WP_Scripts();
		$wp_scripts->add( 'd', 'https://example.com/d.js' );
		$wp_scripts->add_data( 'd', 'strategy', 'defer' );

		/*
		 * Simulate a cached result in `$stored_results` for D.
		 * If caching logic is functioning properly, this cached value
		 * should be returned immediately without recomputing.
		 */
		$stored_results = array( 'd' => array( 'async' ) );

		// Access the private method via reflection.
		$method = new ReflectionMethod( WP_Scripts::class, 'filter_eligible_strategies' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		// Invoke the method with `$stored_results` passed by reference.
		$result = $method->invokeArgs( $wp_scripts, array( 'd', null, array(), &$stored_results ) );

		$this->assertSame(
			array( 'async' ),
			$result,
			'Expected cached `$stored_results` value for D to be reused instead of recomputed.'
		);
	}

	/**
	 * Tests that scripts registered for the head do indeed end up there.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers ::wp_enqueue_script
	 * @covers ::wp_register_script
	 */
	public function test_scripts_targeting_head() {
		wp_register_script( 'header-old', '/header-old.js', array(), null, false );
		wp_register_script( 'header-new', '/header-new.js', array( 'header-old' ), null, array( 'in_footer' => false ) );
		wp_enqueue_script( 'enqueue-header-old', '/enqueue-header-old.js', array( 'header-new' ), null, false );
		wp_enqueue_script( 'enqueue-header-new', '/enqueue-header-new.js', array( 'enqueue-header-old' ), null, array( 'in_footer' => false ) );

		$actual_header = get_echo( 'wp_print_head_scripts' );
		$actual_footer = get_echo( 'wp_print_scripts' );

		$expected_header  = "<script type='text/javascript' src='/header-old.js'></script>\n";
		$expected_header .= "<script type='text/javascript' src='/header-new.js'></script>\n";
		$expected_header .= "<script type='text/javascript' src='/enqueue-header-old.js'></script>\n";
		$expected_header .= "<script type='text/javascript' src='/enqueue-header-new.js'></script>\n";

		$this->assertEqualHTML( $expected_header, $actual_header, '<body>', 'Scripts registered/enqueued using the older $in_footer parameter or the newer $args parameter should have the same outcome.' );
		$this->assertEmpty( $actual_footer, 'Expected footer to be empty since all scripts were for head.' );
	}

	/**
	 * Test that scripts registered for the footer do indeed end up there.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::do_item
	 * @covers ::wp_enqueue_script
	 * @covers ::wp_register_script
	 */
	public function test_scripts_targeting_footer() {
		wp_register_script( 'footer-old', '/footer-old.js', array(), null, true );
		wp_register_script( 'footer-new', '/footer-new.js', array( 'footer-old' ), null, array( 'in_footer' => true ) );
		wp_enqueue_script( 'enqueue-footer-old', '/enqueue-footer-old.js', array( 'footer-new' ), null, true );
		wp_enqueue_script( 'enqueue-footer-new', '/enqueue-footer-new.js', array( 'enqueue-footer-old' ), null, array( 'in_footer' => true ) );

		$actual_header = get_echo( 'wp_print_head_scripts' );
		$actual_footer = get_echo( 'wp_print_scripts' );

		$expected_footer  = "<script type='text/javascript' src='/footer-old.js'></script>\n";
		$expected_footer .= "<script type='text/javascript' src='/footer-new.js'></script>\n";
		$expected_footer .= "<script type='text/javascript' src='/enqueue-footer-old.js'></script>\n";
		$expected_footer .= "<script type='text/javascript' src='/enqueue-footer-new.js'></script>\n";

		$this->assertEmpty( $actual_header, 'Expected header to be empty since all scripts targeted footer.' );
		$this->assertEqualHTML( $expected_footer, $actual_footer, '<body>', 'Scripts registered/enqueued using the older $in_footer parameter or the newer $args parameter should have the same outcome.' );
	}

	/**
	 * Data provider for test_setting_in_footer_and_strategy.
	 *
	 * @return array[]
	 */
	public function get_data_for_test_setting_in_footer_and_strategy() {
		return array(
			// Passing in_footer and strategy via args array.
			'async_footer_in_args_array'    => array(
				'set_up'   => static function ( $handle ) {
					$args = array(
						'in_footer' => true,
						'strategy'  => 'async',
					);
					wp_enqueue_script( $handle, '/footer-async.js', array(), null, $args );
				},
				'group'    => 1,
				'strategy' => 'async',
			),

			// Passing in_footer=true but no strategy.
			'blocking_footer_in_args_array' => array(
				'set_up'   => static function ( $handle ) {
					wp_register_script( $handle, '/defaults.js', array(), null, array( 'in_footer' => true ) );
				},
				'group'    => 1,
				'strategy' => false,
			),

			// Passing async strategy in script args array.
			'async_in_args_array'           => array(
				'set_up'   => static function ( $handle ) {
					wp_register_script( $handle, '/defaults.js', array(), null, array( 'strategy' => 'async' ) );
				},
				'group'    => false,
				'strategy' => 'async',
			),

			// Passing empty array as 5th arg.
			'empty_args_array'              => array(
				'set_up'   => static function ( $handle ) {
					wp_register_script( $handle, '/defaults.js', array(), null, array() );
				},
				'group'    => false,
				'strategy' => false,
			),

			// Passing no value as 5th arg.
			'undefined_args_param'          => array(
				'set_up'   => static function ( $handle ) {
					wp_register_script( $handle, '/defaults.js', array(), null );
				},
				'group'    => false,
				'strategy' => false,
			),

			// Test backward compatibility, passing $in_footer=true as 5th arg.
			'passing_bool_as_args_param'    => array(
				'set_up'   => static function ( $handle ) {
					wp_enqueue_script( $handle, '/footer-async.js', array(), null, true );
				},
				'group'    => 1,
				'strategy' => false,
			),

			// Test backward compatibility, passing $in_footer=true as 5th arg and setting strategy via wp_script_add_data().
			'bool_as_args_and_add_data'     => array(
				'set_up'   => static function ( $handle ) {
					wp_register_script( $handle, '/footer-async.js', array(), null, true );
					wp_script_add_data( $handle, 'strategy', 'defer' );
				},
				'group'    => 1,
				'strategy' => 'defer',
			),
		);
	}

	/**
	 * Tests that scripts print in the correct group (head/footer) when using in_footer and assigning a strategy.
	 *
	 * @ticket 12009
	 *
	 * @covers ::wp_register_script
	 * @covers ::wp_enqueue_script
	 * @covers ::wp_script_add_data
	 *
	 * @dataProvider get_data_for_test_setting_in_footer_and_strategy
	 *
	 * @param callable     $set_up            Set up.
	 * @param int|false    $expected_group    Expected group.
	 * @param string|false $expected_strategy Expected strategy.
	 */
	public function test_setting_in_footer_and_strategy( $set_up, $expected_group, $expected_strategy ) {
		$handle = 'foo';
		$set_up( $handle );
		$this->assertSame( $expected_group, wp_scripts()->get_data( $handle, 'group' ) );
		$this->assertSame( $expected_strategy, wp_scripts()->get_data( $handle, 'strategy' ) );
	}

	/**
	 * Tests that scripts print with no strategy when an incorrect strategy is passed during wp_register_script.
	 *
	 * For an invalid strategy defined during script registration, default to a blocking strategy.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::add_data
	 * @covers ::wp_register_script
	 * @covers ::wp_enqueue_script
	 *
	 * @expectedIncorrectUsage WP_Scripts::add_data
	 */
	public function test_script_strategy_doing_it_wrong_via_register() {
		wp_register_script( 'invalid-strategy', '/defaults.js', array(), null, array( 'strategy' => 'random-strategy' ) );
		wp_enqueue_script( 'invalid-strategy' );

		$this->assertEqualHTML(
			"<script type='text/javascript' src='/defaults.js'></script>\n",
			get_echo( 'wp_print_scripts' )
		);
	}

	/**
	 * Tests that scripts print with no strategy when an incorrect strategy is passed via wp_script_add_data().
	 *
	 * For an invalid strategy defined during script registration, default to a blocking strategy.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::add_data
	 * @covers ::wp_script_add_data
	 * @covers ::wp_register_script
	 * @covers ::wp_enqueue_script
	 *
	 * @expectedIncorrectUsage WP_Scripts::add_data
	 */
	public function test_script_strategy_doing_it_wrong_via_add_data() {
		wp_register_script( 'invalid-strategy', '/defaults.js', array(), null );
		wp_script_add_data( 'invalid-strategy', 'strategy', 'random-strategy' );
		wp_enqueue_script( 'invalid-strategy' );

		$this->assertEqualHTML(
			"<script type='text/javascript' src='/defaults.js'></script>\n",
			get_echo( 'wp_print_scripts' )
		);
	}

	/**
	 * Tests that scripts print with no strategy when an incorrect strategy is passed during wp_enqueue_script.
	 *
	 * For an invalid strategy defined during script registration, default to a blocking strategy.
	 *
	 * @ticket 12009
	 *
	 * @covers WP_Scripts::add_data
	 * @covers ::wp_enqueue_script
	 *
	 * @expectedIncorrectUsage WP_Scripts::add_data
	 */
	public function test_script_strategy_doing_it_wrong_via_enqueue() {
		wp_enqueue_script( 'invalid-strategy', '/defaults.js', array(), null, array( 'strategy' => 'random-strategy' ) );

		$this->assertEqualHTML(
			"<script type='text/javascript' src='/defaults.js'></script>\n",
			get_echo( 'wp_print_scripts' )
		);
	}

	/**
	 * @ticket 42804
	 */
	public function test_wp_enqueue_script_with_html5_support_does_not_contain_type_attribute() {
		global $wp_version;

		$this->add_html5_script_theme_support();

		$GLOBALS['wp_scripts']                  = new WP_Scripts();
		$GLOBALS['wp_scripts']->default_version = calm_version_hash( calmpress_version() );

		wp_enqueue_script( 'empty-deps-no-version', 'example.com' );

		$ver      = calm_version_hash( calmpress_version() );
		$expected = "<script src='http://example.com?ver=$ver'></script>\n";

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Test the different protocol references in wp_enqueue_script
	 *
	 * @ticket 16560
	 *
	 * @global WP_Scripts $wp_scripts
	 */
	public function test_protocols() {
		// Init.
		global $wp_scripts, $wp_version;
		$base_url_backup      = $wp_scripts->base_url;
		$wp_scripts->base_url = 'http://example.com/wordpress';
		$expected             = '';
		$ver                  = calm_version_hash( calmpress_version());

		// Try with an HTTP reference.
		wp_enqueue_script( 'jquery-http', 'http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js' );
		$expected .= "<script type='text/javascript' src='http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js?ver=$ver'></script>\n";

		// Try with an HTTPS reference.
		wp_enqueue_script( 'jquery-https', 'https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js' );
		$expected .= "<script type='text/javascript' src='https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js?ver=$ver'></script>\n";

		// Try with an automatic protocol reference (//).
		wp_enqueue_script( 'jquery-doubleslash', '//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js' );
		$expected .= "<script type='text/javascript' src='//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js?ver=$ver'></script>\n";

		// Try with a local resource and an automatic protocol reference (//).
		$url = '//my_plugin/script.js';
		wp_enqueue_script( 'plugin-script', $url );
		$expected .= "<script type='text/javascript' src='$url?ver=$ver'></script>\n";

		// Try with a bad protocol.
		wp_enqueue_script( 'jquery-ftp', 'ftp://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js' );
		$expected .= "<script type='text/javascript' src='{$wp_scripts->base_url}ftp://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js?ver=$ver'></script>\n";

		// Go!
		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );

		// No scripts left to print.
		$this->assertSame( '', get_echo( 'wp_print_scripts' ) );

		// Cleanup.
		$wp_scripts->base_url = $base_url_backup;
	}

	/**
	 * Testing `wp_script_add_data` with the data key.
	 *
	 * @ticket 16024
	 */
	public function test_wp_script_add_data_with_data_key() {
		// Enqueue and add data.
		wp_enqueue_script( 'test-only-data', 'example.com', array(), null );
		wp_script_add_data( 'test-only-data', 'data', 'testing' );
		$expected  = "<script type='text/javascript'>\n/* <![CDATA[ */\ntesting\n//# sourceURL=test-only-data-js-extra\n/* ]]> */\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com'></script>\n";

		// Go!
		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );

		// No scripts left to print.
		$this->assertSame( '', get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Testing `wp_script_add_data` with an anvalid key.
	 *
	 * @ticket 16024
	 */
	public function test_wp_script_add_data_with_invalid_key() {
		// Enqueue and add an invalid key.
		wp_enqueue_script( 'test-invalid', 'example.com', array(), '1.0' );
		wp_script_add_data( 'test-invalid', 'invalid', 'testing' );
		$expected = "<script type='text/javascript' src='http://example.com?ver=1.0'></script>\n";

		// Go!
		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );

		// No scripts left to print.
		$this->assertEqualHTML( '', get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Testing 'wp_register_script' return boolean success/failure value.
	 *
	 * @ticket 31126
	 */
	public function test_wp_register_script() {
		$this->assertTrue( wp_register_script( 'duplicate-handler', 'http://example.com' ) );
		$this->assertFalse( wp_register_script( 'duplicate-handler', 'http://example.com' ) );
	}

	/**
	 * @ticket 35229
	 */
	public function test_wp_register_script_with_handle_without_source() {
		$expected  = "<script type='text/javascript' src='http://example.com?ver=1'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com?ver=2'></script>\n";

		wp_register_script( 'handle-one', 'http://example.com', array(), 1 );
		wp_register_script( 'handle-two', 'http://example.com', array(), 2 );
		wp_register_script( 'handle-three', false, array( 'handle-one', 'handle-two' ) );

		wp_enqueue_script( 'handle-three' );

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 35643
	 */
	public function test_wp_enqueue_script_footer_alias() {
		wp_register_script( 'foo', false, array( 'bar', 'baz' ), '1.0', true );
		wp_register_script( 'bar', home_url( 'bar.js' ), array(), '1.0', true );
		wp_register_script( 'baz', home_url( 'baz.js' ), array(), '1.0', true );

		wp_enqueue_script( 'foo' );

		$header = get_echo( 'wp_print_head_scripts' );
		$footer = get_echo( 'wp_print_footer_scripts' );

		$this->assertEmpty( $header );
		$this->assertStringContainsString( home_url( 'bar.js' ), $footer );
		$this->assertStringContainsString( home_url( 'baz.js' ), $footer );
	}

	/**
	 * Test mismatch of groups in dependencies outputs all scripts in right order.
	 *
	 * @ticket 35873
	 *
	 * @covers WP_Dependencies::add
	 * @covers WP_Dependencies::enqueue
	 * @covers WP_Dependencies::do_items
	 */
	public function test_group_mismatch_in_deps() {
		$scripts = new WP_Scripts();
		$scripts->add( 'one', 'one', array(), 'v1', 1 );
		$scripts->add( 'two', 'two', array( 'one' ) );
		$scripts->add( 'three', 'three', array( 'two' ), 'v1', 1 );

		$scripts->enqueue( array( 'three' ) );

		$this->expectOutputRegex( '/^(?:<script[^>]+><\/script>\\n){7}$/' );

		$scripts->do_items( false, 0 );
		$this->assertContains( 'one', $scripts->done );
		$this->assertContains( 'two', $scripts->done );
		$this->assertNotContains( 'three', $scripts->done );

		$scripts->do_items( false, 1 );
		$this->assertContains( 'one', $scripts->done );
		$this->assertContains( 'two', $scripts->done );
		$this->assertContains( 'three', $scripts->done );

		$scripts = new WP_Scripts();
		$scripts->add( 'one', 'one', array(), 'v1', 1 );
		$scripts->add( 'two', 'two', array( 'one' ), 'v1', 1 );
		$scripts->add( 'three', 'three', array( 'one' ) );
		$scripts->add( 'four', 'four', array( 'two', 'three' ), 'v1', 1 );

		$scripts->enqueue( array( 'four' ) );

		$scripts->do_items( false, 0 );
		$this->assertContains( 'one', $scripts->done );
		$this->assertNotContains( 'two', $scripts->done );
		$this->assertContains( 'three', $scripts->done );
		$this->assertNotContains( 'four', $scripts->done );

		$scripts->do_items( false, 1 );
		$this->assertContains( 'one', $scripts->done );
		$this->assertContains( 'two', $scripts->done );
		$this->assertContains( 'three', $scripts->done );
		$this->assertContains( 'four', $scripts->done );
	}

	/**
	 * @ticket 35873
	 */
	public function test_wp_register_script_with_dependencies_in_head_and_footer() {
		wp_register_script( 'parent', '/parent.js', array( 'child-head' ), '1.0', true );            // In footer.
		wp_register_script( 'child-head', '/child-head.js', array( 'child-footer' ), '1.0', false ); // In head.
		wp_register_script( 'child-footer', '/child-footer.js', array(), '1.0', true );              // In footer.

		wp_enqueue_script( 'parent' );

		$header = get_echo( 'wp_print_head_scripts' );
		$footer = get_echo( 'wp_print_footer_scripts' );

		$expected_header  = "<script type='text/javascript' src='/child-footer.js?ver=1.0'></script>\n";
		$expected_header .= "<script type='text/javascript' src='/child-head.js?ver=1.0'></script>\n";
		$expected_footer  = "<script type='text/javascript' src='/parent.js?ver=1.0'></script>\n";

		$this->assertEqualHTML( $expected_header, $header, '<body>', 'Expected same header markup.' );
		$this->assertEqualHTML( $expected_footer, $footer, '<body>', 'Expected same footer markup.' );
	}

	/**
	 * @ticket 35956
	 */
	public function test_wp_register_script_with_dependencies_in_head_and_footer_in_reversed_order() {
		wp_register_script( 'child-head', '/child-head.js', array(), '1.0', false );                      // In head.
		wp_register_script( 'child-footer', '/child-footer.js', array(), '1.0', true );                   // In footer.
		wp_register_script( 'parent', '/parent.js', array( 'child-head', 'child-footer' ), '1.0', true ); // In footer.

		wp_enqueue_script( 'parent' );

		$header = get_echo( 'wp_print_head_scripts' );
		$footer = get_echo( 'wp_print_footer_scripts' );

		$expected_header  = "<script type='text/javascript' src='/child-head.js?ver=1.0'></script>\n";
		$expected_footer  = "<script type='text/javascript' src='/child-footer.js?ver=1.0'></script>\n";
		$expected_footer .= "<script type='text/javascript' src='/parent.js?ver=1.0'></script>\n";

		$this->assertEqualHTML( $expected_header, $header, '<body>', 'Expected same header markup.' );
		$this->assertEqualHTML( $expected_footer, $footer, '<body>', 'Expected same footer markup.' );
	}

	/**
	 * @ticket 35956
	 */
	public function test_wp_register_script_with_dependencies_in_head_and_footer_in_reversed_order_and_two_parent_scripts() {
		wp_register_script( 'grandchild-head', '/grandchild-head.js', array(), '1.0', false );             // In head.
		wp_register_script( 'child-head', '/child-head.js', array(), '1.0', false );                       // In head.
		wp_register_script( 'child-footer', '/child-footer.js', array( 'grandchild-head' ), '1.0', true ); // In footer.
		wp_register_script( 'child2-head', '/child2-head.js', array(), '1.0', false );                     // In head.
		wp_register_script( 'child2-footer', '/child2-footer.js', array(), '1.0', true );                  // In footer.
		wp_register_script( 'parent-footer', '/parent-footer.js', array( 'child-head', 'child-footer', 'child2-head', 'child2-footer' ), '1.0', true ); // In footer.
		wp_register_script( 'parent-header', '/parent-header.js', array( 'child-head' ), '1.0', false );   // In head.

		wp_enqueue_script( 'parent-footer' );
		wp_enqueue_script( 'parent-header' );

		$header = get_echo( 'wp_print_head_scripts' );
		$footer = get_echo( 'wp_print_footer_scripts' );

		$expected_header  = "<script type='text/javascript' src='/child-head.js?ver=1.0'></script>\n";
		$expected_header .= "<script type='text/javascript' src='/grandchild-head.js?ver=1.0'></script>\n";
		$expected_header .= "<script type='text/javascript' src='/child2-head.js?ver=1.0'></script>\n";
		$expected_header .= "<script type='text/javascript' src='/parent-header.js?ver=1.0'></script>\n";

		$expected_footer  = "<script type='text/javascript' src='/child-footer.js?ver=1.0'></script>\n";
		$expected_footer .= "<script type='text/javascript' src='/child2-footer.js?ver=1.0'></script>\n";
		$expected_footer .= "<script type='text/javascript' src='/parent-footer.js?ver=1.0'></script>\n";

		$this->assertEqualHTML( $expected_header, $header, '<body>', 'Expected same header markup.' );
		$this->assertEqualHTML( $expected_footer, $footer, '<body>', 'Expected same footer markup.' );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_returns_bool() {
		$this->assertFalse( wp_add_inline_script( 'test-example', 'console.log("before");', 'before' ) );
		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		$this->assertTrue( wp_add_inline_script( 'test-example', 'console.log("before");', 'before' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_unknown_handle() {
		$this->assertFalse( wp_add_inline_script( 'test-invalid', 'console.log("before");', 'before' ) );
		$this->assertSame( '', get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_before() {
		wp_enqueue_script( 'test-example', 'example.com', array(), '1.0' );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );

		$expected  = <<<HTML
<script type='text/javascript'>
/* <![CDATA[ */
console.log("before");
//# sourceURL=test-example-js-before
/* ]]> */
</script>

HTML;
		$expected .= "<script type='text/javascript' src='http://example.com?ver=1.0'></script>\n";

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_after() {
		wp_enqueue_script( 'test-example', 'example.com', array(), '1.0' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		$expected  = "<script type='text/javascript' src='http://example.com?ver=1.0'></script>\n";
		$expected .= <<<HTML
<script type='text/javascript'>
/* <![CDATA[ */
console.log("after");
//# sourceURL=test-example-js-after
/* ]]> */
</script>

HTML;

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_before_and_after() {
		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		$expected  = "<script type='text/javascript'>\n/* <![CDATA[ */\nconsole.log(\"before\");\n//# sourceURL=test-example-js-before\n/* ]]> */\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com'></script>\n";
		$expected .= "<script type='text/javascript'>\n/* <![CDATA[ */\nconsole.log(\"after\");\n//# sourceURL=test-example-js-after\n/* ]]> */\n</script>\n";

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 44551
	 */
	public function test_wp_add_inline_script_before_for_handle_without_source() {
		wp_register_script( 'test-example', '' );
		wp_enqueue_script( 'test-example' );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );

		$expected = "<script type='text/javascript'>\n/* <![CDATA[ */\nconsole.log(\"before\");\n//# sourceURL=test-example-js-before\n/* ]]> */\n</script>\n";

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 44551
	 */
	public function test_wp_add_inline_script_after_for_handle_without_source() {
		wp_register_script( 'test-example', '' );
		wp_enqueue_script( 'test-example' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		$expected = "<script type='text/javascript'>\n/* <![CDATA[ */\nconsole.log(\"after\");\n//# sourceURL=test-example-js-after\n/* ]]> */\n</script>\n";

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 44551
	 */
	public function test_wp_add_inline_script_before_and_after_for_handle_without_source() {
		wp_register_script( 'test-example', '' );
		wp_enqueue_script( 'test-example' );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		$expected  = "<script type='text/javascript'>\n/* <![CDATA[ */\nconsole.log(\"before\");\n//# sourceURL=test-example-js-before\n/* ]]> */\n</script>\n";
		$expected .= "<script type='text/javascript'>\n/* <![CDATA[ */\nconsole.log(\"after\");\n//# sourceURL=test-example-js-after\n/* ]]> */\n</script>\n";

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_multiple() {
		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		$expected  = "<script type='text/javascript'>\n/* <![CDATA[ */\nconsole.log(\"before\");\nconsole.log(\"before\");\n//# sourceURL=test-example-js-before\n/* ]]> */\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com'></script>\n";
		$expected .= "<script type='text/javascript'>\n/* <![CDATA[ */\nconsole.log(\"after\");\nconsole.log(\"after\");\n//# sourceURL=test-example-js-after\n/* ]]> */\n</script>\n";

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_localized_data_is_added_first() {
		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		wp_localize_script( 'test-example', 'testExample', array( 'foo' => 'bar' ) );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		$expected  = "<script type='text/javascript'>\n/* <![CDATA[ */\nvar testExample = {\"foo\":\"bar\"};\n//# sourceURL=test-example-js-extra\n/* ]]> */\n</script>\n";
		$expected .= "<script type='text/javascript'>\n/* <![CDATA[ */\nconsole.log(\"before\");\n//# sourceURL=test-example-js-before\n/* ]]> */\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com'></script>\n";
		$expected .= "<script type='text/javascript'>\n/* <![CDATA[ */\nconsole.log(\"after\");\n//# sourceURL=test-example-js-after\n/* ]]> */\n</script>\n";

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 36392
	 */
	public function test_wp_add_inline_script_customize_dependency() {
		global $wp_scripts;

		wp_default_scripts( $wp_scripts );
		wp_default_packages( $wp_scripts );

		$wp_scripts->base_url  = '';
		$wp_scripts->do_concat = true;

		$expected_tail  = "<script type='text/javascript' src='/customize-dependency.js'></script>\n";
		$expected_tail .= "<script type='text/javascript'>\n";
		$expected_tail .= "/* <![CDATA[ */\n";
		$expected_tail .= "tryCustomizeDependency()\n";
		$expected_tail .= "//# sourceURL=customize-dependency-js-after\n";
		$expected_tail .= "/* ]]> */\n";
		$expected_tail .= "</script>\n";

		$handle = 'customize-dependency';
		wp_enqueue_script( $handle, '/customize-dependency.js', array( 'customize-controls' ), null );
		wp_add_inline_script( $handle, 'tryCustomizeDependency()' );

		// Effectively ignore the output until retrieving it later via `getActualOutput()`.
		$this->expectOutputRegex( '`.`' );

		wp_print_scripts();
		_print_scripts();
		$print_scripts = $this->getActualOutput();

		$tail = substr( $print_scripts, strrpos( $print_scripts, '<script type="text/javascript" src="/customize-dependency.js">' ) );

		$this->assertEqualHTML( $expected_tail, $tail );
	}

	/**
	 * Data provider to test get_inline_script_data and get_inline_script_tag.
	 *
	 * @return array[]
	 */
	public function data_provider_to_test_get_inline_script() {
		return array(
			'before-blocking' => array(
				'position'       => 'before',
				'inline_scripts' => array(
					'/*before foo 1*/',
				),
				'delayed'        => false,
				'expected_data'  => "/*before foo 1*/\n//# sourceURL=foo-js-before",
				'expected_tag'   => "<script type='text/javascript'>\n/* <![CDATA[ */\n/*before foo 1*/\n//# sourceURL=foo-js-before\n/* ]]> */\n</script>\n",
			),
			'after-blocking'  => array(
				'position'       => 'after',
				'inline_scripts' => array(
					'/*after foo 1*/',
					'/*after foo 2*/',
				),
				'delayed'        => false,
				'expected_data'  => "/*after foo 1*/\n/*after foo 2*/\n//# sourceURL=foo-js-after",
				'expected_tag'   => "<script type='text/javascript'>\n/* <![CDATA[ */\n/*after foo 1*/\n/*after foo 2*/\n//# sourceURL=foo-js-after\n/* ]]> */\n</script>\n",
			),
			'before-delayed'  => array(
				'position'       => 'before',
				'inline_scripts' => array(
					'/*before foo 1*/',
				),
				'delayed'        => true,
				'expected_data'  => "/*before foo 1*/\n//# sourceURL=foo-js-before",
				'expected_tag'   => "<script type='text/javascript'>\n/* <![CDATA[ */\n/*before foo 1*/\n//# sourceURL=foo-js-before\n/* ]]> */\n</script>\n",
			),
			'after-delayed'   => array(
				'position'       => 'after',
				'inline_scripts' => array(
					'/*after foo 1*/',
					'/*after foo 2*/',
				),
				'delayed'        => true,
				'expected_data'  => "/*after foo 1*/\n/*after foo 2*/\n//# sourceURL=foo-js-after",
				'expected_tag'   => "<script type='text/javascript'>\n/* <![CDATA[ */\n/*after foo 1*/\n/*after foo 2*/\n//# sourceURL=foo-js-after\n/* ]]> */\n</script>\n",
			),
		);
	}

	/**
	 * Test getting inline scripts.
	 *
	 * @covers WP_Scripts::get_inline_script_data
	 * @covers WP_Scripts::get_inline_script_tag
	 *
	 * @dataProvider data_provider_to_test_get_inline_script
	 *
	 * @param string   $position       Position.
	 * @param string[] $inline_scripts Inline scripts.
	 * @param bool     $delayed        Delayed.
	 * @param string   $expected_data  Expected data.
	 * @param string   $expected_tag   Expected tag.
	 */
	public function test_get_inline_script( $position, $inline_scripts, $delayed, $expected_data, $expected_tag ) {
		global $wp_scripts;

		$deps = array();
		if ( $delayed ) {
			$wp_scripts->add( 'dep', 'https://example.com/dependency.js', array(), false ); // TODO: Cannot pass strategy to $args e.g. array( 'strategy' => 'defer' )
			$wp_scripts->add_data( 'dep', 'strategy', 'defer' );
			$deps[] = 'dep';
		}

		$handle = 'foo';
		$wp_scripts->add( $handle, 'https://example.com/foo.js', $deps );
		if ( $delayed ) {
			$wp_scripts->add_data( $handle, 'strategy', 'defer' );
		}

		$this->assertSame( '', $wp_scripts->get_inline_script_data( $handle, $position ) );
		$this->assertSame( '', $wp_scripts->get_inline_script_tag( $handle, $position ) );

		foreach ( $inline_scripts as $inline_script ) {
			$wp_scripts->add_inline_script( $handle, $inline_script, $position );
		}

		$this->assertSame( $expected_data, $wp_scripts->get_inline_script_data( $handle, $position ) );
		$this->assertEqualHTML(
			$expected_tag,
			$wp_scripts->get_inline_script_tag( $handle, $position )
		);
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations() {
		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), '1.0' );
		wp_enqueue_script( 'test-example', '/wp-includes/js/script.js', array(), '1.0' );
		wp_set_script_translations( 'test-example', 'default', DIR_TESTDATA . '/languages' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js?ver=1.0'></script>\n";
		$expected .= str_replace(
			array(
				'__DOMAIN__',
				'__HANDLE__',
				'__JSON_TRANSLATIONS__',
			),
			array(
				'default',
				'test-example',
				file_get_contents( DIR_TESTDATA . '/languages/en_US-813e104eb47e13dd4cc5af844c618754.json' ),
			),
			$this->wp_scripts_print_translations_output
		);
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script.js?ver=1.0'></script>\n";

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 63944
	 */
	public function test_wp_set_script_translations_uses_registered_domainpath_for_plugin() {
		global $wp_textdomain_registry;

		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), null );
		wp_enqueue_script( 'domain-path-plugin', '/wp-content/plugins/my-plugin/js/script.js', array(), null );

		// Simulate a plugin declaring DomainPath: /languages by registering a custom path.
		$wp_textdomain_registry->set_custom_path( 'internationalized-plugin', DIR_TESTDATA . '/languages/plugins' );
		wp_set_script_translations( 'domain-path-plugin', 'internationalized-plugin' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js'></script>\n";
		$expected .= str_replace(
			array(
				'__DOMAIN__',
				'__HANDLE__',
				'__JSON_TRANSLATIONS__',
			),
			array(
				'internationalized-plugin',
				'domain-path-plugin',
				file_get_contents( DIR_TESTDATA . '/languages/plugins/internationalized-plugin-en_US-2f86cb96a0233e7cb3b6f03ad573be0b.json' ),
			),
			$this->wp_scripts_print_translations_output
		);
		$expected .= "<script type='text/javascript' src='/wp-content/plugins/my-plugin/js/script.js'></script>\n";

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 63944
	 *
	 * Ensure human-readable script translation filenames are found when a
	 * textdomain has a custom DomainPath registered and no explicit $path is passed.
	 */
	public function test_wp_set_script_translations_prefers_human_readable_filename_in_registered_domainpath() {
		global $wp_textdomain_registry;

		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), null );
		wp_enqueue_script( 'script-handle', '/wp-admin/js/script.js', array(), null );

		// Register the admin textdomain path and use the admin translations file for this script-handle test.
		$wp_textdomain_registry->set_custom_path( 'admin', DIR_TESTDATA . '/languages' );
		wp_set_script_translations( 'script-handle', 'admin' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js'></script>\n";
		$expected .= str_replace(
			array(
				'__DOMAIN__',
				'__HANDLE__',
				'__JSON_TRANSLATIONS__',
			),
			array(
				'admin',
				'script-handle',
				file_get_contents( DIR_TESTDATA . '/languages/admin-en_US-script-handle.json' ),
			),
			$this->wp_scripts_print_translations_output
		);
		$expected .= "<script type='text/javascript' src='/wp-admin/js/script.js'></script>\n";

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations_for_plugin() {
		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), '1.0' );
		wp_enqueue_script( 'plugin-example', '/wp-content/plugins/my-plugin/js/script.js', array(), '1.0' );
		wp_set_script_translations( 'plugin-example', 'internationalized-plugin', DIR_TESTDATA . '/languages/plugins' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js?ver=1.0'></script>\n";
		$expected .= str_replace(
			array(
				'__DOMAIN__',
				'__HANDLE__',
				'__JSON_TRANSLATIONS__',
			),
			array(
				'internationalized-plugin',
				'plugin-example',
				file_get_contents( DIR_TESTDATA . '/languages/plugins/internationalized-plugin-en_US-2f86cb96a0233e7cb3b6f03ad573be0b.json' ),
			),
			$this->wp_scripts_print_translations_output
		);
		$expected .= "<script type='text/javascript' src='/wp-content/plugins/my-plugin/js/script.js?ver=1.0'></script>\n";

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations_for_theme() {
		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), '1.0' );
		wp_enqueue_script( 'theme-example', '/wp-content/themes/my-theme/js/script.js', array(), '1.0' );
		wp_set_script_translations( 'theme-example', 'internationalized-theme', DIR_TESTDATA . '/languages/themes' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js?ver=1.0'></script>\n";
		$expected .= str_replace(
			array(
				'__DOMAIN__',
				'__HANDLE__',
				'__JSON_TRANSLATIONS__',
			),
			array(
				'internationalized-theme',
				'theme-example',
				file_get_contents( DIR_TESTDATA . '/languages/themes/internationalized-theme-en_US-2f86cb96a0233e7cb3b6f03ad573be0b.json' ),
			),
			$this->wp_scripts_print_translations_output
		);
		$expected .= "<script type='text/javascript' src='/wp-content/themes/my-theme/js/script.js?ver=1.0'></script>\n";

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations_with_handle_file() {
		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), '1.0' );
		wp_enqueue_script( 'script-handle', '/wp-admin/js/script.js', array(), '1.0' );
		wp_set_script_translations( 'script-handle', 'admin', DIR_TESTDATA . '/languages/' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js?ver=1.0'></script>\n";
		$expected .= str_replace(
			array(
				'__DOMAIN__',
				'__HANDLE__',
				'__JSON_TRANSLATIONS__',
			),
			array(
				'admin',
				'script-handle',
				file_get_contents( DIR_TESTDATA . '/languages/admin-en_US-script-handle.json' ),
			),
			$this->wp_scripts_print_translations_output
		);
		$expected .= "<script type='text/javascript' src='/wp-admin/js/script.js?ver=1.0'></script>\n";

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations_i18n_dependency() {
		global $wp_scripts;

		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), '1.0' );
		wp_enqueue_script( 'test-example', '/wp-includes/js/script.js', array(), '1.0' );
		wp_set_script_translations( 'test-example', 'default', DIR_TESTDATA . '/languages/' );

		$script = $wp_scripts->registered['test-example'];

		$this->assertContains( 'wp-i18n', $script->deps );
	}

	/**
	 * @ticket 45103
	 * @ticket 55250
	 */
	public function test_wp_set_script_translations_when_translation_file_does_not_exist() {
		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), null );
		wp_enqueue_script( 'test-example', '/wp-admin/js/script.js', array(), null );
		wp_set_script_translations( 'test-example', 'admin', DIR_TESTDATA . '/languages/' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js'></script>\n";
		$expected .= "<script type='text/javascript' src='/wp-admin/js/script.js'></script>\n";

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations_after_register() {
		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), '1.0' );
		wp_register_script( 'test-example', '/wp-includes/js/script.js', array(), '1.0' );
		wp_set_script_translations( 'test-example', 'default', DIR_TESTDATA . '/languages' );

		wp_enqueue_script( 'test-example' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js?ver=1.0'></script>\n";
		$expected .= str_replace(
			array(
				'__DOMAIN__',
				'__HANDLE__',
				'__JSON_TRANSLATIONS__',
			),
			array(
				'default',
				'test-example',
				file_get_contents( DIR_TESTDATA . '/languages/en_US-813e104eb47e13dd4cc5af844c618754.json' ),
			),
			$this->wp_scripts_print_translations_output
		);
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script.js?ver=1.0'></script>\n";

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations_dependency() {
		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), '1.0' );
		wp_register_script( 'test-dependency', '/wp-includes/js/script.js', array(), '1.0' );
		wp_set_script_translations( 'test-dependency', 'default', DIR_TESTDATA . '/languages' );

		wp_enqueue_script( 'test-example', '/wp-includes/js/script2.js', array( 'test-dependency' ), '1.0' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js?ver=1.0'></script>\n";
		$expected .= str_replace(
			array(
				'__DOMAIN__',
				'__HANDLE__',
				'__JSON_TRANSLATIONS__',
			),
			array(
				'default',
				'test-dependency',
				file_get_contents( DIR_TESTDATA . '/languages/en_US-813e104eb47e13dd4cc5af844c618754.json' ),
			),
			$this->wp_scripts_print_translations_output
		);
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script.js?ver=1.0'></script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script2.js?ver=1.0'></script>\n";

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Testing `wp_enqueue_code_editor` with file path.
	 *
	 * @ticket 41871
	 *
	 * @covers ::wp_enqueue_code_editor
	 */
	public function test_wp_enqueue_code_editor_when_php_file_will_be_passed() {
		$real_file              = WP_PLUGIN_DIR . '/hello.php';
		$wp_enqueue_code_editor = wp_enqueue_code_editor( array( 'file' => $real_file ) );
		$this->assertNonEmptyMultidimensionalArray( $wp_enqueue_code_editor );

		$this->assertSameSets( array( 'codemirror', 'csslint', 'jshint', 'htmlhint' ), array_keys( $wp_enqueue_code_editor ) );
		$this->assertSameSets(
			array(
				'autoCloseBrackets',
				'autoCloseTags',
				'continueComments',
				'direction',
				'extraKeys',
				'indentUnit',
				'indentWithTabs',
				'inputStyle',
				'lineNumbers',
				'lineWrapping',
				'matchBrackets',
				'matchTags',
				'mode',
				'styleActiveLine',
				'gutters',
			),
			array_keys( $wp_enqueue_code_editor['codemirror'] )
		);
		$this->assertEmpty( $wp_enqueue_code_editor['codemirror']['gutters'] );

		$this->assertSameSets(
			array(
				'errors',
				'box-model',
				'display-property-grouping',
				'duplicate-properties',
				'known-properties',
				'outline-none',
			),
			array_keys( $wp_enqueue_code_editor['csslint'] )
		);

		$this->assertSameSets(
			array(
				'boss',
				'curly',
				'eqeqeq',
				'eqnull',
				'es3',
				'expr',
				'immed',
				'noarg',
				'nonbsp',
				'onevar',
				'quotmark',
				'trailing',
				'undef',
				'unused',
				'browser',
				'globals',
			),
			array_keys( $wp_enqueue_code_editor['jshint'] )
		);

		$this->assertSameSets(
			array(
				'tagname-lowercase',
				'attr-lowercase',
				'attr-value-double-quotes',
				'doctype-first',
				'tag-pair',
				'spec-char-escape',
				'id-unique',
				'src-not-empty',
				'attr-no-duplication',
				'alt-require',
				'space-tab-mixed-disabled',
				'attr-unsafe-chars',
			),
			array_keys( $wp_enqueue_code_editor['htmlhint'] )
		);
	}

	/**
	 * Testing `wp_enqueue_code_editor` with `compact`.
	 *
	 * @ticket 41871
	 *
	 * @covers ::wp_enqueue_code_editor
	 */
	public function test_wp_enqueue_code_editor_when_generated_array_by_compact_will_be_passed() {
		$file                   = '';
		$wp_enqueue_code_editor = wp_enqueue_code_editor( compact( 'file' ) );
		$this->assertNonEmptyMultidimensionalArray( $wp_enqueue_code_editor );

		$this->assertSameSets( array( 'codemirror', 'csslint', 'jshint', 'htmlhint' ), array_keys( $wp_enqueue_code_editor ) );
		$this->assertSameSets(
			array(
				'continueComments',
				'direction',
				'extraKeys',
				'indentUnit',
				'indentWithTabs',
				'inputStyle',
				'lineNumbers',
				'lineWrapping',
				'mode',
				'styleActiveLine',
				'gutters',
			),
			array_keys( $wp_enqueue_code_editor['codemirror'] )
		);
		$this->assertEmpty( $wp_enqueue_code_editor['codemirror']['gutters'] );

		$this->assertSameSets(
			array(
				'errors',
				'box-model',
				'display-property-grouping',
				'duplicate-properties',
				'known-properties',
				'outline-none',
			),
			array_keys( $wp_enqueue_code_editor['csslint'] )
		);

		$this->assertSameSets(
			array(
				'boss',
				'curly',
				'eqeqeq',
				'eqnull',
				'es3',
				'expr',
				'immed',
				'noarg',
				'nonbsp',
				'onevar',
				'quotmark',
				'trailing',
				'undef',
				'unused',
				'browser',
				'globals',
			),
			array_keys( $wp_enqueue_code_editor['jshint'] )
		);

		$this->assertSameSets(
			array(
				'tagname-lowercase',
				'attr-lowercase',
				'attr-value-double-quotes',
				'doctype-first',
				'tag-pair',
				'spec-char-escape',
				'id-unique',
				'src-not-empty',
				'attr-no-duplication',
				'alt-require',
				'space-tab-mixed-disabled',
				'attr-unsafe-chars',
			),
			array_keys( $wp_enqueue_code_editor['htmlhint'] )
		);
	}

	/**
	 * Testing `wp_enqueue_code_editor` with `array_merge`.
	 *
	 * @ticket 41871
	 *
	 * @covers ::wp_enqueue_code_editor
	 */
	public function test_wp_enqueue_code_editor_when_generated_array_by_array_merge_will_be_passed() {
		$wp_enqueue_code_editor = wp_enqueue_code_editor(
			array_merge(
				array(
					'type'       => 'text/css',
					'codemirror' => array(
						'indentUnit' => 2,
						'tabSize'    => 2,
					),
				),
				array()
			)
		);

		$this->assertNonEmptyMultidimensionalArray( $wp_enqueue_code_editor );

		$this->assertSameSets( array( 'codemirror', 'csslint', 'jshint', 'htmlhint' ), array_keys( $wp_enqueue_code_editor ) );
		$this->assertSameSets(
			array(
				'autoCloseBrackets',
				'continueComments',
				'direction',
				'extraKeys',
				'gutters',
				'indentUnit',
				'indentWithTabs',
				'inputStyle',
				'lineNumbers',
				'lineWrapping',
				'lint',
				'matchBrackets',
				'mode',
				'styleActiveLine',
				'tabSize',
			),
			array_keys( $wp_enqueue_code_editor['codemirror'] )
		);

		$this->assertSameSets(
			array(
				'errors',
				'box-model',
				'display-property-grouping',
				'duplicate-properties',
				'known-properties',
				'outline-none',
			),
			array_keys( $wp_enqueue_code_editor['csslint'] )
		);

		$this->assertSameSets(
			array(
				'boss',
				'curly',
				'eqeqeq',
				'eqnull',
				'es3',
				'expr',
				'immed',
				'noarg',
				'nonbsp',
				'onevar',
				'quotmark',
				'trailing',
				'undef',
				'unused',
				'browser',
				'globals',
			),
			array_keys( $wp_enqueue_code_editor['jshint'] )
		);

		$this->assertSameSets(
			array(
				'tagname-lowercase',
				'attr-lowercase',
				'attr-value-double-quotes',
				'doctype-first',
				'tag-pair',
				'spec-char-escape',
				'id-unique',
				'src-not-empty',
				'attr-no-duplication',
				'alt-require',
				'space-tab-mixed-disabled',
				'attr-unsafe-chars',
			),
			array_keys( $wp_enqueue_code_editor['htmlhint'] )
		);
	}

	/**
	 * Testing `wp_enqueue_code_editor` with `array`.
	 *
	 * @ticket 41871
	 *
	 * @covers ::wp_enqueue_code_editor
	 */
	public function test_wp_enqueue_code_editor_when_simple_array_will_be_passed() {
		$wp_enqueue_code_editor = wp_enqueue_code_editor(
			array(
				'type'       => 'text/css',
				'codemirror' => array(
					'indentUnit' => 2,
					'tabSize'    => 2,
				),
			)
		);

		$this->assertNonEmptyMultidimensionalArray( $wp_enqueue_code_editor );

		$this->assertSameSets( array( 'codemirror', 'csslint', 'jshint', 'htmlhint' ), array_keys( $wp_enqueue_code_editor ) );
		$this->assertSameSets(
			array(
				'autoCloseBrackets',
				'continueComments',
				'direction',
				'extraKeys',
				'gutters',
				'indentUnit',
				'indentWithTabs',
				'inputStyle',
				'lineNumbers',
				'lineWrapping',
				'lint',
				'matchBrackets',
				'mode',
				'styleActiveLine',
				'tabSize',
			),
			array_keys( $wp_enqueue_code_editor['codemirror'] )
		);

		$this->assertSameSets(
			array(
				'errors',
				'box-model',
				'display-property-grouping',
				'duplicate-properties',
				'known-properties',
				'outline-none',
			),
			array_keys( $wp_enqueue_code_editor['csslint'] )
		);

		$this->assertSameSets(
			array(
				'boss',
				'curly',
				'eqeqeq',
				'eqnull',
				'es3',
				'expr',
				'immed',
				'noarg',
				'nonbsp',
				'onevar',
				'quotmark',
				'trailing',
				'undef',
				'unused',
				'browser',
				'globals',
			),
			array_keys( $wp_enqueue_code_editor['jshint'] )
		);

		$this->assertSameSets(
			array(
				'tagname-lowercase',
				'attr-lowercase',
				'attr-value-double-quotes',
				'doctype-first',
				'tag-pair',
				'spec-char-escape',
				'id-unique',
				'src-not-empty',
				'attr-no-duplication',
				'alt-require',
				'space-tab-mixed-disabled',
				'attr-unsafe-chars',
			),
			array_keys( $wp_enqueue_code_editor['htmlhint'] )
		);
	}

	/**
	 * @ticket 52534
	 *
	 * @covers ::wp_localize_script
	 *
	 * @dataProvider data_wp_localize_script_data_formats
	 *
	 * @param mixed  $l10n_data Localization data passed to wp_localize_script().
	 * @param string $expected  Expected transformation of localization data.
	 */
	public function test_wp_localize_script_data_formats( $l10n_data, $expected ) {
		if ( ! is_array( $l10n_data ) ) {
			$this->setExpectedIncorrectUsage( 'WP_Scripts::localize' );
		}

		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		wp_localize_script( 'test-example', 'testExample', $l10n_data );

		$expected  = "<script type='text/javascript'>\n/* <![CDATA[ */\nvar testExample = {$expected};\n//# sourceURL=test-example-js-extra\n/* ]]> */\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com'></script>\n";

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Data provider for test_wp_localize_script_data_formats().
	 *
	 * @return array[] {
	 *     Array of arguments for test.
	 *
	 *     @type mixed  $l10n_data Localization data passed to wp_localize_script().
	 *     @type string $expected  Expected transformation of localization data.
	 * }
	 */
	public function data_wp_localize_script_data_formats() {
		return array(
			// Officially supported formats.
			array( array( 'array value, no key' ), '["array value, no key"]' ),
			array( array( 'foo' => 'bar' ), '{"foo":"bar"}' ),
			array( array( 'foo' => array( 'bar' => 'foobar' ) ), '{"foo":{"bar":"foobar"}}' ),
			array( array( 'foo' => 6.6 ), '{"foo":"6.6"}' ),
			array( array( 'foo' => 6 ), '{"foo":"6"}' ),
			array( array(), '[]' ),

			// Unofficially supported format.
			array( 'string', '"string"' ),

			// Unsupported formats.
			array( 1.5, '1.5' ),
			array( 1, '1' ),
			array( false, '[""]' ),
			array( null, 'null' ),
		);
	}

	/**
	 * Ensure tinymce scripts aren't loading async.
	 *
	 * @ticket 58648
	 */
	public function test_printing_tinymce_scripts() {
		global $wp_scripts;

		wp_register_tinymce_scripts( $wp_scripts, true );

		$actual = get_echo( 'wp_print_scripts', array( array( 'wp-tinymce' ) ) );

		$this->assertStringNotContainsString( 'async', $actual, 'TinyMCE should not have an async attribute.' );
		$this->assertStringNotContainsString( 'defer', $actual, 'TinyMCE should not have a defer attribute.' );
	}

	/**
	 * Make sure scripts with a loading strategy that are printed
	 * without being enqueued are handled properly.
	 *
	 * @ticket 58648
	 *
	 * @dataProvider data_provider_delayed_strategies
	 */
	public function test_printing_non_enqueued_scripts( $strategy ) {
		wp_register_script( 'test-script', 'test-script.js', array(), false, array( 'strategy' => $strategy ) );

		$actual = get_echo( 'wp_print_scripts', array( array( 'test-script' ) ) );

		$this->assertStringContainsString( $strategy, $actual );
	}

	/**
	 * Adds html5 script theme support.
	 */
	protected function add_html5_script_theme_support() {
		add_theme_support( 'html5', array( 'script' ) );
	}

	/**
	 * Test that a script is moved to the footer if it is made non-deferrable, was in the header and
	 * all scripts that depend on it are in the footer.
	 *
	 * @ticket 58599
	 *
	 * @dataProvider data_provider_script_move_to_footer
	 *
	 * @param callable $set_up             Test setup.
	 * @param string   $expected_header    Expected output for header.
	 * @param string   $expected_footer    Expected output for footer.
	 * @param string[] $expected_in_footer Handles expected to be in the footer.
	 * @param array    $expected_groups    Expected groups.
	 */
	public function test_wp_scripts_move_to_footer( $set_up, $expected_header, $expected_footer, $expected_in_footer, $expected_groups ) {
		$set_up();

		// Get the header output.
		ob_start();
		wp_scripts()->do_head_items();
		$header = ob_get_clean();

		// Print a script in the body just to make sure it doesn't cause problems.
		ob_start();
		wp_print_scripts( array( 'jquery' ) );
		ob_end_clean();

		// Get the footer output.
		ob_start();
		wp_scripts()->do_footer_items();
		$footer = ob_get_clean();

		$this->assertEqualHTML( $expected_header, $header, '<body>', 'Expected header script markup to match.' );
		$this->assertEqualHTML( $expected_footer, $footer, '<body>', 'Expected footer script markup to match.' );
		$this->assertEqualSets( $expected_in_footer, wp_scripts()->in_footer, 'Expected to have the same handles for in_footer.' );
		$this->assertEquals( $expected_groups, wp_scripts()->groups, 'Expected groups to match.' );
	}

	/**
	 * Test that get_script_polyfill() returns the correct polyfill.
	 *
	 * @ticket 60348
	 *
	 * @covers ::wp_get_script_polyfill
	 *
	 * @global WP_Scripts $wp_scripts WP_Scripts instance.
	 */
	public function test_wp_get_script_polyfill() {
		global $wp_scripts;
		$script_name = 'tmp-polyfill-foo';
		$test_script = 'HTMLScriptElement.supports && HTMLScriptElement.supports("foo")';
		$script_url  = 'https://example.com/polyfill-foo.js';
		wp_register_script( $script_name, $script_url );

		$polyfill = wp_get_script_polyfill(
			$wp_scripts,
			array(
				$test_script => $script_name,
			)
		);

		wp_deregister_script( $script_name );

		$expected = '( ' . $test_script . ' ) || document.write( \'<script src="' . $script_url . '"></scr\' + \'ipt>\' );';

		$this->assertSame( $expected, $polyfill );
	}

	/**
	 * Data provider for test_wp_scripts_move_to_footer.
	 *
	 * @return array[]
	 */
	public function data_provider_script_move_to_footer() {
		return array(
			'footer-blocking-dependent-of-defer-head-script' => array(
				'set_up'             => static function () {
					wp_enqueue_script( 'script-a', 'https://example.com/script-a.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script( 'script-b', 'https://example.com/script-b.js', array( 'script-a' ), null, array( 'in_footer' => true ) );
				},
				'expected_header'    => '',
				'expected_footer'    =>
					"<script type='text/javascript' src='https://example.com/script-a.js' data-wp-strategy='defer'></script>\n" .
					"<script type='text/javascript' src='https://example.com/script-b.js'></script>\n",
				'expected_in_footer' => array(
					'script-a',
					'script-b',
				),
				'expected_groups'    => array(
					'script-a' => 0,
					'script-b' => 1,
					'jquery'   => 0,
				),
			),

			'footer-blocking-dependent-of-async-head-script' => array(
				'set_up'             => static function () {
					wp_enqueue_script( 'script-a', 'https://example.com/script-a.js', array(), null, array( 'strategy' => 'async' ) );
					wp_enqueue_script( 'script-b', 'https://example.com/script-b.js', array( 'script-a' ), null, array( 'in_footer' => true ) );
				},
				'expected_header'    => '',
				'expected_footer'    =>
					"<script type='text/javascript' src='https://example.com/script-a.js' data-wp-strategy='async'></script>\n" .
					"<script type='text/javascript' src='https://example.com/script-b.js'></script>\n",
				'expected_in_footer' => array(
					'script-a',
					'script-b',
				),
				'expected_groups'    => array(
					'script-a' => 0,
					'script-b' => 1,
					'jquery'   => 0,
				),
			),

			'head-blocking-dependent-of-delayed-head-script' => array(
				'set_up'             => static function () {
					wp_enqueue_script( 'script-a', 'https://example.com/script-a.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script( 'script-b', 'https://example.com/script-b.js', array( 'script-a' ), null, array( 'in_footer' => false ) );
				},
				'expected_header'    =>
					"<script type='text/javascript' src='https://example.com/script-a.js' data-wp-strategy='defer'></script>\n" .
					"<script type='text/javascript' src='https://example.com/script-b.js'></script>\n",
				'expected_footer'    => '',
				'expected_in_footer' => array(),
				'expected_groups'    => array(
					'script-a' => 0,
					'script-b' => 0,
					'jquery'   => 0,
				),
			),

			'delayed-footer-dependent-of-delayed-head-script' => array(
				'set_up'             => static function () {
					wp_enqueue_script( 'script-a', 'https://example.com/script-a.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script(
						'script-b',
						'https://example.com/script-b.js',
						array( 'script-a' ),
						null,
						array(
							'strategy'  => 'defer',
							'in_footer' => true,
						)
					);
				},
				'expected_header'    =>
					"<script type='text/javascript' src='https://example.com/script-a.js' defer='defer' data-wp-strategy='defer'></script>\n",
				'expected_footer'    =>
					"<script type='text/javascript' src='https://example.com/script-b.js' defer='defer' data-wp-strategy='defer'></script>\n",
				'expected_in_footer' => array(
					'script-b',
				),
				'expected_groups'    => array(
					'script-a' => 0,
					'script-b' => 1,
					'jquery'   => 0,
				),
			),

			'delayed-dependent-in-header-and-delayed-dependents-in-footer' => array(
				'set_up'             => static function () {
					wp_enqueue_script( 'script-a', 'https://example.com/script-a.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script(
						'script-b',
						'https://example.com/script-b.js',
						array( 'script-a' ),
						null,
						array(
							'strategy'  => 'defer',
							'in_footer' => false,
						)
					);
					wp_enqueue_script(
						'script-c',
						'https://example.com/script-c.js',
						array( 'script-a' ),
						null,
						array(
							'strategy'  => 'defer',
							'in_footer' => true,
						)
					);
					wp_enqueue_script(
						'script-d',
						'https://example.com/script-d.js',
						array( 'script-a' ),
						null,
						array(
							'strategy'  => 'defer',
							'in_footer' => true,
						)
					);
				},
				'expected_header'    =>
					"<script type='text/javascript' src='https://example.com/script-a.js' defer='defer' data-wp-strategy='defer'></script>\n" .
					"<script type='text/javascript' src='https://example.com/script-b.js' defer='defer' data-wp-strategy='defer'></script>\n",
				'expected_footer'    =>
					"<script type='text/javascript' src='https://example.com/script-c.js' defer='defer' data-wp-strategy='defer'></script>\n" .
					"<script type='text/javascript' src='https://example.com/script-d.js' defer='defer' data-wp-strategy='defer'></script>\n",
				'expected_in_footer' => array(
					'script-c',
					'script-d',
				),
				'expected_groups'    => array(
					'script-a' => 0,
					'script-b' => 0,
					'script-c' => 1,
					'script-d' => 1,
					'jquery'   => 0,
				),
			),

			'all-dependents-in-footer-with-one-blocking' => array(
				'set_up'             => static function () {
					wp_enqueue_script( 'script-a', 'https://example.com/script-a.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script(
						'script-b',
						'https://example.com/script-b.js',
						array( 'script-a' ),
						null,
						array(
							'strategy'  => 'defer',
							'in_footer' => true,
						)
					);
					wp_enqueue_script( 'script-c', 'https://example.com/script-c.js', array( 'script-a' ), null, true );
					wp_enqueue_script(
						'script-d',
						'https://example.com/script-d.js',
						array( 'script-a' ),
						null,
						array(
							'strategy'  => 'defer',
							'in_footer' => true,
						)
					);
				},
				'expected_header'    => '',
				'expected_footer'    =>
					"<script type='text/javascript' src='https://example.com/script-a.js' data-wp-strategy='defer'></script>\n" .
					"<script type='text/javascript' src='https://example.com/script-b.js' defer='defer' data-wp-strategy='defer'></script>\n" .
					"<script type='text/javascript' src='https://example.com/script-c.js'></script>\n" .
					"<script type='text/javascript' src='https://example.com/script-d.js' defer='defer' data-wp-strategy='defer'></script>\n",
				'expected_in_footer' => array(
					'script-a',
					'script-b',
					'script-c',
					'script-d',
				),
				'expected_groups'    => array(
					'script-a' => 0,
					'script-b' => 1,
					'script-c' => 1,
					'script-d' => 1,
					'jquery'   => 0,

				),
			),

			'blocking-dependents-in-head-and-footer'     => array(
				'set_up'             => static function () {
					wp_enqueue_script( 'script-a', 'https://example.com/script-a.js', array(), null, array( 'strategy' => 'defer' ) );
					wp_enqueue_script(
						'script-b',
						'https://example.com/script-b.js',
						array( 'script-a' ),
						null,
						array(
							'strategy'  => 'defer',
							'in_footer' => false,
						)
					);
					wp_enqueue_script( 'script-c', 'https://example.com/script-c.js', array( 'script-a' ), null, true );
					wp_enqueue_script(
						'script-d',
						'https://example.com/script-d.js',
						array( 'script-a' ),
						null,
						array(
							'strategy'  => 'defer',
							'in_footer' => true,
						)
					);
				},
				'expected_header'    =>
					"<script type='text/javascript' src='https://example.com/script-a.js' data-wp-strategy='defer'></script>\n" .
					"<script type='text/javascript' src='https://example.com/script-b.js' defer='defer' data-wp-strategy='defer'></script>\n",
				'expected_footer'    =>
					"<script type='text/javascript' src='https://example.com/script-c.js'></script>\n" .
					"<script type='text/javascript' src='https://example.com/script-d.js' defer='defer' data-wp-strategy='defer'></script>\n",
				'expected_in_footer' => array(
					'script-c',
					'script-d',
				),
				'expected_groups'    => array(
					'script-a' => 0,
					'script-b' => 0,
					'script-c' => 1,
					'script-d' => 1,
					'jquery'   => 0,
				),
			),

		);
	}

	/**
	 * Tests default scripts are registered with the correct versions.
	 *
	 * Ensures that vendor scripts registered in wp_default_scripts() and
	 * wp_default_packages_vendor() are registered with the correct version
	 * number from package.json.
	 *
	 * @ticket 61855
	 * @ticket 60048
	 *
	 * @covers ::wp_default_scripts
	 * @covers ::wp_default_packages_vendor
	 *
	 * @dataProvider data_vendor_script_versions_registered_manually
	 *
	 * @param string $script Script name as defined in package.json.
	 * @param string $handle Optional. Handle to check for. Defaults to the script name.
	 */
	public function test_vendor_script_versions_registered_manually( $script, $handle = null ) {
		global $wp_scripts;
		wp_default_packages_vendor( $wp_scripts );
		wp_default_scripts( $wp_scripts );

		$package_json = $this->_scripts_from_package_json();
		if ( ! $handle ) {
			$handle = $script;
		}

		/*
		 * Append '.1' to the version number for React and ReactDOM.
		 *
		 * This is due to a change in the build to use the UMD version of the
		 * scripts, requiring a different version number in order to break the
		 * caches of some CDNs.
		 *
		 * This can be removed in the next update to the packages.
		 *
		 * See https://core.trac.wordpress.org/ticket/62422
		 */
		if ( in_array( $handle, array( 'react', 'react-dom' ), true ) ) {
			$package_json[ $script ] .= '.1';
		}

		$script_query = $wp_scripts->query( $handle, 'registered' );

		$this->assertNotFalse( $script_query, "The script '{$handle}' should be registered." );
		$this->assertArrayHasKey( $script, $package_json, "The dependency '{$script}' should be included in package.json." );
		$this->assertSame( $package_json[ $script ], $wp_scripts->query( $handle, 'registered' )->ver, "The script '{$handle}' should be registered with version {$package_json[ $script ]}." );
	}

	/**
	 * Data provider for test_vendor_script_versions_registered_manually.
	 *
	 * @return array[]
	 */
	public function data_vendor_script_versions_registered_manually() {
		return array(
			'backbone'                         => array( 'backbone' ),
			'clipboard'                        => array( 'clipboard' ),
			'core-js-url-browser'              => array( 'core-js-url-browser', 'wp-polyfill-url' ),
			'element-closest'                  => array( 'element-closest', 'wp-polyfill-element-closest' ),
			'formdata-polyfill'                => array( 'formdata-polyfill', 'wp-polyfill-formdata' ),
			'imagesloaded'                     => array( 'imagesloaded' ),
			'jquery-color'                     => array( 'jquery-color' ),
			'jquery-core'                      => array( 'jquery', 'jquery-core' ),
			'jquery-hoverintent'               => array( 'jquery-hoverintent', 'hoverIntent' ),
			'lodash'                           => array( 'lodash' ),
			'masonry'                          => array( 'masonry-layout', 'masonry' ),
			'moment'                           => array( 'moment' ),
			'objectFitPolyfill'                => array( 'objectFitPolyfill', 'wp-polyfill-object-fit' ),
			'polyfill-library (dom rect)'      => array( 'polyfill-library', 'wp-polyfill-dom-rect' ),
			'polyfill-library (node contains)' => array( 'polyfill-library', 'wp-polyfill-node-contains' ),
			'react (jsx-runtime)'              => array( 'react', 'react-jsx-runtime' ),
			'react (React)'                    => array( 'react' ),
			'react-dom'                        => array( 'react-dom' ),
			'regenerator-runtime'              => array( 'regenerator-runtime' ),
			'underscore'                       => array( 'underscore' ),
			'vanilla-js-hoverintent'           => array( 'hoverintent', 'hoverintent-js' ),
			'whatwg-fetch'                     => array( 'whatwg-fetch', 'wp-polyfill-fetch' ),
			'wicg-inert'                       => array( 'wicg-inert', 'wp-polyfill-inert' ),
		);
	}

	/**
	 * Ensures that all the scripts in the package.json are included in the data provider.
	 *
	 * This is a test the tests to ensure the data provider includes all the scripts in package.json.
	 *
	 * @ticket 61855
	 */
	public function test_vendor_script_data_provider_includes_all_packages() {
		$package_json_dependencies  = array_keys( $this->_scripts_from_package_json() );
		$data_provider_dependencies = $this->data_vendor_script_versions_registered_manually();

		/*
		 * Exclude `@wordpress/*` packages from the packages in package.json.
		 *
		 * The version numbers for these packages is generated by the build
		 * process based on a hash of the file contents.
		 */
		$package_json_dependencies = array_filter(
			$package_json_dependencies,
			static function ( $dependency ) {
				return 0 !== strpos( $dependency, '@wordpress/' );
			}
		);

		// Get the script names from the data provider.
		$data_provider_dependencies = array_map(
			static function ( $dependency ) {
				return $dependency[0];
			},
			$data_provider_dependencies
		);

		// Exclude packages that are not registered in WordPress.
		$exclude                   = array( 'react-is', 'json2php' );
		$package_json_dependencies = array_diff( $package_json_dependencies, $exclude );

		/*
		 * Ensure the arrays are unique.
		 *
		 * This is for the react package as it is included in the data provider
		 * as both `react` and `react-jsx-runtime`.
		 */
		$package_json_dependencies  = array_unique( $package_json_dependencies );
		$data_provider_dependencies = array_unique( $data_provider_dependencies );

		$this->assertSameSets( $package_json_dependencies, $data_provider_dependencies );
	}

	/**
	 * Helper to return dependencies from package.json.
	 */
	private function _scripts_from_package_json() {
		$package = file_get_contents( ABSPATH . '../package.json' );
		$data    = json_decode( $package, true );

		$provider = array();
		return $data['dependencies'];
	}

	/**
	 * @ticket 63887
	 */
	public function test_source_url_encoding() {
		$this->add_html5_script_theme_support();

		$handle = '# test/</script> #';

		wp_enqueue_script( $handle, '/example.js', array(), '0.0' );
		wp_add_inline_script( $handle, '"before";', 'before' );
		wp_add_inline_script( $handle, '"after";' );
		wp_localize_script( $handle, 'test', array() );

		$expected = <<<HTML
<script>
var test = [];
//# sourceURL=%23%20test%2F%3C%2Fscript%3E%20%23-js-extra
</script>
<script>
"before";
//# sourceURL=%23%20test%2F%3C%2Fscript%3E%20%23-js-before
</script>
<script src="/example.js?ver=0.0"></script>
<script>
"after";
//# sourceURL=%23%20test%2F%3C%2Fscript%3E%20%23-js-after
</script>

HTML;

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Ensure that `::print_translations()` does not include the sourceURL comment when `$display` is false.
	 *
	 * @ticket 63887
	 * @covers ::print_translations
	 */
	public function test_print_translations_no_display_no_sourceurl() {
		global $wp_scripts;
		$this->add_html5_script_theme_support();

		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), null );
		wp_enqueue_script( 'test-example', '/wp-includes/js/script.js', array(), null );
		wp_set_script_translations( 'test-example', 'default', DIR_TESTDATA . '/languages' );

		$translations_script_data = $wp_scripts->print_translations( 'test-example', false );
		$this->assertStringNotContainsStringIgnoringCase( 'sourceURL=', $translations_script_data );
	}

	/**
	 * Normalizes markup for snapshot.
	 *
	 * @param string $markup Markup.
	 * @return string Normalized markup.
	 */
	private function normalize_markup_for_snapshot( string $markup ): string {
		$processor = new WP_HTML_Tag_Processor( $markup );
		$clean_url = static function ( string $url ): string {
			$url = preg_replace( '#^https?://[^/]+#', '', $url );
			return remove_query_arg( 'ver', $url );
		};
		while ( $processor->next_tag() ) {
			if ( 'LINK' === $processor->get_tag() && is_string( $processor->get_attribute( 'href' ) ) ) {
				$processor->set_attribute( 'href', $clean_url( $processor->get_attribute( 'href' ) ) );
			} elseif ( 'SCRIPT' === $processor->get_tag() && is_string( $processor->get_attribute( 'src' ) ) ) {
				$processor->set_attribute( 'src', $clean_url( $processor->get_attribute( 'src' ) ) );
			}
		}
		return $processor->get_updated_html();
	}

	/**
	 * Tests that WP_Scripts emits a _doing_it_wrong() notice for missing dependencies.
	 *
	 * @ticket 64229
	 * @covers WP_Dependencies::all_deps
	 */
	public function test_wp_scripts_doing_it_wrong_for_missing_dependencies() {
		$expected_incorrect_usage = 'WP_Scripts::add';
		$this->setExpectedIncorrectUsage( $expected_incorrect_usage );

		wp_register_script( 'registered-dep', '/registered-dep.js' );
		wp_enqueue_script( 'main', '/main.js', array( 'registered-dep', 'missing-dep' ) );

		$markup = get_echo( 'wp_print_scripts' );
		$this->assertStringNotContainsString( 'main.js', $markup, 'Expected script to be absent.' );

		$this->assertArrayHasKey(
			$expected_incorrect_usage,
			$this->caught_doing_it_wrong,
			"Expected $expected_incorrect_usage to trigger a _doing_it_wrong() notice for missing dependency."
		);

		$this->assertStringContainsString(
			'The script with the handle "main" was enqueued with dependencies that are not registered: missing-dep',
			$this->caught_doing_it_wrong[ $expected_incorrect_usage ],
			'Expected _doing_it_wrong() notice to indicate missing dependencies for enqueued script.'
		);
	}
}
