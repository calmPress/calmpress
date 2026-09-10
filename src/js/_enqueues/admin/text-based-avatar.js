/**
 * Updates text-based avatar previews when their source text changes.
 *
 * @since calmPress 1.0.0
 */
( function() {

/**
 * Calculates the CRC32 value used to select a generated avatar color.
 * Keep this algorithm synchronized with Text_Based_Avatar::attributes().
 *
 * @since calmPress 1.0.0
 *
 * @param {string} value Value from which the checksum is calculated.
 * @return {number} Unsigned CRC32 value.
 */
function calculate_avatar_crc32( value ) {
	const bytes = new TextEncoder().encode( value );
	let checksum = 0xffffffff;

	for ( const byte of bytes ) {
		checksum ^= byte;
		for ( let bit = 0; bit < 8; bit++ ) {
			checksum = ( checksum >>> 1 ) ^ ( 0xedb88320 & -( checksum & 1 ) );
		}
	}

	return ( checksum ^ 0xffffffff ) >>> 0;
}

/**
 * Derives the letters displayed in a generated avatar from source text.
 * Keep this algorithm synchronized with Text_Based_Avatar::avatar_text().
 *
 * @since calmPress 1.0.0
 *
 * @param {string} value Source text.
 * @return {string} Up to three avatar letters.
 */
function text_based_avatar_letters( value ) {
	let parts = value.trim().replace( /[._-]/g, ' ' ).split( ' ' );
	let avatar_text = '';

	parts = parts.filter( Boolean );

	if ( 3 < parts.length ) {
		parts = [ parts[ 0 ], parts[ 1 ], parts[ parts.length - 1 ] ];
	}

	for ( const part of parts ) {
		avatar_text += Array.from( part )[ 0 ];
	}

	return avatar_text.normalize( 'NFKD' ).replace( /[\u0300-\u036f]/g, '' );
}

/**
 * Encodes SVG markup as a base64 data URL.
 *
 * @since calmPress 1.0.0
 *
 * @param {string} svg SVG markup.
 * @return {string} SVG data URL.
 */
function avatar_svg_data_url( svg ) {
	const bytes = new TextEncoder().encode( svg );
	let binary = '';

	for ( const byte of bytes ) {
		binary += String.fromCharCode( byte );
	}

	return 'data:image/svg+xml;base64,' + window.btoa( binary );
}

/**
 * Updates every text-based avatar preview from its configured input.
 * Keep the generated SVG synchronized with Text_Based_Avatar::attributes().
 *
 * @since calmPress 1.0.0
 */
function update_text_based_avatar_previews() {
	const previews = document.querySelectorAll( '[data-text-based-avatar-input]' );

	for ( const preview of previews ) {
		const input = document.getElementById( preview.dataset.textBasedAvatarInput );
		const image = preview.querySelector( 'img' );
		const colors = JSON.parse( preview.dataset.textBasedAvatarColors );

		if ( ! input || ! image || ! colors.length ) {
			continue;
		}

		const avatar_text = text_based_avatar_letters( input.value );
		const color_index = calculate_avatar_crc32( input.value + preview.dataset.textBasedAvatarColorFactor ) % colors.length;
		const escaped_text = avatar_text.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
		const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><text x="50%" y="50%" font-size="50" text-anchor="middle" dy=".35em" fill="white" font-family="Arial">' + escaped_text + '</text></svg>';

		image.src = avatar_svg_data_url( svg );
		image.className = ( image.className.replace( /(?:^|\s)av-\d+(?=\s|$)/g, '' ).trim() + ' av-' + color_index ).trim();
		image.style.backgroundColor = colors[ color_index ];
	}
}

/**
 * Connects text-based avatar previews to their source inputs.
 *
 * @since calmPress 1.0.0
 */
function initialize_text_based_avatar_previews() {
	const previews = document.querySelectorAll( '[data-text-based-avatar-input]' );
	const inputs = new Set();

	for ( const preview of previews ) {
		const input = document.getElementById( preview.dataset.textBasedAvatarInput );
		if ( input ) {
			inputs.add( input );
		}
	}

	for ( const input of inputs ) {
		input.addEventListener( 'input', update_text_based_avatar_previews );
	}
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', initialize_text_based_avatar_previews );
} else {
	initialize_text_based_avatar_previews();
}
}() );
