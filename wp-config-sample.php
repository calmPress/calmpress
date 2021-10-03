<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// Load a local config if one exists. Such a config should declare all the defines redlared in this file.
if ( is_file( __DIR__ . '/.local-wp-config.php' ) ) {
	require_once __DIR__ . '/.local-wp-config.php';
} else {

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'database_name_here' );

/** MySQL database username */
define( 'DB_USER', 'username_here' );

/** MySQL database password */
define( 'DB_PASSWORD', 'password_here' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'put your unique phrase here should be at least 32 characters long' );
define( 'SECURE_AUTH_KEY',  'put your unique phrase here should be at least 32 characters long' );
define( 'LOGGED_IN_KEY',    'put your unique phrase here should be at least 32 characters long' );
define( 'NONCE_KEY',        'put your unique phrase here should be at least 32 characters long' );
define( 'AUTH_SALT',        'put your unique phrase here should be at least 32 characters long' );
define( 'SECURE_AUTH_SALT', 'put your unique phrase here should be at least 32 characters long' );
define( 'LOGGED_IN_SALT',   'put your unique phrase here should be at least 32 characters long' );
define( 'NONCE_SALT',       'put your unique phrase here should be at least 32 characters long' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/*
 * Custom values configured via the admin. Do not modify manually.
 */
// BEGIN User
// END User

/*
 * Any costum code that can not or should not be entered via the admin should be placed
 * below this comment.
 */

/* That's all, stop editing! Happy publishing. */
}

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
