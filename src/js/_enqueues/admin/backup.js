/* global calmAuthorL10N */
jQuery( document ).ready( function( $ ) {
    'use strict';

	/* Globals */
	var author_images_modal;

	$( '#addtag, #edittag' )

		/**
		 * Invoke the media modal
		 *
		 * @param {object} event The event
		 */
		.on( 'click', '.featured-image-choose', function ( event ) {
			author_show_media_modal( this, event );
		} )

		/**
		 * Remove image
		 *
		 * @param {object} event The event
		 */
		.on( 'click', '.featured-image-remove', function ( event ) {
			author_images_reset( this, event );
		} );

	/**
	 * Reset the form on submit.
	 *
	 * Since the form is never *actually* submitted (but instead serialized on
	 * #submit being clicked), we'll have to do the same.
	 *
	 * @see wp-admin/js/tags.js
	 * @link https://core.trac.wordpress.org/ticket/36956
	 *
	 * @param {object} event The event.
	 */
	$( document ).on( 'term-added', function ( event ) {
		author_images_reset( $( '#addtag #submit' ), event );
	} );

	/**
	 * Shows media modal, and sets image in placeholder
	 *
	 * @param {type} element
	 * @param {type} event
	 * @returns {void}
	 */
	function author_show_media_modal( element, event ) {
		event.preventDefault();

		// Initialize the modal the first time.
		if ( ! author_images_modal ) {
			author_images_modal = wp.media.frames.author_images_modal || wp.media( {
				title:    calmAuthorL10N.mediaTitle,
				button:   { text: calmAuthorL10N.selectText },
				library:  { type: 'image' },
				multiple: false
			} );

			// Picking an image
			author_images_modal.on( 'select', function () {

				// Get the image URL
				var image = author_images_modal.state().get( 'selection' ).first().toJSON();

				if ( '' !== image ) {
					$( '#featured-image-id' ).val( image.id );
					$( '#featured-image' ).attr( 'src', image.url ).show();
					$( '.featured-image-remove' ).show();
				}
			} );
		}

		// Open the modal
		author_images_modal.open();
	}

	/**
	 * Reset the add-tag form
	 *
	 * @param {element} element
	 * @param {event} event
	 * @returns {void}
	 */
	function author_images_reset( element, event ) {
		event.preventDefault();

		// Clear image metadata
		$( '#featured-image-id' ).val( 0 );
		$( '#featured-image' ).attr( 'src', '' ).hide();
		$( '.featured-image-remove' ).hide();
	}
} );
