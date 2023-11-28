<?php
/**
 * Unit tests trait to test the Observer_Collection trait.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\observer\Observer;
use calmpress\observer\Observer_Collection;
use calmpress\observer\Observer_Priority;

/**
 * A simple wrapper around the Observer_Collection trait to be able
 * to create objects using the trait and expose private methods and data structures
 * when needed.
 */
class Mock_Observer_Collection extends Observer_Collection {

	/**
	 * Expose the internal observers property.
	 */
	public function internal_collection() : array {
		return $this->collection;
	}
}

/**
 * An implementation of an Observer interface to use in testing.
 */
class Mock_Collection_Observer implements Observer {

	public array $notify_after  = [];
	public array $notify_before = [];

	public function notification_dependency_with( Observer $observer ) : Observer_Priority {
		if ( in_array( get_class( $observer ), $this->notify_before ) ) {
			return Observer_Priority::BEFORE;
		}

		if ( in_array( get_class( $observer ), $this->notify_after ) ) {
			return Observer_Priority::AFTER;
		}

		return Observer_Priority::NONE;
	}
}

/**
 * Use to create observers with different class than Mock_Observer. 
 */
class Mock_Collection_Observer2 extends Mock_Collection_Observer {};

/**
 * Use to create observers with different class than Mock_Observer. 
 */
class Mock_Observer_First extends Mock_Collection_Observer {
	public function notification_dependency_with( Observer $observer ) : Observer_Priority {
		return Observer_Priority::BEFORE;
	}
};

/**
 * Use to create observers with different class than Mock_Observer. 
 */
class Mock_Observer_Last extends Mock_Collection_Observer {
	public function notification_dependency_with( Observer $observer ) : Observer_Priority {
		return Observer_Priority::AFTER;
	}
};

/**
 * tests for the private method of the Observer_Collection trait.
 */
class Observer_Collection_Test extends WP_UnitTestCase {
	
	/**
	 * Test add_observer
	 */
	public function test_add_observer() {
		$add_observer = new ReflectionMethod( 'Mock_Observer_Collection', 'add_observer' );
        $add_observer->setAccessible(true);

		$collection = new Mock_Observer_Collection();
		$observer1  = new Mock_Collection_Observer();
		$observer2  = new Mock_Collection_Observer();
		$add_observer->invoke( $collection, $observer1 );
		$add_observer->invoke( $collection, $observer2 );
		$observers = $collection->internal_collection();
		$this->assertEquals( 2, count( $observers ) );
		$this->assertTrue( array_key_exists( spl_object_id( $observer1 ), $observers ) );
		$this->assertTrue( array_key_exists( spl_object_id( $observer2 ), $observers ) );
	}

	/**
	 * Test remove_observer
	 */
	public function test_remove_observer() {
		$add_observer = new ReflectionMethod( 'Mock_Observer_Collection', 'add_observer' );
        $add_observer->setAccessible(true);

		$collection = new Mock_Observer_Collection();
		$observer1  = new Mock_Collection_Observer();
		$observer2  = new Mock_Collection_Observer();
		$add_observer->invoke( $collection, $observer1 );
		$add_observer->invoke( $collection, $observer2 );
		$collection->remove_observer( $observer1 );
		$observers = $collection->internal_collection();
		$this->assertEquals( 1, count( $observers ) );
		$this->assertTrue( array_key_exists( spl_object_id( $observer2 ), $observers ) );
	}

	/**
	 * Test remove_observers_of_class
	 */
	public function test_remove_observers_of_class() {
		$add_observer = new ReflectionMethod( 'Mock_Observer_Collection', 'add_observer' );
        $add_observer->setAccessible(true);

		$collection = new Mock_Observer_Collection();
		$observer1  = new Mock_Collection_Observer();
		$observer2  = new Mock_Collection_Observer();
		$observer3  = new Mock_Collection_Observer2();
		$add_observer->invoke( $collection, $observer1 );
		$add_observer->invoke( $collection, $observer2 );
		$add_observer->invoke( $collection, $observer3 );
		$collection->remove_observers_of_class( 'Mock_Collection_Observer' );
		$observers = $collection->internal_collection();
		$this->assertEquals( 1, count( $observers ) );
		$this->assertTrue( array_key_exists( spl_object_id( $observer3 ), $observers ) );
	}


	/**
	 * test compare_observers
	 */
	public function test_compare_observers() {

		$compare_observers = new ReflectionMethod( 'Mock_Observer_Collection', 'compare_observers' );
        $compare_observers->setAccessible(true);

		$mock  = new Mock_Collection_Observer();
		$mock2 = new Mock_Collection_Observer2();

		$this->assertSame( 0, $compare_observers->invoke( null, $mock, $mock2 ) );
		$this->assertSame( 0, $compare_observers->invoke( null, $mock2, $mock ) );

		$mock->notify_after = ['Mock_Collection_Observer2'];
		$this->assertSame( 1, $compare_observers->invoke( null, $mock, $mock2 ) );
		$this->assertSame( -1, $compare_observers->invoke( null, $mock2, $mock ) );

		$mock->notify_before = ['Mock_Collection_Observer2'];
		$mock->notify_after = [];
		$this->assertSame( -1, $compare_observers->invoke( null, $mock, $mock2 ) );
		$this->assertSame( 1, $compare_observers->invoke( null, $mock2, $mock ) );

		$mock->notify_before = [];
		$mock2->notify_after = ['Mock_Collection_Observer'];
		$this->assertSame( -1, $compare_observers->invoke( null, $mock, $mock2 ) );
		$this->assertSame( 1, $compare_observers->invoke( null, $mock2, $mock ) );

		$mock->notify_after  = [];
		$mock2->notify_before = ['Mock_Collection_Observer'];
		$this->assertSame( 1, $compare_observers->invoke( null, $mock, $mock2 ) );
		$this->assertSame( -1, $compare_observers->invoke( null, $mock2, $mock ) );
	}

	/**
	 * test observers
	 */
	public function test_observers() {

		$add_observer = new ReflectionMethod( 'Mock_Observer_Collection', 'add_observer' );
        $add_observer->setAccessible(true);
		$observers = new ReflectionMethod( 'Mock_Observer_Collection', 'observers' );
        $observers->setAccessible(true);

		$first        = new Mock_Observer_First();
		$unspecified  = new Mock_Collection_Observer(); 
		$unspecified->notify_after = ['Mock_Collection_Observer2'];
		$last         = new Mock_Observer_Last();
		$first2       = new Mock_Observer_First(); 
		$unspecified2 = new Mock_Collection_Observer2(); 
		$last2        = new Mock_Observer_Last();

		$collection = new Mock_Observer_Collection();
		$o = iterator_to_array( $observers->invoke( $collection ) );
		$this->assertSame( 0, count( $o ) );

		$add_observer->invoke( $collection, $first );
		$add_observer->invoke( $collection, $unspecified );
		$add_observer->invoke( $collection, $last );
		$add_observer->invoke( $collection, $unspecified2 );
		$add_observer->invoke( $collection, $last2 );
		$add_observer->invoke( $collection, $first2 );

		$observers = iterator_to_array( $observers->invoke( $collection ) );

		$this->assertSame( 6, count( $observers ) );
		$this->assertSame( 'Mock_Observer_First', get_class( $observers[0] ) );
		$this->assertSame( 'Mock_Observer_First', get_class( $observers[1] ) );
		$this->assertSame( spl_object_id( $unspecified2 ), spl_object_id( $observers[2] ) );
		$this->assertSame( spl_object_id( $unspecified ), spl_object_id( $observers[3] ) );
		$this->assertSame( 'Mock_Observer_Last', get_class( $observers[4] ) );
		$this->assertSame( 'Mock_Observer_Last', get_class( $observers[5] ) );
	}

	/**
	 * test add_observer while notifying.
	 * 
	 * The observer being added should be "popped" in its relative order in relation
	 * to the observers that were not notified yet (technically the ones that weren't
	 * "popped" yet)
	 */
	public function test_add_observer_while_notifying() { 
		$add_observer = new ReflectionMethod( 'Mock_Observer_Collection', 'add_observer' );
		$add_observer->setAccessible(true);
		$observers = new ReflectionMethod( 'Mock_Observer_Collection', 'observers' );
        $observers->setAccessible(true);

		$first        = new Mock_Observer_First();
		$unspecified  = new Mock_Collection_Observer(); 
		$unspecified->notify_after = ['Mock_Collection_Observer2'];
		$last         = new Mock_Observer_Last();
		$first2       = new Mock_Observer_First(); 
		$unspecified2 = new Mock_Collection_Observer2(); 
		$last2        = new Mock_Observer_Last();
		$inserted     = new Mock_Collection_Observer2(); 

		$collection = new Mock_Observer_Collection();

		$add_observer->invoke( $collection, $first );
		$add_observer->invoke( $collection, $unspecified );
		$add_observer->invoke( $collection, $last );
		$add_observer->invoke( $collection, $unspecified2 );
		$add_observer->invoke( $collection, $last2 );
		$add_observer->invoke( $collection, $first2 );

		$t = $observers->invoke( $collection )->current(); // pop the first element.

		// add new element
		$add_observer->invoke( $collection, $inserted );

		// Get the ones that weren't popped yet.
		$observers = iterator_to_array( $observers->invoke( $collection ) );
		$this->assertSame( 6, count( $observers ) );
		$this->assertSame( 'Mock_Observer_First', get_class( $observers[0] ) );
		$this->assertSame( 'Mock_Collection_Observer2', get_class( $observers[2] ) );
		$this->assertSame( 'Mock_Collection_Observer2', get_class( $observers[2] ) );
		$this->assertSame( 'Mock_Collection_Observer', get_class( $observers[3] ) );
		$this->assertSame( 'Mock_Observer_Last', get_class( $observers[4] ) );
		$this->assertSame( 'Mock_Observer_Last', get_class( $observers[5] ) );
	}

	/**
	 * test remove_observer while notifying.
	 * 
	 * The observer being removed should not be notified.
	 */
	public function test_remove_observer_while_notifying() { 
		$add_observer = new ReflectionMethod( 'Mock_Observer_Collection', 'add_observer' );
		$add_observer->setAccessible(true);
		$observers = new ReflectionMethod( 'Mock_Observer_Collection', 'observers' );
        $observers->setAccessible(true);

		$first        = new Mock_Observer_First();
		$unspecified  = new Mock_Collection_Observer(); 
		$unspecified->notify_after = ['Mock_Collection_Observer2'];
		$last         = new Mock_Observer_Last();
		$first2       = new Mock_Observer_First(); 
		$unspecified2 = new Mock_Collection_Observer2(); 
		$last2        = new Mock_Observer_Last();

		$collection = new Mock_Observer_Collection();

		$add_observer->invoke( $collection, $first );
		$add_observer->invoke( $collection, $unspecified );
		$add_observer->invoke( $collection, $last );
		$add_observer->invoke( $collection, $unspecified2 );
		$add_observer->invoke( $collection, $last2 );
		$add_observer->invoke( $collection, $first2 );

		$t = $observers->invoke( $collection )->current(); // pop the first element.
		$collection->remove_observer( $last2 );

		// Get the ones that weren't popped yet.
		$observers = iterator_to_array( $observers->invoke( $collection ) );
		$this->assertSame( 4, count( $observers ) );
		$this->assertSame( 'Mock_Observer_First', get_class( $observers[0] ) );
		$this->assertSame( 'Mock_Collection_Observer2', get_class( $observers[1] ) );
		$this->assertSame( 'Mock_Collection_Observer', get_class( $observers[2] ) );
		$this->assertSame( 'Mock_Observer_Last', get_class( $observers[3] ) );
	}
	/**
	 * test remove_observer_of_class while notifying.
	 * 
	 * The observers being removed should not be notified.
	 */
	public function test_remove_observer_of_class_while_notifying() { 
		$add_observer = new ReflectionMethod( 'Mock_Observer_Collection', 'add_observer' );
		$add_observer->setAccessible(true);
		$observers = new ReflectionMethod( 'Mock_Observer_Collection', 'observers' );
        $observers->setAccessible(true);

		$first        = new Mock_Observer_First();
		$unspecified  = new Mock_Collection_Observer(); 
		$unspecified->notify_after = ['Mock_Collection_Observer2'];
		$last         = new Mock_Observer_Last();
		$first2       = new Mock_Observer_First(); 
		$unspecified2 = new Mock_Collection_Observer2(); 
		$last2        = new Mock_Observer_Last();

		$collection = new Mock_Observer_Collection();

		$add_observer->invoke( $collection, $first );
		$add_observer->invoke( $collection, $unspecified );
		$add_observer->invoke( $collection, $last );
		$add_observer->invoke( $collection, $unspecified2 );
		$add_observer->invoke( $collection, $last2 );
		$add_observer->invoke( $collection, $first2 );

		$t = $observers->invoke( $collection )->current(); // pop the first element.
		$collection->remove_observers_of_class( 'Mock_Collection_Observer2' );

		// Get the ones that weren't popped yet.
		$observers = iterator_to_array( $observers->invoke( $collection ) );
		$this->assertSame( 4, count( $observers ) );
		$this->assertSame( 'Mock_Observer_First', get_class( $observers[0] ) );
		$this->assertSame( 'Mock_Collection_Observer', get_class( $observers[1] ) );
		$this->assertSame( 'Mock_Observer_Last', get_class( $observers[2] ) );
		$this->assertSame( 'Mock_Observer_Last', get_class( $observers[3] ) );
	}
}
?>
