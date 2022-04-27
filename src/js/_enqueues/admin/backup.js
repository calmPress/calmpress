/* Handle Ajaxifying the backup creation. */

jQuery( document ).ready( function( $ ) {
    'use strict';

	/**
	 * Update the notification area with a specific type of message.
	 * 
	 * @param string status Can be either 
	 *                      'failure' Indicating bad network or response before backup even started.
	 *                      'backup_failed' Indicating a failure during backup.
	 *                      'success' Indicating backup was finished
	 *                      'in_progress' Indication backup still being done.
	 *
	 * @param string message Provides additional information text for the failures
	 */
	function update_status_notification( status, message ) {
		const notification_el   = document.getElementById( 'notifications' );
		const notification_p_el = notification_el.querySelector( 'p' );

		notification_el.classList.remove( 'notice-info' );
		notification_el.classList.add( 'notice' );
		switch ( status ) {
			case 'in_progress' :
				notification_el.classList.add( 'notice-info' );
				notification_p_el.innerText = calmBackupData.in_progress_message;
				break;
			case 'failure' :
				notification_el.classList.add( 'notice-error' );
				notification_p_el.innerText = calmBackupData.generic_fail_message + message;
				break;
			case 'backup_failed' :
				notification_el.classList.add( 'notice-error' );
				notification_p_el.innerText = calmBackupData.backup_fail_message + message;
				break;
			case 'success' :
				notification_el.classList.add( 'notice-success' );
				notification_p_el.innerText = calmBackupData.success_message;
				break;
			default:
				console.log( 'bad status was passed: ' + status );
				break;
		}
	}

	async function send_new_backup_request( nonce, description ) {
		let url = calmBackupData.rest_end_point;
		fetch(
			url,
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				credentials: 'same-origin',
				body: JSON.stringify(
					{
						description : description,
					}
				),
			}
		)
		.then( response =>
			{
				if ( 200 != response.status ) {
					throw new Error( 'Server rejected request' );
				}
				return response.json();
			}
		)
		.then( data =>
			{
				switch ( data.status ) {
					case 'complete':
						update_status_notification( 'success', '' );
						break;
					case 'incomplete':
						// Not finished, send another rfequest.
						send_new_backup_request( nonce, description );
						break;
					case 'failed' :
						update_status_notification( 'backup_failed', data.message );
						break;
					default:
						throw new Error( 'Server send unknow state ' + data.status );
				}
			}
		)
		.catch( error =>
			{
				update_status_notification( 'failure', error.message );
			}
		)
	}

	$( 'form' )

	/**
	 * Turn a submition into ajax request
	 *
	 * @param {object} event The event
	 */
	.on( 'submit', function ( event ) {
		event.preventDefault();
		let nonce = document.getElementById( '_wpnonce' ).value;
		let description = document.getElementById( 'description' ).value;
		update_status_notification( 'in_progress' );
		send_new_backup_request( nonce, description );
		description = document.getElementById( 'submit' ).setAttribute( 'disabled', 'disabled' );
	} )

} );
