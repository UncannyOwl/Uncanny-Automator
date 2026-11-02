<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Bridge;

use Uncanny_Automator\Automator_Send_Webhook;

/**
 * Send_Webhook_Bridge over the live `Automator()->send_webhook` facade.
 */
final class Automator_Send_Webhook_Bridge implements Send_Webhook_Bridge {

	/**
	 * The configured payload data type.
	 *
	 * @param array $data Flat field map.
	 * @return string
	 */
	public function get_data_type( array $data ): string {
		return (string) Automator()->send_webhook->get_data_type( $data );
	}

	/**
	 * The configured webhook URL.
	 *
	 * @param array $data Flat field map.
	 * @return string
	 */
	public function get_url( array $data ): string {
		return (string) Automator()->send_webhook->get_url( $data, false );
	}

	/**
	 * The outgoing request headers.
	 *
	 * @param array  $data      Flat field map.
	 * @param string $data_type Payload data type.
	 * @param int    $action_id Action post id.
	 * @return array
	 */
	public function get_headers( array $data, string $data_type, int $action_id ): array {
		return (array) Automator()->send_webhook->get_headers( $data, $data_type, $action_id );
	}

	/**
	 * The outgoing payload (preview string when $preview).
	 *
	 * @param array  $data      Flat field map.
	 * @param string $data_type Payload data type.
	 * @param bool   $preview   Whether to build the pretty preview.
	 * @return mixed
	 */
	public function get_fields( array $data, string $data_type, bool $preview = false ) {
		return Automator()->send_webhook->get_fields( $data, false, $data_type, array(), $preview );
	}

	/**
	 * The configured HTTP method.
	 *
	 * @param array $data Flat field map.
	 * @return string
	 */
	public function request_type( array $data ): string {
		return (string) Automator()->send_webhook->request_type( $data );
	}

	/**
	 * Fire the webhook.
	 *
	 * @param string $url          Webhook URL.
	 * @param array  $args         Request args.
	 * @param string $request_type HTTP method.
	 * @return array|\WP_Error
	 */
	public function call_webhook( string $url, array $args, string $request_type ) {
		return Automator_Send_Webhook::call_webhook( $url, $args, $request_type );
	}

	/**
	 * Response-token leafs for the action-tokens side effect.
	 *
	 * @param array|\WP_Error $response HTTP response.
	 * @return array
	 */
	public function collect_response_tokens( $response ): array {
		if ( is_wp_error( $response ) ) {
			return array();
		}

		$header_leafs   = Automator_Send_Webhook::parse_headers( wp_remote_retrieve_headers( $response ) );
		$response_leafs = Automator_Send_Webhook::get_leafs( json_decode( wp_remote_retrieve_body( $response ), true ) );

		return array_merge( (array) $header_leafs, (array) $response_leafs );
	}
}
