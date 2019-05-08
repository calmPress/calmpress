<?php
/**
 * Implementation of an avatar class based on textual information.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\avatar;

/**
 * A representation of an avatar which is based on textual information.
 *
 * @since 1.0.0
 */
class Text_Based_Avatar implements Avatar {
	use Html_Parameter_Validation;

	/**
	 * The text from which the avatar's text will be derived.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	private $text_source;

	/**
	 * The additional factor to apply when calculating the avatar's background color.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	private $color_factor;

	/**
	 * Conversion helper to get color base on a number. Based on the colors from
	 * https://material-ui.com/style/color/ which have a good contrast for white
	 * text.
	 *
	 * @var string[]
	 *
	 * @since 1.0.0
	 */
	const COLORS = [
		0 => '#f44336', // Red.
		1 => '#e91e63', // Pink.
		2 => '#9c27b0', // Purple.
		3 => '#673ab7', // Deep purple.
		4 => '#3f51b5', // Indigo.
		5 => '#2196f3', // Blue.
		6 => '#009688', // Teal.
		7 => '#ff5722', // Deep Orange.
		8 => '#795548', // Brown.
		9 => '#607d8b', // Blue gray.
	];

	/**
	 * Construct the avatar object based on an attachment.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text_source  The text from which the avatar's text will be derived.
	 * @param string $color_factor The additional factor to apply when calculating
	 *                             the avatar's background color.
	 *                             It should be used to help visually differentiate
	 *                             between avatars with the same text that should
	 *                             represent different people.
	 */
	public function __construct( string $text_source, string $color_factor ) {
		$this->text_source  = $text_source;
		$this->color_factor = $color_factor;
	}

	/**
	 * Provides the HTML required to display the avatar.
	 *
	 * @since 1.0.0
	 *
	 * @param int $width  The width of the avatar image.
	 * @param int $height The height of the avatar image.
	 *
	 * @return string An HTML which will be rendered as a rectangle of the
	 *                requested dimensions which will contain capital letters based
	 *                on the initials of the name and background color based on
	 *                the email address.
	 */
	protected function _html( int $width, int $height ) : string {
		$font_size = $height / 2;

		// crc32 is not optimal but it is easy to use.
		$color = self::COLORS[ absint( crc32( $this->text_source . $this->color_factor ) ) % count( self::COLORS ) ];

		$text = trim( $this->text_source );
		if ( '' === $text ) {
			$o = new Blank_Avatar();
			return $o->html( $width, $height );
		}

		$text_parts = explode( ' ', $text );
		$text       = substr( $text_parts[0], 0, 1 );

		// If the container is wide enough, get two characters.
		if ( 40 < $width && 1 < count( $text_parts ) ) {
			$text .= substr( $text_parts[ count( $text_parts ) - 1 ], 0, 1 );
		}
		$text = esc_html( strtoupper( $text ) );

		$html = "<span aria-hidden='true' style='display:inline-block;border-radius:50%;text-align:center;color:white;line-height:${height}px;width:${width}px;height:${height}px;font-size:${font_size}px;background:${color}'>$text</span>";

		/**
		 * Filters the generated image avatar.
		 *
		 * @since 1.0.0
		 *
		* @param string The HTML of the avatar.
		* @param string The text on which the avatar's text is based.
		* @param string The additional factor used in background color generation.
		* @param int    The width of the avatar.
		* @param int    The height of the avatar.
		*/
		return apply_filters( 'calm_text_based_avatar_html', $html, $this->text_source, $this->color_factor, $width, $height );
	}

	/**
	 * Implementation of the attachment method of the Avatar interface which
	 * returns null as the text avatar can not be configured by user.
	 *
	 * @since 1.0.0
	 *
	 * @return null Indicates no attachment is associated with the avatar.
	 */
	public function attachment() {
		return null;
	}
}
