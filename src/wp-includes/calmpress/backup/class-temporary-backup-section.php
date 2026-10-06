<?php
/**
 * Implementation of a temporary backup section.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * A normal directory tree which becomes immutable when committed by its storage.
 *
 * @since 1.0.0
 */
abstract class Temporary_Backup_Section {

	/**
	 * Section category, such as plugin, theme, or options.
	 *
	 * @since 1.0.0
	 */
	public readonly string $type;

	/**
	 * Type-unique relative restore location.
	 *
	 * @since 1.0.0
	 */
	public readonly string $location;

	/**
	 * Section version.
	 *
	 * @since 1.0.0
	 */
	public readonly string $version;

	/**
	 * Human-readable name of the section.
	 *
	 * @since 1.0.0
	 */
	protected string $display_name;

	/**
	 * Absolute path to the temporary directory tree.
	 *
	 * @since 1.0.0
	 */
	private string $temporary_directory;

	/**
	 * Whether this section was committed.
	 *
	 * @since 1.0.0
	 */
	private bool $committed = false;

	/**
	 * Create a temporary section rooted in a local directory.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Section_Identity $identity     Section identity.
	 * @param string                  $display_name Human-readable section name.
	 *
	 * @throws \RuntimeException If the temporary directory cannot be created.
	 */
	public function __construct( Backup_Section_Identity $identity, string $display_name ) {
		$this->temporary_directory = trailingslashit( get_temp_dir() . 'backup-section-' . wp_generate_uuid4() );
		$this->type                = $identity->type;
		$this->location            = $identity->location;
		$this->version             = $identity->version;
		$this->display_name        = $display_name;
		\calmpress\utils\ensure_dir_exists( $this->temporary_directory );
	}

	/**
	 * Copy a local file into the section directory tree.
	 *
	 * @since 1.0.0
	 *
	 * @param string $source   Source file path.
	 * @param string $dest_uri Path relative to the section root.
	 *
	 * @throws \RuntimeException If the section was committed, a destination directory cannot
	 *                           be created, or the file cannot be copied.
	 */
	public function copy_file( string $source, string $dest_uri ): void {
		$this->throw_if_committed();
		$destination = $this->temporary_directory . ltrim( $dest_uri, '/\\' );
		\calmpress\utils\ensure_dir_exists( dirname( $destination ) );
		if ( ! @copy( $source, $destination ) ) {
			throw new \RuntimeException( 'Failed copying a file into a temporary backup section.' );
		}
	}

	/**
	 * Write content into a file in the section directory tree.
	 *
	 * @since 1.0.0
	 *
	 * @param string $dest_uri Path relative to the section root.
	 * @param string $content  File content.
	 *
	 * @throws \RuntimeException If the section was committed, a destination directory cannot
	 *                           be created, or the file cannot be written completely.
	 */
	public function file_put_contents( string $dest_uri, string $content ): void {
		$this->throw_if_committed();
		$destination = $this->temporary_directory . ltrim( $dest_uri, '/\\' );
		\calmpress\utils\ensure_dir_exists( dirname( $destination ) );
		if ( strlen( $content ) !== @file_put_contents( $destination, $content ) ) {
			throw new \RuntimeException( 'Failed writing a file into a temporary backup section.' );
		}
	}

	/**
	 * Commit the complete section as one immutable storage unit.
	 *
	 * A successful commit makes the section discoverable through its storage and prevents
	 * further changes to this temporary section. A failed commit leaves the temporary files
	 * available for handling or retrying the failure.
	 *
	 * @since 1.0.0
	 *
	 * @throws \RuntimeException If the section was already committed or cannot be committed completely.
	 */
	public function commit(): void {
		$this->throw_if_committed();
		try {
			$this->commit_files( $this->temporary_directory );
		} catch ( \RuntimeException $exception ) {
			throw new \RuntimeException(
				sprintf(
					'Failed committing backup section (type: %s, location: %s, version: %s): %s',
					$this->type,
					$this->location,
					$this->version,
					$exception->getMessage()
				),
					0,
					$exception
			);
		}
		$this->committed = true;
		$this->cleanup();
	}

	/**
	 * Commit the temporary directory using the storage-specific operation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $temporary_directory Absolute path to the complete temporary section tree.
	 *
	 * @throws \RuntimeException If the section cannot be committed completely.
	 */
	abstract protected function commit_files( string $temporary_directory ): void;

	/**
	 * Reject operations which would mutate an already committed section.
	 *
	 * @since 1.0.0
	 *
	 * @throws \RuntimeException If the section was already committed.
	 */
	private function throw_if_committed(): void {
		if ( $this->committed ) {
			throw new \RuntimeException( 'The temporary backup section was already committed.' );
		}
	}

	/**
	 * Remove the temporary directory tree.
	 *
	 * @since 1.0.0
	 */
	private function cleanup(): void {
		if ( ! is_dir( $this->temporary_directory ) ) {
			return;
		}
		try {
			\calmpress\utils\delete_directory( $this->temporary_directory );
		} catch ( \Throwable ) {
			// Cleanup must not hide the result of commit or throw from the destructor.
		}
	}

	/**
	 * Clean an abandoned temporary section.
	 *
	 * @since 1.0.0
	 */
	public function __destruct() {
		$this->cleanup();
	}
}
