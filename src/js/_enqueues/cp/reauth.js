/**
 * API to trigger the reauthentication dialog from JS
 */

window.calmpress = window.calmpress || {};

calmpress.reauth = {
    
    /**
     * Helper for trigger_reauth_dialog which does the actual triggering of the
     * wp-auth-check dialog.
     */
    trigger_helper: function () {
        jQuery(document).trigger( 'heartbeat-tick', [ { 'wp-auth-check':false }, 'heartbeat' ] );
    },

    /**
     * Triggers the display of the login dialog when reauthentication is required.
     * If dom not ready yet waits for it to get loaded first.
     */
    trigger_reauth_dialog: function () {
        if (document.readyState === 'loading') {
            document.addEventListener(
                'DOMContentLoaded', 
                () => {
                    setTimeout( () => {
                        calmpress.reauth.trigger_helper();
                    },
                    0);
                }
            );
        } else {
            calmpress.reauth.trigger_helper();
        }        
    }

};

jQuery(document).on( 'heartbeat-send', function ( event, data ) {
    data.reauth_capabilities = Array.isArray( calmpress.reauth.capabilities ) ? calmpress.reauth.capabilities : [];
});