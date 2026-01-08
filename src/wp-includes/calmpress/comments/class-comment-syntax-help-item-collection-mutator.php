<?php
/**
 * Declaration of a interface that mutators for comment help collections
 * has to implement
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\comments;

/**
 * Decleration of a "by ref" mutator observer that can mutate Comment_Syntax_Help_Item_Collection objects.
 *
 * @since 1.0.0
 */
interface Comment_Syntax_Help_Item_Collection_Mutator extends \calmpress\observer\Observer {

	/**
	 * Adjust an Comment_Syntax_Help_Item_Collection object.
	 *
	 * @since 1.0.0
	 *
	 * @param Comment_Syntax_Help_Item_Collection $collection The collection object to mutate.
	 */
	public function mutate_by_ref( Comment_Syntax_Help_Item_Collection &$collection ): void;
}