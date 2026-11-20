/**
 * Code that handles the combined FTP and SFTP connection-settings form.
 *
 * @since 1.0.0
 */

( function () {
	'use strict';

	/**
	 * Display the selected protocol's fields and hide the other protocol's fields.
	 *
	 * @since 1.0.0
	 *
	 * @param {HTMLFormElement} form Combined credentials form.
	 */
	function display_selected_protocol_form( form ) {
		const select = form.elements.calm_credentials_protocol;
		form.querySelectorAll( '[data-credentials-protocol]' ).forEach( function ( section ) {
			const active = select.value === section.dataset.credentialsProtocol;
			section.hidden = ! active;
		} );
	}

	// For each combined credentials form, remember the protocol selection and
	// display its fields initially and whenever the selection changes.
	document.querySelectorAll( 'form[data-credentials-type="ftp-sftp"]' ).forEach( function ( form ) {
		// Name attribute of the protocol selector whose value is saved to localStorage and used for prefilling.
		new Credentials_Storage( form, 'selection', [ 'calm_credentials_protocol' ] );
		const validated_form = Generic_Validated_Form.for_form( form );

		form.elements.calm_credentials_protocol.addEventListener( 'change', function () {
			display_selected_protocol_form( form );
			validated_form.refresh();
		} );
		display_selected_protocol_form( form );
		validated_form.refresh();
	} );
} )();
