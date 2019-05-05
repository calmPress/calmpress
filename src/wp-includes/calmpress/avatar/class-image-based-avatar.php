<?php
/**
 * Implementation of a blan avatar.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\avatar;

/**
 * A representation of an avatar which is based on an image stored as an attachment.
 *
 * @since 1.0.0
 */
class Image_Based_Avatar implements Avatar {
	use Html_Parameter_Validation;

	/**
	 * The attachment storing information on the avatar image.
	 *
	 * @var \WP_Post
	 *
	 * @since 1.0.0
	 */
	private $attachment;

	/**
	 * Construct the avatar object based on an attachment.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $attachment The attachment.
	 */
	public function __construct( \WP_Post $attachment ) {
		$this->attachment = $attachment;
	}

	/**
	 * An IMG element pointing to the image in the attachment.
	 *
	 * @since 1.0.0
	 *
	 * @param int $width  The width of the avatar image.
	 * @param int $hieght The height of the avatar image.
	 *
	 * @return string The IMG element.
	 */
	protected function _html( int $width, int $height ) : string {
		$attr          = [ 'style' => 'border-radius:50%' ];
		$attachment_id = $this->attachment->ID;

		/*
		 * Following code is mostly taken from wp_get_attachment_image which
		 * is not called directly to avoid triggering the filters.
		 */

		$image = wp_get_attachment_image_src( $attachment_id, [ $width, $height ], false );

		// If it is impossible to get the image URL return empty avatar.
		if ( ! $image ) {
			$avatar = new Blank_Avatar();
			return $avatar->html( $width, $height );
		}

		list($src, $w, $h) = $image;

		// get srcset related attributes.
		$image_meta = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $image_meta ) ) {
			$size_array = [ $w, $h ] ;
        	$srcset     = wp_calculate_image_srcset( $size_array, $src, $image_meta, $attachment_id );
			if ( $srcset ) {
        		$sizes = wp_calculate_image_sizes( $size_array, $src, $image_meta, $attachment_id );
				if ( $sizes ) {
					$attr['srcset'] = $srcset;
					$attr['sizes'] = $sizes;
				}
			}
		}

		$attr_str = array_map( 'esc_attr', $attr );
		$html = rtrim( "<img alt='' width='$width' height='$height'" );
        foreach ( $attr as $name => $value ) {
            $html .= " $name=" . '"' . $value . '"';
        }
        $html .= '>';

		/**
		 * Filters the generated image avatar.
		 *
		 * @since 1.0.0
		 *
		 * @param string The HTML of the avatar.
		 * @param int    The ID of the image attachment.
		 * @param int    The width of the avatar.
		 * @param int    The height of the avatar.
		 */
		return apply_filters( 'calm_image_based_avatar_html', $html, $attachment_id, $width, $height );
	}

	/**
	 * Implementation of the attachment method of the Avatar interface which
	 * returns null as the blank avatar can not be configured by user.
	 *
	 * @since 1.0.0
	 *
	 * @return null Indicates no attachment is associated with the avatar.
	 */
	public function attachment() {
		return $this->attachment;
	}
}
