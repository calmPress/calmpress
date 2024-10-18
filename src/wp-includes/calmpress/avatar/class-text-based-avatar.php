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
	use Html_Generation_Helper,
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
	 * Generate (max) 3 letters avatars from text while
	 * using space, dot, dash and underscore as word separator and splitting
	 * the input into "separated" parts from which the first character of the 
	 * first two parts and the last part are used to generte the avatar text.
	 * 
	 * if intl extension is active remove accents from the resulting string.
	 * 
	 * Designed to be used by other code.
	 * 
	 * @since 1.0.0
	 *
	 * @param string $text The string to derive an avatar text from.
	 * 
	 * @return string The calculated avatar string, an empty string if
	 *                the $text is empty or contains only separators.
	 */
	public static function avatar_text( string $text ): string {
		$text = trim( $text );

		// Replace dot, dash, underscore with spaces.
		$text = str_replace( ['.', '-', '_'], ' ', $text );

		$text_parts = explode( ' ', $text );

		// Remove empty strings that might have been generated because of two space
		// next to each other.
		$text_parts = array_filter( $text_parts );

		// If there are more than 3 parts create a new array from first two and last.
		if ( count( $text_parts ) > 3 ) {
			$text_parts = [ $text_parts[0], $text_parts[1], $text_parts[ count( $text_parts ) - 1 ] ];
		}

		$text = '';
		foreach ( $text_parts as $part ) {
			$text .= mb_substr( $part, 0, 1, 'UTF-8' );
		}

		// Remove accents if the relevant function exists.
		if ( function_exists( 'iconv' ) ) {
			$text = iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $text );
		}

		return $text;
	}

	/**
	 * The attributes to be used in the generated img. generate a data URI
	 * containing the SVG with the appropriate letters for the src attribute
	 * and a class identifying the background color to use by default.
	 *
	 * @since 1.0.0
	 *
	 * @param int $size The width and height of the avatar image in pixels.
	 *
	 * @return string[] A map of the attributes.
	 */
	public function attributes( int $size ) : array {

		// crc32 is not optimal but it is easy to use.
		$color_index = absint( crc32( $this->text_source . $this->color_factor ) ) % count( self::COLORS );

		$text = static::avatar_text( $this->text_source );
		if ( '' === $text ) {
			$o = new Blank_Avatar();
			return $o->attributes( $size );
		}

		$attr = [
			'src' => 'data:image/svg+xml;base64,' .
				base64_encode(
					'<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100">' .
					'<text x="50%" y="50%" font-size="50" text-anchor="middle" dy=".35em" fill="white" font-family="Arial">' . $text . '</text>' .
					'</svg>'
				),
			'class' => 'av-' . $color_index,
		];

		// Add default styling for the avatar.
		\calmpress\utils\enqueue_inline_style_once( 'avatar-text-av-' . $color_index, '.avatar.av-' . $color_index . '{background:' . self::COLORS[ $color_index ] . ';}' );

		$attr = self::mutate( $attr, $this->text_source, $this->color_factor, $size );
		return $attr;
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
	 * Register a mutatur to be called when the attributes are generated.
	 *
	 * @since calmPress 1.0.0
	 *
	 * Text_Based_Avatar_Attributes_Mutator $mutator The object implementing the mutation observer.
	 */
	public static function register_generated_attributes_mutator( Text_Based_Avatar_Attributes_Mutator $mutator ): void {
		self::add_observer( $mutator );
	}
}
