/**
 * Remember and prefill the last submitted FTP settings.
 *
 * @since 1.0.0
 */
( function () {
	'use strict';

	// Name attributes of the FTP fields whose values are saved to localStorage and used for prefilling.
	const names = [
		'calm_ftp_host', 'calm_ftp_port', 'calm_ftp_base',
		'calm_ftp_username', 'calm_ftp_password'
	];

	document.querySelectorAll( '[data-credentials-type="ftp"]' ).forEach( function ( container ) {
		new Credentials_Storage( container, 'ftp', names );
	} );
}() );
