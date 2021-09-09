<?php
/**
 * robots.txt Settings Administration Screen.
 *
 * @package calmPress
 */

declare(strict_types=1);

namespace calmpress\object_caching\APCu_Controller;

/** WordPress Administration Bootstrap */
require_once dirname( __FILE__ ) . '/admin.php';

if ( ! is_super_admin() ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to manage robots.txt for this site.' ) );
}

$title       = __( 'Object Caching Settings' );
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

require ABSPATH . 'wp-admin/admin-header.php';

?>
<div class="wrap">
	<h1><?php echo esc_html( $title ); ?></h1>

	<form method="post" action="options.php" novalidate="novalidate">
		<?php
		settings_fields( 'object_caching' );
		do_settings_sections( 'object_caching' );
		submit_button();
		?>
	</form>
</div>

<?php
require ABSPATH . 'wp-admin/admin-footer.php';
