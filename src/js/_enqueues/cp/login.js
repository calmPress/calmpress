/**
 * Handlers for the login screen
 */

/**
 * If the logo elemnt do not have height, which is the default which can be changed by
 * adding logo image via css, remove the default margin for the element.
 * In that case also make the H1 hidden to creen readers.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	const heading = document.querySelector( '.login h1' );
	const headingLink = heading?.querySelector( 'a' );

	if (headingLink && headingLink.offsetHeight === 0) {
		heading.setAttribute( 'aria-hidden', 'true' );
		headingLink.style.margin = '0';
	}

    // Handle the form type switch buttons.
    const submitButton = document.getElementById( 'wp-submit' );
    const form_type_input = document.getElementById( 'form_type' );
    var buttons = document.querySelectorAll( '#form_switch button' );
    buttons.forEach( function ( button ) {
        button.addEventListener( 'click', function ( event ) {
            const clickedButton = event.currentTarget;
            const text = clickedButton.getAttribute('data-text');
            const password_section = document.querySelector( '.user-pass-wrap' );
            submitButton.value = text;
            clickedButton.className = 'hidden';
            if ( clickedButton.id === 'use_password' ) {
                password_section.className = 'user-pass-wrap';
                document.getElementById( 'use_magiclink' ).className = '';
                form_type_input.value="password";
            }
            if ( clickedButton.id === 'use_magiclink' ) {
                password_section.className = 'user-pass-wrap hidden';
                document.getElementById( 'use_password' ).className = '';
                form_type_input.value="magiclink";
            }
            const errorcont = document.getElementById( 'login_error' );
            errorcont.remove();
        });
    });
});
