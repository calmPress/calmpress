<?php
/**
 * Implementation of an email "type".
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * A representation of email related information.
 * 
 * Extending this class is discouraged. If it just has to be done you should
 * figure a way to mutate the email with all mutators which were registered with
 * this class.
 *
 * @since 1.0.0
 */
class Email {

	use \calmpress\observer\Static_Mutation_By_Ref_Observer_Collection;

	/**
	 * The email subject.
	 * 
	 * @since 1.0.0
	 */
	private string $subject;

	/**
	 * The email textual content.
	 * 
	 * @since 1.0.0
	 */
	private string $content;

	/**
	 * The emails content type. a true value indicates that the email
	 * contains HTML content, false indicates pure text.
	 * 
	 * @since 1.0.0
	 */
	private bool $content_is_html;

	/**
	 * The "TO" adresses in the "to" header.
	 *
	 * @var Email_Address[]
	 * 
	 * @since 1.0.0
	 */
	private array $to = [];

	/**
	 * The "CC" addresses in the "CC" header.
	 *
	 * @var Email_Address[]
	 * 
	 * @since 1.0.0
	 */
	private array $cc = [];

	/**
	 * The "BCC" addresses in the "CC" header.
	 *
	 * @var Email_Address[]
	 * 
	 * @since 1.0.0
	 */
	private array $bcc = [];

	/**
	 * The sender address to be used in the "From" header overriding the system default.
	 * A value of null indicates that defaults are used.
	 *
	 * @var ?Email_Address
	 * 
	 * @since 1.0.0
	 */
	private ?Email_Address $from = null;

	/**
	 * Hold the reply-to addresses to be used in the "Reply-To" header.
	 *
	 * @var Email_Address[]
	 * 
	 * @since 1.0.0
	 */
	private array $reply_to = [];

	/**
	 * Hold the address to which bounce messages should sent. To be used in the
	 * "Reply-To" header.
	 * 
	 * @since 1.0.0
	 */
	private string $return_path = '';

	/**
	 * The collection of the attachments to send with the email.
	 *
	 * @var Email_Attachment[]
	 */
	private array $attachments = [];

	/**
	 * Create Email object.
	 */
	public function __construct(
		string $subject,
		string $content,
		bool   $content_is_html,
		Email_Address ...$to,
	) {
		$this->set_subject( $subject );
		$this->content         = $content;
		$this->content_is_html = $content_is_html;
		$this->to              = $to;
	}

	/**
	 * Set the subject of the email. Value is sanitized to remove leading and
	 * trailing spaces and line breaks.
	 * 
	 * @since calmPress 1.0.0
	 */
	public function set_subject( string $subject ) : void {
		$this->subject = trim( str_replace( ["\r", "\n"], '', $subject ) );
	}

	/**
	 * The subject of the email.
	 * 
	 * @since calmPress 1.0.0
	 */
	public function subject() : string {
		return $this->subject;
	}

	/**
	 * Set the textual content of the email.
	 * 
	 * @since 1.0.0
	 */
	public function set_content( string $content, bool $content_is_html ) : void {
		$this->content         = $content;
		$this->content_is_html = $content_is_html;
	}

	/**
	 * The textual content of the email.
	 * 
	 * @since 1.0.0
	 *
	 * @return string The content.
	 */
	public function content() : string {
		return $this->content;
	}

	/**
	 * Whether the textual content is formatted as HTML.
	 * 
	 * @since 1.0.0
	 *
	 * @return true if content formatted as HTML, false if a pure text.
	 */
	public function content_is_html() : bool {
		return $this->content_is_html;
	}

	/**
	 * Set the destination address(es) ("TO") of the email.
	 * 
	 * @since calmPress 1.0.0
	 *
	 * @param Email_Address $to Variable number of addresses to which to send the email.
	 *                          If none provided there will be no destinations.
	 */
	public function set_to_addresses( Email_Address ...$to ) : void {
		$this->to = $to;
	}

	/**
	 * Add a destination address ("TO") of the email.
	 * 
	 * @since calmPress 1.0.0
	 * 
	 * @param Email_Address $to An email address to add to the list of destination
	 *                          email addresses.
	 */
	public function add_to_address( Email_Address $to ) : void {
		$this->to[] = $to;
	}

	/**
	 * The email addresses to which the email should be sent to.
	 *
	 * The values of the keys is undefined.
	 * 
	 * @since calmPress 1.0.0
	 * 
	 * @return Email_Address[] A collection of the email addresses to which to send the
	 *                         email. Empty array indicates that none are configured.
	 */
	public function to_addresses():array {
		return $this->to;
	}

	/**
	 * Set the CC destination address(es) of the email.
	 * 
	 * @since calmPress 1.0.0
	 *
	 * @param Email_Address $cc Variable number of address(es) to which
	 *                          to send the email as CC.
	 *                          If none provided there will be no destinations.
	 */
	public function set_cc_addresses( Email_Address ...$cc ) : void {
		$this->cc = $cc;
	}

	/**
	 * Add a CC destination address of the email.
	 * 
	 * @since calmPress 1.0.0
	 * 
	 * @param Email_Address $cc An email address to add to the list of destination
	 *                          email addresses sent as CC.
	 */
	public function add_cc_address( Email_Address $cc ) : void {
		$this->cc[] = $cc;
	}

	/**
	 * The email addresses to which the email should be sent to as CC.
	 *
	 * The values of the keys is undefined.
	 * 
	 * @since calmPress 1.0.0
	 * 
	 * @return Email_Address[] A collection of the email addresses to which to send the
	 *                         email as CC. Empty array indicates that none are
	 *                         configured.
	 */
	public function cc_addresses():array {
		return $this->cc;
	}

	/**
	 * Set the BCC destination address(es) of the email.
	 * 
	 * @since calmPress 1.0.0
	 *
	 * @param Email_Address $bcc Variable number of address that denote the
	 *                           to which to send the email as BCC.
	 *                           If none provided there will be no destinations.
	 */
	public function set_bcc_addresses( Email_Address ...$bcc ) : void {
		$this->bcc = $bcc;
	}

	/**
	 * Add a BCC destination address of the email.
	 * 
	 * @since calmPress 1.0.0
	 * 
	 * @param Email_Address $bcc An email address to add to the list of destination
	 *                           email addresses to send the email as BCC.
	 */
	public function add_bcc_address( Email_Address $bcc ) : void {
		$this->bcc[] = $bcc;
	}

	/**
	 * The email addresses to which the email should be sent to as BCC.
	 *
	 * The values of the keys is undefined.
	 * 
	 * @since calmPress 1.0.0
	 * 
	 * @return Email_Address[] A collection of the email addresses to which to send the
	 *                         email as BCC. Empty array indicates that none are
	 *                         configured.
	 */
	public function bcc_addresses():array {
		return $this->bcc;
	}

	/**
	 * Set the sender (From) address of the email. This will override the system
	 * settings.
	 * 
	 * This should be used very carefully as SMTP mail severs my reject sending
	 * emails for addresses which are "unknown" to them. 
	 * 
	 * @since calmPress 1.0.0
	 *
	 * @param ?Email_Address $sender The address to be used as a sender.
	 *                               If null is provided the system setting will be used.
	 */
	public function set_sender( ?Email_Address $sender ) : void {
		$this->from = $sender;
	}

	/**
	 * The email address which will be used as the sender of the email overriding
	 * the global settings. 
	 * 
	 * @since calmPress 1.0.0
	 * 
	 * @return ?Email_Address The email address, null if none is configured and system
	 *                        settings are used.
	 */
	public function sender():?Email_Address {
		return $this->from;
	}

	/**
	 * Set the destination address(es) to be set as the reply to (Reply-To) address(es)
	 * of the email.
	 * 
	 * @since calmPress 1.0.0
	 *
	 * @param Email_Address $reply_to Variable number of address that denote
	 *                                to which to send the email as BCC.
	 *                                If none provided there will be no destinations
	 *                                added.
	 */
	public function set_reply_to_addresses( Email_Address ...$reply_to ) : void {
		$this->reply_to = $reply_to;
	}

	/**
	 * Add a destination address to be set as a reply to (Reply-To) address
	 * of the email.
	 * 
	 * @since calmPress 1.0.0
	 * 
	 * @param Email_Address $reply_to An email address to add to the list of reply to
	 *                                email addresses.
	 */
	public function add_reply_to_address( Email_Address $reply_to ) : void {
		$this->reply_to[] = $reply_to;
	}

	/**
	 * The email addresses which will be indicated as the reply to (Reply-To) address
	 * of the email.
	 *
	 * The values of the keys is undefined.
	 * 
	 * @since calmPress 1.0.0
	 * 
	 * @return Email_Address[] A collection of the email addresses which will be 
	 *                         indicated as the reply to address. Empty array indicates
	 *                         that none are configured.
	 */
	public function reply_to_addresses():array {
		return $this->reply_to;
	}

	/**
	 * Set the attachments to be used in the email.
	 *
	 * @since 1.0.0
	 *
	 * @param Email_Attachment $attachments The attachment(s) to use in the email.
	 */
	public function set_attachments( Email_Attachment ...$attachments ): void {
		$this->attachments = $attachments;
	}

	/**
	 * Add an attachment to the email.
	 *
	 * @since 1.0.0
	 *
	 * @param Email_Attachment $attachment The attachment to add.
	 */
	public function add_attachment( Email_Attachment $attachment ): void {
		$this->attachments[] = $attachment;
	}

	/**
	 * The attachments which will be used in the email.
	 *
	 * The values of the keys is undefined.
	 *
	 * @since 1.0.0
	 *
	 * @return Email_Attachment[] the attachments.
	 */
	public function attachments(): array {
		return $this->attachments;
	}

	/**
	 * Set the email address to which bounce messages should be sent
	 * (Return-Path header), or do not indicate such an address when the email is sent.
	 * 
	 * @since calmPress 1.0.0
	 *
	 * @param string $address The email address to use for bounce mails or empty if
	 *                        no bounce notification is wanted. 
	 *
	 * @throws RuntimeException If $address is not a valid email address.
	 */
	public function set_bounce_address( string $address ) : void {

		// Delegate trimming,, sanitization and validation check.
		if ( $address !== '' ) {
			$t = new Email_Address( $address );
			$address = $t->address;
		}

		$this->return_path = $address;
	}

	/**
	 * The email address of the bounce emails (Return-Path header).
	 * 
	 * @since calmPress 1.0.0
	 *
	 * @return string The email address used for bounce mails, empty if non specified.
	 */
	public function bounce_address() : string {
		return $this->return_path;
	}

	/**
	 * Register a mutatur to be called before an email is sent.
	 *
	 * @since 1.0.0
	 *
	 * Email_Mutator $mutator The object implementing the mutation observer.
	 */
	public static function register_mutator( Email_Mutator $mutator ): void {
		self::add_observer( $mutator );
	}

	/**
	 * Utility to iterate over an array of objects and extract values of a method
	 * of the object into an array.
	 * 
	 * Keys are preserved.
	 * 
	 * @since 1.0.0
	 *
	 * @param array  $ar     The array of objects to iterate over.
	 * @param string $method The object's metode to call to extract the value.
	 *                       A property name can be used as well.
	 * 
	 * @return array The values.
	 */
	private static function iterate_objects_method_into_array(array $ar, string $method ):array {
		$ret = [];
		foreach ( $ar as $key => $element ) {
			if ( method_exists( $element, $method ) ) {
				$ret[ $key ] = $element->$method();
			} else {
				$ret[ $key ] = $element->$method;
			}
		}

		return $ret;
	}

	/**
	 * Utility to iterate over an array of objects and create a string which has
	 * a list of the values of a method of the objects separated by a ",".
	 * 
	 * Order might not be preserved.
	 * 
	 * @since 1.0.0
	 *
	 * @param array  $ar The array of objects to iterate over.
	 * @param string $method The object metod to call to extract the value.
	 * 
	 * @return string The values.
	 */
	private static function iterate_objects_method_into_list( array $ar, string $method ):string {
		$ret = self::iterate_objects_method_into_array( $ar, $method );

		return join( ',', $ret );
	}

	/**
	 * Send the email.
	 * 
	 * If there are two attachments with the same title, one of them will be changed
	 * while preserving the original title as the prefix to the newly generate one.
	 *
	 * @since 1.0.0
	 */
	public function send(): void {
		// Let mutators change whatever needed.
		self::mutate_by_ref( $this );

		$to  = self::iterate_objects_method_into_array( $this->to, 'full_address' );
		$headers = [];
		if ( ! empty( $this->cc ) ) {
			$headers[] = 'cc: ' . self::iterate_objects_method_into_list( $this->cc, 'full_address' );
		}

		if ( ! empty( $this->bcc ) ) {
			$headers[] = 'bcc: ' . self::iterate_objects_method_into_list( $this->bcc, 'full_address' );
		}

		if ( ! empty( $this->reply_to ) ) {
			$headers[] = 'Reply-To: ' . self::iterate_objects_method_into_list( $this->reply_to, 'full_address' );
		}

		// In case it is an HTML email we just set the content type header properly
		// and let wp_mail and php mailer to handle plain text and other encoding
		// details.
		if ( $this->content_is_html() ) {
			$headers[] = 'Content-Type:text/html';
		}

		$attachments      = [];
		$duplicate_titles = [];

		foreach ( $this->attachments as $attachment ) {
			$key = $attachment->title();
			if ( $key === '' ) {
				$attachments[] = $attachment->path();
				continue;
			} elseif ( isset( $attachments[ $key ] ) ) {
				if ( ! isset( $duplicate_titles[ $key ] ) ) {
					\calmpress\logger\Controller::log_info_message(
						'Several different attachments were added with same title - "' . $key . '"',
						__FILE__,
						__LINE__
					);
					$duplicate_titles[ $key ] = true;
				}

				for ( $i = 2; ; $i++ ) {
					if ( ! isset( $attachments[ $key . ' (' . $i . ')' ] ) ) {
						$key = $key . ' (' . $i . ')';
						break;
					}
				}
			}
			$attachments[ $key ] = $attachment->path();
		}

		// Return path needs to be set directly at the phpmailer object.
		if ( $this->return_path !== null ) {
			$address = $this->return_path;
			add_action(
				'phpmailer_init',
				static function ( $phpmailer ) use ( $address ) {
					$phpmailer->Sender = $address;
				}
			);
		}

		// Sender AKA From is set by using WP filters.
		if ( $this->from !== null ) {
			$address = $this->from;
			add_filter(
				'wp_mail_from',
				static function ( $value ) use ( $address ) {
					return $address->address;
				}
			);
			add_filter(
				'wp_mail_from_name',
				static function ( $value ) use ( $address ) {
					return $address->name;
				}
			);
		}

		wp_mail( $to, $this->subject, $this->content, $headers, $attachments );
	}
}
