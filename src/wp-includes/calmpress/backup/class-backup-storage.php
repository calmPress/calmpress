<?php
/**
 * Interface specification of a virtual backup storage using an incremental backup.
 * This can be a specific location on the hard drive, cloud storage, etc...
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * An abstract representation of a backup storage medium used for incremental backups.
 *
 * @since 1.0.0
 */
abstract class Backup_Storage {

	/**
	 * Human readable description of the storage. Shoiuld not contain HTML (it will be escaped),
	 * and be translated where appropriate.
	 *
	 * @since 1.0.0
	 *
	 * @return string The description text.
	 */
	abstract public function description() : string;

	/**
	 * A unique identifier of the storage. Anything may be used
	 * as long as it is consistant between page reloads.
	 *
	 * @since 1.0.0
	 *
	 * @return string The identifier.
	 */
	abstract public function identifier() : string;

	/**
	 * The committed sections stored in this storage.
	 *
	 * @since 1.0.0
	 *
	 * @return iterable<Backup_Section> Committed sections.
	 */
	abstract public function sections(): iterable;

	/**
	 * Locate a committed section by its structured identity.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Section_Identity $identity Section identity.
	 *
	 * @return ?Backup_Section Matching section, or null when it does not exist.
	 */
	public function section( Backup_Section_Identity $identity ): ?Backup_Section {
		foreach ( $this->sections() as $section ) {
			if ( $identity->type === $section->identity->type && $identity->location === $section->identity->location && $identity->version === $section->identity->version ) {
				return $section;
			}
		}

		return null;
	}

	/**
	 * Create a temporary working area for a new section.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Section_Identity $identity     Section identity.
	 * @param string                  $display_name Human-readable section name.
	 *
	 * @return Temporary_Backup_Section Temporary section.
	 *
	 * @throws Backup_Section_Already_Exists_Exception If the section already exists.
	 */
	public function create_section( Backup_Section_Identity $identity, string $display_name ): Temporary_Backup_Section {
		if ( null !== $this->section( $identity ) ) {
			throw new Backup_Section_Already_Exists_Exception( 'The backup section already exists.' );
		}

		return $this->create_temporary_section( $identity, $display_name );
	}

	/**
	 * Create a temporary working area for an identity not stored by this storage.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Section_Identity $identity     Section identity.
	 * @param string                  $display_name Human-readable section name.
	 *
	 * @return Temporary_Backup_Section Temporary section.
	 */
	abstract protected function create_temporary_section( Backup_Section_Identity $identity, string $display_name ): Temporary_Backup_Section;

	/**
	 * Delete expired sections that no backup references.
	 *
	 * @since 1.0.0
	 *
	 * @param int                       $expiration_time    Unix timestamp before which unreferenced sections may be deleted.
	 * @param Backup_Section_Identity[] $referenced_sections Sections that backups in this storage reference.
	 *
	 * @throws \RuntimeException If a section selected for cleanup cannot be deleted.
	 */
	public function cleanup( int $expiration_time, array $referenced_sections ): void {
		$references = array();
		foreach ( $referenced_sections as $identity ) {
			$references[ $identity->type . "\0" . $identity->location . "\0" . $identity->version ] = true;
		}

		foreach ( $this->sections() as $section ) {
			$identity = $section->identity->type . "\0" . $section->identity->location . "\0" . $section->identity->version;
			if ( $section->creation_time < $expiration_time && ! isset( $references[ $identity ] ) ) {
				$section->remove();
			}
		}
	}
}
