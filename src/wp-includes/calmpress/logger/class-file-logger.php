<?php
/**
 * An implementation of a file logger.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\logger;

/**
 * An implementation of a file logger.
 *
 * The logger is initialized with a path to the directory in which
 * log files should be stored. The log files names are in the format
 * of {prefix}-{utc date}.log and contain line with the server time in which the
 * log was added and the message.
 * The format of each log entry tries to emulate the php error file with the addition
 * information of logged in user and url with which the server was accessed.
 *
 * @since 1.0.0
 */
class File_Logger implements Logger {

	/**
	 * The base path of the directory in which the log files should be stored.
	 * 
	 * @var string
	 * 
	 * @since 1.0.0
	 */
	readonly private string $directory;

	/**
	 * The prefix to be used for generated files.
	 * 
	 * @var string
	 * 
	 * @since 1.0.0
	 */
	readonly private string $prefix;

	/**
	 * Construct a file logger with the file to log to.
	 *
	 * @param string $directory The directory in which to store log files.
	 *
	 * @throws \RuntimeException if directory is not writable or can not be created.
	 *
	 * @since 1.0.0
	 */
	public function __construct( string $directory, string $prefix ) {
		$this->prefix = $prefix;

		$this->directory = rtrim( $directory, '/' );
		if ( ! file_exists( $this->directory ) ) {
			if ( ! @mkdir( $this->directory , 0755, true ) ) {
				throw new \RuntimeException( 'Can not create log directory at ' . $this->directory );
			}
		}

		if ( ! wp_is_writable( $this->directory ) ) {
			throw new \RuntimeException( 'Log directory is not writable directory at ' . $this->directory );
		}

	}

	/**
	 * Log a message.
	 * 
	 * All information that deemed to be useful may be passed but it is up to
	 * the implementation to decide how to use it.
	 *
	 * @param string $message     The message to log.
	 * @param string $file_name   The name of the file in which the log generated.
	 * @param int    $line_number The line number in which the log generated.
	 * @param int    $user_id     The user id of the logged in user.
	 * @param string $stack_trace The stack trace to attach, in the format generate
	 *                            by debug_print_backtrace
	 *                            @see https://www.php.net/manual/en/function.debug-print-backtrace.php
	 * @param string $request     Information about the http request being handled.
	 *
	 * @since 1.0.0
	 */
	public function log_message(
		string $message,
		string $file_name = '',
		int $line_number = -1,
		int $user_id = 0,
		string $stack_trace = '',
		string $request = '',
		): void
	{
		$date = gmdate( 'Y-m-d' );
		$time = gmdate( 'H:i:s' );
		$file = $this->directory . '/' . $this->prefix . '-' . $date . '.log';
		$in   = '';
		if ( $file_name ) {
			$in = ' in ' . $file_name;
			if ( -1 !== $line_number ) {
				$in .= ':' . $line_number;
			}
		}
		$s = '[' .$time. '] ' . $message . $in . ' user is ' . $user_id . "\n";

		if ( ! empty( $stack_trace ) ) {
			$s .= "Stack trace:\n$stack_trace\n";
		}

		if ( ! empty( $request ) ) {
			$s .= "Request:\n$request\n";
		}

		// Use file lock to prevent multiple writes at same time from
		// other processes which handle requests.
		file_put_contents( $file, $s , FILE_APPEND | LOCK_EX );
	}

	/**
	 * Remove old log entries.
	 * 
	 * For simplicity just remove files older than $days_to_keep;
	 *
	 * @param int $days_to_keep The number of days a log should be kept, any log
	 *                          older than that is removed.
	 *
	 * @since 1.0.0
	 */
	public function purge_old_log_entries( int $days_to_keep ) : void {
		$files = glob( $this->directory . '/' . $this->prefix . '-*.log');
		$threshold = strtotime( '-' . $days_to_keep . ' day' );
  
		foreach ($files as $file) {
			if ($threshold >= filemtime($file)) {
				unlink($file);
			}
		}
	}
}