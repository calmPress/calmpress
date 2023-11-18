<?php
/**
 * Unit tests trait to test the No_Parameters_Progress_Observer_Collection trait.
 * Implicitly tests the Static_Progress_Observer_Collection trait.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\observer\Observer_Priority;
use calmpress\observer\Observer;
use calmpress\observer\Static_Mutation_Observer_Collection;

class Mock_Static_Mutation_Observer_Collection {
	use Static_Mutation_Observer_Collection;

	public static function add_mutation_observer( Mock_Mutation_Observer $observer ) : void {
		self::add_observer( $observer );
	}
}

/**
 * An implementation of an No_Parameters_Progress_Observer interface to use in testing.
 */
class Mock_Mutation_Observer implements Observer {

	public int    $priority = 0;
	public string $value;

	public function __construct( int $priority, string $value ) {
		$this->priority = $priority;
		$this->value    = $value; 
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

	public function mutate( string $value ): string {
		return $value . $this->value;
	}
}

/**
 * Use to create observers with different class than Mock_Mutation_Observer. 
 */
class Mock_Mutation_Observer2 extends Mock_Mutation_Observer {};

/**
 * tests for the private method of the Observer_Collection trait.
 */
class Static_Mutation_Observer_Collection_test extends WP_UnitTestCase {
	
	/**
	 * test observers
	 */
	public function test_mutate() {

		$mutator1  = new Mock_Mutation_Observer( 5, 'a' );
		$mutator2  = new Mock_Mutation_Observer( 3, 'b' );
		$mutator3  = new Mock_Mutation_Observer2( 6, 'c' );
		Mock_Static_Mutation_Observer_Collection::add_mutation_observer( $mutator1 );
		Mock_Static_Mutation_Observer_Collection::add_mutation_observer( $mutator2 );
		Mock_Static_Mutation_Observer_Collection::add_mutation_observer( $mutator3 );

		$value = Mock_Static_Mutation_Observer_Collection::mutate( '' );

		$this->assertSame( 'bac' , $value );
	}
}
?>
