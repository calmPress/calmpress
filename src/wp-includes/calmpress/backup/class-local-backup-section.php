<?php
/**
 * Local backup section implementation.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * A committed section stored as a local directory tree.
 *
 * @since 1.0.0
 */
class Local_Backup_Section extends Backup_Section {

	/**
	 * Absolute path of the committed section directory.
	 *
	 * @since 1.0.0
	 */
	private string $directory;

	/**
	 * Absolute path of the section metadata file.
	 *
	 * @since 1.0.0
	 */
	private string $metadata_file;

	/**
	 * Create a committed local section.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Section_Identity $identity Section identity.
	 * @param int    $creation_time UTC-based Unix timestamp at commit.
	 * @param string $display_name Human-readable section name.
	 * @param string $directory     Absolute path of the committed section directory.
	 * @param string $metadata_file Absolute path of the section metadata file.
	 *
	 * @throws \RuntimeException If the content directory or metadata file does not exist.
	 */
	public function __construct( Backup_Section_Identity $identity, int $creation_time, string $display_name, string $directory, string $metadata_file ) {
		if ( ! is_dir( $directory ) ) {
			throw new \RuntimeException( 'The backup section content directory does not exist.' );
		}
		if ( ! is_file( $metadata_file ) ) {
			throw new \RuntimeException( 'The backup section metadata file does not exist.' );
		}
		parent::__construct( $identity, $creation_time, $display_name );
		$this->directory     = $directory;
		$this->metadata_file = $metadata_file;
	}

	/**
	 * Copy the committed directory tree into an empty directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $destination Absolute path of an existing empty destination directory.
	 *
	 * @throws \RuntimeException If the section cannot be fetched completely.
	 */
	protected function fetch_files( string $destination ): void {
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $this->directory, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ( $iterator as $item ) {
			$relative_path = substr( $item->getPathname(), strlen( $this->directory ) + 1 );
			if ( $item->isLink() ) {
				throw new \RuntimeException( 'A backup section cannot contain symbolic links.' );
			}
			$target = $destination . '/' . $relative_path;
			if ( $item->isDir() ) {
				\calmpress\utils\ensure_dir_exists( $target );
			} elseif ( ! @copy( $item->getPathname(), $target ) ) {
				throw new \RuntimeException( 'Failed copying a fetched backup section file.' );
			}
		}
	}

	/**
	 * Remove the complete committed directory tree.
	 *
	 * @since 1.0.0
	 *
	 * @throws \RuntimeException If the section cannot be removed completely.
	 */
	public function remove(): void {
		if ( ! @unlink( $this->metadata_file ) ) {
			throw new \RuntimeException( 'Failed deleting backup section metadata.' );
		}
		\calmpress\utils\delete_directory( $this->directory );
	}
}
