<?php
/**
 * Unit tests covering functions of markdown Utils class.
 *
 * @package calmPress
 * @since 1.0.0
 */

class WP_Markdown_Utils extends WP_UnitTestCase {

    /**
	 * test comment_markdown_to_html
	 * 
	 * @since 1.0.0
	 * 
     * @dataProvider markdownProvider
     */
    public function test_comment_markdown_to_html(string $input, string $expected): void {
        $this->assertSame( $expected, calmpress\markdown\Utils::comment_markdown_to_html( $input ) );
    }

	/**
	 * Data provider for test_comment_markdown_to_html
	 * 
	 * @since 1.0.0
	 */
    public function markdownProvider(): array {
        return [
            // Italic
            ['*italic*', "<p><em>italic</em></p>"],

            // Bold
            ['**bold**', "<p><strong>bold</strong></p>"],

            // Strikethrough
            ['~~strike~~', "<p><del>strike</del></p>"],

            // Unordered list
            ["- item1\n- item2", "<ul>\n<li>item1</li>\n<li>item2</li>\n</ul>"],

            // Blockquote
            ['> quote', "<blockquote>\n<p>quote</p>\n</blockquote>"],

            // HTML inside text
            ['<script>alert("x")</script>', "<p>&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;</p>"],

            // Markdown with literal HTML inside
            ['*<*', "<p><em>&lt;</em></p>"],

            // Link is ignored / treated as text
            ['[link](https://example.com)', "<p>[link](https://example.com)</p>"],

            // Image is ignored / treated as text
            ['![img](x.jpg)', "<p>![img](x.jpg)</p>"],

            // Combination: bold + italic + strike
            ['**bold** *italic* ~~strike~~', "<p><strong>bold</strong> <em>italic</em> <del>strike</del></p>"],

            // Empty string
            ['', ''],

			// single line paragraph.
			["This is a paragraph.", "<p>This is a paragraph.</p>"],

			// Paragraph with multiple lines (still one paragraph)
			["This is line one.\nThis is line two.", "<p>This is line one.\nThis is line two.</p>"],

			// Multiple paragraphs (separated by empty line)
			["Para one.\n\nPara two.", "<p>Para one.</p>\n<p>Para two.</p>"],

			// Markdown element containing literal HTML (should be escaped)
			["*<b>italic</b>*", "<p><em>&lt;b&gt;italic&lt;/b&gt;</em></p>"],

			// Blockquote containing HTML (HTML treated as literal text)
			["> <div>quote</div>", "<blockquote>\n<p>&lt;div&gt;quote&lt;/div&gt;</p>\n</blockquote>"],

			// List with Markdown element containing HTML
			["- *<i>item</i>*\n- **<b>item2</b>**",
			"<ul>\n<li><em>&lt;i&gt;item&lt;/i&gt;</em></li>\n<li><strong>&lt;b&gt;item2&lt;/b&gt;</strong></li>\n</ul>"],

			// Paragraph containing HTML entity chars (<, >, &)
			["Text with <, >, &", "<p>Text with &lt;, &gt;, &amp;</p>"],

			// Blockquote with multiple lines and markdown inside
			["> **Bold quote**\n> *Italic line*\n> ~~Strike~~",
			"<blockquote>\n<p><strong>Bold quote</strong>\n<em>Italic line</em>\n<del>Strike</del></p>\n</blockquote>"],

			// Blockquote with HTML entities
			["> <script>alert('x')</script>",
			"<blockquote>\n<p>&lt;script&gt;alert('x')&lt;/script&gt;</p>\n</blockquote>"],

			// List with markdown elements and HTML entities
			["- Item with *italic* & <\n- Item with **bold** > &",
			"<ul>\n<li>Item with <em>italic</em> &amp; &lt;</li>\n<li>Item with <strong>bold</strong> &gt; &amp;</li>\n</ul>"],

			// List inside blockquote
			["> - List item 1\n> - List item 2",
			"<blockquote>\n<ul>\n<li>List item 1</li>\n<li>List item 2</li>\n</ul>\n</blockquote>"],

			// Nested blockquote with markdown
			["> > Nested *italic* quote",
			"<blockquote>\n<blockquote>\n<p>Nested <em>italic</em> quote</p>\n</blockquote>\n</blockquote>"],

			// Blockquote with multiple paragraphs and markdown
			["> Para one\n>\n> Para two with **bold**",
			"<blockquote>\n<p>Para one</p>\n<p>Para two with <strong>bold</strong></p>\n</blockquote>"],

			// Underscore italics (ignored)
			['_italic_', "<p>_italic_</p>"],

			// Double underscore bold (ignored)
			['__bold__', "<p>__bold__</p>"],

			// Inline mix: ignored _ and allowed **
			['_italic_ and **bold**', "<p>_italic_ and <strong>bold</strong></p>"],

			// Inline mix: ignored __ and allowed **
			['**bold** and __bold__', "<p><strong>bold</strong> and __bold__</p>"],

			// Underscore inside text
			['Text with _underscores_ inside', "<p>Text with _underscores_ inside</p>"],

			// Double underscore inside text
			['Text with __double__ underscores', "<p>Text with __double__ underscores</p>"],

			// Escaping
			["f \\~d \\* \\\\ \\a", '<p>f ~d * \\ \\a</p>'],
		];
    }
}