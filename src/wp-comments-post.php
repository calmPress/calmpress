<?php
/**
 * Handles Comment Post to WordPress and prevents duplicate comment posting.
 *
 * @package WordPress
 */

if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
	$protocol = $_SERVER['SERVER_PROTOCOL'];
	if ( ! in_array( $protocol, array( 'HTTP/1.1', 'HTTP/2', 'HTTP/2.0', 'HTTP/3' ), true ) ) {
		$protocol = 'HTTP/1.0';
	}

	header( 'Allow: POST' );
	header( "$protocol 405 Method Not Allowed" );
	header( 'Content-Type: text/plain' );
	exit;
}

/** Sets up the WordPress Environment. */
require __DIR__ . '/wp-load.php';

nocache_headers();

// Assign dummy email if email not given, or based on previous comment if there was.

if ( empty( $_POST['email'] ) ) {
	// Try to restore email from cookie if no email was submitted.
	if ( isset( $_COOKIE[ 'comment_author_' . COOKIEHASH ] ) ) {
		$id = (int) $_COOKIE[ 'comment_author_' . COOKIEHASH ];
		if ( $id ) {
			$comment = get_comment( $id );
			if ( $comment ) {
				$_POST['email'] = $comment->comment_author_email;
			}
		}
	}
} else {
	// email address was given, check whether it belongs to a registered user.
	$existing_user = get_user_by( 'email', $_POST['email'] );
	if ( $existing_user ) {
		// Treat as if no email was supplied to avoid impersonation.
		$_POST['email'] = '';
	}
}

// If still empty generate a dummy.
if ( empty( $_POST['email'] ) ) {
	$_POST['email'] = 'anon-' . uniqid() . '@example.com';
}

$comment = wp_handle_comment_submission( wp_unslash( $_POST ) );
if ( is_wp_error( $comment ) ) {
	$data = (int) $comment->get_error_data();
	if ( ! empty( $data ) ) {
		wp_die(
			'<p>' . $comment->get_error_message() . '</p>',
			__( 'Comment Submission Failure' ),
			array(
				'response'  => $data,
				'back_link' => true,
			)
		);
	} else {
		exit;
	}
}

$user = wp_get_current_user();

/**
 * Fires after comment cookies are set.
 *
 * @since 3.4.0
 * @since 4.9.6 The `$cookies_consent` parameter was added.
 * 
 * @param WP_Comment $comment         Comment object.
 * @param WP_User    $user            Comment author's user object. The user may not exist.
 * @param bool       $cookies_consent Comment author's consent to store cookies.
 */
do_action( 'set_comment_cookies', $comment, $user, true );

$location = empty( $_POST['redirect_to'] ) ? get_comment_link( $comment ) : $_POST['redirect_to'] . '#comment-' . $comment->comment_ID;

/**
 * Filters the location URI to send the commenter after posting.
 *
 * @since 2.0.5
 *
 * @param string     $location The 'redirect_to' URI sent via $_POST.
 * @param WP_Comment $comment  Comment object.
 */
$location = apply_filters( 'comment_post_redirect', $location, $comment );

wp_safe_redirect( $location );
exit;
