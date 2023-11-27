<?php
/**
 * Helper class to create a dummy of PHPMailer for testing.
 */

require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

/**
 * Used to create "PHPMailer" objects which do not actually try to send
 * mail.
 */
class dummy_PHPMailer extends \PHPMailer\PHPMailer\PHPMailer {

	public $Mailer = 'dummy';

	/**
	 * Use the constructor to change validator to the one
	 * set by wp_mail.
	 */
	public function __construct() {
		self::$validator = static function ( $email ) {
			return (bool) is_email( $email );
		};

		parent::__construct( true );
	}

	/**
	 * Need to override this to avoid setting mailer back to mail.
	 */
	public function isMail() {}

	/**
	 * just don't send anything.
	 */
	public function dummySend( $headers_str, $content ) {
	}
}
