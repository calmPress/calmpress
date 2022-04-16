<?php
/**
 * WordPress Administration Update API
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * Selects the first update version from the update_core option.
 *
 * @since 2.7.0
 *
 * @return object|array|false The response from the API on success, false on failure.
 */
function get_preferred_from_update_core() {
	$updates = get_core_updates();
	if ( ! is_array( $updates ) ) {
		return false;
	}
	if ( empty( $updates ) ) {
		return false;
	}
	return $updates[0];
}

/**
 * Gets available core updates.
 *
 * @since 2.7.0
 *
 * @param array $options Set $options['dismissed'] to true to show dismissed upgrades too,
 *                       set $options['available'] to false to skip not-dismissed updates.
 * @return array|false Array of the update objects on success, false on failure.
 *                     If there are relevant upgrades the first one (index 0) is
 *                     the most recommended one.
 */
function get_core_updates( $options = array() ) {
	$options   = array_merge(
		array(
			'available' => true,
			'dismissed' => false,
		),
		$options
	);
	$dismissed = get_site_option( 'dismissed_update_core' );

	if ( ! is_array( $dismissed ) ) {
		$dismissed = array();
	}

	$from_api = get_site_transient( 'update_core' );

	if ( ! isset( $from_api->updates ) || ! is_array( $from_api->updates ) ) {
		return false;
	}

	// Going to assume that the the first is the recommended.
	$updates = $from_api->updates;
	$result  = array();
	foreach ( $updates as $update ) {
		if ( array_key_exists( $update->version, $dismissed ) ) {
			if ( $options['dismissed'] ) {
				$update->dismissed = true;
				$result[]          = $update;
			}
		} else {
			if ( $options['available'] ) {
				$update->dismissed = false;
				$result[]          = $update;
			}
		}
	}
	return $result;
}

/**
 * @param object $update
 * @return bool
 */
function dismiss_core_update( $update ) {
	$dismissed = get_site_option( 'dismissed_update_core' );
	$dismissed[ $update->current . '|' . $update->locale ] = true;
	return update_site_option( 'dismissed_update_core', $dismissed );
}

/**
 * Undismisses core update.
 *
 * @since 2.7.0
 *
 * @param string $version
 * @param string $locale
 * @return bool
 */
function undismiss_core_update( $version, $locale ) {
	$dismissed = get_site_option( 'dismissed_update_core' );
	$key       = $version . '|' . $locale;

	if ( ! isset( $dismissed[ $key ] ) ) {
		return false;
	}

	unset( $dismissed[ $key ] );
	return update_site_option( 'dismissed_update_core', $dismissed );
}

/**
 * Finds the available update for WordPress core.
 *
 * @since 2.7.0
 *
 * @param string $version Version string to find the update for.
 * @param string $locale  Locale to find the update for.
 * @return object|false The core update offering on success, false on failure.
 */
function find_core_update( $version, $locale ) {
	$from_api = get_site_transient( 'update_core' );

	if ( ! isset( $from_api->updates ) || ! is_array( $from_api->updates ) ) {
		return false;
	}

	$updates = $from_api->updates;
	foreach ( $updates as $update ) {
		if ( $update->version == $version ) {
			return $update;
		}
	}
	return false;
}

/**
 * @since 2.3.0
 *
 * @param string $msg
 * @return string
 */
function core_update_footer( $msg = '' ) {
	$cur = get_preferred_from_update_core();

	// If no available upgrade, or user can not upgrade, just show the current version.
	if ( empty( $cur ) || ! current_user_can( 'update_core' ) ) {
		/* translators: %s: calmPress version. */
		return sprintf( __( 'Version %s' ), calmpress_version() );
	}

	$cur = get_preferred_from_update_core();
	if ( ! is_object( $cur ) ) {
		$cur = new stdClass;
	}

	if ( ! isset( $cur->current ) ) {
		$cur->current = '';
	}

	if ( ! isset( $cur->response ) ) {
		$cur->response = '';
	}

	switch ( $cur->response ) {
		case 'upgrade':
			return sprintf(
				'<strong><a href="%s">%s</a></strong>',
				network_admin_url( 'update-core.php' ),
				/* translators: %s: WordPress version. */
				sprintf( __( 'Get Version %s' ), $cur->current )
			);

		case 'latest':
		default:
			/* translators: %s: calmPress version. */
			return sprintf( __( 'Version %s' ), calmpress_version() );
	}

	// If there is possible upgrade show the link to the page which will do the upgrade.
	return '<strong><a href="' . network_admin_url( 'update-core.php' ) . '">' . sprintf( __( 'Get Version %s' ), $cur->version ) . '</a></strong>';
}

/**
 * @since 2.3.0
 *
 * @global string $pagenow
 * @return void|false
 */
function update_nag() {
	if ( is_multisite() && !current_user_can('update_core') ) {
		return;
	}

	global $pagenow;

	if ( 'update-core.php' === $pagenow ) {
		return;
	}

	$cur = get_preferred_from_update_core();

	if ( empty( $cur ) ) {
		return false;
	}

	$version_slug_parts = explode( '.', $cur->version );
	$version_slug = $version_slug_parts[0] . '-' . $version_slug_parts[1];
	if ( current_user_can( 'update_core' ) ) {
		$msg = sprintf(
			/* translators: 1: calmpress.org URL to release notes, 2: new calmPress version, 3: URL to network admin, 4: accessibility text */
			__( '<a href="%1$s">calmPress %2$s</a> is available! <a href="%3$s" aria-label="%4$s">Please update now</a>.' ),
			sprintf(
				/* translators: %s: calmPress version slug on calmpress.org */
				esc_url( __( 'https://calmpress.org/version/%s' ) ),
				$version_slug
			),
			$cur->version,
			network_admin_url( 'update-core.php' ),
			esc_attr__( 'Please update calmPress now' )
		);
	} else {
		$msg = sprintf(
			/* translators: 1: calmpress.org URL to release notes, 2: new calmPress version */
			__( '<a href="%1$s">calmPress %2$s</a> is available! Please notify the site administrator.' ),
			sprintf(
				/* translators: %s: calmPress version slug on calmpress.org */
				esc_url( __( 'https://calmpress.org/version/%s' ) ),
				$version_slug
			),
			$cur->version
		);
	}

	echo "<div class='update-nag notice notice-warning inline'>$msg</div>";
}

/**
 * Displays WordPress version and active theme in the 'At a Glance' dashboard widget.
 *
 * @since 2.5.0
 */
function update_right_now_message() {
	$theme_name = wp_get_theme();
	if ( current_user_can( 'switch_themes' ) ) {
		$theme_name = sprintf( '<a href="themes.php">%1$s</a>', $theme_name );
	}

	$msg = '';

	if ( current_user_can( 'update_core' ) ) {
		$cur = get_preferred_from_update_core();

		if ( ! empty( $cur ) ) {
			$msg .= sprintf(
				'<a href="%s" class="button" aria-describedby="wp-version">%s</a> ',
				network_admin_url( 'update-core.php' ),
				/* translators: %s: calmPress version number. */
				sprintf( __( 'Update to %s' ), $cur->current )
			);
		}
	}

	/* translators: 1: Version number, 2: Theme name. */
	$content = __( 'calmPress %1$s (based on WordPress %2$s) running %3$s theme.' );

	/**
	 * Filters the text displayed in the 'At a Glance' dashboard widget.
	 *
	 * Prior to 3.8.0, the widget was named 'Right Now'.
	 *
	 * @since 4.4.0
	 *
	 * @param string $content Default text.
	 */
	$content = apply_filters( 'update_right_now_text', $content );

	$msg .= sprintf( '<span id="wp-version">' . $content . '</span>', calmpress_version(), wordpress_core_version(), $theme_name );

	echo "<p id='wp-version-message'>$msg</p>";
}

/**
 * @since 2.9.0
 *
 * @return array
 */
function get_plugin_updates() {
	$all_plugins     = get_plugins();
	$upgrade_plugins = array();
	$current         = get_site_transient( 'update_plugins' );
	foreach ( (array) $all_plugins as $plugin_file => $plugin_data ) {
		if ( isset( $current->response[ $plugin_file ] ) ) {
			$upgrade_plugins[ $plugin_file ]         = (object) $plugin_data;
			$upgrade_plugins[ $plugin_file ]->update = $current->response[ $plugin_file ];
		}
	}

	return $upgrade_plugins;
}

/**
 * @since 2.9.0
 */
function wp_plugin_update_rows() {
	if ( ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	$plugins = get_site_transient( 'update_plugins' );
	if ( isset( $plugins->response ) && is_array( $plugins->response ) ) {
		$plugins = array_keys( $plugins->response );
		foreach ( $plugins as $plugin_file ) {
			add_action( "after_plugin_row_{$plugin_file}", 'wp_plugin_update_row', 10, 2 );
		}
	}
}

/**
 * Displays update information for a plugin.
 *
 * @since 2.3.0
 *
 * @param string $file        Plugin basename.
 * @param array  $plugin_data Plugin information.
 * @return void|false
 */
function wp_plugin_update_row( $file, $plugin_data ) {
	$current = get_site_transient( 'update_plugins' );
	if ( ! isset( $current->response[ $file ] ) ) {
		return false;
	}

	$response = $current->response[ $file ];

	$plugins_allowedtags = array(
		'a'       => array(
			'href'  => array(),
			'title' => array(),
		),
		'abbr'    => array( 'title' => array() ),
		'acronym' => array( 'title' => array() ),
		'code'    => array(),
		'em'      => array(),
		'strong'  => array(),
	);

	$plugin_name = wp_kses( $plugin_data['Name'], $plugins_allowedtags );
	$plugin_slug = isset( $response->slug ) ? $response->slug : $response->id;

	if ( isset( $response->slug ) ) {
		$details_url = self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $plugin_slug . '&section=changelog' );
	} elseif ( isset( $response->url ) ) {
		$details_url = $response->url;
	} else {
		$details_url = $plugin_data['PluginURI'];
	}

	$details_url = add_query_arg(
		array(
			'TB_iframe' => 'true',
			'width'     => 600,
			'height'    => 800,
		),
		$details_url
	);

	/** @var WP_Plugins_List_Table $wp_list_table */
	$wp_list_table = _get_list_table(
		'WP_Plugins_List_Table',
		array(
			'screen' => get_current_screen(),
		)
	);

	if ( is_network_admin() || ! is_multisite() ) {
		if ( is_network_admin() ) {
			$active_class = is_plugin_active_for_network( $file ) ? ' active' : '';
		} else {
			$active_class = is_plugin_active( $file ) ? ' active' : '';
		}

		$requires_php   = isset( $response->requires_php ) ? $response->requires_php : null;
		$compatible_php = is_php_version_compatible( $requires_php );
		$notice_type    = $compatible_php ? 'notice-warning' : 'notice-error';

		printf(
			'<tr class="plugin-update-tr%s" id="%s" data-slug="%s" data-plugin="%s">' .
			'<td colspan="%s" class="plugin-update colspanchange">' .
			'<div class="update-message notice inline %s notice-alt"><p>',
			$active_class,
			esc_attr( $plugin_slug . '-update' ),
			esc_attr( $plugin_slug ),
			esc_attr( $file ),
			esc_attr( $wp_list_table->get_column_count() ),
			$notice_type
		);

		if ( ! current_user_can( 'update_plugins' ) ) {
			printf(
				/* translators: 1: Plugin name, 2: Details URL, 3: Additional link attributes, 4: Version number. */
				__( 'There is a new version of %1$s available. <a href="%2$s" %3$s>View version %4$s details</a>.' ),
				$plugin_name,
				esc_url( $details_url ),
				sprintf(
					'class="thickbox open-plugin-details-modal" aria-label="%s"',
					/* translators: 1: Plugin name, 2: Version number. */
					esc_attr( sprintf( __( 'View %1$s version %2$s details' ), $plugin_name, $response->new_version ) )
				),
				esc_attr( $response->new_version )
			);
		} elseif ( empty( $response->package ) ) {
			printf(
				/* translators: 1: Plugin name, 2: Details URL, 3: Additional link attributes, 4: Version number. */
				__( 'There is a new version of %1$s available. <a href="%2$s" %3$s>View version %4$s details</a>. <em>Automatic update is unavailable for this plugin.</em>' ),
				$plugin_name,
				esc_url( $details_url ),
				sprintf(
					'class="thickbox open-plugin-details-modal" aria-label="%s"',
					/* translators: 1: Plugin name, 2: Version number. */
					esc_attr( sprintf( __( 'View %1$s version %2$s details' ), $plugin_name, $response->new_version ) )
				),
				esc_attr( $response->new_version )
			);
		} else {
			if ( $compatible_php ) {
				printf(
					/* translators: 1: Plugin name, 2: Details URL, 3: Additional link attributes, 4: Version number, 5: Update URL, 6: Additional link attributes. */
					__( 'There is a new version of %1$s available. <a href="%2$s" %3$s>View version %4$s details</a> or <a href="%5$s" %6$s>update now</a>.' ),
					$plugin_name,
					esc_url( $details_url ),
					sprintf(
						'class="thickbox open-plugin-details-modal" aria-label="%s"',
						/* translators: 1: Plugin name, 2: Version number. */
						esc_attr( sprintf( __( 'View %1$s version %2$s details' ), $plugin_name, $response->new_version ) )
					),
					esc_attr( $response->new_version ),
					wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $file, 'upgrade-plugin_' . $file ),
					sprintf(
						'class="update-link" aria-label="%s"',
						/* translators: %s: Plugin name. */
						esc_attr( sprintf( _x( 'Update %s now', 'plugin' ), $plugin_name ) )
					)
				);
			} else {
				printf(
					/* translators: 1: Plugin name, 2: Details URL, 3: Additional link attributes, 4: Version number 5: URL to Update PHP page. */
					__( 'There is a new version of %1$s available, but it doesn&#8217;t work with your version of PHP. <a href="%2$s" %3$s>View version %4$s details</a> or <a href="%5$s">learn more about updating PHP</a>.' ),
					$plugin_name,
					esc_url( $details_url ),
					sprintf(
						'class="thickbox open-plugin-details-modal" aria-label="%s"',
						/* translators: 1: Plugin name, 2: Version number. */
						esc_attr( sprintf( __( 'View %1$s version %2$s details' ), $plugin_name, $response->new_version ) )
					),
					esc_attr( $response->new_version ),
					esc_url( wp_get_update_php_url() )
				);
				wp_update_php_annotation( '<br><em>', '</em>' );
			}
		}

		/**
		 * Fires at the end of the update message container in each
		 * row of the plugins list table.
		 *
		 * The dynamic portion of the hook name, `$file`, refers to the path
		 * of the plugin's primary file relative to the plugins directory.
		 *
		 * @since 2.8.0
		 *
		 * @param array  $plugin_data An array of plugin metadata. See get_plugin_data()
		 *                            and the {@see 'plugin_row_meta'} filter for the list
		 *                            of possible values.
		 * @param object $response {
		 *     An object of metadata about the available plugin update.
		 *
		 *     @type string   $id           Plugin ID, e.g. `w.org/plugins/[plugin-name]`.
		 *     @type string   $slug         Plugin slug.
		 *     @type string   $plugin       Plugin basename.
		 *     @type string   $new_version  New plugin version.
		 *     @type string   $url          Plugin URL.
		 *     @type string   $package      Plugin update package URL.
		 *     @type string[] $icons        An array of plugin icon URLs.
		 *     @type string[] $banners      An array of plugin banner URLs.
		 *     @type string[] $banners_rtl  An array of plugin RTL banner URLs.
		 *     @type string   $requires     The version of WordPress which the plugin requires.
		 *     @type string   $tested       The version of WordPress the plugin is tested against.
		 *     @type string   $requires_php The version of PHP which the plugin requires.
		 * }
		 */
		do_action( "in_plugin_update_message-{$file}", $plugin_data, $response ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		echo '</p></div></td></tr>';
	}
}

/**
 * @since 2.9.0
 *
 * @return array
 */
function get_theme_updates() {
	$current = get_site_transient( 'update_themes' );

	if ( ! isset( $current->response ) ) {
		return array();
	}

	$update_themes = array();
	foreach ( $current->response as $stylesheet => $data ) {
		$update_themes[ $stylesheet ]         = wp_get_theme( $stylesheet );
		$update_themes[ $stylesheet ]->update = $data;
	}

	return $update_themes;
}

/**
 * @since 3.1.0
 */
function wp_theme_update_rows() {
	if ( ! current_user_can( 'update_themes' ) ) {
		return;
	}

	$themes = get_site_transient( 'update_themes' );
	if ( isset( $themes->response ) && is_array( $themes->response ) ) {
		$themes = array_keys( $themes->response );

		foreach ( $themes as $theme ) {
			add_action( "after_theme_row_{$theme}", 'wp_theme_update_row', 10, 2 );
		}
	}
}

/**
 * Displays update information for a theme.
 *
 * @since 3.1.0
 *
 * @param string   $theme_key Theme stylesheet.
 * @param WP_Theme $theme     Theme object.
 * @return void|false
 */
function wp_theme_update_row( $theme_key, $theme ) {
	$current = get_site_transient( 'update_themes' );

	if ( ! isset( $current->response[ $theme_key ] ) ) {
		return false;
	}

	$response = $current->response[ $theme_key ];

	$details_url = add_query_arg(
		array(
			'TB_iframe' => 'true',
			'width'     => 1024,
			'height'    => 800,
		),
		$current->response[ $theme_key ]['url']
	);

	/** @var WP_MS_Themes_List_Table $wp_list_table */
	$wp_list_table = _get_list_table( 'WP_MS_Themes_List_Table' );

	$active = $theme->is_allowed( 'network' ) ? ' active' : '';

	$requires_wp  = isset( $response['requires'] ) ? $response['requires'] : null;
	$requires_php = isset( $response['requires_php'] ) ? $response['requires_php'] : null;

	$compatible_wp  = is_wp_version_compatible( $requires_wp );
	$compatible_php = is_php_version_compatible( $requires_php );

	printf(
		'<tr class="plugin-update-tr%s" id="%s" data-slug="%s">' .
		'<td colspan="%s" class="plugin-update colspanchange">' .
		'<div class="update-message notice inline notice-warning notice-alt"><p>',
		$active,
		esc_attr( $theme->get_stylesheet() . '-update' ),
		esc_attr( $theme->get_stylesheet() ),
		$wp_list_table->get_column_count()
	);

	if ( $compatible_wp && $compatible_php ) {
		if ( ! current_user_can( 'update_themes' ) ) {
			printf(
				/* translators: 1: Theme name, 2: Details URL, 3: Additional link attributes, 4: Version number. */
				__( 'There is a new version of %1$s available. <a href="%2$s" %3$s>View version %4$s details</a>.' ),
				$theme['Name'],
				esc_url( $details_url ),
				sprintf(
					'class="thickbox open-plugin-details-modal" aria-label="%s"',
					/* translators: 1: Theme name, 2: Version number. */
					esc_attr( sprintf( __( 'View %1$s version %2$s details' ), $theme['Name'], $response['new_version'] ) )
				),
				$response['new_version']
			);
		} elseif ( empty( $response['package'] ) ) {
			printf(
				/* translators: 1: Theme name, 2: Details URL, 3: Additional link attributes, 4: Version number. */
				__( 'There is a new version of %1$s available. <a href="%2$s" %3$s>View version %4$s details</a>. <em>Automatic update is unavailable for this theme.</em>' ),
				$theme['Name'],
				esc_url( $details_url ),
				sprintf(
					'class="thickbox open-plugin-details-modal" aria-label="%s"',
					/* translators: 1: Theme name, 2: Version number. */
					esc_attr( sprintf( __( 'View %1$s version %2$s details' ), $theme['Name'], $response['new_version'] ) )
				),
				$response['new_version']
			);
		} else {
			printf(
				/* translators: 1: Theme name, 2: Details URL, 3: Additional link attributes, 4: Version number, 5: Update URL, 6: Additional link attributes. */
				__( 'There is a new version of %1$s available. <a href="%2$s" %3$s>View version %4$s details</a> or <a href="%5$s" %6$s>update now</a>.' ),
				$theme['Name'],
				esc_url( $details_url ),
				sprintf(
					'class="thickbox open-plugin-details-modal" aria-label="%s"',
					/* translators: 1: Theme name, 2: Version number. */
					esc_attr( sprintf( __( 'View %1$s version %2$s details' ), $theme['Name'], $response['new_version'] ) )
				),
				$response['new_version'],
				wp_nonce_url( self_admin_url( 'update.php?action=upgrade-theme&theme=' ) . $theme_key, 'upgrade-theme_' . $theme_key ),
				sprintf(
					'class="update-link" aria-label="%s"',
					/* translators: %s: Theme name. */
					esc_attr( sprintf( _x( 'Update %s now', 'theme' ), $theme['Name'] ) )
				)
			);
		}
	} else {
		if ( ! $compatible_wp && ! $compatible_php ) {
			printf(
				/* translators: %s: Theme name. */
				__( 'There is a new version of %s available, but it doesn&#8217;t work with your versions of WordPress and PHP.' ),
				$theme['Name']
			);
			if ( current_user_can( 'update_core' ) && current_user_can( 'update_php' ) ) {
				printf(
					/* translators: 1: URL to WordPress Updates screen, 2: URL to Update PHP page. */
					' ' . __( '<a href="%1$s">Please update WordPress</a>, and then <a href="%2$s">learn more about updating PHP</a>.' ),
					self_admin_url( 'update-core.php' ),
					esc_url( wp_get_update_php_url() )
				);
				wp_update_php_annotation( '</p><p><em>', '</em>' );
			} elseif ( current_user_can( 'update_core' ) ) {
				printf(
					/* translators: %s: URL to WordPress Updates screen. */
					' ' . __( '<a href="%s">Please update WordPress</a>.' ),
					self_admin_url( 'update-core.php' )
				);
			} elseif ( current_user_can( 'update_php' ) ) {
				printf(
					/* translators: %s: URL to Update PHP page. */
					' ' . __( '<a href="%s">Learn more about updating PHP</a>.' ),
					esc_url( wp_get_update_php_url() )
				);
				wp_update_php_annotation( '</p><p><em>', '</em>' );
			}
		} elseif ( ! $compatible_wp ) {
			printf(
				/* translators: %s: Theme name. */
				__( 'There is a new version of %s available, but it doesn&#8217;t work with your version of WordPress.' ),
				$theme['Name']
			);
			if ( current_user_can( 'update_core' ) ) {
				printf(
					/* translators: %s: URL to WordPress Updates screen. */
					' ' . __( '<a href="%s">Please update WordPress</a>.' ),
					self_admin_url( 'update-core.php' )
				);
			}
		} elseif ( ! $compatible_php ) {
			printf(
				/* translators: %s: Theme name. */
				__( 'There is a new version of %s available, but it doesn&#8217;t work with your version of PHP.' ),
				$theme['Name']
			);
			if ( current_user_can( 'update_php' ) ) {
				printf(
					/* translators: %s: URL to Update PHP page. */
					' ' . __( '<a href="%s">Learn more about updating PHP</a>.' ),
					esc_url( wp_get_update_php_url() )
				);
				wp_update_php_annotation( '</p><p><em>', '</em>' );
			}
		}
	}

	/**
	 * Fires at the end of the update message container in each
	 * row of the themes list table.
	 *
	 * The dynamic portion of the hook name, `$theme_key`, refers to
	 * the theme slug as found in the WordPress.org themes repository.
	 *
	 * @since 3.1.0
	 *
	 * @param WP_Theme $theme    The WP_Theme object.
	 * @param array    $response {
	 *     An array of metadata about the available theme update.
	 *
	 *     @type string $new_version New theme version.
	 *     @type string $url         Theme URL.
	 *     @type string $package     Theme update package URL.
	 * }
	 */
	do_action( "in_theme_update_message-{$theme_key}", $theme, $response ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

	echo '</p></div></td></tr>';
}

/**
 * Prints the JavaScript templates for update admin notices.
 *
 * Template takes one argument with four values:
 *
 *     param {object} data {
 *         Arguments for admin notice.
 *
 *         @type string id        ID of the notice.
 *         @type string className Class names for the notice.
 *         @type string message   The notice's message.
 *         @type string type      The type of update the notice is for. Either 'plugin' or 'theme'.
 *     }
 *
 * @since 4.6.0
 */
function wp_print_admin_notice_templates() {
	?>
	<script id="tmpl-wp-updates-admin-notice" type="text/html">
		<div <# if ( data.id ) { #>id="{{ data.id }}"<# } #> class="notice {{ data.className }}"><p>{{{ data.message }}}</p></div>
	</script>
	<script id="tmpl-wp-bulk-updates-admin-notice" type="text/html">
		<div id="{{ data.id }}" class="{{ data.className }} notice <# if ( data.errors ) { #>notice-error<# } else { #>notice-success<# } #>">
			<p>
				<# if ( data.successes ) { #>
					<# if ( 1 === data.successes ) { #>
						<# if ( 'plugin' === data.type ) { #>
							<?php
							/* translators: %s: Number of plugins. */
							printf( __( '%s plugin successfully updated.' ), '{{ data.successes }}' );
							?>
						<# } else { #>
							<?php
							/* translators: %s: Number of themes. */
							printf( __( '%s theme successfully updated.' ), '{{ data.successes }}' );
							?>
						<# } #>
					<# } else { #>
						<# if ( 'plugin' === data.type ) { #>
							<?php
							/* translators: %s: Number of plugins. */
							printf( __( '%s plugins successfully updated.' ), '{{ data.successes }}' );
							?>
						<# } else { #>
							<?php
							/* translators: %s: Number of themes. */
							printf( __( '%s themes successfully updated.' ), '{{ data.successes }}' );
							?>
						<# } #>
					<# } #>
				<# } #>
				<# if ( data.errors ) { #>
					<button class="button-link bulk-action-errors-collapsed" aria-expanded="false">
						<# if ( 1 === data.errors ) { #>
							<?php
							/* translators: %s: Number of failed updates. */
							printf( __( '%s update failed.' ), '{{ data.errors }}' );
							?>
						<# } else { #>
							<?php
							/* translators: %s: Number of failed updates. */
							printf( __( '%s updates failed.' ), '{{ data.errors }}' );
							?>
						<# } #>
						<span class="screen-reader-text"><?php _e( 'Show more details' ); ?></span>
						<span class="toggle-indicator" aria-hidden="true"></span>
					</button>
				<# } #>
			</p>
			<# if ( data.errors ) { #>
				<ul class="bulk-action-errors hidden">
					<# _.each( data.errorMessages, function( errorMessage ) { #>
						<li>{{ errorMessage }}</li>
					<# } ); #>
				</ul>
			<# } #>
		</div>
	</script>
	<?php
}

/**
 * Prints the JavaScript templates for update and deletion rows in list tables.
 *
 * The update template takes one argument with four values:
 *
 *     param {object} data {
 *         Arguments for the update row
 *
 *         @type string slug    Plugin slug.
 *         @type string plugin  Plugin base name.
 *         @type string colspan The number of table columns this row spans.
 *         @type string content The row content.
 *     }
 *
 * The delete template takes one argument with four values:
 *
 *     param {object} data {
 *         Arguments for the update row
 *
 *         @type string slug    Plugin slug.
 *         @type string plugin  Plugin base name.
 *         @type string name    Plugin name.
 *         @type string colspan The number of table columns this row spans.
 *     }
 *
 * @since 4.6.0
 */
function wp_print_update_row_templates() {
	?>
	<script id="tmpl-item-update-row" type="text/template">
		<tr class="plugin-update-tr update" id="{{ data.slug }}-update" data-slug="{{ data.slug }}" <# if ( data.plugin ) { #>data-plugin="{{ data.plugin }}"<# } #>>
			<td colspan="{{ data.colspan }}" class="plugin-update colspanchange">
				{{{ data.content }}}
			</td>
		</tr>
	</script>
	<script id="tmpl-item-deleted-row" type="text/template">
		<tr class="plugin-deleted-tr inactive deleted" id="{{ data.slug }}-deleted" data-slug="{{ data.slug }}" <# if ( data.plugin ) { #>data-plugin="{{ data.plugin }}"<# } #>>
			<td colspan="{{ data.colspan }}" class="plugin-update colspanchange">
				<# if ( data.plugin ) { #>
					<?php
					printf(
						/* translators: %s: Plugin name. */
						_x( '%s was successfully deleted.', 'plugin' ),
						'<strong>{{{ data.name }}}</strong>'
					);
					?>
				<# } else { #>
					<?php
					printf(
						/* translators: %s: Theme name. */
						_x( '%s was successfully deleted.', 'theme' ),
						'<strong>{{{ data.name }}}</strong>'
					);
					?>
				<# } #>
			</td>
		</tr>
	</script>
	<?php
}
