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
 * Loads the correct template based on the visitor's URL
 *
 * @package WordPress
 */
if ( wp_using_themes() ) {
	/**
	 * Fires before determining which template to load.
	 *
	 * This action hook executes just before WordPress determines which template page to load.
	 * It is a good hook to use if you need to do a redirect with full knowledge of the content
	 * that has been queried.
	 *
	 * Note: Loading a different template is not a good use of this hook. If you include another template
	 * and then use `exit()` or `die()`, no subsequent `template_redirect` hooks will be run, which could
	 * break the site’s functionality. Instead, use the {@see 'template_include'} filter hook to return
	 * the path to the new template you want to use. This will allow an alternative template to be used
	 * without interfering with the WordPress loading process.
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

		/**
		 * Fires immediately before including the template.
		 *
		 * @since 6.9.0
		 *
		 * @param string $template The path of the template about to be included.
		 */
		do_action( 'wp_before_include_template', $template );

		include $template;
		$final_output = ob_get_clean();
		echo calmpress\utils\insert_style_into_html_head( $final_output );
	} elseif ( current_user_can( 'switch_themes' ) ) {
		$theme = wp_get_theme();
		if ( $theme->errors() ) {
			wp_die( $theme->errors() );
		}
	}
	return;
}
