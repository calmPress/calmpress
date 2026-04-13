document.addEventListener( 'DOMContentLoaded', () => {
  const notices = document.querySelectorAll( '.notice' );

  notices.forEach( notice => {
    const btn = notice.querySelector( '.notice-dismiss' );

    // not all nortices are dismissable.
    if ( btn ) {
      btn.addEventListener( 'click', () => {
        notice_manager.hide( notice.id );
      });
    }

    // Handle auto-dismiss if data-dismiss is set (seonds) and not zero
    // and notice not hidden.
    const auto_dismiss = parseInt( notice.dataset.dismiss, 10 );
    if ( ! notice.hidden && auto_dismiss > 0 ) {
      const timer = setTimeout( () => {
        notice_manager.hide( notice.id );
      }, auto_dismiss * 1000 );
      notice_manager._timers.set( notice.id, timer);
    }
  });
});

const notice_manager = {
    _timers: new Map(), //  Map of notice area ids to the timers controling the
	                     // automatic hiding.

    /**
     * Update or show a notice message.
     * @param {string} id - The ID of the notice element.
     * @param {string} message - Message text or HTML.
     * @param {string} type - The message type - success, error, warning, info.
     */
    show( id, message, type ) {
        const allowed = [ 'success', 'error', 'warning', 'info' ];
        if ( ! allowed.includes( type ) ) {
            console.log( 'Unknown type ' + type );
            return;
        }
        
        const notice = document.getElementById( id );
        if ( ! notice ) {
            console.log( 'unknown id ' +id );
            return;
        }

        const msg = notice.querySelector( 'p' );
        msg.innerHTML = message;

        notice.classList.remove( 'notice-success', 'notice-error', 'notice-warning', 'notice-info', 'notice-hidden' );
        notice.classList.add( `notice-${type}` );

        notice.hidden = false;

        const auto_dismiss = parseInt( notice.dataset.dismiss, 10 );
        if ( auto_dismiss ) {
            const timer = setTimeout( () => this.hide( id ), parseInt( auto_dismiss, 10 ) * 1000 );
            this._timers.set( id, timer );
        }
    },

    /**
     * Hide a notice with a fade-out effect.
     * @param {string} id - The ID of the notice element.
     */
    hide( id ) {
        const notice = document.getElementById( id );
        if ( ! notice ) {
            console.log( 'unknown id ' + id );
            return;
        }
        notice.classList.add( 'notice-fadeout' );
        notice.addEventListener(
          'transitionend',
          () => {
            notice.classList.remove( 'notice-fadeout', 'notice-success', 'notice-error', 'notice-warning', 'notice-info', 'notice-hidden' );
            notice.classList.add( 'notice-hidden' );
            notice.hidden = true;
          }, 
          { once: true }
        );

        this._clear_timer( id );
    },

   	/**
	 * Internal helper to clear a "hidding" timeout for specific notice.
	 * 
	 * @param {string} id The id of the div in which the notice should be displayed.
	 */
    _clear_timer( id ) {
        if ( this._timers.has( id ) ) {
            clearTimeout( this._timers.get( id ) );
            this._timers.delete( id );
        }
    }

}