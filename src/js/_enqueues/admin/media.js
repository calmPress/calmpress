
( function( $ ){
	/**
	 * Initializes the file once the DOM is fully loaded and attaches events to the
	 * various form elements.
	 *
	 * @return {void}
	 */
	$( function() {
		var settings,
			$mediaGridWrap             = $( '#wp-media-grid' ),
			copyAttachmentURLClipboard = new ClipboardJS( '.copy-attachment-url.media-library' ),
			copyAttachmentURLSuccessTimeout,
			previousSuccessElement = null;

		// Opens a manage media frame into the grid.
		if ( $mediaGridWrap.length && window.wp && window.wp.media ) {
			settings = _wpMediaGridSettings;

			var frame = window.wp.media({
				frame: 'manage',
				container: $mediaGridWrap,
				library: settings.queryVars
			}).open();

			// Fire a global ready event.
			$mediaGridWrap.trigger( 'wp-media-grid-ready', frame );
		}

		// Binds the bulk action events to the submit buttons.
		$( '#doaction' ).on( 'click', function( event ) {

			/*
			 * Handle the bulk action based on its value.
			 */
			$( 'select[name="action"]' ).each( function() {
				var optionValue = $( this ).val();

				if ( 'delete' === optionValue ) {
					if ( ! showNotice.warn() ) {
						event.preventDefault();
					}
				}
			});
		});

		/**
		 * Handles media list copy media URL button.
		 *
		 * @since 6.0.0
		 *
		 * @param {MouseEvent} event A click event.
		 * @return {void}
		 */
		copyAttachmentURLClipboard.on( 'success', function( event ) {
			var triggerElement = $( event.trigger ),
				successElement = $( '.success', triggerElement.closest( '.copy-to-clipboard-container' ) );

			// Clear the selection and move focus back to the trigger.
			event.clearSelection();

			// Checking if the previousSuccessElement is present, adding the hidden class to it.
			if ( previousSuccessElement ) {
				previousSuccessElement.addClass( 'hidden' );
			}

			// Show success visual feedback.
			clearTimeout( copyAttachmentURLSuccessTimeout );
			successElement.removeClass( 'hidden' );

			// Hide success visual feedback after 3 seconds since last success and unfocus the trigger.
			copyAttachmentURLSuccessTimeout = setTimeout( function() {
				successElement.addClass( 'hidden' );
				// No need to store the previous success element further.
				previousSuccessElement = null;
			}, 3000 );

			previousSuccessElement = successElement;

			// Handle success audible feedback.
			wp.a11y.speak( wp.i18n.__( 'The file URL has been copied to your clipboard' ) );
		} );
	});
})( jQuery );
