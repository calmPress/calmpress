/**
 * Shared browser storage for credentials forms.
 *
 * The container's data-credentials-site URL identifies the site in the storage key.
 *
 * Saved values are filled only when the container has data-credentials-prefill="true".
 *
 * @since 1.0.0
 */
( function () {
	'use strict';

	/**
	 * Browser storage for a container of credential fields.
	 *
	 * @since 1.0.0
	 */
	class Credentials_Storage {
		/**
		 * Bind the fields, fill saved values if requested, and register submission saving.
		 *
		 * The fields' form property identifies the owning form whose submit event
		 * triggers saving. All fields in the container must share that form.
		 *
		 * @since 1.0.0
		 *
		 * @param {HTMLElement} container Element containing the credential fields.
		 * @param {string} group Storage group.
		 * @param {string[]} names Fields to save and fill.
		 */
		constructor( container, group, names ) {
			this.container = container;
			this.storage_key = 'calmpress_credentials:' + container.dataset.credentialsSite + ':' + group;
			this.fields = new Map();

			names.forEach( ( name ) => {
				const field = container.querySelector( '[name="' + name + '"]' );

				if ( ! field ) {
					throw new Error( 'Missing credentials form field: ' + name );
				}

				if ( ! field.form || ( this.form && field.form !== this.form ) ) {
					throw new Error( 'Credential fields must belong to the same form.' );
				}

				this.form = field.form;
				this.fields.set( name, field );
			} );

			try {
				localStorage.getItem( this.storage_key );
			} catch ( error ) {
				// Skip prefilling and save registration when storage access is denied.
				return;
			}

			this.prefill_if_requested();

			this.form.addEventListener( 'submit', () => {
				this.save();
			}, true );
		}

		/**
		 * Fill named fields with the relevant values stored in localStorage.
		 *
		 * Set data-credentials-prefill="true" on the container to enable prefilling.
		 *
		 * @since 1.0.0
		 */
		prefill_if_requested() {
			if ( this.container.dataset.credentialsPrefill !== 'true' ) {
				return;
			}

			let values;

			try {
				const stored = localStorage.getItem( this.storage_key );

				if ( stored === null ) {
					return;
				}

				values = JSON.parse( stored );
			} catch ( error ) {
				// Storage may be unavailable or contain invalid JSON.
				return;
			}

			if ( ! values || typeof values !== 'object' || Array.isArray( values ) ) {
				console.error( 'Invalid stored credentials: expected an object.' );
				return;
			}

			// Fill each configured field with its saved string value, provided that
			// the value is an available option when the field is a dropdown.
			this.fields.forEach( ( field, name ) => {
				if ( typeof values[ name ] !== 'string' ) {
					console.error( 'Invalid stored credentials: expected a string for field ' + name + '.' );
					return;
				}

				// Keep the current selection if the saved value is no longer an available option.
				if ( field.tagName === 'SELECT' && ! Array.from( field.options ).some( option => option.value === values[ name ] ) ) {
					return;
				}

				field.value = values[ name ];
			} );
		}

		/**
		 * Save the current field values to localStorage.
		 *
		 * Saving is best effort: storage failures are ignored so form submission can continue.
		 *
		 * @since 1.0.0
		 */
		save() {
			const values = {};

			this.fields.forEach( ( field, name ) => {
				values[ name ] = field.value;
			} );

			try {
				localStorage.setItem( this.storage_key, JSON.stringify( values ) );
			} catch ( error ) {
				// Storage may be full or access may have been revoked since initialization.
			}
		}
	}

	window.Credentials_Storage = Credentials_Storage;
}() );
