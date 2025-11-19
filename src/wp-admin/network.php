<?php
/**
 * Network installation administration panel.
 *
 * A multi-step process allowing the user to enable a network of calmPress sites.
 *
 * @since 3.0.0
 *
 * @package WordPress
 * @subpackage Administration
 */

define( 'WP_INSTALLING_NETWORK', true );

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'setup_network' ) ) {
	wp_die( __( 'Sorry, you are not allowed to manage options for this site.' ) );
}

if ( is_multisite() ) {
	if ( ! is_network_admin() ) {
		wp_redirect( network_admin_url( 'setup.php' ) );
		exit;
	}

	if ( ! defined( 'MULTISITE' ) ) {
		wp_die( __( 'The Network creation panel is not for calmPress MU networks.' ) );
	}
}

require_once __DIR__ . '/includes/network.php';

// We need to create references to ms global tables to enable Network.
foreach ( $wpdb->tables( 'ms_global' ) as $table => $prefixed_table ) {
	$wpdb->$table = $prefixed_table;
}

if ( ! network_domain_check() && ( ! defined( 'WP_ALLOW_MULTISITE' ) || ! WP_ALLOW_MULTISITE ) ) {
	wp_die(
		printf(
			/* translators: 1: WP_ALLOW_MULTISITE, 2: wp-config.php */
			__( 'You must define the %1$s constant as true in your %2$s file to allow creation of a Network.' ),
			'<code>WP_ALLOW_MULTISITE</code>',
			'<code>wp-config.php</code>'
		)
	);
}

if ( is_network_admin() ) {
	// Used in the HTML title tag.
	$title       = __( 'Network Setup' );
	$parent_file = 'settings.php';
} else {
	// Used in the HTML title tag.
	$title       = __( 'Create a Network of calmPress Sites' );
	$parent_file = 'tools.php';
}

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap">
<h1><?php echo esc_html( $title ); ?></h1>

<?php
if ( $_POST ) {

	check_admin_referer( 'install-network-1' );

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	// Create network tables.
	install_network();
	$base              = parse_url( trailingslashit( get_option( 'home' ) ), PHP_URL_PATH );
	$subdomain_install = allow_subdomain_install() ? ! empty( $_POST['subdomain_install'] ) : false;
	if ( ! network_domain_check() ) {
		$result = populate_network( 1, get_clean_basedomain(), sanitize_email( $_POST['email'] ), wp_unslash( $_POST['sitename'] ), $base, $subdomain_install );
		if ( is_wp_error( $result ) ) {
			if ( 1 === count( $result->get_error_codes() ) && 'no_wildcard_dns' === $result->get_error_code() ) {
				network_step2( $result );
			} else {
				network_step1( $result );
			}
		} else {
			network_step2();
		}
	} else {
		network_step2();
	}
} elseif ( is_multisite() || network_domain_check() ) {
	network_step2();
} else {
	network_step1();
}
?>
</div>

<?php require_once ABSPATH . 'wp-admin/admin-footer.php'; ?>
