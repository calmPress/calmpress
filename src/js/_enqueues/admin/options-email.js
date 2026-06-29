/**
 * UX for the gateway type in mail delivery forms with input fields as
 * used in the options-email screen.
 *
 * @since 1.0.0
 */

document.addEventListener( 'DOMContentLoaded', function () {

    /**
     * Hide SMTP related inputes when using local gateway.
     * 
     * @since 1.0.0
     */
    function update_smtp() {
        let type = cp_$( '#email_delivery_type' );
        if ( type.value() == 'local' ) {
            cp_$( '#smtp_settings' ).hide();
        } else {
            cp_$( '#smtp_settings' ).show();
        }
    }

    /**
     * Show full sets of inputs, if network setting can be ovveriden or hide them if not.
     * 
     * @since 1.0.0
     */
    function update_override_network() {
        let override = cp_$( '#calm-override_email-delivery-field' ).el.checked;
        cp_$$( '.gateway_settings' ).forEach( ( el ) => {
            if ( override ) {
                el.show();
                update_smtp(); // Sync SMTP state.
            } else {
                el.hide();
                cp_$( '#smtp_settings' ).hide();
            }
        });
    }

	cp_$( '#email_delivery_type' ).on( 'change', update_smtp );
    update_smtp();

    // element exists only on network sites.
    try {
	    cp_$( '#calm-override_email-delivery-field' ).on( 'change', update_override_network );
        update_override_network();
    } catch ( e ) {}
});