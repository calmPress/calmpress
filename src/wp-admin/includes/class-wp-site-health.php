<?php
/**
 * Class for looking up a site's health based on a user's WordPress environment.
 *
 * @package WordPress
 * @subpackage Site_Health
 * @since 5.2.0
 */

#[AllowDynamicProperties]
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
	 * Outputs the content of a tab in the Site Health screen.
	 *
	 * @since 5.8.0
	 *
	 * @param string $tab Slug of the current tab being displayed.
	 */
	public function show_site_health_tab( $tab ) {
		if ( 'debug' === $tab ) {
			require_once ABSPATH . 'wp-admin/site-health-info.php';
		}
	}

	/**
	 * Returns an instance of the WP_Site_Health class, or create one if none exist yet.
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
	 * Runs a Site Health test directly.
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
	 * Tests if `wp_version_check` is blocked.
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

		$core_current_version = wp_get_wp_version();
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
				__( 'Unable to check if any new versions of WordPress are available.' )
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
	 * Tests if plugins are outdated, or unnecessary.
	 *
	 * The test checks if your plugins are up to date, and encourages you to remove any
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

		$plugins_active      = 0;
		$plugins_total       = 0;
		$plugins_need_update = 0;

		// Loop over the available plugins and check their versions and active state.
		foreach ( $plugins as $plugin_path => $plugin ) {
			++$plugins_total;

			if ( is_plugin_active( $plugin_path ) ) {
				++$plugins_active;
			}

			if ( array_key_exists( $plugin_path, $plugin_updates ) ) {
				++$plugins_need_update;
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
				__( 'Inactive plugins are tempting targets for attackers. If you are not going to use a plugin, you should consider removing it.' )
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
	 * Tests if themes are outdated, or unnecessary.
	 *
	 * Checks if your site has a default theme (to fall back on if there is a need),
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
			++$allowed_theme_count;
		}

		// If there's a default theme installed and not in use, we count that as allowed as well.
		if ( $has_default_theme && ! $using_default_theme ) {
			++$allowed_theme_count;
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
						__( 'To enhance your site&#8217;s security, you should consider removing any themes you&#8217;re not using. You should keep %1$s, your current theme, and %2$s, its parent theme.' ),
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
						/* translators: 1: The amount of inactive themes. 2: The currently active theme. */
						_n(
							'Your site has %1$d inactive theme, other than %2$s, your active theme.',
							'Your site has %1$d inactive themes, other than %2$s, your active theme.',
							$themes_inactive
						),
						$themes_inactive,
						$active_theme->name
					),
					__( 'You should consider removing any inactive theme to enhance your site&#8217;s security.' )
				);
			}
		}

		return $result;
	}

	/**
	 * Checks if the passed extension or function are available.
	 *
	 * Make the check for available PHP modules into a simple boolean operator for a cleaner test runner.
	 *
	 * @since 5.2.0
	 * @since 5.3.0 The `$constant_name` and `$class_name` parameters were added.
	 *
	 * @param string $extension_name Optional. The extension name to test. Default null.
	 * @param string $function_name  Optional. The function name to test. Default null.
	 * @param string $constant_name  Optional. The constant name to test for. Default null.
	 * @param string $class_name     Optional. The class name to test for. Default null.
	 * @return bool Whether or not the extension and function are available.
	 */
	private function test_php_extension_availability( $extension_name = null, $function_name = null, $constant_name = null, $class_name = null ) {
		// If no extension or function is passed, claim to fail testing, as we have nothing to test against.
		if ( ! $extension_name && ! $function_name && ! $constant_name && ! $class_name ) {
			return false;
		}

		if ( $extension_name && ! extension_loaded( $extension_name ) ) {
			return false;
		}

		if ( $function_name && ! function_exists( $function_name ) ) {
			return false;
		}

		if ( $constant_name && ! defined( $constant_name ) ) {
			return false;
		}

		if ( $class_name && ! class_exists( $class_name ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Tests if required PHP modules are installed on the host.
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
			'hash'      => array(
				'function' => 'hash',
				'required' => true,
			),
			'imagick'   => array(
				'extension' => 'imagick',
				'required'  => false,
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
			'apcu'       => array(
				'extension' => 'apcu',
				'required'  => false,
			),
		);

		/**
		 * Filters the array representing all the modules we wish to test for.
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
			$extension_name = ( isset( $module['extension'] ) ? $module['extension'] : null );
			$function_name  = ( isset( $module['function'] ) ? $module['function'] : null );
			$constant_name  = ( isset( $module['constant'] ) ? $module['constant'] : null );
			$class_name     = ( isset( $module['class'] ) ? $module['class'] : null );

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

			if ( ! $this->test_php_extension_availability( $extension_name, $function_name, $constant_name, $class_name )
				&& ( ! isset( $module['php_bundled_version'] )
					|| version_compare( PHP_VERSION, $module['php_bundled_version'], '<' ) )
			) {
				if ( $module['required'] ) {
					$result['status'] = 'critical';

					$class = 'error';
					/* translators: Hidden accessibility text. */
					$screen_reader = __( 'Error' );
					$message       = sprintf(
						/* translators: %s: The module name. */
						__( 'The required module, %s, is not installed, or has been disabled.' ),
						$library
					);
				} else {
					$class = 'warning';
					/* translators: Hidden accessibility text. */
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

				$failures[ $library ] = "<span class='dashicons $class' aria-hidden='true'></span><span class='screen-reader-text'>$screen_reader</span> $message";
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
					/* translators: Hidden accessibility text. */
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
	 * Tests if debug information is enabled.
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
				'<p><a href="%s" target="_blank">%s<span class="screen-reader-text"> %s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a></p>',
				/* translators: Documentation explaining debugging in WordPress. */
				esc_url( __( 'https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/' ) ),
				__( 'Learn more about debugging in WordPress.' ),
				/* translators: Hidden accessibility text. */
				__( '(opens in a new tab)' )
			),
			'test'        => 'is_in_debug_mode',
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				$result['label'] = __( 'Your site is set to log errors to a potentially public file.' );

				$result['status'] = str_starts_with( ini_get( 'error_log' ), ABSPATH ) ? 'critical' : 'recommended';

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
	 * Tests if debug information is enabled.
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
	public function get_test_object_cache() {
		$result = array(
			'label'       => esc_html__( 'Your site is not set to allow persistant object cache' ),
			'status'      => 'critical',
			'badge'       => array(
				'label' => esc_html__( 'Performance' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Persistant object cache helps in improving your overall site responsiveness by adding a storage area on your server which is used to keep frequently used values instead of getting the from the DataBase which is a slow operation.' )
			),
			'actions'     => '',
			'test'        => 'object_cache',
		);

		$additional_messages = [];
		$can_cache           = false;
		
		$opcode_cache_usable = false;
		if ( function_exists( 'opcache_get_status' ) ) {
			// This check ensures that it is possible to use the api to do stuff,
			// especially invalidate cache files. Without invalidation there is
			// a potential of using stale values.
			if ( false !== opcache_get_status( false ) ) {
				$opcode_cache_usable = true;
			}
		}

		// Check if APCu is used.
		if ( function_exists( 'apcu_enabled' ) && apcu_enabled() ) {
			if ( defined( 'APCU_DISABLED' ) && APCU_DISABLED ) {
				$additional_messages[] = sprintf(
					/* translators: 1: APCU_DISABLED define, 2: wp-config.php */
					esc_html__(	'The APCu php extension is intalled and enabled but it is not used as %1$s is defined, probably in your  %2$s. APCu is the prefered solution for object caching.' ),
					'<code>APCU_DISABLED</code>',
					'<code>wp-config.php</code>'
				);
			} else {
				$can_cache = true;
				$result['label'] = esc_html__( 'The APCu php extension is used for persistant object caching' );
			}
		}

		// If not, check if file caching is possible
		if ( ! $can_cache ) {
			if ( wp_is_writable( \calmpress\object_cache\File::CACHE_ROOT_DIR ) ) {
				$can_cache = true;
				$result['label'] = esc_html__( 'Files are used for persistant object caching' );
				if ( $opcode_cache_usable && ! wp_is_writable( \calmpress\object_cache\PHP_File::CACHE_ROOT_DIR ) ) {
					$additional_messages[] = sprintf(
						/* translators: 1: directory path */
						esc_html__(	'Speed can be improved by making %s writable which will enable the usage of the PHP opcode caching for storing values in high demand.' ),
						'<code>' . \calmpress\object_cache\PHP_File::PHP_File . '</code>'
					);	
				}
			} else {
				$additional_messages[] = sprintf(
					/* translators: 1: directory path */
					esc_html__(	'File based object caching can not be done because the %s directory is not writable.' ),
					'<code>' . \calmpress\object_cache\File::CACHE_ROOT_DIR . '</code>'
				);
			}
		}

		if ( ! $can_cache ) {
			if ( \calmpress\object_cache\PHP_File::is_available() ) {
				$can_cache = true;
				$result['label'] = esc_html__( 'Partial object caching is available by utilizing PHP opcode caching for the most used values.' );
				$additional_messages[] = esc_html__( 'The PHP opcode caching is used for storing values in high demand, but it is not a good enough caching solution by itself for the long run.' );
			} else {
				if ( ! $opcode_cache_usable ) {
					$additional_messages[] = sprintf(
					/* translators: 1: php.ini */
					esc_html__( 'Your PHP uses opcode caching but settings in your %s file prevent this site from utilizing it for caching.' ),
						'<code>php.ini</code>'
					);
				} else {
					$additional_messages[] = sprintf(
						/* translators: 1: directory path */
						esc_html__(	'If the directory %s was writable PHP opcode caching would have been used for storing values in high demand.' ),
						'<code>' . \calmpress\object_cache\PHP_File::CACHE_ROOT_DIR . '</code>'
					);
				}
			}
		}

		if ( $can_cache ) {
			$result['status'] = 'good';
		}

		foreach ( $additional_messages as $message ) {
			$result['description'] .= '<p>' . $message . '</p>';
		}

		return $result;
	}

	/**
	 * Tests if scheduled events run as intended.
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
	 * Tests if loopbacks work as expected.
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
	 * Tests if 'file_uploads' directive in PHP.ini is turned off.
	 *
	 * @since 5.5.0
	 *
	 * @return array The test results.
	 */
	public function get_test_file_uploads() {
		$result = array(
			'label'       => __( 'Files can be uploaded' ),
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
				__( 'The "%1$s" value is smaller than "%2$s"' ),
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
			'label'       => __( 'The Authorization header is working as expected' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'The Authorization header is used by third-party applications you have approved for this site. Without this header, those apps cannot connect to your site.' )
			),
			'actions'     => '',
			'test'        => 'authorization_header',
		);

		if ( ! isset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ) ) {
			$result['label'] = __( 'The authorization header is missing' );
		} elseif ( 'user' !== $_SERVER['PHP_AUTH_USER'] || 'pwd' !== $_SERVER['PHP_AUTH_PW'] ) {
			$result['label'] = __( 'The authorization header is invalid' );
		} else {
			return $result;
		}

		$result['status']       = 'recommended';
		$result['description'] .= sprintf(
			'<p>%s</p>',
			__( 'If you are still seeing this warning after having tried the actions below, you may need to contact your hosting provider for further assistance.' )
		);

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
	 * Tests if a full page cache is available.
	 *
	 * @since 6.1.0
	 *
	 * @return array The test result.
	 */
	public function get_test_page_cache() {
		$description  = '<p>' . __( 'Page cache enhances the speed and performance of your site by saving and serving static pages instead of calling for a page every time a user visits.' ) . '</p>';
		$description .= '<p>' . __( 'Page cache is detected by looking for an active page cache plugin as well as making three requests to the homepage and looking for one or more of the following HTTP client caching response headers:' ) . '</p>';
		$description .= '<code>' . implode( '</code>, <code>', array_keys( $this->get_page_cache_headers() ) ) . '.</code>';

		$result = array(
			'badge'       => array(
				'label' => __( 'Performance' ),
				'color' => 'blue',
			),
			'description' => wp_kses_post( $description ),
			'test'        => 'page_cache',
			'status'      => 'good',
			'label'       => '',
			'actions'     => sprintf(
				'<p><a href="%1$s" target="_blank" rel="noreferrer">%2$s<span class="screen-reader-text"> %3$s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a></p>',
				__( 'https://developer.wordpress.org/advanced-administration/performance/optimization/#caching' ),
				__( 'Learn more about page cache' ),
				/* translators: Hidden accessibility text. */
				__( '(opens in a new tab)' )
			),
		);

		$page_cache_detail = $this->get_page_cache_detail();

		if ( is_wp_error( $page_cache_detail ) ) {
			$result['label']  = __( 'Unable to detect the presence of page cache' );
			$result['status'] = 'recommended';
			$error_info       = sprintf(
			/* translators: 1: Error message, 2: Error code. */
				__( 'Unable to detect page cache due to possible loopback request problem. Please verify that the loopback request test is passing. Error: %1$s (Code: %2$s)' ),
				$page_cache_detail->get_error_message(),
				$page_cache_detail->get_error_code()
			);
			$result['description'] = wp_kses_post( "<p>$error_info</p>" ) . $result['description'];
			return $result;
		}

		$result['status'] = $page_cache_detail['status'];

		switch ( $page_cache_detail['status'] ) {
			case 'recommended':
				$result['label'] = __( 'Page cache is not detected but the server response time is OK' );
				break;
			case 'good':
				$result['label'] = __( 'Page cache is detected and the server response time is good' );
				break;
			default:
				if ( empty( $page_cache_detail['headers'] ) && ! $page_cache_detail['advanced_cache_present'] ) {
					$result['label'] = __( 'Page cache is not detected and the server response time is slow' );
				} else {
					$result['label'] = __( 'Page cache is detected but the server response time is still slow' );
				}
		}

		$page_cache_test_summary = array();

		if ( empty( $page_cache_detail['response_time'] ) ) {
			$page_cache_test_summary[] = '<span class="dashicons dashicons-dismiss" aria-hidden="true"></span> ' . __( 'Server response time could not be determined. Verify that loopback requests are working.' );
		} else {

			$threshold = $this->get_good_response_time_threshold();
			if ( $page_cache_detail['response_time'] < $threshold ) {
				$page_cache_test_summary[] = '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> ' . sprintf(
					/* translators: 1: The response time in milliseconds, 2: The recommended threshold in milliseconds. */
					__( 'Median server response time was %1$s milliseconds. This is less than the recommended %2$s milliseconds threshold.' ),
					number_format_i18n( $page_cache_detail['response_time'] ),
					number_format_i18n( $threshold )
				);
			} else {
				$page_cache_test_summary[] = '<span class="dashicons dashicons-warning" aria-hidden="true"></span> ' . sprintf(
					/* translators: 1: The response time in milliseconds, 2: The recommended threshold in milliseconds. */
					__( 'Median server response time was %1$s milliseconds. It should be less than the recommended %2$s milliseconds threshold.' ),
					number_format_i18n( $page_cache_detail['response_time'] ),
					number_format_i18n( $threshold )
				);
			}

			if ( empty( $page_cache_detail['headers'] ) ) {
				$page_cache_test_summary[] = '<span class="dashicons dashicons-warning" aria-hidden="true"></span> ' . __( 'No client caching response headers were detected.' );
			} else {
				$headers_summary  = '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>';
				$headers_summary .= ' ' . sprintf(
					/* translators: %d: Number of caching headers. */
					_n(
						'There was %d client caching response header detected:',
						'There were %d client caching response headers detected:',
						count( $page_cache_detail['headers'] )
					),
					count( $page_cache_detail['headers'] )
				);
				$headers_summary          .= ' <code>' . implode( '</code>, <code>', $page_cache_detail['headers'] ) . '</code>.';
				$page_cache_test_summary[] = $headers_summary;
			}
		}

		if ( $page_cache_detail['advanced_cache_present'] ) {
			$page_cache_test_summary[] = '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> ' . __( 'A page cache plugin was detected.' );
		} elseif ( ! ( is_array( $page_cache_detail ) && ! empty( $page_cache_detail['headers'] ) ) ) {
			// Note: This message is not shown if client caching response headers were present since an external caching layer may be employed.
			$page_cache_test_summary[] = '<span class="dashicons dashicons-warning" aria-hidden="true"></span> ' . __( 'A page cache plugin was not detected.' );
		}

		$result['description'] .= '<ul><li>' . implode( '</li><li>', $page_cache_test_summary ) . '</li></ul>';
		return $result;
	}

	/**
	 * Tests if the site uses persistent object cache and recommends to use it if not.
	 *
	 * @since 6.1.0
	 *
	 * @return array The test result.
	 */
	public function get_test_persistent_object_cache() {
		/**
		 * Filters the action URL for the persistent object cache health check.
		 *
		 * @since 6.1.0
		 *
		 * @param string $action_url Learn more link for persistent object cache health check.
		 */
		$action_url = apply_filters(
			'site_status_persistent_object_cache_url',
			/* translators: Localized Support reference. */
			__( 'https://developer.wordpress.org/advanced-administration/performance/optimization/#persistent-object-cache' )
		);

		$result = array(
			'test'        => 'persistent_object_cache',
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance' ),
				'color' => 'blue',
			),
			'label'       => __( 'A persistent object cache is being used' ),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'A persistent object cache makes your site&#8217;s database more efficient, resulting in faster load times because WordPress can retrieve your site&#8217;s content and settings much more quickly.' )
			),
			'actions'     => sprintf(
				'<p><a href="%s" target="_blank">%s<span class="screen-reader-text"> %s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a></p>',
				esc_url( $action_url ),
				__( 'Learn more about persistent object caching.' ),
				/* translators: Hidden accessibility text. */
				__( '(opens in a new tab)' )
			),
		);

		if ( wp_using_ext_object_cache() ) {
			return $result;
		}

		if ( ! $this->should_suggest_persistent_object_cache() ) {
			$result['label'] = __( 'A persistent object cache is not required' );

			return $result;
		}

		$available_services = $this->available_object_cache_services();

		$notes = __( 'Your hosting provider can tell you if a persistent object cache can be enabled on your site.' );

		if ( ! empty( $available_services ) ) {
			$notes .= ' ' . sprintf(
				/* translators: Available object caching services. */
				__( 'Your host appears to support the following object caching services: %s.' ),
				implode( ', ', $available_services )
			);
		}

		/**
		 * Filters the second paragraph of the health check's description
		 * when suggesting the use of a persistent object cache.
		 *
		 * Hosts may want to replace the notes to recommend their preferred object caching solution.
		 *
		 * Plugin authors may want to append notes (not replace) on why object caching is recommended for their plugin.
		 *
		 * @since 6.1.0
		 *
		 * @param string   $notes              The notes appended to the health check description.
		 * @param string[] $available_services The list of available persistent object cache services.
		 */
		$notes = apply_filters( 'site_status_persistent_object_cache_notes', $notes, $available_services );

		$result['status']       = 'recommended';
		$result['label']        = __( 'You should use a persistent object cache' );
		$result['description'] .= sprintf(
			'<p>%s</p>',
			wp_kses(
				$notes,
				array(
					'a'      => array( 'href' => true ),
					'code'   => true,
					'em'     => true,
					'strong' => true,
				)
			)
		);

		return $result;
	}

	/**
	 * Calculates total amount of autoloaded data.
	 *
	 * @since 6.6.0
	 *
	 * @return int Autoloaded data in bytes.
	 */
	public function get_autoloaded_options_size() {
		$alloptions = wp_load_alloptions();

		$total_length = 0;

		foreach ( $alloptions as $option_value ) {
			if ( is_array( $option_value ) || is_object( $option_value ) ) {
				$option_value = maybe_serialize( $option_value );
			}
			$total_length += strlen( (string) $option_value );
		}

		return $total_length;
	}

	/**
	 * Tests the number of autoloaded options.
	 *
	 * @since 6.6.0
	 *
	 * @return array The test results.
	 */
	public function get_test_autoloaded_options() {
		$autoloaded_options_size  = $this->get_autoloaded_options_size();
		$autoloaded_options_count = count( wp_load_alloptions() );

		$base_description = __( 'Autoloaded options are configuration settings for plugins and themes that are automatically loaded with every page load in WordPress. Having too many autoloaded options can slow down your site.' );

		$result = array(
			'label'       => __( 'Autoloaded options are acceptable' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				/* translators: 1: Number of autoloaded options, 2: Autoloaded options size. */
				'<p>' . esc_html( $base_description ) . ' ' . __( 'Your site has %1$s autoloaded options (size: %2$s) in the options table, which is acceptable.' ) . '</p>',
				$autoloaded_options_count,
				size_format( $autoloaded_options_size )
			),
			'actions'     => '',
			'test'        => 'autoloaded_options',
		);

		/**
		 * Filters max bytes threshold to trigger warning in Site Health.
		 *
		 * @since 6.6.0
		 *
		 * @param int $limit Autoloaded options threshold size. Default 800000.
		 */
		$limit = apply_filters( 'site_status_autoloaded_options_size_limit', 800000 );

		if ( $autoloaded_options_size < $limit ) {
			return $result;
		}

		$result['status']      = 'critical';
		$result['label']       = __( 'Autoloaded options could affect performance' );
		$result['description'] = sprintf(
			/* translators: 1: Number of autoloaded options, 2: Autoloaded options size. */
			'<p>' . esc_html( $base_description ) . ' ' . __( 'Your site has %1$s autoloaded options (size: %2$s) in the options table, which could cause your site to be slow. You can review the options being autoloaded in your database and remove any options that are no longer needed by your site.' ) . '</p>',
			$autoloaded_options_count,
			size_format( $autoloaded_options_size )
		);

		/**
		 * Filters description to be shown on Site Health warning when threshold is met.
		 *
		 * @since 6.6.0
		 *
		 * @param string $description Description message when autoloaded options bigger than threshold.
		 */
		$result['description'] = apply_filters( 'site_status_autoloaded_options_limit_description', $result['description'] );

		$result['actions'] = sprintf(
			/* translators: 1: HelpHub URL, 2: Link description. */
			'<p><a target="_blank" href="%1$s">%2$s</a></p>',
			esc_url( __( 'https://developer.wordpress.org/advanced-administration/performance/optimization/#autoloaded-options' ) ),
			__( 'More info about optimizing autoloaded options' )
		);

		/**
		 * Filters actionable information to tackle the problem. It can be a link to an external guide.
		 *
		 * @since 6.6.0
		 *
		 * @param string $actions Call to Action to be used to point to the right direction to solve the issue.
		 */
		$result['actions'] = apply_filters( 'site_status_autoloaded_options_action_to_perform', $result['actions'] );
		return $result;
	}

	/**
	 * Tests whether search engine indexing is enabled.
	 *
	 * Surfaces as “good” if `blog_public === 1`, or “recommended” if `blog_public === 0`.
	 *
	 * @since 6.9.0
	 *
	 * @return array The test results.
	 */
	public function get_test_search_engine_visibility() {
		$result = array(
			'label'       => __( 'Search engine indexing is enabled.', 'default' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Privacy', 'default' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Search engines can crawl and index your site. No action needed.', 'default' )
			),
			'actions'     => sprintf(
				'<p><a href="%1$s">%2$s</a></p>',
				esc_url( admin_url( 'options-reading.php#blog_public' ) ),
				__( 'Review your visibility settings', 'default' )
			),
			'test'        => 'search_engine_visibility',
		);

		// If indexing is discouraged, flip to “recommended”:
		if ( ! get_option( 'blog_public' ) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Search engines are discouraged from indexing this site.', 'default' );
			$result['badge']['color'] = 'blue';
			$result['description']    = sprintf(
				'<p>%s</p>',
				__( 'Your site is hidden from search engines. Consider enabling indexing if this is a public site.', 'default' )
			);
		}

		return $result;
	}

	/**
	 * Returns a set of tests that belong to the site status page.
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
				'wordpress_version'            => array(
					'label' => __( 'calmPress Version' ),
					'test'  => 'wordpress_version',
				),
				'plugin_version'               => array(
					'label' => __( 'Plugin Versions' ),
					'test'  => 'plugin_version',
				),
				'theme_version'                => array(
					'label' => __( 'Theme Versions' ),
					'test'  => 'theme_version',
				),
				'php_version'                  => array(
					'label' => __( 'PHP Version' ),
					'test'  => 'php_version',
				),
				'php_extensions'               => array(
					'label' => __( 'PHP Extensions' ),
					'test'  => 'php_extensions',
				),
				'php_default_timezone'         => array(
					'label' => __( 'PHP Default Timezone' ),
					'test'  => 'php_default_timezone',
				),
				'php_sessions'                 => array(
					'label' => __( 'PHP Sessions' ),
					'test'  => 'php_sessions',
				),
				'sql_server'                   => array(
					'label' => __( 'Database Server version' ),
					'test'  => 'sql_server',
				),
				'ssl_support'                  => array(
					'label' => __( 'Secure communication' ),
					'test'  => 'ssl_support',
				),
				'scheduled_events'             => array(
					'label' => __( 'Scheduled events' ),
					'test'  => 'scheduled_events',
				),
				'debug_enabled'                => array(
					'label' => __( 'Debugging enabled' ),
					'test'  => 'is_in_debug_mode',
				),
				'file_uploads'                 => array(
					'label' => __( 'File uploads' ),
					'test'  => 'file_uploads',
				),
				'search_engine_visibility'     => array(
					'label' => __( 'Search Engine Visibility' ),
					'test'  => 'search_engine_visibility',
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

		// Only check for caches in production environments.
		if ( 'production' === wp_get_environment_type() ) {
			$tests['async']['page_cache'] = array(
				'label'             => __( 'Page cache' ),
				'test'              => rest_url( 'wp-site-health/v1/tests/page-cache' ),
				'has_rest'          => true,
				'async_direct_test' => array( WP_Site_Health::get_instance(), 'get_test_page_cache' ),
			);

			$tests['direct']['persistent_object_cache'] = array(
				'label' => __( 'Persistent object cache' ),
				'test'  => 'persistent_object_cache',
			);
		}

		/**
		 * Filters which site status tests are run on a site.
		 *
		 * The site health is determined by a set of tests based on best practices from
		 * both the WordPress Hosting Team and web standards in general.
		 *
		 * Some sites may not have the same requirements, for example the automatic update
		 * checks may be handled by a host, and are therefore disabled in core.
		 * Or maybe you want to introduce a new test, is caching enabled/disabled/stale for example.
		 *
		 * Tests may be added either as direct, or asynchronous ones. Any test that may require some time
		 * to complete should run asynchronously, to avoid extended loading periods within wp-admin.
		 *
		 * @since 5.2.0
		 * @since 5.6.0 Added the `async_direct_test` array key for asynchronous tests.
		 *              Added the `skip_cron` array key for all tests.
		 *
		 * @param array[] $tests {
		 *     An associative array of direct and asynchronous tests.
		 *
		 *     @type array[] $direct {
		 *         An array of direct tests.
		 *
		 *         @type array ...$identifier {
		 *             `$identifier` should be a unique identifier for the test. Plugins and themes are encouraged to
		 *             prefix test identifiers with their slug to avoid collisions between tests.
		 *
		 *             @type string   $label     The friendly label to identify the test.
		 *             @type callable $test      The callback function that runs the test and returns its result.
		 *             @type bool     $skip_cron Whether to skip this test when running as cron.
		 *         }
		 *     }
		 *     @type array[] $async {
		 *         An array of asynchronous tests.
		 *
		 *         @type array ...$identifier {
		 *             `$identifier` should be a unique identifier for the test. Plugins and themes are encouraged to
		 *             prefix test identifiers with their slug to avoid collisions between tests.
		 *
		 *             @type string   $label             The friendly label to identify the test.
		 *             @type string   $test              An admin-ajax.php action to be called to perform the test, or
		 *                                               if `$has_rest` is true, a URL to a REST API endpoint to perform
		 *                                               the test.
		 *             @type bool     $has_rest          Whether the `$test` property points to a REST API endpoint.
		 *             @type bool     $skip_cron         Whether to skip this test when running as cron.
		 *             @type callable $async_direct_test A manner of directly calling the test marked as asynchronous,
		 *                                               as the scheduled event can not authenticate, and endpoints
		 *                                               may require authentication.
		 *         }
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
	 * Adds a class to the body HTML tag.
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
	 * Initiates the WP_Cron schedule test cases.
	 *
	 * @since 5.2.0
	 */
	private function wp_schedule_test_init() {
		$this->schedules = wp_get_schedules();
		$this->get_cron_tasks();
	}

	/**
	 * Populates the list of cron events and store them to a class-wide variable.
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
	 * Checks if any scheduled tasks have been missed.
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
	 * Checks if any scheduled tasks are late.
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
	public function can_perform_loopback() {
		$body    = array( 'site-health' => 'loopback-test' );
		$cookies = wp_unslash( $_COOKIE );
		$timeout = 10; // 10 seconds.
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
	 * Creates a weekly cron event, if one does not already exist.
	 *
	 * @since 5.4.0
	 */
	public function maybe_create_scheduled_event() {
		if ( ! wp_next_scheduled( 'wp_site_health_scheduled_check' ) && ! wp_installing() ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'wp_site_health_scheduled_check' );
		}
	}

	/**
	 * Runs the scheduled event to check and update the latest site health status for the website.
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
				++$site_status['critical'];
			} elseif ( 'recommended' === $result['status'] ) {
				++$site_status['recommended'];
			} else {
				++$site_status['good'];
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

	/**
	 * Returns a list of headers and its verification callback to verify if page cache is enabled or not.
	 *
	 * Note: key is header name and value could be callable function to verify header value.
	 * Empty value mean existence of header detect page cache is enabled.
	 *
	 * @since 6.1.0
	 *
	 * @return array List of client caching headers and their (optional) verification callbacks.
	 */
	public function get_page_cache_headers() {

		$cache_hit_callback = static function ( $header_value ) {
			return str_contains( strtolower( $header_value ), 'hit' );
		};

		$cache_headers = array(
			'cache-control'          => static function ( $header_value ) {
				return (bool) preg_match( '/max-age=[1-9]/', $header_value );
			},
			'expires'                => static function ( $header_value ) {
				return strtotime( $header_value ) > time();
			},
			'age'                    => static function ( $header_value ) {
				return is_numeric( $header_value ) && $header_value > 0;
			},
			'last-modified'          => '',
			'etag'                   => '',
			'x-cache-enabled'        => static function ( $header_value ) {
				return 'true' === strtolower( $header_value );
			},
			'x-cache-disabled'       => static function ( $header_value ) {
				return ( 'on' !== strtolower( $header_value ) );
			},
			'x-srcache-store-status' => $cache_hit_callback,
			'x-srcache-fetch-status' => $cache_hit_callback,
		);

		/**
		 * Filters the list of cache headers supported by core.
		 *
		 * @since 6.1.0
		 *
		 * @param array $cache_headers Array of supported cache headers.
		 */
		return apply_filters( 'site_status_page_cache_supported_cache_headers', $cache_headers );
	}

	/**
	 * Checks if site has page cache enabled or not.
	 *
	 * @since 6.1.0
	 *
	 * @return WP_Error|array {
	 *     Page cache detection details or else error information.
	 *
	 *     @type bool    $advanced_cache_present        Whether a page cache plugin is present.
	 *     @type array[] $page_caching_response_headers Sets of client caching headers for the responses.
	 *     @type float[] $response_timing               Response timings.
	 * }
	 */
	private function check_for_page_caching() {

		/** This filter is documented in wp-includes/class-wp-http-streams.php */
		$sslverify = apply_filters( 'https_local_ssl_verify', false );

		$headers = array();

		/*
		 * Include basic auth in loopback requests. Note that this will only pass along basic auth when user is
		 * initiating the test. If a site requires basic auth, the test will fail when it runs in WP Cron as part of
		 * wp_site_health_scheduled_check. This logic is copied from WP_Site_Health::can_perform_loopback().
		 */
		if ( isset( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) {
			$headers['Authorization'] = 'Basic ' . base64_encode( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) . ':' . wp_unslash( $_SERVER['PHP_AUTH_PW'] ) );
		}

		$caching_headers               = $this->get_page_cache_headers();
		$page_caching_response_headers = array();
		$response_timing               = array();

		for ( $i = 1; $i <= 3; $i++ ) {
			$start_time    = microtime( true );
			$http_response = wp_remote_get( home_url( '/' ), compact( 'sslverify', 'headers' ) );
			$end_time      = microtime( true );

			if ( is_wp_error( $http_response ) ) {
				return $http_response;
			}
			if ( wp_remote_retrieve_response_code( $http_response ) !== 200 ) {
				return new WP_Error(
					'http_' . wp_remote_retrieve_response_code( $http_response ),
					wp_remote_retrieve_response_message( $http_response )
				);
			}

			$response_headers = array();

			foreach ( $caching_headers as $header => $callback ) {
				$header_values = wp_remote_retrieve_header( $http_response, $header );
				if ( empty( $header_values ) ) {
					continue;
				}
				$header_values = (array) $header_values;
				if ( empty( $callback ) || ( is_callable( $callback ) && count( array_filter( $header_values, $callback ) ) > 0 ) ) {
					$response_headers[ $header ] = $header_values;
				}
			}

			$page_caching_response_headers[] = $response_headers;
			$response_timing[]               = ( $end_time - $start_time ) * 1000;
		}

		return array(
			'advanced_cache_present'        => (
				file_exists( WP_CONTENT_DIR . '/advanced-cache.php' )
				&&
				( defined( 'WP_CACHE' ) && WP_CACHE )
				&&
				/** This filter is documented in wp-settings.php */
				apply_filters( 'enable_loading_advanced_cache_dropin', true )
			),
			'page_caching_response_headers' => $page_caching_response_headers,
			'response_timing'               => $response_timing,
		);
	}

	/**
	 * Gets page cache details.
	 *
	 * @since 6.1.0
	 *
	 * @return WP_Error|array {
	 *     Page cache detail or else a WP_Error if unable to determine.
	 *
	 *     @type string   $status                 Page cache status. Good, Recommended or Critical.
	 *     @type bool     $advanced_cache_present Whether page cache plugin is available or not.
	 *     @type string[] $headers                Client caching response headers detected.
	 *     @type float    $response_time          Response time of site.
	 * }
	 */
	private function get_page_cache_detail() {
		$page_cache_detail = $this->check_for_page_caching();
		if ( is_wp_error( $page_cache_detail ) ) {
			return $page_cache_detail;
		}

		// Use the median server response time.
		$response_timings = $page_cache_detail['response_timing'];
		rsort( $response_timings );
		$page_speed = $response_timings[ floor( count( $response_timings ) / 2 ) ];

		// Obtain unique set of all client caching response headers.
		$headers = array();
		foreach ( $page_cache_detail['page_caching_response_headers'] as $page_caching_response_headers ) {
			$headers = array_merge( $headers, array_keys( $page_caching_response_headers ) );
		}
		$headers = array_unique( $headers );

		// Page cache is detected if there are response headers or a page cache plugin is present.
		$has_page_caching = ( count( $headers ) > 0 || $page_cache_detail['advanced_cache_present'] );

		if ( $page_speed && $page_speed < $this->get_good_response_time_threshold() ) {
			$result = $has_page_caching ? 'good' : 'recommended';
		} else {
			$result = 'critical';
		}

		return array(
			'status'                 => $result,
			'advanced_cache_present' => $page_cache_detail['advanced_cache_present'],
			'headers'                => $headers,
			'response_time'          => $page_speed,
		);
	}

	/**
	 * Gets the threshold below which a response time is considered good.
	 *
	 * @since 6.1.0
	 *
	 * @return int Threshold in milliseconds.
	 */
	private function get_good_response_time_threshold() {
		/**
		 * Filters the threshold below which a response time is considered good.
		 *
		 * The default is based on https://web.dev/time-to-first-byte/.
		 *
		 * @since 6.1.0
		 *
		 * @param int $threshold Threshold in milliseconds. Default 600.
		 */
		return (int) apply_filters( 'site_status_good_response_time_threshold', 600 );
	}

	/**
	 * Determines whether to suggest using a persistent object cache.
	 *
	 * @since 6.1.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return bool Whether to suggest using a persistent object cache.
	 */
	public function should_suggest_persistent_object_cache() {
		global $wpdb;

		/**
		 * Filters whether to suggest use of a persistent object cache and bypass default threshold checks.
		 *
		 * Using this filter allows to override the default logic, effectively short-circuiting the method.
		 *
		 * @since 6.1.0
		 *
		 * @param bool|null $suggest Boolean to short-circuit, for whether to suggest using a persistent object cache.
		 *                           Default null.
		 */
		$short_circuit = apply_filters( 'site_status_should_suggest_persistent_object_cache', null );
		if ( is_bool( $short_circuit ) ) {
			return $short_circuit;
		}

		if ( is_multisite() ) {
			return true;
		}

		/**
		 * Filters the thresholds used to determine whether to suggest the use of a persistent object cache.
		 *
		 * @since 6.1.0
		 *
		 * @param int[] $thresholds The list of threshold numbers keyed by threshold name.
		 */
		$thresholds = apply_filters(
			'site_status_persistent_object_cache_thresholds',
			array(
				'alloptions_count' => 500,
				'alloptions_bytes' => 100000,
				'comments_count'   => 1000,
				'options_count'    => 1000,
				'posts_count'      => 1000,
				'terms_count'      => 1000,
				'users_count'      => 1000,
			)
		);

		$alloptions = wp_load_alloptions();

		if ( $thresholds['alloptions_count'] < count( $alloptions ) ) {
			return true;
		}

		if ( $thresholds['alloptions_bytes'] < strlen( serialize( $alloptions ) ) ) {
			return true;
		}

		$table_names = implode( "','", array( $wpdb->comments, $wpdb->options, $wpdb->posts, $wpdb->terms, $wpdb->users ) );

		// With InnoDB the `TABLE_ROWS` are estimates, which are accurate enough and faster to retrieve than individual `COUNT()` queries.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- This query cannot use interpolation.
				"SELECT TABLE_NAME AS 'table', TABLE_ROWS AS 'rows', SUM(data_length + index_length) as 'bytes' FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME IN ('$table_names') GROUP BY TABLE_NAME;",
				DB_NAME
			),
			OBJECT_K
		);

		$threshold_map = array(
			'comments_count' => $wpdb->comments,
			'options_count'  => $wpdb->options,
			'posts_count'    => $wpdb->posts,
			'terms_count'    => $wpdb->terms,
			'users_count'    => $wpdb->users,
		);

		foreach ( $threshold_map as $threshold => $table ) {
			if ( $thresholds[ $threshold ] <= $results[ $table ]->rows ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns a list of available persistent object cache services.
	 *
	 * @since 6.1.0
	 *
	 * @return string[] The list of available persistent object cache services.
	 */
	private function available_object_cache_services() {
		$extensions = array_map(
			'extension_loaded',
			array(
				'APCu'      => 'apcu',
				'Redis'     => 'redis',
				'Relay'     => 'relay',
				'Memcache'  => 'memcache',
				'Memcached' => 'memcached',
			)
		);

		$services = array_keys( array_filter( $extensions ) );

		/**
		 * Filters the persistent object cache services available to the user.
		 *
		 * This can be useful to hide or add services not included in the defaults.
		 *
		 * @since 6.1.0
		 *
		 * @param string[] $services The list of available persistent object cache services.
		 */
		return apply_filters( 'site_status_available_object_cache_services', $services );
	}
}
