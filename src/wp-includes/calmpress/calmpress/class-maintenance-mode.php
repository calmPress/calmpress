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

		return $opt['bypass_code'];
	}

	/**
	 * Indicate if maintenance mode is active and current user do not have a permission to read or
	 * login to the site when its in such a state.
	 *
	 * Blocked users are ones that do not have the maintenance_mode capability, to not have
	 * the bypass cookie set, and not using a bypass url parameter. For the later, set the cookie.
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if maintenance mode is active for the user, false otherwise.
	 */
	public static function current_user_blocked():bool {

		// If not enabled, no reason to block.
		if ( ! self::is_active() ) {
			return false;
		}

		// Users with the capability are not blocked.
		if ( current_user_can( 'maintenance_mode') ) {
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
			setcookie( self::BYPASS_NAME, self::bypass_code(), 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl() );
			return false;
		}

		return true;
	}

	/**
	 * Get the maintenance page HTML.
	 *
	 * @since 1.0.0
	 *
	 * @return string The HTML.
	 */
	public static function page_html(): string {

	}

	/**
	 * Get the maintenance page Title.
	 *
	 * @since 1.0.0
	 *
	 * @return string The HTML.
	 */
	public static function page_title(): string {
		$p     = static::text_holder_post();
		$title = get_post_meta( $p->ID, 'title', true );
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
		$errors = [];
		static::verify_post_request( 'maintenance_mode_status' );

		// Check basic validity.
		if ( ! isset( $_POST['hours'] ) || ! isset( $_POST['minutes'] ) ) {
			$errors[] = esc_html__( 'Something went wrong, please try again' );
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
		}

		set_transient( 'maintenance_mode_errors', $errors, 30 );	
	
		// Redirect back to the settings page that was submitted.
		$goback = add_query_arg( 'settings-updated', 'true', wp_get_referer() );
		wp_redirect( $goback );
		exit;			
	}
}
