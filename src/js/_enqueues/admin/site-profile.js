/**
 * Site profile administration interactions.
 *
 * @package CalmPress
 * @since calmPress 1.0.0
 */

/**
 * Controls field state, validation, and avatar selection on the site profile page.
 *
 * @since 1.0.0
 */
function control_site_profile_page() {
	const can_upload_avatar_images = site_profile_settings.can_upload_avatar_images;
	const display_name_override = document.getElementById( 'override_display_name' );
	const display_name = document.getElementById( 'display_name' );
	const avatar_source = can_upload_avatar_images ? document.getElementById( 'avatar_source' ) : null;
	const account_avatar_option = can_upload_avatar_images ? avatar_source.querySelector( 'option[value="account"]' ) : null;
	const account_avatar = can_upload_avatar_images ? document.getElementById( 'account_avatar_preview' ) : null;
	const generated_avatar = can_upload_avatar_images ? document.getElementById( 'generated_avatar_preview' ) : null;
	const site_avatar = can_upload_avatar_images ? document.getElementById( 'site_avatar_preview' ) : null;
	const select_avatar = can_upload_avatar_images ? document.getElementById( 'select_avatar_image' ) : null;
	const form = display_name.closest( 'form' );
	const submit_button = document.getElementById( 'submit' );

	// Connect the page controls and apply their initial state.
	display_name_override.addEventListener( 'change', update_display_name_field );
	display_name.addEventListener( 'input', update_display_name_validity );
	if ( can_upload_avatar_images ) {
		avatar_source.addEventListener( 'change', update_avatar_fields );
	}
	form.addEventListener( 'submit', validate_form );
	update_display_name_validity();
	update_avatar_source_availability();

	/**
	 * Switches the display name input between account and site-specific values.
	 *
	 * The entered site-specific value is preserved while the account display name
	 * is shown in the read-only input.
	 *
	 * @since 1.0.0
	 */
	function update_display_name_field() {

		// Without a site-specific value, the input uses the display name set in the account profile.
		display_name.readOnly = ! display_name_override.checked;

		// Swap the visible value while preserving an unsaved site-specific name in the element data.
		if ( display_name_override.checked ) {
			display_name.value = display_name.dataset.siteValue;
		} else {
			display_name.dataset.siteValue = display_name.value;
			display_name.value = display_name.dataset.accountValue;
		}

		// Notify validation and avatar-preview code that the displayed name changed programmatically.
		display_name.dispatchEvent( new Event( 'input' ) );
		update_avatar_source_availability();
	}

	/**
	 * Prevents inheriting an account avatar with a site-specific display name.
	 *
	 * @since 1.0.0
	 */
	function update_avatar_source_availability() {
		if ( ! can_upload_avatar_images ) {
			return;
		}

		account_avatar_option.disabled = display_name_override.checked;
		if ( display_name_override.checked && 'account' === avatar_source.value ) {
			avatar_source.value = 'generated';
		}

		update_avatar_fields();
	}

	/**
	 * Prevents submitting an empty site-specific display name.
	 *
	 * @since 1.0.0
	 *
	 * @param {SubmitEvent} event Form submission event.
	 */
	function validate_form( event ) {
		if ( display_name_override.checked && '' === display_name.value.trim() ) {
			event.preventDefault();
			display_name.classList.add( 'validation-warning' );
			display_name.setAttribute( 'aria-invalid', 'true' );
			display_name.focus();
		}
	}

	/**
	 * Updates the display name error state and submit button.
	 *
	 * @since 1.0.0
	 */
	function update_display_name_validity() {
		const invalid = display_name_override.checked && '' === display_name.value.trim();

		submit_button.disabled = invalid;
		if ( invalid ) {
			display_name.setAttribute( 'aria-invalid', 'true' );
		} else {
			display_name.removeAttribute( 'aria-invalid' );
		}
	}

	/**
	 * Updates the avatar fields when their source changes.
	 *
	 * @since 1.0.0
	 */
	function update_avatar_fields() {
		account_avatar.hidden = 'account' !== avatar_source.value;
		account_avatar.style.display = 'account' === avatar_source.value ? '' : 'none';
		generated_avatar.hidden = 'generated' !== avatar_source.value;
		generated_avatar.style.display = 'generated' === avatar_source.value ? '' : 'none';
		site_avatar.hidden = 'image' !== avatar_source.value;
		site_avatar.style.display = 'image' === avatar_source.value ? '' : 'none';
		select_avatar.disabled = 'image' !== avatar_source.value;
	}

}

// The script is loaded in the admin footer after the site profile markup.
control_site_profile_page();
