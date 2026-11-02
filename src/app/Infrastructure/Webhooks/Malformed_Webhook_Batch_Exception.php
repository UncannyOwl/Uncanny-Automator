<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Webhooks;

/**
 * Thrown when a batched platform callback violates the batch contract itself —
 * an event that is not an array, or one missing the `job_id` / `payload_type`
 * the inbox is keyed on. The batch is validated in full BEFORE anything is
 * stored, so a throw here means nothing was inserted.
 *
 * This is a NON-retryable, caller-side error: retrying an identical malformed
 * batch can only fail again. The router answers
 * `422 {"error":{"type":"PAYLOAD_REJECTED","description":<the message>}}` —
 * the one final refusal of callback delivery contract §4: the platform reads
 * `type` and dead-letters the batch instead of retrying it; `description` is
 * for an operator.
 *
 * @since 8.0.0
 */
class Malformed_Webhook_Batch_Exception extends \Exception implements Provides_Http_Status, Provides_Http_Body {

	/**
	 * @return int
	 */
	public function get_http_status(): int {

		return 422;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_http_body(): array {

		return array(
			'error' => array(
				'type'        => 'PAYLOAD_REJECTED',
				'description' => $this->getMessage(),
			),
		);
	}
}
