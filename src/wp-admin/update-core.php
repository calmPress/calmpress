<?php
/**
 * Update Core administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

wp_enqueue_style( 'plugin-install' );
wp_enqueue_script( 'plugin-install' );
wp_enqueue_script( 'updates' );
add_thickbox();

if ( is_multisite() && ! is_network_admin() ) {
	wp_redirect( network_admin_url( 'update-core.php' ) );
	exit;
}

if ( ! current_user_can( 'update_core' ) && ! current_user_can( 'update_themes' ) && ! current_user_can( 'update_plugins' ) && ! current_user_can( 'update_languages' ) ) {
	wp_die( __( 'Sorry, you are not allowed to update this site.' ) );
}

/**
 * @global wpdb   $wpdb WordPress database abstraction object.
 *
 * @param object $update
 */
function list_core_update( $update ) {
	global $wpdb;
	static $first_pass = true;

	$submit = __('Update Now');
	$form_action = 'update-core.php?action=do-core-upgrade';
	$php_version    = phpversion();
	$mysql_version  = $wpdb->db_version();
	$show_buttons = true;
	$php_compat     = version_compare( $php_version, $update->min_php, '>=' );
	if ( file_exists( WP_CONTENT_DIR . '/db.php' ) && empty( $wpdb->is_mysql ) )
		$mysql_compat = true;
	else
		$mysql_compat = version_compare( $mysql_version, $update->min_mysql, '>=' );

	$version_slug_parts = explode( '.', calmpress_version() );
	$version_slug = $version_slug_parts[0] . '-' . $version_slug_parts[1];

	$version_url = sprintf(
		/* translators: %s: calmPress main version. */
		esc_url( __( 'https://calmpress.org/version/%s/' ) ),
		$version_slug
	);

	$annotation = wp_get_update_php_annotation();
	if ( $annotation ) {
		$php_update_message .= '</p><p><em>' . $annotation . '</em>';
	}

	if ( ! $mysql_compat && ! $php_compat ) {
		$message = sprintf(
			/* translators: 1: URL to calmPress release notes, 2: calmPress version number, 3: Minimum required PHP version number, 4: Minimum required MySQL version number, 5: Current PHP version number, 6: Current MySQL version number. */
			__( 'You cannot update because <a href="%1$s">calmPress %2$s</a> requires PHP version %3$s or higher and MySQL version %4$s or higher. You are running PHP version %5$s and MySQL version %6$s.' ),
			$version_url,
			$update->current,
			$update->php_version,
			$update->mysql_version,
			$php_version,
			$mysql_version
		) . $php_update_message;
	} elseif ( ! $php_compat ) {
		$message = sprintf(
			/* translators: 1: URL to calmPress release notes, 2: calmPress version number, 3: Minimum required PHP version number, 4: Current PHP version number. */
			__( 'You cannot update because <a href="%1$s">calmPress %2$s</a> requires PHP version %3$s or higher. You are running version %4$s.' ),
			$version_url,
			$update->current,
			$update->php_version,
			$php_version
		) . $php_update_message;
	} elseif ( ! $mysql_compat ) {
		$message = sprintf(
			/* translators: 1: URL to calmPress release notes, 2: calmPress version number, 3: Minimum required MySQL version number, 4: Current MySQL version number. */
			__( 'You cannot update because <a href="%1$s">calmPress %2$s</a> requires MySQL version %3$s or higher. You are running version %4$s.' ),
			$version_url,
			$update->current,
			$update->mysql_version,
			$mysql_version
		);
	}
	
	if ( ! $mysql_compat || ! $php_compat ) {
		$show_buttons = false;
	}

	echo '<p>';
	echo $message;
	echo '</p>';

	echo '<form method="post" action="' . esc_url( $form_action ) . '" name="upgrade" class="upgrade">';
	wp_nonce_field( 'upgrade-core' );

	echo '<p>';
	echo '<input name="version" value="' . esc_attr( $update->version ) . '" type="hidden" />';
	if ( $show_buttons ) {
		if ( $first_pass ) {
			submit_button( $submit, 'primary regular', 'upgrade', false );
			$first_pass = false;
		} else {
			submit_button( $submit, '', 'upgrade', false );
		}
	}
	echo '</p>';
	echo '</form>';

}

/**
 * Display dismissed updates.
 *
 * @since 2.7.0
 */
function dismissed_updates() {
	$dismissed = get_core_updates(
		array(
			'dismissed' => true,
			'available' => false,
		)
	);

	if ( $dismissed ) {
		$show_text = esc_js( __( 'Show hidden updates' ) );
		$hide_text = esc_js( __( 'Hide hidden updates' ) );
		?>
		<script type="text/javascript">
			jQuery( function( $ ) {
				$( '#show-dismissed' ).on( 'click', function() {
					var isExpanded = ( 'true' === $( this ).attr( 'aria-expanded' ) );

					if ( isExpanded ) {
						$( this ).text( '<?php echo $show_text; ?>' ).attr( 'aria-expanded', 'false' );
					} else {
						$( this ).text( '<?php echo $hide_text; ?>' ).attr( 'aria-expanded', 'true' );
					}

					$( '#dismissed-updates' ).toggle( 'fast' );
				});
			});
		</script>
		<?php
		echo '<p class="hide-if-no-js"><button type="button" class="button" id="show-dismissed" aria-expanded="false">' . __( 'Show hidden updates' ) . '</button></p>';
		echo '<ul id="dismissed-updates" class="core-updates dismissed">';
		foreach ( (array) $dismissed as $update ) {
			echo '<li>';
			list_core_update( $update );
			echo '</li>';
		}
		echo '</ul>';
	}
}

/**
 * Display upgrade calmPress for downloading latest or upgrading automatically form.
 *
 * @since 2.7.0
 */
function core_upgrade_preamble() {

	$updates = get_core_updates();

	if ( empty( $updates ) ) {
		echo '<h2>';
		_e('You have the latest version of calmPress.');
		echo '</h2>';
	}

	if ( isset( $updates[0]->version ) && version_compare( $updates[0]->version, calmpress_version(), '>' ) ) {
		echo '<div class="notice notice-warning"><p>';
		_e( '<strong>Important:</strong> before updating, please back up your database and files.' );
		echo '</p></div>';

		echo '<h2 class="response">';
		_e( 'An updated version of calmPress is available.' );
		echo '</h2>';
	}

	echo '<ul class="core-updates">';
	foreach ( (array) $updates as $update ) {
		echo '<li>';
		list_core_update( $update );
		echo '</li>';
	}
	echo '</ul>';

	// Don't show the maintenance mode notice when we are only showing a single re-install option.
	if ( $updates && ( count( $updates ) > 1 ) ) {
		echo '<p>' . __( 'While your site is being updated, it will be in maintenance mode. As soon as your updates are complete, your site will return to normal.' ) . '</p>';
	}

	dismissed_updates();
}

/**
 * Display the upgrade plugins form.
 *
 * @since 2.9.0
 */
function list_plugin_updates() {
	$version     = get_bloginfo( 'version' );
	$cur_version = preg_replace( '/-.*$/', '', $version );

	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	$plugins = get_plugin_updates();
	if ( empty( $plugins ) ) {
		echo '<h2>' . __( 'Plugins' ) . '</h2>';
		echo '<p>' . __( 'Your plugins are all up to date.' ) . '</p>';
		return;
	}
	$form_action = 'update-core.php?action=do-plugin-upgrade';

	$core_updates = get_core_updates();
	if ( ! isset( $core_updates[0]->response ) || 'latest' === $core_updates[0]->response || 'development' === $core_updates[0]->response || version_compare( $core_updates[0]->current, $cur_version, '=') ) {
		$core_update_version = false;
	} else {
		$core_update_version = $core_updates[0]->current;
	}

	$plugins_count = count( $plugins );
	?>
<h2>
	<?php
	printf(
		'%s <span class="count">(%d)</span>',
		__( 'Plugins' ),
		number_format_i18n( $plugins_count )
	);
	?>
</h2>
<p><?php _e( 'The following plugins have new versions available. Check the ones you want to update and then click &#8220;Update Plugins&#8221;.' ); ?></p>
<form method="post" action="<?php echo esc_url( $form_action ); ?>" name="upgrade-plugins" class="upgrade">
	<?php wp_nonce_field( 'upgrade-core' ); ?>
<p><input id="upgrade-plugins" class="button" type="submit" value="<?php esc_attr_e( 'Update Plugins' ); ?>" name="upgrade" /></p>
<table class="widefat updates-table" id="update-plugins-table">
	<thead>
	<tr>
		<td class="manage-column check-column"><input type="checkbox" id="plugins-select-all" /></td>
		<td class="manage-column"><label for="plugins-select-all"><?php _e( 'Select All' ); ?></label></td>
	</tr>
	</thead>

	<tbody class="plugins">
	<?php

	foreach ( (array) $plugins as $plugin_file => $plugin_data ) {
		$plugin_data = (object) _get_plugin_data_markup_translate( $plugin_file, (array) $plugin_data, false, true );

		$icon            = '<span class="dashicons dashicons-admin-plugins"></span>';
		$preferred_icons = array( 'svg', '2x', '1x', 'default' );
		foreach ( $preferred_icons as $preferred_icon ) {
			if ( ! empty( $plugin_data->update->icons[ $preferred_icon ] ) ) {
				$icon = '<img src="' . esc_url( $plugin_data->update->icons[ $preferred_icon ] ) . '" alt="" />';
				break;
			}
		}

		// Get plugin compat for running version of the base WordPress.
		if ( isset( $plugin_data->update->tested ) && version_compare( $plugin_data->update->tested, $cur_version, '>=') ) {
			/* translators: %s: WordPress version. */
			$compat = '<br />' . sprintf( __(' Compatibility with the underlying WordPress version %1$s: 100%% (according to its author)' ), $cur_version );
		} else {
			/* translators: %s: WordPress version. */
			$compat = '<br />' . sprintf( __('Compatibility with the underlying WordPress version %1$s: Unknown' ), $cur_version );
		}
		// Get plugin compat for updated version of calmPress.
		if ( $core_update_version ) {
			if ( isset( $plugin_data->update->tested ) && version_compare( $plugin_data->update->tested, $core_update_version, '>=' ) ) {
				/* translators: %s: WordPress version. */
				$compat .= '<br />' . sprintf( __( 'Compatibility with the underlying WordPress version %s: 100%% (according to its author)' ), $core_update_version );
			} else {
				/* translators: %s: WordPress version. */
				$compat .= '<br />' . sprintf( __( 'Compatibility with the underlying WordPress version %s: Unknown' ), $core_update_version );
			}
		}

		$requires_php   = isset( $plugin_data->update->requires_php ) ? $plugin_data->update->requires_php : null;
		$compatible_php = is_php_version_compatible( $requires_php );

		if ( ! $compatible_php && current_user_can( 'update_php' ) ) {
			$compat .= '<br>' . __( 'This update doesn&#8217;t work with your version of PHP.' ) . '&nbsp;';
			$compat .= sprintf(
				/* translators: %s: URL to Update PHP page. */
				__( '<a href="%s">Learn more about updating PHP</a>.' ),
				esc_url( wp_get_update_php_url() )
			);

			$annotation = wp_get_update_php_annotation();

			if ( $annotation ) {
				$compat .= '</p><p><em>' . $annotation . '</em>';
			}
		}

		// Get the upgrade notice for the new plugin version.
		if ( isset( $plugin_data->update->upgrade_notice ) ) {
			$upgrade_notice = '<br />' . strip_tags( $plugin_data->update->upgrade_notice );
		} else {
			$upgrade_notice = '';
		}

		$details_url = self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $plugin_data->update->slug . '&section=changelog&TB_iframe=true&width=640&height=662' );
		$details     = sprintf(
			'<a href="%1$s" class="thickbox open-plugin-details-modal" aria-label="%2$s">%3$s</a>',
			esc_url( $details_url ),
			/* translators: 1: Plugin name, 2: Version number. */
			esc_attr( sprintf( __( 'View %1$s version %2$s details' ), $plugin_data->Name, $plugin_data->update->new_version ) ),
			/* translators: %s: Plugin version. */
			sprintf( __( 'View version %s details.' ), $plugin_data->update->new_version )
		);

		$checkbox_id = 'checkbox_' . md5( $plugin_file );
		?>
	<tr>
		<td class="check-column">
			<?php if ( $compatible_php ) : ?>
				<input type="checkbox" name="checked[]" id="<?php echo $checkbox_id; ?>" value="<?php echo esc_attr( $plugin_file ); ?>" />
				<label for="<?php echo $checkbox_id; ?>" class="screen-reader-text">
					<?php
					/* translators: %s: Plugin name. */
					printf( __( 'Select %s' ), $plugin_data->Name );
					?>
				</label>
			<?php endif; ?>
		</td>
		<td class="plugin-title"><p>
			<?php echo $icon; ?>
			<strong><?php echo $plugin_data->Name; ?></strong>
			<?php
			printf(
				/* translators: 1: Plugin version, 2: New version. */
				__( 'You have version %1$s installed. Update to %2$s.' ),
				$plugin_data->Version,
				$plugin_data->update->new_version
			);

			echo ' ' . $details . $compat . $upgrade_notice;
			?>
		</p></td>
	</tr>
			<?php
	}
	?>
	</tbody>

	<tfoot>
	<tr>
		<td class="manage-column check-column"><input type="checkbox" id="plugins-select-all-2" /></td>
		<td class="manage-column"><label for="plugins-select-all-2"><?php _e( 'Select All' ); ?></label></td>
	</tr>
	</tfoot>
</table>
<p><input id="upgrade-plugins-2" class="button" type="submit" value="<?php esc_attr_e( 'Update Plugins' ); ?>" name="upgrade" /></p>
</form>
	<?php
}

/**
 * Display the upgrade themes form.
 *
 * @since 2.9.0
 */
function list_theme_updates() {
	$themes = get_theme_updates();
	if ( empty( $themes ) ) {
		echo '<h2>' . __( 'Themes' ) . '</h2>';
		echo '<p>' . __( 'Your themes are all up to date.' ) . '</p>';
		return;
	}

	$form_action = 'update-core.php?action=do-theme-upgrade';

	$themes_count = count( $themes );
	?>
<h2>
	<?php
	printf(
		'%s <span class="count">(%d)</span>',
		__( 'Themes' ),
		number_format_i18n( $themes_count )
	);
	?>
</h2>
<p><?php _e( 'The following themes have new versions available. Check the ones you want to update and then click &#8220;Update Themes&#8221;.' ); ?></p>
<p>
	<?php
	printf(
		/* translators: %s: Link to documentation on child themes. */
		__( '<strong>Please Note:</strong> Any customizations you have made to theme files will be lost. Please consider using <a href="%s">child themes</a> for modifications.' ),
		__( 'https://developer.wordpress.org/themes/advanced-topics/child-themes/' )
	);
	?>
</p>
<form method="post" action="<?php echo esc_url( $form_action ); ?>" name="upgrade-themes" class="upgrade">
	<?php wp_nonce_field( 'upgrade-core' ); ?>
<p><input id="upgrade-themes" class="button" type="submit" value="<?php esc_attr_e( 'Update Themes' ); ?>" name="upgrade" /></p>
<table class="widefat updates-table" id="update-themes-table">
	<thead>
	<tr>
		<td class="manage-column check-column"><input type="checkbox" id="themes-select-all" /></td>
		<td class="manage-column"><label for="themes-select-all"><?php _e( 'Select All' ); ?></label></td>
	</tr>
	</thead>

	<tbody class="plugins">
	<?php
	foreach ( $themes as $stylesheet => $theme ) {
		$requires_wp  = isset( $theme->update['requires'] ) ? $theme->update['requires'] : null;
		$requires_php = isset( $theme->update['requires_php'] ) ? $theme->update['requires_php'] : null;

		$compatible_wp  = is_wp_version_compatible( $requires_wp );
		$compatible_php = is_php_version_compatible( $requires_php );

		$compat = '';

		if ( ! $compatible_wp && ! $compatible_php ) {
			$compat .= '<br>' . __( 'This update doesn&#8217;t work with your versions of WordPress and PHP.' ) . '&nbsp;';
			if ( current_user_can( 'update_core' ) && current_user_can( 'update_php' ) ) {
				$compat .= sprintf(
					/* translators: 1: URL to WordPress Updates screen, 2: URL to Update PHP page. */
					__( '<a href="%1$s">Please update WordPress</a>, and then <a href="%2$s">learn more about updating PHP</a>.' ),
					self_admin_url( 'update-core.php' ),
					esc_url( wp_get_update_php_url() )
				);

				$annotation = wp_get_update_php_annotation();

				if ( $annotation ) {
					$compat .= '</p><p><em>' . $annotation . '</em>';
				}
			} elseif ( current_user_can( 'update_core' ) ) {
				$compat .= sprintf(
					/* translators: %s: URL to WordPress Updates screen. */
					__( '<a href="%s">Please update WordPress</a>.' ),
					self_admin_url( 'update-core.php' )
				);
			} elseif ( current_user_can( 'update_php' ) ) {
				$compat .= sprintf(
					/* translators: %s: URL to Update PHP page. */
					__( '<a href="%s">Learn more about updating PHP</a>.' ),
					esc_url( wp_get_update_php_url() )
				);

				$annotation = wp_get_update_php_annotation();

				if ( $annotation ) {
					$compat .= '</p><p><em>' . $annotation . '</em>';
				}
			}
		} elseif ( ! $compatible_wp ) {
			$compat .= '<br>' . __( 'This update doesn&#8217;t work with your version of WordPress.' ) . '&nbsp;';
			if ( current_user_can( 'update_core' ) ) {
				$compat .= sprintf(
					/* translators: %s: URL to WordPress Updates screen. */
					__( '<a href="%s">Please update WordPress</a>.' ),
					self_admin_url( 'update-core.php' )
				);
			}
		} elseif ( ! $compatible_php ) {
			$compat .= '<br>' . __( 'This update doesn&#8217;t work with your version of PHP.' ) . '&nbsp;';
			if ( current_user_can( 'update_php' ) ) {
				$compat .= sprintf(
					/* translators: %s: URL to Update PHP page. */
					__( '<a href="%s">Learn more about updating PHP</a>.' ),
					esc_url( wp_get_update_php_url() )
				);

				$annotation = wp_get_update_php_annotation();

				if ( $annotation ) {
					$compat .= '</p><p><em>' . $annotation . '</em>';
				}
			}
		}

		$checkbox_id = 'checkbox_' . md5( $theme->get( 'Name' ) );
		?>
	<tr>
		<td class="check-column">
			<?php if ( $compatible_wp && $compatible_php ) : ?>
				<input type="checkbox" name="checked[]" id="<?php echo $checkbox_id; ?>" value="<?php echo esc_attr( $stylesheet ); ?>" />
				<label for="<?php echo $checkbox_id; ?>" class="screen-reader-text">
					<?php
					/* translators: %s: Theme name. */
					printf( __( 'Select %s' ), $theme->display( 'Name' ) );
					?>
				</label>
			<?php endif; ?>
		</td>
		<td class="plugin-title"><p>
			<img src="<?php echo esc_url( $theme->get_screenshot() ); ?>" width="85" height="64" class="updates-table-screenshot" alt="" />
			<strong><?php echo $theme->display( 'Name' ); ?></strong>
			<?php
			printf(
				/* translators: 1: Theme version, 2: New version. */
				__( 'You have version %1$s installed. Update to %2$s.' ),
				$theme->display( 'Version' ),
				$theme->update['new_version']
			);

			echo ' ' . $compat;
			?>
		</p></td>
	</tr>
			<?php
	}
	?>
	</tbody>

	<tfoot>
	<tr>
		<td class="manage-column check-column"><input type="checkbox" id="themes-select-all-2" /></td>
		<td class="manage-column"><label for="themes-select-all-2"><?php _e( 'Select All' ); ?></label></td>
	</tr>
	</tfoot>
</table>
<p><input id="upgrade-themes-2" class="button" type="submit" value="<?php esc_attr_e( 'Update Themes' ); ?>" name="upgrade" /></p>
</form>
	<?php
}

/**
 * Display the update translations form.
 *
 * @since 3.7.0
 */
function list_translation_updates() {
	$updates = wp_get_translation_updates();
	if ( ! $updates ) {
		if ( 'en_US' !== get_locale() ) {
			echo '<h2>' . __( 'Translations' ) . '</h2>';
			echo '<p>' . __( 'Your translations are all up to date.' ) . '</p>';
		}
		return;
	}

	$form_action = 'update-core.php?action=do-translation-upgrade';
	?>
	<h2><?php _e( 'Translations' ); ?></h2>
	<form method="post" action="<?php echo esc_url( $form_action ); ?>" name="upgrade-translations" class="upgrade">
		<p><?php _e( 'New translations are available.' ); ?></p>
		<?php wp_nonce_field( 'upgrade-translations' ); ?>
		<p><input class="button" type="submit" value="<?php esc_attr_e( 'Update Translations' ); ?>" name="upgrade" /></p>
	</form>
	<?php
}

/**
 * Upgrade WordPress core display.
 *
 * @since 2.7.0
 *
 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
 *
 * @param bool $reinstall
 */
function do_core_upgrade( $reinstall = false ) {
	global $wp_filesystem;

	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	if ( $reinstall ) {
		$url = 'update-core.php?action=do-core-reinstall';
	} else {
		$url = 'update-core.php?action=do-core-upgrade';
	}
	$url = wp_nonce_url( $url, 'upgrade-core' );

	$version = isset( $_POST['version'] ) ? $_POST['version'] : false;
	$locale  = isset( $_POST['locale'] ) ? $_POST['locale'] : 'en_US';
	$update  = find_core_update( $version, $locale );
	if ( ! $update ) {
		return;
	}

	// Allow relaxed file ownership writes for User-initiated upgrades when the API specifies
	// that it's safe to do so. This only happens when there are no new files to create.
	$allow_relaxed_file_ownership = ! $reinstall && isset( $update->new_files ) && ! $update->new_files;

	?>
	<div class="wrap">
	<h1><?php _e( 'Update calmPress' ); ?></h1>
	<?php

	$credentials = request_filesystem_credentials( $url, '', false, ABSPATH, array( 'version', 'locale' ), $allow_relaxed_file_ownership );
	if ( false === $credentials ) {
		echo '</div>';
		return;
	}

	if ( ! WP_Filesystem( $credentials, ABSPATH, $allow_relaxed_file_ownership ) ) {
		// Failed to connect. Error and request again.
		request_filesystem_credentials( $url, '', true, ABSPATH, array( 'version', 'locale' ), $allow_relaxed_file_ownership );
		echo '</div>';
		return;
	}

	if ( $wp_filesystem->errors->has_errors() ) {
		foreach ( $wp_filesystem->errors->get_error_messages() as $message ) {
			show_message( $message );
		}
		echo '</div>';
		return;
	}

	if ( $reinstall ) {
		$update->response = 'reinstall';
	}

	add_filter( 'update_feedback', 'show_message' );

	$upgrader = new Core_Upgrader();
	$result   = $upgrader->upgrade(
		$update,
		array(
			'allow_relaxed_file_ownership' => $allow_relaxed_file_ownership,
		)
	);

	if ( is_wp_error( $result ) ) {
		show_message( $result );
		if ( 'up_to_date' != $result->get_error_code() && 'locked' != $result->get_error_code() ) {
			show_message( __( 'Installation failed.' ) );
		}
		echo '</div>';
		return;
	}

	show_message( __('calmPress updated successfully.' ) );
	?>
	</div>
	<?php
}

/**
 * Dismiss a core update.
 *
 * @since 2.7.0
 */
function do_dismiss_core_update() {
	$version = isset( $_POST['version'] ) ? $_POST['version'] : false;
	$locale  = isset( $_POST['locale'] ) ? $_POST['locale'] : 'en_US';
	$update  = find_core_update( $version, $locale );
	if ( ! $update ) {
		return;
	}
	dismiss_core_update( $update );
	wp_redirect( wp_nonce_url( 'update-core.php?action=upgrade-core', 'upgrade-core' ) );
	exit;
}

/**
 * Undismiss a core update.
 *
 * @since 2.7.0
 */
function do_undismiss_core_update() {
	$version = isset( $_POST['version'] ) ? $_POST['version'] : false;
	$locale  = isset( $_POST['locale'] ) ? $_POST['locale'] : 'en_US';
	$update  = find_core_update( $version, $locale );
	if ( ! $update ) {
		return;
	}
	undismiss_core_update( $version, $locale );
	wp_redirect( wp_nonce_url( 'update-core.php?action=upgrade-core', 'upgrade-core' ) );
	exit;
}

$action = isset( $_GET['action'] ) ? $_GET['action'] : 'upgrade-core';

$upgrade_error = false;
if ( ( 'do-theme-upgrade' === $action || ( 'do-plugin-upgrade' === $action && ! isset( $_GET['plugins'] ) ) )
	&& ! isset( $_POST['checked'] ) ) {
	$upgrade_error = ( 'do-theme-upgrade' === $action ) ? 'themes' : 'plugins';
	$action        = 'upgrade-core';
}

$title       = __( 'calmPress Updates' );
$parent_file = 'index.php';

$updates_overview  = '<p>' . __( 'On this screen, you can update to the latest version of calmPress, as well as update your themes, plugins, and translations from the WordPress.org repositories.' ) . '</p>';
$updates_overview .= '<p>' . __( 'If an update is available, you&#8127;ll see a notification appear in the Toolbar and navigation menu.' ) . ' ' . __( 'Keeping your site updated is important for security. It also makes the internet a safer place for you and your readers.' ) . '</p>';

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' => $updates_overview,
	)
);

$updates_howto  = '<p>' . __( '<strong>calmPress</strong> &mdash; Updating your calmPress installation is a simple one-click procedure: just <strong>click on the &#8220;Update Now&#8221; button</strong> when you are notified that a new version is available.' ) . '</p>';
$updates_howto .= '<p>' . __( '<strong>Themes and Plugins</strong> &mdash; To update individual themes or plugins from this screen, use the checkboxes to make your selection, then <strong>click on the appropriate &#8220;Update&#8221; button</strong>. To update all of your themes or plugins at once, you can check the box at the top of the section to select all before clicking the update button.' ) . '</p>';

if ( 'en_US' != get_locale() ) {
	$updates_howto .= '<p>' . __( '<strong>Translations</strong> &mdash; The files translating calmPress into your language are updated for you whenever any other updates occur. But if these files are out of date, you can <strong>click the &#8220;Update Translations&#8221;</strong> button.' ) . '</p>';
}

get_current_screen()->add_help_tab(
	array(
		'id'      => 'how-to-update',
		'title'   => __( 'How to Update' ),
		'content' => $updates_howto,
	)
);

if ( 'upgrade-core' === $action ) {
	// Force a update check when requested.
	$force_check = ! empty( $_GET['force-check'] );
	wp_version_check( array(), $force_check );

	require_once ABSPATH . 'wp-admin/admin-header.php';
	?>
	<div class="wrap">
	<h1><?php _e( 'calmPress Updates' ); ?></h1>
	<p><?php _e( 'Here you can find information about updates, and see what plugins or themes need updating.' ); ?></p>

	<?php
	if ( $upgrade_error ) {
		echo '<div class="error"><p>';
		if ( 'themes' === $upgrade_error ) {
			_e( 'Please select one or more themes to update.' );
		} else {
			_e( 'Please select one or more plugins to update.' );
		}
		echo '</p></div>';
	}

	$last_update_check = false;
	$current           = get_site_transient( 'update_core' );

	if ( $current && isset( $current->last_checked ) ) {
		$last_update_check = $current->last_checked + get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
	}

	echo '<h2 class="wp-current-version">';
	/* translators: Current version of calmPress. */
	printf( __( 'Current version: %s' ), calmpress_version() );
	echo '</h2>';

	echo '<p class="update-last-checked">';
	/* translators: 1: Date, 2: Time. */
	printf( __( 'Last checked on %1$s at %2$s.' ), date_i18n( __( 'F j, Y' ), $last_update_check ), date_i18n( __( 'g:i a T' ), $last_update_check ) );
	echo ' <a href="' . esc_url( self_admin_url( 'update-core.php?force-check=1' ) ) . '">' . __( 'Check again.' ) . '</a>';
	echo '</p>';

	if ( current_user_can( 'update_core' ) ) {
		core_upgrade_preamble();
	}
	if ( current_user_can( 'update_plugins' ) ) {
		list_plugin_updates();
	}
	if ( current_user_can( 'update_themes' ) ) {
		list_theme_updates();
	}
	if ( current_user_can( 'update_languages' ) ) {
		list_translation_updates();
	}

	/**
	 * Fires after the core, plugin, and theme update tables.
	 *
	 * @since 2.9.0
	 */
	do_action( 'core_upgrade_preamble' );
	echo '</div>';

	wp_localize_script(
		'updates',
		'_wpUpdatesItemCounts',
		array(
			'totals' => wp_get_update_data(),
		)
	);

	require_once ABSPATH . 'wp-admin/admin-footer.php';

} elseif ( 'do-core-upgrade' === $action || 'do-core-reinstall' === $action ) {

	if ( ! current_user_can( 'update_core' ) ) {
		wp_die( __( 'Sorry, you are not allowed to update this site.' ) );
	}

	check_admin_referer( 'upgrade-core' );

	// Do the (un)dismiss actions before headers, so that they can redirect.
	if ( isset( $_POST['dismiss'] ) ) {
		do_dismiss_core_update();
	} elseif ( isset( $_POST['undismiss'] ) ) {
		do_undismiss_core_update();
	}

	require_once ABSPATH . 'wp-admin/admin-header.php';
	if ( 'do-core-reinstall' === $action ) {
		$reinstall = true;
	} else {
		$reinstall = false;
	}

	if ( isset( $_POST['upgrade'] ) ) {
		do_core_upgrade( $reinstall );
	}

	wp_localize_script(
		'updates',
		'_wpUpdatesItemCounts',
		array(
			'totals' => wp_get_update_data(),
		)
	);

	require_once ABSPATH . 'wp-admin/admin-footer.php';

} elseif ( 'do-plugin-upgrade' === $action ) {

	if ( ! current_user_can( 'update_plugins' ) ) {
		wp_die( __( 'Sorry, you are not allowed to update this site.' ) );
	}

	check_admin_referer( 'upgrade-core' );

	if ( isset( $_GET['plugins'] ) ) {
		$plugins = explode( ',', $_GET['plugins'] );
	} elseif ( isset( $_POST['checked'] ) ) {
		$plugins = (array) $_POST['checked'];
	} else {
		wp_redirect( admin_url( 'update-core.php' ) );
		exit;
	}

	$url = 'update.php?action=update-selected&plugins=' . urlencode( implode( ',', $plugins ) );
	$url = wp_nonce_url( $url, 'bulk-update-plugins' );

	// Used in the HTML title tag.
	$title = __( 'Update Plugins' );

	require_once ABSPATH . 'wp-admin/admin-header.php';
	?>
	<div class="wrap">
		<h1><?php _e( 'Update Plugins' ); ?></h1>
		<iframe src="<?php echo $url; ?>" style="width: 100%; height: 100%; min-height: 750px;" frameborder="0" title="<?php esc_attr_e( 'Update progress' ); ?>"></iframe>
	</div>
	<?php

	wp_localize_script(
		'updates',
		'_wpUpdatesItemCounts',
		array(
			'totals' => wp_get_update_data(),
		)
	);

	require_once ABSPATH . 'wp-admin/admin-footer.php';

} elseif ( 'do-theme-upgrade' === $action ) {

	if ( ! current_user_can( 'update_themes' ) ) {
		wp_die( __( 'Sorry, you are not allowed to update this site.' ) );
	}

	check_admin_referer( 'upgrade-core' );

	if ( isset( $_GET['themes'] ) ) {
		$themes = explode( ',', $_GET['themes'] );
	} elseif ( isset( $_POST['checked'] ) ) {
		$themes = (array) $_POST['checked'];
	} else {
		wp_redirect( admin_url( 'update-core.php' ) );
		exit;
	}

	$url = 'update.php?action=update-selected-themes&themes=' . urlencode( implode( ',', $themes ) );
	$url = wp_nonce_url( $url, 'bulk-update-themes' );

	// Used in the HTML title tag.
	$title = __( 'Update Themes' );

	require_once ABSPATH . 'wp-admin/admin-header.php';
	?>
	<div class="wrap">
		<h1><?php _e( 'Update Themes' ); ?></h1>
		<iframe src="<?php echo $url; ?>" style="width: 100%; height: 100%; min-height: 750px;" frameborder="0" title="<?php esc_attr_e( 'Update progress' ); ?>"></iframe>
	</div>
	<?php

	wp_localize_script(
		'updates',
		'_wpUpdatesItemCounts',
		array(
			'totals' => wp_get_update_data(),
		)
	);

	require_once ABSPATH . 'wp-admin/admin-footer.php';

} elseif ( 'do-translation-upgrade' === $action ) {

	if ( ! current_user_can( 'update_languages' ) ) {
		wp_die( __( 'Sorry, you are not allowed to update this site.' ) );
	}

	check_admin_referer( 'upgrade-translations' );

	require_once ABSPATH . 'wp-admin/admin-header.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	$url     = 'update-core.php?action=do-translation-upgrade';
	$nonce   = 'upgrade-translations';
	$title   = __( 'Update Translations' );
	$context = WP_LANG_DIR;

	$upgrader = new Language_Pack_Upgrader( new Language_Pack_Upgrader_Skin( compact( 'url', 'nonce', 'title', 'context' ) ) );
	$result   = $upgrader->bulk_upgrade();

	wp_localize_script(
		'updates',
		'_wpUpdatesItemCounts',
		array(
			'totals' => wp_get_update_data(),
		)
	);

	require_once ABSPATH . 'wp-admin/admin-footer.php';

} else {
	/**
	 * Fires for each custom update action on the WordPress Updates screen.
	 *
	 * The dynamic portion of the hook name, `$action`, refers to the
	 * passed update action. The hook fires in lieu of all available
	 * default update actions.
	 *
	 * @since 3.2.0
	 */
	do_action( "update-core-custom_{$action}" );  // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
}
