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
 * Fields are checked 500 milliseconds after page load, their latest change, or after
 * they become available for validation.
 * The validation-failure-class attribute selects the CSS class added to an invalid
 * field; the default is validation-warning. Disabled fields and fields inside
 * hidden elements are ignored.
 *
 * Get the shared validated form with Generic_Validated_Form.for_form(form), then call
 * validated_form.refresh() after changing field visibility or availability. This clears
 * warnings for hidden or disabled fields and schedules checks for available fields.
 *
 * Submit buttons are disabled while a form has invalid fields.
 *
 * @since 1.0.0
 */

( function () {
	'use strict';

	// Reuse one instance per form so callers share timers and event handlers.
	const validated_forms = new WeakMap();

	// Only for_form() can supply this module-private construction token.
	const constructor_token = Symbol();

	/**
	 * A form with generic field validation and validation feedback.
	 *
	 * @since 1.0.0
	 */
	class Generic_Validated_Form {
		/**
		 * Get the shared validated form instance.
		 *
		 * Different scripts and automatic initialization can request validation for the same form.
		 *
		 * Use this method instead of constructing an instance directly so they share one
		 * instance, with one set of event handlers and validation timers.
		 *
		 * @since 1.0.0
		 *
		 * @param {HTMLFormElement} form Form to validate.
		 *
		 * @return {Generic_Validated_Form} Form with generic validation.
		 */
		static for_form( form ) {
			if ( ! ( form instanceof HTMLFormElement ) ) {
				throw new TypeError( 'Expected a form element.' );
			}

			if ( ! validated_forms.has( form ) ) {
				validated_forms.set( form, new Generic_Validated_Form( form, constructor_token ) );
			}

			return validated_forms.get( form );
		}

		/**
		 * Bind validation state and event handlers to the form.
		 *
		 * @since 1.0.0
		 *
		 * @param {HTMLFormElement} form Form to validate.
		 * @param {symbol} token Module-private token supplied by for_form().
		 *
		 * @throws {TypeError} If construction is attempted outside for_form().
		 */
		constructor( form, token ) {
			if ( token !== constructor_token ) {
				throw new TypeError( 'Use Generic_Validated_Form.for_form().' );
			}

			this.form = form;
			this.timers = new WeakMap();
			this.requires_validation_by_field = new WeakMap();
			this.refresh();

			form.querySelectorAll( '[validate]' ).forEach( ( field ) => {
				if ( ! field.disabled && ! field.closest( '[hidden]' ) ) {
					this.schedule_field_validation( field );
				}
			} );

			form.addEventListener( 'input', this.schedule_validation.bind( this ) );
			form.addEventListener( 'change', this.schedule_validation.bind( this ) );
			form.addEventListener( 'compositionend', this.schedule_validation.bind( this ) );
		}

		/**
		 * Checks a field according to its validation mode.
		 *
		 * @since 1.0.0
		 *
		 * @param {HTMLElement} field Field to check.
		 *
		 * @return {boolean} Whether the field is valid.
		 */
		field_is_valid( field ) {
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
		 *
		 * @return {boolean} Whether the field is currently valid.
		 */
		validate_field( field ) {
			const warningClass = field.getAttribute( 'validation-failure-class' ) || 'validation-warning';
			const requires_validation = ! field.disabled && ! field.closest( '[hidden]' );
			const valid = ! requires_validation || this.field_is_valid( field );

			field.classList.toggle( warningClass, ! valid );
			if ( valid ) {
				field.removeAttribute( 'aria-invalid' );
			} else {
				field.setAttribute( 'aria-invalid', 'true' );
			}

			return valid;
		}

		/**
		 * Update submit-button state to reflect whether all fields requiring validation pass their checks.
		 *
		 * @since 1.0.0
		 */
		update_submit_buttons() {
			const invalid = Array.from( this.form.querySelectorAll( '[validate]' ) ).some( ( field ) => {
				return ! field.disabled && ! field.closest( '[hidden]' ) && ! this.field_is_valid( field );
			} );

			this.form.querySelectorAll( 'input[type="submit"], button[type="submit"], button:not([type])' ).forEach( function ( button ) {
				button.disabled = invalid;
			} );
		}

		/**
		 * Schedules validation of a field 500 milliseconds after its latest change.
		 *
		 * @since 1.0.0
		 *
		 * @param {HTMLElement} field Field to validate.
		 */
		schedule_field_validation( field ) {
			clearTimeout( this.timers.get( field ) );
			this.timers.set( field, setTimeout( () => {
				this.validate_field( field );
				this.update_submit_buttons();
				this.timers.delete( field );
			}, 500 ) );
		}

		/**
		 * Event handler that schedules validation for the target element.
		 *
		 * @since 1.0.0
		 *
		 * @param {Event} event Field input, change, or composition event.
		 */
		schedule_validation( event ) {
			const field = event.target;
			if ( ! field.hasAttribute( 'validate' ) || event.isComposing ) {
				return;
			}

			this.schedule_field_validation( field );
		}

		/**
		 * Refresh field validation and submit-button state.
		 *
		 * @since 1.0.0
		 */
		refresh() {
			this.form.querySelectorAll( '[validate]' ).forEach( ( field ) => {
				const requires_validation = ! field.disabled && ! field.closest( '[hidden]' );
				const previously_required = this.requires_validation_by_field.get( field );
				this.requires_validation_by_field.set( field, requires_validation );

				if ( ! requires_validation ) {
					clearTimeout( this.timers.get( field ) );
					this.timers.delete( field );
					this.validate_field( field );
				} else if ( previously_required === false ) {
					this.schedule_field_validation( field );
				}
			} );
			this.update_submit_buttons();
		}

	}

	window.Generic_Validated_Form = Generic_Validated_Form;

	/**
	 * Initialize generic validation for forms marked with calm-validate.
	 *
	 * @since 1.0.0
	 */
	function initialize_forms_validation() {
		// Give each marked form one shared instance that checks its fields and updates validation feedback.
		document.querySelectorAll( 'form.calm-validate' ).forEach( Generic_Validated_Form.for_form );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initialize_forms_validation );
	} else {
		initialize_forms_validation();
	}
} )();
