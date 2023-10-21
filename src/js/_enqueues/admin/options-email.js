/**
 * UX for the gateway type in mail delivery forms with input fields as
 * used in the options-email screen.
 *
 * @since 1.0.0
 */

document.addEventListener( 'DOMContentLoaded', function () {

    element = document.querySelector( '#email_delivery_type' );
    element.addEventListener( 'change' , function (e) {
        smtp = document.querySelector( '#smtp_settings' );
        if ( this.value == 'local' ) {
            smtp.style.display = 'none';
        } else {
            smtp.style.display = 'block';
        }
    });
});