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
<?php settings_errors(); ?>
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
				<input class="minutes" type="number" min="0" max="59" name="minutes" value="00">
				<?php esc_html_e( 'Hours' ); ?>
				<?php
				echo '</p>';
				echo '<p class="description">' . esc_html__( 'If no value is given, a 10 minutes value will be assumed. If maintenance mode is still active after the expected time it will be prolonged by 10 minutes untile it is deactivated.' ) . '</p>';
				echo '<p><b>' . esc_html__( 'Before activating maintenance mode you might want to consider changing the setting of your analytics or any other plugin that assume "live" content as plugins keep operating fully.' ) . '</b></p>';
				submit_button( __( 'Activate maintenance mode' ), 'primary', 'enter' );
			} else {
				echo '<p class="status_line active">' . esc_html__( 'In maintenance mode' ) . '</p>';
				$bypass_url = site_url() . '?' . Maintenance_Mode::BYPASS_NAME . '=' . Maintenance_Mode::bypass_code();
				?>
				<p>
					<?php
					printf(
						/* translators: 1: br, 2: Bypass URL */
						esc_html__( 'Bypass maintenance mode with the fillowing URL:%1$s%2$s' ),
						'<br>',
						'<code>' . esc_html( $bypass_url ) . '</code>'
					);
					?>
					<br />
					<span class="description">
					<?php
					esc_html_e( 'Once the URL is used, a cookie is set and the user will automatically bypass the maintenance mode for the rest of the session. The URL changes with every maintenance mode activation.' );
					?>
					</span>
				</p>
				<?php
				$lasts_for = Maintenance_Mode::projected_time_till_end();

				$hours   = '0';
				$minutes = '00';
				if ( $lasts_for <= 10 * MINUTE_IN_SECONDS ) {
					echo '<p>' . esc_html__( 'Seems like the initialy configured time had passed, try to estimate again.' ) . '<p>';
				}
				echo '<p>' . esc_html__( 'Configured to last for another :' );
				$hours   = intdiv( $lasts_for, 60 * MINUTE_IN_SECONDS );
				$minutes = sprintf( '%02d', intdiv( $lasts_for % ( 60 * MINUTE_IN_SECONDS ), 60 ) );
				?>
				<br>
				<input class="hours" type="number" min="0" max="999" name="hours" value="<?php echo esc_attr( $hours ); ?>"> :
				<input class="minutes" type="number" min="0" max="59" name="minutes" value="<?php echo esc_attr( $minutes ); ?>">
				<?php esc_html_e( 'Hours' ); ?>
				<?php
				echo '</p>';
				echo '<p>';
				echo '<p><b>' . esc_html__( 'Before deactivating maintenance mode you might want to change back the settings that were changed before activation.' ) . '</b><p>';
				echo get_submit_button( esc_html__( 'Deactivate maintenance mode' ), 'primary', 'exit', false );
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
			<p><a target="_blank" href="<?php echo esc_url( wp_nonce_url( site_url(), Maintenance_Mode::PREVIEW_PARAM, Maintenance_Mode::PREVIEW_PARAM ) ); ?>"><?php esc_html_e( 'Preview page' ); ?></a></p>
			<form action="admin-post.php" method="post">
				<input name='action' type="hidden" value='maintenance_mode_content'>
				<?php wp_nonce_field( 'maintenance_mode_content' ); ?>
				<table class="form-table">
					<tr><th><label for="page_title"><?php esc_html_e( 'Page title' ); ?></label></th><td><input id="page_title" name="page_title" value="<?php echo esc_attr( Maintenance_Mode::page_title() ); ?>"></td></tr>
					<tr><th><label for="text_title"><?php esc_html_e( 'Text title' ); ?></label></th><td><input id="text_title" name="text_title" value="<?php echo esc_attr( Maintenance_Mode::text_title() ); ?>"></td></tr>
					<tr><th><label for="theme_page"><?php esc_html_e( 'Use normal header and footer' ); ?></label></th><td><input id="theme_page" name="theme_page" type="checkbox" <?php checked( Maintenance_Mode::theme_frame_used() ); ?>></td></tr>
					<tr><th><label><?php esc_html_e( 'Message text' ); ?></label></th></tr>
					<tr><td colspan="2">
						<?php
						$content   = Maintenance_Mode::content();
						$editor_id = 'message_text';
						$settings  = [
							'media_buttons' => true,
							'wpautop'       => true,
							'quicktags'     => true,
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
							<br/>
							<?php
								esc_html_e( 'Other shortcodes can be used as well but you will need to check if they work as expected.' );
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
