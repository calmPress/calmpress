<?php
/**
 * Implementation of a comment syntax help items ollection
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\comments;

/**
 * A representation of the collection of lines making up the syntax help.
 * 
 * A manipulation of the object is allowed only in mutators called as part
 * of the object's construction.
 * 
 * @since 1.0.0
 */
class Comment_Syntax_Help_Item_Collection {
	use \calmpress\observer\Static_Mutation_By_Ref_Observer_Collection;

	/**
	 * An array in which the colletion is kept sorted by first line
	 * that should be output to the last line.
	 * The key is the item's id.
	 *
	 * @since 1.0.0
	 * 
	 * @var Comment_Syntax_Help_Item[]
	 */
	private array $items = [];

	/**
	 * Indicate if constrution is complete
	 * 
	 * @since 1.0.0
	 */
	private bool $instantiated = false;

	/**
	 * Create a Comment Syntax Help collection object.
	 * 
	 * @since 1.0.0
	 * 
	 * @param Comment_Syntax_Help_Item ...$items The items with which to initiate the collection
	 *                                 oredered by first the top line and last the last line.
	 * 
	 * @throws LogicException if $items comtain objets with same id.
	 */
	public function __construct( Comment_Syntax_Help_Item ...$items ) {
		foreach ( $items as $item ) {
			if ( isset( $this->items[ $item->id ] ) ) {
				throw new \LogicException(
					'Trying to add items with same id ' . $item->id
				);
			}

			$this->items[ $item->id ] = $item;
		}

		self::mutate_by_ref( $this );

		$this->instantiated = true;
	}

	/**
	 * All the items in the collection returned as an array sorted with first line first
	 * and last line last.
	 * 
	 * @since 1.0.0
	 * 
	 * @return iterable<Comment_Syntax_Help_Item> The items returned from "top" to
	 *                                            "bottom".
	 */
	public function all_items():iterable {
		foreach ( $this->items as $item ) {
        	yield $item;
    	}
	}

	/**
	 * Add items to the top of the collection.
	 * 
	 * @since 1.0.0
	 * 
	 * @param Comment_Syntax_Help_Item $item The item to add to the collection
	 *                                 at it top.
     *
	 * @param Comment_Syntax_Help_Item ...$items The items to add to the collection
	 *                                 at it top after $item, oredered by first the top line and last the last line.
	 */
	public function add_items_at_top(
		Comment_Syntax_Help_Item $item,
		Comment_Syntax_Help_Item ...$items
	):void {

		$this->throw_if_instantiated();

		$this->throw_if_not_unique( $item, ...$items );

		$items = [ $item, ...$items ];

		$prefix = [];
		foreach ( $items as $item ) {
			$prefix[ $item->id ] = $item;
		}

		$this->items = $prefix + $this->items;
	}

	/**
	 * Add items to the bottom of the collection.
	 * 
	 * @since 1.0.0
	 * 
	 * @param Comment_Syntax_Help_Item $item The item to add to the collection
	 *                                 at it bottom.
     *
	 * @param Comment_Syntax_Help_Item ...$items The additional items to add to the collection
	 *                                 at it bottom after $item, oredered by the first being the 
	 *                                 top most line and the last the bottom line.
	 */
	public function add_items_at_bottom(
		Comment_Syntax_Help_Item $item,
		Comment_Syntax_Help_Item ...$items
	):void {
		$this->throw_if_instantiated();

		$this->throw_if_not_unique( $item, ...$items );

		$this->items[ $item->id ] = $item;

		foreach ( $items as $item ) {
			$this->items[ $item->id ] = $item;
		}
	}

	/**
	 * Remove items from the collection based on their id.
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $id     The id of an item to remove.
	 * @param string ...$ids The id(s) of additional items to remove.
	 * 
	 * @throws LogicException If the collection already instansiated.
	 */
	public function remove_items( string $id, string ...$ids ):void {
		$this->throw_if_instantiated();

		unset( $this->items[ $id ] );

		foreach ( $ids as $id ) {
			unset( $this->items[ $id ] );
		}
	}

	/**
	 * Check if a specific id is of an item in the collection
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $id The id to check for.
	 * 
	 * @return bool true if the $id is of an element in the collection,
	 *              otherwise false.
	 */
	public function id_exists( string $id ):bool {
		return array_key_exists( $id, $this->items );
	}

	/**
	 * Helper to throw an exception if an an item in a list has an id already part of
	 * of item already part the collection.
	 * 
	 * @since 1.0.0
	 * 
	 * @throws LogicException If one of the items has an id which belongs to an item
	 *                        already in the colletion.
	 */
	private function throw_if_not_unique( Comment_Syntax_Help_Item ...$items ): void
	{
		foreach ( $items as $item ) {
			if ( $this->id_exists( $item->id ) ) {
				throw new \LogicException(
					sprintf( 'Item with id "%s" already exists in the collection.', $item->id )
				);
			}
		}
	}

	/**
	 * Helper to throw an exception if an id is already part of
	 * the collection
	 * 
	 * @since 1.0.0
	 * 
	 * @throws LogicException if collection was already instantiated.
	 */
	private function throw_if_instantiated(): void {
		if ( $this->instantiated ) {
			throw new \LogicException(
				'You can not change a collection after it was instantiated'
			);
		}
	}

	/**
	 * Register a mutatur to be called at the end of construction phase.
	 *
	 * @since 1.0.0
	 *
	 * Comment_Syntax_Help_Item_Collection_Mutator $mutator The object implementing
	 *                                                      the mutation observer.
	 */
	public static function register_mutator(
		Comment_Syntax_Help_Item_Collection_Mutator $mutator
	): void {
		self::add_observer( $mutator );
	}
}
