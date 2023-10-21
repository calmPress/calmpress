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

	// The marker identifying the user section in a wp-config.php file.
	const USER_SECTION_MARKER = 'User';

	// The prefix used before marker identifying user section in a wp-config.php file.
	const USER_SECTION_PREFIX = '//';

	/**
	 * The path to the wp-config.php file.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private string $filename;

	/**
	 * Construct an object.
	 *
	 * @since 1.0.0
	 *
	 * @param string $filename The path to the wp-config.php file.
	 */
	public function __construct( string $filename ) {
		$this->filename = $filename;
	}

	/**
	 * The path to the actual file managed by the object.
	 *
	 * @since 1.0.0
	 *
	 * @return string The file path.
	 */
	public function filename() : string {
		return $this->filename;
	}

	/**
	 * Get the object representing the wp-config file used for the current session.
	 *
	 * @since 1.0.0
	 *
	 * @return wp_config The object.
	 */
	public static function current_wp_config() : wp_config {
		$paths       = new \calmpress\calmpress\Paths();
		$config_file = $paths->wp_config_file();

		return new wp_config( $config_file );
	}

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
		preg_match( '#^define\(\s*([\"\\\'][A-Z_a-z]*[\"\\\'])\s*,\s*([^\)]*)\s*\)\s*;\s*(\\/\\/.*)?#', $trimmed, $matches );
		if ( 2 >= count( $matches ) ) {
			// No matches for the two essential parts.
			return false;
		}
		$delim = substr( $matches[1], 0, 1 );
		if ( substr( $matches[1], -1, 1 ) !== $delim ) {
			// First and last quote style do not match.
			return false;
		}
		$second = trim( $matches[2] );
		if ( empty( $second ) ) {
			// No value as second parameter to the define.
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

		if ( substr( $second, -1, 1 ) !== $delim ) {
			// First and last quote style do not match.
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
	 * @return string The sanitized string.
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

	/**
	 * Get the user section from the file.
	 *
	 * @since 1.0.0
	 */
	public function user_section_in_file() : string {
		/*
		 * WordPress associate this function only with admin.
		 * As we want to avoid making structural changes to file
		 * in order to keep merging easy we need to use this hack.
		 */
		if ( ! function_exists( 'extract_from_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		$lock  = new \calmpress\filesystem\Path_Lock( $this->filename );
		$lines = extract_from_markers( $this->filename, self::USER_SECTION_MARKER, self::USER_SECTION_PREFIX );
		return join( "\n", $lines );
	}

	/**
	 * Save a string as the user section in the file.
	 *
	 * The string is sanitized before save.
	 *
	 * @since 1.0.0
	 *
	 * @param string                             $settings The settings to store in the user section.
	 * @param \calmpress\credentials\credentials $credentials The credential that should be used to extract
	 *                             the stream and context which should be used for writting.
	 *
	 * @throws \Exception If the save had failed. The message contains the last php error.
	 */
	public function save_user_section_to_file( string $settings, \calmpress\credentials\credentials $credentials ) {
		$sanitized = static::sanitize_user_setting( $settings );

		/*
		 * WordPress associate this function only with admin.
		 * As we want to avoid making structural changes to file
		 * in order to keep merging easy we need to use this hack.
		 */
		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		$stream = $credentials->stream_url_from_path( $this->filename );
		$lock   = new \calmpress\filesystem\Path_Lock( $this->filename );
		if ( ! insert_with_markers( $stream, self::USER_SECTION_MARKER, $sanitized, self::USER_SECTION_PREFIX, $credentials->stream_context() ) ) {
			// Write failed.
			throw new \Exception( \calmpress\utils\last_error_message() );
		}
	}

	/**
	 * The content of the file that will result from applying the user settings.
	 *
	 * @since 1.0.0
	 *
	 * @param string $user_settings The user setting to apply.
	 *
	 * @return string The content of the file after the settings are applied.
	 */
	public function expected_file_content( string $user_settings ) : string {
		$setting = static::sanitize_user_setting( $user_settings );
		$insertion = explode( "\n", $setting );

		$lock    = new \calmpress\filesystem\Path_Lock( $this->filename );
		$current = file_get_contents( $this->filename );

		// Split the content to lines based on all possible line endings.
		$lines = preg_split( "/\r\n|\n|\r/", $current );

		$newlines = insert_with_markers_into_array( $lines, self::USER_SECTION_MARKER, $insertion, self::USER_SECTION_PREFIX );

		// Generate the new file data.
		$new_file_data = implode( "\n", $newlines );
		return $new_file_data;
	}
}
