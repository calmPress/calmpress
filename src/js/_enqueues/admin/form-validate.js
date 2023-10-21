/**
 * Self contained functionality to trigger and do form input validation.
 *
 * forms should have a class of "calm-validate" and inputs should have an 
 * "validate" attribute which contains the type of validation handling, for now
 * only value supported is "pattern"
 * 
 * The attribute "validate-on" control the UX of the validation,
 * two type are available, "input" which validate on each new input and 
 * "focusout" which validate afte input loses focus and for which input is valid
 * while focus is on it.
 * 
 * The value in the attribute "validation-pattern" should contain the regexp
 * against which validation is done.
 *
 * The value in the "validation-failue-class" attribute should be the class that
 * will be attached to the input element when validation fails.
 */

document.addEventListener( 'DOMContentLoaded', function () {

    /**
     * Validate an input.
     *  
     * @param DOMElement input 
     */
    function validate_change( input ) {
        value = input.value;
        pattern = input.getAttribute( 'validation-pattern' );
        regex = new RegExp( pattern );
        fail_class = input.getAttribute( 'validation-failue-class' );
        if ( regex.test( value ) ) {
            input.classList.remove( fail_class );
        } else {
            input.classList.add( fail_class );
        }
    };

    inputs = document.querySelectorAll( '.calm-validate input[validate]' );
    inputs.forEach( input => {
        validation_trigger = input.getAttribute( 'validate-on' );
        switch ( validation_trigger ) {
            case 'input' :
                validate_change( input );
                input.addEventListener( 'input' , function (e) {
                    validate_change( this );
                });
                break;
            case 'focusout' :
                validate_change( input );
                input.addEventListener( 'blur' , function (e) {
                    validate_change( this );
                });
                input.addEventListener( 'focus' , function (e) {
                    fail_class = input.getAttribute( 'validation-failue-class' );
                    input.classList.remove( fail_class );
                });
                break;
            default:
                console.log( 'Unknown validation trigger: ' + validation_trigger );
        }
    });
});