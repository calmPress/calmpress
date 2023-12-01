<?php
/**
 * A trait to send email with mutators that can abort the.
 *
 * @package calmPress
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * Used to provide a unique exception type which can be used by mutators to signal
 * that email should not be sent at all.
 *
 * @since 1.0.0
 */
trait Email_To_User {
    use \calmpress\observer\Static_Mutation_By_Ref_Observer_Collection;

	/**
	 * The email to send.
	 *
	 * since 1.0.0
	 */
	public readonly Email $email;

	/**
	 * The user to which the email is sent.
	 * 
	 * @since 1.0.0
	 */
	public readonly \WP_User $user;

    /**
	 * Send the email.
	 *
	 * Mutation done in two steps, first mutating the generated emails with the
	 * mutators registered with this class, after that using the Email class to
	 * send the email which will trigger the mutators registered at that class.
     * 
     * Mutators can abort sending by throwing a Abort_Send_Exception exception. 
	 * 
	 * @since 1.0.0
	 */
	public function send(): void {
        try {
            // Let mutators change whatever needed or abort.
            self::mutate_by_ref( $this );
        } catch ( Abort_Send_Exception $e ) {
            return;
        }

		// And send...
		$this->email->send();
	}
}
