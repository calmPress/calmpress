/**
 * Handle the logo setting in options-general.php.
 *
 * @since 1.0.0
 * @output wp-admin/js/cp-logo-selection.js
 */

/* global calm_logo_selection, wp */

(function () {
	var $chooseButton = cp_$( '#choose-logo-from-library-button' ),
		$logoPreview = cp_$( '#logo-preview' ),
		$logoPreviewContainer = cp_$( '#logo-preview-container' ),
		$hiddenDataField = cp_$( '#logo_hidden_field' ),
		$removeButton = cp_$( '#js-remove-logo' ),
		$network_logo_label = cp_$( '#network-logo-label' ),
		frame;

	wp.Uploader.errorMap.FILE_EXTENSION_ERROR = calm_logo_selection.invalid_file_type;

	/**
	 * Initializes the media frame for selecting or cropping an image.
	 *
	 * @since 1.0.0
	 */
	$chooseButton.on( 'click', function () {
		var $el = $chooseButton;

		// Create the media frame.
		frame = wp.media( {
			button: {
				// Set the text of the button.
				text: $el.data( 'update' ),

				// Don't close, we might need to crop.
				close: false,
			},
			states: [
				new wp.media.controller.Library( {
					title: $el.data( 'choose-text' ),
					library: wp.media.query( { type: 'image' } ),
					date: false,
				} ),
			],
		} );

		// When an image is selected, run a callback.
		frame.on( 'select', function () {
			// Grab the selected attachment.
			const attachment = frame.state().get( 'selection' ).first();

			switchToUpdate( attachment.attributes );

			$hiddenDataField.setValue( attachment.id );
			frame.close();
		} );

		frame.open();
	} );

	/**
	 * Update the UI when a logo is selected.
	 *
	 * @since 1.0.0
	 *
	 * @param {array} attributes The attributes for the attachment.
	 */
	function switchToUpdate( attributes ) {

		$logoPreview.setAttribute( 'src', attributes.url );

		// Remove hidden class from icon preview div and remove button.
		$logoPreviewContainer.removeClass( 'hidden' );
		$network_logo_label.addClass( 'hidden' );
		$removeButton.removeClass( 'hidden' );

		// If the choose button is not in the update state, swap the classes.
		if ( $chooseButton.data( 'state' ) !== '1' ) {
			const old_class = $chooseButton.attribute( 'class' );

			$chooseButton
				.setAttribute( 'class', $chooseButton.data( 'alt-classes' ) )
				.setData( 'alt-classes', old_class )
				.setData( 'stat', '1' );
		}

		// Swap the text of the choose button.
		$chooseButton.setText( $chooseButton.data( 'update-text' ) );
	}

	/**
	 * Handles the click event of the remove button.
	 *
	 * @since 1.0.0
	 */
	$removeButton.on( 'click', function () {
		var network_logo_url = $chooseButton.data( 'network-logo-url' );

		$hiddenDataField.setValue( 'false' );
		$removeButton.toggleClass( 'hidden' );
		$logoPreviewContainer.toggleClass( 'hidden', ! network_logo_url );
		$network_logo_label
			.toggleClass( 'hidden', ! network_logo_url )
			.find( 'strong' )
			.setText( $network_logo_label.data( 'pending-text' ) );

		/**
		 * Resets state to the button, for correct visual style and state.
		 * Updates the text of the button.
		 * Sets focus state to the button.
		 */
		$chooseButton
			.setAttribute( 'class', $chooseButton.data( 'alt-classes' ) )
			.setData( 'alt-classes', $chooseButton.attribute( 'class' ) )
			.setData( 'data-state', '' )
			.setText( $chooseButton.data( 'choose-text' ) )
			.trigger( 'focus' );
		$logoPreview.setAttribute( 'src', network_logo_url || '' );
	} );
})();
