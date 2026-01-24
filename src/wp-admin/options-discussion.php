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

// Used in the HTML title tag.
$title       = __( 'Discussion Settings' );
$parent_file = 'options-general.php';

add_action( 'admin_print_footer_scripts', 'options_discussion_add_js' );

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
<p class="description"><?php _e( 'Individual posts may override these settings. Changes here will only be applied to new posts.' ); ?></p>
</fieldset></td>
</tr>
<tr>
<th scope="row"><?php _e( 'Other comment settings' ); ?></th>
<td><fieldset><legend class="screen-reader-text"><span><?php _e( 'Other comment settings' ); ?></span></legend>
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
	if ( (int) get_option( 'thread_comments_depth' ) === $i ) {
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
</fieldset></td>
</tr>
<tr>
<th scope="row"><?php _e( 'Before a comment appears' ); ?></th>
<td><fieldset><legend class="screen-reader-text"><span><?php _e( 'Before a comment appears' ); ?></span></legend>
<label for="comment_moderation">
<input name="comment_moderation" type="checkbox" id="comment_moderation" value="1" <?php checked( '1', get_option( 'comment_moderation' ) ); ?> />
<?php _e( 'Comment must be manually approved' ); ?> </label>
<br />
<label for="comment_previously_approved"><input type="checkbox" name="comment_previously_approved" id="comment_previously_approved" value="1" <?php checked( '1', get_option( 'comment_previously_approved' ) ); ?> /> <?php _e( 'Comment author must have a previously approved comment on the post' ); ?></label>
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
<tr>
	<th scope="row"><label for="comment_moderator_user"><?php esc_html_e( 'Default recipient for comment moderation notifications' ); ?></label></th>
	<td>
		<?php
			$user_email = WP_User::default_comment_moderator_email();
			echo '<select name="comment_moderator_user">';
			$users = get_users( [
				'role__in' => ['administrator', 'editor'],
			] );

			foreach ( $users as $user ) {
				$selected = '';
				if ( $user->user_email === $user_email ) {
					$selected = ' selected';
				}
				echo '<option value="' . esc_attr( $user->ID ) . '"' . $selected .'>'
				     . esc_html( $user->display_name . ' ('. $user->user_email . ')' ) . '</option>';
			}
			echo '</select>';
		?>
		<p class="description">
			<?php esc_html_e( 'An administrator or editor who receives comment moderation notifications by default. Used when a post does not specify a different recipient.' ); ?>
		</p>
	</td>
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
