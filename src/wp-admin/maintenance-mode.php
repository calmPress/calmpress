<?php
/**
 * Maintenance mode tools Screen.
 *
 * @package calmPress
 */

declare(strict_types=1);

namespace calmpress\calmpress;

/** WordPress Administration Bootstrap */
require_once dirname( __FILE__ ) . '/admin.php';

if ( ! current_user_can( 'maintenance_mode' ) ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to manage maintenance mode for this site.' ) );
}

wp_enqueue_style( 'maintenance-mode' );

$title       = __( 'Maintenance Mode' );
$parent_file = 'tools.php';

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' =>
			'<p>' . esc_html__( 'The maintenance mode enable you to softly disable the site while showing some explenation to users.' ) . '</p>' .
			'<p>' . esc_html__( 'This screen allows you to control its activation and the content displayed on it.' ) . '</p>',
	)
);

require ABSPATH . 'wp-admin/admin-header.php';

?>
<div class="wrap metabox-holder">
	<h1><?php echo esc_html( $title ); ?></h1>

	<div id="status" class="postbox">
		<h3 class="hndle"><?php esc_html_e( 'Status' ); ?></h3>
		<div class="inside">
			<form action="admin-post.php" method="post">
			<input name='action' type="hidden" value='maintenance_mode_status'>
			<?php
			wp_nonce_field( 'maintenance_mode_status' );
			if ( ! Maintenance_Mode::is_active() ) {
				echo '<p class="status_line inactive">' . esc_html__( 'Not in maintenance mode' ) . '</p>';
				echo '<p>';
				esc_html_e( 'If activated, it is expected to last for:' );
				?>
				<br>
				<input class="hours" type="number" min="0" max="999" name="hours" value="0"> :
				<input class="minutes" type="number" min="0" max="59" name="minutes" value="0">
				<?php esc_html_e( 'Hours' ); ?>
				<?php
				echo '</p>';
				echo '<p class="description>' . esc_html__( 'If no value is given, a 30 minutes value will be assumed. If maintenance mode is still active after this time it will be prolonged everytime by 30 minutes untile it is exited.' ) . '</p>';
				submit_button( __( 'Activate maintenance mode' ), 'primary', 'enter' );
			} else {
				echo '<p class="status_line active">' . esc_html__( 'In maintenance mode' ) . '</p>';
				$lasts_for = Maintenance_Mode::projected_time_till_end();

				$hours   = '0';
				$minutes = '0';
				if ( $lasts_for <= 10 * MINUTE_IN_SECONDS ) {
					echo '<p>' . esc_html__( 'Seems like the initialy configured time had passed, try to estimate again.' ) . '<p>';
				} else {
					echo '<p>' . esc_html__( 'Configured to last for another :' );
					$hours   = intdiv( $lasts_for, 60 * MINUTE_IN_SECONDS );
					$minutes = intdiv( $lasts_for % ( 60 * MINUTE_IN_SECONDS ), 60);
				}
				?>
				<br>
				<input class="hours" type="number" min="0" max="999" name="hours" value="<?php echo esc_attr( $hours ); ?>"> :
				<input class="minutes" type="number" min="0" max="59" name="minutes" value="<?php echo esc_attr( $minutes ); ?>">
				<?php esc_html_e( 'Hours' ); ?>
				<?php
				echo '</p>';
				echo '<p>';
				echo get_submit_button( esc_html__( 'Exit maintenance mode' ), 'primary', 'exit', false );
				echo get_submit_button( esc_html__( 'Change remaining time' ), 'large', 'change_time', false );
				echo '</p>';
			}
			?>
			</form>
		</div>
	</div>
	<div id="message" class="postbox">
		<h3 class="hndle"><?php esc_html_e( 'Page content' ); ?></h3>
		<div class="inside">
			<p><a href="<?php echo esc_url( admin_url( 'maintenance-mode.php?preview=1' ) ); ?>"><?php esc_html_e( 'Preview page' ); ?></a></p>
			<form action="admin-post.php" method="post">
				<input name='action' type="hidden" value='maintenance_mode_content'>
				<?php wp_nonce_field( 'maintenance_mode_content' );	?>
				<table class="form-table">
					<tr><th><label for="page_title"><?php esc_html_e( 'Page title' ); ?></label></th><td><input id="page_title" name="page_title"></td></tr>
					<tr><th><label for="text_title"><?php esc_html_e( 'Text title' ); ?></label></th><td><input id="text_title" name="text_title"></td></tr>
					<tr><th><label for="theme_page"><?php esc_html_e( 'Use normal header and footer' ); ?></label></th><td><input id="theme_page" name="theme_page" type="checkbox"></td></tr>
					<tr><th><label><?php esc_html_e( 'Message text' ); ?></label></th></tr>
					<tr><td colspan="2">
						<?php
						$content   = '';
						$editor_id = 'message_text';
						$settings  = [
							'media_buttons' => true,
							'wpautop'       => false,
							'quicktags'     => false,
							'textarea_rows' => 5,
						];
						wp_editor( $content, $editor_id, $settings );
						?>
						<p class="description">
							<?php
							printf(
								/* translators: 1: shortcode */
								esc_html__( 'Use the %1$s shortcode to insert the approximate time left' ),
								'<code>[maintenance_left]</code>'
							);
							?>
						</p>
					</td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
	</div>
</div>

<?php
require ABSPATH . 'wp-admin/admin-footer.php';
