/**
 * Creates a dialog containing posts that can have a particular media attached
 * to it.
 *
 * @since 2.7.0
 * @output wp-admin/js/media.js
 *
 * @namespace findPosts
 *
 * @requires jQuery
 */

/* global ajaxurl, _wpMediaGridSettings, showNotice */

( function( $ ){
	/**
	 * Initializes the file once the DOM is fully loaded and attaches events to the
	 * various form elements.
	 *
	 * @return {void}
	 */
	$( function() {
		var settings, $mediaGridWrap = $( '#wp-media-grid' );

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
	});
})( jQuery );
