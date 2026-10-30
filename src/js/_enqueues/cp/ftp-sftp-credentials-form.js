/**
 * Switches between FTP and SFTP fields in the combined credentials form.
 *
 * @since 1.0.0
 */

( function () {
	'use strict';

	/**
	 * Shows the fields for the selected protocol.
	 *
	 * @since 1.0.0
	 *
	 * @param {HTMLFormElement} form Combined credentials form.
	 */
	function update_protocol( form ) {
		const select = form.elements.calm_credentials_protocol;
		form.querySelectorAll( '[data-credentials-protocol]' ).forEach( function ( section ) {
			const active = select.value === section.dataset.credentialsProtocol;
			section.hidden = ! active;
		} );
	}

	document.querySelectorAll( 'form.calm-credentials-form' ).forEach( function ( form ) {
		form.elements.calm_credentials_protocol.addEventListener( 'change', function () {
			update_protocol( form );
			window.calmpress_form_validation.refresh( form );
		} );
		update_protocol( form );
		window.calmpress_form_validation.refresh( form );
	} );
} )();
