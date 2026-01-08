<?php
/**
 * Unit tests covering the Comment_Syntax_Help_Item class.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\comments\Comment_Syntax_Help_Item;

class Comment_Syntax_Help_Item_Test extends WP_UnitTestCase {

	/**
	 * Test the constructor basic functionality
	 *
	 * @since 1.0.0
	 */
	public function test_constructor() {

		$o = new Comment_Syntax_Help_Item( 'id', 'example','explanation' );
		$this->assertSame( 'id', $o->id );
		$this->assertSame( 'example', $o->example );
		$this->assertSame( 'explanation', $o->explanation );

		// empty id throws.
		$thrown = false;
		try {
			$o = new Comment_Syntax_Help_Item( '', 'example','explanation' );	
		} catch ( Exception $e ) {
			$thrown = true;
		}
		$this->assertTrue( $thrown );

		// empty example throws.
		$thrown = false;
		try {
			$o = new Comment_Syntax_Help_Item( 'id', '','explanation' );	
		} catch ( Exception $e ) {
			$thrown = true;
		}
		$this->assertTrue( $thrown );
	}

	/**
	 * Test the constructor sanitizes example
	 *
	 * @since 1.0.0
	 */
	public function test_no_html_in_examlple() {

		$o = new Comment_Syntax_Help_Item( 'id', 'tyt<b>tat','' );
		$this->assertSame( 'tyttat', $o->example );

		$o = new Comment_Syntax_Help_Item( 'id', 'tyt<i>tat</i>','' );
		$this->assertSame( 'tyttat', $o->example );
	}

	/**
	 * Test the constructor sanitizes explanation
	 *
	 * @since 1.0.0
	 * 
	 * @dataProvider explanation_data
	 */
	public function test_html_in_explanation( $test, $result ) {

		$o = new Comment_Syntax_Help_Item( 'id', 'example',$test );
		$this->assertSame( $result, $o->explanation );
	}

	public function explanation_data() {
		return [
			[ '<div>s</div>', 's' ],
			[ '<s>s</s>', '<s>s</s>' ],
			[ '<s attr="g">s</s>', '<s>s</s>' ],
			[ '<b>s</b>', '<b>s</b>' ],
			[ '<b attr="g">s</b>', '<b>s</b>' ],
			[ '<sup>s</sup>', '<sup>s</sup>' ],
			[ '<sup attr="g">s</sup>', '<sup>s</sup>' ],
			[ '<sub>s</sub>', '<sub>s</sub>' ],
			[ '<sub attr="g">s</sub>', '<sub>s</sub>' ],
			[ '<i>s</i>', '<i>s</i>' ],
			[ '<i attr="g">s</i>', '<i>s</i>' ],
			[ '<em>s</em>', '<em>s</em>' ],
			[ '<em attr="g">s</em>', '<em>s</em>' ],
			[ '<strong>s</strong>', '<strong>s</strong>' ],
			[ '<strong attr="g">s</strong>', '<strong>s</strong>' ],
			[ '<code>s</code>', '<code>s</code>' ],
			[ '<code attr="g">s</code>', '<code>s</code>' ],
			[ '<span>s</span>', '<span>s</span>' ],
			[ '<span attr="g">s</span>', '<span>s</span>' ],
			[ '<span class="g">s</span>', '<span class="g">s</span>' ],
			[ 'its a<br>s', 'its a<br>s' ],
		];
	}
}