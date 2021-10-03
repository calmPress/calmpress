<?php

/*
 * Path to the WordPress codebase you'd like to test. Add a forward slash in the end.
 * realpath is used here to get consistant windows style path on windows.
*/
define( 'ABSPATH', realpath( dirname( __FILE__ ) . '/src' ) . '/' );

/*
 * Path to the theme to test with.
 *
 * The 'default' theme is symlinked from test/phpunit/data/themedir1/default into
 * the themes directory of the calmPress installation defined above.
 */
define( 'WP_DEFAULT_THEME', 'default' );

/*
 * Test with multisite enabled.
 * Alternatively, use the tests/phpunit/multisite.xml configuration file.
 */
// define( 'WP_TESTS_MULTISITE', true );

/*
 * Force known bugs to be run.
 * Tests with an associated Trac ticket that is still open are normally skipped.
 */
// define( 'WP_TESTS_FORCE_KNOWN_BUGS', true );

// Test with WordPress debug mode (default).
define( 'WP_DEBUG', true );

// ** MySQL settings ** //

/*
 * This configuration file will be used by the copy of WordPress being tested.
 * wordpress/wp-config.php will be ignored.
 *
 * WARNING WARNING WARNING!
 * These tests will DROP ALL TABLES in the database with the prefix named below.
 * DO NOT use a production database or one that is shared with something else.
 */

define( 'DB_NAME', 'youremptytestdbnamehere' );
define( 'DB_USER', 'yourusernamehere' );
define( 'DB_PASSWORD', 'yourpasswordhere' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 */
define( 'AUTH_KEY',         'put your unique phrase here should be at least 32 characters long' );
define( 'SECURE_AUTH_KEY',  'put your unique phrase here should be at least 32 characters long' );
define( 'LOGGED_IN_KEY',    'put your unique phrase here should be at least 32 characters long' );
define( 'NONCE_KEY',        'put your unique phrase here should be at least 32 characters long' );
define( 'AUTH_SALT',        'put your unique phrase here should be at least 32 characters long' );
define( 'SECURE_AUTH_SALT', 'put your unique phrase here should be at least 32 characters long' );
define( 'LOGGED_IN_SALT',   'put your unique phrase here should be at least 32 characters long' );
define( 'NONCE_SALT',       'put your unique phrase here should be at least 32 characters long' );

$table_prefix = 'calmtests_';   // Only numbers, letters, and underscores please!

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );

define( 'WPLANG', '' );

// Uncomment and fill the information bellow if you want to run FTP related tests.
/*
define( 'FTP_HOST', 'The host:post on which the FTP server listens' );
define( 'FTP_USER', 'The username that can be authenticated by the FTP server' );
define( 'FTP_PASS', 'The password related to the username' );
define( 'FTP_BASE', 'The root directory of the files which the FTP server can manipulate' );
*/
