<?php
/**
 * robots.txt Settings Administration Screen.
 *
 * @package calmPress
 */

declare(strict_types=1);

namespace calmpress\admin\robots_txt;

/** WordPress Administration Bootstrap */
require_once dirname( __FILE__ ) . '/admin.php';

if ( ! is_super_admin() ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to manage robots.txt for this site.' ) );
}

$title       = __( 'robots.txt Settings' );
$parent_file = 'options-general.php';

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' =>
			'<p>' . esc_html__( 'The robots.txt file contains crawling instructions for search engine, and points to relevant site maps.' ) . '</p>' .
			'<p>' . esc_html__( 'This screen allows you to control its content.' ) . '</p>',
	)
);

add_settings_section(
	'calm-robots-txt-section',
	'',
	'',
	'robots_txt'
);

add_settings_field(
	'calm-robots-txt-section-content',
	__( 'Content' ),
	__NAMESPACE__ . '\content_input',
	'robots_txt',
	'calm-robots-txt-section',
	[ 'label_for' => 'calm-robots-txt-section-content' ]
);

/**
 * Output the textarea in which the user rules can be edited.
 *
 * @since 1.0.0
 */
function content_input() {
	?>
	<textarea class="large-text" name="robots_txt" id="robots_txt" rows="6"><?php echo esc_textarea( get_option( 'robots_txt' ) ); ?></textarea>
	<p class="description">
		<?php
		esc_html_e(
			'This content will be used when the robots.txt URL in your domain\'s root directory will be accessed. 
It should follow the robots.txt standard to prevent and allow search engine to crawl parts of you site
and to add sitemaps in addition to the one appended automatically by the system.'
		);
		?>
	</p>
	<?php
}

// Show a notice if site is on subdirectory.
if ( '/' !== wp_parse_url( site_url(), PHP_URL_PATH ) ) {
	add_action(
		'admin_notices',
		function () {
			$error = error_get_last();
			?>
			<div class="notice notice-info">
				<p>
				<?php esc_html_e( 'The site is installed in a subdirectory therefor search engine are not going to read the file' ); ?>
				</p>
			</div>
			<?php
		},
		9,
		1
	);
}

require ABSPATH . 'wp-admin/admin-header.php';

?>
<div class="wrap">
	<h1><?php echo esc_html( $title ); ?></h1>

	<form method="post" action="options.php" novalidate="novalidate">
		<?php
		settings_fields( 'robots_txt' );
		do_settings_sections( 'robots_txt' );
		submit_button();
		?>
	</form>
</div>

<?php
require ABSPATH . 'wp-admin/admin-footer.php';
