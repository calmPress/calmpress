/**
 * @output wp-admin/js/cp-application-password.js
 */

/* @global application_passwordsL10n translation strings. */

document.addEventListener( 'DOMContentLoaded', () => {

	var can_add = null;

	/**
	 * Enable and disable UI elements based on if user can add passwords.
	 * 
	 * Serves also as general refresh of UI state after adding a password.
	 * 
	 * @param {bool} state The state to switch to, if true user can add passwords, if false it can not.
	 */
	function set_can_add_state( state ) {
		if ( state ) {
			cp_$( '#new_application_password_name' ).el.focus();
			cp_$( '#max_passwords_reached' ).hide();
			cp_$( '#new_application_password_name ').enable();
			if ( can_add === false ) {
				// An actuall state change, announce that passwords can be added.
				wp.a11y.speak( application_passwordsL10n.can_add );
			}
		} else {
			cp_$( '#max_passwords_reached' ).show();
			cp_$( '#register_button').disable();
			cp_$( '#new_application_password_name ').disable();
		}
		can_add = state;
	}

	/**
	 * Hide all of the notices.
	 */
	function remove_application_passwords_notices() {
		cp_$$( '.notice' ).forEach( (el) => {
			el.hide();
		})
	}

	/**
	 * A DRY to display an error which was thrown by calm_fetch.post in a specific div.
	 * 
	 * @param {Error} error The exception including error status and message.
	 * @param {string} id   The id of the div in which to display the error.
	 */
	function display_error( error, id ) {
		if ( error instanceof calm_fetch_error ) {
			// Special handling to wp_error results, they are sent with a 500 code but contain
			// the message text in the data.
			if ( error.status == 500 && error.data.message ) {
				inline_notice_manager.show( id, 'error', error.data.message );
			} else {
				inline_notice_manager.show( id, 'error', error.cause_message() );
			}
		} else {
			inline_notice_manager.show( id, 'error', calmUtilsL10n.error_unexpected );
			console.log( error );
		}
	}

	/**
	 * An handler for enabling and disabeling the register new device button based
	 * on whether there is non empty string at the device name input.
	 */
	cp_$( '#new_application_password_name' ).on( 'input', function () {
		val = cp_$( this ).getValue();
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
	cp_$( '#new_application_password_name' ).on( 'keypress', function ( event ) {
		if ( event.key === 'Enter' ) {
			val = event.target.value;
			if ( val.trim() != '' ) {
				cp_$('#register_button').trigger( 'click' );
			}
		}
	});

	/**
	 * Use a template to insert a new password row into the table.
	 * 
	 * @param {string} row         The row number
	 * @param {string} uuid        The uuid of the password.
	 * @param {string} description The description of the device.
	 * @param {string} created     The date when the password was create.
	 * @param {string} last_used   The date when the password was last used.
	 * @param {string} last_ip     The IP address from which the password was last used.
	 */
	function passwords_add_row( row, uuid, description, created, last_used, last_ip ) {
		const tmpl = cp_$( '#password_row_template' ).el;
		const clone = tmpl.content.cloneNode( true );

		// Process all elements in the clone to replace place holder with their actual values
		clone.querySelectorAll('*').forEach( el => {
			// replace place holders in attributes.
			Array.from( el.attributes ).forEach( attr => {
				if ( attr.value.includes( '__ROW__' ) ) {
					el.setAttribute( attr.name, attr.value.replace(/__ROW__/g, row ) );
				}
				if ( attr.value.includes( '__UUID__' ) ) {
					el.setAttribute( attr.name, attr.value.replace(/__UUID__/g, uuid ) );
				}
			});

			// Replace placeholders in text.
			el.childNodes.forEach( node => {
      			if ( node.nodeType === Node.TEXT_NODE ) {
					if ( node.nodeValue.includes( '__DESC__' ) ) {
						node.nodeValue = node.nodeValue.replace( /__DESC__/g, description );
					}
					if ( node.nodeValue.includes( '__CREATED__' ) ) {
						node.nodeValue = node.nodeValue.replace( /__CREATED__/g, created );
					}
					if ( node.nodeValue.includes( '__LAST_USED__' ) ) {
						node.nodeValue = node.nodeValue.replace( /__LAST_USED__/g, last_used );
					}
					if ( node.nodeValue.includes( '__LAST_IP__' ) ) {
						node.nodeValue = node.nodeValue.replace( /__LAST_IP__/g, last_ip );
					}
				}
			} );
		} );

		cp_$( '#passwords-grid tbody' ).el.appendChild( clone );
	}

	cp_$( '#register_button' )
		/**
		 * Handle adding a password with the description.
		 *
		 * @param {object} event The event
		 */
		.on( 'click', async function ( event ) {

			try {
				remove_application_passwords_notices();
				let name = cp_$( '#new_application_password_name' ).getValue();

				// Send the create request.
				const response = await calm_fetch.post( 'wp/v2/users/me/application-passwords',
					{
						name: name
					}
				);

				cp_$( '#new_application_password_name' ).setValue( '' );
				cp_$( '#register_button' ).disable();
				cp_$( '#generated_password_description' ).el.textContent = response.name;
				cp_$( '#generated_login_description' ).el.textContent = response.name;
				cp_$( '#new-application-login-value' ).setValue( response.login );
				cp_$( '#new-application-password-value' ).setValue( response.password );
				inline_notice_manager.show( 'add_password_success_message', 'success' );
				row = cp_$$( '#application-passwords-table tbody tr' ).length + 1;

				// Last used and last ip inserted as em dash. 
				passwords_add_row( row, response.uuid, response.name, response.created_human, '—', '—' );
				cp_$( '#passwords-grid' ).show();
				cp_$( '#no_passwords_message' ).hide();
				set_can_add_state( response.can_add );

			} catch ( error ) {
				if ( error instanceof calm_fetch_error ) {
					display_error( error, 'add_password_error_message' );
				} else {
					console.log( error );
				}
			}
		} );

	/**
	 * Handler for revoke of a password.
	 *
	 * @param {Event} event The event
	 */
	cp_$( '#passwords-grid' ).on( 'click', '.actions .revoke', async function ( event ) {

		const uuid = cp_$( this ).parent().parent().data( 'uuid' );
		const $row = cp_$( this ).closest('tr');

		// Send the revoke request.
		try {
			remove_application_passwords_notices();
			const response = await calm_fetch.delete( 'wp/v2/users/me/application-passwords/' + uuid );

			inline_notice_manager.show( 'password_table_message', 'success', application_passwordsL10n.deleted );
			$row.addClass( 'fade-out' );
			$row.on( 'transitionend', () => {
				$row.el.remove();
				const $tbody = cp_$( '#passwords-grid tbody' );
				if ( $tbody.find( 'tr' ) === null ) {
					cp_$( '#no_passwords_message' ).show();
					cp_$( '#passwords-grid' ).hide();
				}
			});
			$row.el.style.opacity = 0; // trigger the fadeout.
			set_can_add_state( true );
		} catch ( error ) {
			display_error( error, 'password_table_message' );
		}
	});

	/**
	 * Handler for starting editing the password description.
	 *
	 * @param {object} event The event
	 */
	cp_$( '#passwords-grid' ).on( 'click', '.actions .edit', function ( event ) {
	
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
	 * An handler for canceling edit of a password description.
	 *
	 * @param {object} event The event
	 */
	cp_$( '#passwords-grid' ).on( 'click', '.close_change', function ( event ) {
	
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
		remove_application_passwords_notices();
		const $row   = cp_$( element ).closest( 'tr' );
		const uuid   = $row.data( 'uuid' );
		const $box   = $row.find( '.edit_form' );
		const $input = $box.find( 'input' );

		const data = {
			'name'   : $input.getValue(),
		};

		// Send the update request.
		try {

			const response = await calm_fetch.post( 'wp/v2/users/me/application-passwords/' + uuid, data );

			inline_notice_manager.show( 'password_table_message', 'success', response.message );
			$box.hide(); // Hide edit box

			// Indicate on edit button box is closed.
			const $edit = $row.find( '.edit' );
			$edit.setAttribute( 'aria-expanded', 'false' );

			// Update description.
			$box.parent().find( 'span' ).el.textContent = response.name;
		} catch ( error ) {
			display_error( error, 'password_table_message' );
		}
	}

	/**
	 * Handler for sending updated password description to the server.
	 *
	 * @param {object} event The event
	 */
	cp_$( '#passwords-grid' ).on( 'click', '.update_description', function ( event ) {
		update_description( event.target );
	});

	/**
	 * Handler for sending updated password description to the server.
	 *
	 * @param {Event} event The event
	 */
	cp_$( '#passwords-grid' ).on( 'keypress', 'input', function ( event ) {

		if ( event.key === 'Enter' ) {
			val = cp_$( this ).getValue();
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
	cp_$( '#passwords-grid' ).on( 'input', 'input', function ( event ) {

		val = cp_$( this ).getValue();
		const $row = cp_$( this ).closest( 'tr' );
		const $but = $row.find( '.update_description' );
		if ( val.trim() != '' ) {
			$but.enable();
		} else {
			$but.disable();
		}
	});

	// Copy to clipboard, assumes the text to copy is in an input before the button and "copied" indicator
	// is after it.
	cp_$$( '.copy-button' ).forEach( (el) => {
		el.on( 'click', async function( e ) {
			const but    = e.target;
			const input  = but.previousElementSibling;
			const copied = but.nextElementSibling;
			const value  = input.value;

			try {
				await navigator.clipboard.writeText( value.replace(/\s+/g, '') );

				// visual indication that copy was done.
				copied.classList.remove( 'hidden' );

				// hide the copied message to be able to indicate to the user that value was
				// copied if the user clicks again.
				setTimeout( function() {
					copied.classList.add( 'hidden' );
				}, 3000 );

				wp.a11y.speak( but.dataset.speak );
			} catch ( error ) {
				console.log( error );
			}
		});
	});

	set_can_add_state( application_passwords_can_add );
});
