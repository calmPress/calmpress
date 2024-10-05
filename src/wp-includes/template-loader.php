<?php

// If in maintenace mode just echo the maintenance mode text and 503 code.
// Only exception is the favicon that is handled normally.
if ( \calmpress\calmpress\Maintenance_Mode::current_user_blocked() ) {
	if ( is_favicon() ) {
		/**
		 * Fired when the template loader determines a favicon.ico request.
		 *
		 * @since 5.4.0
		 */
		do_action( 'do_favicon' );
		return;
	}

	header( 'Retry-After: ' . \calmpress\calmpress\Maintenance_Mode::projected_time_till_end() );
	status_header( 503 );
	if ( is_feed() ) {
		return;
	}

	\calmpress\calmpress\Maintenance_Mode::render_html();
	return;
}

/**
 * Loads the correct template based on the visitor's url
 *
 * @package WordPress
 */
if ( wp_using_themes() ) {
	/**
	 * Fires before determining which template to load.
	 *
	 * @since 1.5.0
	 */
	do_action( 'template_redirect' );
}

/**
 * Filters whether to allow 'HEAD' requests to generate content.
 *
 * Provides a significant performance bump by exiting before the page
 * content loads for 'HEAD' requests. See #14348.
 *
 * @since 3.5.0
 *
 * @param bool $exit Whether to exit without generating any content for 'HEAD' requests. Default true.
 */
if ( 'HEAD' === $_SERVER['REQUEST_METHOD'] && apply_filters( 'exit_on_http_head', true ) ) {
	exit;
}

// Process feeds even if not using themes.
if ( is_robots() ) {
	/**
	 * Fired when the template loader determines a robots.txt request.
	 *
	 * @since 2.1.0
	 */
	do_action( 'do_robots' );
	return;
} elseif ( is_favicon() ) {
	/**
	 * Fired when the template loader determines a favicon.ico request.
	 *
	 * @since 5.4.0
	 */
	do_action( 'do_favicon' );
	return;
} elseif ( is_feed() ) {
	do_feed();
	return;
}

if ( wp_using_themes() ) {

	$tag_templates = array(
		'is_embed'             => 'get_embed_template',
		'is_404'               => 'get_404_template',
		'is_search'            => 'get_search_template',
		'is_front_page'        => 'get_front_page_template',
		'is_home'              => 'get_home_template',
		'is_privacy_policy'    => 'get_privacy_policy_template',
		'is_post_type_archive' => 'get_post_type_archive_template',
		'is_tax'               => 'get_taxonomy_template',
		'is_single'            => 'get_single_template',
		'is_page'              => 'get_page_template',
		'is_singular'          => 'get_singular_template',
		'is_category'          => 'get_category_template',
		'is_tag'               => 'get_tag_template',
		'is_date'              => 'get_date_template',
		'is_archive'           => 'get_archive_template',
	);
	$template      = false;

	// Loop through each of the template conditionals, and find the appropriate template file.
	foreach ( $tag_templates as $tag => $template_getter ) {
		if ( call_user_func( $tag ) ) {
			$template = call_user_func( $template_getter );
		}

		if ( $template ) {
			break;
		}
	}

	if ( ! $template ) {
		$template = get_index_template();
	}

	/**
	 * Filters the path of the current template before including it.
	 *
	 * @since 3.0.0
	 *
	 * @param string $template The path of the template to include.
	 */
	$template = apply_filters( 'template_include', $template );
	if ( $template ) {
		/*
		* The buffering around include( $template ) have two goals
		* 1. Buffer the output to be able to add a noopener and noreferer
		*    to links that open in new window.
		* 2. Make it easier to do redirects or emit any other header
		*    at any point during the generation of the HTML
		*/
		ob_start();
		include $template;
		$final_output = wp_targeted_link_rel( ob_get_clean() );
		$position = strpos( $final_output, '</head>' );

		// Verify we handling a proper HTML including an head element before adding CSS in the header.
        if ( $position === false ) {
			calmpress\logger\Controller::log_warning_message(
				'could not find </head> in the genrated HTML, you should check it is there and with lower case and no extra spaces',
				__FILE__,
				__LINE__,
				get_current_user_id(),
				'',
				calmpress\logger\Controller::request_info( 20 )
			);
		} else {
            // fetch the CSS links and inlines just the way it would have been done as a wp_head action.

			ob_start();
			wp_maybe_inline_styles();
			wp_print_styles();
			print_late_styles();
			$css = ob_get_clean();
			
            $final_output = substr_replace( $final_output, $css . '</head>', $position, strlen( '</head>' ) );
        }
		echo $final_output;
	} elseif ( current_user_can( 'switch_themes' ) ) {
		$theme = wp_get_theme();
		if ( $theme->errors() ) {
			wp_die( $theme->errors() );
		}
	}
	return;
}
