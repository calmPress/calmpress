<?php
/**
 * User Administration Landing Screen
 *
 * @package WordPress
 * @subpackage Administration
 * @since 3.1.0
 * @since calmPress 1.0.0 Redirects to the Sites screen instead of displaying a user dashboard.
 */

/** Load WordPress Administration Bootstrap. */
require_once __DIR__ . '/admin.php';

wp_safe_redirect( user_admin_url( 'sites.php' ) );
exit;
