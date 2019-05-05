<?php
/**
 * Implementation of parameters validation helper trait.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\avatar;

/**
 * An helper trait used to reduce code replication when validating the parameters
 * of the html method.
 *
 * @since 1.0.0
 */
trait Html_Parameter_Validation {

	/**
	 * Helper to validate the parameters in a consistent way while delegating
	 * the actual HTML generation to the abstract _html method.
	 * $width and $height are checked to be positive, and if they are not an error
	 * is logged and an empty string returned.
	 *
	 * @since 1.0.0
	 *
	 * @param int $width  The width of the avatar image.
	 * @param int $hieght The height of the avatar image.
	 *
	 * @return string An HTML which will be rendered as a blank rectangle of the
	 *                requested dimensions. In case of a validation error an empty
	 *                string.
	 */
	public function html( int $width, int $height ) : string {

		if ( $width < 1 ) {
			trigger_error( 'width has to have a positive value ' . $width . ' was given', E_USER_WARNING );
			return '';
		}

		if ( $height < 1 ) {
			trigger_error( 'height has to have a positive value ' . $height . ' was given', E_USER_WARNING );
			return '';
		}

		return $this->_html( $width, $height );
	}

	/**
	 * Abstract function called by the html method that provides the HTML required to
	 * display the avatar and can assume parameters are validated.
	 *
	 * @since 1.0.0
	 *
	 * @param int $width  The width of the avatar image.
	 * @param int $hieght The height of the avatar image.
	 *
	 * @return string The escaped HTML needed to display the avatar.
	 */
	protected abstract function _html( int $width, int $height ) : string;
}
