<?php
/**
 * An implementation of a logger for sent emails.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\logger;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * An implementation of a logger for sent emails.
 * 
 * Two logs are created for messages that are successfully sent (which do not mean
 * that they were delivered or read) and one for those that didn't even got sent.
 *
 * @since 1.0.0
 */
class Log_Emails {

	/**
	 * The logger used to log emails which where failed to send.
	 * 
	 * @var Logger
	 * 
	 * @since 1.0.0
	 */
	static protected Logger $failed_logger;

	/**
	 * The logger used to log successfully sent mails.
	 * 
	 * @var Logger
	 * 
	 * @since 1.0.0
	 */
	static protected Logger $sent_logger;

	/**
	 * Initialize loggers and cleanup handler. 
	 */
	static public function init():void {
		$dir                 = Controller::log_directory_path();
		self::$failed_logger = new File_Logger( $dir, 'failed_emails' );
		self::$sent_logger   = new File_Logger( $dir, 'sent_emails' );

		// Cleanup logs once a day.
		add_action( 'logs_cleanup', __CLASS__ . '::purge_old_log_entries' );
	}

	/**
	 * Create a human readable email address from name and address
	 * in the format of {name} <{email}>
	 *
	 * @since 1.0.0
	 * 
	 * @param string $name  The name of the owner of the email address.
	 * @param string $email The email address.
	 * 
	 * @return string the readable email address.
	 */
	static private function readable_address( string $name, string $email ):string {
		// No sanitization of input as it is assumed to be something retrieved
		// from the PHPMailer APIs and sanitized by it before that.
		if ( '' === $name ) {
			return $email;
		}

		return "$name <$email>";
	}

	/**
	 * Create a human readable email address from an array containing element with
	 * name and address. The result is in the format of
	 * {name1} <{email2}>, {name2} <{email2}>
	 *
	 * @since 1.0.0
	 * 
	 * @param array[] $addresses The array containing the addresses with each element
	 *                           is an array with first element the email address
	 *                           and the second is the name
	 * 
	 * @return string the readable email addresses, empty if no address were given.
	 */
	static private function readable_address_collection( array $addresses ):string {
		$ret = '';
		foreach ( $addresses as $address ) {
			if ( '' !== $ret ) {
				$ret .= ', ';
			}
			$ret .= self::readable_address( $address[1], $address[0] );
		}
		return $ret;
	}

	/**
	 * Format mail content for output.
	 *
	 * @param PHPMailer $mailer The phpmailer control object while sending the mail.
	 * @param string    $log_verbosity The level of verbosity of the log. Valid values
	 *                                 'recipients' - show recipients info and subject
	 *                                 'full' - Should content as well as recipients
	 *                                 and subject.
	 *
	 * @since 1.0.0
	 *
	 * @return string The human readable output.
	 */
	static protected function output_email( PHPMailer $mailer, string $log_verbosity ) : string {
		$to     = self::readable_address_collection( $mailer->getToAddresses() );
		$from   = self::readable_address( $mailer->FromName, $mailer->From );
		$cc     = self::readable_address_collection( $mailer->getCCAddresses() );
		$bcc    = self::readable_address_collection( $mailer->getBCCAddresses() );
		$reply  = self::readable_address_collection( $mailer->getReplyToAddresses() );
		
		$output  = 'Subject: ' . $mailer->Subject . "\n";
		$output .= 'To: ' . $to  . "\n";
		if ( $cc ) {
			$output .= 'CC: ' . $cc  . "\n";
		}
		if ( $bcc ) {
			$output .= 'BCC: ' . $bcc  . "\n";
		}
		$output .= 'From: ' . $from  . "\n";
		$output .= 'Reply-To: ' . $reply  . "\n";


		if ( $log_verbosity === 'full' ) {
			$attachments = [];
			foreach ( $mailer->getAttachments() as $attachment ) {
				// For a string based attachment (probably base64), indicate its name
				if ( $attachment[5] ) {
					$attachments[] = $attachment[2] . ' (string)';
				} else {
					$attachments[] = $attachment[0];
				}
			}
			$output.= 'Attachments: ' . join( ', ', $attachments ) . "\n";
			
			// to log the content we need to first detect its type.
			// Text mails can just be logged as is, but HTML ones need
			// to be implified to text, replacing <br> with line ends
			// and removing all html tags. 
			// ... and not trying to even think about 
			if ( $mailer->ContentType === PHPMailer::CONTENT_TYPE_PLAINTEXT ) {
				$output .= 'Body: ' . $mailer->Body . "\n";
			} else {
				$output .= 'Body (HTML simplified)' . wp_kses( $mailer->Body, 'strip' ) ."\n";
			}
		}

		return $output;
	}

	/**
	 * Log sent successfuly sent mails.
	 *
	 * @param PHPMailer $mailer        The phpmailer control object while sending the mail.
	 * @param string    $log_verbosity The level of verbosity of the log. Valid values
	 *                                 'recipients' - show recipients info and subject
	 *                                 'full' - Should content as well as recipients
	 *                                 and subject.
	 *
	 * @since 1.0.0
	 */
	static public function mail_success( PHPMailer $mailer, string $log_verbosity ): void {
		$output = self::output_email( $mailer, $log_verbosity );
		self::$sent_logger->log_message( $output );
	}

	/**
	 * Log mail which failed to send.
	 *
	 * @param PHPMailer  $mailer The phpmailer control object while sending the mail.
	 * @param \Exception $ex     The exception raised by PHPMailer.
	 *
	 * @since 1.0.0
	 */
	static public function mail_failed( PHPMailer $mailer, \Exception $ex ): void {

		$output  = $ex->getMessage() . "\n";
		$output .= self::output_email( $mailer, 'recipients' );
		self::$failed_logger->log_message( $output );
	}

	/**
	 * Remove failed and sent log files older than 30 days.
	 * 
	 * @since 1.0.0
	 */
	static public function purge_old_log_entries() : void {
		self::$failed_logger->purge_old_log_entries( 30 );
		self::$sent_logger->purge_old_log_entries( 30 );
	}
}