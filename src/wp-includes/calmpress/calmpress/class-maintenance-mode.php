<?php
/**
 * Implementation of maintenance mode related utils.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\calmpress;

/**
 * Maintenance mode urils packaged as a class.
 * 
 * @since 1.0.0
 */
class Maintenance_Mode {

	/**
	 * The name for the post type for for storing the maintenance mode content.
	 *
	 * @since 1.0.0
	 */
	const POST_TYPE_NAME = 'maintenance_mode';

	/**
	 * The name for the transient key storing the maintenance mode content post id.
	 *
	 * @since 1.0.0
	 */
	const POST_ID_CACHE_KEY = '_calmpress_maintenance_mode_post_id';

	/**
	 * The interval for which the maintenance mode content post id should be stored in cache.
	 *
	 * @since 1.0.0
	 */
	const POST_ID_CACHE_INTERVAL = 10 * MINUTE_IN_SECONDS;

	/**
	 * The option name in which the maintenance mode status is stored.
	 *
	 * @since 1.0.0
	 */
	const OPTION_NAME = 'calm_maintenance_mode_type';

	/**
	 * The type value in the option that indicates that maintenace mode is active.
	 *
	 * @since 1.0.0
	 */
	const TYPE_VALUE = 'maintenance_mode';

	/**
	 * The name of the cookie and URL parameter that might include the bypass code.
	 *
	 * @since 1.0.0
	 */
	const BYPASS_NAME = '_maintenance_mode';

	/**
	 * The name of the url parameter and nonce action used in the preview URL.
	 *
	 * @since 1.0.0
	 */
	const PREVIEW_PARAM = 'maintenance_mode_preview';

	/**
	 * Register the post type that will be used for storing the maintenance mode related text.
	 *
	 * @since 1.0.0
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE_NAME,
			array(
				'labels'           => array(
					'name'          => __( 'Maintenance Mode' ),
					'singular_name' => __( 'Maintenance Mode' ),
				),
				'public'           => false,
				'hierarchical'     => false,
				'rewrite'          => false,
				'query_var'        => false,
				'delete_with_user' => false,
				'can_export'       => false,
				'_builtin'         => true, /* internal use only. don't use this when registering your own post type. */
				'supports'         => array( 'title', 'revisions' ),
				'capabilities'     => array(
					'delete_posts'           => 'maintenance_mode',
					'delete_post'            => 'maintenance_mode',
					'delete_published_posts' => 'maintenance_mode',
					'delete_private_posts'   => 'maintenance_mode',
					'delete_others_posts'    => 'maintenance_mode',
					'edit_post'              => 'maintenance_mode',
					'edit_posts'             => 'maintenance_mode',
					'edit_others_posts'      => 'maintenance_mode',
					'edit_published_posts'   => 'maintenance_mode',
					'read_post'              => 'maintenance_mode',
					'read_private_posts'     => 'maintenance_mode',
					'publish_posts'          => 'maintenance_mode',
				),
			)
		);	
	}

	/**
	 * Get the post containing the text for the maintenance mode notice, create one
	 * if none exists.
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_Post The Post.
	 */
	public static function text_holder_post() : \WP_Post {
		$post_id = wp_cache_get( self::POST_ID_CACHE_KEY, 'transient' );
		if ( false === $post_id ) {
			$post_id = 0;
		}

		if ( 0 !== $post_id ) {
			$post = get_post( $post_id );
			// Make sure we didn't get some garbage value.
			if ( $post ) {
				return $post;
			}
		}

		$posts = get_posts(
			[
				'numberposts' => 1,
				'post_type'   => self::POST_TYPE_NAME,
				'post_status' => 'publish',
			]
		);

		if ( ! empty( $posts ) ) {
			// There should be only one...
			wp_cache_set( self::POST_ID_CACHE_KEY, $posts[0]->ID,  'transient', self::POST_ID_CACHE_INTERVAL );
			return $posts[0];
		}

		$post_id = wp_insert_post(
			[
				'post_type'   => self::POST_TYPE_NAME,
				'post_status' => 'publish',
			]
		);

		wp_cache_set( self::POST_ID_CACHE_KEY, $post_id,  'transient', self::POST_ID_CACHE_INTERVAL );
		return get_post( $post_id );
	}

	/**
	 * Activate the maintenance mode and generate a 4 digit bypass code.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		update_option(
			self::OPTION_NAME,
			[
				'type'        => self::TYPE_VALUE,
				'bypass_code' => rand( 1000, 9999 ),
			]
		);
	}

	/**
	 * deactivate the maintenance mode..
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		update_option( self::OPTION_NAME, '' );
	}

	/**
	 * Indicate if maintenance mode is active.
	 * 
	 * It is active when the maintenance mode option type has a maintenance mode value.
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if maintenance mode is active, false otherwise.
	 */
	public static function is_active(): bool {
		$opt = get_option( self::OPTION_NAME, '' );
		if ( ! is_array( $opt ) ) {
			return false;
		}

		if ( isset( $opt['type'] ) && self::TYPE_VALUE === $opt['type'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Retrieve the maintenance mode bypass code.
	 * 
	 * Is meaningful only when maintenance mode is active.
	 * 
	 * @since 1.0.0
	 *
	 * @return string The maintenance mode bypass code or empty string if not configured.
	 */
	public static function bypass_code(): string {
		$opt = get_option( self::OPTION_NAME, '' );
		if ( ! is_array( $opt ) ) {
			return '';
		}

		if ( isset( $opt['type'] ) && self::TYPE_VALUE !== $opt['type'] ) {
			return '';
		}

		return (string) $opt['bypass_code'];
	}

	/**
	 * Helper function to set the bypass cookie, exists mainly to be able to avoid headers
	 * already sent type of errors during testing.
	 *
	 * @since 1.0.0
	 */
	protected static function set_bypass_cookie() {
		setcookie( self::BYPASS_NAME, self::bypass_code(), 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl() );
	}

	/**
	 * Indicate if maintenance mode is active and current user do not have a permission to read or
	 * login to the site when its in such a state.
	 *
	 * Blocked users are ones that do not have the maintenance_mode capability, or not have
	 * the bypass cookie set, and not using a bypass url parameter. For the later, set the cookie.
	 *
	 * In addition, detection of the maintenance mode preview will get request to be treated as blocked.
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if maintenance mode is active for the user, false otherwise.
	 */
	public static function current_user_blocked():bool {

		if ( isset( $_GET[ static::PREVIEW_PARAM ] ) &&
			wp_verify_nonce( $_GET[ static::PREVIEW_PARAM ], static::PREVIEW_PARAM ) ) {
			return true;
		}

		// If not enabled, check that it is not a preview URL. If neither true, the user is not blocked.
		if ( ! self::is_active() ) {
			return false;
		}

		// Users with the capability are not blocked.
		if ( current_user_can( 'maintenance_mode' ) ) {
			return false;
		}

		// Users with the cookie set with the correct bypass code are not blocked.
		if ( isset( $_COOKIE[ self::BYPASS_NAME ] ) &&
			( $_COOKIE[ self::BYPASS_NAME ] === self::bypass_code() ) ) {
			return false;
		}

		// Users with the url parameter set with the correct bypass code are not blocked.
		if ( isset( $_GET[ self::BYPASS_NAME ] ) &&
			( $_GET[ self::BYPASS_NAME ] === self::bypass_code() ) ) {
			// Set the cookie only for the session.
			static::set_bypass_cookie();
			return false;
		}

		return true;
	}

	/**
	 * A shortcode callback for the maintenance_left short code. Returns the expected time left
	 * in the maintenance mode as a localized string.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $attributes The attributes supplied with the shortcode (ignored here).
	 *
	 * @return string The text that shows the remaining time until maintenance mode expires.
	 */
	public static function maintenance_left_shortcode( $attributes ) {
		$lasts_for = static::projected_time_till_end();
		$hours     = intdiv( $lasts_for, 60 * MINUTE_IN_SECONDS );
		$minutes   = sprintf( '%02d', intdiv( $lasts_for % ( 60 * MINUTE_IN_SECONDS ), 60 ) );

		$human_readable_duration = [];

		// Add the hour part to the string if not zero.
		if ( 0 !== $hours ) {
			/* translators: %s: Time duration in hour or hours. */
			$human_readable_duration[] = sprintf( _n( '%s hour', '%s hours', $hours ), $hours );
		}
	
		// Add the minute part to the string.
		/* translators: %s: Time duration in minute or minutes. */
		$human_readable_duration[] = sprintf( _n( '%s minute', '%s minutes', $minutes ), $minutes );
	
		return implode( ', ', $human_readable_duration );
	}

	/**
	 * Generate the maintenance mode page HTML.
	 *
	 * @since 1.0.0
	 */
	public static function render_html() {
		global $wp_query;

		// Make sure page title is set based on configuration.
		add_filter(	'document_title', __NAMESPACE__ . '\Maintenance_Mode::page_title', 999 );
		
		// Add maintenance_mode class to body to allow styling.
		add_filter(
			'body_class',
			static function ( $classes ) {
				$classes[] = 'maintenance_mode';
				return $classes;
			}
		);

		// Add shortcode to insert projected time left in maintenance mode.
		add_shortcode( 'maintenance_left' , __NAMESPACE__ . '\Maintenance_Mode::maintenance_left_shortcode' );

		if ( static::theme_frame_used() ) {
			$template = get_query_template( 'page', ['page.php'] );
			include $template;
		} else {
			?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js no-svg">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body class="maintenance_mode">
	<div class="message_container" style="max-width:600px; text-align:center; margin:10px auto">
			<?php
			$h1  = static::text_title();
			if ( $h1 ) {
				echo '<h1>' . esc_html( $h1 ) . '</h1>';
			}
			echo apply_filters( 'the_content', static::content() );
			wp_footer();
			?>
	</div>
</body>
</html>
			<?php
		}
	}

	/**
	 * Set the main loop to include the maintenance mode post if not processing feeds or favicon.
	 *
	 * Supposed to be called by the posts_pre_query filter when in maintenance mode.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post[] $posts The array current posts probably from previous runs of the filter.
	 *                          If there are any they are overriden.
	 * @param \WP_Query  $query The query for which the filter is run.
	 *
	 * @return null|\WP_Post[]  The maintenance mode post in an array if $query is the main query,
	 *                          otherwise whatever was passed in $posts.
	 */
	public static function setup_wp_query( $posts, $query ) {

		// Handle only on the main query to avoid recurssion when getting
		// the maintenance mode post.
		if ( $query->is_main_query() ) {
			$maintenance_post = static::text_holder_post();
			if ( ! $query->is_feed && ! $query->is_favicon ) {
				// Needed at least for calmSeventeen to produce the same design on all pages.
				$query->is_home = false;
				$query->is_page = true;

				// Set the queried object to avoid php errors when it is being checked.
				$query->queried_object    = $maintenance_post;
				$query->queried_object_id = (int) $maintenance_post->ID;
			}
			return [ $maintenance_post ];
		}

		return $posts;
	}

	/**
	 * Set the server time in seconds when the maintenance mode is expected to end.
	 *
	 * @since 1.0.0
	 *
	 * @param int $time The time.
	 */
	public static function set_projected_end_time( int $time ) {
		$p = static::text_holder_post();
		update_post_meta( $p->ID, 'end_time', $time );
	}

	/**
	 * Get the time in seconds until the maintenance mode is expected to end. If the configured time
	 * is less than 30 minute in the future, will retun 30 minutes.
	 *
	 * @since 1.0.0
	 *
	 * @return int The time.
	 */
	public static function projected_time_till_end(): int {
		$p        = static::text_holder_post();
		$end_time = (int) get_post_meta( $p->ID, 'end_time', true );
		$interval = $end_time - time();
		if ( $interval < 10 * MINUTE_IN_SECONDS ) {
			$interval = 10 * MINUTE_IN_SECONDS;
		}

		return $interval;
	}

	/**
	 * Set the text used in the title element of the maintenance mode page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title The text to set to.
	 */
	public static function set_page_title( string $title ) {
		$p = static::text_holder_post();
		update_post_meta( $p->ID, 'page_title', wp_slash( $title ) );
	}

	/**
	 * Get the text used in the title element of the maintenance mode page.
	 *
	 * @since 1.0.0
	 *
	 * @return string The text that will be used.
	 */
	public static function page_title():string {
		$p     = static::text_holder_post();
		$title = (string) get_post_meta( $p->ID, 'page_title', true );

		return $title;
	}

	/**
	 * Set the text used in the h1 element of the maintenance mode page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title The text to set to.
	 */
	public static function set_text_title( string $title ) {
		$p = static::text_holder_post();
		wp_update_post(
			[
				'ID' => $p->ID,
				'post_title' => wp_slash( $title ),
			]
		);
	}

	/**
	 * Get the text used in the h1 element of the maintenance mode page.
	 *
	 * @since 1.0.0
	 *
	 * @return string The text that will be used.
	 */
	public static function text_title():string {
		$p = static::text_holder_post();

		return $p->post_title;
	}

	/**
	 * Set wheather the active theme is used when rendering the maintenance mode page.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $use true if the theme should be used, false when it should not.
	 */
	public static function set_use_theme_frame( bool $use ) {
		$p = static::text_holder_post();
		update_post_meta( $p->ID, 'use_theme', (int) $use );
	}

	/**
	 * Get wheather the active theme is used when rendering the maintenance mode page.
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if theme is used, otherwise false.
	 */
	public static function theme_frame_used():bool {
		$p    = static::text_holder_post();
		$used = (int) get_post_meta( $p->ID, 'use_theme', true );

		return 0 !== $used;
	}

	/**
	 * Set the content to use in the maintenance mode page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The text to set to.
	 */
	public static function set_content( string $content ) {
		$p = static::text_holder_post();
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$content =  wp_kses_post( $content );
		}
		wp_update_post(
			[
				'ID' => $p->ID,
				'post_content' => wp_slash( $content ),
			]
		);
	}

	/**
	 * Get the content that will be used in the maintenance mode page.
	 *
	 * @since 1.0.0
	 *
	 * @return string The text that will be used.
	 */
	public static function content():string {
		$p = static::text_holder_post();

		return $p->post_content;
	}

	/**
	 * Verify capability, nonce, and validitty of referer data a POST request. Die if the
	 * user is not allowed to changed maintenance mode related data, or nonce/referer include
	 * bad data. 
	 *
	 * @since 1.0.0
	 *
	 * @param string $action The name of the action expected to be used for generating the nonce
	 *                       and admin referer fields in the request.
	 */
	private static function verify_post_request( string $action ) {
		if ( ! current_user_can( 'maintenance_mode' ) ) {
			wp_die(
				'<h1>' . __( 'You need additional permission.' ) . '</h1>' .
				'<p>' . __( 'Sorry, you are not allowed to manage maintenance mode for this site.' ) . '</p>',
				403
			);
		}
		check_admin_referer( $action );
	}

	/**
	 * Handles the form post regarding content related maintenance page changes. Updates the
	 * post holding the content data.
	 *
	 * Used as a hook on admin-post.
	 *
	 * @since 1.0.0
	 */
	public static function handle_content_change_post() {

		static::verify_post_request( 'maintenance_mode_content' );

		if ( ! isset( $_POST['page_title'] ) || ! isset( $_POST['text_title'] ) || ! isset( $_POST['message_text'] ) ) {
			add_settings_error(
				'maintenance_mode_content',
				'maintenance_mode_content',
				esc_html__( 'Something went wrong, please try again' ),
				'error'
			);
		} else {
			static::set_page_title( wp_unslash( $_POST['page_title'] ) );
			static::set_text_title( wp_unslash( $_POST['text_title'] ) );
			static::set_content( wp_unslash( $_POST['message_text'] ) );
			static::set_use_theme_frame( isset( $_POST['theme_page'] ) );
			add_settings_error(
				'maintenance_mode_content',
				'settings_updated',
				__( 'Settings saved.' ),
				'success'
			);
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );	
	
		// Redirect back to the settings page that was submitted.
		$goback = add_query_arg( 'settings-updated', 'true', wp_get_referer() );
		wp_redirect( $goback );
		exit;			
	}

	/**
	 * Handle the form post regarding maintenance mode (de)activation. Updates the activation state
	 * and/or the interval till expected end of it.
	 *
	 * Used as a hook on admin-post.
	 *
	 * @since 1.0.0
	 */
	public static function handle_status_change_post() {
		static::verify_post_request( 'maintenance_mode_status' );

		// Check basic validity.
		if ( ! isset( $_POST['hours'] ) || ! isset( $_POST['minutes'] ) ) {
			add_settings_error(
				'maintenance_mode_status',
				'maintenance_mode_status',
				esc_html__( 'Something went wrong, please try again' ),
				'error'
			);
		} else {
			// Not putting much effort in validating the values as out of expected range
			// values can not do any harm.
			$hours    = (int) wp_unslash( $_POST['hours'] );
			$minutes  = (int) wp_unslash( $_POST['minutes'] );
			$end_time = time() + ( 60 * $hours + $minutes ) * 60;
			static::set_projected_end_time( $end_time );

			if ( isset( $_POST['enter'] ) ) {
				static::activate();
			}

			if ( isset( $_POST['exit'] ) ) {
				static::deactivate();
			}
			
			add_settings_error(
				'maintenance_mode_status',
				'settings_updated',
				__( 'Staus updated.' ),
				'success'
			);
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );	
	
		// Redirect back to the settings page that was submitted.
		$goback = add_query_arg( 'settings-updated', 'true', wp_get_referer() );
		wp_redirect( $goback );
		exit;			
	}
}
