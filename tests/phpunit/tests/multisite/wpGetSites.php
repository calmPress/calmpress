<?php

if ( is_multisite() ) :

	/**
	 * @group wp-get-site
	 * @group ms-site
	 * @group multisite
	 */
	class Tests_Multisite_WP_Get_Sites extends WP_UnitTestCase {
		protected static $site_ids;

		public static function wpSetUpBeforeClass( $factory ) {
			self::$site_ids = array(
				'w.org/'      => array(
					'domain'     => 'w.org',
					'path'       => '/',
					'network_id' => 2,
				),
				'wp.org/'     => array(
					'domain'     => 'wp.org',
					'path'       => '/',
					'network_id' => 2,
					'public'     => 0,
				),
				'wp.org/foo/' => array(
					'domain'     => 'wp.org',
					'path'       => '/foo/',
					'network_id' => 1,
					'public'     => 0,
				),
				'wp.org/oof/' => array(
					'domain' => 'wp.org',
					'path'   => '/oof/',
				),
			);

			foreach ( self::$site_ids as &$id ) {
				$id = $factory->blog->create( $id );
			}
			unset( $id );
		}

		public static function wpTearDownAfterClass() {
			foreach ( self::$site_ids as $id ) {
				wp_delete_site( $id );
			}

			wp_update_network_site_counts();
		}

	}

endif;
