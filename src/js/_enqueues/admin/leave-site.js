/**
 * Site departure confirmation interactions.
 *
 * @package CalmPress
 * @since calmPress 1.0.0
 */

( function() {
	'use strict';

	/**
	 * Requires confirmation before enabling the site departure button.
	 *
	 * @since 1.0.0
	 */
	function control_leave_site_confirmation() {
		const confirmation = document.getElementById( 'confirm_leave_site' );
		const submit_button = document.getElementById( 'submit' );

		confirmation.addEventListener( 'change', update_submit_button );
		update_submit_button();

		/**
		 * Matches the departure button state to the confirmation checkbox.
		 *
		 * @since 1.0.0
		 */
		function update_submit_button() {
			submit_button.disabled = ! confirmation.checked;
		}
	}

	control_leave_site_confirmation();
}() );
