/**
 * Handle SFTP connection settings and remember the last submitted values.
 *
 * @since 1.0.0
 */

( function () {
	'use strict';

	// Name attributes of the SFTP fields whose values are saved to localStorage and used for prefilling.
	const names = [
		'calm_sftp_host', 'calm_sftp_port', 'calm_sftp_base',
		'calm_sftp_username', 'calm_sftp_password', 'calm_sftp_auth_method',
		'calm_sftp_private_key_text', 'calm_sftp_passphrase'
	];

	/**
	 * SFTP connection-settings UI with browser storage and private-key file loading.
	 *
	 * @since 1.0.0
	 */
	class SFTP_Connection_Settings {
		/**
		 * Bind the container's elements, storage, and change handlers.
		 *
		 * @since 1.0.0
		 *
		 * @param {HTMLElement} section_element Container holding SFTP credential fields.
		 */
		constructor( section_element ) {
			this.section_element = section_element;
			this.file_element = section_element.querySelector( '[data-sftp-private-key-file]' );
			this.validated_form = Generic_Validated_Form.for_form( this.file_element.form );
			this.method_element = section_element.querySelector( '[name="calm_sftp_auth_method"]' );
			this.key_element = section_element.querySelector( '[name="calm_sftp_private_key_text"]' );
			this.error_element = this.file_element.parentElement.querySelector( '[data-private-key-error]' );
			this.loaded_message_element = this.key_element.parentElement.querySelector( '[data-private-key-loaded]' );

			new Credentials_Storage( section_element, 'sftp', names );

			this.file_element.addEventListener( 'change', this.read_private_key.bind( this ) );
			this.method_element.addEventListener( 'change', this.authentication_changed.bind( this ) );
			this.update_sftp_authentication();
			this.validated_form.refresh();
		}

		/**
		 * Shows the fields for the selected authentication method.
		 *
		 * @since 1.0.0
		 */
		update_sftp_authentication() {
			this.section_element.querySelectorAll( '[data-sftp-auth-method]' ).forEach( ( row_element ) => {
				const active = row_element.dataset.sftpAuthMethod.split( ' ' ).includes( this.method_element.value );
				row_element.hidden = ! active;
				row_element.querySelectorAll( 'input, textarea' ).forEach( function ( field_element ) {
					if ( field_element.dataset.sftpRequired === 'true' ) {
						field_element.required = active;
					}
				} );
			} );
		}

		/**
		 * Clear the private-key file input and display the supplied error message beside it.
		 *
		 * @since 1.0.0
		 *
		 * @param {string} error_text Error message to display.
		 */
		clear_private_key_file_and_show_error( error_text ) {
			this.file_element.value = '';
			this.error_element.textContent = error_text;
			this.error_element.hidden = false;
			this.validated_form.refresh();
		}

		/**
		 * Read the selected private-key file into the private-key text field.
		 *
		 * @since 1.0.0
		 */
		async read_private_key() {
			const selected_file = this.file_element.files[ 0 ];

			// A change event can mean the selection was cleared, including when the picker is cancelled.
			if ( ! selected_file ) {
				this.validated_form.refresh();
				return;
			}

			// Clear the previous key and error message, and hide the previous confirmation before reading the new file.
			clearTimeout( this.loaded_message_timeout );
			this.loaded_message_element.hidden = true;
			this.key_element.value = '';
			this.error_element.textContent = '';
			this.error_element.hidden = true;

			if ( selected_file.size > Number( this.file_element.dataset.maxBytes ) ) {
				this.clear_private_key_file_and_show_error( this.file_element.dataset.sizeError );
				return;
			}

			// Prevent selecting another file while the current file is being read and processed.
			this.file_element.disabled = true;
			this.file_element.setAttribute( 'aria-busy', 'true' );

			// Read the selected file asynchronously and decode its contents as UTF-8 text.
			try {
				let bytes;

				// Read raw bytes so invalid UTF-8 can be detected during decoding.

				try {
					bytes = await selected_file.arrayBuffer();
				} catch ( error ) {
					this.clear_private_key_file_and_show_error( this.file_element.dataset.readError );
					return;
				}

				let contents;

				// Decode as UTF-8 and reject malformed sequences rather than replacing them.
				try {
					contents = new TextDecoder( 'utf-8', { fatal: true } ).decode( bytes );
				} catch ( error ) {
					this.clear_private_key_file_and_show_error( this.file_element.dataset.contentError );
					return;
				}

				// Leave a different authentication method selected if the user changed it during reading.
				if ( this.method_element.value !== 'key_file' ) {
					return;
				}

				// Fill the key-text field, show the loaded message, and display the private-key settings.
				this.key_element.value = contents;
				this.loaded_message_element.hidden = false;

				// Hide the confirmation after eight seconds.
				this.loaded_message_timeout = setTimeout( () => {
					this.loaded_message_element.hidden = true;
				}, 8000 );

				this.method_element.value = 'key_text';
				this.update_sftp_authentication();
			} finally {
				// Clear and re-enable the picker after success or failure, then refresh field validation.
				this.file_element.value = '';
				this.file_element.disabled = false;
				this.file_element.setAttribute( 'aria-busy', 'false' );
				this.validated_form.refresh();
			}
		}

		/**
		 * Event handler that displays the selected authentication fields and refreshes validation.
		 *
		 * @since 1.0.0
		 */
		authentication_changed() {
			this.update_sftp_authentication();
			this.validated_form.refresh();
		}
	}

	// Initialize an independent settings UI for each SFTP container.
	document.querySelectorAll( '[data-credentials-type="sftp"]' ).forEach( function ( section_element ) {
		new SFTP_Connection_Settings( section_element );
	} );
} )();
