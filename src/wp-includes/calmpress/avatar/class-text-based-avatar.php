<?php
/**
 * Implementation of an avatar class based on textual information.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\avatar;
use calmpress\observer\Static_Mutation_Observer_Collection;

/**
 * A representation of an avatar which is based on textual information.
 *
 * @since 1.0.0
 */
class Text_Based_Avatar implements Avatar {
	use Html_Parameter_Validation,
	Static_Mutation_Observer_Collection {
		Static_Mutation_Observer_Collection::remove_observer as remove_mutator;
		Static_Mutation_Observer_Collection::remove_observers_of_class as remove_mutator_of_class;
	}

	/**
	 * The text from which the avatar's text will be derived.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	private string $text_source;

	/**
	 * The additional factor to apply when calculating the avatar's background color.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	private string $color_factor;

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
	 * Construct the avatar object based on an the text to display and some addition
	 * text to get more randomize background colors.
	 * 
	 * If text is not given the behaviour is the same as of a blank avatar.
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
	 * @param int $size The width and height of the avatar image in pixels.
	 *
	 * @return string An HTML which will be rendered as a rectangle of the
	 *                requested dimensions which will contain capital letters based
	 *                on the initials of the name and background color based on
	 *                the email address.
	 */
	protected function _html( int $size ) : string {

		// crc32 is not optimal but it is easy to use.
		$color = self::COLORS[ absint( crc32( $this->text_source . $this->color_factor ) ) % count( self::COLORS ) ];

		$text = trim( $this->text_source );
		if ( '' === $text ) {
			$o = new Blank_Avatar();
			return $o->html( $size );
		}

		$text_parts = explode( ' ', $text );
		$text = '';
		foreach ( $text_parts as $part ) {
			$text .= mb_substr( $part, 0, 1, 'UTF-8' );
		}

		$text = esc_html( strtoupper( $text ) );

		if ( count( $text_parts ) < 2 ) {
			$font_size = round ( $size / 2 );
		} else {
			// use smaller font size when there are more characters to display.
			$font_size = round( $size / 5 * 2 );
		}

		$html = "<span aria-hidden='true' style='display:inline-block;border-radius:50%;text-align:center;color:white;line-height:" . $size . "px;width:" . $size . "px;height:" . $size . "px;font-size:" . $font_size . "px;background:" . $color . "'>$text</span>";

		// Allow plugin and themes to override.
		$html = self::mutate( $html, $this->text_source, $this->color_factor, $size );

		return $html;
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

	/**
	 * Register a mutatur to be called when the HTML is generated.
	 *
	 * @since calmPress 1.0.0
	 *
	 * Text_Based_Avatar_HTML_Mutator $mutator The object implementing the mutation observer.
	 */
	public static function register_generated_HTML_mutator( Text_Based_Avatar_HTML_Mutator $mutator ): void {
		self::add_observer( $mutator );
	}
}
