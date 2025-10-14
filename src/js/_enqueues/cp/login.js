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

	if ( window.PublicKeyCredential ) {
		PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()
			.then( ( available ) => {
				if ( ! available ) {
					hide_webauthn();
				} 
			})
	}

    if (headingLink && headingLink.offsetHeight === 0) {
		heading.setAttribute( 'aria-hidden', 'true' );
		headingLink.style.margin = '0';
	}

    function show_password() {
        const password_section = document.querySelector( '.user-pass-wrap' );
        password_section.className = 'user-pass-wrap';
        password_section.removeAttribute( 'aria-hidden' );
        show_submit();
    }

    function hide_password() {
        const password_section = document.querySelector( '.user-pass-wrap' );
        password_section.className = 'user-pass-wrap hidden';
        password_section.setAttribute( 'aria-hidden', 'true' );
    }

    function show_email() {
        const email_section = document.querySelector( '.user-email-wrap' );
        email_section.className = 'user-email-wrap';
        email_section.setAttribute( 'aria-hidden', 'false' );
        show_submit();
    }

    function hide_email() {
        const email_section = document.querySelector( '.user-email-wrap' );
        email_section.className = 'user-email-wrap hidden';
        email_section.removeAttribute( 'aria-hidden' );
    }

    function show_webauthn() {
        el = document.querySelector( '.webauthn' );
        el.style.display = 'block';
        hide_submit();
    }

    function hide_webauthn() {
        el = document.querySelector( '.webauthn' );
        el.style.display = 'none';
    }

    function show_submit() {
        const submitButton = document.querySelector( '.submit' );
        submitButton.style.display = 'block';
    }

    function hide_submit() {
        const submitButton = document.querySelector( '.submit' );
        submitButton.style.display = 'none';
    }

    function hide_error( text ) {
        const errorcont = document.getElementById( 'login_error' );
        errorcont.className = 'hidden';
        errorcont.textContent = '';
    }

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

    // Handle the form type switch buttons.
    const submitButton = document.getElementById( 'wp-submit' );
    const form_type_input = document.getElementById( 'form_type' );
    const aria_notification = document.getElementById( 'login_aria_notice' );
    var buttons = document.querySelectorAll( '#form_switch button' );
    buttons.forEach( function ( button ) {
        button.addEventListener( 'click', function ( event ) {
            for (const button of buttons) {
                button.className = '';
            }
            const clickedButton = event.currentTarget;
            const text = clickedButton.getAttribute('data-text');
            submitButton.value = text;
            aria_notification.textContent = clickedButton.getAttribute('data-notice');
            clickedButton.className = 'hidden';
            if ( clickedButton.id === 'use_password' ) {
                show_password();
                show_email();
                hide_webauthn();
                form_type_input.value="password";
                aria_notification.textContent = clickedButton.getAttribute('data-notice');
            }
            if ( clickedButton.id === 'use_magiclink' ) {
                hide_password();
                show_email();
                hide_webauthn();
                form_type_input.value="magiclink";
            }
            if ( clickedButton.id === 'use_webauthn' ) {
                hide_email();
                hide_password();
                show_webauthn();
            }

            hide_error();
        });
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
});
