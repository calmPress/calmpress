<?php
/**
 * Implementation of stats holder for opcache.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\opcache;

/**
 * An object of the class holds a sample in specific time (object's creation time) of the opcache stats.
 *
 * @since 1.0.0
 */
class Stats {

	/**
	 * Holds the stats data set at object creation.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private array $stats = [];

	/**
	 * Create a stats object with the stats at the time of creation.
	 *
	 * @since 1.0.0
	 *
	 * @param array $stats. An array structured like what opcache_get_status returns.
	 */
	public function __construct( array $stats ) {
		$this->stats = $stats;
	}

	/**
	 * Get last restart of the opcache in unix seconds.
	 *
	 * @since 1.0.0
	 *
	 * @return int The unix time of the last restart.
	 */
	public function last_restart_time(): int {
		$ret = $this->stats['opcache_statistics']['last_restart_time'];

		// If the value is 0, the opcache did not restart since server restarted therefor
		// fetch the server start time.
		if ( 0 === $ret ) {
			$ret = $this->stats['opcache_statistics']['start_time'];
		}

		return $ret;
	}

	/**
	 * Get total hits.
	 *
	 * @since 1.0.0
	 *
	 * @return int The number of total hits.
	 */
	public function hits(): int {
		return $this->stats['opcache_statistics']['hits'];
	}

	/**
	 * Number of system initiated restarts. Lamps together out of memory and out of cache.
	 *
	 * @since 1.0.0
	 *
	 * @return int The number restarts.
	 */
	public function system_restarts(): int {
		return $this->stats['opcache_statistics']['oom_restarts'] + $this->stats['opcache_statistics']['hash_restarts'];
	}

	/**
	 * Number of initiated restarts via the API.
	 *
	 * @since 1.0.0
	 *
	 * @return int The number restarts.
	 */
	public function external_restarts(): int {
		return $this->stats['opcache_statistics']['manual_restarts'];
	}

	/**
	 * Get miss rate.
	 *
	 * @since 1.0.0
	 *
	 * @return float The miss rate.
	 */
	public function miss_rate(): float {
		return 100.0 - $this->stats['opcache_statistics']['opcache_hit_rate'];
	}

	/**
	 * The precentage of cached keys used out of the max possible number.
	 *
	 * @since 1.0.0
	 *
	 * @return float The precentage.
	 */
	public function cached_keys_usage(): float {
		return $this->stats['opcache_statistics']['num_cached_keys'] / $this->stats['opcache_statistics']['max_cached_keys'] * 100.0;
	}

	/**
	 * The precentage of memory used out of the max possible.
	 *
	 * @since 1.0.0
	 *
	 * @return float The precentage.
	 */
	public function memory_usage(): float {
		$used  = $this->stats['memory_usage']['used_memory'] + $this->stats['memory_usage']['wasted_memory'];
		$total = $used + $this->stats['memory_usage']['free_memory'];
		return $used / $total * 100.0;
	}
}