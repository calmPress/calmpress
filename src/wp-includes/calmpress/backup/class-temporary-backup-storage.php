<?php
/**
 * Interface specification of a virtual temporary backup storage.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * An abstract representation of a temporary backup storage medium used for storing files while
 * backuping is in progress with the intetion of "commiting" it to the main storage once applicable.
 * 
 * It is assumed that such storages start at a "blank" state with files being added to them, without a need
 * for any explicit way to modify or delete them.
 * 
 * Implementations should take notice that the store method assume that the storage and the location
 * to which to "commit" the files are already known at the time of the call.
 *
 * Implementations should make sure that different objects do not share same "physical" storage area.
 * For example, if a local disk is used to store files in a directory hierarchy, the root directory
 * should be different for different objects.
 *
 * @since 1.0.0
 */
abstract class Temporary_Backup_Storage {

	/**
	 * Indicates if the temporary storage was already stored ("commited").
	 * 
	 * @var bool
	 *
	 * @since 1.0.0
	 */
	private bool $stored = false;

	/**
	 * Copy a local file to the storage at a specific location (URI).
	 *
	 * It is the responsability of the implementation to create "directories" or any other meta
	 * information whenever needed.
	 * 
	 * If object is already stored (store method was called) nothing will be done,
	 * but an error will be logged.
	 *
	 * @since 1.0.0
	 *
	 * @param string $source   The absolute path (or URI) to the file to copy.
	 * @param string $dest_uri The copied file's path relative to temporary storage root.
	 *
	 * @throws \Exception If the copy fails.
	 */
	final public function copy_file( string $source, string $dest_uri ) {
		if ( $this->stored ) {
			trigger_error( 'Attempting to add to a temporary storage after it was commited' );
			return;
		}

		$this->copy_file_implementation( $source, $dest_uri );
	}

	/**
	 * Create/overwrite a file with a specific content 
	 *
	 * It is the responsability of the implementation to create "directories" or any other meta
	 * information whenever needed.
	 * 
	 * If object is already stored (store method was called) nothing will be done,
	 * but an error will be logged.
	 *
	 * @since 1.0.0
	 *
	 * @param string $dest_uri The created file's path relative to temporary storage root.
	 * @param string $content  The content to write to the file.
	 *
	 * @throws \Exception If the file creation fails.
	 */
	final public function file_put_contents( string $dest_uri, string $content ) {
		if ( $this->stored ) {
			trigger_error( 'Attempting to add to a temporary storage after it was commited' );
			return;
		}

		$this->file_put_contents_implementation( $dest_uri, $content );
	}

	/**
	 * Transfer all the temporary files into the actual storage. Once transfer is finished perform a cleanup.
	 *
	 * @since 1.0.0
	 *
	 * @throws \Exception If the transfer fails. 
	 */
	final public function store() {

		// If it is a second attempt at performing a store, it is probably an error, but as long
		// as no other files where added in between (which will generate an error), no harm was done
		// and can be treated as a nope.
		if ( $this->stored ) {
			return;
		}

		$this->store_to_storage();

		$this->cleanup();
	}

	/**
	 * On object destruction try to cleanup the files.
	 */
	final public function __destruct() {
		if ( ! $this->stored ) {
			$this->cleanup();
		}
	}

	/**
	 * Copy a local file to the storage at a specific location (URI).
	 *
	 * Should raise an exception on failure of any type. The exception's message should be translatable
	 * wherever possible as it will most likely be presented to the user.
	 *
	 * The implementation should create "directories" or any other meta
	 * information whenever needed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $source   The absolute path (or URI) to the file to copy.
	 * @param string $dest_uri The copied file's path relative to temporary storage root.
	 *
	 * @throws \Exception If the copy fails. 
	 */
	abstract protected function copy_file_implementation( string $source, string $dest_uri );

	/**
	 * Create/overwrite a file with a specific content 
	 *
	 * It is the responsability of the implementation to create "directories" or any other meta
	 * information whenever needed.
	 * 
	 * @since 1.0.0
	 *
	 * @param string $dest_uri The created file's path relative to temporary storage root.
	 * @param string $content  The content to write to the file.
	 *
	 * @throws \Exception If the file creation fails.
	 */
	abstract protected function file_put_contents_implementation( string $dest_uri, string $content );

	/**
	 * Implement the transfer of temporary file to the actual storage.
	 *
	 * @since 1.0.0
	 *
	 * @throws \Exception If transfer fails.
	 */
	abstract protected function store_to_storage();

	/**
	 * Remove any temporary files or other information generated during the life of the object.
	 *
	 * Should not throw exceptions.
	 *
	 * @since 1.0.0
	 */
	abstract protected function cleanup();
}
