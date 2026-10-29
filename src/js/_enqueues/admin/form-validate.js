/**
 * Validates fields in forms marked with calm-validate.
 *
 * Set a field's validate attribute to one of these modes:
 * - "pattern": The value must match the regular expression in validation-pattern.
 * - "nonempty": The value must contain a non-whitespace character.
 * - "integer-range": The value must be a nonnegative integer within the
 *   optional min and max attributes (inclusive).
 * - "file": Exactly one file must be selected.
 *
 * Fields are checked one second after page load, their latest change, or after
 * they become available for validation, and on submission.
 * The validation-failure-class attribute selects the CSS class added to an invalid
 * field; the default is validation-warning. Disabled fields and fields inside
 * hidden elements are ignored.
 *
 * Call calmpress_form_validation.refresh(form) after changing field visibility
 * or availability. The argument may be a form element or a CSS selector for
 * one. It clears warnings for fields that have become hidden or disabled and
 * schedules validation for fields that have become available.
 *
 * Invalid forms are not submitted.
 *
 * @since 1.0.0
 */

( function () {
	'use strict';
	const timers = new WeakMap();
	const validation_state = new WeakMap();

	/**
	 * Checks a field according to its validation mode.
	 *
	 * @since 1.0.0
	 *
	 * @param {HTMLElement} field Field to check.
	 * @return {boolean} Whether the field is valid.
	 */
	function field_is_valid( field ) {
		switch ( field.getAttribute( 'validate' ) ) {
			case 'pattern':
				return new RegExp( field.getAttribute( 'validation-pattern' ) ).test( field.value );
			case 'nonempty':
				return field.value.trim() !== '';
			case 'integer-range':
				return /^\d+$/.test( field.value ) &&
					( ! field.hasAttribute( 'min' ) || Number( field.value ) >= Number( field.min ) ) &&
					( ! field.hasAttribute( 'max' ) || Number( field.value ) <= Number( field.max ) );
			case 'file':
				return field.files.length === 1;
			default:
				return true;
		}
	}

	/**
	 * Updates a field's warning state.
	 *
	 * @since 1.0.0
	 *
	 * @param {HTMLElement} field Field to check.
	 * @return {boolean} Whether the field is currently valid.
	 */
	function validate_field( field ) {
		const warningClass = field.getAttribute( 'validation-failure-class' ) || 'validation-warning';
		const requires_validation = ! field.disabled && ! field.closest( '[hidden]' );
		const valid = ! requires_validation || field_is_valid( field );

		field.classList.toggle( warningClass, ! valid );
		if ( valid ) {
			field.removeAttribute( 'aria-invalid' );
		} else {
			field.setAttribute( 'aria-invalid', 'true' );
		}

		return valid;
	}

	/**
	 * Checks fields requiring validation in one form.
	 *
	 * @since 1.0.0
	 *
	 * @param {HTMLFormElement} form Form to check.
	 * @return {boolean} Whether the fields requiring validation are valid.
	 */
	function validate_form( form ) {
		let valid = true;

		form.querySelectorAll( '[validate]' ).forEach( function ( field ) {
			if ( ! validate_field( field ) ) {
				valid = false;
			}
		} );

		return valid;
	}

	/**
	 * Schedules validation of a field one second after its latest change.
	 *
	 * @since 1.0.0
	 *
	 * @param {HTMLElement} field Field to validate.
	 */
	function schedule_field_validation( field ) {
		clearTimeout( timers.get( field ) );
		timers.set( field, setTimeout( function () {
			validate_field( field );
			timers.delete( field );
		}, 1000 ) );
	}

	/**
	 * Schedules validation of the event's target field.
	 *
	 * @since 1.0.0
	 *
	 * @param {Event} event Field input, change, or composition event.
	 */
	function schedule_validation( event ) {
		const field = event.target;
		if ( ! field.hasAttribute( 'validate' ) || event.isComposing ) {
			return;
		}

		schedule_field_validation( field );
	}

	window.calmpress_form_validation = {
		/**
		 * Updates validation after fields become available, hidden, or disabled.
		 *
		 * @since 1.0.0
		 *
		 * @param {HTMLFormElement|string} form Form whose fields have changed, or a CSS selector for it.
		 */
		refresh( form ) {
			if ( typeof form === 'string' ) {
				form = document.querySelector( form );
			}

			if ( ! ( form instanceof HTMLFormElement ) ) {
				throw new TypeError( 'Expected a form element or a selector matching one.' );
			}

			form.querySelectorAll( '[validate]' ).forEach( function ( field ) {
				const requires_validation = ! field.disabled && ! field.closest( '[hidden]' );
				const previously_required = validation_state.get( field );
				validation_state.set( field, requires_validation );

				if ( ! requires_validation ) {
					clearTimeout( timers.get( field ) );
					timers.delete( field );
					validate_field( field );
				} else if ( previously_required === false ) {
					schedule_field_validation( field );
				}
			} );
		}
	};

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( 'form.calm-validate' ).forEach( function ( form ) {
			window.calmpress_form_validation.refresh( form );
			form.querySelectorAll( '[validate]' ).forEach( function ( field ) {
				if ( ! field.disabled && ! field.closest( '[hidden]' ) ) {
					schedule_field_validation( field );
				}
			} );
			form.addEventListener( 'input', schedule_validation );
			form.addEventListener( 'change', schedule_validation );
			form.addEventListener( 'compositionend', schedule_validation );
			form.addEventListener( 'submit', function ( event ) {
				if ( ! validate_form( form ) ) {
					event.preventDefault();
				}
			} );
		} );
	} );
} )();
