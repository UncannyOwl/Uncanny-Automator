<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Default implementation of {@see Error_Bridge}.
 *
 * @since 7.4.0
 */
final class Automator_Error_Bridge implements Error_Bridge {

	/**
	 * @inheritDoc
	 */
	public function add_error( string $context, string $message, $data = null ): void {
		\Automator()->wp_error->add_error( $context, $message, $data );
	}

	/**
	 * @inheritDoc
	 */
	public function get_error_messages( string $context ): array {
		$result = \Automator()->wp_error->get_messages( $context );

		return is_array( $result ) ? $result : array();
	}

	/**
	 * @inheritDoc
	 */
	public function get_error_message( string $key ): string {
		return (string) \Automator()->error_message->get( $key );
	}
}
