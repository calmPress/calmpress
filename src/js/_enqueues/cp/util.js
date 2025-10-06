/**
 * CCollection of commonly used utils
 */

/**
 * helps to identify that internal code is executed from this files code.
 */
const local_code = Symbol( 'local_code' );

/**
 * calm_fetch is basically a namespace for the wrapper over our fetch api.
 */
const calm_fetch = {

    // Contains the rest api root url. Should be set at html to proper value and 
    // end with a slash.
    rest_root : undefined,

    // Contains the nonce to be used in the requests.
    // Should be set at html to proper value.
    nonce     : undefined,

    /**
     * POST JSON data to a REST endpoint with structured error handling.
     * 
     * @param {string} route The route of the endpoint relative to rest apy root url.
     * @param {object} data  An object containing the data that will be jsonified when
     *                       sent to the endpoint.
     * 
     * @returns promise to a json structure of the content of the response if the
     *          response has 2xx code and it inludes valid json.
     * 
     * @throws If there was a connectivity issue at whih case error.type will be 'network'
     *         or response had a 4xx or 5xx code in which case error.type will be 'http'
     *         and error.body will contain the body of the response.
     *         Will throw json parsing error if the response has valid code but
     *         invalid json data.
     */
    post: async ( route, data ) => {
        if ( ! calm_fetch.rest_root || ! calm_fetch.nonce ) {
            throw new Error( 'calm_fetch was not initialized with rest_root or nonce');
        }

        try {
            const response = await fetch( calm_fetch.rest_root + route, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': calm_fetch.nonce,
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify( data ),
            });

            if ( ! response.ok ) {
                // HTTP error, return structured object, try to extract
                // a message body if its a json string.
                const text = await response.text().catch( () => '' );
                let msg = text;
                try {
                    const json = JSON.parse(text);
                    if ( json && typeof json.message === 'string' ) {
                        msg = json.message;
                    }
                } catch (e) {
                    // leave msg as plain text if JSON parse fails
                }

                throw {
                    type: 'http',
                    status: response.status,
                    statusText: response.statusText,
                    body: text,
                    message: msg
                };
            }

            return await response.json(); // resolves to parsed JSON

        } catch ( err ) {
            // Network error (or JSON parsing error)
            if ( err.type ) throw err; // already structured
            throw { type: 'network', error: err };
        }
    }
};

/**
 * A wrapper arround HTMLElement that provides common methods as they are used in jquery.
 */
class jquery_like_element_wrapper {

    /**
     * Construct an object based on a HTMLElement node or query selector jquery style.
     * 
     * private to the module.
     * 
     * @since 1.0
     * 
     * @param {HTMLElement|string} element   The element selector string or an actual element.
     * @param {symbol}             local_key A symbol which should identify the constructor
     *                                       as being called from inside the file.
     */
    constructor( element, local_key ) {
        if ( local_key !== local_code ) {
            throw new Error( 'jquery_like_element_wrapper constructor is private, use $() instead' );
        }

        this.el = ( element instanceof HTMLElement ) ? element : document.querySelector(element);
        if ( ! this.el ) {
            throw new Error( 'Could not find element for selector ' + element );
        }
    }

    /**
     * Set an event handler. It is possible to specify a selector for the handler to be alled
     * only for elements matching it.
     * 
     * @param {string}   eventType The name of the event to which the handler is associated.
     * @param @param {string|Function} selectorOrHandler
     *                             Either a CSS selector string for delegated event handling,
     *                             or a direct event handler function.
     * @param {function} handler   The handler for the event.
     * 
     * @returns {jquery_like_element_wrapper} The element for easy chaining.
     */
    on( eventType, selectorOrHandler, handler) {
        if (typeof selectorOrHandler === 'function') {
            // Direct binding, no delegation
            this.el.addEventListener( eventType, selectorOrHandler );
        } else if (typeof selectorOrHandler === 'string' && typeof handler === 'function') {
            // Delegated binding
            this.el.addEventListener( eventType, e => {
                if ( e.target.matches( selectorOrHandler ) ) {
                    handler.call( e.target, e );
                }
            });
        }

        return this;
    }
    /**
     * Visualy show an element by manipulating its display style.
     * 
     * @returns {jquery_like_element_wrapper} The element for easy chaining.
     */
    show() {
        if ( this.el.tagName == 'SPAN' ) {
            this.el.style.display = 'inline';
        } else if ( this.el.tagName == 'BUTTON' ) {
             this.el.style.display = 'inline-block';
        } else {
            this.el.style.display = 'block';
        }

        return this;
    }

    /**
     * Visually hide an element by manipulating its display style.
     * 
     * @returns {jquery_like_element_wrapper} The element for easy chaining.
     */
    hide() {
        this.el.style.display = 'none';
        return this;
    }

    /**
     * Emable an input.
     * 
     * @returns {jquery_like_element_wrapper} The element for easy chaining.
     * 
     * @throws if the element can not be enabled (do not support disabled property).
     */
    enable() {
        if ( ! ( 'disabled' in this.el ) ) {
            throw new Error( 'Element can not be enabled.' );
        }
        this.el.disabled = false;
        return this;
    }

    /**
     * Disable an input.
     * 
     * @returns {jquery_like_element_wrapper} The element for easy chaining.
     * 
     * @throws if the element can not be disabled (do not support disabled property).
     */
    disable() {
        if ( ! ( 'disabled' in this.el ) ) {
            throw new Error( 'Element can not be disabled.' );
        }
        this.el.disabled = true;
        return this;
    }

    /**
     * Add class(es) to the current classes the element has.
     * 
     * @param {string|string[]} classes The classes to add. If its an array
     *                                  each string in the array will be added.
     *                                  If a string, multiple classes can be indicated
     *                                  by separating them by a space.
     * 
     * @returns {jquery_like_element_wrapper} The element for easy chaining.
     */
    addClass( classes ) {
        if ( ! Array.isArray( classes ) ) {
            classes = classes.split(' ');
        }

        this.el.classList.add(...classes);
        return this;
    }

    /**
     * Remove class(es) from the current classes the element has.
     * 
     * @param {string|string[]} classes The classes to remove. If its an array
     *                                  each string in the array will be removed.
     *                                  If a string, multiple classes can be indicated
     *                                  by separating them by a space.
     * 
     * @returns {jquery_like_element_wrapper} The element for easy chaining.
     */
    removeClass( classes ) {
        if ( ! Array.isArray( classes ) ) {
            classes = classes.split(' ');
        }

        this.el.classList.remove(...classes);
        return this;
    }

    /**
     * Checks if an element has a specific class.
     * 
     * @param {string} return this.el.classList.contains(className); The name of the class to check for.
     * 
     * @returns {bool} true if the element has the class, false otherwise.
     */
    hasClass( className ) {
        return this.el.classList.contains( className );
    }

    /**
     * The value of an input element.
     * 
     * @return {string} The value of the input.
     *  
     * @throws if the element is not an input (do not support value property).
     */
    getValue() {
        if ( ! ('value' in this.el ) ) {
            throw new Error( 'Element does not support value' );
        }
        return this.el.value;
    }

    /**
     * Set the value of an input element.
     * 
     * @param {string} value The value to be set in the input.
     *  
     * @throws if the element is not an input (do not support value property).
     */
    setValue( value ) {
        if ( ! ( 'value' in this.el ) ) {
            throw new Error( 'Element does not support value' );
        }
        this.el.value = value;
    }

    /**
     * Find the first element in the subtree of this element matching a selector.
     * 
     * @param {string} selector The selector string to match.
     * 
     * @return {jquery_like_element_wrapper|null} The element requested or null if do not exist.
     */
    find( selector ) {
        const el = this.el.querySelector( selector );
        if ( ! el ) {
            return null;
        }

        return new jquery_like_element_wrapper( el, local_code );
    }

    /**
     * Find all elements matching a selector in the subtree of this element.
     *
     * @param {string} selector A selector to match against.
     * 
     * @return {jquery_like_element_wrapper[]} An array of matching elements.
     */
    findAll( selector ) {
        return Array.from( this.el.querySelectorAll( selector ) )
            .map( el => new jquery_like_element_wrapper( el, local_code ) );
    }

    /**
     * Find the parent of the element.
     * 
     * @return {jquery_like_element_wrapper|null} The parent of the element or null if do not exist.
     */
    parent() {
        if ( ! this.el.parentElement ) {
            return null;
        }
        return new jquery_like_element_wrapper( this.el.parentElement, local_code );
    }

    /**
     * Find the "closest" (first) parent of the element which match a seletor.
     * Might be the element itself.
     * 
     * @param {string} selector The seletor the parent element should match.
     * 
     * @returns {jquery_like_element_wrapper|null} The matching element or null if none found.
     */
    closest( seletor ) {
        const node = this.el.closest( seletor );

        if ( ! node ) {
            return null;
        }

        return new jquery_like_element_wrapper( node, local_code );
    }

    /**
     * Get the value of a data- attribute of the element.
     *
     * @param {string} [attr] The name of the attribute. If omitted, returns an object
     *                        containing all data-* attributes and their values.
     *    
     * @returns {string|object} The value of the specified data-* attribute if attr is provided;
     *                          otherwise, an object mapping all data-* attributes to their values.
     * 
     *  @throws {Error} If the specified attribute does not exist.
     */
    data( attr ) {
        if ( attr === undefined ) {
            // Return the full dataset as a plain object
            return { ...this.el.dataset };
        }

        if ( ! ( attr in this.el.dataset )) {
            throw new Error(` Attribute data-"${attr}" does not exist on element.`);
        }

        return this.el.dataset[attr];
    }

    /**
     * Trigger (dispatch) an event for the element
     * 
     * @param {string} event_name 
     */
    trigger( event_name ) {
        this.el.dispatchEvent( new Event( event_name ) );
    }

    /**
     * Get the value of an attribute or all attributes of the element.
     *
     * @param {string|undefined} attr The name of the attribute. If undefined,
     *                                 returns all attributes as an object.
     * @returns {string|Object} The value of the attribute if `attr` specified,
     *                          otherwise an object mapping attribute names to values.
     * @throws {Error} If `attr` is specified but the element does not have that attribute.
     */
    getAttribute( attr ) {
        if ( attr === undefined ) {
            const result = {};
            for (const a of this.el.attributes) {
                result[a.name] = a.value;
            }
            return result;
        }

        if ( ! this.el.hasAttribute( attr ) ) {
            throw new Error( `Element has no attribute '${attr}'` );
        }

        return this.el.getAttribute( attr );
    }

    /**
     * Set a value for an attribute on the element.
     * 
     * @param {*} attr  The name of the attribute.
     * @param {*} value The value to set.
     */
    setAttribute( attr, value ) {
        this.el.setAttribute( attr, value );
    }
}

/**
 * Creates a jquery like object from an htmlelement or the first element matching the
 * selector.
 * 
 * @param {HTMLElement|string} selector The element selector string or an actual element.
 * 
 * @return {jquery_like_element_wrapper|null} The element requested or null if do not exist.
 */
function $( selector ) {
    try {
        return new jquery_like_element_wrapper( selector, local_code );
    } catch ( e ) {
        return null;
    }
}

/**
 * Find all elements matching a CSS selector and return as an array.
 *
 * @param {string} selector A CSS selector string.
 * 
 * @return {jquery_like_element_wrapper[]} An array of matching elements.
 */
function $$( selector ) {
    return Array.from( document.querySelectorAll( selector ) ).map( el => $( el ) );
}
