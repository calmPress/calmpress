<?php
/**
 * Implementation of an image based avatar.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\avatar;
use calmpress\observer\Static_Mutation_Observer_Collection;

/**
 * A representation of an avatar which is based on an image stored as an attachment.
 *
 * @since 1.0.0
 */
class Image_Based_Avatar implements Avatar {
	use Html_Generation_Helper,
	Static_Mutation_Observer_Collection {
		Static_Mutation_Observer_Collection::remove_observer as remove_mutator;
		Static_Mutation_Observer_Collection::remove_observers_of_class as remove_mutator_of_class;
	}

	/**
	 * The attachment storing information on the avatar image.
	 *
	 * @var \WP_Post
	 *
	 * @since 1.0.0
	 */
	private \WP_Post $attachment;

	/**
	 * Construct the avatar object based on an attachment.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $attachment The attachment.
	 *
	 * @throws \RunTimeException If $attachment is not an attachment.
	 */
	public function __construct( \WP_Post $attachment ) {
		if ( $attachment->post_type !== 'attachment' ) {
			throw new \RuntimeException(
				'An attachment was expected but ' . $attachment->post_type .' is given',
			);
		}
		
		$this->attachment = $attachment;
	}

	/**
	 * Generate an attributes to be used in the generation of the img tag. Expected
	 * to be called from the html() method of the Html_Generation_Helper trait.
	 * 
	 * Attributes with special treatment:
	 * class  - Should contain additional classes to be added with the core onse (
	 *          avatar, avatar-{size}, photo ). Optional.
	 * src    - The image URI. Mandatory.
	 * alt    - if a meaningful alt attribute is required. Optional.
	 * size   - A shortcut to override the height and width attribue. Not included
	 *          by the HTML otherwise, only as part of a class. Mandatoty
	 * widht  - Ignored
	 * height - Ignored.
	 * 
	 *
	 * In case the attachment do not correspond to an actual image, an HTML of a
	 * blank avatar is retrieved.
	 *
	 * @since 1.0.0
	 *
	 * @param int $size The width and height of the avatar image in pixels.
	 *
	 * @return string[] A map of an attribute to its value.
	 */
	public function attributes( int $size ) : array {
		$attachment_id = $this->attachment->ID;

		/*
		 * Following code is mostly taken from wp_get_attachment_image which
		 * is not called directly to avoid triggering the filters.
		 */

		$image = wp_get_attachment_image_src( $attachment_id, [ $size, $size ], false );

		// If it is impossible to get the image URL return empty avatar.
		if ( ! $image ) {
			$avatar = new Blank_Avatar();
			return $avatar->attributes( $size );
		}

		list($src, $w, $h) = $image;
		$attr['src']       = $src;
		// get srcset related attributes.
		$image_meta = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $image_meta ) ) {
			$size_array = [ $w, $h ];
			$srcset     = wp_calculate_image_srcset( $size_array, $src, $image_meta, $attachment_id );
			if ( $srcset ) {
				$sizes = wp_calculate_image_sizes( $size_array, $src, $image_meta, $attachment_id );
				if ( $sizes ) {
					$attr['srcset'] = $srcset;
					$attr['sizes']  = $sizes;
				}
			}
		}

		// Allow plugin and themes to override.
		$attr = self::mutate( $attr, $this->attachment, $size );

		return $attr;
	}

	/**
	 * Implementation of the attachment method of the Avatar interface which
	 * returns null as the blank avatar can not be configured by user.
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_Post The attachment which is associated with the avatar.
	 */
	public function attachment() {
		return $this->attachment;
	}

	/**
	 * Register a mutatur to be called when the IMG tag attributes are generated.
	 *
	 * @since calmPress 1.0.0
	 *
	 * Image_Based_Avatar_Attributes_Mutator $mutator The object implementing the mutation observer.
	 */
	public static function register_generated_attributes_mutator( Image_Based_Avatar_Attributes_Mutator $mutator ): void {
		self::add_observer( $mutator );
	}
}
