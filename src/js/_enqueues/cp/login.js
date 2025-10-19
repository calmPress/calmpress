/**
 * Handlers for the login screen
 */

/**
 * If the logo element do not have height, which is the default which can be changed by
 * adding logo image via css, remove the default margin for the element.
 * In that case also make the H1 hidden to creen readers.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	const heading = document.querySelector( '.login h1' );
	const headingLink = heading?.querySelector( 'a' );

    // Disable webauthn if browser do not support it.
	if ( window.PublicKeyCredential ) {
		PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()
			.then( ( available ) => {
				if ( ! available ) {
					cp_$( '.webauthn-separator' ).hide();
					cp_$( '.webauthn' ).hide();
				} 
			})
	}

    if (headingLink && headingLink.offsetHeight === 0) {
		heading.setAttribute( 'aria-hidden', 'true' );
		headingLink.style.margin = '0';
	}

    /**
     * Enable and disable the login and OTP buttons based on value in the
     * email and password input.
     * Log in enable only when both inputs has value
     * OTP enabled when email has value but no password.
     */
    function update_buttons_state() {
        const form = document.getElementById( 'loginform' );
        const submit = document.getElementById( 'wp-submit' );
        const user_login = document.getElementById( 'user_login' );
        const get_otp = document.getElementById( 'get_otp' );
        submit.disabled = ! form.checkValidity();
        get_otp.disabled = ! user_login.checkValidity();
    }

    /**
     * Display a text in the message area.
     * 
     * @param {string} text The text to display.
     */
    function set_message( text ) {
        const errorcont = document.getElementById( 'login_message' );
        errorcont.textContent = text;
    }

    /**
     * Display a text in the error area.
     * 
     * @param {string} text The text to display.
     */
    function set_error( text ) {
        const errorcont = document.getElementById( 'login_error' );
        errorcont.className = '';
        errorcont.textContent = text;
    }

    /**
     * Convert a string which is assumed to be base64url encoded to its original
     * an Uint8Array representation.
     * 
     * @param {string} base64url A string assumed to be a base64url encoded string.
     * 
     * @returns Uint8Array
     */
    function base64urlToUint8Array( base64url ) {
        const padding = '='.repeat( ( 4 - base64url.length % 4 ) % 4 );
        const base64 = ( base64url + padding )
            .replace( /-/g, '+' )
            .replace( /_/g, '/' );
        const rawData = window.atob( base64 );
        return Uint8Array.from( [...rawData].map( c => c.charCodeAt( 0 ) ) );
    }

    /**
     * Convert an ArrayBuffer (or TypedArray) -> base64url (no padding).
     *
     * @param {ArrayBuffer|Uint8Array} buffer ArrayBuffer or TypedArray
     * @returns {string} base64url encoded string (RFC4648 with -/_ and no = padding)
     */
    function arrayBufferToBase64Url(buffer) {
        const bytes = buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer);

        // Convert to binary string in chunks to avoid call-stack limits for large buffers.
        let binary = '';
        const chunkSize = 0x8000; // 32KB chunks
        for (let i = 0; i < bytes.length; i += chunkSize) {
            binary += String.fromCharCode.apply(null, bytes.subarray(i, i + chunkSize));
        }

        const base64 = btoa(binary);
        // Convert to base64url (replace +/ with -_ and strip padding)
        return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    update_buttons_state();

    // Check enable/disable submit button state base on form validity
    cp_$( '#loginform' ).on( 'input', () => {
        update_buttons_state();
    });

    cp_$( '#webauthn_button' ).on( 'click', async function ( event ) {
        try {
            const response_data = await calm_fetch.post_no_nonce( 'calmpress/webauthn/login_challenge', {} );
            const options = {
                publicKey: {
                    challenge: base64urlToUint8Array( response_data.challenge ),
                    rpId: response_data.rpId,
                    allowCredentials: response_data.allowCredentials.map( cred => ({
                        id: base64urlToUint8Array( cred.id ),
                        type: cred.type,
                        transports: cred.transports,
                    })),
                    userVerification: response_data.userVerification,
                }
            };
            const credential = await navigator.credentials.get( options );

            const data = {
                credential_id: credential.id,
                clientDataJSON: arrayBufferToBase64Url( credential.response.clientDataJSON ),
                redirect_to: cp_$( '#redirect_to' ).getValue(),
            };

            const login_response = await calm_fetch.post_no_nonce( 'calmpress/webauthn/login', data );
            window.location.href = login_response.redirect_to;
        } catch ( error ) {
            if ( error instanceof calm_fetch_error ) {
                set_error( error.cause_message() );
            } else {
                switch ( error.name ) {
                    case 'NotAllowedError':
                        ; // Most likely the user had canceled.
                        break;
                    default:
                        console.log( error );
                }
            }
        }
    });

    cp_$( '#get_otp' ).on( 'click', async function ( event ) {
        try {
            const email = cp_$( '#user_login' ).getValue();
            const data = {
                email: email
            }
            const response_data = await calm_fetch.post_no_nonce( 'calmpress/send_otp', data );
            set_message( response_data.message );
        } catch ( error ) {
            if ( error instanceof calm_fetch_error ) {
                set_error( error.cause_message() );
            } else {
                switch ( error.name ) {
                    case 'NotAllowedError':
                        ; // Most likely the user had canceled.
                        break;
                    default:
                        console.log( error );
                }
            }
        }
    });
});
