<?php
/**
 * Misc WordPress Administration API.
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * Returns whether the server is running Apache with the mod_rewrite module loaded.
 * For calmPress it is an alias to detecting with an apache server is being used.
 *
 * @since 2.0.0
 * @since calmPress 1.0.0
 *
 * @return bool True if the server is Apache, false if it is not.
 */
function got_mod_rewrite() {
	$got_rewrite = apache_mod_loaded('mod_rewrite', true);
	return $got_rewrite;
}

/**
 * Extracts strings from between the BEGIN and END markers in the .htaccess file.
 *
 * @since 1.5.0
 *
 * @param string $filename Filename to extract the strings from.
 * @param string $marker   The marker to extract the strings from.
 * @return string[] An array of strings from a file (.htaccess) from between BEGIN and END markers.
 */
function extract_from_markers( $filename, $marker ) {
	$result = array();

	if ( ! file_exists( $filename ) ) {
		return $result;
	}

	$markerdata = explode( "\n", implode( '', file( $filename ) ) );

	$state = false;
	foreach ( $markerdata as $markerline ) {
		if ( false !== strpos( $markerline, '# END ' . $marker ) ) {
			$state = false;
		}
		if ( $state ) {
			if ( '#' === substr( $markerline, 0, 1 ) ) {
				continue;
			}
			$result[] = $markerline;
		}
		if ( false !== strpos( $markerline, '# BEGIN ' . $marker ) ) {
			$state = true;
		}
	}

	return $result;
}

/**
 * Inserts an array of strings into an array of strings, placing it between
 * htaccess style comments of # BEGIN and # END marker lines in the original array.
 *
 * Replaces existing marked info. Retains surrounding data. If the data is empty
 * removes the markers as well as the data.
 *
 * @since calmPress 1.0.0
 *
 * @param string[] $lines     Array to alter.
 * @param string   $marker    The marker.
 * @param string[] $insertion The new content to insert.
 *
 * @return string[] The original array with the relevant "section" replaced.
 */
function insert_with_markers_into_array( array $lines, string $marker, array $insertion ) {
	$start_marker = "# BEGIN {$marker}";
	$end_marker   = "# END {$marker}";

	// Split out the existing file into the preceding lines, and those that appear after the marker.
	$pre_lines        = [];
	$post_lines       = [];
	$existing_lines   = [];
	$found_marker     = false;
	$found_end_marker = false;

	foreach ( $lines as $line ) {
		if ( ! $found_marker && false !== strpos( $line, $start_marker ) ) {
			$found_marker = true;
			continue;
		} elseif ( ! $found_end_marker && false !== strpos( $line, $end_marker ) ) {
			$found_end_marker = true;
			continue;
		}
		if ( ! $found_marker ) {
			$pre_lines[] = $line;
		} elseif ( $found_marker && $found_end_marker ) {
			$post_lines[] = $line;
		} else {
			$existing_lines[] = $line;
		}
	}

	if ( empty( $insertion ) ) {
		$lines = array_merge(
			$pre_lines,
			$post_lines
		);
	} else {
		$lines = array_merge(
			$pre_lines,
			array( $start_marker ),
			$insertion,
			array( $end_marker ),
			$post_lines
		);
	}

	return $lines;
}

/**
 * Inserts an array of strings into a file (.htaccess ), placing it between
 * BEGIN and END markers.
 *
 * Replaces existing marked info. Retains surrounding
 * data. Creates file if none exists.
 *
 * If saving of the file fails, an action calm_insert_with_markers_exception
 * which includes failure information is run.
 *
 * @since 1.5.0
 * @since calmPress 1.0.0
 *
 * @param string       $filename  Filename to alter.
 * @param string       $marker    The marker to alter.
 * @param array|string $insertion The new content to insert.
 * @return bool True on write success, false on failure.
 */
function insert_with_markers( $filename, $marker, $insertion ) {

	// Get a callable which will be used to get the relevant locked file object.
	/**
	 * Get a function to call (AKA callable) to create a locked file access object
	 * to be able to lock the file while accessing.
	 * The default is a function that creates a direct access file lock object.
	 *
	 * The function signature should be
	 * function ( string $filename ) : \calmpress\filesystem\Locked_File_Direct_Access
	 *
	 * It will accept a file path and return a locked object for it.
	 *
	 * @since calmPress 1.0.0
	 */
	$getter = apply_filters('calm_insert_with_markers_locked_file_getter', function ( $filename ) {
		return new \calmpress\filesystem\Locked_File_Direct_Access( $filename );
	} );

	// Use the callable to get the locked file object.
	$file = $getter( $filename );

	if ( ! $file instanceof \calmpress\filesystem\Locked_File_Access ) {
		// Report an error, as the getter returned a bad object.
		trigger_error( 'Failed in getting a locked file object', E_USER_ERROR );
		return false;
	}

	if ( ! is_array( $insertion ) ) {
		$insertion = explode( "\n", $insertion );
	}

	try {
		$current = $file->get_contents();

		// Split the content to lines based on all possible line endings.
		$lines = preg_split( "/\r\n|\n|\r/", $current );

		$newlines = insert_with_markers_into_array( $lines, $marker, $insertion );
		// Check to see if there was a change.
		if ( $lines === $newlines ) {
			return true;
		}

		// Generate the new file data.
		$new_file_data = implode( "\n", $newlines );
		$file->put_contents( $new_file_data );
	} catch ( \calmpress\filesystem\Locked_File_Exception $exception ) {
		/**
		 * Notify listeners that the file manipulation failed with the exception
		 * which was reported by the lower level functions.
		 *
		 * @since calmPress 1.0.0
		 *
		 * @param \calmpress\filesystem\Locked_File_Exceptio $exception The exception raised.
		 */
		do_action( 'calm_insert_with_markers_exception', $exception );
		return false;
	} catch ( \Exception $exception ) {
		// For backward compatability, ignore other types of errors as the original
		// WordPress function did not do more than returning false on failure.
		return false;
	}

	return true;
}

/**
 * Updates the htaccess file with the current rules if it is writable.
 *
 * Always writes to the file if it exists and is writable to ensure that we
 * blank out old rules.
 *
 * @since 1.5.0
 *
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 *
 * @return bool|null True on write success, false on failure. Null in multisite.
 */
function save_mod_rewrite_rules() {
	if ( is_multisite() ) {
		return;
	}

	global $wp_rewrite;

	// Ensure get_home_path() is declared.
	require_once ABSPATH . 'wp-admin/includes/file.php';

	$home_path     = get_home_path();
	$htaccess_file = $home_path . '.htaccess';

	// Check the webserver is apache before trying to save .htaccess.
	if ( got_mod_rewrite() ) {
		$rules = explode( "\n", $wp_rewrite->mod_rewrite_rules() );
		return insert_with_markers( $htaccess_file, 'WordPress', $rules );
	}

	return false;
}

/**
 * Updates the IIS web.config file with the current rules if it is writable.
 * If the permalinks do not require rewrite rules then the rules are deleted from the web.config file.
 *
 * @since 2.8.0
 *
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 *
 * @return bool|null True on write success, false on failure. Null in multisite.
 */
function iis7_save_url_rewrite_rules() {
	if ( is_multisite() ) {
		return;
	}

	global $wp_rewrite;

	// Ensure get_home_path() is declared.
	require_once ABSPATH . 'wp-admin/includes/file.php';

	$home_path       = get_home_path();
	$web_config_file = $home_path . 'web.config';

	// Using win_is_writable() instead of is_writable() because of a bug in Windows PHP.
	if ( iis7_supports_permalinks() && ( ( ! file_exists( $web_config_file ) && win_is_writable( $home_path ) ) || win_is_writable( $web_config_file ) ) ) {
		$rule = $wp_rewrite->iis7_url_rewrite_rules( false );
		if ( ! empty( $rule ) ) {
			return iis7_add_rewrite_rule( $web_config_file, $rule );
		} else {
			return iis7_delete_rewrite_rule( $web_config_file );
		}
	}
	return false;
}

/**
 * Flushes rewrite rules if siteurl, home or page_on_front changed.
 *
 * @since 2.1.0
 *
 * @param string $old_value
 * @param string $value
 */
function update_home_siteurl( $old_value, $value ) {
	if ( wp_installing() ) {
		return;
	}

	if ( is_multisite() && ms_is_switched() ) {
		delete_option( 'rewrite_rules' );
	} else {
		flush_rewrite_rules();
	}
}


/**
 * Resets global variables based on $_GET and $_POST
 *
 * This function resets global variables based on the names passed
 * in the $vars array to the value of $_POST[$var] or $_GET[$var] or ''
 * if neither is defined.
 *
 * @since 2.0.0
 *
 * @param array $vars An array of globals to reset.
 */
function wp_reset_vars( $vars ) {
	foreach ( $vars as $var ) {
		if ( empty( $_POST[ $var ] ) ) {
			if ( empty( $_GET[ $var ] ) ) {
				$GLOBALS[ $var ] = '';
			} else {
				$GLOBALS[ $var ] = $_GET[ $var ];
			}
		} else {
			$GLOBALS[ $var ] = $_POST[ $var ];
		}
	}
}

/**
 * Displays the given administration message.
 *
 * @since 2.1.0
 *
 * @param string|WP_Error $message
 */
function show_message( $message ) {
	if ( is_wp_error( $message ) ) {
		if ( $message->get_error_data() && is_string( $message->get_error_data() ) ) {
			$message = $message->get_error_message() . ': ' . $message->get_error_data();
		} else {
			$message = $message->get_error_message();
		}
	}
	echo "<p>$message</p>\n";
	wp_ob_end_flush_all();
	flush();
}

/**
 * Saves option for number of rows when listing posts, pages, comments, etc.
 *
 * @since 2.8.0
 */
function set_screen_options() {

	if ( isset( $_POST['wp_screen_options'] ) && is_array( $_POST['wp_screen_options'] ) ) {
		check_admin_referer( 'screen-options-nonce', 'screenoptionnonce' );

		$user = wp_get_current_user();
		if ( ! $user ) {
			return;
		}
		$option = $_POST['wp_screen_options']['option'];
		$value  = $_POST['wp_screen_options']['value'];

		if ( sanitize_key( $option ) != $option ) {
			return;
		}

		$map_option = $option;
		$type       = str_replace( 'edit_', '', $map_option );
		$type       = str_replace( '_per_page', '', $type );
		if ( in_array( $type, get_taxonomies() ) ) {
			$map_option = 'edit_tags_per_page';
		} elseif ( in_array( $type, get_post_types() ) ) {
			$map_option = 'edit_per_page';
		} else {
			$option = str_replace( '-', '_', $option );
		}

		switch ( $map_option ) {
			case 'edit_per_page':
			case 'users_per_page':
			case 'edit_comments_per_page':
			case 'upload_per_page':
			case 'edit_tags_per_page':
			case 'plugins_per_page':
			case 'export_personal_data_requests_per_page':
			case 'remove_personal_data_requests_per_page':
				// Network admin.
			case 'sites_network_per_page':
			case 'users_network_per_page':
			case 'site_users_network_per_page':
			case 'plugins_network_per_page':
			case 'themes_network_per_page':
			case 'site_themes_network_per_page':
				$value = (int) $value;
				if ( $value < 1 || $value > 999 ) {
					return;
				}
				break;
			default:
				/**
				 * Filters a screen option value before it is set.
				 *
				 * The filter can also be used to modify non-standard [items]_per_page
				 * settings. See the parent function for a full list of standard options.
				 *
				 * Returning false to the filter will skip saving the current option.
				 *
				 * @since 2.8.0
				 *
				 * @see set_screen_options()
				 *
				 * @param bool     $keep   Whether to save or skip saving the screen option value. Default false.
				 * @param string   $option The option name.
				 * @param int      $value  The number of rows to use.
				 */
				$value = apply_filters( 'set-screen-option', false, $option, $value ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

				if ( false === $value ) {
					return;
				}
				break;
		}

		update_user_meta( $user->ID, $option, $value );

		$url = remove_query_arg( array( 'pagenum', 'apage', 'paged' ), wp_get_referer() );
		if ( isset( $_POST['mode'] ) ) {
			$url = add_query_arg( array( 'mode' => $_POST['mode'] ), $url );
		}

		wp_safe_redirect( $url );
		exit;
	}
}

/**
 * Check if rewrite rule for WordPress already exists in the IIS 7+ configuration file
 *
 * @since 2.8.0
 *
 * @return bool
 * @param string $filename The file path to the configuration file
 */
function iis7_rewrite_rule_exists( $filename ) {
	if ( ! file_exists( $filename ) ) {
		return false;
	}
	if ( ! class_exists( 'DOMDocument', false ) ) {
		return false;
	}

	$doc = new DOMDocument();
	if ( $doc->load( $filename ) === false ) {
		return false;
	}
	$xpath = new DOMXPath( $doc );
	$rules = $xpath->query( '/configuration/system.webServer/rewrite/rules/rule[starts-with(@name,\'wordpress\')] | /configuration/system.webServer/rewrite/rules/rule[starts-with(@name,\'WordPress\')]' );
	if ( 0 == $rules->length ) {
		return false;
	} else {
		return true;
	}
}

/**
 * Delete WordPress rewrite rule from web.config file if it exists there
 *
 * @since 2.8.0
 *
 * @param string $filename Name of the configuration file
 * @return bool
 */
function iis7_delete_rewrite_rule( $filename ) {
	// If configuration file does not exist then rules also do not exist, so there is nothing to delete.
	if ( ! file_exists( $filename ) ) {
		return true;
	}

	if ( ! class_exists( 'DOMDocument', false ) ) {
		return false;
	}

	$doc                     = new DOMDocument();
	$doc->preserveWhiteSpace = false;

	if ( $doc->load( $filename ) === false ) {
		return false;
	}
	$xpath = new DOMXPath( $doc );
	$rules = $xpath->query( '/configuration/system.webServer/rewrite/rules/rule[starts-with(@name,\'wordpress\')] | /configuration/system.webServer/rewrite/rules/rule[starts-with(@name,\'WordPress\')]' );
	if ( $rules->length > 0 ) {
		$child  = $rules->item( 0 );
		$parent = $child->parentNode;
		$parent->removeChild( $child );
		$doc->formatOutput = true;
		saveDomDocument( $doc, $filename );
	}
	return true;
}

/**
 * Add WordPress rewrite rule to the IIS 7+ configuration file.
 *
 * @since 2.8.0
 *
 * @param string $filename The file path to the configuration file
 * @param string $rewrite_rule The XML fragment with URL Rewrite rule
 * @return bool
 */
function iis7_add_rewrite_rule( $filename, $rewrite_rule ) {
	if ( ! class_exists( 'DOMDocument', false ) ) {
		return false;
	}

	// If configuration file does not exist then we create one.
	if ( ! file_exists( $filename ) ) {
		$fp = fopen( $filename, 'w' );
		fwrite( $fp, '<configuration/>' );
		fclose( $fp );
	}

	$doc                     = new DOMDocument();
	$doc->preserveWhiteSpace = false;

	if ( $doc->load( $filename ) === false ) {
		return false;
	}

	$xpath = new DOMXPath( $doc );

	// First check if the rule already exists as in that case there is no need to re-add it.
	$wordpress_rules = $xpath->query( '/configuration/system.webServer/rewrite/rules/rule[starts-with(@name,\'wordpress\')] | /configuration/system.webServer/rewrite/rules/rule[starts-with(@name,\'WordPress\')]' );
	if ( $wordpress_rules->length > 0 ) {
		return true;
	}

	// Check the XPath to the rewrite rule and create XML nodes if they do not exist.
	$xmlnodes = $xpath->query( '/configuration/system.webServer/rewrite/rules' );
	if ( $xmlnodes->length > 0 ) {
		$rules_node = $xmlnodes->item( 0 );
	} else {
		$rules_node = $doc->createElement( 'rules' );

		$xmlnodes = $xpath->query( '/configuration/system.webServer/rewrite' );
		if ( $xmlnodes->length > 0 ) {
			$rewrite_node = $xmlnodes->item( 0 );
			$rewrite_node->appendChild( $rules_node );
		} else {
			$rewrite_node = $doc->createElement( 'rewrite' );
			$rewrite_node->appendChild( $rules_node );

			$xmlnodes = $xpath->query( '/configuration/system.webServer' );
			if ( $xmlnodes->length > 0 ) {
				$system_webServer_node = $xmlnodes->item( 0 );
				$system_webServer_node->appendChild( $rewrite_node );
			} else {
				$system_webServer_node = $doc->createElement( 'system.webServer' );
				$system_webServer_node->appendChild( $rewrite_node );

				$xmlnodes = $xpath->query( '/configuration' );
				if ( $xmlnodes->length > 0 ) {
					$config_node = $xmlnodes->item( 0 );
					$config_node->appendChild( $system_webServer_node );
				} else {
					$config_node = $doc->createElement( 'configuration' );
					$doc->appendChild( $config_node );
					$config_node->appendChild( $system_webServer_node );
				}
			}
		}
	}

	$rule_fragment = $doc->createDocumentFragment();
	$rule_fragment->appendXML( $rewrite_rule );
	$rules_node->appendChild( $rule_fragment );

	$doc->encoding     = 'UTF-8';
	$doc->formatOutput = true;
	saveDomDocument( $doc, $filename );

	return true;
}

/**
 * Saves the XML document into a file
 *
 * @since 2.8.0
 *
 * @param DOMDocument $doc
 * @param string $filename
 */
function saveDomDocument( $doc, $filename ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	$config = $doc->saveXML();
	$config = preg_replace( "/([^\r])\n/", "$1\r\n", $config );
	$fp     = fopen( $filename, 'w' );
	fwrite( $fp, $config );
	fclose( $fp );
}

/**
 * Display the default admin color scheme picker (Used in user-edit.php)
 *
 * @since 3.0.0
 *
 * @global array $_wp_admin_css_colors
 *
 * @param int $user_id User ID.
 */
function admin_color_scheme_picker( $user_id ) {
	global $_wp_admin_css_colors;

	ksort( $_wp_admin_css_colors );

	if ( isset( $_wp_admin_css_colors['fresh'] ) ) {
		// Set Default ('fresh') and Light should go first.
		$_wp_admin_css_colors = array_filter(
			array_merge(
				array(
					'fresh' => '',
					'light' => '',
				),
				$_wp_admin_css_colors
			)
		);
	}

	$current_color = get_user_option( 'admin_color', $user_id );

	if ( empty( $current_color ) || ! isset( $_wp_admin_css_colors[ $current_color ] ) ) {
		$current_color = 'fresh';
	}

	?>
	<fieldset id="color-picker" class="scheme-list">
		<legend class="screen-reader-text"><span><?php _e( 'Admin Color Scheme' ); ?></span></legend>
		<?php
		wp_nonce_field( 'save-color-scheme', 'color-nonce', false );
		foreach ( $_wp_admin_css_colors as $color => $color_info ) :

			?>
			<div class="color-option <?php echo ( $color == $current_color ) ? 'selected' : ''; ?>">
				<input name="admin_color" id="admin_color_<?php echo esc_attr( $color ); ?>" type="radio" value="<?php echo esc_attr( $color ); ?>" class="tog" <?php checked( $color, $current_color ); ?> />
				<input type="hidden" class="css_url" value="<?php echo esc_url( $color_info->url ); ?>" />
				<input type="hidden" class="icon_colors" value="<?php echo esc_attr( wp_json_encode( array( 'icons' => $color_info->icon_colors ) ) ); ?>" />
				<label for="admin_color_<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $color_info->name ); ?></label>
				<table class="color-palette">
					<tr>
					<?php

					foreach ( $color_info->colors as $html_color ) {
						?>
						<td style="background-color: <?php echo esc_attr( $html_color ); ?>">&nbsp;</td>
						<?php
					}

					?>
					</tr>
				</table>
			</div>
			<?php

		endforeach;

		?>
	</fieldset>
	<?php
}

/**
 *
 * @global array $_wp_admin_css_colors
 */
function wp_color_scheme_settings() {
	global $_wp_admin_css_colors;

	$color_scheme = get_user_option( 'admin_color' );

	// It's possible to have a color scheme set that is no longer registered.
	if ( empty( $_wp_admin_css_colors[ $color_scheme ] ) ) {
		$color_scheme = 'fresh';
	}

	if ( ! empty( $_wp_admin_css_colors[ $color_scheme ]->icon_colors ) ) {
		$icon_colors = $_wp_admin_css_colors[ $color_scheme ]->icon_colors;
	} elseif ( ! empty( $_wp_admin_css_colors['fresh']->icon_colors ) ) {
		$icon_colors = $_wp_admin_css_colors['fresh']->icon_colors;
	} else {
		// Fall back to the default set of icon colors if the default scheme is missing.
		$icon_colors = array(
			'base'    => '#a0a5aa',
			'focus'   => '#00a0d2',
			'current' => '#fff',
		);
	}

	echo '<script type="text/javascript">var _wpColorScheme = ' . wp_json_encode( array( 'icons' => $icon_colors ) ) . ";</script>\n";
}

/**
 * @since 3.3.0
 */
function _ipad_meta() {
	if ( wp_is_mobile() ) {
		?>
		<meta name="viewport" id="viewport-meta" content="width=device-width, initial-scale=1">
		<?php
	}
}

/**
 * Check lock status for posts displayed on the Posts screen
 *
 * @since 3.6.0
 *
 * @param array  $response  The Heartbeat response.
 * @param array  $data      The $_POST data sent.
 * @param string $screen_id The screen id.
 * @return array The Heartbeat response.
 */
function wp_check_locked_posts( $response, $data, $screen_id ) {
	$checked = array();

	if ( array_key_exists( 'wp-check-locked-posts', $data ) && is_array( $data['wp-check-locked-posts'] ) ) {
		foreach ( $data['wp-check-locked-posts'] as $key ) {
			$post_id = absint( substr( $key, 5 ) );
			if ( ! $post_id ) {
				continue;
			}

			$user_id = wp_check_post_lock( $post_id );
			if ( $user_id ) {
				$user = get_userdata( $user_id );
				if ( $user && current_user_can( 'edit_post', $post_id ) ) {
					$send = array(
						/* translators: %s: User's display name. */
						'text' => sprintf( __( '%s is currently editing' ), $user->display_name ),
					);

					$avatar = get_avatar( $user->ID, 18 );
					if ( $avatar && preg_match( "|src='([^']+)'|", $avatar, $matches ) ) {
						$send['avatar_src'] = $matches[1];
					}

					$checked[ $key ] = $send;
				}
			}
		}
	}

	if ( ! empty( $checked ) ) {
		$response['wp-check-locked-posts'] = $checked;
	}

	return $response;
}

/**
 * Check lock status on the New/Edit Post screen and refresh the lock
 *
 * @since 3.6.0
 *
 * @param array  $response  The Heartbeat response.
 * @param array  $data      The $_POST data sent.
 * @param string $screen_id The screen id.
 * @return array The Heartbeat response.
 */
function wp_refresh_post_lock( $response, $data, $screen_id ) {
	if ( array_key_exists( 'wp-refresh-post-lock', $data ) ) {
		$received = $data['wp-refresh-post-lock'];
		$send     = array();

		$post_id = absint( $received['post_id'] );
		if ( ! $post_id ) {
			return $response;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $response;
		}

		$user_id = wp_check_post_lock( $post_id );
		$user    = get_userdata( $user_id );
		if ( $user ) {
			$error = array(
				/* translators: %s: User's display name. */
				'text' => sprintf( __( '%s has taken over and is currently editing.' ), $user->display_name ),
			);

			$avatar = get_avatar( $user->ID, 64 );
			if ( $avatar ) {
				if ( preg_match( "|src='([^']+)'|", $avatar, $matches ) ) {
					$error['avatar_src'] = $matches[1];
				}
			}

			$send['lock_error'] = $error;
		} else {
			$new_lock = wp_set_post_lock( $post_id );
			if ( $new_lock ) {
				$send['new_lock'] = implode( ':', $new_lock );
			}
		}

		$response['wp-refresh-post-lock'] = $send;
	}

	return $response;
}

/**
 * Check nonce expiration on the New/Edit Post screen and refresh if needed
 *
 * @since 3.6.0
 *
 * @param array  $response  The Heartbeat response.
 * @param array  $data      The $_POST data sent.
 * @param string $screen_id The screen id.
 * @return array The Heartbeat response.
 */
function wp_refresh_post_nonces( $response, $data, $screen_id ) {
	if ( array_key_exists( 'wp-refresh-post-nonces', $data ) ) {
		$received                           = $data['wp-refresh-post-nonces'];
		$response['wp-refresh-post-nonces'] = array( 'check' => 1 );

		$post_id = absint( $received['post_id'] );
		if ( ! $post_id ) {
			return $response;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $response;
		}

		$response['wp-refresh-post-nonces'] = array(
			'replace' => array(
				'getpermalinknonce'    => wp_create_nonce( 'getpermalink' ),
				'samplepermalinknonce' => wp_create_nonce( 'samplepermalink' ),
				'closedpostboxesnonce' => wp_create_nonce( 'closedpostboxes' ),
				'_ajax_linking_nonce'  => wp_create_nonce( 'internal-linking' ),
				'_wpnonce'             => wp_create_nonce( 'update-post_' . $post_id ),
			),
		);
	}

	return $response;
}

/**
 * Add the latest Heartbeat and REST-API nonce to the Heartbeat response.
 *
 * @since 5.0.0
 *
 * @param array  $response  The Heartbeat response.
 * @return array The Heartbeat response.
 */
function wp_refresh_heartbeat_nonces( $response ) {
	// Refresh the Rest API nonce.
	$response['rest_nonce'] = wp_create_nonce( 'wp_rest' );

	// Refresh the Heartbeat nonce.
	$response['heartbeat_nonce'] = wp_create_nonce( 'heartbeat-nonce' );
	return $response;
}

/**
 * Disable suspension of Heartbeat on the Add/Edit Post screens.
 *
 * @since 3.8.0
 *
 * @global string $pagenow
 *
 * @param array $settings An array of Heartbeat settings.
 * @return array Filtered Heartbeat settings.
 */
function wp_heartbeat_set_suspension( $settings ) {
	global $pagenow;

	if ( 'post.php' === $pagenow || 'post-new.php' === $pagenow ) {
		$settings['suspension'] = 'disable';
	}

	return $settings;
}

/**
 * Autosave with heartbeat
 *
 * @since 3.9.0
 *
 * @param array $response The Heartbeat response.
 * @param array $data     The $_POST data sent.
 * @return array The Heartbeat response.
 */
function heartbeat_autosave( $response, $data ) {
	if ( ! empty( $data['wp_autosave'] ) ) {
		$saved = wp_autosave( $data['wp_autosave'] );

		if ( is_wp_error( $saved ) ) {
			$response['wp_autosave'] = array(
				'success' => false,
				'message' => $saved->get_error_message(),
			);
		} elseif ( empty( $saved ) ) {
			$response['wp_autosave'] = array(
				'success' => false,
				'message' => __( 'Error while saving.' ),
			);
		} else {
			/* translators: Draft saved date format, see https://www.php.net/date */
			$draft_saved_date_format = __( 'g:i:s a' );
			$response['wp_autosave'] = array(
				'success' => true,
				/* translators: %s: Date and time. */
				'message' => sprintf( __( 'Draft saved at %s.' ), date_i18n( $draft_saved_date_format ) ),
			);
		}
	}

	return $response;
}

/**
 * Remove single-use URL parameters and create canonical link based on new URL.
 *
 * Remove specific query string parameters from a URL, create the canonical link,
 * put it in the admin header, and change the current URL to match.
 *
 * @since 4.2.0
 */
function wp_admin_canonical_url() {
	$removable_query_args = wp_removable_query_args();

	if ( empty( $removable_query_args ) ) {
		return;
	}

	// Ensure we're using an absolute URL.
	$current_url  = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
	$filtered_url = remove_query_arg( $removable_query_args, $current_url );
	?>
	<link id="wp-admin-canonical" rel="canonical" href="<?php echo esc_url( $filtered_url ); ?>" />
	<script>
		if ( window.history.replaceState ) {
			window.history.replaceState( null, null, document.getElementById( 'wp-admin-canonical' ).href + window.location.hash );
		}
	</script>
	<?php
}

/**
 * Send a referrer policy header so referrers are not sent externally from administration screens.
 *
 * @since 4.9.0
 */
function wp_admin_headers() {
	$policy = 'strict-origin-when-cross-origin';

	/**
	 * Filters the admin referrer policy header value.
	 *
	 * @since 4.9.0
	 * @since 4.9.5 The default value was changed to 'strict-origin-when-cross-origin'.
	 *
	 * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Referrer-Policy
	 *
	 * @param string $policy The admin referrer policy header value. Default 'strict-origin-when-cross-origin'.
	 */
	$policy = apply_filters( 'admin_referrer_policy', $policy );

	header( sprintf( 'Referrer-Policy: %s', $policy ) );
}

/**
 * Outputs JS that reloads the page if the user navigated to it with the Back or Forward button.
 *
 * Used on the Edit Post and Add New Post screens. Needed to ensure the page is not loaded from browser cache,
 * so the post title and editor content are the last saved versions. Ideally this script should run first in the head.
 *
 * @since 4.6.0
 */
function wp_page_reload_on_back_button_js() {
	?>
	<script>
		if ( typeof performance !== 'undefined' && performance.navigation && performance.navigation.type === 2 ) {
			document.location.reload( true );
		}
	</script>
	<?php
}

/**
 * Send a confirmation request email when a change of site admin email address is attempted.
 *
 * The new site admin address will not become active until confirmed.
 *
 * @since 3.0.0
 * @since 4.9.0 This function was moved from wp-admin/includes/ms.php so it's no longer Multisite specific.
 *
 * @param string $old_value The old site admin email address.
 * @param string $value     The proposed new site admin email address.
 */
function update_option_new_admin_email( $old_value, $value ) {
	if ( get_option( 'admin_email' ) === $value || ! is_email( $value ) ) {
		return;
	}

	$hash            = md5( $value . time() . wp_rand() );
	$new_admin_email = array(
		'hash'     => $hash,
		'newemail' => $value,
	);
	update_option( 'adminhash', $new_admin_email );

	$switched_locale = switch_to_locale( get_user_locale() );

	/* translators: Do not translate USERNAME, ADMIN_URL, EMAIL, SITENAME, SITEURL: those are placeholders. */
	$email_text = __(
		'Howdy ###EMAIL###,

You recently requested to have the administration email address on
your site changed.

If this is correct, please click on the following link to change it:
###ADMIN_URL###

You can safely ignore and delete this email if you do not want to
take this action.

This email has been sent to ###EMAIL###

Regards,
All at ###SITENAME###
###SITEURL###'
	);

	/**
	 * Filters the text of the email sent when a change of site admin email address is attempted.
	 *
	 * The following strings have a special meaning and will get replaced dynamically:
	 * ###USERNAME###  The current user's username.
	 * ###ADMIN_URL### The link to click on to confirm the email change.
	 * ###EMAIL###     The proposed new site admin email address.
	 * ###SITENAME###  The name of the site.
	 * ###SITEURL###   The URL to the site.
	 *
	 * @since MU (3.0.0)
	 * @since 4.9.0 This filter is no longer Multisite specific.
	 *
	 * @param string $email_text      Text in the email.
	 * @param array  $new_admin_email {
	 *     Data relating to the new site admin email address.
	 *
	 *     @type string $hash     The secure hash used in the confirmation link URL.
	 *     @type string $newemail The proposed new site admin email address.
	 * }
	 */
	$content = apply_filters( 'new_admin_email_content', $email_text, $new_admin_email );

	$current_user = wp_get_current_user();
	$content      = str_replace( '###USERNAME###', $current_user->user_login, $content );
	$content      = str_replace( '###ADMIN_URL###', esc_url( self_admin_url( 'options.php?adminhash=' . $hash ) ), $content );
	$content      = str_replace( '###EMAIL###', $value, $content );
	$content      = str_replace( '###SITENAME###', wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), $content );
	$content      = str_replace( '###SITEURL###', home_url(), $content );

	wp_mail(
		$value,
		sprintf(
			/* translators: New admin email address notification email subject. %s: Site title. */
			__( '[%s] New Admin Email Address' ),
			wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES )
		),
		$content
	);

	if ( $switched_locale ) {
		restore_previous_locale();
	}
}

/**
 * Appends '(Draft)' to draft page titles in the privacy page dropdown
 * so that unpublished content is obvious.
 *
 * @since 4.9.8
 * @access private
 *
 * @param string  $title Page title.
 * @param WP_Post $page  Page data object.
 *
 * @return string Page title.
 */
function _wp_privacy_settings_filter_draft_page_titles( $title, $page ) {
	if ( 'draft' === $page->post_status && 'privacy' === get_current_screen()->id ) {
		/* translators: %s: Page title. */
		$title = sprintf( __( '%s (Draft)' ), $title );
	}

	return $title;
}

