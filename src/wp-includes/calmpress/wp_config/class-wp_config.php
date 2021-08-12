<?php
/**
 * Implementation of helper class to manipulate the content of the content of wp-config.php.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\wp_config;

/**
 * Helper class to manipulate the content of the content of wp-config.php.
 *
 * @since 1.0.0
 */
class wp_config {

    /**
     * Checks if a string is in a format appropriate to be used in the user section
     * of wp-config.php.
     *
     * Line can be
     *  - Empty
     *  - Begining with '//' (php line comment token)
     *  - Format of 'define("...",...); // comment'
     *    double quotes and single quotes can be used as delimiters for the first value, both
     *    values should be valid php literals.
	 *    the comment part is optional.
     * 
     * all lines can be padded by space.
     *
     * @since 1.0.0
     *
     * @param string $line The string to validate.
     * 
     * @return bool true if valid, otherwise false.
     */
    private static function valid_user_setting_line( string $line ) : bool {

        $trimmed = trim( $line );
        if ( empty( $trimmed ) ) {
            $result[] = $line;
            return true;
        }
        if ( '//' === substr( $trimmed, 0, 2 ) ) {
            $result[] = $line;
            return true;
        }
		preg_match( '#^define\(\s*([\"\\\'][A-Z_a-z]*[\"\\\'])\s*,\s*([^\)]*)\s*\)\s*;\s*(\\/\\/.*)?#', $trimmed, $matches);
        if ( 2 >= count( $matches ) ) {
			//no matches for the two essential parts.
			return false;
		}
		$delim = substr( $matches[1], 0, 1 );
		if ( $delim !== substr( $matches[1], -1, 1 ) ) {
			//first and last quote style do not match.
			return false;
		}
		$second = trim( $matches[2] );
		if ( empty( $second ) ) {
			// no value as second parameter to the define.
			return false;
		}

		if ( is_numeric( $second ) || ( 'false' === $second ) || ( 'true' === $second ) ) {
			// It is a valid number or bool.
			return true;
		}

		// Not a numeric nor boolean value, check for valid string.
		$delim = substr( $second, 0, 1 );
		if ( ( "'" !== $delim ) && ( '"' !== $delim ) || ( 1 === strlen( $second ) ) ) {
			// string do not start with a quote or not long enough to have two quotes.
			return false;
		}

		if ( $delim !== substr( $second, -1, 1 ) ) {
			//first and last quote style do not match.
			return false;
		}

		// get the actual content between the quotes.
		$content = substr( $second, 1, -1 );

		/*
		 * check that if the sorounding quote is part of the content, it is escaped
		 * by removing escaped quotes and checking if any are left behind.
		 */
		$stripped = str_replace( '\\' . $delim, '', $content );
		if ( false !== strpos( $stripped, $delim ) ) {
			return false;
		}

		return true;
    }

    /**
     * Sanitize a string to a format appropriate to be used in the user section
     * of wp-config.php.
     *
     * The user section can have
     *  - Empty lines
     *  - lines begining with '//' (php line comment token)
     *  - lines of the format 'define("...",...);'
     *    double quotes and single quotes can be used as delimiters for the first value, both
     *    values should be valid php literals.
     * 
     * all lines can be padded by space.
     * 
     * Sanitization will remove lines that do not match the format.
     *
     * @since 1.0.0
     *
     * @param string $value The string to sanitize.
     * 
     * @return string
     */
    public static function sanitize_user_setting( string $value ) : string {
        $result = [];

        $lines = explode( "\n", $value );
        foreach ( $lines as $line ) {
			if ( static::valid_user_setting_line( $line ) ) {
				$result[] = $line;
			}
		}
    
        return join( "\n", $result );
    }
}