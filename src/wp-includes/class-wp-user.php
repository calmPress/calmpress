<?php
/**
 * User API: WP_User class
 *
 * @package WordPress
 * @subpackage Users
 * @since 4.4.0
 */

use calmpress\email\Email_Address;
use calmpress\utils\One_Time_Password;
use calmpress\webauthn\Devices_Of_User;

/**
 * Core class used to implement the WP_User object.
 *
 * @since 2.0.0
 * @since 6.8.0 The `user_pass` property is now hashed using bcrypt by default instead of phpass.
 *              Existing passwords may still be hashed using phpass.
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
#[AllowDynamicProperties]
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
	 * The user meta key in which the one time password is stored.
	 * @since calmPress 1.0.0
	 */
	const OTP_META_ID = 'otp';

	/**
	 * The user meta key identifying a network invitation awaiting authentication.
	 * Each pending network is stored as a separate value under this key.
	 *
	 * @since calmPress 1.0.0
	 */
	public const INVITED_BY_NETWORK_META_KEY = 'calmpress_network_invitation';

	/**
	 * The user meta key identifying an account created for a network invitation.
	 *
	 * @since calmPress 1.0.0
	 */
	public const CREATED_FOR_NETWORK_INVITATION_META_KEY = 'calmpress_created_for_network_invitation';

	/**
	 * Constructor.
	 *
	 * Retrieves the userdata and passes it to WP_User::init().
	 *
	 * @since 2.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int|string|stdClass|WP_User $id      User's ID, a WP_User object, or a user object from the DB.
	 * @param string                      $name    Optional. User's username
	 * @param int                         $site_id Optional Site ID, defaults to current site.
	 */
	public function __construct( $id = 0, $name = '', $site_id = 0 ) {
		global $wpdb;

		if ( ! isset( self::$back_compat_keys ) ) {
			$prefix = $wpdb->prefix;

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
			$this->data = new stdClass();
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
	public function init( $data, $site_id = 0 ) {
		if ( ! isset( $data->ID ) ) {
			$data->ID = 0;
		}
		$this->data = $data;
		$this->ID   = (int) $data->ID;

		$this->for_site( $site_id );
	}

	/**
	 * Returns only the main user fields.
	 *
	 * @since 3.3.0
	 * @since 4.4.0 Added 'ID' as an alias of 'id' for the `$field` parameter.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string     $field The field to query against: Accepts 'id', 'ID', 'slug', 'email' or 'login'.
	 * @param string|int $value The field value.
	 * @return object|false Raw user object.
	 */
	public static function get_data_by( $field, $value ) {
		global $wpdb;

		// 'ID' is an alias of 'id'.
		if ( 'ID' === $field ) {
			$field = 'id';
		}

		if ( 'id' === $field ) {
			// Make sure the value is numeric to avoid casting objects, for example, to int 1.
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
	 * Determines whether the user exists in the database.
	 *
	 * @since 3.4.0
	 *
	 * @return bool True if user exists in the database, false if not.
	 */
	public function exists() {
		return ! empty( $this->ID );
	}

	/**
	 * Indicates whether the account can be used to log in.
	 *
	 * Users that do not exist or are marked as deleted cannot log in. Standalone
	 * installations store the deleted state as a role. Multisite stores
	 * network-wide deletion in the global users table.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return bool Whether the account can be used to log in.
	 */
	public function can_login(): bool {
		if ( ! $this->exists() ) {
			return false;
		}

		return empty( $this->deleted ) && ! in_array( 'deleted', $this->roles, true );
	}

	/**
	 * Marks the user as invited to a network.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param WP_Network $network Network to which the user is invited.
	 *
	 * @throws RuntimeException If the invitation cannot be stored.
	 */
	public function invite_to_network( WP_Network $network ): void {
		if ( false === add_user_meta( $this->ID, self::INVITED_BY_NETWORK_META_KEY, (int) $network->id ) ) {
			throw new RuntimeException( 'The network invitation could not be stored.' );
		}
	}

	/**
	 * Indicates whether the user has a pending invitation to a network.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param WP_Network $network Network whose invitation is checked.
	 *
	 * @return bool Whether the user has a pending invitation to the network.
	 */
	public function has_network_invite( WP_Network $network ): bool {
		$network_ids = array_map( 'intval', get_user_meta( $this->ID, self::INVITED_BY_NETWORK_META_KEY ) );

		return in_array( (int) $network->id, $network_ids, true );
	}

	/**
	 * Indicates whether the user has any pending network invitations.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return bool Whether the user has pending network invitations.
	 */
	public function has_any_network_invites(): bool {
		return metadata_exists( 'user', $this->ID, self::INVITED_BY_NETWORK_META_KEY );
	}

	/**
	 * Marks the account as having been created for a network invitation.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @throws RuntimeException If the account cannot be marked.
	 */
	public function mark_as_created_for_network_invitation(): void {
		if ( ! update_user_meta( $this->ID, self::CREATED_FOR_NETWORK_INVITATION_META_KEY, true ) ) {
			throw new RuntimeException( 'The account could not be marked as created for a network invitation.' );
		}
	}

	/**
	 * Indicates whether the account was created for a network invitation.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return bool Whether the account was created for a network invitation.
	 */
	public function was_created_for_network_invitation(): bool {
		return metadata_exists( 'user', $this->ID, self::CREATED_FOR_NETWORK_INVITATION_META_KEY );
	}

	/**
	 * Retrieves the IDs of sites on which the user has been assigned capabilities.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return int[] Site IDs.
	 */
	public function site_ids(): array {
		global $wpdb;

		if ( ! $this->exists() ) {
			return [];
		}

		$keys = get_user_meta( $this->ID );

		if ( [] === $keys ) {
			return [];
		}

		if ( ! is_multisite() ) {
			return [ get_current_blog_id() ];
		}

		$site_ids = [];

		if ( isset( $keys[ $wpdb->base_prefix . 'capabilities' ] ) && defined( 'MULTISITE' ) ) {
			$site_ids[] = 1;
			unset( $keys[ $wpdb->base_prefix . 'capabilities' ] );
		}

		foreach ( array_keys( $keys ) as $key ) {
			if ( ! str_ends_with( $key, 'capabilities' ) ) {
				continue;
			}

			if ( $wpdb->base_prefix && ! str_starts_with( $key, $wpdb->base_prefix ) ) {
				continue;
			}

			$site_id = str_replace( [ $wpdb->base_prefix, '_capabilities' ], '', $key );

			if ( ! is_numeric( $site_id ) ) {
				continue;
			}

			$site_ids[] = (int) $site_id;
		}

		return $site_ids;
	}

	/**
	 * Retrieves the sites on which the user has been assigned capabilities.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return calmpress\site\Site[] Sites on which the user has been assigned capabilities.
	 */
	public function sites(): array {
		$site_ids = $this->site_ids();

		if ( [] === $site_ids ) {
			return [];
		}

		if ( ! is_multisite() ) {
			return [ calmpress\site\Site::current() ];
		}

		$args = [
			'number'   => '',
			'site__in' => $site_ids,
		];

		$args['archived'] = 0;
		$args['deleted']  = 0;

		return get_sites( $args );
	}

	/**
	 * Indicates whether the user has been assigned capabilities on a site in a network.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param WP_Network $network Network whose membership is checked.
	 *
	 * @return bool Whether the user is part of the network.
	 */
	public function is_member_of_network( WP_Network $network ): bool {
		$site_ids = $this->site_ids();

		if ( [] === $site_ids ) {
			return false;
		}

		return [] !== get_sites(
			[
				'fields'     => 'ids',
				'network_id' => (int) $network->id,
				'number'     => 1,
				'site__in'   => $site_ids,
			]
		);
	}

	/**
	 * Marks a pending network invitation as accepted.
	 *
	 * Accepting an invitation activates the shared account, so it must be preserved if
	 * invitations from other networks are later cancelled.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param WP_Network $network Network whose invitation is accepted.
	 */
	public function mark_network_invite_as_accepted( WP_Network $network ): void {
		$network->add_orphaned_user( $this );
		delete_user_meta( $this->ID, self::INVITED_BY_NETWORK_META_KEY, (int) $network->id );
		delete_user_meta( $this->ID, self::CREATED_FOR_NETWORK_INVITATION_META_KEY );
	}

	/**
	 * Cancels a pending invitation to a network.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param WP_Network $network Network whose invitation is cancelled.
	 */
	public function cancel_network_invite( WP_Network $network ): void {
		delete_user_meta( $this->ID, self::INVITED_BY_NETWORK_META_KEY, (int) $network->id );
	}

	/**
	 * Retrieves the value of a property or meta key.
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
	 * Determines whether a property or meta key is set.
	 *
	 * Consults the users and usermeta tables.
	 *
	 * @since 3.3.0
	 *
	 * @param string $key Property.
	 * @return bool
	 */
	public function has_prop( $key ) {
		return $this->__isset( $key );
	}

	/**
	 * Returns an array representation.
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
		if ( is_multisite() && get_current_blog_id() !== $this->site_id ) {
			$switch_site = true;

			switch_to_blog( $this->site_id );
		}

		$wp_roles = wp_roles();

		// Select caps that are role names and assign to $this->roles.
		if ( is_array( $this->caps ) ) {
			$this->roles = array();

			foreach ( $this->caps as $key => $value ) {
				if ( $wp_roles->is_role( $key ) ) {
					$this->roles[] = $key;
				}
			}
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
	 * Adds role to user.
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

		if ( in_array( $role, $this->roles, true ) ) {
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
	 * Removes role from user.
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
	 * Sets the role of the user.
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
		if ( 1 === count( $this->roles ) && current( $this->roles ) === $role ) {
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
			$this->roles = array();
		}

		update_user_meta( $this->ID, $this->cap_key, $this->caps );
		$this->get_role_caps();
		$this->update_user_level_from_caps();

		foreach ( $old_roles as $old_role ) {
			if ( ! $old_role || $old_role === $role ) {
				continue;
			}

			/** This action is documented in wp-includes/class-wp-user.php */
			do_action( 'remove_user_role', $this->ID, $old_role );
		}

		if ( $role && ! in_array( $role, $old_roles, true ) ) {
			/** This action is documented in wp-includes/class-wp-user.php */
			do_action( 'add_user_role', $this->ID, $role );
		}

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
	 * Chooses the maximum level the user has.
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
	 * Updates the maximum user level for the user.
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
	 * Adds capability and grant or deny access to capability.
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
	 * Removes capability from user.
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
	 * Removes all of the capabilities of the user.
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
	 * Converts numeric level to level capability name.
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
	public function for_site( $site_id = 0 ) {
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
	 *                e.g. `array( 'edit_posts' => true, 'delete_posts' => false )`.
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
	 * The URL to be used to approve installer's email.
	 * 
	 * @since calmPress 1.0.0
	 *
	 * @return string the URL, unescaped.
	 */
	public function installer_email_verification_url(): string {
		$expiry = time() + 1 * DAY_IN_SECONDS;
		return 
			get_admin_url() . 
			'admin-post.php?action=installeremail&id=' .
			\calmpress\utils\encrypt_int_to_base64URL( $this->ID, $expiry );
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
			'admin-post.php?action=newuseremail&id=' .
			\calmpress\utils\encrypt_int_to_base64URL( $this->ID, $expiry );
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
			'admin-post.php?action=undouseremail&id=' .
			\calmpress\utils\encrypt_int_to_base64URL( $this->ID, $expiry );
	}

	/**
	 * Generate a one time password, send email with with it to the user,
	 * and stores it in the meta.
	 * 
	 * The one time password generated has an expiry time of one hour.
	 * 
	 * @since calmpress 1.0.0
	 */
	public function generate_and_email_one_time_password(): void {
		$password = One_Time_Password::new( 1 * HOUR_IN_SECONDS );
		$this->set_one_time_password( $password );
		$email = new calmpress\email\User_One_Time_Password_Email( $this, $password );
		$email->send();
	}

	/**
	 * Generate a one time password, to be used in QR login URL.
	 * 
	 * The one time password generated has an expiry time of 2 minutes.
	 * 
	 * @since calmpress 1.0.0
	 * 
	 * @return string The password string.
	 */
	public function generate_QR_one_time_password(): string {
		$password = One_Time_Password::new( 2 * MINUTE_IN_SECONDS );
		$this->set_one_time_password( $password );
		return $password->password;
	}

	/**
	 * Set the one time password associated with the user.
	 * 
	 * An helper to faciliate better testing.
	 * 
	 * @since calmpress 1.0.0
	 */
	private function set_one_time_password( One_Time_Password $otp ): void {
		update_user_meta( $this->ID, self::OTP_META_ID , $otp->serialize() );
	}

	/**
	 * Gets the one time password associated with the user, if any.
	 * 
	 * An helper to faciliate better testing.
	 * 
	 * @since calmpress 1.0.0
	 * 
	 * @return ?One_Time_Password The the one time password if exists and
	 *                            didn't expire yet, otherwisse null.
	 */
	private function the_one_time_password(): ?One_Time_Password {
		$p = get_user_meta( $this->ID, self::OTP_META_ID, true );

		if ( empty( $p) ) {
			return null;
		}

		try {
			$o = One_Time_Password::unserialize( (string) $p );
			return $o;
		} catch ( \RuntimeException $e ) {
			delete_user_meta( $this->ID, self::OTP_META_ID );
			return null;
		}
	}

	/**
	 * Check if a value is a one-time password of the user which has not expired yet.
	 * 
	 * @since calmPress 1.0.0
	 * 
	 * @param string $value The value to test.
	 * 
	 * @return bool true If $value is the one-time password and it has not expired,
	 *              false otherwise.
	 */
	public function is_matching_one_time_password( string $value ): bool {
		$p = $this->the_one_time_password();

		if ( empty( $this->the_one_time_password() ) ) {
			return false;
		}

		return $p->is_matching( $value );
	}

	/**
	 * Indicates whether the user is the target for notifications about system events
	 * for a specific site or network.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param calmpress\site\Site|WP_Network $context Site or network whose notification target is checked.
	 *
	 * @return bool True if the user is the notification target in the supplied context, otherwise false.
	 */
	public function is_system_notification_recipient( calmpress\site\Site|WP_Network $context ): bool {
		if ( $context instanceof WP_Network ) {
			return $this->ID === (int) get_network_option( $context->id, 'admin_user_id' );
		}

		return $this->user_email === $context->admin_email();
	}

	/**
	 * Indicates whether the user is the default target for notification about comment
	 * moderation events.
	 * 
	 * @since calmPress 1.0.0
	 * 
	 * @return bool true if the user configured to recieve moderation notification,
	 *              otherwise false.
	 */
	public function is_default_comment_moderation_notification_recipient():bool {
		// Can not just compare id in option as it might not be of a valid user.
		$email = calmpress\site\Site::current()->default_comment_moderator_email();
		return $this->user_email === $email;
	}

	/**
	 * Try to create a user out of the id encrypted in a string which is supposed
	 * to be encrypted by encrypt_int_to_base64URL and verify the value had not expired.
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
			$decrypt_result = \calmpress\utils\decrypt_int_from_base64URL( $encrypted_value );
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
	 * Indicate if email change is in progress for an activated user.
	 * Change is in progress while the user can undo the change.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return bool true If user is activated and in process of changing it email, false
	 *              otherwise.
	 */
	public function email_change_in_progress(): bool {
		if ( in_array( 'pending_activation', $this->roles, true ) ) {
			return false;
		} elseif ( get_user_meta( $this->ID, 'installer_verify_email', true ) ) {
			// If the installer was not verified yet, ignore the change related logic. 
			return false;
		} else {
			$change_inprogress = false;
			try {
				// Exception is thrown when there is no change in progress.
				$dummy     = $this->changed_email_from();
				$change_inprogress = true;
			} catch ( \RuntimeException $e ) {
				;
			}
			return $change_inprogress;
		}
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
		if ( get_user_meta( $this->ID, 'installer_verify_email', true ) ) {
			// If the installer was not verified yet, ignore the change related logic. 
			;
		} elseif ( in_array( 'pending_activation', $this->roles, true ) ) {
			; // Original email was not verified yet, no point in sending.
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
	 * Remove the indication that the user is an installer which requires email
	 * address verification.
	 *
	 * @since calmPress 1.0.0
	 */
	public function approve_installer_email(): void {
		delete_user_meta( $this->ID, 'installer_verify_email' );
	}

	/**
	 * Approve the new email of the email change if did not expire.
	 *
	 * @since calmPress 1.0.0
	 * 
	 * @throws RuntimeException If there was nothing to approve. This can be cause
	 *                          by double approval, or attempt to approve after undo.
	 *                          Or if email already exists which might happen if a
	 *                          a user was registered after a change was requested
	 */
	public function approve_new_email(): void {
		$new_email = $this->changed_email_into()->address;

		// clear DB. Do not delete expiry and undo email meta as the undo
		// can be done after new email was approved.
		delete_user_meta( $this->ID, 'new_email' );
	
		// Throw if a user already exists for the email.
		if ( get_user_by( 'email', $new_email ) ) {
			throw new RuntimeException( 'email already exists' );
		}

		// All good, update the user's email.
		wp_update_user(
			[
				'ID'         => $this->ID,
				'user_email' => $new_email,
			]
		);
	}

	/**
	 * Undo the new email change if did not expire.
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
	 * Cancel the email change state.
	 *
	 * @since calmPress 1.0.0
	 */
	public function cancel_email_change(): void {
		$this->remove_email_change_meta();
	}

	/**
	 * Helper function to check if the time to complete the email change had expired.
	 *
	 * If time had expired, clean the DB.
	 *
	 * @return bool True if time had expired or no change is active, false otherwise.
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
		return $this->email_from_meta( 'new_email', 'There is no configured email to change to, or change expired' );
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

	/**
	 * The collection of devices the user had authenticated with using webauthn.
	 * 
	 * @since calmPress 1.0.0
	 * 
	 * return Devices_Of_User The collection of devices.
	 */
	public function webauthn_registered_devices(): Devices_Of_User {
		return new Devices_Of_User( $this );
	}
}
