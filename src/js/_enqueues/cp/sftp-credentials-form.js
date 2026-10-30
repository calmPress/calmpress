/**
 * Switches between SFTP authentication fields in credentials forms.
 *
 * @since 1.0.0
 */

( function () {
	'use strict';

	/**
	 * Shows the fields for the selected authentication method.
	 *
	 * @since 1.0.0
	 *
	 * @param {HTMLElement} section SFTP credentials section.
	 */
	function update_sftp_authentication( section ) {
		const select = section.querySelector( '[name="calm_sftp_auth_method"]' );
		section.querySelectorAll( '[data-sftp-auth-method]' ).forEach( function ( row ) {
			const active = row.dataset.sftpAuthMethod.split( ' ' ).includes( select.value );
			row.hidden = ! active;
			row.querySelectorAll( 'input, textarea' ).forEach( function ( field ) {
				if ( field.dataset.sftpRequired === 'true' ) {
					field.required = active;
				}
			} );
		} );
	}

	document.querySelectorAll( 'form.calm-credentials-form' ).forEach( function ( form ) {
		const section = form.querySelector( '[data-credentials-protocol="sftp"]' ) || form.querySelector( '.calm-sftp-credentials' );
		if ( ! section ) {
			return;
		}

		section.querySelector( '[name="calm_sftp_auth_method"]' ).addEventListener( 'change', function () {
			update_sftp_authentication( section );
			window.calmpress_form_validation.refresh( form );
		} );
		update_sftp_authentication( section );
		window.calmpress_form_validation.refresh( form );
	} );
} )();
