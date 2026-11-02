<?php
/**
 * Observe native attachment creation for one in-process upload request.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Events;

use WP_REST_Request;
use WP_Error;

/** Core may create an attachment before a later metadata callback fails. */
final class Media_Upload_Events {

	/** Capture only the attachment inserted by this exact native request. */
	public static function capture( WP_REST_Request $request, callable $dispatch ): array {
		$attachment_id = 0;
		$observe       = static function ( $attachment, $candidate, $creating ) use ( $request, &$attachment_id ): void {
			if ( $candidate === $request && true === $creating && $attachment instanceof \WP_Post && 'attachment' === $attachment->post_type ) {
				$attachment_id = (int) $attachment->ID;
			}
		};
		add_action( 'rest_insert_attachment', $observe, 1, 3 );
		try {
			$response = $dispatch();
		} catch ( \Throwable $error ) {
			// Native hooks can throw after insertion. Keep the observed attachment
			// for recovery and never expose arbitrary exception messages to clients.
			$response = new WP_Error( 'upload_failed', 'WordPress could not complete this upload.', array( 'status' => 500 ) );
		} finally {
			remove_action( 'rest_insert_attachment', $observe, 1 );
		}
		return array(
			'response'      => $response,
			'attachment_id' => $attachment_id,
		);
	}
}
