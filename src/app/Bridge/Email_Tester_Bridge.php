<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Bridge;

/**
 * Seam over the legacy \Uncanny_Automator\Services\Email\Tester service
 * for node-test providers.
 */
interface Email_Tester_Bridge {

	/**
	 * Send a test email.
	 *
	 * @param array $args Tester args: to, subject, body, optional content-type / attachments.
	 * @return bool True when the mail was handed to the mailer.
	 * @throws \Exception When the tester rejects its configuration.
	 */
	public function send( array $args ): bool;
}
