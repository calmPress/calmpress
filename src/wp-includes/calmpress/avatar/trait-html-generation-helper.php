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
trait Html_Generation_Helper {

	/**
	 * Helper to validate the parameters in a consistent way while delegating
	 * the actual HTML generation to the abstract _html method.
	 * $width and $height are checked to be positive, and if they are not an error
	 * is logged and an empty string returned.
	 *
	 * @since 1.0.0
	 *
	 * @param int $size The width and height of the avatar image in pixels.
	 *
	 * @return string An HTML which will be rendered as a blank rectangle of the
	 *                requested dimensions. In case of a validation error an empty
	 *                string.
	 */
	public function html( int $size ) : string {

		$use_blank = false; // Used to indicated that a blank avatar should be used
		                    // because of an error.
		if ( $size < 1 ) {
			trigger_error( 'size has to have a positive value ' . $size . ' was given', E_USER_WARNING );
			$use_blank = true;
		}
		
		/*
		 * Get the attributes to be added to the generated img tag.
		 * 
		 * Attributes with special treatment:
		 * class  - Should contain additional classes to be added with the core onse (
		 *          avatar, avatar-{size}, photo ). Optional.
		 * src    - The image URI. Mandatory.
		 * alt    - if a meaningful alt attribute is required. Optional.
		 * widht  - Ignored
		 * height - Ignored.
		 */
		$attr = $this->attributes( $size );
		if ( ! isset( $attr['src'] ) ) {
			trigger_error( 'src URI was not given', E_USER_WARNING );
			$use_blank = true;
		}
		if ( $use_blank ) {
			$blank = new Blank_Avatar();
			// If there is an error in generation of blank avatar this is going
			// to cause recursion and stack overflow, but nothing more intelegent to
			// do here.
			return $blank->html( $size );
		}
		
		$attr['height'] = $size;
		$attr['width']  = $size;
		if ( ! isset( $attr['alt'] ) ) {
			$attr['alt'] = '';
		}

		// Mimic the inclusion of the avatar class to be compatible with the
		// classes added by get_avatar for code flows that do not use it.
		if ( isset( $attr['class'] ) ) {
			$attr['class'] = 'avatar ' . $attr['class'];
		} else {
			$attr['class'] = 'avatar';
		}
		\calmpress\utils\enqueue_avatar_inline_style();

		$html = '<img';
		foreach ( $attr as $name => $value ) {
			$html .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}
		$html .= '>';
		return $html;
	}

	/**
	 * Abstract function called by the html method that provides the attribute required to
	 * display the avatar and can assume parameters are validated.
	 *
	 * @since 1.0.0
	 *
	 * @param int $size The width and height of the avatar image in pixels.
	 *
	 * @return string[] A map of attributes to their values used in the generated img
	 *                  Attributes with special treatment:
	 *                  class  - Should contain additional classes to be added with the core onse (
	 *                           avatar, avatar-{size}, photo ). Optional.
	 *                  src    - The image URI. Mandatory.
	 *                  alt    - if a meaningful alt attribute is required. Optional.
	 *                  widht  - Ignored
	 *                  height - Ignored.
	 */
	public abstract function attributes( int $size ) : array;
}
