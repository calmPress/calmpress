<?php
/**
 * Unit tests trait to test the No_Parameters_Progress_Observer_Collection class.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\observer\No_Parameters_Progress_Observer;
use calmpress\observer\No_Parameters_Progress_Observer_Collection;
use calmpress\observer\Observer_Priority;
use calmpress\observer\Observer;

/**
 * An implementation of an No_Parameters_Progress_Observer interface to use in testing.
 */
class Mock_Observer implements No_Parameters_Progress_Observer {

	public int $priority = 0;
	public static array $done=[];

	public function __construct( int $priority = 0 ) {
		$this->priority = $priority;
	}

	public function notification_dependency_with( Observer $observer ) : Observer_Priority {
		if ( $this->priority < $observer->priority ) {
			return Observer_Priority::BEFORE;
		}

		if ( $observer->priority < $this->priority ) {
			return Observer_Priority::AFTER;
		}

		return Observer_Priority::NONE;
	}

	public function reached(): void {
		self::$done[] = spl_object_id( $this );
	}
}

/**
 * Use to create observers with different class than Mock_Observer. 
 */
class Mock_Observer2 extends Mock_Observer {};

/**
 * tests for the private method of the Observer_Collection trait.
 */
class Observer_Collection_Test extends WP_UnitTestCase {
	
	/**
	 * test observers
	 */
	public function test_reached() {

		$collection = new No_Parameters_Progress_Observer_Collection();
		$observer1  = new Mock_Observer( 5 );
		$observer2  = new Mock_Observer( 3 );
		$observer3  = new Mock_Observer2( 6 );
		$collection->add_observer( $observer1 );
		$collection->add_observer( $observer2 );
		$collection->add_observer( $observer3 );

		$collection->reached();

		$this->assertSame( 3, count( Mock_Observer::$done ) );
		$this->assertSame( spl_object_id( $observer2 ), Mock_Observer::$done[0] );
		$this->assertSame( spl_object_id( $observer1 ), Mock_Observer::$done[1] );
		$this->assertSame( spl_object_id( $observer3 ), Mock_Observer::$done[2] );
	}
}
?>
