<?php
/**
 * Implementation of the installed plugins repository class.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\plugin;

/**
 * A repository of installed plugins.
 *
 * @since 1.0.0
 */
class Installed_Plugins implements Repository {

	/**
	 * Iterate on all the plugins in the repository, optionally starting at a specific location.
	 *
	 * @since 1.0.0
	 *
	 * @param int $offset Skips the first "$offset" items in the repository.
	 *                    Zero or positive value is expected, a negative one will
	 *                    be treated like a zero and a warning will be generate.
	 *
	 * @return Plugin The next plugin to iterate on.
	 */
	public function plugins( int $offset ) {
		if ( $offset < 0 ) {
			trigger_error( 'Offset of ' . $offset . 'was given, but offset should not be negative', E_USER_WARNING );
			$offset = 0;
		}

		$plugins = get_plugins();

		$i = 0;
		foreach ( $plugin as $k => $v ) {
			$i++;
			if ( $i < $offset ) {
				continue;
			}
			yield new Installed_Plugin( $k, $v );
		}
	}

	/**
	 * Iterate on all the plugins in the repository which match some keywords,
	 * optionally starting at a specific location.
	 *
	 * A match is found when a keyword is a whole word in either the name of the
	 *  plugin or its description.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $offset Skips the first "$offset" items in the repository.
	 *                         Zero or positive value is expected, a negative one will
	 *                         be treated like a zero and a warning will be generate.
	 * @param string[] $keywords The keywords to search by.
	 *
	 * @return Plugin The next plugin that match the keywords to iterate on.
	 */
	public function search_by_keywords( int $offset, array $keywords ) {
		if ( $offset < 0 ) {
			trigger_error( 'Offset of ' . $offset . 'was given, but offset should not be negative', E_USER_WARNING );
			$offset = 0;
		}

		$plugins = get_plugins();

		$i = 0;
		foreach ( $plugin as $k => $v ) {
			$i++;
			if ( $i < $offset ) {
				continue;
			}
			yield new Installed_Plugin( $k, $v );
		}
	}

	/**
	 * Search the repository for plugin matching a slug. A full slug match is required.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The slug to search by.
	 *
	 * @return Plugin|null The plugins matching the slug, or null if there is no match.
	 */
	public function search_by_slug( string $slug ) {
		$plugins = get_plugins();
		foreach ( $plugin as $k => $v ) {
			$dir = dirname( $key );
			if ( $dir === $slug ) {
				return new Installed_Plugin( $k, $v );
			}
		}

		return null;
	}
}
