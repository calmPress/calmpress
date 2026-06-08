<?php
/**
 * Network options saving handler
 *
 * This file is the target of the forms in core and custom options pages
 * that use the Settings API for network related options. In this case it saves the new option values
 * and returns the user to their page of origin.
 *
 * @since calmPress 1.0.0
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

$option_page = ! empty( $_REQUEST['option_page'] ) ? sanitize_text_field( $_REQUEST['option_page'] ) : '';

$capability = 'manage_network_options';

/**
 * Filters the capability required when using the Settings API on a network options page.
 *
 * By default, the options groups for all registered settings require the manage_network_options capability.
 * This filter is required to change the capability required for a certain options page.
 *
 * @since 1.0.0
 *
 * @param string $capability The capability used for the page, which is manage_network_options by default.
 */
$capability = apply_filters( "network_option_page_capability_{$option_page}", $capability );


if ( ! current_user_can( $capability ) ) {
	wp_die(
		'<h1>' . __( 'You need a higher level of permission.' ) . '</h1>' .
		'<p>' . __( 'Sorry, you are not allowed to manage options for this site.' ) . '</p>',
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
		'admin_email'
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
		'blog_public',
		'calm_embedding_on',
	),
	'htaccess'   => [
		'htaccess_user_section',
	],
	'robots_txt'   => [
		'robots_txt',
	],
	'email_delivery' => [
		'calm_email_delivery',
	]
);

/**
 * Filters the allowed options list.
 *
 * @since 1.0.0
 *
 * @param array $allowed_options The allowed options list.
 */
$allowed_options = apply_filters( 'allowed_network_options', $allowed_options );

check_admin_referer( $option_page . '-options' );

if ( ! isset( $allowed_options[ $option_page ] ) ) {
	wp_die(
		sprintf(
			/* translators: %s: The options page name. */
			__( '<strong>Error:</strong> The %s options page is not in the allowed options list.' ),
			'<code>' . esc_html( $option_page ) . '</code>'
		)
	);
}

$options = $allowed_options[ $option_page ];

if ( $options ) {
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
		update_site_option( $option, $value );
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
}

set_transient( 'settings_errors', get_settings_errors(), 30 ); // 30 seconds.

// Redirect back to the settings page that was submitted.
$goback = add_query_arg( 'settings-updated', 'true', wp_get_referer() );
wp_redirect( $goback );
exit;