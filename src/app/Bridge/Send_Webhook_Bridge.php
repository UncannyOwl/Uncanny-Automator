<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Bridge;

/**
 * Seam over the legacy `Automator()->send_webhook` facade
 * (Automator_Send_Webhook) for node-test providers.
 */
interface Send_Webhook_Bridge {

	/**
	 * The configured payload data type (json/xml/…).
	 *
	 * @param array $data Flat code => value field map.
	 * @return string
	 */
	public function get_data_type( array $data ): string;

	/**
	 * The configured webhook URL.
	 *
	 * @param array $data Flat field map.
	 * @return string
	 */
	public function get_url( array $data ): string;

	/**
	 * The outgoing request headers.
	 *
	 * @param array  $data      Flat field map.
	 * @param string $data_type Payload data type.
	 * @param int    $action_id Action post id.
	 * @return array
	 */
	public function get_headers( array $data, string $data_type, int $action_id ): array;

	/**
	 * The outgoing payload — preview string when $preview, else the request body.
	 *
	 * @param array  $data      Flat field map.
	 * @param string $data_type Payload data type.
	 * @param bool   $preview   Whether to build the pretty preview.
	 * @return mixed
	 */
	public function get_fields( array $data, string $data_type, bool $preview = false );

	/**
	 * The configured HTTP method.
	 *
	 * @param array $data Flat field map.
	 * @return string
	 */
	public function request_type( array $data ): string;

	/**
	 * Fire the webhook.
	 *
	 * @param string $url          Webhook URL.
	 * @param array  $args         wp_remote_* args (method/body/headers/timeout).
	 * @param string $request_type HTTP method.
	 * @return array|\WP_Error
	 */
	public function call_webhook( string $url, array $args, string $request_type );

	/**
	 * Response-token leafs (headers + body) for the action-tokens side effect.
	 *
	 * @param array|\WP_Error $response HTTP response.
	 * @return array
	 */
	public function collect_response_tokens( $response ): array;

	/**
	 * Whether the answer is a success as a run judges it: a 2xx whose body
	 * reports no error.
	 *
	 * @since 8.0.1
	 *
	 * @param array|\WP_Error $response HTTP response.
	 * @return bool
	 */
	public function is_successful_response( $response ): bool;
}
