<?php
/**
 * Locale API: WP_Locale class
 *
 * @package WordPress
 * @subpackage i18n
 * @since 4.6.0
 */

/**
 * Core class used to store translated data for a locale.
 *
 * @since 2.1.0
 * @since 4.6.0 Moved to its own file from wp-includes/locale.php.
 */
class WP_Locale {

	/**
	 * Mimic the wordpress weekday public property.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return array Where day numbers (0-6) are the indexes and their translated names
	 *               the values.
	 */
	private function weekday(): array {
		return [
			0 => /* translators: Weekday. */ __( 'Sunday' ),
			1 => /* translators: Weekday. */ __( 'Monday' ),
			2 => /* translators: Weekday. */ __( 'Tuesday' ),
			3 => /* translators: Weekday. */ __( 'Wednesday' ),
			4 => /* translators: Weekday. */ __( 'Thursday' ),
			5 => /* translators: Weekday. */ __( 'Friday' ),
			6 => /* translators: Weekday. */ __( 'Saturday' ),
		];
	}

	/**
	 * Mimic the wordpress weekday_initial public property.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return array Where translated day names are the indexes and their translated initials
	 *               the values.
	 */
	private function weekday_initial(): array {
		return [
			__( 'Sunday', 'default', false )    => /* translators: One-letter abbreviation of the weekday. */ _x( 'S', 'Sunday initial' ),
			__( 'Monday', 'default', false )    => /* translators: One-letter abbreviation of the weekday. */ _x( 'M', 'Monday initial' ),
			__( 'Tuesday', 'default', false )   => /* translators: One-letter abbreviation of the weekday. */ _x( 'T', 'Tuesday initial' ),
			__( 'Wednesday', 'default', false ) => /* translators: One-letter abbreviation of the weekday. */ _x( 'W', 'Wednesday initial' ),
			__( 'Thursday', 'default', false )  => /* translators: One-letter abbreviation of the weekday. */ _x( 'T', 'Thursday initial' ),
			__( 'Friday', 'default', false )    => /* translators: One-letter abbreviation of the weekday. */ _x( 'F', 'Friday initial' ),
			__( 'Saturday', 'default', false )  => /* translators: One-letter abbreviation of the weekday. */ _x( 'S', 'Saturday initial' ),
		];
	}

	/**
	 * Mimic the wordpress weekday_abbrev public property.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return array Where translated day names are the indexes and their translated abbreveation
	 *               the values.
	 */
	private function weekday_abbrev(): array {
		return [
			__( 'Sunday', 'default', false )    => /* translators: Three-letter abbreviation of the weekday. */ __( 'Sun' ),
			__( 'Monday', 'default', false )    => /* translators: Ttree-letter abbreviation of the weekday. */ __( 'Mon' ),
			__( 'Tuesday', 'default', false )   => /* translators: Three-letter abbreviation of the weekday. */ __( 'Tue' ),
			__( 'Wednesday', 'default', false ) => /* translators: Three-letter abbreviation of the weekday. */ __( 'Wed' ),
			__( 'Thursday', 'default', false )  => /* translators: Three-letter abbreviation of the weekday. */ __( 'Thu' ),
			__( 'Friday', 'default', false )    => /* translators: Three-letter abbreviation of the weekday. */ __( 'Fri' ),
			__( 'Saturday', 'default', false )  => /* translators: Three-letter abbreviation of the weekday. */ __( 'Sat' ),
		];
	}

	/**
	 * Mimic the wordpress month public property.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return array Where month numbers (01-12) are the indexes and their translated names
	 *               the values.
	 */
	private function month(): array {
		return [
			'01' => /* translators: Month name. */ __( 'January' ),
			'02' => /* translators: Month name. */ __( 'February' ),
			'03' => /* translators: Month name. */ __( 'March' ),
			'04' => /* translators: Month name. */ __( 'April' ),
			'05' => /* translators: Month name. */ __( 'May' ),
			'06' => /* translators: Month name. */ __( 'June' ),
			'07' => /* translators: Month name. */ __( 'July' ),
			'08' => /* translators: Month name. */ __( 'August' ),
			'09' => /* translators: Month name. */ __( 'September' ),
			'10' => /* translators: Month name. */ __( 'October' ),
			'11' => /* translators: Month name. */ __( 'November' ),
			'12' => /* translators: Month name. */ __( 'December' ),
		];
	}

	/**
	 * Mimic the wordpress month_genitive public property.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return array Where month numbers (01-12) are the indexes and their translated geniative names
	 *               the values.
	 */
	private function month_genitive(): array {
		return [
			'01' => /* translators: Month name, genitive. */ _x( 'January', 'genitive' ),
			'02' => /* translators: Month name, genitive. */ _x( 'February', 'genitive' ),
			'03' => /* translators: Month name, genitive. */ _x( 'March', 'genitive' ),
			'04' => /* translators: Month name, genitive. */ _x( 'April', 'genitive' ),
			'05' => /* translators: Month name, genitive. */ _x( 'May', 'genitive' ),
			'06' => /* translators: Month name, genitive. */ _x( 'June', 'genitive' ),
			'07' => /* translators: Month name, genitive. */ _x( 'July', 'genitive' ),
			'08' => /* translators: Month name, genitive. */ _x( 'August', 'genitive' ),
			'09' => /* translators: Month name, genitive. */ _x( 'September', 'genitive' ),
			'10' => /* translators: Month name, genitive. */ _x( 'October', 'genitive' ),
			'11' => /* translators: Month name, genitive. */ _x( 'November', 'genitive' ),
			'12' => /* translators: Month name, genitive. */ _x( 'December', 'genitive' ),
		];
	}

	/**
	 * Mimic the wordpress month_abbrev public property.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return array Where month translated names are the indexes and their translated abbreviations
	 *               the values.
	 */
	private function month_abbrev(): array {
		return [
			__( 'January', 'default', false )   => /* translators: Three-letter abbreviation of the month. */ _x( 'Jan', 'January abbreviation' ),
			__( 'February', 'default', false )  => /* translators: Three-letter abbreviation of the month. */ _x( 'Feb', 'February abbreviation' ),
			__( 'March', 'default', false )     => /* translators: Three-letter abbreviation of the month. */ _x( 'Mar', 'March abbreviation' ),
			__( 'April', 'default', false )     => /* translators: Three-letter abbreviation of the month. */ _x( 'Apr', 'April abbreviation' ),
			__( 'May', 'default', false )       => /* translators: Three-letter abbreviation of the month. */ _x( 'May', 'May abbreviation' ),
			__( 'June', 'default', false )      => /* translators: Three-letter abbreviation of the month. */ _x( 'Jun', 'June abbreviation' ),
			__( 'July', 'default', false )      => /* translators: Three-letter abbreviation of the month. */ _x( 'Jul', 'July abbreviation' ),
			__( 'August', 'default', false )    => /* translators: Three-letter abbreviation of the month. */ _x( 'Aug', 'August abbreviation' ),
			__( 'September', 'default', false ) => /* translators: Three-letter abbreviation of the month. */ _x( 'Sep', 'September abbreviation' ),
			__( 'October', 'default', false )   => /* translators: Three-letter abbreviation of the month. */ _x( 'Oct', 'October abbreviation' ),
			__( 'November', 'default', false )  => /* translators: Three-letter abbreviation of the month. */ _x( 'Nov', 'November abbreviation' ),
			__( 'December', 'default', false )  => /* translators: Three-letter abbreviation of the month. */ _x( 'Dec', 'December abbreviation' ),
		];
	}

	/**
	 * Mimic the wordpress meridiem public property.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return array Where meridiem names are the indexes and their translated value
	 *               the values.
	 */
	private function meridiem(): array {
		return [
			'am' => __( 'am' ),
			'pm' => __( 'pm' ),
			'AM' => __( 'AM' ),
			'PM' => __( 'PM' ),
		];
	}

	/**
	 * Mimic the wordpress number_format public property.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return array The number format used in the translation with thousands_sep and decimal_point indexes
	 *               which point to the the appropriate translation.
	 */
	private function number_format(): array {
		// Numbers formatting.
		// See https://www.php.net/number_format

		/* translators: $thousands_sep argument for https://www.php.net/number_format, default is ',' */
		$thousands_sep = __( 'number_format_thousands_sep', 'default', true );

		// Replace space with a non-breaking space to avoid wrapping.
		$thousands_sep = str_replace( ' ', '&nbsp;', $thousands_sep );

		$ret['thousands_sep'] = ( 'number_format_thousands_sep' === $thousands_sep ) ? ',' : $thousands_sep;

		/* translators: $dec_point argument for https://www.php.net/number_format, default is '.' */
		$decimal_point = __( 'number_format_decimal_point', 'default', true );

		$ret['decimal_point'] = ( 'number_format_decimal_point' === $decimal_point ) ? '.' : $decimal_point;

		return $ret;
	}

	/**
	 * Mimic the wordpress text_direction public property.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return string 'rtl' if its is an rtl translation, otherwise 'ltr'.
	 */
	private function text_direction(): string {
		// Set text direction.
		if ( isset( $GLOBALS['text_direction'] ) ) {
			return $GLOBALS['text_direction'];

			/* translators: 'rtl' or 'ltr'. This sets the text direction for WordPress. */
		} elseif ( 'rtl' === _x( 'ltr', 'text direction', 'default', false ) ) {
			return 'rtl';
		}

		return 'ltr';
	}

	/**
	 * Retrieve the full translated weekday word.
	 *
	 * Week starts on translated Sunday and can be fetched
	 * by using 0 (zero). So the week starts with 0 (zero)
	 * and ends on Saturday with is fetched by using 6 (six).
	 *
	 * @since 2.1.0
	 *
	 * @param int $weekday_number 0 for Sunday through 6 Saturday.
	 * @return string Full translated weekday.
	 */
	public function get_weekday( $weekday_number ) {
		return $this->weekday[ $weekday_number ];
	}

	/**
	 * Retrieve the translated weekday initial.
	 *
	 * The weekday initial is retrieved by the translated
	 * full weekday word. When translating the weekday initial
	 * pay attention to make sure that the starting letter does
	 * not conflict.
	 *
	 * @since 2.1.0
	 *
	 * @param string $weekday_name Full translated weekday word.
	 * @return string Translated weekday initial.
	 */
	public function get_weekday_initial( $weekday_name ) {
		return $this->weekday_initial[ $weekday_name ];
	}

	/**
	 * Retrieve the translated weekday abbreviation.
	 *
	 * The weekday abbreviation is retrieved by the translated
	 * full weekday word.
	 *
	 * @since 2.1.0
	 *
	 * @param string $weekday_name Full translated weekday word.
	 * @return string Translated weekday abbreviation.
	 */
	public function get_weekday_abbrev( $weekday_name ) {
		return $this->weekday_abbrev[ $weekday_name ];
	}

	/**
	 * Retrieve the full translated month by month number.
	 *
	 * The $month_number parameter has to be a string
	 * because it must have the '0' in front of any number
	 * that is less than 10. Starts from '01' and ends at
	 * '12'.
	 *
	 * You can use an integer instead and it will add the
	 * '0' before the numbers less than 10 for you.
	 *
	 * @since 2.1.0
	 *
	 * @param string|int $month_number '01' through '12'.
	 * @return string Translated full month name.
	 */
	public function get_month( $month_number ) {
		return $this->month[ zeroise( $month_number, 2 ) ];
	}

	/**
	 * Retrieve translated version of month abbreviation string.
	 *
	 * The $month_name parameter is expected to be the translated or
	 * translatable version of the month.
	 *
	 * @since 2.1.0
	 *
	 * @param string $month_name Translated month to get abbreviated version.
	 * @return string Translated abbreviated month.
	 */
	public function get_month_abbrev( $month_name ) {
		return $this->month_abbrev[ $month_name ];
	}

	/**
	 * Retrieve translated version of meridiem string.
	 *
	 * The $meridiem parameter is expected to not be translated.
	 *
	 * @since 2.1.0
	 *
	 * @param string $meridiem Either 'am', 'pm', 'AM', or 'PM'. Not translated version.
	 * @return string Translated version
	 */
	public function get_meridiem( $meridiem ) {
		return $this->meridiem[ $meridiem ];
	}

	/**
	 * Checks if current locale is RTL.
	 *
	 * @since 3.0.0
	 * @return bool Whether locale is RTL.
	 */
	public function is_rtl() {
		return 'rtl' === $this->text_direction;
	}

	/**
	 * Register date/time format strings for general POT.
	 *
	 * Private, unused method to add some date/time formats translated
	 * on wp-admin/options-general.php to the general POT that would
	 * otherwise be added to the admin POT.
	 *
	 * @since 3.6.0
	 */
	public function _strings_for_pot() {
		/* translators: Localized date format, see https://www.php.net/manual/datetime.format.php */
		__( 'F j, Y' );
		/* translators: Localized time format, see https://www.php.net/manual/datetime.format.php */
		__( 'g:i a' );
		/* translators: Localized date and time format, see https://www.php.net/manual/datetime.format.php */
		__( 'F j, Y g:i a' );
	}

	/**
	 * Implement compatibility for public properties in the wordpress implementation.
	 * 
	 * Implements field for: weekday, weekday_initial, weekday_abbrev, month, month_genitive, month_abbrev,
	 * meridiem 
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param $name the name of the property.
	 */
	public function __get( string $name ) {
		if ( in_array(
				$name,
				[
					'weekday',
					'weekday_initial',
					'weekday_abbrev',
					'month',
					'month_genitive',
					'month_abbrev',
					'meridiem',
					'number_format',
					'text_direction',
				],
				true
			)
		) {
			return $this->$name();
		}
	}
}
