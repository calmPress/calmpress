<?php
/**
 * Interface specification of the plugin repository class
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\plugin;

/**
 * An abstract representation of a plugin repository.
 *
 * @since 1.0.0
 */
interface Repository {

	/**
	 * Iterate on all the plugins in the repository, optionally starting at a specific location.
	 *
	 * It does not have to, but may be implemented as generator to reduce memory
	 * consumption.
	 *
	 * @since 1.0.0
	 *
	 * @param int $offset Skips the first "$offset" items in the repository.
	 *                    Zero or positive value is expected, a negative one will
	 *                    be treated like a zero.
	 *                    A warning should be triggered if the value is negative.
	 *
	 * @return Plugin The next plugin to iterate on.
	 */
	public function plugins( int $offset ): array;

	/**
	 * Iterate on all the plugins in the repository which match some keywords,
	 * optionally starting at a specific location.
	 *
	 * How keywords are matched to the actual plugin data is up to the implementation
	 * of the repository. Should include a match of title and description but
	 * do not have to limited to it.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $offset Skips the first "$offset" items in the repository.
	 *                         Zero or positive value is expected, a negative one will
	 *                         be treated like a zero.
	 *                         A warning should be triggered if the value is negative.
	 * @param string[] $keywords The keywords to search by.
	 *
	 * @return Plugin The next plugin that match the keywords to iterate on.
	 */
	public function search_by_keywords( int $offset, array $keywords ): \Iterator;

	/**
	 * Search the repository for plugin matching a slug. A full slug match is required.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The slug to search by.
	 *
	 * @return Plugin|null The plugins matching the slug, or null if there is no match.
	 */
	public function search_by_slug( string $slug );
}
