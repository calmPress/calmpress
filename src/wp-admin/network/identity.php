<?php
/**
 * Network Identity Settings Administration Screen.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\admin\network\identity;

/** Load WordPress Administration Bootstrap. */
require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'manage_network_options' ) ) {
	wp_die(
		esc_html__( 'Sorry, you are not allowed to access this page.' ),
		esc_html__( 'Identity Settings' ),
		[ 'response' => 403 ]
	);
}

// Used in the HTML title tag.
$title        = __( 'Identity Settings' );
$parent_file  = 'settings.php';

add_settings_section( 'identity-section', '', '', 'identity' );

add_settings_field(
	'site_name',
	esc_html__( 'Network title' ),
	__NAMESPACE__ . '\\network_title_field',
	'identity',
	'identity-section',
	[ 'label_for' => 'site_name' ]
);

add_settings_field(
	'site_icon',
	esc_html__( 'Site Icon' ),
	__NAMESPACE__ . '\\site_icon_field',
	'identity',
	'identity-section'
);

/**
 * Configures the Network Site Icon uploader for a single file selection.
 *
 * @since 1.0.0
 *
 * @param array $settings Default Plupload settings.
 *
 * @return array Network Site Icon Plupload settings.
 */
function network_media_upload_settings( array $settings ): array {
	$settings['multi_selection'] = false;

	return $settings;
}

/**
 * Marks Network Site Icon uploads as media owned by the network.
 *
 * @since 1.0.0
 *
 * @param array $params Default Plupload multipart parameters.
 *
 * @return array Network Site Icon multipart parameters.
 */
function network_media_upload_params( array $params ): array {
	$params['media_owned_by_network'] = '1';

	return $params;
}

/**
 * Enqueues the Network Identity settings assets.
 *
 * Media configuration and its AJAX URL are generated from the network main
 * site's context so attachments are uploaded to and selected from that site.
 *
 * @since 1.0.0
 */
function enqueue_assets(): void {
	$main_site_id = get_main_site_id();

	// Media Library configuration must use the network main site's uploads and AJAX endpoint.
	switch_to_blog( $main_site_id );

	add_filter( 'plupload_default_settings', __NAMESPACE__ . '\\network_media_upload_settings' );
	add_filter( 'plupload_default_params', __NAMESPACE__ . '\\network_media_upload_params' );

	wp_enqueue_media();

	remove_filter( 'plupload_default_settings', __NAMESPACE__ . '\\network_media_upload_settings' );
	remove_filter( 'plupload_default_params', __NAMESPACE__ . '\\network_media_upload_params' );

	wp_add_inline_style(
		'media-views',
		'.attachment-details [data-setting="alt"],
		.attachment-details [data-setting="title"],
		.attachment-details [data-setting="caption"],
		.attachment-details [data-setting="description"],
		.attachment-details .alt-text + .description,
		.attachment-details .compat-meta,
		.attachment-details .attachment-compat {
			display: none;
		}'
	);

	$media_ajax_url = admin_url( 'admin-ajax.php', 'relative' );

	restore_current_blog();

	wp_enqueue_script( 'site-icon' );
	wp_enqueue_script( 'calm-network-identity' );

	wp_add_inline_script(
		'calm-network-identity',
		'var calm_network_identity = ' . wp_json_encode(
			[
				'ajax_url' => $media_ajax_url,
			]
		) . ';',
		'before'
	);
}

enqueue_assets();

/**
 * Outputs the Network Title setting field.
 *
 * @since 1.0.0
 */
function network_title_field(): void {
	?>
	<input name="site_name" type="text" id="site_name" class="regular-text" value="<?php echo esc_attr( get_network()->site_name ); ?>" />
	<?php
}

/**
 * Outputs the Network Site Icon setting field.
 *
 * Attachment APIs are called from the network main site's context.
 *
 * @since 1.0.0
 */
function site_icon_field(): void {
	$main_site_id = get_main_site_id();

	// The Network Site Icon attachment belongs to the network main site.
	switch_to_blog( $main_site_id );

	$site_icon_id  = (int) get_network_option( 0, 'site_icon', 0 );
	$site_icon_url = ( 0 === $site_icon_id ) ? false : wp_get_attachment_image_url( $site_icon_id, 'full' );

	if ( is_string( $site_icon_url ) ) {
		$site_icon_filename = wp_basename( $site_icon_url );
		$app_icon_alt      = sprintf(
			/* translators: %s: The selected image filename. */
			__( 'App icon preview: Current image: %s' ),
			$site_icon_filename
		);
		$browser_icon_alt  = sprintf(
			/* translators: %s: The selected image filename. */
			__( 'Browser icon preview: Current image: %s' ),
			$site_icon_filename
		);
	} else {
		$site_icon_id     = 0;
		$site_icon_url    = '';
		$app_icon_alt     = '';
		$browser_icon_alt = '';
	}
	?>
	<div class="site-icon-section">
	<style>
	:root {
		--site-icon-url: url( '<?php echo esc_url( $site_icon_url ); ?>' );
	}

	/* The upload-only Site Icon frame must not expose additional Media Library items. */
	.media-modal .load-more {
		display: none;
	}
	</style>

	<div id="site-icon-preview" class="<?php echo esc_attr( 'site-icon-preview settings ' . ( $site_icon_id ? 'has-site-icon' : 'hidden' ) ); ?>">
		<div class="direction-wrap">
			<img id="app-icon-preview" src="<?php echo esc_url( $site_icon_url ); ?>" class="app-icon-preview" alt="<?php echo esc_attr( $app_icon_alt ); ?>" />
			<div class="site-icon-preview-browser">
				<svg role="img" aria-hidden="true" fill="none" xmlns="http://www.w3.org/2000/svg" class="browser-buttons"><path fill-rule="evenodd" clip-rule="evenodd" d="M0 20a6 6 0 1 1 12 0 6 6 0 0 1-12 0Zm18 0a6 6 0 1 1 12 0 6 6 0 0 1-12 0Zm24-6a6 6 0 1 0 0 12 6 6 0 0 0 0-12Z" /></svg>
				<div class="site-icon-preview-tab">
					<img id="browser-icon-preview" src="<?php echo esc_url( $site_icon_url ); ?>" class="browser-icon-preview" alt="<?php echo esc_attr( $browser_icon_alt ); ?>" />
					<div class="site-icon-preview-site-title" id="site-icon-preview-site-title" aria-hidden="true"><?php echo esc_html( get_network()->site_name ); ?></div>
					<svg role="img" aria-hidden="true" fill="none" xmlns="http://www.w3.org/2000/svg" class="close-button">
						<path d="M12 13.0607L15.7123 16.773L16.773 15.7123L13.0607 12L16.773 8.28772L15.7123 7.22706L12 10.9394L8.28771 7.22705L7.22705 8.28771L10.9394 12L7.22706 15.7123L8.28772 16.773L12 13.0607Z" />
					</svg>
				</div>
			</div>
		</div>
	</div>

	<input type="hidden" name="site_icon" id="site_icon_hidden_field" value="<?php echo esc_attr( $site_icon_id ); ?>" />
	<div class="site-icon-action-buttons">
		<button
			type="button"
			id="choose-from-library-button"
			class="<?php echo esc_attr( $site_icon_id ? 'button' : 'upload-button button' ); ?>"
			data-alt-classes="<?php echo esc_attr( $site_icon_id ? 'upload-button button' : 'button' ); ?>"
			data-size="512"
			data-choose-text="<?php esc_attr_e( 'Choose a Site Icon' ); ?>"
			data-update-text="<?php esc_attr_e( 'Change Site Icon' ); ?>"
			data-update="<?php esc_attr_e( 'Set as Site Icon' ); ?>"
			data-state="<?php echo esc_attr( (bool) $site_icon_id ); ?>"
			data-upload-only="true"
		>
			<?php if ( $site_icon_id ) { ?>
				<?php esc_html_e( 'Change Site Icon' ); ?>
			<?php } else { ?>
				<?php esc_html_e( 'Choose a Site Icon' ); ?>
			<?php } ?>
		</button>
		<button
			id="js-remove-site-icon"
			type="button"
			class="<?php echo esc_attr( 'button button-secondary reset remove-site-icon' . ( $site_icon_id ? '' : ' hidden' ) ); ?>"
		>
			<?php esc_html_e( 'Remove Network Site Icon' ); ?>
		</button>
	</div>
	<p class="description<?php echo ( $site_icon_id ) ? '' : ' hidden'; ?>" id="network-site-icon-removal-warning">
		<?php esc_html_e( 'The image will be permanently deleted after the Network Site Icon is removed.' ); ?>
	</p>

	<p class="description">
		<?php esc_html_e( 'Sites that do not configure their own Site Icon will use this icon. For best results across different uses, choose a square image. The recommended size is 512 by 512 pixels.' ); ?>
	</p>
	</div>
	<?php
	restore_current_blog();
}

require_once ABSPATH . 'wp-admin/admin-header.php';
?>

<div class="wrap">
	<?php settings_errors(); ?>
	<h1><?php esc_html_e( 'Identity Settings' ); ?></h1>
	<form method="post" action="options.php" novalidate="novalidate">
		<?php
		settings_fields( 'identity' );
		do_settings_sections( 'identity' );
		submit_button();
		?>
	</form>
</div>

<?php require_once ABSPATH . 'wp-admin/admin-footer.php'; ?>
