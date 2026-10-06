<?php
/**
 * Definition of a committed backup section.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * An immutable, independently stored part of a backup.
 *
 * @since 1.0.0
 */
abstract class Backup_Section {

	/**
	 * The storage-independent identity of the section.
	 *
	 * @since 1.0.0
	 */
	public readonly Backup_Section_Identity $identity;

	/**
	 * The UTC-based Unix timestamp at which the complete section was committed.
	 *
	 * @since 1.0.0
	 */
	public readonly int $creation_time;

	/**
	 * Human-readable name of the section.
	 *
	 * @since 1.0.0
	 */
	private string $display_name;

	/**
	 * Initialize the immutable section identity.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Section_Identity $identity Section identity.
	 * @param int    $creation_time UTC-based Unix timestamp at which the section was committed.
	 * @param string $display_name Human-readable section name.
	 */
	public function __construct( Backup_Section_Identity $identity, int $creation_time, string $display_name ) {
		$this->identity      = $identity;
		$this->creation_time = $creation_time;
		$this->display_name  = $display_name;
	}

	/**
	 * Return the human-readable name of the section.
	 *
	 * @since 1.0.0
	 *
	 * @return string Human-readable section name.
	 */
	public function display_name(): string {
		return $this->display_name;
	}

	/**
	 * Copy the section contents into an empty local directory.
	 *
	 * The fetched contents mirror the directory structure of the section when it was committed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $destination Absolute path of an existing empty destination directory.
	 *
	 * @throws \RuntimeException If the destination is invalid, is not empty, or the section cannot be fetched completely.
	 */
	public function fetch( string $destination ): void {
		if ( ! path_is_absolute( $destination ) ) {
			throw new \RuntimeException( 'The backup section destination must be an absolute path.' );
		}
		$destination = rtrim( $destination, '/\\' );
		if ( ! is_dir( $destination ) || is_link( $destination ) ) {
			throw new \RuntimeException( 'The backup section destination must be a directory.' );
		}
		$destination_iterator = new \FilesystemIterator( $destination, \FilesystemIterator::SKIP_DOTS );
		if ( $destination_iterator->valid() ) {
			throw new \RuntimeException( 'The backup section destination must be empty.' );
		}

		try {
			$this->fetch_files( $destination );
		} catch ( \Throwable $throwable ) {
			try {
				\calmpress\utils\empty_directory( $destination );
			} catch ( \Throwable ) {
				// Cleanup failure must not mask the fetch failure.
			}
			throw new \RuntimeException( 'Failed fetching the backup section.', 0, $throwable );
		}
	}

	/**
	 * Copy the storage-specific section contents into an empty local directory.
	 *
	 * The destination must mirror the directory structure of the section when it was committed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $destination Absolute path of an existing empty destination directory.
	 *
	 * @throws \RuntimeException If the section cannot be fetched completely.
	 */
	abstract protected function fetch_files( string $destination ): void;

	/**
	 * Remove the complete section.
	 *
	 * @since 1.0.0
	 *
	 * @throws \RuntimeException If the section cannot be removed completely.
	 */
	abstract public function remove(): void;
}
