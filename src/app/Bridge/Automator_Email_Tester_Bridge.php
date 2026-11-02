<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Bridge;

use Uncanny_Automator\Services\Email\Tester;

/**
 * Email_Tester_Bridge over the live core email tester.
 */
final class Automator_Email_Tester_Bridge implements Email_Tester_Bridge {

	/**
	 * Send a test email through the core Tester.
	 *
	 * @param array $args Tester args.
	 * @return bool True when the mailer accepted the message.
	 * @throws \Exception When the tester rejects its configuration.
	 */
	public function send( array $args ): bool {
		$result = ( new Tester( $args ) )->send( true );

		return is_array( $result ) && ! empty( $result['success'] );
	}
}
