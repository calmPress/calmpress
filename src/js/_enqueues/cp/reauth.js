jQuery(document).ready( function( $ ) {

    let dialog_displayed = false;

    /**
     * Define the handler that will pause heartbeat
     */
	function Pause_Heartbeat_Handler( event ) {
		event.preventDefault();
	}

	// Attach the handler
	function attachHeartbeatPause() {
		$(document).on('heartbeat-send.conditionalPause', pauseHandler);
	}

	// Detach the handler
	function detachHeartbeatPause() {
		$(document).off('heartbeat-send.conditionalPause');
	}
     
    /**
     * Triggers the display of the login dialog when reauthentication is required.
     */
    function trigger_reauth_dialog() {

        // do not try to open the dialog again if its already displayed to avoid
        // race and edge cases

        if ( dialog_displayed ) {
            return;
        }
        dialog_displayed = true;
     
        // Pause heartbeat to prevent duplicates and race conditions.
        $(document).on('heartbeat-send.reauth_pause_handler', Pause_Heartbeat_Handler);

        // Trigger the wp-auth-check dialog
        jQuery(document).trigger('heartbeat-tick', [{'wp-auth-check':false}, 'heartbeat']);

        // Add an event listener to resume Heartbeat after reauthentication
        $( document ).one( 'wp-auth-dialog-closed', function () {
            dialog_displayed = false;
            // Resume the Heartbeat API
            wp.heartbeat.connectNow();
            $(document).off('heartbeat-send.reauth_pause_handler');
        });
    }

    /**
     * Trigger the authentication dialog when page load is completed.
     */
    trigger_reauth_dialog();
});