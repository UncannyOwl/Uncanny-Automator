<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Http;

use Uncanny_Automator\Core\Lib\AI\Http\Component\Endpoint;
use Uncanny_Automator\Core\Lib\AI\Http\Component\Headers;
use Uncanny_Automator\Core\Lib\AI\Http\Component\Body;

/**
 * Immutable HTTP request object.
 *
 * Combines endpoint, headers, and body into a complete AI request.
 * Built by the Payload builder.
 *
 * @since 5.6
 */
final class Request {

	/**
	 * @var Endpoint
	 */
	private $endpoint;
	/**
	 * @var Headers
	 */
	private $headers;
	/**
	 * @var Body
	 */
	private $body;

	/**
	 * Initialize request with components.
	 *
	 * @param Endpoint $endpoint API endpoint
	 * @param Headers  $headers  HTTP headers
	 * @param Body     $body     Request body
	 */
	public function __construct( Endpoint $endpoint, Headers $headers, Body $body ) {
		$this->endpoint = $endpoint;
		$this->headers  = $headers;
		$this->body     = $body;
	}

	/**
	 * Get endpoint component.
	 *
	 * @return Endpoint API endpoint
	 */
	public function get_endpoint(): Endpoint {
		return $this->endpoint;
	}

	/**
	 * Get headers component.
	 *
	 * @return Headers HTTP headers
	 */
	public function get_headers(): Headers {
		return $this->headers;
	}

	/**
	 * Get body component.
	 *
	 * @return Body Request body
	 */
	public function get_body(): Body {
		return $this->body;
	}
}
