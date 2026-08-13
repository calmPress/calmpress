/**
 * Handles the UX required by the Network Identity Settings page.
 *
 * @since 1.0.0
 * @output wp-admin/js/network-identity.js
 */

/* global calm_network_identity, jQuery, wp */

( function ( $ ) {
	var media_ajax = wp.media.ajax,
		$remove_button = $( '#js-remove-site-icon' ),
		$removal_warning = $( '#network-site-icon-removal-warning' );

	/**
	 * Uses the network main site's AJAX URL for media attachment requests.
	 *
	 * The override is needed because network-owned attachments are stored on the
	 * main site, while the requests originate from the Network Admin screen.
	 *
	 * @since 1.0.0
	 *
	 * @param {string|Object} action    AJAX action, or request options when no action is passed separately.
	 * @param {Object}        [options] Request options when an action is passed separately.
	 *
	 * @return {Promise} AJAX request promise.
	 */
	wp.media.ajax = function ( action, options ) {
		// WordPress accepts request options as either the first or second argument.
		if ( 'object' === typeof action ) {
			action.url = calm_network_identity.ajax_url;
		} else {
			options = options || {};
			options.url = calm_network_identity.ajax_url;
		}

		return media_ajax.call( this, action, options );
	};

	/**
	 * Crops a Network Site Icon in the network main-site context.
	 *
	 * @since 1.0.0
	 *
	 * @param {Object} attachment Selected attachment model.
	 * @return {Promise} AJAX request promise.
	 */
	wp.media.controller.SiteIconCropper.prototype.doCrop = function ( attachment ) {
		var crop_details = attachment.get( 'cropDetails' ),
			control = this.get( 'control' );

		crop_details.dst_width = control.params.width;
		crop_details.dst_height = control.params.height;

		return wp.ajax.send( {
			url: calm_network_identity.ajax_url,
			data: {
				action: 'crop-image',
				media_owned_by_network: '1',
				nonce: attachment.get( 'nonces' ).edit,
				id: attachment.get( 'id' ),
				context: 'site-icon',
				cropDetails: crop_details,
			},
		} );
	};

	$remove_button.on( 'click', function () {
		$( '#site_icon_hidden_field' ).val( '0' );
	} );

	// Keep the warning synchronized with the removal action as the selected icon changes.
	new MutationObserver( function () {
		$removal_warning.toggleClass( 'hidden', $remove_button.hasClass( 'hidden' ) );
	} ).observe( $remove_button[0], { attributes: true, attributeFilter: [ 'class' ] } );
}( jQuery ) );
