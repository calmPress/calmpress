<?php
/**
 * Discussion settings administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */
/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( __( 'Sorry, you are not allowed to manage options for this site.' ) );
}

$title       = __( 'Discussion Settings' );
$parent_file = 'options-general.php';

add_action( 'admin_print_footer_scripts', 'options_discussion_add_js' );

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' => '<p>' . __( 'This screen provides many options for controlling the management and display of comments and links to your posts/pages. So many, in fact, they won&#8217;t all fit here! :) Use the documentation links to get information on what each discussion setting does.' ) . '</p>' .
			'<p>' . __( 'You must click the Save Changes button at the bottom of the screen for new settings to take effect.' ) . '</p>',
	)
);

require_once ABSPATH . 'wp-admin/admin-header.php';
?>

<div class="wrap">
<h1><?php echo esc_html( $title ); ?></h1>

<form method="post" action="options.php">
<?php settings_fields( 'discussion' ); ?>

<table class="form-table" role="presentation">
<tr>
<th scope="row"><?php _e( 'Default article settings' ); ?></th>
<td><fieldset><legend class="screen-reader-text"><span><?php _e( 'Default article settings' ); ?></span></legend>
<label for="default_comment_status">
<input name="default_comment_status" type="checkbox" id="default_comment_status" value="open" <?php checked( 'open', get_option( 'default_comment_status' ) ); ?> />
<?php _e( 'Allow people to submit comments on new posts' ); ?></label>
<br />
<p class="description"><?php echo '(' . __( 'These settings may be overridden for individual posts.' ) . ')'; ?></p>
</fieldset></td>
</tr>
<tr>
<th scope="row"><?php _e( 'Other comment settings' ); ?></th>
<td><fieldset><legend class="screen-reader-text"><span><?php _e( 'Other comment settings' ); ?></span></legend>
<label for="require_name_email"><input type="checkbox" name="require_name_email" id="require_name_email" value="1" <?php checked( '1', get_option( 'require_name_email' ) ); ?> /> <?php _e( 'Comment author must fill out name and email' ); ?></label>
<br />
<label for="comment_registration">
<input name="comment_registration" type="checkbox" id="comment_registration" value="1" <?php checked( '1', get_option( 'comment_registration' ) ); ?> />
<?php _e( 'Users must be registered and logged in to comment' ); ?>
<?php
if ( ! get_option( 'users_can_register' ) && is_multisite() ) {
	echo ' ' . __( '(Signup has been disabled. Only members of this site can comment.)' );}
?>
</label>
<br />

<label for="close_comments_for_old_posts">
<input name="close_comments_for_old_posts" type="checkbox" id="close_comments_for_old_posts" value="1" <?php checked( '1', get_option( 'close_comments_for_old_posts' ) ); ?> />
<?php
printf(
	/* translators: %s: Number of days. */
	__( 'Automatically close comments on posts older than %s days' ),
	'</label> <label for="close_comments_days_old"><input name="close_comments_days_old" type="number" min="0" step="1" id="close_comments_days_old" value="' . esc_attr( get_option( 'close_comments_days_old' ) ) . '" class="small-text" />'
);
?>
</label>
<br />

<label for="show_comments_cookies_opt_in">
<input name="show_comments_cookies_opt_in" type="checkbox" id="show_comments_cookies_opt_in" value="1" <?php checked( '1', get_option( 'show_comments_cookies_opt_in' ) ); ?> />
<?php _e( 'Show comments cookies opt-in checkbox, allowing comment author cookies to be set' ); ?>
</label>
<br />

<label for="thread_comments">
<input name="thread_comments" type="checkbox" id="thread_comments" value="1" <?php checked( '1', get_option( 'thread_comments' ) ); ?> />
<?php
/**
 * Filters the maximum depth of threaded/nested comments.
 *
 * @since 2.7.0
 *
 * @param int $max_depth The maximum depth of threaded comments. Default 10.
 */
$maxdeep = (int) apply_filters( 'thread_comments_depth_max', 10 );

$thread_comments_depth = '</label> <label for="thread_comments_depth"><select name="thread_comments_depth" id="thread_comments_depth">';
for ( $i = 2; $i <= $maxdeep; $i++ ) {
	$thread_comments_depth .= "<option value='" . esc_attr( $i ) . "'";
	if ( get_option( 'thread_comments_depth' ) == $i ) {
		$thread_comments_depth .= " selected='selected'";
	}
	$thread_comments_depth .= ">$i</option>";
}
$thread_comments_depth .= '</select>';

/* translators: %s: Number of levels. */
printf( __( 'Enable threaded (nested) comments %s levels deep' ), $thread_comments_depth );

?>
</label>
<br />
<label for="page_comments">
<input name="page_comments" type="checkbox" id="page_comments" value="1" <?php checked( '1', get_option( 'page_comments' ) ); ?> />
<?php
$default_comments_page = '</label> <label for="default_comments_page"><select name="default_comments_page" id="default_comments_page"><option value="newest"';
if ( 'newest' === get_option( 'default_comments_page' ) ) {
	$default_comments_page .= ' selected="selected"';
}
$default_comments_page .= '>' . __( 'last' ) . '</option><option value="oldest"';
if ( 'oldest' === get_option( 'default_comments_page' ) ) {
	$default_comments_page .= ' selected="selected"';
}
$default_comments_page .= '>' . __( 'first' ) . '</option></select>';
printf(
	/* translators: 1: Form field control for number of top level comments per page, 2: Form field control for the 'first' or 'last' page. */
	__( 'Break comments into pages with %1$s top level comments per page and the %2$s page displayed by default' ),
	'</label> <label for="comments_per_page"><input name="comments_per_page" type="number" step="1" min="0" id="comments_per_page" value="' . esc_attr( get_option( 'comments_per_page' ) ) . '" class="small-text" />',
	$default_comments_page
);
?>
</label>
<br />
<label for="comment_order">
<?php

$comment_order = '<select name="comment_order" id="comment_order"><option value="asc"';
if ( 'asc' === get_option( 'comment_order' ) ) {
	$comment_order .= ' selected="selected"';
}
$comment_order .= '>' . __( 'older' ) . '</option><option value="desc"';
if ( 'desc' === get_option( 'comment_order' ) ) {
	$comment_order .= ' selected="selected"';
}
$comment_order .= '>' . __( 'newer' ) . '</option></select>';

/* translators: %s: Form field control for 'older' or 'newer' comments. */
printf( __( 'Comments should be displayed with the %s comments at the top of each page' ), $comment_order );

?>
</label>
</fieldset></td>
</tr>
<tr>
<th scope="row"><?php _e( 'Email me whenever' ); ?></th>
<td><fieldset><legend class="screen-reader-text"><span><?php _e( 'Email me whenever' ); ?></span></legend>
<label for="comments_notify">
<input name="comments_notify" type="checkbox" id="comments_notify" value="1" <?php checked( '1', get_option( 'comments_notify' ) ); ?> />
<?php _e( 'Anyone posts a comment' ); ?> </label>
<br />
<label for="moderation_notify">
<input name="moderation_notify" type="checkbox" id="moderation_notify" value="1" <?php checked( '1', get_option( 'moderation_notify' ) ); ?> />
<?php _e( 'A comment is held for moderation' ); ?> </label>
</fieldset></td>
</tr>
<tr>
<th scope="row"><?php _e( 'Before a comment appears' ); ?></th>
<td><fieldset><legend class="screen-reader-text"><span><?php _e( 'Before a comment appears' ); ?></span></legend>
<label for="comment_moderation">
<input name="comment_moderation" type="checkbox" id="comment_moderation" value="1" <?php checked( '1', get_option( 'comment_moderation' ) ); ?> />
<?php _e( 'Comment must be manually approved' ); ?> </label>
<br />
<label for="comment_previously_approved"><input type="checkbox" name="comment_previously_approved" id="comment_previously_approved" value="1" <?php checked( '1', get_option( 'comment_previously_approved' ) ); ?> /> <?php _e( 'Comment author must have a previously approved comment' ); ?></label>
</fieldset></td>
</tr>
<tr>
<th scope="row"><?php _e( 'Comment Moderation' ); ?></th>
<td><fieldset><legend class="screen-reader-text"><span><?php _e( 'Comment Moderation' ); ?></span></legend>
<p><label for="comment_max_links">
<?php
printf(
	/* translators: %s: Number of links. */
	__( 'Hold a comment in the queue if it contains %s or more links. (A common characteristic of comment spam is a large number of hyperlinks.)' ),
	'<input name="comment_max_links" type="number" step="1" min="0" id="comment_max_links" value="' . esc_attr( get_option( 'comment_max_links' ) ) . '" class="small-text" />'
);
?>
</label></p>
</fieldset></td>
</tr>
<?php do_settings_fields( 'discussion', 'default' ); ?>
</table>

<h2 class="title"><?php _e( 'Avatars' ); ?></h2>

<p><?php _e( 'An avatar is an image that appears beside your name when you comment. It is used a visual identification of the comment author for all comments on the site.' ); ?></p>

<?php

$show_avatars       = get_option( 'show_avatars' );
$show_avatars_class = '';
if ( ! $show_avatars ) {
	$show_avatars_class = ' hide-if-js';
}
?>

<table class="form-table" role="presentation">
<tr>
<th scope="row"><?php _e( 'Avatar Display' ); ?></th>
<td>
	<label for="show_avatars">
		<input type="checkbox" id="show_avatars" name="show_avatars" value="1" <?php checked( $show_avatars, 1 ); ?> />
		<?php _e( 'Show Avatars' ); ?>
	</label>
</td>
</tr>
<?php do_settings_fields( 'discussion', 'avatars' ); ?>
</table>

<?php do_settings_sections( 'discussion' ); ?>

<?php submit_button(); ?>
</form>
</div>

<?php require_once ABSPATH . 'wp-admin/admin-footer.php'; ?>
