/**
 * Handlers for the login screen
 */

/**
 * If the logo elemnt do not have height, which is the default which can be changed by
 * adding logo image via css, remove the default margin for the element.
 * In that case also make the H1 hidden to creen readers.
 */
document.addEventListener('DOMContentLoaded', function () {
	const heading = document.querySelector( '.login h1' );
	const headingLink = heading?.querySelector( 'a' );

	if (headingLink && headingLink.offsetHeight === 0) {
		heading.setAttribute( 'aria-hidden', 'true' );
		headingLink.style.margin = '0';
	}
});