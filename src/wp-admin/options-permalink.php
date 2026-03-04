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

$home_path           = get_home_path();
$permalink_structure = get_option( 'permalink_structure' );

$index_php_prefix = '';
$blog_prefix      = '';

/*
 * In a subdirectory configuration of multisite, the `/blog` prefix is used by
 * default on the main site to avoid collisions with other sites created on that
 * network. If the `permalink_structure` option has been changed to remove this
 * base prefix, calmPress core can no longer account for the possible collision.
 */
if ( is_multisite() && ! is_subdomain_install() && is_main_site()
	&& str_starts_with( $permalink_structure, '/blog/' )
) {
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

		if ( $index_php_prefix && $blog_prefix ) {
			$permalink_structure = $index_php_prefix . preg_replace( '#^/?index\.php#', '', $permalink_structure );
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
	if ( ( ! file_exists( $home_path . '.htaccess' )
		&& is_writable( $home_path ) ) || is_writable( $home_path . '.htaccess' )
	) {
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

	set_transient( 'settings_errors', get_settings_errors(), 30 ); // 30 seconds.

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
printf(
	/* translators: %s: Documentation URL. */
	__( 'calmPress offers you the ability to create a custom URL structure for your permalinks and archives. Custom URL structures can improve the aesthetics, usability, and forward-compatibility of your links. A <a href="%s">number of tags are available</a>, and here are some examples to get you started.' ),
	__( 'https://wordpress.org/documentation/article/customize-permalinks/' )
);
?>
</p>

<?php
if ( is_multisite() && ! is_subdomain_install() && is_main_site()
	&& str_starts_with( $permalink_structure, '/blog/' )
) {
	$permalink_structure = preg_replace( '|^/?blog|', '', $permalink_structure );
	$category_base       = preg_replace( '|^/?blog|', '', $category_base );
	$tag_base            = preg_replace( '|^/?blog|', '', $tag_base );
}

$url_base = home_url( $blog_prefix . $index_php_prefix );

$default_structures = array(
	array(
		'id'      => 'day-name',
		'label'   => __( 'Day and name' ),
		'value'   => $index_php_prefix . '/%year%/%monthnum%/%day%/%postname%/',
		'example' => $url_base . '/' . gmdate( 'Y/m/d' ) . '/' . _x( 'sample-post', 'sample permalink structure' ) . '/',
	),
	array(
		'id'      => 'month-name',
		'label'   => __( 'Month and name' ),
		'value'   => $index_php_prefix . '/%year%/%monthnum%/%postname%/',
		'example' => $url_base . '/' . gmdate( 'Y/m' ) . '/' . _x( 'sample-post', 'sample permalink structure' ) . '/',
	),
	array(
		'id'      => 'post-name',
		'label'   => __( 'Post name' ),
		'value'   => $index_php_prefix . '/%postname%/',
		'example' => $url_base . '/' . _x( 'sample-post', 'sample permalink structure' ) . '/',
	),
);

$default_structure_values = wp_list_pluck( $default_structures, 'value' );

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
	/* translators: %s: Permalink structure tag. */
	'post_id'  => __( '%s (The unique ID of the post, for example 423.)' ),
	/* translators: %s: Permalink structure tag. */
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
$tag_added = __( '%s added to permalink structure' );
/* translators: %s: Permalink structure tag. */
$tag_removed = __( '%s removed from permalink structure' );
/* translators: %s: Permalink structure tag. */
$tag_already_used = __( '%s (already used in permalink structure)' );
?>
<h2 class="title"><?php _e( 'Common Settings' ); ?></h2>
<p>
<?php
printf(
	/* translators: %s: %postname% */
	__( 'Select the permalink structure for your website. Including the %s tag makes links easy to understand, and can help your posts rank higher in search engines.' ),
	'<code>%postname%</code>'
);
?>
</p>
<table class="form-table permalink-structure" role="presentation">
<tbody>
<?php $permalink_structure_title = __( 'Permalink structure' ); ?>
<tr>
	<th scope="row"><?php echo $permalink_structure_title; ?></th>
	<td>
		<fieldset class="structure-selection">
			<legend class="screen-reader-text"><?php echo $permalink_structure_title; ?></legend>
			<?php foreach ( $default_structures as $input ) : ?>
			<div class="row">
				<input id="permalink-input-<?php echo esc_attr( $input['id'] ); ?>"
					name="selection" aria-describedby="permalink-<?php echo esc_attr( $input['id'] ); ?>"
					type="radio" value="<?php echo esc_attr( $input['value'] ); ?>"
					<?php checked( $input['value'], $permalink_structure ); ?>
				/>
				<div>
					<label for="permalink-input-<?php echo esc_attr( $input['id'] ); ?>">
						<?php echo esc_html( $input['label'] ); ?>
					</label>
					<p>
						<code id="permalink-<?php echo esc_attr( $input['id'] ); ?>">
							<?php echo esc_html( $input['example'] ); ?>
						</code>
					</p>
				</div>
			</div><!-- .row -->
			<?php endforeach; ?>

			<div class="row">
				<input id="custom_selection"
					name="selection" type="radio" value="custom"
					<?php checked( ! in_array( $permalink_structure, $default_structure_values, true ) ); ?>
				/>
				<div>
					<label for="custom_selection"><?php _e( 'Custom Structure' ); ?></label>
					<p>
						<label for="permalink_structure" class="screen-reader-text">
							<?php
							/* translators: Hidden accessibility text. */
							_e( 'Customize permalink structure by selecting available tags' );
							?>
						</label>
						<span class="code">
							<code id="permalink-custom"><?php echo esc_url( $url_base ); ?></code>
							<input name="permalink_structure" id="permalink_structure"
								type="text" value="<?php echo esc_attr( $permalink_structure ); ?>"
								aria-describedby="permalink-custom" class="regular-text code"
							/>
						</span>
					</p>

					<div class="available-structure-tags hide-if-no-js">
						<div id="custom_selection_updated" aria-live="assertive" class="screen-reader-text"></div>
						<?php if ( ! empty( $available_tags ) ) : ?>
						<fieldset>
							<legend><?php _e( 'Available tags:' ); ?></legend>
							<ul role="list">
							<?php foreach ( $available_tags as $tag => $explanation ) : ?>
								<li>
									<button type="button"
										class="button button-secondary"
										aria-label="<?php echo esc_attr( sprintf( $explanation, $tag ) ); ?>"
										data-added="<?php echo esc_attr( sprintf( $tag_added, $tag ) ); ?>"
										data-removed="<?php echo esc_attr( sprintf( $tag_removed, $tag ) ); ?>"
										data-used="<?php echo esc_attr( sprintf( $tag_already_used, $tag ) ); ?>">
										<?php echo '%' . esc_html( $tag ) . '%'; ?>
									</button>
								</li>
							<?php endforeach; ?>
							</ul>
						</fieldset>
						<?php endif; ?>
					</div><!-- .available-structure-tags -->
				</div>
			</div><!-- .row -->
		</fieldset><!-- .structure-selection -->
	</td>
</tr>
</tbody>
</table>

<h2 class="title"><?php _e( 'Optional' ); ?></h2>
<p>
<?php
printf(
	/* translators: %s: Placeholder that must come at the start of the URL. */
	__( 'If you like, you may enter custom structures for your category and tag URLs here. For example, using <code>topics</code> as your category base would make your category links like <code>%s/topics/uncategorized/</code>. If you leave these blank the defaults will be used.' ),
	$url_base
);
?>
</p>

<table class="form-table" role="presentation">
	<tr>
		<th>
			<label for="category_base">
				<?php /* translators: Prefix for category permalinks. */ _e( 'Category base' ); ?>
			</label>
		</th>
		<td>
			<?php echo $blog_prefix; ?>
			<input name="category_base" id="category_base" type="text"
				value="<?php echo esc_attr( $category_base ); ?>" class="regular-text code"
			/>
		</td>
	</tr>
	<tr>
		<th>
			<label for="tag_base"><?php _e( 'Tag base' ); ?></label>
		</th>
		<td>
			<?php echo $blog_prefix; ?>
			<input name="tag_base" id="tag_base" type="text"
				value="<?php echo esc_attr( $tag_base ); ?>" class="regular-text code"
			/>
		</td>
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

</div><!-- .wrap -->

<?php require_once ABSPATH . 'wp-admin/admin-footer.php'; ?>
