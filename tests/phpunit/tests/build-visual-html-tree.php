<?php

/**
 * Tests for build_visual_html_tree().
 *
 * @package WordPress
 *
 * @group testsuite
 */
class Tests_Build_Equivalent_HTML_Semantic_Tree extends WP_UnitTestCase {

	public function data_build_equivalent_html_semantic_tree_with_equivalent_html() {
		return array(
			'Different attribute order'                => array(
				'<img src="wp.png" alt="The WordPress logo">',
				'<img alt="The WordPress logo" src="wp.png">',
			),
			'Different class name order'               => array(
				'<hr class="wp-block-separator is-style-default">',
				'<hr class="is-style-default wp-block-separator">',
			),
			'Differences in style attribute whitespace and trailing semicolon' => array(
				'<hr style="margin-top: 50px; margin-bottom: 50px;">',
				'<hr style="margin-top:50px;margin-bottom: 50px">',
			),
			'Different Capitalization of tag'          => array(
				'<IMG src="wp.png" alt="The WordPress logo">',
				'<img src="wp.png" alt="The WordPress logo">',
			),
		);
	}

	/**
	 * @ticket 63527
	 *
	 * @covers ::build_visual_html_tree
	 *
	 * @dataProvider data_build_equivalent_html_semantic_tree_with_equivalent_html
	 */
	public function test_build_equivalent_html_semantic_tree_with_equivalent_html( $expected, $actual ) {
		$tree_expected = build_visual_html_tree( $expected, '<body>' );
		$tree_actual   = build_visual_html_tree( $actual, '<body>' );

		$this->assertSame( $tree_expected, $tree_actual );
	}

	public function data_build_equivalent_html_semantic_tree_with_non_equivalent_html() {
		return array(
			'Different attributes'             => array(
				'<img src="wp.png" alt="The WordPress logo">',
				'<img alt="The WordPress logo" src="wp.png" title="WordPress">',
			),
			'Different class names'            => array(
				'<hr class="wp-block-separator is-style-default">',
				'<hr class="is-style-default wp-block-hairline">',
			),
			'Different styles'                 => array(
				'<hr style="margin-top: 50px; margin-bottom: 50px;">',
				'<hr style="margin-top: 50px; margin-bottom: 100px">',
			),
			'Different comments'               => array(
				'<!-- abc -->',
				'<!-- xyz -->',
			),
			'Semantically relevant whitespace' => array(
				'<div style="color: rgb(50 139 31)">Test</div>',
				'<div style="color:rgb(5013931)">Test</div>',
			),
		);
	}

	/**
	 * @ticket 63527
	 *
	 * @covers ::build_visual_html_tree
	 *
	 * @dataProvider data_build_equivalent_html_semantic_tree_with_non_equivalent_html
	 */
	public function test_build_equivalent_html_semantic_tree_with_non_equivalent_html( $expected, $actual ) {
		$tree_expected = build_visual_html_tree( $expected, '<body>' );
		$tree_actual   = build_visual_html_tree( $actual, '<body>' );

		$this->assertNotSame( $tree_expected, $tree_actual );
	}

	/**
	 * @ticket 64531
	 *
	 * @covers ::build_visual_html_tree
	 */
	public function test_spacing() {
		$html = <<<'HTML'
<p> space-surrounded&#x20;</p>
<p>&nbsp;nbsp-surrounded&#xA0;</p>
<p>
newline-surrounded&#xA;</p>
<p>&#x9;tab-surrounded	</p>
<p>ok</p>
HTML;

		$expected = <<<TREE
<p>
  " space-surrounded "
"\n"
<p>
  "\u{00A0}nbsp-surrounded\u{00A0}"
"\n"
<p>
  "\nnewline-surrounded\n"
"\n"
<p>
  "\ttab-surrounded\t"
"\n"
<p>
  "ok"

TREE;

		$tree_result = build_visual_html_tree( $html, '<body>' );
		$this->assertSame( $expected, $tree_result );
	}
}
