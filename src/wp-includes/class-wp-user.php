<?php
/**
 * User API: WP_User class
 *
 * @package WordPress
 * @subpackage Users
 * @since 4.4.0
 */

use calmpress\email\Email_Address;

/**
 * Core class used to implement the WP_User object.
 *
 * @since 2.0.0
 *
 * @property string $nickname
 * @property string $description
 * @property string $user_description
 * @property string $first_name
 * @property string $user_firstname
 * @property string $last_name
 * @property string $user_lastname
 * @property string $user_login
 * @property string $user_pass
 * @property string $user_nicename
 * @property string $user_email
 * @property string $user_url Mostly for backward compatibility with wordpress
 * @property string $user_registered
 * @property string $user_activation_key
 * @property string $user_status
 * @property int    $user_level
 * @property string $display_name
 * @property string $spam
 * @property string $deleted
 * @property string $locale
 * @property string $use_ssl
 */
class WP_User implements \calmpress\avatar\Has_Avatar {
	/**
	 * User data container.
	 *
	 * @since 2.0.0
	 * @var stdClass
	 */
	public $data;

	/**
	 * The user's ID.
	 *
	 * @since 2.1.0
	 * @var int
	 */
	public $ID = 0;

	/**
	 * Capabilities that the individual user has been granted outside of those inherited from their role.
	 *
	 * @since 2.0.0
	 * @var bool[] Array of key/value pairs where keys represent a capability name
	 *             and boolean values represent whether the user has that capability.
	 */
	public $caps = array();

	/**
	 * User metadata option name.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public $cap_key;

	/**
	 * The roles the user is part of.
	 *
	 * @since 2.0.0
	 * @var string[]
	 */
	public $roles = array();

	/**
	 * All capabilities the user has, including individual and role based.
	 *
	 * @since 2.0.0
	 * @var bool[] Array of key/value pairs where keys represent a capability name
	 *             and boolean values represent whether the user has that capability.
	 */
	public $allcaps = array();

	/**
	 * The filter context applied to user data fields.
	 *
	 * @since 2.9.0
	 * @var string
	 */
	public $filter = null;

	/**
	 * The site ID the capabilities of this user are initialized for.
	 *
	 * @since 4.9.0
	 * @var int
	 */
	private $site_id = 0;

	/**
	 * @since 3.3.0
	 * @var array
	 */
	private static $back_compat_keys;

	/**
	 * The user meta key in which the avatar attachment ID is stored.
	 * @since calmPress 1.0.0
	 */
	const AVATAR_ATTACHMENT_ID = 'calm_avatar_id';

	/**
	 * Constructor.
	 *
	 * Retrieves the userdata and passes it to WP_User::init().
	 *
	 * @since 2.0.0
	 *
	 * @param int|string|stdClass|WP_User $id      User's ID, a WP_User object, or a user object from the DB.
	 * @param string                      $name    Optional. User's username
	 * @param int                         $site_id Optional Site ID, defaults to current site.
	 */
	public function __construct( $id = 0, $name = '', $site_id = '' ) {
		if ( ! isset( self::$back_compat_keys ) ) {
			$prefix                 = $GLOBALS['wpdb']->prefix;
			self::$back_compat_keys = array(
				'user_firstname'             => 'first_name',
				'user_lastname'              => 'last_name',
				'user_description'           => 'description',
				'user_level'                 => $prefix . 'user_level',
				$prefix . 'usersettings'     => $prefix . 'user-settings',
				$prefix . 'usersettingstime' => $prefix . 'user-settings-time',
			);
		}

		if ( $id instanceof WP_User ) {
			$this->init( $id->data, $site_id );
			return;
		} elseif ( is_object( $id ) ) {
			$this->init( $id, $site_id );
			return;
		}

		if ( ! empty( $id ) && ! is_numeric( $id ) ) {
			$name = $id;
			$id   = 0;
		}

		if ( $id ) {
			$data = self::get_data_by( 'id', $id );
		} else {
			$data = self::get_data_by( 'login', $name );
		}

		if ( $data ) {
			$this->init( $data, $site_id );
		} else {
			$this->data = new stdClass;
		}
	}

	/**
	 * Sets up object properties, including capabilities.
	 *
	 * @since 3.3.0
	 *
	 * @param object $data    User DB row object.
	 * @param int    $site_id Optional. The site ID to initialize for.
	 */
	public function init( $data, $site_id = '' ) {
		if ( ! isset( $data->ID ) ) {
			$data->ID = 0;
		}
		$this->data = $data;
		$this->ID   = (int) $data->ID;

		$this->for_site( $site_id );
	}

	/**
	 * Return only the main user fields
	 *
	 * @since 3.3.0
	 * @since 4.4.0 Added 'ID' as an alias of 'id' for the `$field` parameter.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string     $field The field to query against: 'id', 'ID', 'slug', 'email' or 'login'.
	 * @param string|int $value The field value
	 * @return object|false Raw user object
	 */
	public static function get_data_by( $field, $value ) {
		global $wpdb;

		// 'ID' is an alias of 'id'.
		if ( 'ID' === $field ) {
			$field = 'id';
		}

		if ( 'id' === $field ) {
			// Make sure the value is numeric to avoid casting objects, for example,
			// to int 1.
			if ( ! is_numeric( $value ) ) {
				return false;
			}
			$value = (int) $value;
			if ( $value < 1 ) {
				return false;
			}
		} else {
			$value = trim( $value );
		}

		if ( ! $value ) {
			return false;
		}

		switch ( $field ) {
			case 'id':
				$user_id  = $value;
				$db_field = 'ID';
				break;
			case 'slug':
				$user_id  = wp_cache_get( $value, 'userslugs' );
				$db_field = 'user_nicename';
				break;
			case 'email':
				$user_id  = wp_cache_get( $value, 'useremail' );
				$db_field = 'user_email';
				break;
			case 'login':
				$value    = sanitize_user( $value );
				$user_id  = wp_cache_get( $value, 'userlogins' );
				$db_field = 'user_login';
				break;
			default:
				return false;
		}

		if ( false !== $user_id ) {
			$user = wp_cache_get( $user_id, 'users' );
			if ( $user ) {
				return $user;
			}
		}

		$user = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $wpdb->users WHERE $db_field = %s LIMIT 1",
				$value
			)
		);
		if ( ! $user ) {
			return false;
		}

		update_user_caches( $user );

		return $user;
	}

	/**
	 * Magic method for checking the existence of a certain custom field.
	 *
	 * @since 3.3.0
	 *
	 * @param string $key User meta key to check if set.
	 * @return bool Whether the given user meta key is set.
	 */
	public function __isset( $key ) {

		if ( isset( $this->data->$key ) ) {
			return true;
		}

		if ( isset( self::$back_compat_keys[ $key ] ) ) {
			$key = self::$back_compat_keys[ $key ];
		}

		return metadata_exists( 'user', $this->ID, $key );
	}

	/**
	 * Magic method for accessing custom fields.
	 *
	 * @since 3.3.0
	 *
	 * @param string $key User meta key to retrieve.
	 * @return mixed Value of the given user meta key (if set). If `$key` is 'id', the user ID.
	 */
	public function __get( $key ) {

		if ( isset( $this->data->$key ) ) {
			$value = $this->data->$key;
		} else {
			if ( isset( self::$back_compat_keys[ $key ] ) ) {
				$key = self::$back_compat_keys[ $key ];
			}
			$value = get_user_meta( $this->ID, $key, true );
		}

		if ( $this->filter ) {
			$value = sanitize_user_field( $key, $value, $this->ID, $this->filter );
		}

		return $value;
	}

	/**
	 * Magic method for setting custom user fields.
	 *
	 * This method does not update custom fields in the database. It only stores
	 * the value on the WP_User instance.
	 *
	 * @since 3.3.0
	 *
	 * @param string $key   User meta key.
	 * @param mixed  $value User meta value.
	 */
	public function __set( $key, $value ) {
		$this->data->$key = $value;
	}

	/**
	 * Magic method for unsetting a certain custom field.
	 *
	 * @since 4.4.0
	 *
	 * @param string $key User meta key to unset.
	 */
	public function __unset( $key ) {
		if ( isset( $this->data->$key ) ) {
			unset( $this->data->$key );
		}

		if ( isset( self::$back_compat_keys[ $key ] ) ) {
			unset( self::$back_compat_keys[ $key ] );
		}
	}

	/**
	 * Determine whether the user exists in the database.
	 *
	 * @since 3.4.0
	 *
	 * @return bool True if user exists in the database, false if not.
	 */
	public function exists() {
		return ! empty( $this->ID );
	}

	/**
	 * Retrieve the value of a property or meta key.
	 *
	 * Retrieves from the users and usermeta table.
	 *
	 * @since 3.3.0
	 *
	 * @param string $key Property
	 * @return mixed
	 */
	public function get( $key ) {
		return $this->__get( $key );
	}

	/**
	 * Determine whether a property or meta key is set
	 *
	 * Consults the users and usermeta tables.
	 *
	 * @since 3.3.0
	 *
	 * @param string $key Property
	 * @return bool
	 */
	public function has_prop( $key ) {
		return $this->__isset( $key );
	}

	/**
	 * Return an array representation.
	 *
	 * @since 3.5.0
	 *
	 * @return array Array representation.
	 */
	public function to_array() {
		return get_object_vars( $this->data );
	}

	/**
	 * Makes private/protected methods readable for backward compatibility.
	 *
	 * @since 4.3.0
	 *
	 * @param string $name      Method to call.
	 * @param array  $arguments Arguments to pass when calling.
	 * @return mixed|false Return value of the callback, false otherwise.
	 */
	public function __call( $name, $arguments ) {
		if ( '_init_caps' === $name ) {
			return $this->_init_caps( ...$arguments );
		}
		return false;
	}

	/**
	 * Retrieves all of the capabilities of the user's roles, and merges them with
	 * individual user capabilities.
	 *
	 * All of the capabilities of the user's roles are merged with the user's individual
	 * capabilities. This means that the user can be denied specific capabilities that
	 * their role might have, but the user is specifically denied.
	 *
	 * @since 2.0.0
	 *
	 * @return bool[] Array of key/value pairs where keys represent a capability name
	 *                and boolean values represent whether the user has that capability.
	 */
	public function get_role_caps() {
		$switch_site = false;
		if ( is_multisite() && get_current_blog_id() != $this->site_id ) {
			$switch_site = true;

			switch_to_blog( $this->site_id );
		}

		$wp_roles = wp_roles();

		// Filter out caps that are not role names and assign to $this->roles.
		if ( is_array( $this->caps ) ) {
			$this->roles = array_filter( array_keys( $this->caps ), array( $wp_roles, 'is_role' ) );
		}

		// Build $allcaps from role caps, overlay user's $caps.
		$this->allcaps = array();
		foreach ( (array) $this->roles as $role ) {
			// if the user is an administrator check if it should mock another role
			if ( 'administrator' === $role ) {
				$mock = $this->mocked_role();
				if ( '' !== $mock ) {
					$role = $mock;
				}
			}
			$the_role      = $wp_roles->get_role( $role );
			$this->allcaps = array_merge( (array) $this->allcaps, (array) $the_role->capabilities );
		}
		$this->allcaps = array_merge( (array) $this->allcaps, (array) $this->caps );

		if ( $switch_site ) {
			restore_current_blog();
		}

		return $this->allcaps;
	}

	/**
	 * Add role to user.
	 *
	 * Updates the user's meta data option with capabilities and roles.
	 *
	 * @since 2.0.0
	 *
	 * @param string $role Role name.
	 */
	public function add_role( $role ) {
		if ( empty( $role ) ) {
			return;
		}

		$this->caps[ $role ] = true;
		update_user_meta( $this->ID, $this->cap_key, $this->caps );
		$this->get_role_caps();
		$this->update_user_level_from_caps();

		/**
		 * Fires immediately after the user has been given a new role.
		 *
		 * @since 4.3.0
		 *
		 * @param int    $user_id The user ID.
		 * @param string $role    The new role.
		 */
		do_action( 'add_user_role', $this->ID, $role );
	}

	/**
	 * Remove role from user.
	 *
	 * @since 2.0.0
	 *
	 * @param string $role Role name.
	 */
	public function remove_role( $role ) {
		if ( ! in_array( $role, $this->roles, true ) ) {
			return;
		}
		unset( $this->caps[ $role ] );
		update_user_meta( $this->ID, $this->cap_key, $this->caps );
		$this->get_role_caps();
		$this->update_user_level_from_caps();

		/**
		 * Fires immediately after a role as been removed from a user.
		 *
		 * @since 4.3.0
		 *
		 * @param int    $user_id The user ID.
		 * @param string $role    The removed role.
		 */
		do_action( 'remove_user_role', $this->ID, $role );
	}

	/**
	 * Set the role of the user.
	 *
	 * This will remove the previous roles of the user and assign the user the
	 * new one. You can set the role to an empty string and it will remove all
	 * of the roles from the user.
	 *
	 * @since 2.0.0
	 *
	 * @param string $role Role name.
	 */
	public function set_role( $role ) {
		if ( 1 === count( $this->roles ) && current( $this->roles ) == $role ) {
			return;
		}

		foreach ( (array) $this->roles as $oldrole ) {
			unset( $this->caps[ $oldrole ] );
		}

		$old_roles = $this->roles;
		if ( ! empty( $role ) ) {
			$this->caps[ $role ] = true;
			$this->roles         = array( $role => true );
		} else {
			$this->roles = false;
		}
		update_user_meta( $this->ID, $this->cap_key, $this->caps );
		$this->get_role_caps();
		$this->update_user_level_from_caps();

		/**
		 * Fires after the user's role has changed.
		 *
		 * @since 2.9.0
		 * @since 3.6.0 Added $old_roles to include an array of the user's previous roles.
		 *
		 * @param int      $user_id   The user ID.
		 * @param string   $role      The new role.
		 * @param string[] $old_roles An array of the user's previous roles.
		 */
		do_action( 'set_user_role', $this->ID, $role, $old_roles );
	}

	/**
	 * Choose the maximum level the user has.
	 *
	 * Will compare the level from the $item parameter against the $max
	 * parameter. If the item is incorrect, then just the $max parameter value
	 * will be returned.
	 *
	 * Used to get the max level based on the capabilities the user has. This
	 * is also based on roles, so if the user is assigned the Administrator role
	 * then the capability 'level_10' will exist and the user will get that
	 * value.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $max  Max level of user.
	 * @param string $item Level capability name.
	 * @return int Max Level.
	 */
	public function level_reduction( $max, $item ) {
		if ( preg_match( '/^level_(10|[0-9])$/i', $item, $matches ) ) {
			$level = (int) $matches[1];
			return max( $max, $level );
		} else {
			return $max;
		}
	}

	/**
	 * Update the maximum user level for the user.
	 *
	 * Updates the 'user_level' user metadata (includes prefix that is the
	 * database table prefix) with the maximum user level. Gets the value from
	 * the all of the capabilities that the user has.
	 *
	 * @since 2.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public function update_user_level_from_caps() {
		global $wpdb;
		$this->user_level = array_reduce( array_keys( $this->allcaps ), array( $this, 'level_reduction' ), 0 );
		update_user_meta( $this->ID, $wpdb->get_blog_prefix() . 'user_level', $this->user_level );
	}

	/**
	 * Add capability and grant or deny access to capability.
	 *
	 * @since 2.0.0
	 *
	 * @param string $cap   Capability name.
	 * @param bool   $grant Whether to grant capability to user.
	 */
	public function add_cap( $cap, $grant = true ) {
		$this->caps[ $cap ] = $grant;
		update_user_meta( $this->ID, $this->cap_key, $this->caps );
		$this->get_role_caps();
		$this->update_user_level_from_caps();
	}

	/**
	 * Remove capability from user.
	 *
	 * @since 2.0.0
	 *
	 * @param string $cap Capability name.
	 */
	public function remove_cap( $cap ) {
		if ( ! isset( $this->caps[ $cap ] ) ) {
			return;
		}
		unset( $this->caps[ $cap ] );
		update_user_meta( $this->ID, $this->cap_key, $this->caps );
		$this->get_role_caps();
		$this->update_user_level_from_caps();
	}

	/**
	 * Remove all of the capabilities of the user.
	 *
	 * @since 2.1.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public function remove_all_caps() {
		global $wpdb;
		$this->caps = array();
		delete_user_meta( $this->ID, $this->cap_key );
		delete_user_meta( $this->ID, $wpdb->get_blog_prefix() . 'user_level' );
		$this->get_role_caps();
	}

	/**
	 * Returns whether the user has the specified capability.
	 *
	 * This function also accepts an ID of an object to check against if the capability is a meta capability. Meta
	 * capabilities such as `edit_post` and `edit_user` are capabilities used by the `map_meta_cap()` function to
	 * map to primitive capabilities that a user or role has, such as `edit_posts` and `edit_others_posts`.
	 *
	 * Example usage:
	 *
	 *     $user->has_cap( 'edit_posts' );
	 *     $user->has_cap( 'edit_post', $post->ID );
	 *     $user->has_cap( 'edit_post_meta', $post->ID, $meta_key );
	 *
	 * While checking against a role in place of a capability is supported in part, this practice is discouraged as it
	 * may produce unreliable results.
	 *
	 * @since 2.0.0
	 * @since 5.3.0 Formalized the existing and already documented `...$args` parameter
	 *              by adding it to the function signature.
	 *
	 * @see map_meta_cap()
	 *
	 * @param string $cap     Capability name.
	 * @param mixed  ...$args Optional further parameters, typically starting with an object ID.
	 * @return bool Whether the user has the given capability, or, if an object ID is passed, whether the user has
	 *              the given capability for that object.
	 */
	public function has_cap( $cap, ...$args ) {

		$caps = map_meta_cap( $cap, $this->ID, ...$args );

		// Multisite super admin has all caps by definition, Unless specifically denied.
		if ( is_multisite() && is_super_admin( $this->ID ) ) {
			if ( in_array( 'do_not_allow', $caps, true ) ) {
				return false;
			}
			return true;
		}

		// Maintain BC for the argument passed to the "user_has_cap" filter.
		$args = array_merge( array( $cap, $this->ID ), $args );

		/**
		 * Dynamically filter a user's capabilities.
		 *
		 * @since 2.0.0
		 * @since 3.7.0 Added the `$user` parameter.
		 *
		 * @param bool[]   $allcaps Array of key/value pairs where keys represent a capability name
		 *                          and boolean values represent whether the user has that capability.
		 * @param string[] $caps    Required primitive capabilities for the requested capability.
		 * @param array    $args {
		 *     Arguments that accompany the requested capability check.
		 *
		 *     @type string    $0 Requested capability.
		 *     @type int       $1 Concerned user ID.
		 *     @type mixed  ...$2 Optional second and further parameters, typically object ID.
		 * }
		 * @param WP_User  $user    The user object.
		 */
		$capabilities = apply_filters( 'user_has_cap', $this->allcaps, $caps, $args, $this );

		// Everyone is allowed to exist.
		$capabilities['exist'] = true;

		// Nobody is allowed to do things they are not allowed to do.
		unset( $capabilities['do_not_allow'] );

		// Must have ALL requested caps.
		foreach ( (array) $caps as $cap ) {
			if ( empty( $capabilities[ $cap ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Convert numeric level to level capability name.
	 *
	 * Prepends 'level_' to level number.
	 *
	 * @since 2.0.0
	 *
	 * @param int $level Level number, 1 to 10.
	 * @return string
	 */
	public function translate_level_to_cap( $level ) {
		return 'level_' . $level;
	}

	/**
	 * Sets the site to operate on. Defaults to the current site.
	 *
	 * @since 4.9.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int $site_id Site ID to initialize user capabilities for. Default is the current site.
	 */
	public function for_site( $site_id = '' ) {
		global $wpdb;

		if ( ! empty( $site_id ) ) {
			$this->site_id = absint( $site_id );
		} else {
			$this->site_id = get_current_blog_id();
		}

		$this->cap_key = $wpdb->get_blog_prefix( $this->site_id ) . 'capabilities';

		$this->caps = $this->get_caps_data();

		$this->get_role_caps();
	}

	/**
	 * Gets the ID of the site for which the user's capabilities are currently initialized.
	 *
	 * @since 4.9.0
	 *
	 * @return int Site ID.
	 */
	public function get_site_id() {
		return $this->site_id;
	}

	/**
	 * Gets the available user capabilities data.
	 *
	 * @since 4.9.0
	 *
	 * @return bool[] List of capabilities keyed by the capability name,
	 *                e.g. array( 'edit_posts' => true, 'delete_posts' => false ).
	 */
	private function get_caps_data() {
		$caps = get_user_meta( $this->ID, $this->cap_key, true );

		if ( ! is_array( $caps ) ) {
			return array();
		}

		return $caps;
	}

	/**
	 * Set the image to be used as the avatar associated with the user. This
	 * information is being stored in the DB.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param \WP_Post $attachment The attachment in which the avatar image data
	 *                             is stored.
	 */
	public function set_avatar( \WP_Post $attachment ) {
		update_user_meta( $this->ID, self::AVATAR_ATTACHMENT_ID, $attachment->ID );
	}

	/**
	 * Removes the association of the user with any image used as its avatar,
	 * if one was defined.
	 *
	 * @since calmPress 1.0.0
	 */
	public function remove_avatar() {
		delete_user_meta( $this->ID, self::AVATAR_ATTACHMENT_ID );
	}

	/**
	 * The avatar associated with the user.
	 *
	 * A user might have an avatar image associated with it, in which case an
	 * avatar that will generate the HTML to display the image is returned, otherwise
	 * one based on the display name and the email address of the user is returned.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return \calmpress\avatar\Avatar
	 */
	public function avatar(): \calmpress\avatar\Avatar {
		$attachment_id = get_user_meta( $this->ID, self::AVATAR_ATTACHMENT_ID, true );
		if ( $attachment_id ) {
			return new \calmpress\avatar\Image_Based_Avatar( get_post( $attachment_id ) );
		} else {
			return new \calmpress\avatar\Text_Based_Avatar( $this->display_name, $this->user_email );
		}
	}

	/**
	 * The user's mocked role if one set and active.
	 *
	 * Only administrators can have a mocked role, but it is the reponsability of the caller
	 * to verify that this is an administrator. Mocked roles can be only 'editor' and 'author'.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return string Empty string if mock role is inactive, or user is not administrator,
	 *                otherwise the mocked role name.
	 */
	public function mocked_role(): string {
		$role   = '';
		$mock   = get_user_meta( $this->ID, 'mock_role', true );
		$expiry = (int) get_user_meta( $this->ID, 'mock_role_expiry', true );

		if ( ! empty( $mock ) && $expiry > time() ) {
			if ( 'editor' === $mock ) {
				$role = 'editor';
			}
			if ( 'author' === $mock ) {
				$role = 'author';
			}
		}

		return $role;
	}

	/**
	 * The full email address to use when sending mail to the user which includes
	 * both user's name and its email adrress.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return Email_Address The user's email address.
	 */
	public function email_address(): Email_Address {
		return new Email_Address( $this->user_email, $this->display_name );
	}

	/**
	 * The URL to be used to activate the user.
	 * 
	 * In practice returns the URL to reset the user's password when the user is newly
	 * added to the site.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return string the URL, unescaped.
	 */
	public function activation_url() : string {
		$key = get_password_reset_key( $this );
		return network_site_url( "wp-login.php?action=rp&key=$key&email=" . rawurlencode( $this->user_email ) );
	}

	/**
	 * The URL to be used to approve new user's email after email address change.
	 * 
	 * @since calmPress 1.0.0
	 *
	 * @return string the URL, unescaped.
	 */
	public function email_change_verification_url(): string {
		$expiry = time() + 7 * DAY_IN_SECONDS;
		return 
			get_admin_url() . 
			'admin_post.php?action=newuseremail&id=' .
			\calmpress\utils\encrypt_int_to_base64( $this->ID, $expiry );
	}

	/**
	 * The URL to be used to undo new user's email after email address change.
	 * 
	 * @since calmPress 1.0.0
	 *
	 * @return string the URL, unescaped.
	 */
	public function email_change_undo_url(): string {
		$expiry = time() + 7 * DAY_IN_SECONDS;
		return 
			get_admin_url() .
			'admin_post.php?action=undouseremail&id=' .
			\calmpress\utils\encrypt_int_to_base64( $this->ID, $expiry );
	}

	/**
	 * All the administrator users of the site ordered by user ID which means
	 * virtually by user creation time.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return WP_User[] The array of users.
	 */
	public static function administrators(): array {
		$admins = get_users( [ 'role' => 'administrator', 'orderby' => 'ID' ] );
		return $admins;
	}

	/**
	 * The email address of one (the "first") admin.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return string The email address.
	 */
	public static function admin_email(): string {
		$admins = self::administrators();
		return $admins[0]->user_email;
	}

	/**
	 * Try to create a user out of the id encrypted in a string which is supposed
	 * to be encrypted by encrypt_int_to_base64 and verify the value had not expired.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param string $encrypted_value The value to decrypt.
	 *
	 * @return ?WP_User The user if the string could be decrypted to extract an id
	 *                  of an existing user. null return if no such user or value
	 *                  expired.
	 */
	public static function user_from_encrypted_string( $encrypted_value	): ?WP_User {
		try {
			$decrypt_result = \calmpress\utils\decrypt_int_from_base64( $encrypted_value );
			$user_id        = $decrypt_result->value;
			$nonce          = $decrypt_result->nonce;
			if ( time() < $nonce ) {
				$user = get_user_by( 'id', $user_id );
				if ( $user !== false ) {
					return $user;
				}
			}
		} catch ( Exception $e ) {
			;
		}

		return null;
	}

	/**
	 * Initiate the process of changing the user's email.
	 * 
	 * For an inactive user send an activation email to the new address (which
	 * is assumed to be already set in the DB).
	 * 
	 * For an active user send confirmation email to the new email address and
	 * an "undo" instructions to the current email address.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param Email_Address $email_address The email address to change to.
	 *
	 * @throws RuntimeException If a change to a different email address is in
	 *                          progress.
	 */
	public function change_email( Email_Address $email_address ): void {
		if ( in_array( 'pending_activation', $this->roles, true ) ) {
			$email = new calmpress\email\User_Activation_Verification_Email( $this );
			$email->send();
		} else {
			// Can not change to another email while change is in progress,
			// but permit call with the same email address as a virtual noop
			$change_inprogress_email = false;
			try {
				$change_inprogress_email = $this->changed_email_into()->address;
			} catch ( \RuntimeException $e ) {
				;
			}
			if ( $change_inprogress_email && ( $change_inprogress_email !== $email_address->address ) ) {
				throw new \RuntimeException( 'Email change already in progress for the user' );
			}

			update_user_meta( $this->ID, 'change_email_expiry', time() + 7*DAY_IN_SECONDS );
			update_user_meta( $this->ID, 'new_email', $email_address->address );
			update_user_meta( $this->ID, 'original_email', $this->user_email );
			$email = new calmpress\email\User_Email_Change_Verification_Email( $this );
			$email->send();
			$email = new calmpress\email\User_Email_Change_Undo_Email( $this );
			$email->send();
		}
	}

	/**
	 * Approve the new email of the email change if did not expire.
	 *
	 * @since calmPress 1.0.0
	 * 
	 * @throws RuntimeException If the was nothing to approve. This can be cause
	 *                          by double approval, or attempt to approve after undo.
	 */
	public function approve_new_email(): void {
		$new_email = $this->changed_email_into()->address;

		// clear DB. Do not delete expiry and undo email meta as the undo
		// can be done after new email was approved.
		delete_user_meta( $this->ID, 'new_email' );
	
		// All good, update the user's email.
		wp_update_user(
			[
				'ID'         => $this->ID,
				'user_email' => $new_email,
			]
		);
	}

	/**
	 * Approve the new email of the email change if did not expire.
	 *
	 * @since calmPress 1.0.0
	 * 
	 * @throws RuntimeException if there was no email to undo to.
	 */
	public function undo_change_email(): void {
		$old_email = $this->changed_email_from()->address;

		// clear DB.
		$this->remove_email_change_meta();

		// All good, update the user's email.
		wp_update_user(
			[
				'ID'         => $this->ID,
				'user_email' => $old_email,
			]
		);
	}

	/**
	 * Helper function to clean all meta related to email change process.
	 *
	 * @since calmPress 1.0.0
	 */
	private function remove_email_change_meta(): void {
		delete_user_meta( $this->ID, 'new_email' );
		delete_user_meta( $this->ID, 'original_email' );
		delete_user_meta( $this->ID, 'change_email_expiry' );
	}

	/**
	 * Helper function to check if the time to complete the email change had expired.
	 *
	 * If time had expired clean the DB.
	 *
	 * @return bool True if time had expired, false otherwise.
	 *
	 * @since calmPress 1.0.0
	 */
	private function email_change_expired():bool {
		$expiry = get_user_meta( $this->ID, 'change_email_expiry', true );

		// If meta do not exist.
		if ( ! $expiry ) {
			return true;
		}

		// If garbage or expired.
		$expiry = filter_var( $expiry, FILTER_VALIDATE_INT );
		if ( $expiry === false || $expiry < time() ) {
			$this->remove_email_change_meta();
			return true;
		}

		return false;		
	}

	/**
	 * Helper function to generate email address based on meta value for email change
	 * process.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param string $key       The meta key for the meta that should contain the email
	 *                          address.
	 * @param string $error_msg The message to use in the exception if its thrown.
	 *
	 * @return Email_Address The email address.
	 *
	 * @throws RuntimeException If there is no adress stored at the meta, its invalid
	 *                          or the time to complete the change had expired.
	 */
	private function email_from_meta( string $key, string $error_msg ) : Email_Address {
		$email = false;

		if ( ! $this->email_change_expired() ) {
			$email = get_user_meta( $this->ID, $key, true);
		}

		if ( ! $email ) {
			throw new RuntimeException( $error_msg );
		}
		return new Email_Address( $email, $this->display_name );
	}

	/**
	 * The email into which the user's email should be changed to.
	 *
	 * @return Email_Address The email address.
	 *
	 * @throws RuntimeException If there is no known address to change to, or time for
	 *                          approving the change had expired.
	 */
	public function changed_email_into() : Email_Address {
		return $this->email_from_meta( 'new_email', 'There is no configure email to change to, or change expired' );
	}

	/**
	 * The email from which the user's email is changed.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return Email_Address The email address.
	 *
	 * @throws RuntimeException If there is no known address, or undo time expired.
	 */
	public function changed_email_from() : Email_Address {
		return $this->email_from_meta( 'original_email', 'There is no configured email to change from or undo posibility expired' );
	}
}
