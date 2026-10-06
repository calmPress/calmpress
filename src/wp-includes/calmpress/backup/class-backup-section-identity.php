<?php
/**
 * Definition of a backup section identity.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * The storage-independent identity of a backup section.
 *
 * @since 1.0.0
 */
class Backup_Section_Identity {

	/**
	 * Create a section identity.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type     Section category, such as plugin, theme, or options.
	 * @param string $location A type-unique identifier for the section's relative location when restored.
	 * @param string $version  Section version.
	 */
	public function __construct(
		public readonly string $type,
		public readonly string $location,
		public readonly string $version
	) {
	}
}
