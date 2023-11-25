<?php
/**
 * Declaration of a interface that email mutators have to implement
 *
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * Decleration of a "by ref" mutator observer that can mutate Email objects.
 *
 * @since 1.0.0
 */
interface Email_Mutator extends \calmpress\observer\Observer {

	/**
	 * Generate (override or leave) the HTML that is generated for a blank avatar.
	 *
	 * @since 1.0.0
	 *
	 * @param Email $email The email object to mutate.
	 */
	public function mutate_by_ref( Email &$mutator ): void;
}