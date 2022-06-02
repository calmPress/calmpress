<?php
/**
 * Implementation of an unkown back engine class
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * Implementation of a stub engine to be used when as a unknown/"null" engine.
 *
 * @since 1.0.0
 */
class Unknown_Backup_Engine implements Engine_Specific_Backup {

	/**
	 * The identifier of the engine being stubbed.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	private string $id;

	/**
	 * Construct the stub engine.
	 *
	 * @param string $id The identifier of the engine being stubbed.
	 */
	public function __construc( string $id ) {
		$this->id = $id;
	}

	/**
	 * By interface specification, the data associated with the engine. There is no data
	 * that is meaningful enough to associate with an unknown engine.
	 *
	 * @since 1.0.0
	 *
	 * @return array Always an empty array.
	 */
	public function data(): array {
		return [];
	}

	/**
	 * Implementation of a required interface, does nothing
	 *
	 * @since 1.0.0
	 *
	 * @param string $backup_root The root directory under which backup
	 *                            directories, ignored.
	 * @param int    $max_time    The maximum amount of time in second th backup
	 *                            should last before terminating, ignored.
	 */
	public function backup( string $backup_root, int $max_time ): array {
		return [];
	}

	/**
	 * Implementation of a required interface, does nothing
	 *
	 * @since 1.0.0
	 *
	 */
	public function restore() {
	}

	/**
	 * Human redable description of the engine. Should not contain HTML (it will be escaped),
	 * and be translated where appropriate.
	 *
	 * @since 1.0.0
	 *
	 * @return string The description text.
	 */
	public function description() : string {
		return __( 'An unkown partial backup type (engine): ' . $this->id );
	}

	/**
	 * The unique identiier of the engine being stubbed.
	 *
	 * @since 1.0.0
	 *
	 * @return string The identifier.
	 */
	public function identifier(): string {
		return $this->id;
	}
}