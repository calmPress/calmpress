<?php
/**
 * Reading settings administration panel.
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
$title       = __( 'Reading Settings' );
$parent_file = 'options-general.php';

add_action( 'admin_head', 'options_reading_add_js' );

require_once ABSPATH . 'wp-admin/admin-header.php';
?>

<div class="wrap">
<h1><?php echo esc_html( $title ); ?></h1>

<form method="post" action="options.php">
<?php
settings_fields( 'reading' );

if ( ! is_utf8_charset() ) {
	add_settings_field( 'blog_charset', __( 'Encoding for pages and feeds' ), 'options_reading_blog_charset', 'reading', 'default', array( 'label_for' => 'blog_charset' ) );
}
?>

<?php if ( ! get_pages() ) : ?>
<input name="show_on_front" type="hidden" value="posts" />
<table class="form-table" role="presentation">
	<?php
	if ( 'posts' !== get_option( 'show_on_front' ) ) :
		update_option( 'show_on_front', 'posts' );
	endif;

else :
	if ( 'page' === get_option( 'show_on_front' ) && ! get_option( 'page_on_front' ) && ! get_option( 'page_for_posts' ) ) {
		update_option( 'show_on_front', 'posts' );
	}

	$your_homepage_displays_title = __( 'Your homepage displays' );
	?>
<table class="form-table" role="presentation">
<tr>
<th scope="row"><?php echo $your_homepage_displays_title; ?></th>
<td id="front-static-pages"><fieldset>
	<legend class="screen-reader-text"><span><?php echo $your_homepage_displays_title; ?></span></legend>
	<p><label>
		<input name="show_on_front" type="radio" value="posts" class="tog" <?php checked( 'posts', get_option( 'show_on_front' ) ); ?> />
		<?php _e( 'Your latest posts' ); ?>
	</label>
	</p>
	<p><label>
		<input name="show_on_front" type="radio" value="page" class="tog" <?php checked( 'page', get_option( 'show_on_front' ) ); ?> />
		<?php
		printf(
			/* translators: %s: URL to Pages screen. */
			__( 'A <a href="%s">static page</a> (select below)' ),
			'edit.php?post_type=page'
		);
		?>
	</label>
	</p>
<ul>
	<li><label for="page_on_front">
	<?php
	printf(
		/* translators: %s: Select field to choose the front page. */
		__( 'Homepage: %s' ),
		wp_dropdown_pages(
			array(
				'name'              => 'page_on_front',
				'echo'              => 0,
				'show_option_none'  => __( '&mdash; Select &mdash;' ),
				'option_none_value' => '0',
				'selected'          => get_option( 'page_on_front' ),
			)
		)
	);
	?>
</label></li>
	<li><label for="page_for_posts">
	<?php
	printf(
		/* translators: %s: Select field to choose the page for posts. */
		__( 'Posts page: %s' ),
		wp_dropdown_pages(
			array(
				'name'              => 'page_for_posts',
				'echo'              => 0,
				'show_option_none'  => __( '&mdash; Select &mdash;' ),
				'option_none_value' => '0',
				'selected'          => get_option( 'page_for_posts' ),
			)
		)
	);
	?>
</label></li>
</ul>
	<?php
	if ( 'page' === get_option( 'show_on_front' ) && get_option( 'page_for_posts' ) === get_option( 'page_on_front' ) ) :
		wp_admin_notice(
			__( '<strong>Warning:</strong> these pages should not be the same!' ),
			array(
				'type'               => 'warning',
				'id'                 => 'front-page-warning',
				'additional_classes' => array( 'inline' ),
			)
		);
	endif;
	?>
</fieldset></td>
</tr>
<?php endif; ?>
<tr>
<th scope="row"><label for="posts_per_page"><?php _e( 'Blog pages show at most' ); ?></label></th>
<td>
<input name="posts_per_page" type="number" step="1" min="1" id="posts_per_page" value="<?php form_option( 'posts_per_page' ); ?>" class="small-text" /> <?php _e( 'posts' ); ?>
</td>
</tr>
<tr>
<th scope="row"><label for="posts_per_rss"><?php _e( 'Syndication feeds show the most recent' ); ?></label></th>
<td><input name="posts_per_rss" type="number" step="1" min="0" id="posts_per_rss" value="<?php form_option( 'posts_per_rss' ); ?>" class="small-text" /> <?php _e( 'items' ); ?>
<p class="description"><?php esc_html_e( 'A value of zero can be used to turn off the feeds.' ); ?></p>
</td>
</tr>

<?php $rss_use_excerpt_title = __( 'For each post in a feed, include' ); ?>
<tr>
<th scope="row"><?php echo $rss_use_excerpt_title; ?> </th>
<td><fieldset>
	<legend class="screen-reader-text"><span><?php echo $rss_use_excerpt_title; ?></span></legend>
	<p>
		<label><input name="rss_use_excerpt" type="radio" value="0" <?php checked( 0, get_option( 'rss_use_excerpt' ) ); ?>	/> <?php _e( 'Full text' ); ?></label><br />
		<label><input name="rss_use_excerpt" type="radio" value="1" <?php checked( 1, get_option( 'rss_use_excerpt' ) ); ?> /> <?php _e( 'Excerpt' ); ?></label>
	</p>
	<p class="description">
		<?php
		printf(
			/* translators: %s: Documentation URL. */
			__( 'Your theme determines how content is displayed in browsers. <a href="%s">Learn more about feeds</a>.' ),
			__( 'https://developer.wordpress.org/advanced-administration/wordpress/feeds/' )
		);
		?>
	</p>
</fieldset></td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Embedding' ); ?></th>
<td><fieldset><legend class="screen-reader-text"><span><?php _e( 'Embedding' ); ?> </span></legend>
<label><input name="calm_embedding_on" type="checkbox" value="1" <?php checked( 1, get_option( 'calm_embedding_on' ) ); ?>	/> <?php _e( 'Allow other sites to embed content from this one.' ); ?></label>
</fieldset></td>
</tr>

<?php do_settings_fields( 'reading', 'default' ); ?>
</table>

<?php do_settings_sections( 'reading' ); ?>

<?php submit_button(); ?>
</form>
</div>
<?php require_once ABSPATH . 'wp-admin/admin-footer.php'; ?>
