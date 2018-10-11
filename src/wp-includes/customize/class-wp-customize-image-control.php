<?php
/**
 * Customize API: WP_Customize_Image_Control class
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.4.0
 */

/**
 * Customize Image Control class.
 *
 * @since 3.4.0
 *
 * @see WP_Customize_Upload_Control
 */
class WP_Customize_Image_Control extends WP_Customize_Upload_Control {
	public $type = 'image';
	public $mime_type = 'image';

	/**
	 * @since 3.4.2
	 * @deprecated 4.1.0
	 */
	public function prepare_control() {}
}
