<?php
/**
 * Class for looking up a site's health based on a user's WordPress environment.
 *
 * @package WordPress
 * @subpackage Site_Health
 * @since 5.2.0
 */

class WP_Site_Health {
	private static $instance = null;

	public $schedules;
	public $crons;
	public $last_missed_cron     = null;
	public $last_late_cron       = null;
	private $timeout_missed_cron = null;
	private $timeout_late_cron   = null;

	/**
	 * WP_Site_Health constructor.
	 *
	 * @since 5.2.0
	 */
	public function __construct() {
		$this->maybe_create_scheduled_event();

		// Save memory limit before it's affected by wp_raise_memory_limit( 'admin' ).
		$this->php_memory_limit = ini_get( 'memory_limit' );

		$this->timeout_late_cron   = 0;
		$this->timeout_missed_cron = - 5 * MINUTE_IN_SECONDS;

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$this->timeout_late_cron   = - 15 * MINUTE_IN_SECONDS;
			$this->timeout_missed_cron = - 1 * HOUR_IN_SECONDS;
		}

		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_site_health_scheduled_check', array( $this, 'wp_cron_scheduled_check' ) );

		add_action( 'site_health_tab_content', array( $this, 'show_site_health_tab' ) );
	}

	/**
	 * Output the content of a tab in the Site Health screen.
	 *
	 * @since 5.8.0
	 *
	 * @param string $tab Slug of the current tab being displayed.
	 */
	public function show_site_health_tab( $tab ) {
		if ( 'debug' === $tab ) {
			require_once ABSPATH . '/wp-admin/site-health-info.php';
		}
	}

	/**
	 * Return an instance of the WP_Site_Health class, or create one if none exist yet.
	 *
	 * @since 5.4.0
	 *
	 * @return WP_Site_Health|null
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new WP_Site_Health();
		}

		return self::$instance;
	}

	/**
	 * Enqueues the site health scripts.
	 *
	 * @since 5.2.0
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();
		if ( 'site-health' !== $screen->id && 'dashboard' !== $screen->id ) {
			return;
		}

		$health_check_js_variables = array(
			'screen'      => $screen->id,
			'nonce'       => array(
				'site_status'        => wp_create_nonce( 'health-check-site-status' ),
				'site_status_result' => wp_create_nonce( 'health-check-site-status-result' ),
			),
			'site_status' => array(
				'direct' => array(),
				'async'  => array(),
				'issues' => array(
					'good'        => 0,
					'recommended' => 0,
					'critical'    => 0,
				),
			),
		);

		$issue_counts = get_transient( 'health-check-site-status-result' );

		if ( false !== $issue_counts ) {
			$issue_counts = json_decode( $issue_counts );

			$health_check_js_variables['site_status']['issues'] = $issue_counts;
		}

		if ( 'site-health' === $screen->id && ( ! isset( $_GET['tab'] ) || empty( $_GET['tab'] ) ) ) {
			$tests = WP_Site_Health::get_tests();

			foreach ( $tests['direct'] as $test ) {
				if ( is_string( $test['test'] ) ) {
					$test_function = sprintf(
						'get_test_%s',
						$test['test']
					);

					if ( method_exists( $this, $test_function ) && is_callable( array( $this, $test_function ) ) ) {
						$health_check_js_variables['site_status']['direct'][] = $this->perform_test( array( $this, $test_function ) );
						continue;
					}
				}

				if ( is_callable( $test['test'] ) ) {
					$health_check_js_variables['site_status']['direct'][] = $this->perform_test( $test['test'] );
				}
			}

			foreach ( $tests['async'] as $test ) {
				if ( is_string( $test['test'] ) ) {
					$health_check_js_variables['site_status']['async'][] = array(
						'test'      => $test['test'],
						'has_rest'  => ( isset( $test['has_rest'] ) ? $test['has_rest'] : false ),
						'completed' => false,
						'headers'   => isset( $test['headers'] ) ? $test['headers'] : array(),
					);
				}
			}
		}

		wp_localize_script( 'site-health', 'SiteHealth', $health_check_js_variables );
	}

	/**
	 * Run a Site Health test directly.
	 *
	 * @since 5.4.0
	 *
	 * @param callable $callback
	 * @return mixed|void
	 */
	private function perform_test( $callback ) {
		/**
		 * Filters the output of a finished Site Health test.
		 *
		 * @since 5.3.0
		 *
		 * @param array $test_result {
		 *     An associative array of test result data.
		 *
		 *     @type string $label       A label describing the test, and is used as a header in the output.
		 *     @type string $status      The status of the test, which can be a value of `good`, `recommended` or `critical`.
		 *     @type array  $badge {
		 *         Tests are put into categories which have an associated badge shown, these can be modified and assigned here.
		 *
		 *         @type string $label The test label, for example `Performance`.
		 *         @type string $color Default `blue`. A string representing a color to use for the label.
		 *     }
		 *     @type string $description A more descriptive explanation of what the test looks for, and why it is important for the end user.
		 *     @type string $actions     An action to direct the user to where they can resolve the issue, if one exists.
		 *     @type string $test        The name of the test being ran, used as a reference point.
		 * }
		 */
		return apply_filters( 'site_status_test_result', call_user_func( $callback ) );
	}

	/**
	 * Test if `wp_version_check` is blocked.
	 *
	 * It's possible to block updates with the `wp_version_check` filter, but this can't be checked
	 * during an Ajax call, as the filter is never introduced then.
	 *
	 * This filter overrides a standard page request if it's made by an admin through the Ajax call
	 * with the right query argument to check for this.
	 *
	 * @since 5.2.0
	 */
	public function check_wp_version_check_exists() {
		if ( ! is_admin() || ! is_user_logged_in() || ! current_user_can( 'update_core' ) || ! isset( $_GET['health-check-test-wp_version_check'] ) ) {
			return;
		}

		echo ( has_filter( 'wp_version_check', 'wp_version_check' ) ? 'yes' : 'no' );

		die();
	}

	/**
	 * Tests for WordPress version and outputs it.
	 *
	 * Gives various results depending on what kind of updates are available, if any, to encourage
	 * the user to install security updates as a priority.
	 *
	 * @since 5.2.0
	 *
	 * @return array The test result.
	 */
	public function get_test_wordpress_version() {
		$result = array(
			'label'       => '',
			'status'      => '',
			'badge'       => array(
				'label' => __( 'Performance' ),
				'color' => 'blue',
			),
			'description' => '',
			'actions'     => '',
			'test'        => 'wordpress_version',
		);

		$core_current_version = get_bloginfo( 'version' );
		$core_updates         = get_core_updates();

		if ( ! is_array( $core_updates ) ) {
			$result['status'] = 'recommended';

			$result['label'] = sprintf(
				/* translators: %s: Your current version of WordPress. */
				__( 'WordPress version %s' ),
				$core_current_version
			);

			$result['description'] = sprintf(
				'<p>%s</p>',
				__( 'We were unable to check if any new versions of WordPress are available.' )
			);

			$result['actions'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'update-core.php?force-check=1' ) ),
				__( 'Check for updates manually' )
			);
		} else {
			foreach ( $core_updates as $core => $update ) {
				if ( 'upgrade' === $update->response ) {
					$current_version = explode( '.', $core_current_version );
					$new_version     = explode( '.', $update->version );

					$current_major = $current_version[0] . '.' . $current_version[1];
					$new_major     = $new_version[0] . '.' . $new_version[1];

					$result['label'] = sprintf(
						/* translators: %s: The latest version of WordPress available. */
						__( 'WordPress update available (%s)' ),
						$update->version
					);

					$result['actions'] = sprintf(
						'<a href="%s">%s</a>',
						esc_url( admin_url( 'update-core.php' ) ),
						__( 'Install the latest version of WordPress' )
					);

					if ( $current_major !== $new_major ) {
						// This is a major version mismatch.
						$result['status']      = 'recommended';
						$result['description'] = sprintf(
							'<p>%s</p>',
							__( 'A new version of WordPress is available.' )
						);
					} else {
						// This is a minor version, sometimes considered more critical.
						$result['status']         = 'critical';
						$result['badge']['label'] = __( 'Security' );
						$result['description']    = sprintf(
							'<p>%s</p>',
							__( 'A new minor update is available for your site. Because minor updates often address security, it&#8217;s important to install them.' )
						);
					}
				} else {
					$result['status'] = 'good';
					$result['label']  = sprintf(
						/* translators: %s: The current version of WordPress installed on this site. */
						__( 'Your version of WordPress (%s) is up to date' ),
						$core_current_version
					);

					$result['description'] = sprintf(
						'<p>%s</p>',
						__( 'You are currently running the latest version of WordPress available, keep it up!' )
					);
				}
			}
		}

		return $result;
	}

	/**
	 * Test if plugins are outdated, or unnecessary.
	 *
	 * The tests checks if your plugins are up to date, and encourages you to remove any
	 * that are not in use.
	 *
	 * @since 5.2.0
	 *
	 * @return array The test result.
	 */
	public function get_test_plugin_version() {
		$result = array(
			'label'       => __( 'The site does not have inactive plugins' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Plugins extend your site&#8217;s functionality, but it comes with the cost of extending possible attack targets. An inactive plugin can still be an attack target and it is better to remove it as soon as possible.' )
			),
			'actions'     => sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( admin_url( 'plugins.php' ) ),
				__( 'Manage your plugins' )
			),
			'test'        => 'plugin_version',
		);

		$plugins        = get_plugins();
		$plugin_updates = get_plugin_updates();

		$plugins_have_updates = false;
		$plugins_active       = 0;
		$plugins_total        = 0;
		$plugins_need_update  = 0;

		// Loop over the available plugins and check their versions and active state.
		foreach ( $plugins as $plugin_path => $plugin ) {
			$plugins_total++;

			if ( is_plugin_active( $plugin_path ) ) {
				$plugins_active++;
			}

			$plugin_version = $plugin['Version'];

			if ( array_key_exists( $plugin_path, $plugin_updates ) ) {
				$plugins_need_update++;
				$plugins_have_updates = true;
			}
		}

		// Check if there are inactive plugins.
		if ( $plugins_total > $plugins_active && ! is_multisite() ) {
			$unused_plugins = $plugins_total - $plugins_active;

			$result['status'] = 'recommended';

			$result['label'] = __( 'You should remove inactive plugins' );

			$result['description'] .= sprintf(
				'<p>%s %s</p>',
				sprintf(
					/* translators: %d: The number of inactive plugins. */
					_n(
						'Your site has %d inactive plugin.',
						'Your site has %d inactive plugins.',
						$unused_plugins
					),
					$unused_plugins
				),
				__( 'Inactive plugins are tempting targets for attackers. If you&#8217;re not going to use a plugin, we recommend you remove it.' )
			);

			$result['actions'] .= sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( admin_url( 'plugins.php?plugin_status=inactive' ) ),
				__( 'Manage inactive plugins' )
			);
		}

		return $result;
	}

	/**
	 * Test if themes are outdated, or unnecessary.
	 *
	 * Сhecks if your site has a default theme (to fall back on if there is a need),
	 * if your themes are up to date and, finally, encourages you to remove any themes
	 * that are not needed.
	 *
	 * @since 5.2.0
	 *
	 * @return array The test results.
	 */
	public function get_test_theme_version() {
		$result = array(
			'label'       => __( 'The site does not have inactive themes' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Themes add your site&#8217;s look and feel, but inactive ones just add attack targets.' )
			),
			'actions'     => sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( admin_url( 'themes.php' ) ),
				__( 'Manage your themes' )
			),
			'test'        => 'theme_version',
		);

		$theme_updates = get_theme_updates();

		$themes_total        = 0;
		$themes_need_updates = 0;
		$themes_inactive     = 0;

		// This value is changed during processing to determine how many themes are considered a reasonable amount.
		$allowed_theme_count = 1;

		$has_default_theme   = false;
		$has_unused_themes   = false;
		$show_unused_themes  = true;

		// Populate a list of all themes available in the install.
		$all_themes   = wp_get_themes();
		$active_theme = wp_get_theme();

		foreach ( $all_themes as $theme_slug => $theme ) {
			$themes_total++;
		}

		// If this is a child theme, increase the allowed theme count by one, to account for the parent.
		if ( is_child_theme() ) {
			$allowed_theme_count++;
		}

		if ( $themes_total > $allowed_theme_count ) {
			$has_unused_themes = true;
			$themes_inactive   = ( $themes_total - $allowed_theme_count );
		}

		if ( $has_unused_themes && $show_unused_themes && ! is_multisite() ) {

			// This is a child theme, so we want to be a bit more explicit in our messages.
			if ( $active_theme->parent() ) {
				// Recommend removing inactive themes, except a default theme, your current one, and the parent theme.
				$result['status'] = 'recommended';

				$result['label'] = __( 'You should remove inactive themes' );

				$result['description'] .= sprintf(
					'<p>%s %s</p>',
					sprintf(
						/* translators: %d: The number of inactive themes. */
						_n(
							'Your site has %d inactive theme.',
							'Your site has %d inactive themes.',
							$themes_inactive
						),
						$themes_inactive
					),
					sprintf(
						/* translators: 1: The currently active theme. 2: The active theme's parent theme. */
						__( 'To enhance your site&#8217;s security, we recommend you remove any themes you&#8217;re not using. You should keep %1$s, your current theme, and %2$s, its parent theme.' ),
						$active_theme->name,
						$active_theme->parent()->name
					)
				);
			} else {
				// Recommend removing all inactive themes.
				$result['status'] = 'recommended';

				$result['label'] = __( 'You should remove inactive themes' );

				$result['description'] .= sprintf(
					'<p>%s %s</p>',
					sprintf(
						/* translators: 1: The amount of inactive themes. 2: The default theme for WordPress. 3: The currently active theme. */
						_n(
							'Your site has %1$d inactive theme, other than %2$s, the default WordPress theme, and %3$s, your active theme.',
							'Your site has %1$d inactive themes, other than %2$s, the default WordPress theme, and %3$s, your active theme.',
							$themes_inactive
						),
						$themes_inactive,
						$default_theme ? $default_theme->name : WP_DEFAULT_THEME,
						$active_theme->name
					),
					__( 'We recommend removing any inactive theme to enhance your site&#8217;s security.' )
				);
			}
		}

		return $result;
	}

	/**
	 * Check if the passed extension or function are available.
	 *
	 * Make the check for available PHP modules into a simple boolean operator for a cleaner test runner.
	 *
	 * @since 5.2.0
	 * @since 5.3.0 The `$constant` and `$class` parameters were added.
	 *
	 * @param string $extension Optional. The extension name to test. Default null.
	 * @param string $function  Optional. The function name to test. Default null.
	 * @param string $constant  Optional. The constant name to test for. Default null.
	 * @param string $class     Optional. The class name to test for. Default null.
	 * @return bool Whether or not the extension and function are available.
	 */
	private function test_php_extension_availability( $extension = null, $function = null, $constant = null, $class = null ) {
		// If no extension or function is passed, claim to fail testing, as we have nothing to test against.
		if ( ! $extension && ! $function && ! $constant && ! $class ) {
			return false;
		}

		if ( $extension && ! extension_loaded( $extension ) ) {
			return false;
		}
		if ( $function && ! function_exists( $function ) ) {
			return false;
		}
		if ( $constant && ! defined( $constant ) ) {
			return false;
		}
		if ( $class && ! class_exists( $class ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Test if required PHP modules are installed on the host.
	 *
	 * This test builds on the recommendations made by the WordPress Hosting Team
	 * as seen at https://make.wordpress.org/hosting/handbook/handbook/server-environment/#php-extensions
	 *
	 * @since 5.2.0
	 *
	 * @return array
	 */
	public function get_test_php_extensions() {
		$result = array(
			'label'       => __( 'Required and recommended modules are installed' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'PHP modules perform most of the tasks on the server that make your site run. Any changes to these must be made by your server administrator.' ),
			),
			'actions'     => '',
			'test'        => 'php_extensions',
		);

		$modules = array(
			'dom'       => array(
				'class'    => 'DOMNode',
				'required' => false,
			),
			'exif'      => array(
				'function' => 'exif_read_data',
				'required' => false,
			),
			'fileinfo'  => array(
				'function' => 'finfo_file',
				'required' => false,
			),
			'mbstring'  => array(
				'function' => 'mb_check_encoding',
				'required' => false,
			),
			'mysqli'    => array(
				'function' => 'mysqli_connect',
				'required' => false,
			),
			'libsodium' => array(
				'constant'            => 'SODIUM_LIBRARY_VERSION',
				'required'            => false,
				'php_bundled_version' => '7.2.0',
			),
			'openssl'   => array(
				'function' => 'openssl_encrypt',
				'required' => false,
			),
			'imagick'   => array(
				'extension' => 'imagick',
				'required'  => false,
			),
			'mod_xml'   => array(
				'extension' => 'libxml',
				'required'  => false,
			),
			'zip'       => array(
				'class'    => 'ZipArchive',
				'required' => false,
			),
			'gd'        => array(
				'extension'    => 'gd',
				'required'     => false,
				'fallback_for' => 'imagick',
			),
			'simplexml' => array(
				'extension'    => 'simplexml',
				'required'     => false,
				'fallback_for' => 'mod_xml',
			),
		);

		/**
		 * An array representing all the modules we wish to test for.
		 *
		 * @since 5.2.0
		 * @since 5.3.0 The `$constant` and `$class` parameters were added.
		 *
		 * @param array $modules {
		 *     An associative array of modules to test for.
		 *
		 *     @type array ...$0 {
		 *         An associative array of module properties used during testing.
		 *         One of either `$function` or `$extension` must be provided, or they will fail by default.
		 *
		 *         @type string $function     Optional. A function name to test for the existence of.
		 *         @type string $extension    Optional. An extension to check if is loaded in PHP.
		 *         @type string $constant     Optional. A constant name to check for to verify an extension exists.
		 *         @type string $class        Optional. A class name to check for to verify an extension exists.
		 *         @type bool   $required     Is this a required feature or not.
		 *         @type string $fallback_for Optional. The module this module replaces as a fallback.
		 *     }
		 * }
		 */
		$modules = apply_filters( 'site_status_test_php_modules', $modules );

		$failures = array();

		foreach ( $modules as $library => $module ) {
			$extension  = ( isset( $module['extension'] ) ? $module['extension'] : null );
			$function   = ( isset( $module['function'] ) ? $module['function'] : null );
			$constant   = ( isset( $module['constant'] ) ? $module['constant'] : null );
			$class_name = ( isset( $module['class'] ) ? $module['class'] : null );

			// If this module is a fallback for another function, check if that other function passed.
			if ( isset( $module['fallback_for'] ) ) {
				/*
				 * If that other function has a failure, mark this module as required for usual operations.
				 * If that other function hasn't failed, skip this test as it's only a fallback.
				 */
				if ( isset( $failures[ $module['fallback_for'] ] ) ) {
					$module['required'] = true;
				} else {
					continue;
				}
			}

			if ( ! $this->test_php_extension_availability( $extension, $function, $constant, $class_name ) && ( ! isset( $module['php_bundled_version'] ) || version_compare( PHP_VERSION, $module['php_bundled_version'], '<' ) ) ) {
				if ( $module['required'] ) {
					$result['status'] = 'critical';

					$class         = 'error';
					$screen_reader = __( 'Error' );
					$message       = sprintf(
						/* translators: %s: The module name. */
						__( 'The required module, %s, is not installed, or has been disabled.' ),
						$library
					);
				} else {
					$class         = 'warning';
					$screen_reader = __( 'Warning' );
					$message       = sprintf(
						/* translators: %s: The module name. */
						__( 'The optional module, %s, is not installed, or has been disabled.' ),
						$library
					);
				}

				if ( ! $module['required'] && 'good' === $result['status'] ) {
					$result['status'] = 'recommended';
				}

				$failures[ $library ] = "<span class='dashicons $class'><span class='screen-reader-text'>$screen_reader</span></span> $message";
			}
		}

		if ( ! empty( $failures ) ) {
			$output = '<ul>';

			foreach ( $failures as $failure ) {
				$output .= sprintf(
					'<li>%s</li>',
					$failure
				);
			}

			$output .= '</ul>';
		}

		if ( 'good' !== $result['status'] ) {
			if ( 'recommended' === $result['status'] ) {
				$result['label'] = __( 'One or more recommended modules are missing' );
			}
			if ( 'critical' === $result['status'] ) {
				$result['label'] = __( 'One or more required modules are missing' );
			}

			$result['description'] .= $output;
		}

		return $result;
	}

	/**
	 * Test if the site can communicate with WordPress.org.
	 *
	 * @since 5.2.0
	 *
	 * @return array The test results.
	 */
	public function get_test_dotorg_communication() {
		$result = array(
			'label'       => __( 'Can communicate with calmpress.org' ),
			'status'      => '',
			'badge'       => array(
				'label' => __( 'Security' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Communicating with the calmPress servers is used to check for new versions, and to both install and update calmPress core, themes or plugins.' )
			),
			'actions'     => '',
			'test'        => 'dotorg_communication',
		);

		$wp_dotorg = wp_remote_get(
			'https://api.calmpress.org',
			array(
				'timeout' => 10,
			)
		);
		if ( ! is_wp_error( $wp_dotorg ) ) {
			$result['status'] = 'good';
		} else {
			$result['status'] = 'critical';

			$result['label'] = __( 'Could not reach calmpress.org' );

			$result['description'] .= sprintf(
				'<p>%s</p>',
				sprintf(
					'<span class="error"><span class="screen-reader-text">%s</span></span> %s',
					__( 'Error' ),
					sprintf(
						/* translators: 1: The IP address calmpress.org resolves to. 2: The error returned by the lookup. */
						__( 'Your site is unable to reach calmpress.org at %1$s, and returned the error: %2$s' ),
						gethostbyname( 'api.calmpress.org' ),
						$wp_dotorg->get_error_message()
					)
				)
			);
		}

		return $result;
	}

	/**
	 * Test if debug information is enabled.
	 *
	 * When WP_DEBUG is enabled, errors and information may be disclosed to site visitors,
	 * or logged to a publicly accessible file.
	 *
	 * Debugging is also frequently left enabled after looking for errors on a site,
	 * as site owners do not understand the implications of this.
	 *
	 * @since 5.2.0
	 *
	 * @return array The test results.
	 */
	public function get_test_is_in_debug_mode() {
		$result = array(
			'label'       => __( 'Your site is not set to output debug information' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Debug mode is often enabled to gather more details about an error or site failure, but may contain sensitive information which should not be available on a publicly available website.' )
			),
			'actions'     => sprintf(
				'<p><a href="%s" target="_blank" rel="noopener">%s <span class="screen-reader-text">%s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a></p>',
				/* translators: Documentation explaining debugging in WordPress. */
				esc_url( __( 'https://wordpress.org/support/article/debugging-in-wordpress/' ) ),
				__( 'Learn more about debugging in WordPress.' ),
				/* translators: Accessibility text. */
				__( '(opens in a new tab)' )
			),
			'test'        => 'is_in_debug_mode',
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				$result['label'] = __( 'Your site is set to log errors to a potentially public file.' );

				$result['status'] = ( 0 === strpos( ini_get( 'error_log' ), ABSPATH ) ) ? 'critical' : 'recommended';

				$result['description'] .= sprintf(
					'<p>%s</p>',
					sprintf(
						/* translators: %s: WP_DEBUG_LOG */
						__( 'The value, %s, has been added to this website&#8217;s configuration file. This means any errors on the site will be written to a file which is potentially available to all users.' ),
						'<code>WP_DEBUG_LOG</code>'
					)
				);
			}

			if ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) {
				$result['label'] = __( 'Your site is set to display errors to site visitors' );

				$result['status'] = 'critical';

				// On development environments, set the status to recommended.
				if ( $this->is_development_environment() ) {
					$result['status'] = 'recommended';
				}

				$result['description'] .= sprintf(
					'<p>%s</p>',
					sprintf(
						/* translators: 1: WP_DEBUG_DISPLAY, 2: WP_DEBUG */
						__( 'The value, %1$s, has either been enabled by %2$s or added to your configuration file. This will make errors display on the front end of your site.' ),
						'<code>WP_DEBUG_DISPLAY</code>',
						'<code>WP_DEBUG</code>'
					)
				);
			}
		}

		return $result;
	}

	/**
	 * Test if scheduled events run as intended.
	 *
	 * If scheduled events are not running, this may indicate something with WP_Cron is not working
	 * as intended, or that there are orphaned events hanging around from older code.
	 *
	 * @since 5.2.0
	 *
	 * @return array The test results.
	 */
	public function get_test_scheduled_events() {
		$result = array(
			'label'       => __( 'Scheduled events are running' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Scheduled events are what periodically looks for updates to plugins, themes and WordPress itself. It is also what makes sure scheduled posts are published on time. It may also be used by various plugins to make sure that planned actions are executed.' )
			),
			'actions'     => '',
			'test'        => 'scheduled_events',
		);

		$this->wp_schedule_test_init();

		if ( is_wp_error( $this->has_missed_cron() ) ) {
			$result['status'] = 'critical';

			$result['label'] = __( 'It was not possible to check your scheduled events' );

			$result['description'] = sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: The error message returned while from the cron scheduler. */
					__( 'While trying to test your site&#8217;s scheduled events, the following error was returned: %s' ),
					$this->has_missed_cron()->get_error_message()
				)
			);
		} elseif ( $this->has_missed_cron() ) {
			$result['status'] = 'recommended';

			$result['label'] = __( 'A scheduled event has failed' );

			$result['description'] = sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: The name of the failed cron event. */
					__( 'The scheduled event, %s, failed to run. Your site still works, but this may indicate that scheduling posts or automated updates may not work as intended.' ),
					$this->last_missed_cron
				)
			);
		} elseif ( $this->has_late_cron() ) {
			$result['status'] = 'recommended';

			$result['label'] = __( 'A scheduled event is late' );

			$result['description'] = sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: The name of the late cron event. */
					__( 'The scheduled event, %s, is late to run. Your site still works, but this may indicate that scheduling posts or automated updates may not work as intended.' ),
					$this->last_late_cron
				)
			);
		}

		return $result;
	}

	/**
	 * Test if loopbacks work as expected.
	 *
	 * A loopback is when WordPress queries itself, for example to start a new WP_Cron instance,
	 * or when editing a plugin or theme. This has shown itself to be a recurring issue,
	 * as code can very easily break this interaction.
	 *
	 * @since 5.2.0
	 *
	 * @return array The test results.
	 */
	public function get_test_loopback_requests() {
		$result = array(
			'label'       => __( 'Your site can perform loopback requests' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Loopback requests are used to run scheduled events, and are also used by the built-in editors for themes and plugins to verify code stability.' )
			),
			'actions'     => '',
			'test'        => 'loopback_requests',
		);

		$check_loopback = $this->can_perform_loopback();

		$result['status'] = $check_loopback->status;

		if ( 'good' !== $result['status'] ) {
			$result['label'] = __( 'Your site could not complete a loopback request' );

			$result['description'] .= sprintf(
				'<p>%s</p>',
				$check_loopback->message
			);
		}

		return $result;
	}

	/**
	 * Test if 'file_uploads' directive in PHP.ini is turned off.
	 *
	 * @since 5.5.0
	 *
	 * @return array The test results.
	 */
	public function get_test_file_uploads() {
		$result = array(
			'label'       => __( 'Files can be uploaded.' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: 1: file_uploads, 2: php.ini */
					__( 'The %1$s directive in %2$s determines if uploading files is allowed on your site.' ),
					'<code>file_uploads</code>',
					'<code>php.ini</code>'
				)
			),
			'actions'     => '',
			'test'        => 'file_uploads',
		);

		if ( ! function_exists( 'ini_get' ) ) {
			$result['status']       = 'critical';
			$result['description'] .= sprintf(
				/* translators: %s: ini_get() */
				__( 'The %s function has been disabled, some media settings are unavailable because of this.' ),
				'<code>ini_get()</code>'
			);
			return $result;
		}

		if ( empty( ini_get( 'file_uploads' ) ) ) {
			$result['status']       = 'critical';
			$result['description'] .= sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: 1: file_uploads, 2: 0 */
					__( '%1$s is set to %2$s. You won\'t be able to upload files on your site.' ),
					'<code>file_uploads</code>',
					'<code>0</code>'
				)
			);
			return $result;
		}

		$post_max_size       = ini_get( 'post_max_size' );
		$upload_max_filesize = ini_get( 'upload_max_filesize' );

		if ( wp_convert_hr_to_bytes( $post_max_size ) < wp_convert_hr_to_bytes( $upload_max_filesize ) ) {
			$result['label'] = sprintf(
				/* translators: 1: post_max_size, 2: upload_max_filesize */
				__( 'The "%1$s" value is smaller than "%2$s".' ),
				'post_max_size',
				'upload_max_filesize'
			);
			$result['status'] = 'recommended';

			if ( 0 === wp_convert_hr_to_bytes( $post_max_size ) ) {
				$result['description'] = sprintf(
					'<p>%s</p>',
					sprintf(
						/* translators: 1: post_max_size, 2: upload_max_filesize */
						__( 'The setting for %1$s is currently configured as 0, this could cause some problems when trying to upload files through plugin or theme features that rely on various upload methods. It is recommended to configure this setting to a fixed value, ideally matching the value of %2$s, as some upload methods read the value 0 as either unlimited, or disabled.' ),
						'<code>post_max_size</code>',
						'<code>upload_max_filesize</code>'
					)
				);
			} else {
				$result['description'] = sprintf(
					'<p>%s</p>',
					sprintf(
						/* translators: 1: post_max_size, 2: upload_max_filesize */
						__( 'The setting for %1$s is smaller than %2$s, this could cause some problems when trying to upload files.' ),
						'<code>post_max_size</code>',
						'<code>upload_max_filesize</code>'
					)
				);
			}

			return $result;
		}

		return $result;
	}

	/**
	 * Tests if the Authorization header has the expected values.
	 *
	 * @since 5.6.0
	 *
	 * @return array
	 */
	public function get_test_authorization_header() {
		$result = array(
			'label'       => __( 'The Authorization header is working as expected.' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'The Authorization header comes from the third-party applications you approve. Without it, those apps cannot connect to your site.' )
			),
			'actions'     => '',
			'test'        => 'authorization_header',
		);

		if ( ! isset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ) ) {
			$result['label'] = __( 'The authorization header is missing.' );
		} elseif ( 'user' !== $_SERVER['PHP_AUTH_USER'] || 'pwd' !== $_SERVER['PHP_AUTH_PW'] ) {
			$result['label'] = __( 'The authorization header is invalid.' );
		} else {
			return $result;
		}

		$result['status'] = 'recommended';

		$result['actions'] .= sprintf(
			'<p><a href="%s" target="_blank" rel="noopener">%s <span class="screen-reader-text">%s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a></p>',
			__( 'https://developer.wordpress.org/rest-api/frequently-asked-questions/#why-is-authentication-not-working' ),
			__( 'Learn how to configure the Authorization header.' ),
			/* translators: Accessibility text. */
			__( '(opens in a new tab)' )
		);

		return $result;
	}

	/**
	 * Return a set of tests that belong to the site status page.
	 *
	 * Each site status test is defined here, they may be `direct` tests, that run on page load, or `async` tests
	 * which will run later down the line via JavaScript calls to improve page performance and hopefully also user
	 * experiences.
	 *
	 * @since 5.2.0
	 * @since 5.6.0 Added support for `has_rest` and `permissions`.
	 *
	 * @return array The list of tests to run.
	 */
	public static function get_tests() {
		$tests = array(
			'direct' => array(
				'wordpress_version'    => array(
					'label' => __( 'calmPress Version' ),
					'test'  => 'wordpress_version',
				),
				'plugin_version'            => array(
					'label' => __( 'Plugin Versions' ),
					'test'  => 'plugin_version',
				),
				'theme_version'             => array(
					'label' => __( 'Theme Versions' ),
					'test'  => 'theme_version',
				),
				'php_extensions'            => array(
					'label' => __( 'PHP Extensions' ),
					'test'  => 'php_extensions',
				),
				'php_default_timezone'      => array(
					'label' => __( 'PHP Default Timezone' ),
					'test'  => 'php_default_timezone',
				),
				'php_sessions'              => array(
					'label' => __( 'PHP Sessions' ),
					'test'  => 'php_sessions',
				),
				'sql_server'                => array(
					'label' => __( 'Database Server version' ),
					'test'  => 'sql_server',
				),
				'scheduled_events'          => array(
					'label' => __( 'Scheduled events' ),
					'test'  => 'scheduled_events',
				),
				'debug_enabled'             => array(
					'label' => __( 'Debugging enabled' ),
					'test'  => 'is_in_debug_mode',
				),
				'file_uploads'              => array(
					'label' => __( 'File uploads' ),
					'test'  => 'file_uploads',
				),
			),
			'async'  => array(
				'dotorg_communication' => array(
					'label'             => __( 'Communication with calmpress.org' ),
					'test'              => rest_url( 'wp-site-health/v1/tests/dotorg-communication' ),
					'has_rest'          => true,
					'async_direct_test' => array( WP_Site_Health::get_instance(), 'get_test_dotorg_communication' ),
				),
				'loopback_requests'    => array(
					'label'             => __( 'Loopback request' ),
					'test'              => rest_url( 'wp-site-health/v1/tests/loopback-requests' ),
					'has_rest'          => true,
					'async_direct_test' => array( WP_Site_Health::get_instance(), 'get_test_loopback_requests' ),
				),
			),
		);

		// Conditionally include Authorization header test if the site isn't protected by Basic Auth.
		if ( ! wp_is_site_protected_by_basic_auth() ) {
			$tests['async']['authorization_header'] = array(
				'label'     => __( 'Authorization header' ),
				'test'      => rest_url( 'wp-site-health/v1/tests/authorization-header' ),
				'has_rest'  => true,
				'headers'   => array( 'Authorization' => 'Basic ' . base64_encode( 'user:pwd' ) ),
				'skip_cron' => true,
			);
		}

		/**
		 * Add or modify which site status tests are run on a site.
		 *
		 * The site health is determined by a set of tests based on best practices from
		 * both the WordPress Hosting Team, but also web standards in general.
		 *
		 * Some sites may not have the same requirements, for example the automatic update
		 * checks may be handled by a host, and are therefore disabled in core.
		 * Or maybe you want to introduce a new test, is caching enabled/disabled/stale for example.
		 *
		 * Tests may be added either as direct, or asynchronous ones. Any test that may require some time
		 * to complete should run asynchronously, to avoid extended loading periods within wp-admin.
		 *
		 * @since 5.2.0
		 * @since 5.6.0 Added the `async_direct_test` array key.
		 *              Added the `skip_cron` array key.
		 *
		 * @param array $test_type {
		 *     An associative array, where the `$test_type` is either `direct` or
		 *     `async`, to declare if the test should run via Ajax calls after page load.
		 *
		 *     @type array $identifier {
		 *         `$identifier` should be a unique identifier for the test that should run.
		 *         Plugins and themes are encouraged to prefix test identifiers with their slug
		 *         to avoid any collisions between tests.
		 *
		 *         @type string   $label             A friendly label for your test to identify it by.
		 *         @type mixed    $test              A callable to perform a direct test, or a string AJAX action
		 *                                           to be called to perform an async test.
		 *         @type boolean  $has_rest          Optional. Denote if `$test` has a REST API endpoint.
		 *         @type boolean  $skip_cron         Whether to skip this test when running as cron.
		 *         @type callable $async_direct_test A manner of directly calling the test marked as asynchronous,
		 *                                           as the scheduled event can not authenticate, and endpoints
		 *                                           may require authentication.
		 *     }
		 * }
		 */
		$tests = apply_filters( 'site_status_tests', $tests );

		// Ensure that the filtered tests contain the required array keys.
		$tests = array_merge(
			array(
				'direct' => array(),
				'async'  => array(),
			),
			$tests
		);

		return $tests;
	}

	/**
	 * Add a class to the body HTML tag.
	 *
	 * Filters the body class string for admin pages and adds our own class for easier styling.
	 *
	 * @since 5.2.0
	 *
	 * @param string $body_class The body class string.
	 * @return string The modified body class string.
	 */
	public function admin_body_class( $body_class ) {
		$screen = get_current_screen();
		if ( 'site-health' !== $screen->id ) {
			return $body_class;
		}

		$body_class .= ' site-health';

		return $body_class;
	}

	/**
	 * Initiate the WP_Cron schedule test cases.
	 *
	 * @since 5.2.0
	 */
	private function wp_schedule_test_init() {
		$this->schedules = wp_get_schedules();
		$this->get_cron_tasks();
	}

	/**
	 * Populate our list of cron events and store them to a class-wide variable.
	 *
	 * @since 5.2.0
	 */
	private function get_cron_tasks() {
		$cron_tasks = _get_cron_array();

		if ( empty( $cron_tasks ) ) {
			$this->crons = new WP_Error( 'no_tasks', __( 'No scheduled events exist on this site.' ) );
			return;
		}

		$this->crons = array();

		foreach ( $cron_tasks as $time => $cron ) {
			foreach ( $cron as $hook => $dings ) {
				foreach ( $dings as $sig => $data ) {

					$this->crons[ "$hook-$sig-$time" ] = (object) array(
						'hook'     => $hook,
						'time'     => $time,
						'sig'      => $sig,
						'args'     => $data['args'],
						'schedule' => $data['schedule'],
						'interval' => isset( $data['interval'] ) ? $data['interval'] : null,
					);

				}
			}
		}
	}

	/**
	 * Check if any scheduled tasks have been missed.
	 *
	 * Returns a boolean value of `true` if a scheduled task has been missed and ends processing.
	 *
	 * If the list of crons is an instance of WP_Error, returns the instance instead of a boolean value.
	 *
	 * @since 5.2.0
	 *
	 * @return bool|WP_Error True if a cron was missed, false if not. WP_Error if the cron is set to that.
	 */
	public function has_missed_cron() {
		if ( is_wp_error( $this->crons ) ) {
			return $this->crons;
		}

		foreach ( $this->crons as $id => $cron ) {
			if ( ( $cron->time - time() ) < $this->timeout_missed_cron ) {
				$this->last_missed_cron = $cron->hook;
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if any scheduled tasks are late.
	 *
	 * Returns a boolean value of `true` if a scheduled task is late and ends processing.
	 *
	 * If the list of crons is an instance of WP_Error, returns the instance instead of a boolean value.
	 *
	 * @since 5.3.0
	 *
	 * @return bool|WP_Error True if a cron is late, false if not. WP_Error if the cron is set to that.
	 */
	public function has_late_cron() {
		if ( is_wp_error( $this->crons ) ) {
			return $this->crons;
		}

		foreach ( $this->crons as $id => $cron ) {
			$cron_offset = $cron->time - time();
			if (
				$cron_offset >= $this->timeout_missed_cron &&
				$cron_offset < $this->timeout_late_cron
			) {
				$this->last_late_cron = $cron->hook;
				return true;
			}
		}

		return false;
	}

	/**
	 * Run a loopback test on our site.
	 *
	 * Loopbacks are what WordPress uses to communicate with itself to start up WP_Cron, scheduled posts,
	 * make sure plugin or theme edits don't cause site failures and similar.
	 *
	 * @since 5.2.0
	 *
	 * @return object The test results.
	 */
	function can_perform_loopback() {
		$body    = array( 'site-health' => 'loopback-test' );
		$cookies = wp_unslash( $_COOKIE );
		$timeout = 10;
		$headers = array(
			'Cache-Control' => 'no-cache',
		);
		/** This filter is documented in wp-includes/class-wp-http-streams.php */
		$sslverify = apply_filters( 'https_local_ssl_verify', false );

		// Include Basic auth in loopback requests.
		if ( isset( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) {
			$headers['Authorization'] = 'Basic ' . base64_encode( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) . ':' . wp_unslash( $_SERVER['PHP_AUTH_PW'] ) );
		}

		$url = site_url( 'wp-cron.php' );

		/*
		 * A post request is used for the wp-cron.php loopback test to cause the file
		 * to finish early without triggering cron jobs. This has two benefits:
		 * - cron jobs are not triggered a second time on the site health page,
		 * - the loopback request finishes sooner providing a quicker result.
		 *
		 * Using a POST request causes the loopback to differ slightly to the standard
		 * GET request WordPress uses for wp-cron.php loopback requests but is close
		 * enough. See https://core.trac.wordpress.org/ticket/52547
		 */
		$r = wp_remote_post( $url, compact( 'body', 'cookies', 'headers', 'timeout', 'sslverify' ) );

		if ( is_wp_error( $r ) ) {
			return (object) array(
				'status'  => 'critical',
				'message' => sprintf(
					'%s<br>%s',
					__( 'The loopback request to your site failed, this means features relying on them are not currently working as expected.' ),
					sprintf(
						/* translators: 1: The WordPress error message. 2: The WordPress error code. */
						__( 'Error: %1$s (%2$s)' ),
						$r->get_error_message(),
						$r->get_error_code()
					)
				),
			);
		}

		if ( 200 !== wp_remote_retrieve_response_code( $r ) ) {
			return (object) array(
				'status'  => 'recommended',
				'message' => sprintf(
					/* translators: %d: The HTTP response code returned. */
					__( 'The loopback request returned an unexpected http status code, %d, it was not possible to determine if this will prevent features from working as expected.' ),
					wp_remote_retrieve_response_code( $r )
				),
			);
		}

		return (object) array(
			'status'  => 'good',
			'message' => __( 'The loopback request to your site completed successfully.' ),
		);
	}

	/**
	 * Create a weekly cron event, if one does not already exist.
	 *
	 * @since 5.4.0
	 */
	public function maybe_create_scheduled_event() {
		if ( ! wp_next_scheduled( 'wp_site_health_scheduled_check' ) && ! wp_installing() ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'wp_site_health_scheduled_check' );
		}
	}

	/**
	 * Run our scheduled event to check and update the latest site health status for the website.
	 *
	 * @since 5.4.0
	 */
	public function wp_cron_scheduled_check() {
		// Bootstrap wp-admin, as WP_Cron doesn't do this for us.
		require_once trailingslashit( ABSPATH ) . 'wp-admin/includes/admin.php';

		$tests = WP_Site_Health::get_tests();

		$results = array();

		$site_status = array(
			'good'        => 0,
			'recommended' => 0,
			'critical'    => 0,
		);

		foreach ( $tests['direct'] as $test ) {
			if ( ! empty( $test['skip_cron'] ) ) {
				continue;
			}

			if ( is_string( $test['test'] ) ) {
				$test_function = sprintf(
					'get_test_%s',
					$test['test']
				);

				if ( method_exists( $this, $test_function ) && is_callable( array( $this, $test_function ) ) ) {
					$results[] = $this->perform_test( array( $this, $test_function ) );
					continue;
				}
			}

			if ( is_callable( $test['test'] ) ) {
				$results[] = $this->perform_test( $test['test'] );
			}
		}

		foreach ( $tests['async'] as $test ) {
			if ( ! empty( $test['skip_cron'] ) ) {
				continue;
			}

			// Local endpoints may require authentication, so asynchronous tests can pass a direct test runner as well.
			if ( ! empty( $test['async_direct_test'] ) && is_callable( $test['async_direct_test'] ) ) {
				// This test is callable, do so and continue to the next asynchronous check.
				$results[] = $this->perform_test( $test['async_direct_test'] );
				continue;
			}

			if ( is_string( $test['test'] ) ) {
				// Check if this test has a REST API endpoint.
				if ( isset( $test['has_rest'] ) && $test['has_rest'] ) {
					$result_fetch = wp_remote_get(
						$test['test'],
						array(
							'body' => array(
								'_wpnonce' => wp_create_nonce( 'wp_rest' ),
							),
						)
					);
				} else {
					$result_fetch = wp_remote_post(
						admin_url( 'admin-ajax.php' ),
						array(
							'body' => array(
								'action'   => $test['test'],
								'_wpnonce' => wp_create_nonce( 'health-check-site-status' ),
							),
						)
					);
				}

				if ( ! is_wp_error( $result_fetch ) && 200 === wp_remote_retrieve_response_code( $result_fetch ) ) {
					$result = json_decode( wp_remote_retrieve_body( $result_fetch ), true );
				} else {
					$result = false;
				}

				if ( is_array( $result ) ) {
					$results[] = $result;
				} else {
					$results[] = array(
						'status' => 'recommended',
						'label'  => __( 'A test is unavailable' ),
					);
				}
			}
		}

		foreach ( $results as $result ) {
			if ( 'critical' === $result['status'] ) {
				$site_status['critical']++;
			} elseif ( 'recommended' === $result['status'] ) {
				$site_status['recommended']++;
			} else {
				$site_status['good']++;
			}
		}

		set_transient( 'health-check-site-status-result', wp_json_encode( $site_status ) );
	}

	/**
	 * Checks if the current environment type is set to 'development' or 'local'.
	 *
	 * @since 5.6.0
	 *
	 * @return bool True if it is a development environment, false if not.
	 */
	public function is_development_environment() {
		return in_array( wp_get_environment_type(), array( 'development', 'local' ), true );
	}

}
