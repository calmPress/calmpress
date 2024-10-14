<?php
/**
 * Implementation of a blank avatar.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\avatar;
use calmpress\observer\Static_Mutation_Observer_Collection;

/**
 * A representation of a blank avatar which can be used where "proper" avatar could not
 * be displayed. A nicer alternative to returning empty values when an avatar is
 * requested.
 *
 * @since 1.0.0
 */
class Blank_Avatar implements Avatar {
	use Html_Generation_Helper,
		Static_Mutation_Observer_Collection {
		Static_Mutation_Observer_Collection::remove_observer as remove_mutator;
		Static_Mutation_Observer_Collection::remove_observers_of_class as remove_mutator_of_class;
	}

	/**
	 * The attributes to be used in the generated img. By deafult just an empty
	 * src but this can be adjusted by mutators.
	 *
	 * @since 1.0.0
	 *
	 * @param int $size The width and height of the avatar image in pixels.
	 *
	 * @return string[] A map of the attributes.
	 */
	public function attributes( int $size ) : array {

		$attr = [ 'src' => '' ];

		// Allow plugin and themes to override.
		$attr = self::mutate( $attr, $size );
	
		return $attr;
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
		return null;
	}

	/**
	 * Register a mutatur to be called when the img attrinues are generated.
	 *
	 * @since calmPress 1.0.0
	 *
	 * Blank_Avatar_Attributes_Mutator $mutator The object implementing the mutation observer.
	 */
	public static function register_generated_attributes_mutator( Blank_Avatar_Attributes_Mutator $mutator ): void {
		self::add_observer( $mutator );
	}
}
