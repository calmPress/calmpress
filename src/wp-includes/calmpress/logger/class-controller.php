<?php
/**
 * An implementation of a system log controller.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\logger;

/**
 * An implementation of a system log controller, implemented as singleton.
 *
 * Provides API and utilities to add log entries to file based error,
 * warning, info and audit logs.
 * The alarm logger intended to log error which must be fixed right now,
 * The warning logger intended to log warnings tht should be fixed ASAP,
 * The info logger intended to log information about fixed that should be done
 * some time in the future
 * The audit logger intended to log information about system state change that
 * might be benfitial in understanding current state and finding security
 * problems.
 * The slow query log intended to log information about DB queries whic were
 * executed slower than a threshold. slow queries do not indicate by
 * themself a fatal condition but might be a leading indicator to one.
 *
 * @since 1.0.0
 */
class Controller {

	/**
	 * The logger used to log error messages, by default a file logger
	 * 
	 * @var Logger
	 * 
	 * @since 1.0.0
	 */
	static protected Logger $error_logger;

	/**
	 * The logger used to log audit messages, by default a file logger
	 * 
	 * @var Logger
	 * 
	 * @since 1.0.0
	 */
	static protected Logger $audit_logger;

	/**
	 * The logger used to log warning messages, by default a file logger
	 * 
	 * @var Logger
	 * 
	 * @since 1.0.0
	 */
	static protected Logger $warnings_logger;

	/**
	 * The logger used to log slow queries, by default a file logger
	 * 
	 * @var Logger
	 * 
	 * @since 1.0.0
	 */
	static protected Logger $slow_queries_logger;

	/**
	 * The logger used to log info messages, by default a file logger
	 * 
	 * @var Logger
	 * 
	 * @since 1.0.0
	 */
	static protected Logger $info_logger;

	/**
	 * A flag indicating if a error is being processed to be able
	 * to avoid additional errors while gathering information for the log
	 * entry.
	 *
	 * @var bool
	 * 
	 * @since 1.0.0
	 */
	static protected bool $handling_error = false;

	/**
	 * Initialize the controller.
	 *
	 * @since 1.0.0
	 */
	static public function init() {
		$dir                       = self::log_directory_path();
		self::$error_logger        = new File_Logger( $dir, 'error' );
		self::$warnings_logger     = new File_Logger( $dir, 'warning' );
		self::$info_logger         = new File_Logger( $dir, 'info' );
		self::$audit_logger        = new File_Logger( $dir, 'audit' );
		self::$slow_queries_logger = new File_Logger( $dir, 'slow_queries' );

		// Let errors propogate when running test with phpunit
		if ( ! defined( 'WP_RUN_CORE_TESTS' ) ) {
			set_error_handler( __CLASS__ . '::error_handler' );
			set_exception_handler( __CLASS__ . '::exception_handler' );
			register_shutdown_function( __CLASS__ . '::shutdown_handler' );
		}

		// Cleanup logs once a day.
		add_action( 'logs_cleanup', __CLASS__ . '::purge_old_log_entries' );
	}

	/**
	 * The path to the directory in which core log files are located.
	 *
	 * @since 1.0.0
	 *
	 * @return string The path
	 */
	static public function log_directory_path(): string {
		return WP_CONTENT_DIR . '/.private/logs';
	}

	/**
	 * Handle errors trigerred by trigger_error categorise them by severity
	 * and "send" them to the appropriate log.
	 * 
	 * @param int    $errno   The error number.
	 * @param string $errstr  The message.
	 * @param string $errfile The file from which the error was triggered.
	 * @param int    $errline The line at which the error was triggered.
	 *
	 * @return bool  Always true to avoid php native processing.
	 *
	 * @since 1.0.0
	 */
	static public function error_handler(
		int $errno,
		string $errstr,
		string $errfile,
		int $errline
	) : bool {
		// Detect if errors are supressed using the @ operator, and if they do avoid
		// logging as it indicates that errors might be expected in the normal flow
		// of execution.
		// php 8.0.0 weirdness https://www.php.net/manual/en/language.operators.errorcontrol.php
		if ( ( E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR | E_PARSE ) === error_reporting() ) {
    		return true;
		}

		// skip if an error already being processed
		if ( self::$handling_error ) 
			return true;
		self::$handling_error = true;

		switch ( $errno ) {
			case E_USER_ERROR:
			case E_ERROR:
			case E_PARSE:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_RECOVERABLE_ERROR:
				self::log_error_message(
					$errstr,
					$errfile,
					$errline,
					self::current_user_id(),
					self::stack_trace( 1 ),
					self::request_info( 20 )
				);
				die( 500 );
				break;
			case E_WARNING:
			case E_CORE_WARNING:
			case E_COMPILE_WARNING:
			case E_USER_WARNING:
			case E_DEPRECATED:
			case E_USER_DEPRECATED:
				self::log_warning_message(
					$errstr,
					$errfile,
					$errline,
					self::current_user_id(),
					self::stack_trace( 1 ),
					self::request_info( 20 )
				);
				break;
			default:
				self::log_info_message(
					$errstr,
					$errfile,
					$errline,
				);
				break;
		}

		self::$handling_error = false;
		return true;
	}

	/**
	 * Shutdown handler, try to log unhandled error and log slow request.
	 *
	 * @since 1.0.0
	 */
	static public function shutdown_handler(): void {
		// Log requests which take longer than 16 seconds to generate.
		// It is not a problem by itself but a leadin indication that
		// some process is slow and might get slower on busy server.
		// Value of 16 as backup "slice" might take about 15 seconds.
		$process_time = microtime( true ) - $_SERVER['REQUEST_TIME_FLOAT'];
		if ( $process_time > 16.0 ) {
			self::log_warning_message(
				'Slow request, took ' . $process_time,
				'',
				0,
				self::current_user_id(),
				'',
				self::request_info( 20 )
			);
		}

		$error = error_get_last();
		if ( $error ) {
			self::error_handler( $error['type'], $error['message'], $error['file'],$error['line'] );
		}
	 }

	/**
	 * Handle uncuaght exception.
	 * 
	 * @param Throwable $exception The exception.
	 *
	 * @since 1.0.0
	 */
	static public function exception_handler( \Throwable $exception ) : void {
		// skip if an error already being processed
		if ( self::$handling_error ) 
			return;
		self::$handling_error = true;

		self::log_error_message(
			$exception->getMessage(),
			$exception->getFile(),
			$exception->getLine(),
			self::current_user_id(),
			$exception->getTraceAsString(),
			self::request_info( 20 )
		);

		self::$handling_error = false;
		die( 500 );
	}

	/**
	 * Generate human readable stack trace while discarding trace of irrelevant
	 * function calls.
	 * 
	 * @param int $discard_frames The number of frames to discard in addition
	 *                            to the frame relevant to the call of this function.
	 *
	 * @return string The stack trace as a string.
	 *
	 * @since 1.0.0
	 */
	static public function stack_trace( int $discard_frames ) {
		ob_start();
        debug_print_backtrace();
        $trace = ob_get_contents();
        ob_end_clean();

		// Remove the line with the info on the call to this function.
		$parts = explode("\n", $trace, 1 + $discard_frames );
		$trace = $parts[ $discard_frames ];
		return $trace;
	}

	/**
	 * Produced a string value which is not too long to be logged.
	 * 
	 * If the supplied string is fitting the requested max length it is
	 * returned as is. If it is too long it is trimmed to the requested length
	 * and "..." add to its end and an additional indicator to its length.
	 *
	 * @param string $s                 The string.
	 * @param int    $max_string_length The maximal length of a string.
	 *
	 * @return string the string as described above.
	 *
	 * @since 1.0.0
	 */
	static protected function trimmmed_string( string $s, int $max_string_length ) : string {
		if ( strlen( $s ) <= $max_string_length ) {
			return $s;
		}

		return substr( $s, 0, $max_string_length ) . '...' . ' (' . strlen( $s ) . ')';
	}

	/**
	 * Generate a human readable string representing a content of
	 * an array element.
	 * 
	 * There are 3 cases handled differently
	 * - A simple string which is printed as is
	 * - An array which is recursively printed with indentation.
	 * - A string which contains valid json is printed like an array. 
	 *
	 * @param string $key            The key of the element in the array.
	 * @param string|array $value    The value of the element.
	 * @param int $offset            Indicator for the amount of indentation
	 *                               Each identation step is of two spaces.
	 * @param int $max_string_length The max amount of characters used to
	 *                               display a string value. Longer strings
	 *                               are trimmed and "..." appended to them
	 *
	 * @return string The representing string.
	 *
	 * @since 1.0.0
	 */
	static protected function pretty_print_array_element(
		string $key,
		$value,
		int $offset,
		int $max_string_length
	) : string {
		$output = '';
		$json = json_decode( $value, true );
		if ( is_array( $json ) ) {
			$output .= str_repeat('  ', $offset) . $key . " (json) => [\n";
			$output .= self::pretty_print_array( $json, $offset + 1, $max_string_length );
			$output .= str_repeat('  ', $offset) . "]\n";
		}
		if ( is_array( $value ) ) {
			$output .= str_repeat('  ', $offset) . $key . " => [\n";
			$output .= self::pretty_print_array( $value, $offset + 1, $max_string_length );
			$output .= str_repeat('  ', $offset) . "]\n";
		}
		$output .= str_repeat('  ', $offset ) . $key . ' => ' . self::trimmmed_string( $value, $max_string_length ) . "\n";

		return $output;
	}

	/**
	 * Generate a human readable string representing a content of
	 * an array.
	 *
	 * @param array $ar            The array.
	 * @param int $max_string_length The max amount of characters used to
	 *                               display a string value. Longer strings
	 *                               are trimmed and "..." appended to them
	 *
	 * @return string The representing string.
	 *
	 * @since 1.0.0
	 */
	static protected function pretty_print_array(
		array $ar,
		int $max_string_length
	) {
		$output = '';
		foreach ( $ar as $key => $value ) {
			$output .= self::pretty_print_array_element( $key, $value, 0, $max_string_length );
		}
		return $output;
	}

	/**
	 * A safe way to get the current user id.
	 *
	 * A wrapper around get_current_user_id that makes sure the function is
	 * accessable and will not generate exception.
	 *
	 * @return int The user id if a user was logged in and information accessable or 0.
	 * 
	 * @since 1.0.0
	 */
	static public function current_user_id(): int {
		if ( function_exists( 'get_current_user_id' ) ) {
			try {
				return get_current_user_id();
			} catch ( \Exception $e ) {
				;
			}
		}

		return 0;
	}

	/**
	 * Generate a string containing a human readable information about
	 * The URL which was accessed and relevant $_GET and $_POST and $_FILES values.
	 *
	 * @param int $max_string_length The maximal length of strings output to
	 *                               the log.
	 *                               This is used to somewhat control the size of
	 *                               the log entry.
	 *                               It applies to string data in places it is deemed
	 *                               that knowledge of the full string is less useful.
	 *                               File path and POST and json field names will not
	 *                               have any impact by this setting.
	 *                                 
	 * @return string
	 *
	 * @since 1.0.0
	 */
	static public function request_info( int $max_string_length ) : string {
		$output = $_SERVER[ 'REQUEST_URI' ] . "\n";
		if ( ! empty( $_GET ) ) {
			$output .= "GET parameters\n";
			$output .= self::pretty_print_array( $_GET, $max_string_length );
		}
		if ( ! empty( $_POST ) ) {
			$output .= "POST parameters\n";
			$output .= self::pretty_print_array( $_POST, $max_string_length );
		}
		if ( ! empty( $_FILES ) ) {
			$output .= "FILES parameters\n";
			foreach ( $_FILES as $key => $value ) {
				$output .= "$key => [ ";
				$output .= self::pretty_print_array( $value, $max_string_length );
				$output .= "]\n";
			}
		}

		// for request which are not normal web form submission
		// add the content of the body of the request, decoding it if its
		// a valid json.
		// "normal" web form is detected by none empty $_POST and $_FILES. While
		// The better way might have been to detect the content-type header it
		// seems to be more error prone right now therefor better use a proxy to the 
		// php engine detection.
		if ( empty( $_POST ) && empty( $_FILES ) ) {
			$body = file_get_contents( 'php://input' );
			if ( $body ) {
				try {
					$json = json_decode( $body, true );
					$output .= "Body contains json\n";
					$output .= self::pretty_print_array( $json, $max_string_length );
				} catch ( \Exception $e ) {
					// $body is not a proper json, treat it as a string.
					$output .= "Body\n";
					$output .= self::trimmmed_string( $body, $max_string_length );
				}
			}
		}

		return $output;
	}

	/**
	 * Log a message in error log.
	 *
	 * @param string $message     The message to log.
	 * @param string $file_name   The name of the file in which the log generated.
	 * @param int    $line_number The line number in which the log generated.
	 * @param int    $user_id     The user id of the logged in user.
	 * @param string $stack_trace The stack trace to attach, in the format generate
	 *                            by debug_print_backtrace
	 *                            @see https://www.php.net/manual/en/function.debug-print-backtrace.php
	 * @param string $request     Information about the http request being handled.
	 *                            request_info can be used to get it.
	 *
	 * @since 1.0.0
	 */
	static public function log_error_message(
		string $message,
		string $file_name,
		int $line_number,
		int $user_id = 0,
		string $stack_trace = '',
		string $request = '',
		): void
	{
		self::$error_logger->log_message(
			$message,
			$file_name,
			$line_number,
			$user_id,
			$stack_trace,
			$request
		);
	}

	/**
	 * Log a message in warning log.
	 * 
	 * @param string $message     The message to log.
	 * @param string $file_name   The name of the file in which the log generated.
	 * @param int    $line_number The line number in which the log generated.
	 * @param int    $user_id     The user id of the logged in user.
	 * @param string $stack_trace The stack trace to attach, in the format generate
	 *                            by debug_print_backtrace
	 *                            @see https://www.php.net/manual/en/function.debug-print-backtrace.php
	 * @param string $request     Information about the http request being handled.
	 *                            request_info can be used to get it.
	 *
	 * @since 1.0.0
	 */
	static public function log_warning_message(
		string $message,
		string $file_name,
		int $line_number,
		int $user_id = 0,
		string $stack_trace = '',
		string $request = '',
		): void
	{
		self::$warnings_logger->log_message(
			$message,
			$file_name,
			$line_number,
			$user_id,
			$stack_trace,
			$request
		);
	}

	/**
	 * Log a message in slow queries log.
	 * 
	 * This method is tightly integrated with the structure on wp_db
	 * therefor it should probably not be used by other clients.
	 *
	 * @param string $query The SQL query to log.
	 * @param float  $time  The time it took to execute the query.
	 *
	 * @since 1.0.0
	 */
	static public function log_slow_query_message(
		string $query,
		float $time
		): void
	{
		self::$slow_queries_logger->log_message(
			$query . "\n" . 'Executed in ' . $time . " second\n",
			'',
			0,
			self::current_user_id(),
			self::stack_trace( 3 ),
			self::request_info( 20 )
		);
	}

	/**
	 * Log a message in info log.
	 *
	 * @param string $message     The message to log.
	 * @param string $file_name   The name of the file in which the log generated.
	 * @param int    $line_number The line number in which the log generated.
	 * @param int    $user_id     The user id of the logged in user.
	 * @param string $stack_trace The stack trace to attach, in the format generate
	 *                            by debug_print_backtrace
	 *                            @see https://www.php.net/manual/en/function.debug-print-backtrace.php
	 * @param string $request     Information about the http request being handled.
	 *                            request_info can be used to get it.
	 *
	 * @since 1.0.0
	 */
	static public function log_info_message(
		string $message,
		string $file_name,
		int $line_number,
		int $user_id = 0,
		string $stack_trace = '',
		string $request = '',
		): void
	{
		self::$info_logger->log_message(
			$message,
			$file_name,
			$line_number,
			$user_id,
			$stack_trace,
			$request
		);
	}

	/**
	 * Log a message in audit log. Unlike the error, warning and info logs
	 * which are intended to be helpful in finding and debuging issues the audit
	 * log should be use to trace significant user activity to help understnd
	 * how the system got to specific state.
	 *
	 * @param string $message     The message to log.
	 * @param string $file_name   The name of the file in which the log generated.
	 * @param int    $line_number The line number in which the log generated.
	 * @param int    $user_id     The user id of the logged in user.
	 * @param string $stack_trace The stack trace to attach, in the format generate
	 *                            by debug_print_backtrace
	 *                            @see https://www.php.net/manual/en/function.debug-print-backtrace.php
	 * @param string $request     Information about the http request being handled.
	 *                            request_info can be used to get it.
	 */
	static public function log_audit_message(
		string $message,
		string $file_name,
		int $line_number,
		int $user_id = 0,
		string $stack_trace = '',
		string $request = '',
		): void
	{
		self::$audit_logger->log_message(
			$message,
			$file_name,
			$line_number,
			$user_id,
			$stack_trace,
			$request
		);
	}

	/**
	 * Remove error, warning, info and audit log files older than 30 days.
	 * 
	 * @since 1.0.0
	 */
	static public function purge_old_log_entries() : void {
		self::$audit_logger->purge_old_log_entries( 30 );
		self::$error_logger->purge_old_log_entries( 30 );
		self::$info_logger->purge_old_log_entries( 30 );
		self::$warnings_logger->purge_old_log_entries( 30 );
		self::$slow_queries_logger->purge_old_log_entries( 30 );
	}
}