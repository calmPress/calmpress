<?php
/**
 * .htaccess Settings Administration Screen.
 *
 * @package calmPress
 */

namespace calmpress\admin\htaccess;

/** WordPress Administration Bootstrap */
require_once dirname( __FILE__ ) . '/admin.php';

if ( ! is_super_admin() ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to manage .htaccess for this site.' ) );
}

if ( ! got_mod_rewrite() ) {
	wp_die( esc_html__( 'Sorry, you are not using an apache web server.' ) );
}

if ( is_multisite() ) {
	wp_die( esc_html__( 'Sorry, but .htaccess for multisite is managed from the network admin.' ) );
}

$title       = __( '.htaccess Settings' );
$parent_file = 'options-general.php';

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' =>
			/* translators: %s: placeholder for the file name. */
			'<p>' . sprintf( __( 'The %s file contains configuration instructions for the Apache web server that control how it handles URLs in the local context.' ), '<code>.htaccess</code>' ) . '</p>' .
			/* translators: %s: placeholder for the file name. */
			'<p>' . sprintf( __( 'This screen allows you to add rules to the %s file without having to edit the full file with an FTP software.' ), '<code>.htaccess</code>' ) . '</p>',
	)
);

$home_path = get_home_path();

$update_required = false;

if ( ( ! file_exists( $home_path . '.htaccess' ) && is_writable( $home_path ) ) || is_writable( $home_path . '.htaccess' ) ) {
	$writable = true;
} else {
	$writable = false;
}

$existing_rules  = array_filter( extract_from_markers( $home_path . '.htaccess', 'WordPress' ) );
$new_rules       = array_filter( explode( "\n", $wp_rewrite->mod_rewrite_rules() ) );
$update_required = ( $new_rules !== $existing_rules );


add_settings_section(
	'calm-htaccess-user-section',
	__( 'User section' ),
	'',
	'htaccess'
);

add_settings_field(
	'calm-htaccess-user-section-rules',
	__( 'Rules' ),
	__NAMESPACE__ . '\rules_input',
	'htaccess',
	'calm-htaccess-user-section',
	[ 'label_for' => 'calm-htaccess-user-section-rules' ]
);

/**
 * Output the textarea in which the user rules can be edited.
 *
 * @since 1.0.0
 */
function rules_input() {
	?>
	<textarea class="large-text" name="htaccess_user_section" id="calm-htaccess-user-section-rules" rows="6"><?php echo esc_textarea( get_option( 'htaccess_user_section' ) ); ?></textarea>
	<p class="description">
		<?php
		/* translators: 1: .htaccess, 2: .htaccess section indicator */
		printf( esc_html__( 'These rules are going to be saved in the %1$s file in a section marked like %2$s' ),
			'<code>.htaccess</code>',
			'<code># BEGIN User ... # END User</code>'
		);
		?>
	</p>
	<?php
}

if ( $writable && $update_required ) {
	save_mod_rewrite_rules();
}

require ABSPATH . 'wp-admin/admin-header.php';

?>
<div class="wrap">
	<h1><?php echo esc_html( $title ); ?></h1>

	<form method="post" action="options.php" novalidate="novalidate">
		<?php
		settings_fields( 'htaccess' );
		do_settings_sections( 'htaccess' );
		if ( ! $writable && $update_required ) {
			?>
			<p>
				<?php
				printf(
					/* translators: 1: .htaccess */
					esc_html( 'If your %1$s file was writable, we could update it, but it isn&#8217;t (which is a good thing!)' ),
					'<code>.htaccess</code>'
				);
				?>
			</p>
			<p><?php esc_html_e( 'You should either update the file with the content below, or supply FTP credentials we would be able to use to save the file for you.' ); ?></p>
			<p><textarea rows="6" class="large-text readonly" onclick="this.focus();this.select()" name="rules" id="rules" readonly="readonly"><?php echo esc_textarea( "# BEGIN WordPress\n" . $wp_rewrite->mod_rewrite_rules() . "# END WordPress\n" ); ?></textarea></p>
			<?php
		}
		submit_button();
		?>
	</form>
</div>

<?php
require ABSPATH . 'wp-admin/admin-footer.php';
