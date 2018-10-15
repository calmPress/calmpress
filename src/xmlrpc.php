<?php
/**
 * XML-RPC protocol support for WordPress
 *
 * @package WordPress
 */

/**
 * Whether this is an XML-RPC Request
 *
 * @var bool
 */
define('XMLRPC_REQUEST', true);

// Some browser-embedded clients send cookies. We don't want them.
$_COOKIE = array();

// A bug in PHP < 5.2.2 makes $HTTP_RAW_POST_DATA not set by default,
// but we can do it ourself.
if ( !isset( $HTTP_RAW_POST_DATA ) ) {
	$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );
}

// fix for mozBlog and other cases where '<?xml' isn't on the very first line
if ( isset($HTTP_RAW_POST_DATA) )
	$HTTP_RAW_POST_DATA = trim($HTTP_RAW_POST_DATA);

/** Include the bootstrap for setting up WordPress environment */
include( dirname( __FILE__ ) . '/wp-load.php' );

include_once(ABSPATH . 'wp-admin/includes/admin.php');
include_once(ABSPATH . WPINC . '/class-IXR.php');
include_once(ABSPATH . WPINC . '/class-wp-xmlrpc-server.php');

/**
 * Posts submitted via the XML-RPC interface get that title
 * @name post_default_title
 * @var string
 */
$post_default_title = "";

/**
 * Filters the class used for handling XML-RPC requests.
 *
 * @since 3.1.0
 *
 * @param string $class The name of the XML-RPC server class.
 */
$wp_xmlrpc_server_class = apply_filters( 'wp_xmlrpc_server_class', 'wp_xmlrpc_server' );
$wp_xmlrpc_server = new $wp_xmlrpc_server_class;

// Fire off the request
$wp_xmlrpc_server->serve_request();

exit;
