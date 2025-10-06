<?php
/**
 * Edit user administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

use function calmpress\utils\base64URL_encode;

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

$current_user = wp_get_current_user();
$user_id      = $current_user->ID;

wp_enqueue_script( 'calm-webauthn' );
wp_enqueue_style( 'calm-webauthn' );


$title = __( 'My Login Devices' );

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap" id="webauthn-page">
<h1 class="wp-heading-inline">
		<?php
		echo esc_html( $title );
		?>
</h1>

<hr class="wp-header-end">

<h2><?php esc_html_e( 'Devices that can be used for Login' ); ?></h2>
<p>
	<?php esc_html_e( 'These are the devices you can use to login to your account on the device where they were set up. On most devices, this means using built-in authenticators such as biometrics or a PIN, or external security keys.' ); ?>
	<span id="device_do_not_support_webauthn">
		<?php esc_html_e( 'Your current browser or device do not support this kind of login.' );?>
	</span>
</p>
<p><?php esc_html_e( 'Any changes you make here are saved automatically.' ); ?></p>

<?php // the section is enabled in JS ?>
<?php \calmpress\utils\html_for_dissmissable_notice( 'webauthn_register_device_message' );?>
<table id="register_device_webauthn" class="form-table" role="presentation" style="display:none">
	<tr>
		<th>
			<label for="new_webautn_device_name">
				<?php esc_html_e( 'Add This Device' );?>
			</label>
		</th>
		<td>
			<input class="regular-text" id="new_webautn_device_name" name="new_webautn_device_name" value="" autocomplete="off" />
			<p>
				<button id="register_button" class="button"  type="button" disabled="disabled">
					<?php esc_html_e( 'Add Device' )?>
				</button>
			</p>
			<p id="webauthn_new_device_button_desc" class="description"><?php esc_html_e( 'This text identifies the device in your account (for example, Office Laptop). It must be unique and not empty.' ); ?></p>
		</td>
	</tr>
</table>

<?php \calmpress\utils\html_for_dissmissable_notice( 'webauthn_devices_table_message' );?>
<table id="webauthn_devices_table" class="form-table" role="presentation">
	<tr>
		<th>
			<?php esc_html_e( 'Devices' ); ?>
		</th>
		<td>
<?php
	// Initialize the JS variable indicating if devices can be added.
	add_action(
		'admin_footer',
		function () use ( $current_user ) {
			?>
<script>
var webauthn_can_add_device = <?php echo $current_user->webauthn_registered_devices()->can_add_device() ? 'true' : 'false'; ?>;
</script>
			<?php
		}
	);
	$devices = $current_user->webauthn_registered_devices()->devices();
	$style = count( $devices ) === 0 ? ' style="display:none"' : '';
	$nonestyle = count( $devices ) !== 0 ? ' style="display:none"' : '';

	echo '<div id="no_devices_message"' . $nonestyle . '>' . esc_html__( 'None' ) . '</div>' . "\n";
	echo '<table id="devices-grid" class="widefat striped"' . $style . '>' . "\n";
		echo '<thead><tr>' . "\n";
			echo '<th class="description">' . esc_html__( 'Description' ) . '</th>' . "\n";
			echo '<th class="last-used">' . esc_html__( 'Last Used' ) . '</th>' . "\n";
			echo '<th class="actions">' . esc_html__( 'Actions' ) . '</th>' . "\n";
		echo '</tr></thead>' . "\n";
		echo '<tbody>';
		$row=0;
		$row_template =
			'<tr data-cred="__CRED__">'
				. '<td class="description">'
					. '<span id="webauthn_description_text__ROW__">'
						. '__DESC__'
					. '</span>'
					. '<div class="edit_form" id="webauthn_device_change__ROW__">'
						. '<label for="webauth_description__ROW__">'
							. esc_html__( 'New Description' )
						. '</label>'
						. '<input id="webauth_description__ROW__" type="text">'
						. '<div>'
							. '<button class="button update_description" type="button">'
								. esc_html__( 'Update' )
							. '</button>'
							. '<button class="button close_change" type="button">'
								. esc_html__( 'Cancel' )
							. '</button>'
						. '</div>'
					. '</div>'
				. '</td>'
				. '<td class="last-used">'
					. '__LAST_USED__'
				. '</td>'
				. '<td class="actions">'
					. '<button class="button edit" type="button" aria-expanded="false" aria-controls="webauthn_device_change___ROW__">'
						. esc_html__( 'Edit' )
					. '</button>'
					. '<button class="button revoke" type="button" aria-describedby="webauthn_description_text___ROW__">'
						. esc_html__( 'Revoke' )
					. '</button>'
				. '</td>'
			. '</tr>';
		foreach ( $devices as $device ) {
			$row++;
			$cred = esc_attr( base64URL_encode( $device->credential_id ) );
			$row_html = str_replace( '__ROW__', $row, $row_template );
			$row_html = str_replace( '__CRED__', $cred, $row_html );
			$row_html = str_replace( '__DESC__', esc_html( $device->description() ), $row_html );
			$row_html = str_replace( '__LAST_USED__', esc_html( $device->human_last_used() ), $row_html );
			echo $row_html;
		}
	echo '</tbody>';
	echo '</table>' . "\n";
	echo '<template id="webauthn_row_template">' . $row_template . '</template>';
?>
		</td>
	</tr>
</table>

</div>
<?php

require_once ABSPATH . 'wp-admin/admin-footer.php';
