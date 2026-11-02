<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol;

use WP_REST_Response;

/**
 * Request-scoped Server-Sent Events response.
 */
final class Mcp_Sse_Response extends WP_REST_Response {

	/** @var array<int,array<string,mixed>> */
	private array $messages;

	/**
	 * @param array<int,array<string,mixed>> $messages Ordered JSON-RPC messages.
	 */
	public function __construct( array $messages ) {
		$this->messages = $messages;
		parent::__construct(
			null,
			200,
			array(
				'Content-Type'    => 'text/event-stream',
				'X-Automator-MCP' => '1',
			)
		);
	}

	/**
	 * Return wire-ready SSE without event IDs or resumption metadata.
	 */
	public function get_sse_payload(): string {
		$payload = '';
		foreach ( $this->messages as $message ) {
			$payload .= 'data: ' . wp_json_encode( $message ) . "\n\n";
		}

		return $payload;
	}
}
