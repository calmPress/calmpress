jQuery(document).ready( function( $ ) {
     
    /**
     * Triggers the display of the login dialog when reauthentication is required.
     */
    function trigger_reauth_dialog() {
    
        // Trigger the wp-auth-check dialog
        jQuery(document).trigger('heartbeat-tick', [{'wp-auth-check':false}, 'heartbeat']);
    }

    /**
     * Trigger the authentication dialog when page load is completed.
     */
    trigger_reauth_dialog();
});