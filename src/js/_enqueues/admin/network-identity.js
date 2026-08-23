/**
 * Handles the UX required by the Network Identity Settings page.
 *
 * @since 1.0.0
 * @output wp-admin/js/network-identity.js
 */

/* global calm_network_identity, cp_$, wp */

( function () {
	var media_ajax = wp.media.ajax,
		$remove_button = cp_$( '#js-remove-site-icon' ),
		$removal_warning = cp_$( '#network-site-icon-removal-warning' ),
		$logo_button = cp_$( '#choose-network-logo-button' ),
		$logo_preview = cp_$( '#network-logo-preview' ),
		$logo_preview_container = cp_$( '#network-logo-preview-container' ),
		$logo_field = cp_$( '#network_logo_hidden_field' ),
		$logo_remove_button = cp_$( '#js-remove-network-logo' ),
		$logo_removal_warning = cp_$( '#network-logo-removal-warning' ),
		logo_frame;

	wp.Uploader.errorMap.FILE_EXTENSION_ERROR = calm_network_identity.invalid_file_type;

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

	/**
	 * Clears the Network Site Icon value when its remove button is clicked.
	 *
	 * @since 1.0.0
	 */
	$remove_button.on( 'click', function () {
		cp_$( '#site_icon_hidden_field' ).setValue( '0' );
	} );

	// Keep the warning synchronized with the removal action as the selected icon changes.
	new MutationObserver( function () {
		$removal_warning.toggleClass( 'hidden', $remove_button.hasClass( 'hidden' ) );
	} ).observe( $remove_button.el, { attributes: true, attributeFilter: [ 'class' ] } );

	/**
	 * Opens an upload-only image picker for the Network Logo.
	 *
	 * The selected image is used as uploaded without a cropping step.
	 *
	 * @since 1.0.0
	 */
	$logo_button.on( 'click', function () {
		logo_frame = wp.media( {
			button: {
				text: $logo_button.data( 'update' ),
			},
			states: [
				new wp.media.controller.Library( {
					title: $logo_button.data( 'choose-text' ),
					library: wp.media.query( { type: 'image' } ),
					content: 'upload',
					contentUserSetting: false,
					router: false,
					searchable: false,
					date: false,
				} ),
			],
		} );

		/**
		 * Updates the Network Logo field and preview when an image is selected.
		 *
		 * @since 1.0.0
		 */
		logo_frame.on( 'select', function () {
			var attachment = logo_frame.state().get( 'selection' ).first(),
				old_class;

			$logo_field.setValue( attachment.id );
			$logo_preview.setAttribute( 'src', attachment.get( 'url' ) );
			$logo_preview_container.removeClass( 'hidden' );
			$logo_remove_button.removeClass( 'hidden' );

			if ( '1' !== $logo_button.data( 'state' ) ) {
				old_class = $logo_button.attribute( 'class' );
				$logo_button
					.setAttribute( 'class', $logo_button.data( 'alt-classes' ) )
					.setData( 'alt-classes', old_class )
					.setData( 'state', '1' );
			}

			$logo_button.setText( $logo_button.data( 'update-text' ) );
			logo_frame.close();
			logo_frame = null;
		} );

		logo_frame.open();
	} );

	/**
	 * Clears the Network Logo value and preview when its remove button is clicked.
	 *
	 * @since 1.0.0
	 */
	$logo_remove_button.on( 'click', function () {
		var old_class = $logo_button.attribute( 'class' );

		$logo_field.setValue( '0' );
		$logo_preview.setAttribute( 'src', '' );
		$logo_preview_container.addClass( 'hidden' );
		$logo_remove_button.addClass( 'hidden' );
		$logo_button
			.setAttribute( 'class', $logo_button.data( 'alt-classes' ) )
			.setData( 'alt-classes', old_class )
			.setData( 'state', '' )
			.setText( $logo_button.data( 'choose-text' ) )
			.trigger( 'focus' );
	} );

	new MutationObserver( function () {
		$logo_removal_warning.toggleClass( 'hidden', $logo_remove_button.hasClass( 'hidden' ) );
	} ).observe( $logo_remove_button.el, { attributes: true, attributeFilter: [ 'class' ] } );
}() );
