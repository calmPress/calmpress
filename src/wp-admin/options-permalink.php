<?php
/**
 * Permalink Settings Administration Screen.
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
$title       = __( 'Permalink Settings' );
$parent_file = 'options-general.php';

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' => '<p>' . __( 'Permalinks are the permanent URLs to your individual pages and blog posts, as well as your category and tag archives. A permalink is the web address used to link to your content. The URL to each post should be permanent, and never change &#8212; hence the name permalink.' ) . '</p>' .
			'<p>' . __( 'This screen allows you to choose your permalink structure. You can choose from common settings or create custom URL structures.' ) . '</p>' .
			'<p>' . __( 'You must click the Save Changes button at the bottom of the screen for new settings to take effect.' ) . '</p>',
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'permalink-settings',
		'title'   => __( 'Permalink Settings' ),
		'content' => '<p>' . __( 'Permalinks can contain useful information, such as the post date, title, or other elements. You can choose from any of the suggested permalink formats, or you can craft your own if you select Custom Structure.' ) . '</p>' .
			'<p>' . sprintf(
				/* translators: %s: Percent sign (%). */
				__( 'If you pick an option other than Plain, your general URL path with structure tags (terms surrounded by %s) will also appear in the custom structure field and your path can be further modified there.' ),
				'<code>%</code>'
			) . '</p>' .
			'<p>' . sprintf(
				/* translators: 1: %category%, 2: %tag% */
				__( 'When you assign multiple categories or tags to a post, only one can show up in the permalink: the lowest numbered category. This applies if your custom structure includes %1$s or %2$s.' ),
				'<code>%category%</code>',
				'<code>%tag%</code>'
			) . '</p>' .
			'<p>' . __( 'You must click the Save Changes button at the bottom of the screen for new settings to take effect.' ) . '</p>',
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'custom-structures',
		'title'   => __( 'Custom Structures' ),
		'content' => '<p>' . __( 'The Optional fields let you customize the &#8220;category&#8221; and &#8220;tag&#8221; base names that will appear in archive URLs. For example, the page listing all posts in the &#8220;Uncategorized&#8221; category could be <code>/topics/uncategorized</code> instead of <code>/category/uncategorized</code>.' ) . '</p>' .
			'<p>' . __( 'You must click the Save Changes button at the bottom of the screen for new settings to take effect.' ) . '</p>',
	)
);

$home_path           = ABSPATH;
$permalink_structure = get_option( 'permalink_structure' );

$prefix      = '';
$blog_prefix = '';

/*
 * In a subdirectory configuration of multisite, the `/blog` prefix is used by
 * default on the main site to avoid collisions with other sites created on that
 * network. If the `permalink_structure` option has been changed to remove this
 * base prefix, calmPress core can no longer account for the possible collision.
 */
if ( is_multisite() && ! is_subdomain_install() && is_main_site() && 0 === strpos( $permalink_structure, '/blog/' ) ) {
	$blog_prefix = '/blog';
}

$category_base = get_option( 'category_base' );
$tag_base      = get_option( 'tag_base' );

$structure_updated        = false;
$htaccess_update_required = false;

if ( isset( $_POST['permalink_structure'] ) || isset( $_POST['category_base'] ) ) {
	check_admin_referer( 'update-permalink' );

	if ( isset( $_POST['permalink_structure'] ) ) {
		if ( isset( $_POST['selection'] ) && 'custom' !== $_POST['selection'] ) {
			$permalink_structure = $_POST['selection'];
		} else {
			$permalink_structure = $_POST['permalink_structure'];

			// A custom permalink structure have to include the post name. If it doesn't
			// revert to the current structure.
			if ( false === strpos( $permalink_structure, '%postname%' ) ) {
				add_settings_error( 'permalink_structure', 'invalid_permalink_structure', __( 'A %postname% tag is required when using custom permalinks.' ) );
				$permalink_structure = get_option( 'permalink_structure' );
			}
		}

		$permalink_structure = preg_replace( '#/+#', '/', '/' . str_replace( '#', '', $permalink_structure ) );
		if ( $prefix && $blog_prefix ) {
			$permalink_structure = $prefix . preg_replace( '#^/?index\.php#', '', $permalink_structure );
		} else {
			$permalink_structure = $blog_prefix . $permalink_structure;
		}

		$permalink_structure = sanitize_option( 'permalink_structure', $permalink_structure );

		$wp_rewrite->set_permalink_structure( $permalink_structure );

		$structure_updated = true;
	}

	if ( isset( $_POST['category_base'] ) ) {
		$category_base = $_POST['category_base'];

		if ( ! empty( $category_base ) ) {
			$category_base = $blog_prefix . preg_replace( '#/+#', '/', '/' . str_replace( '#', '', $category_base ) );
		}

		$wp_rewrite->set_category_base( $category_base );
	}

	if ( isset( $_POST['tag_base'] ) ) {
		$tag_base = $_POST['tag_base'];

		if ( ! empty( $tag_base ) ) {
			$tag_base = $blog_prefix . preg_replace( '#/+#', '/', '/' . str_replace( '#', '', $tag_base ) );
		}

		$wp_rewrite->set_tag_base( $tag_base );
	}
}

if ( is_nginx() ) {
	$writable = false;
} else {
	if ( ( ! file_exists( $home_path . '.htaccess' ) && is_writable( $home_path ) ) || is_writable( $home_path . '.htaccess' ) ) {
		$writable = true;
	} else {
		$writable       = false;
		$existing_rules = array_filter( extract_from_markers( $home_path . '.htaccess', 'WordPress' ) );
		$new_rules      = array_filter( explode( "\n", $wp_rewrite->mod_rewrite_rules() ) );

		$htaccess_update_required = ( $new_rules !== $existing_rules );
	}
}

$using_index_permalinks = $wp_rewrite->using_index_permalinks();

if ( $structure_updated ) {
	$message = __( 'Permalink structure updated.' );

	if ( ! is_multisite() ) {
		if ( ! is_nginx() && $htaccess_update_required && ! $writable ) {
			$message = sprintf(
				/* translators: %s: .htaccess */
				__( 'You should update your %s file now.' ),
				'<code>.htaccess</code>'
			);
		}
	}

	if ( ! get_settings_errors() ) {
		add_settings_error( 'general', 'settings_updated', $message, 'success' );
	}

	set_transient( 'settings_errors', get_settings_errors(), 30 );

	wp_redirect( admin_url( 'options-permalink.php?settings-updated=true' ) );
	exit;
}

flush_rewrite_rules();

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap">
<h1><?php echo esc_html( $title ); ?></h1>

<form name="form" action="options-permalink.php" method="post">
<?php wp_nonce_field( 'update-permalink' ); ?>

	<p>
	<?php
		_e( 'calmPress offers you the ability to create a custom URL structure for your permalinks and archives. Custom URL structures can improve the aesthetics, usability, and forward-compatibility of your links. A number of tags are available, and here are some examples to get you started.' );
	?>
	</p>

<?php
if ( is_multisite() && ! is_subdomain_install() && is_main_site() && 0 === strpos( $permalink_structure, '/blog/' ) ) {
	$permalink_structure = preg_replace( '|^/?blog|', '', $permalink_structure );
	$category_base       = preg_replace( '|^/?blog|', '', $category_base );
	$tag_base            = preg_replace( '|^/?blog|', '', $tag_base );
}

/*
 * Weird looking array indexing to keep max code compatibility with WordPress core,
 * While removing the plain and number based permalinks options.
 */
$structures = array(
	1 => $prefix . '/%year%/%monthnum%/%day%/%postname%/',
	2 => $prefix . '/%year%/%monthnum%/%postname%/',
	4 => $prefix . '/%postname%/',
);
?>
<h2 class="title"><?php _e( 'Common Settings' ); ?></h2>
<table class="form-table permalink-structure">
	<tr>
		<th scope="row"><label><input name="selection" type="radio" value="<?php echo esc_attr( $structures[1] ); ?>" <?php checked( $structures[1], $permalink_structure ); ?> /> <?php _e( 'Day and name' ); ?></label></th>
		<td><code><?php echo get_option( 'home' ) . $blog_prefix . $prefix . '/' . gmdate( 'Y' ) . '/' . gmdate( 'm' ) . '/' . gmdate( 'd' ) . '/' . _x( 'sample-post', 'sample permalink structure' ) . '/'; ?></code></td>
	</tr>
	<tr>
		<th scope="row"><label><input name="selection" type="radio" value="<?php echo esc_attr( $structures[2] ); ?>" <?php checked( $structures[2], $permalink_structure ); ?> /> <?php _e( 'Month and name' ); ?></label></th>
		<td><code><?php echo get_option( 'home' ) . $blog_prefix . $prefix . '/' . gmdate( 'Y' ) . '/' . gmdate( 'm' ) . '/' . _x( 'sample-post', 'sample permalink structure' ) . '/'; ?></code></td>
	</tr>
	<tr>
		<th scope="row"><label><input name="selection" type="radio" value="<?php echo esc_attr( $structures[4] ); ?>" <?php checked( $structures[4], $permalink_structure ); ?> /> <?php _e( 'Post name' ); ?></label></th>
		<td><code><?php echo get_option( 'home' ) . $blog_prefix . $prefix . '/' . _x( 'sample-post', 'sample permalink structure' ) . '/'; ?></code></td>
	</tr>
	<tr>
		<th scope="row">
			<label><input name="selection" id="custom_selection" type="radio" value="custom" <?php checked( ! in_array( $permalink_structure, $structures, true ) ); ?> />
			<?php _e( 'Custom Structure' ); ?>
			</label>
		</th>
		<td>
			<code><?php echo get_option( 'home' ) . $blog_prefix; ?></code>
			<input name="permalink_structure" id="permalink_structure" type="text" value="<?php echo esc_attr( $permalink_structure ); ?>" class="regular-text code" />
			<div class="available-structure-tags hide-if-no-js">
				<div id="custom_selection_updated" aria-live="assertive" class="screen-reader-text"></div>
				<?php
				$available_tags = array(
					/* translators: %s: Permalink structure tag. */
					'year'     => __( '%s (The year of the post, four digits, for example 2004.)' ),
					/* translators: %s: Permalink structure tag. */
					'monthnum' => __( '%s (Month of the year, for example 05.)' ),
					/* translators: %s: Permalink structure tag. */
					'day'      => __( '%s (Day of the month, for example 28.)' ),
					/* translators: %s: Permalink structure tag. */
					'hour'     => __( '%s (Hour of the day, for example 15.)' ),
					/* translators: %s: Permalink structure tag. */
					'minute'   => __( '%s (Minute of the hour, for example 43.)' ),
					/* translators: %s: Permalink structure tag. */
					'second'   => __( '%s (Second of the minute, for example 33.)' ),
					/* translators: %s: permalink structure tag */
					'postname' => __( '%s (The sanitized post title (slug).)' ),
					/* translators: %s: Permalink structure tag. */
					'category' => __( '%s (Category slug. Nested sub-categories appear as nested directories in the URL.)' ),
					/* translators: %s: Permalink structure tag. */
					'author'   => __( '%s (A sanitized version of the author name.)' ),
				);

				/**
				 * Filters the list of available permalink structure tags on the Permalinks settings page.
				 *
				 * @since 4.9.0
				 *
				 * @param string[] $available_tags An array of key => value pairs of available permalink structure tags.
				 */
				$available_tags = apply_filters( 'available_permalink_structure_tags', $available_tags );

				/* translators: %s: Permalink structure tag. */
				$structure_tag_added = __( '%s added to permalink structure' );

				/* translators: %s: Permalink structure tag. */
				$structure_tag_already_used = __( '%s (already used in permalink structure)' );

				if ( ! empty( $available_tags ) ) :
					?>
					<p><?php _e( 'Available tags:' ); ?></p>
					<ul role="list">
						<?php
						foreach ( $available_tags as $tag => $explanation ) {
							?>
							<li>
								<button type="button"
										class="button button-secondary"
										aria-label="<?php echo esc_attr( sprintf( $explanation, $tag ) ); ?>"
										data-added="<?php echo esc_attr( sprintf( $structure_tag_added, $tag ) ); ?>"
										data-used="<?php echo esc_attr( sprintf( $structure_tag_already_used, $tag ) ); ?>">
									<?php echo '%' . $tag . '%'; ?>
								</button>
							</li>
							<?php
						}
						?>
					</ul>
				<?php endif; ?>
			</div>
		</td>
	</tr>
</table>

<h2 class="title"><?php _e( 'Optional' ); ?></h2>
<p>
<?php
/* translators: %s: Placeholder that must come at the start of the URL. */
printf( __( 'If you like, you may enter custom structures for your category and tag URLs here. For example, using <code>topics</code> as your category base would make your category links like <code>%s/topics/uncategorized/</code>. If you leave these blank the defaults will be used.' ), get_option( 'home' ) . $blog_prefix . $prefix );
?>
</p>

<table class="form-table" role="presentation">
	<tr>
		<th><label for="category_base"><?php /* translators: Prefix for category permalinks. */ _e( 'Category base' ); ?></label></th>
		<td><?php echo $blog_prefix; ?> <input name="category_base" id="category_base" type="text" value="<?php echo esc_attr( $category_base ); ?>" class="regular-text code" /></td>
	</tr>
	<tr>
		<th><label for="tag_base"><?php _e( 'Tag base' ); ?></label></th>
		<td><?php echo $blog_prefix; ?> <input name="tag_base" id="tag_base" type="text" value="<?php echo esc_attr( $tag_base ); ?>" class="regular-text code" /></td>
	</tr>
	<?php do_settings_fields( 'permalink', 'optional' ); ?>
</table>

<?php do_settings_sections( 'permalink' ); ?>

<?php submit_button(); ?>
</form>
<?php if ( ! is_multisite() ) {
	if ( ! $writable && $htaccess_update_required ) :
		?>
<p id="htaccess-description">
		<?php
		printf(
			/* translators: 1: .htaccess, 2: .htaccess section indicator */
			__( 'If your %1$s file was writable, we could do this automatically, but it isn&#8217;t so these are the rules you should have in your %1$s file. Copy the text from the field below into your %1$s file replacing the %2$s section.' ),
			'<code>.htaccess</code>',
			'<code># BEGIN Wordress ... # END WordPress</code>'
		);
		?>
</p>
<form action="options-permalink.php" method="post">
		<?php wp_nonce_field( 'update-permalink' ); ?>
	<p><label for="rules"><?php _e( 'Rewrite rules:' ); ?></label><br /><textarea rows="8" class="large-text readonly" name="rules" id="rules" readonly="readonly" aria-describedby="htaccess-description"><?php echo esc_textarea( "# BEGIN WordPress\n" . $wp_rewrite->mod_rewrite_rules() . "# END WordPress\n" ); ?></textarea></p>
</form>
	<?php endif; ?>
<?php } // End if ! is_multisite(). ?>

</div>

<?php require_once ABSPATH . 'wp-admin/admin-footer.php'; ?>
