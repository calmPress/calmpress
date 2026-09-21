<?php
/**
 * Edit Site Info Administration Screen
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 3.1.0
 */

/** Load WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'manage_sites' ) ) {
	wp_die( __( 'Sorry, you are not allowed to edit this site.' ) );
}

$id = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0;

if ( ! $id ) {
	wp_die( __( 'Invalid site ID.' ) );
}

$details = get_site( $id );
if ( ! $details ) {
	wp_die( __( 'The requested site does not exist.' ) );
}

if ( ! can_edit_network( $details->site_id ) ) {
	wp_die( __( 'Sorry, you are not allowed to access this page.' ), 403 );
}

$parsed_scheme      = parse_url( $details->siteurl, PHP_URL_SCHEME );
$is_main_site       = is_main_site( $id );
$site_url_error     = '';
$submitted_site_url = '';

if ( isset( $_REQUEST['action'] ) && 'update-site' === $_REQUEST['action'] ) {
	check_admin_referer( 'edit-site' );

	$blog_data           = isset( $_POST['blog'] ) ? wp_unslash( $_POST['blog'] ) : array();
	$blog_data['scheme'] = 'https';

	if ( $is_main_site ) {
		// On the network's main site, don't allow the domain or path to change.
		$blog_data['domain'] = $details->domain;
		$blog_data['path']   = $details->path;
	} else {
		$submitted_site_url = trim( $blog_data['url'] ?? '' );

		if ( str_starts_with( strtolower( $submitted_site_url ), 'https://' ) ) {
			$submitted_site_url = substr( $submitted_site_url, 8 );
		} elseif ( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $submitted_site_url ) ) {
			$site_url_error = esc_html__( 'Enter a valid site address.' );
		}

		$update_parsed_url = wp_parse_url( 'https://' . $submitted_site_url );

		if (
			! $site_url_error
			&& ( false === $update_parsed_url
			|| ! filter_var( $update_parsed_url['host'] ?? '', FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME )
			|| isset( $update_parsed_url['user'] )
			|| isset( $update_parsed_url['pass'] )
			|| isset( $update_parsed_url['query'] )
			|| isset( $update_parsed_url['fragment'] ) )
		) {
			$site_url_error = esc_html__( 'Enter a valid site address.' );
		}

		if ( ! $site_url_error ) {
			// If a path is not provided, use the default of `/`.
			if ( ! isset( $update_parsed_url['path'] ) ) {
				$update_parsed_url['path'] = '/';
			}

			// Make sure to not lose the port if it was provided.
			$blog_data['domain'] = $update_parsed_url['host'];
			if ( isset( $update_parsed_url['port'] ) ) {
				$blog_data['domain'] .= ':' . $update_parsed_url['port'];
			}

			$blog_data['path'] = $update_parsed_url['path'];
		}
	}

	if ( ! $site_url_error ) {
		switch_to_blog( $id );

		// Rewrite rules can't be flushed during switch to blog.
		delete_option( 'rewrite_rules' );

		$existing_details = get_site( $id );

		update_blog_details( $id, $blog_data );

		// Maybe update home and siteurl options.
		$new_details = get_site( $id );
		$new_url     = untrailingslashit( sanitize_url( 'https://' . $new_details->domain . $new_details->path ) );

		$old_home_url    = trailingslashit( esc_url( get_option( 'home' ) ) );
		$old_home_parsed = parse_url( $old_home_url );
		$old_home_host   = $old_home_parsed['host'] . ( isset( $old_home_parsed['port'] ) ? ':' . $old_home_parsed['port'] : '' );

		if ( $old_home_host === $existing_details->domain && $old_home_parsed['path'] === $existing_details->path ) {
			update_option( 'home', $new_url );
		}

		$old_site_url    = trailingslashit( esc_url( get_option( 'siteurl' ) ) );
		$old_site_parsed = parse_url( $old_site_url );
		$old_site_host   = $old_site_parsed['host'] . ( isset( $old_site_parsed['port'] ) ? ':' . $old_site_parsed['port'] : '' );

		if ( $old_site_host === $existing_details->domain && $old_site_parsed['path'] === $existing_details->path ) {
			update_option( 'siteurl', $new_url );
		}

		restore_current_blog();
		wp_redirect(
			add_query_arg(
				array(
					'update' => 'updated',
					'id'     => $id,
				),
				'site-info.php'
			)
		);
		exit;
	}
}

if ( isset( $_GET['update'] ) ) {
	$messages = array();
	if ( 'updated' === $_GET['update'] ) {
		$messages[] = __( 'Site info updated.' );
	}
}

// Used in the HTML title tag.
/* translators: %s: Site title. */
$title = sprintf( __( 'Edit Site: %s' ), esc_html( $details->blogname ) );

$parent_file  = 'sites.php';
$submenu_file = 'sites.php';

require_once ABSPATH . 'wp-admin/admin-header.php';

?>

<div class="wrap">
<h1 id="edit-site"><?php echo $title; ?></h1>
<p class="edit-site-actions"><a href="<?php echo esc_url( get_home_url( $id, '/' ) ); ?>"><?php _e( 'Visit' ); ?></a> | <a href="<?php echo esc_url( get_admin_url( $id ) ); ?>"><?php _e( 'Dashboard' ); ?></a></p>
<?php

network_edit_site_nav(
	array(
		'blog_id'  => $id,
		'selected' => 'site-info',
	)
);

if ( $site_url_error ) {
	wp_admin_notice(
		$site_url_error,
		array(
			'type'        => 'error',
			'dismissible' => true,
		)
	);
}

if ( ! empty( $messages ) ) {
	$notice_args = array(
		'type'        => 'success',
		'dismissible' => true,
		'id'          => 'message',
	);

	foreach ( $messages as $msg ) {
		wp_admin_notice( $msg, $notice_args );
	}
}
?>
<form method="post" action="site-info.php?action=update-site">
	<?php wp_nonce_field( 'edit-site' ); ?>
	<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>" />
	<table class="form-table" role="presentation">
		<?php
		// The main site of the network should not be updated on this page.
		if ( $is_main_site ) :
			?>
		<tr class="form-field">
			<th scope="row"><?php _e( 'Site Address (URL)' ); ?></th>
			<td><?php echo esc_url( $parsed_scheme . '://' . $details->domain . $details->path ); ?></td>
		</tr>
			<?php
			// For any other site, the domain and path can be changed.
		else :
			?>
		<tr class="form-field form-required">
			<th scope="row"><label for="url"><?php _e( 'Site Address (URL)' ); ?></label></th>
			<td>
				<span class="no-break">https://<input name="blog[url]" type="text" class="regular-text ltr" id="url" value="<?php echo esc_attr( $submitted_site_url ?: $details->domain . $details->path ); ?>" aria-describedby="site-address-description" autocapitalize="none" autocorrect="off" required /></span>
				<p id="site-address-description" class="description"><?php esc_html_e( 'Before changing the site address, configure DNS and the web server to direct this domain to this CalmPress installation, and install a valid HTTPS certificate for it.' ); ?></p>
			</td>
		</tr>
		<?php endif; ?>
	</table>

	<?php
	/**
	 * Fires at the end of the site info form in network admin.
	 *
	 * @since 5.6.0
	 *
	 * @param int $id The site ID.
	 */
	do_action( 'network_site_info_form', $id );

	submit_button();
	?>
</form>

</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
