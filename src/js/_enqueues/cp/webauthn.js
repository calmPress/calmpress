/**
 * @output wp-admin/js/cp-webauthn.js
 */

/* global webauthnL10n */

/**
 * Convert a string which is assumed to be base64url encoded to its original
 * an Uint8Array representation.
 * 
 * @param {string} base64url A string assumed to be a base64url encoded string.
 * 
 * @returns Uint8Array
 */
function base64urlToUint8Array( base64url ) {
	const padding = '='.repeat( ( 4 - base64url.length % 4 ) % 4 );
	const base64 = ( base64url + padding )
		.replace( /-/g, '+' )
		.replace( /_/g, '/' );
	const rawData = window.atob( base64 );
	return Uint8Array.from( [...rawData].map( c => c.charCodeAt( 0 ) ) );
}

/**
 * If the device have authenticator, and devices can be added at the server
 * enable the register device input.
 */
function maybe_enable_webauthn_register_device() {
	// enable the register webauthn button if webauthn is supported.
	if ( webauthn_can_add_device && window.PublicKeyCredential ) {
		PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()
			.then( ( available ) => {
				if ( available ) {
					cp_$( '#register_device_webauthn' ).show();
					cp_$( '#device_do_not_support_webauthn' ).hide();
				} 
			})
	}
}

/**
 * A DRY to display an error which was thrown by calm_fetch.post in a specific div.
 * 
 * @param {Error} error The exception including error status and message.
 * @param {string} id   The id of the div in which to display the error.
 */
function display_error( error, id ) {
	if ( error instanceof calm_fetch_error ) {
		inline_notice_manager.show( id, 'error', error.cause_message() );
	} else {
		inline_notice_manager.show( id, 'error', calmUtilsL10n.error_unexpected );
		console.log( error );
	}
}

document.addEventListener( 'DOMContentLoaded', () => {

	cp_$( '#register_button' ).disable();

	maybe_enable_webauthn_register_device();

	/**
	 * An handler for enabling and disabeling the register new device button based
	 * on whether there is non empty string at the device name input.
	 */
	cp_$( '#new_webautn_device_name' ).on( 'input', function () {
		val = cp_$( this ).value();
		if ( val.trim() != '' ) {
			cp_$( '#register_button' ).enable();
		} else {
			cp_$( '#register_button' ).disable();
		}
	});

	/**
	 * An handler for starting registretion of new device triggered by enter on the input.
	 *
	 * @param {object} event The event
	 */
	cp_$( '#new_webautn_device_name' ).on( 'keypress', function ( event ) {
		if ( event.key === 'Enter' ) {
			val = event.target.value;
			if ( val.trim() != '' ) {
				cp_$('#register_button').trigger( 'click' );
			}
		}
	});

	/**
	 * Use a template to insert a new device row into the table.
	 * 
	 * @param {string} row  The row number
	 * @param {string} cred The credential id of the device.
	 * @param {string} description The description of the device.
	 * @param {string} last_used   The 5textual description when the device was last used.
	 */
	function webauthn_add_row( row, cred, description, last_used ) {
		const tmpl = cp_$( '#webauthn_row_template' ).el;
		const clone = tmpl.content.cloneNode( true );

		// Process all elements in the clone to replace place holder with their actual values
		clone.querySelectorAll('*').forEach( el => {
			// replace place holders in attributes.
			Array.from( el.attributes ).forEach( attr => {
				if ( attr.value.includes( '__ROW__' ) ) {
					el.setAttribute( attr.name, attr.value.replace(/__ROW__/g, row ) );
				}
				if ( attr.value.includes( '__CRED__' ) ) {
					el.setAttribute( attr.name, attr.value.replace(/__CRED__/g, cred ) );
				}
			});

			// Replace placeholders in text.
			el.childNodes.forEach( node => {
      			if ( node.nodeType === Node.TEXT_NODE ) {
					if ( node.nodeValue.includes( '__DESC__' ) ) {
						node.nodeValue = node.nodeValue.replace(/__DESC__/g, description);
					}
					if ( node.nodeValue.includes( '__LAST_USED__' ) ) {
						node.nodeValue = node.nodeValue.replace(/__LAST_USED__/g, last_used);
					}
				}
			} );
		} );

		cp_$( '#devices-grid tbody' ).el.appendChild( clone );
	}

	cp_$( '#register_button' )
		/**
		 * Request an registeration challenge webauthn authentication.
		 *
		 * @param {object} event The event
		 */
		.on( 'click', async function ( event ) {

			try {
				// Send the request challenge request.
				const response = await calm_fetch.post( 'calmpress/webauthn/create_challenge', {} );

				const options = { 'publicKey' : response };

				// Convert base64url → ArrayBuffer for required fields
				options.publicKey.challenge = base64urlToUint8Array( options.publicKey.challenge );
				options.publicKey.user.id = base64urlToUint8Array( options.publicKey.user.id );
				if ( options.publicKey.excludeCredentials ) {
					options.publicKey.excludeCredentials = options.publicKey.excludeCredentials.map( cred => ( {
						...cred,
						id: base64urlToUint8Array( cred.id ),
					} ) );
				}

				// Ask browser to create new credential
				const credential = await navigator.credentials.create( options );

				const payload = {
					id: credential.id,
					rawId: btoa( String.fromCharCode( ...new Uint8Array( credential.rawId ) ) ),
					type: credential.type,
					response: {
						attestationObject: btoa( String.fromCharCode( ...new Uint8Array( credential.response.attestationObject ) ) ),
						clientDataJSON: btoa( String.fromCharCode( ...new Uint8Array( credential.response.clientDataJSON) ) ),
					},
				};

				const data = {
					'name'      : cp_$( '#new_webautn_device_name' ).value(),
					'payload'   : payload,
				}

				// Send request to verify & store the credential
				const register = await calm_fetch.post( 'calmpress/webauthn/register_device', data );
				cp_$( '#new_webautn_device_name' ).setValue( '' );
				cp_$( '#register_button' ).disable();
				inline_notice_manager.show( 'webauthn_register_device_message', 'success', register.message );
				if ( ! register.can_add ) {
					cp_$( '#register_device_webauthn' ).hide();
				}
				row = cp_$$( '#devices-grid tbody tr' ).length + 1;
				webauthn_add_row( row, register.cred, register.description, register.last_used );
				cp_$( '#devices-grid' ).show();
				cp_$( '#no_devices_message' ).hide();
			} catch ( error ) {
				if ( error instanceof calm_fetch_error ) {
					display_error( error, 'webauthn_register_device_message' );
				} else {
					switch ( error.name ) {
						case 'NotAllowedError':
							; // User canceled no need to do anything.
							break;
						default:
							console.log( error );			
					}
				}
			}
		} );

	/**
	 * Handler for revoke of device authentication.
	 *
	 * @param {Event} event The event
	 */
	cp_$( '#devices-grid' ).on( 'click', '.actions .revoke', async function ( event ) {

		const cred = cp_$( this ).parent().parent().data( 'cred' );
		const $row = cp_$( this ).closest('tr');

		var	data = {
			'credential_id' : cred
		};

		// Send the revoke request.
		try {
			const response = await calm_fetch.post( 'calmpress/webauthn/revoke', data );

			inline_notice_manager.show( 'webauthn_devices_table_message', 'success', response.message );
			webauthn_can_add_device = response.can_add;
			maybe_enable_webauthn_register_device();
			$row.addClass( 'fade-out' );
			$row.on( 'transitionend', () => {
				$row.el.remove();
				const $tbody = cp_$( '#devices-grid tbody' );
				if ( $tbody.find( 'tr' ) === null ) {
					cp_$( '#no_devices_message' ).show();
					cp_$( '#devices-grid' ).hide();
				}
			});
			$row.el.style.opacity = 0; // trigger the fadeout.
		} catch ( error ) {
			display_error( error, 'webauthn_devices_table_message' );
		}
	});

	/**
	 * Handler for starting editing the device description.
	 *
	 * @param {object} event The event
	 */
	cp_$( '#devices-grid' ).on( 'click', '.actions .edit', function ( event ) {
	
		const $row = cp_$( this ).closest( 'tr' );
		const $box = $row.find( 'div' );
		const $input = $box.find( 'input' );

		// Set the text in the input to current description.
		const text = $box.parent().find( 'span' ).el.textContent;
		$input.setValue( text );

		$box.show();
		event.target.setAttribute( 'aria-expanded', 'true' );
		$input.trigger( 'focus' );
	});

	/**
	 * An handler for canceling edit of a device description.
	 *
	 * @param {object} event The event
	 */
	cp_$( '#devices-grid' ).on( 'click', '.close_change', function ( event ) {
	
		cp_$( this ).parent().parent().hide();

		const $row = cp_$( this ).closest( 'tr' );
		const $edit = $row.find( '.edit' );

		$edit.setAttribute( 'aria-expanded', 'false' );
	});

	/** 
	 * A DRY for handling the submittion of the updated description to the server.
	 * 
	 * @param {HTMLElement} element The element on which the update was triggered either
	 *                         the update button or the input.
	 */
	async function update_description( element ) {
		const $row   = cp_$( element ).closest( 'tr' );
		const cred   = $row.data( 'cred' );
		const $box   = $row.find( '.edit_form' );
		const $input = $box.find( 'input' );

		const data = {
			'credential_id' : cred,
			'description'   : $input.value(),
		};

		// Send the update request.
		try {
			const response = await calm_fetch.post( 'calmpress/webauthn/set_description', data );

			inline_notice_manager.show( 'webauthn_devices_table_message', 'success', response.message );
			$box.hide(); // Hide edit box

			// Indicate on edit button box is closed.
			const $edit = $row.find( '.edit' );
			$edit.setAttribute( 'aria-expanded', 'false' );

			// Update description.
			$box.parent().find( 'span' ).el.textContent = response.description;
		} catch ( error ) {
			display_error( error, 'webauthn_devices_table_message' );
		}
	}

	/**
	 * Handler for sending updated device description to the server.
	 *
	 * @param {object} event The event
	 */
	cp_$( '#devices-grid' ).on( 'click', '.update_description', function ( event ) {
		update_description( event.target );
	});

	/**
	 * Handler for sending updated device description to the server.
	 *
	 * @param {Event} event The event
	 */
	cp_$( '#devices-grid' ).on( 'keypress', 'input', function ( event ) {

		if ( event.key === 'Enter' ) {
			val = cp_$( this ).value();
			if ( val.trim() != '' ) {
				update_description( event.target );
			}
		}
	});

	/**
	 * Handler to disable update button if no description text given.
	 *
	 * @param {object} event The event
	 */
	cp_$( '#devices-grid' ).on( 'input', 'input', function ( event ) {

		val = cp_$( this ).value();
		const $row = cp_$( this ).closest( 'tr' );
		const $but = $row.find( '.update_description' );
		if ( val.trim() != '' ) {
			$but.enable();
		} else {
			$but.disable();
		}
	});

});
