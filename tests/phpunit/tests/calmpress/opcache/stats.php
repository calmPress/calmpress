<?php
/**
 * Unit tests covering file opcache stats functionality.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\opcache\Stats;


/**
 * Tests for the opcache stats class.
 * 
 * @since 1.0.0
 */
class WP_Test_Stats extends WP_UnitTestCase {

	/**
	 * Test hits returns the value given at construction time.
	 *
	 * @since 1.0.0
	 */
	public function test_hits() {
		$stats = new Stats(
			[
				'opcache_statistics' => [
					'hits' => 5683,
				],
			]
		);

		$this->assertSame( 5683, $stats->hits() );
	}

	/**
	 * Test last_restart_time.
	 *
	 * @since 1.0.0
	 */
	public function test_last_restart_time() {

		// Test without a restart
		$stats = new Stats(
			[
				'opcache_statistics' => [
					'start_time'        => 56830,
					'last_restart_time' => 0,
				],
			]
		);

		$this->assertSame( 56830, $stats->last_restart_time() );

		// Test with a restart
		$stats = new Stats(
			[
				'opcache_statistics' => [
					'start_time'        => 56830,
					'last_restart_time' => 167894,
				],
			]
		);

		$this->assertSame( 167894, $stats->last_restart_time() );
	}

	/**
	 * Test system_restarts.
	 *
	 * @since 1.0.0
	 */
	public function test_system_restarts() {
		// Number of restart should be an aggregate of out of memory and out of hash keys.
		$stats = new Stats(
			[
				'opcache_statistics' => [
					'oom_restarts'  => 3,
					'hash_restarts' => 2,
				],
			]
		);

		$this->assertSame( 5, $stats->system_restarts() );
	}

	/**
	 * Test external_restarts.
	 *
	 * @since 1.0.0
	 */
	public function test_external_restarts() {

		$stats = new Stats(
			[
				'opcache_statistics' => [
					'manual_restarts'  => 4,
				],
			]
		);

		$this->assertSame( 4, $stats->external_restarts() );
	}

	/**
	 * Test miss_rate.
	 *
	 * @since 1.0.0
	 */
	public function test_miss_rate() {

		$stats = new Stats(
			[
				'opcache_statistics' => [
					'opcache_hit_rate'  => 4.9,
				],
			]
		);

		$this->assertSame( 95.1, $stats->miss_rate() );
	}

	/**
	 * Test cached_keys_usage.
	 *
	 * @since 1.0.0
	 */
	public function test_cached_keys_usage() {

		$stats = new Stats(
			[
				'opcache_statistics' => [
					'num_cached_keys' => 49,
					'max_cached_keys' => 100, 
				],
			]
		);

		$this->assertSame( 49.0, $stats->cached_keys_usage() );
	}

	/**
	 * Test memory_usage.
	 *
	 * @since 1.0.0
	 */
	public function test_memory_usage() {

		$stats = new Stats(
			[
				'memory_usage' => [
					'used_memory'   => 49.0,
					'wasted_memory' => 8.0, 
					'free_memory'   => 43.0, 
				],
			]
		);

		$this->assertSame( 57.0, round( $stats->memory_usage() ) );
	}
}