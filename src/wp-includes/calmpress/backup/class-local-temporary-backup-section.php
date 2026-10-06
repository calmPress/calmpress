<?php
/**
 * Local temporary backup section implementation.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * A temporary section committed by local storage.
 *
 * @since 1.0.0
 */
class Local_Temporary_Backup_Section extends Temporary_Backup_Section {

	/**
	 * Root directory of the local backup storage.
	 *
	 * @since 1.0.0
	 */
	private string $storage_root;

	/**
	 * Create a temporary section owned by local storage.
	 *
	 * @since 1.0.0
	 *
	 * @param string                  $storage_root Root directory of the local backup storage.
	 * @param Backup_Section_Identity $identity     Section identity.
	 * @param string                  $display_name Human-readable section name.
	 *
	 * @throws \RuntimeException If the temporary directory cannot be created.
	 */
	public function __construct( string $storage_root, Backup_Section_Identity $identity, string $display_name ) {
		parent::__construct( $identity, $display_name );
		$this->storage_root = trailingslashit( $storage_root );
	}

	/**
	 * Move the temporary section into local storage and write its metadata.
	 *
	 * @since 1.0.0
	 *
	 * @param string $temporary_directory Absolute path to the complete temporary section tree.
	 *
	 * @throws \RuntimeException If the section cannot be committed completely.
	 */
	protected function commit_files( string $temporary_directory ): void {
		$committed_at = time();
		$section_path = $this->section_path();
		$destination  = $this->storage_root . $section_path;
		$metadata_dir = $this->storage_root . Local_Backup_Storage::SECTION_META_DIRECTORY;
		$metadata     = $metadata_dir . '/' . wp_generate_uuid4() . '.json';
		$manifest_data = array(
				'type'          => $this->type,
				'location'      => $this->location,
				'version'       => $this->version,
				'display_name'  => $this->display_name,
				'creation_time' => $committed_at,
				'directory'     => $section_path,
			);
		$manifest = wp_json_encode( $manifest_data );
		\calmpress\utils\ensure_dir_exists( dirname( $destination ) );
		\calmpress\utils\ensure_dir_exists( $metadata_dir );

		// A directory left by an earlier commit can still receive its section metadata.
		if ( ! @rename( rtrim( $temporary_directory, '/\\' ), $destination ) ) {
			if ( ! is_dir( $destination ) ) {
				throw new \RuntimeException( 'Failed moving section files: ' . \calmpress\utils\last_error_message() );
			}
		}
		$temporary_metadata = $metadata . '.tmp';
		try {
			if ( strlen( $manifest ) !== @file_put_contents( $temporary_metadata, $manifest ) ) {
				throw new \RuntimeException( 'Failed writing backup section metadata.' );
			}
			if ( ! @rename( $temporary_metadata, $metadata ) ) {
				throw new \RuntimeException( 'Failed moving backup section metadata into place.' );
			}
		} finally {
			@unlink( $temporary_metadata );
		}
	}

	/**
	 * Determine the committed path relative to the local storage root.
	 *
	 * @since 1.0.0
	 *
	 * @return string Relative committed section path.
	 *
	 * @throws \InvalidArgumentException If the section identity cannot form a safe local path.
	 */
	private function section_path(): string {
		switch ( $this->type ) {
			case 'core':
				$path = 'core/' . $this->version;
				break;
			case 'theme':
				$path = 'themes/' . $this->location . '/' . $this->version;
				break;
			case 'plugin':
				$path = 'plugins/' . $this->location . '/' . $this->version;
				break;
			case 'plugin-file':
				$path = 'single-file-plugins/' . $this->location . '/' . $this->version;
				break;
			case 'dropin':
				$path = 'dropins/' . $this->location . '/' . $this->version;
				break;
			case 'options':
				$path = 'db/options/' . $this->location . '/' . $this->version;
				break;
			case 'network-options':
				$path = 'db/network-options/' . $this->location . '/' . $this->version;
				break;
			case 'root-files':
				$path = 'root_directory/' . $this->version;
				break;
			case 'config-file':
				$path = 'config/' . ( '../wp-config.php' === $this->location ? 'parent' : 'root' ) . '/' . $this->version;
				break;
			default:
				$path = $this->type . '/' . $this->version;
				break;
		}
		$path = preg_replace( '#/+#', '/', str_replace( '\\', '/', $path ) );
		$path = trim( $path, '/' );
		if ( '' === $path || str_contains( $path, "\0" ) || preg_match( '#(^|/)\.\.?(?:/|$)#', $path ) || preg_match( '#^[a-zA-Z]:#', $path ) ) {
			throw new \InvalidArgumentException( 'The section identity cannot be represented by local storage.' );
		}

		return $path;
	}
}
