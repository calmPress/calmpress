<?php
/**
 * Options Management Administration Endpoint.
 *
 * This file is the target of forms in core and custom options pages that use
 * the Settings API. It saves submitted option values and returns the user to
 * their page of origin.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
	exit;
}

$action      = ! empty( $_REQUEST['action'] ) ? sanitize_text_field( $_REQUEST['action'] ) : '';
$option_page = ! empty( $_REQUEST['option_page'] ) ? sanitize_text_field( $_REQUEST['option_page'] ) : '';

$capability = 'manage_options';

// This is for back compat and will eventually be removed.
if ( empty( $option_page ) ) {
	$option_page = 'options';
} else {

	/**
	 * Filters the capability required when using the Settings API.
	 *
	 * By default, the options groups for all registered settings require the manage_options capability.
	 * This filter is required to change the capability required for a certain options page.
	 *
	 * @since 3.2.0
	 *
	 * @param string $capability The capability used for the page, which is manage_options by default.
	 */
	$capability = apply_filters( "option_page_capability_{$option_page}", $capability );
}

if ( ! current_user_can( $capability ) ) {
	wp_die(
		'<h1>' . __( 'You need a higher level of permission.' ) . '</h1>' .
		'<p>' . __( 'Sorry, you are not allowed to manage options for this site.' ) . '</p>',
		403
	);
}

if ( is_multisite() && ! current_user_can( 'manage_network_options' ) && 'update' !== $action ) {
	wp_die(
		'<h1>' . __( 'You need a higher level of permission.' ) . '</h1>' .
		'<p>' . __( 'Sorry, you are not allowed to delete these items.' ) . '</p>',
		403
	);
}

$allowed_options            = array(
	'general'    => array(
		'blogname',
		'blogdescription',
		'custom_logo',
		'site_icon',
		'gmt_offset',
		'date_format',
		'time_format',
		'start_of_week',
		'timezone_string',
		'WPLANG',
		'admin_user_id',
	),
	'discussion' => array(
		'default_comment_status',
		'comments_notify',
		'comment_moderation',
		'comment_previously_approved',
		'comment_max_links',
		'show_avatars',
		'thread_comments',
		'thread_comments_depth',
		'comment_order',
		'comment_moderator_user',
	),
	'media'      => array(
		'thumbnail_size_w',
		'thumbnail_size_h',
		'thumbnail_crop',
		'medium_size_w',
		'medium_size_h',
		'large_size_w',
		'large_size_h',
		'image_default_size',
		'image_default_align',
		'image_default_link_type',
	),
	'reading'    => array(
		'posts_per_page',
		'posts_per_rss',
		'rss_use_excerpt',
		'show_on_front',
		'page_on_front',
		'page_for_posts',
		'calm_embedding_on',
	),
	'htaccess'   => [
		'htaccess_user_section',
	],
	'wp-config'   => [
		'wp_config_user_section',
	],
	'email_delivery' => [
		'calm_email_delivery',
	]
);
$allowed_options['misc']    = array();
$allowed_options['options'] = array();
$allowed_options['privacy'] = array();

if ( ! is_utf8_charset() ) {
	$allowed_options['reading'][] = 'blog_charset';
}

if ( ! is_multisite() ) {
	if ( ! defined( 'WP_HOME' ) ) {
		$allowed_options['general'][] = 'home';
	}

	$allowed_options['media'][] = 'uploads_use_yearmonth_folders';

	/*
	 * If upload_url_path is not the default (empty),
	 * or upload_path is not the default ('wp-content/uploads' or empty),
	 * they can be edited, otherwise they're locked.
	 */
	if ( get_option( 'upload_url_path' )
		|| get_option( 'upload_path' ) && 'wp-content/uploads' !== get_option( 'upload_path' )
	) {
		$allowed_options['media'][] = 'upload_path';
		$allowed_options['media'][] = 'upload_url_path';
	}
}

/**
 * Filters the allowed options list.
 *
 * @since 5.5.0
 *
 * @param array $allowed_options The allowed options list.
 */
$allowed_options = apply_filters( 'allowed_options', $allowed_options );

if ( 'update' === $action ) { // We are saving settings sent from a settings page.
	if ( 'options' === $option_page && ! isset( $_POST['option_page'] ) ) { // This is for back compat and will eventually be removed.
		$unregistered = true;
		check_admin_referer( 'update-options' );
	} else {
		$unregistered = false;
		check_admin_referer( $option_page . '-options' );
	}

	if ( ! isset( $allowed_options[ $option_page ] ) ) {
		wp_die(
			sprintf(
				/* translators: %s: The options page name. */
				__( '<strong>Error:</strong> The %s options page is not in the allowed options list.' ),
				'<code>' . esc_html( $option_page ) . '</code>'
			)
		);
	}

	if ( 'options' === $option_page ) {
		if ( is_multisite() && ! current_user_can( 'manage_network_options' ) ) {
			wp_die( __( 'Sorry, you are not allowed to modify unregistered settings for this site.' ) );
		}
		$options = isset( $_POST['page_options'] ) ? explode( ',', wp_unslash( $_POST['page_options'] ) ) : null;
	} else {
		$options = $allowed_options[ $option_page ];
	}

	if ( 'general' === $option_page ) {
		// Handle custom date/time formats.
		if ( ! empty( $_POST['date_format'] ) && isset( $_POST['date_format_custom'] )
			&& '\c\u\s\t\o\m' === wp_unslash( $_POST['date_format'] )
		) {
			$_POST['date_format'] = $_POST['date_format_custom'];
		}

		if ( ! empty( $_POST['time_format'] ) && isset( $_POST['time_format_custom'] )
			&& '\c\u\s\t\o\m' === wp_unslash( $_POST['time_format'] )
		) {
			$_POST['time_format'] = $_POST['time_format_custom'];
		}

		// Map UTC+- timezones to gmt_offsets and set timezone_string to empty.
		if ( ! empty( $_POST['timezone_string'] ) && preg_match( '/^UTC[+-]/', $_POST['timezone_string'] ) ) {
			$_POST['gmt_offset']      = $_POST['timezone_string'];
			$_POST['gmt_offset']      = preg_replace( '/UTC\+?/', '', $_POST['gmt_offset'] );
			$_POST['timezone_string'] = '';
		} elseif ( isset( $_POST['timezone_string'] ) && ! in_array( $_POST['timezone_string'], timezone_identifiers_list( DateTimeZone::ALL_WITH_BC ), true ) ) {
			// Reset to the current value.
			$current_timezone_string = get_option( 'timezone_string' );

			if ( ! empty( $current_timezone_string ) ) {
				$_POST['timezone_string'] = $current_timezone_string;
			} else {
				$_POST['gmt_offset']      = get_option( 'gmt_offset' );
				$_POST['timezone_string'] = '';
			}

			add_settings_error(
				'general',
				'settings_updated',
				__( 'The timezone you have entered is not valid. Please select a valid timezone.' ),
				'error'
			);
		}
	}

	if ( $options ) {
		$option_values     = [];
		$user_language_old = get_user_locale();

		foreach ( $options as $option ) {

			$option = trim( $option );
			$value  = null;
			if ( isset( $_POST[ $option ] ) ) {
				$value = $_POST[ $option ];
				if ( ! is_array( $value ) ) {
					$value = trim( $value );
				}
				$value = wp_unslash( $value );
			}
			$option_values[ $option ] = $value;
		}

		/**
		 * Validate submitted settings values before they are saved.
		 *
		 * The filter receives the values submitted for all options registered to the
		 * current settings page. Callbacks should inspect the submitted values and add
		 * any validation failures to the supplied WP_Error object.
		 *
		 * Returning a WP_Error containing one or more errors will prevent all options
		 * on the settings page from being updated. Validation errors are displayed to
		 * the user using the Settings API error handling mechanism.
		 *
		 * @since 1.0.0
		 *
		 * @param WP_Error $errors Validation errors detected so far.
		 * @param array    $option_values Submitted option values keyed by option name.
		 *                         All registered options are always present in the array;
		 *                         options not included in the submission will have a value of null.
		 * @return WP_Error Validation errors.
		 */
		$validation_errors = apply_filters( 'check_input_errors_' . $option_page, new WP_Error(), $option_values );
		$id = 0;
		foreach ( $validation_errors->get_error_messages() as $message ) {
			add_settings_error( $option_page, 'setting_error_' . $id++, $message, 'error' );
		}

		if ( ! $validation_errors->has_errors() ) {
			foreach ( $option_values as $k => $v ) {
				if ( $v !== null ) {
					update_option( $k, $v );
				}
			}

			/*
			* Switch translation in case WPLANG was changed.
			* The global $locale is used in get_locale() which is
			* used as a fallback in get_user_locale().
			*/
			unset( $GLOBALS['locale'] );
			$user_language_new = get_user_locale();
			if ( $user_language_old !== $user_language_new ) {
				load_default_textdomain( $user_language_new );
			}
		} else {
			set_transient( get_current_user_id() . '_save_failure_' . $option_page, $option_values, 30 );
		}
	} else {
		add_settings_error( 'general', 'settings_updated', __( 'Settings save failed.' ), 'error' );
	}

	/*
	 * Handle settings errors and return to options page.
	 */

	// If no settings errors were registered add a general 'updated' message.
	if ( ! count( get_settings_errors() ) ) {
		add_settings_error( 'general', 'settings_updated', __( 'Settings saved.' ), 'success' );
		delete_transient( get_current_user_id() . ':save_failure:' . $option_page );
	}

	set_transient( 'settings_errors', get_settings_errors(), 30 ); // 30 seconds.

	// Redirect back to the settings page that was submitted.
	$goback = add_query_arg( 'settings-updated', 'true', wp_get_referer() );
	wp_redirect( $goback );
	exit;
}

exit;
